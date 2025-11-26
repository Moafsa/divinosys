<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Ensure tenant and filial context
$context = \System\TenantHelper::ensureTenantContext();
$tenant = $context['tenant'];
$filial = $context['filial'];
$user = $session->getUser();

// Debug: Log session data
error_log("Pedidos: User data: " . json_encode($user));
error_log("Pedidos: Tenant data: " . json_encode($tenant));
error_log("Pedidos: Filial data: " . json_encode($filial));

// Get tenant and filial from user session
if (!$tenant && $user) {
    $tenantId = $user['tenant_id'] ?? null;
    if ($tenantId) {
        $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        if ($tenant) {
            $session->setTenant($tenant);
        }
    }
}

if (!$filial && $user) {
    $filialId = $user['filial_id'] ?? null;
    if ($filialId) {
        $filial = $db->fetch("SELECT * FROM filiais WHERE id = ?", [$filialId]);
        if ($filial) {
            $session->setFilial($filial);
        }
    }
}

// If no filial but we have tenant, use tenant as default filial
if (!$filial && $tenant) {
    $filial = [
        'id' => $tenant['id'],
        'tenant_id' => $tenant['id'],
        'nome' => $tenant['nome'],
        'endereco' => $tenant['endereco'],
        'telefone' => $tenant['telefone'],
        'email' => $tenant['email'],
        'cnpj' => $tenant['cnpj'],
        'logo_url' => $tenant['logo_url'],
        'status' => $tenant['status']
    ];
    $session->setFilial($filial);
    error_log("Pedidos: Using tenant as default filial for user {$user['id']}");
}

if (!$tenant || !$filial) {
    error_log("Pedidos: User {$user['id']} has no valid tenant/filial context");
    header('Location: index.php?view=login');
    exit;
}

// Get pedidos data
$pedidos = [];
if ($tenant && $filial) {
    $pedidos = $db->fetchAll(
        "SELECT p.*, m.id_mesa, m.nome as mesa_nome, u.login as usuario_nome
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.data >= CURRENT_DATE - INTERVAL '7 days'
         AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado')
         ORDER BY p.hora_pedido DESC",
        [$tenant['id'], $filial['id']]
    );
}

// Check if NFe is enabled in plan
$planoRecursos = [];
$nfeHabilitado = false;
if ($tenant && isset($tenant['plano_id'])) {
    $plano = $db->fetch(
        "SELECT recursos FROM planos WHERE id = ?",
        [$tenant['plano_id']]
    );
    if ($plano && !empty($plano['recursos'])) {
        $planoRecursos = is_string($plano['recursos']) 
            ? json_decode($plano['recursos'], true) 
            : $plano['recursos'];
        $nfeHabilitado = isset($planoRecursos['emissao_nfe']) && $planoRecursos['emissao_nfe'] === true;
    }
}

// Group pedidos by status
$pedidos_por_status = [
    'Pendente' => [],
    'Em Preparo' => [],
    'Pronto' => [],
    'Entregue' => [],
    'Saiu para Entrega' => [],
    'Cancelado' => []
];

foreach ($pedidos as $pedido) {
    $status = $pedido['status'] ?? 'Pendente';
    if (isset($pedidos_por_status[$status])) {
        $pedidos_por_status[$status][] = $pedido;
    }
}

// Get stats for today only
$pedidos_hoje = array_filter($pedidos, function($pedido) {
    return $pedido['data'] === date('Y-m-d');
});

$pedidos_por_status_hoje = [
    'Pendente' => [],
    'Em Preparo' => [],
    'Pronto' => [],
    'Entregue' => [],
    'Saiu para Entrega' => [],
    'Cancelado' => []
];

foreach ($pedidos_hoje as $pedido) {
    $status = $pedido['status'] ?? 'Pendente';
    if (isset($pedidos_por_status_hoje[$status])) {
        $pedidos_por_status_hoje[$status][] = $pedido;
    }
}

$stats = [
    'total_pedidos' => count($pedidos_hoje),
    'valor_total' => array_sum(array_column($pedidos_hoje, 'valor_total')),
    'pendentes' => count($pedidos_por_status_hoje['Pendente']),
    'em_preparo' => count($pedidos_por_status_hoje['Em Preparo'])
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), #6c757d);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.5rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .kanban-column {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            min-height: 600px;
        }
        
        .column-header {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 600;
            color: white;
        }
        
        .column-header.pendente { background: linear-gradient(45deg, #ffc107, #fd7e14); }
        .column-header.em_preparo { background: linear-gradient(45deg, #28a745, #20c997); }
        .column-header.pronto { background: linear-gradient(45deg, #28a745, #20c997); }
        .column-header.saiu_para_entrega { background: linear-gradient(45deg, #17a2b8, #20c997); }
        .column-header.entregue { background: linear-gradient(45deg, #007bff, #6610f2); }
        .column-header.cancelado { background: linear-gradient(45deg, #dc3545, #e83e8c); }
        
        .pedido-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .pedido-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .pedido-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .pedido-numero {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .pedido-hora {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .pedido-mesa {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .pedido-valor {
            font-weight: 700;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .pedido-cliente {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            text-align: center;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            opacity: 0.9;
        }
        
        /* Estilos para a modal do pedido */
        .pedido-details {
            padding: 1rem;
        }
        
        .status-card, .total-card, .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-card h6 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .itens-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .itens-section h6 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        .input-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .input-group-sm .form-control {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .modal-lg {
            max-width: 900px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/components/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content expanded">
                <div class="content-wrapper">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Pedidos
                            </h2>
                            <p class="text-muted mb-0">Gerenciamento de pedidos em tempo real</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-primary" onclick="atualizarPedidos()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                                <a href="<?php echo $router->url('gerar_pedido'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Pedido
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total_pedidos']; ?></div>
                            <div class="stats-label">Total Hoje</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number">R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></div>
                            <div class="stats-label">Faturamento</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['pendentes']; ?></div>
                            <div class="stats-label">Pendentes</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['em_preparo']; ?></div>
                            <div class="stats-label">Em Preparo</div>
                        </div>
                    </div>
                </div>

                <!-- Kanban Board -->
                <div class="row">
                    <?php foreach ($pedidos_por_status as $status => $pedidos_status): ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="kanban-column">
                                <div class="column-header <?php echo strtolower(str_replace(' ', '_', $status)); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo $status; ?></span>
                                        <span class="badge bg-light text-dark"><?php echo count($pedidos_status); ?></span>
                                    </div>
                                </div>
                                
                                <div class="pedidos-list">
                                    <?php foreach ($pedidos_status as $pedido): ?>
                                        <div class="pedido-card" onclick="verPedido(<?php echo $pedido['idpedido']; ?>)">
                                            <div class="pedido-header">
                                                <div class="pedido-numero">#<?php echo $pedido['idpedido']; ?></div>
                                                <div class="pedido-hora"><?php echo $pedido['hora_pedido']; ?></div>
                                            </div>
                                            
                                            <?php if ($pedido['idmesa']): ?>
                                                <div class="pedido-mesa">
                                                    <i class="fas fa-table me-1"></i>
                                                    Mesa <?php echo $pedido['id_mesa']; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="pedido-mesa">
                                                    <i class="fas fa-motorcycle me-1"></i>
                                                    Delivery
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="pedido-valor">
                                                R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?>
                                            </div>
                                            
                                            <?php if ($pedido['cliente']): ?>
                                                <div class="pedido-cliente">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($pedido['cliente']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); atualizarStatus(<?php echo $pedido['idpedido']; ?>, '<?php echo $status; ?>')">
                                                    <i class="fas fa-arrow-right"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); excluirPedido(<?php echo $pedido['idpedido']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pedido -->
    <div class="modal fade" id="modalPedido" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>
                        Pedido #<span id="pedidoNumero"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalPedidoBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function verPedido(pedidoId) {
            document.getElementById('pedidoNumero').textContent = pedidoId;
            
            // Load pedido content via AJAX
            fetch(`mvc/ajax/pedidos.php?buscar_pedido=1&pedido_id=${pedidoId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Dados recebidos:', data);
                    if (data.success) {
                        // Generate HTML content from pedido data
                        const pedido = data.pedido;
                        let html = `
                            <div class="pedido-details">
                                <!-- Header com ações -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h5 class="mb-1">Pedido #${pedido.idpedido}</h5>
                                        <small class="text-muted">${pedido.data} às ${pedido.hora_pedido}</small>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="editarPedido(${pedido.idpedido})">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="imprimirPedido(${pedido.idpedido})" title="Imprimir Pedido">
                                            <i class="fas fa-print"></i> Imprimir
                                        </button>
                                        <?php if ($nfeHabilitado): ?>
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="emitirNotaFiscal(${pedido.idpedido})" title="Emitir Nota Fiscal">
                                            <i class="fas fa-file-invoice"></i> Emitir NF
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="excluirPedido(${pedido.idpedido})">
                                            <i class="fas fa-trash"></i> <?php echo $_SESSION['user_type'] === 'cozinha' ? 'Cancelar' : 'Excluir'; ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Status e Total -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="status-card">
                                            <label class="form-label">Status Atual</label>
                                            <select class="form-select" id="statusSelect" onchange="atualizarStatusRapido(${pedido.idpedido}, this.value)">
                                                <option value="Pendente" ${pedido.status === 'Pendente' ? 'selected' : ''}>Pendente</option>
                                                <option value="Em Preparo" ${pedido.status === 'Em Preparo' ? 'selected' : ''}>Em Preparo</option>
                                                <option value="Pronto" ${pedido.status === 'Pronto' ? 'selected' : ''}>Pronto</option>
                                                <option value="Entregue" ${pedido.status === 'Entregue' ? 'selected' : ''}>Entregue</option>
                                                <option value="Saiu para Entrega" ${pedido.status === 'Saiu para Entrega' ? 'selected' : ''}>Saiu para Entrega</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="total-card">
                                            <label class="form-label">Valor Total</label>
                                            <div class="h4 text-success mb-0">R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informações do Cliente/Mesa -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6><i class="fas fa-user me-2"></i>Cliente</h6>
                                            <p class="mb-1"><strong>Nome:</strong> ${pedido.cliente || 'N/A'}</p>
                                            <p class="mb-0"><strong>Usuário:</strong> ${pedido.usuario_nome || 'N/A'}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6><i class="fas fa-table me-2"></i>Local</h6>
                                            <p class="mb-1"><strong>Mesa:</strong> ${pedido.mesa_nome || 'Delivery'}</p>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarMesa(${pedido.idpedido}, '${pedido.idmesa}')">
                                                <i class="fas fa-edit"></i> Editar Mesa
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informações de Pagamento (se pagamento online) -->
                                ${pedido.asaas_payment_id ? `
                                <div class="mb-3">
                                    <div class="info-card border border-info">
                                        <h6><i class="fas fa-credit-card me-2"></i>Pagamento Online (Asaas)</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Status do Pagamento:</strong> 
                                                    <span class="badge bg-${pedido.status_pagamento === 'quitado' ? 'success' : pedido.status_pagamento === 'pendente' ? 'warning' : 'danger'}">
                                                        ${pedido.status_pagamento === 'quitado' ? 'Pago' : pedido.status_pagamento === 'pendente' ? 'Pendente' : pedido.status_pagamento || 'Pendente'}
                                                    </span>
                                                </p>
                                                <p class="mb-1"><strong>ID do Pagamento:</strong> <small>${pedido.asaas_payment_id}</small></p>
                                                ${pedido.asaas_payment_url ? `
                                                    <p class="mb-0">
                                                        <a href="${pedido.asaas_payment_url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt"></i> Ver Fatura no Asaas
                                                        </a>
                                                    </p>
                                                ` : ''}
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Valor Pago:</strong> R$ ${parseFloat(pedido.valor_pago || 0).toFixed(2).replace('.', ',')}</p>
                                                <p class="mb-1"><strong>Saldo Devedor:</strong> R$ ${parseFloat(pedido.saldo_devedor || pedido.valor_total).toFixed(2).replace('.', ',')}</p>
                                                <button type="button" class="btn btn-sm btn-outline-info mt-1" onclick="consultarStatusPagamento(${pedido.idpedido})" title="Consultar status atualizado no Asaas">
                                                    <i class="fas fa-sync"></i> Atualizar Status
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Observações -->
                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" id="observacaoPedido" rows="2" placeholder="Observações do pedido...">${pedido.observacao || ''}</textarea>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="salvarObservacao(${pedido.idpedido})">
                                        <i class="fas fa-save"></i> Salvar Observação
                                    </button>
                                </div>
                        `;
                        
                        if (pedido.itens && pedido.itens.length > 0) {
                            html += `
                                <!-- Itens do Pedido -->
                                <div class="itens-section">
                                    <h6><i class="fas fa-list me-2"></i>Itens do Pedido</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Produto</th>
                                                    <th width="80">Qtd</th>
                                                    <th width="100">Valor Unit.</th>
                                                    <th width="100">Total</th>
                                                    <th width="80">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                            `;
                            
                            pedido.itens.forEach((item, index) => {
                                html += `
                                    <tr>
                                        <td>
                                            <strong>${item.nome_produto || 'Produto'}</strong>
                                            ${item.observacao ? `<br><small class="text-muted">${item.observacao}</small>` : ''}
                                            ${item.ingredientes_com && Array.isArray(item.ingredientes_com) && item.ingredientes_com.length > 0 ? `
                                                <br><small class="text-success">
                                                    <i class="fas fa-plus"></i> ${item.ingredientes_com.join(', ')}
                                                </small>
                                            ` : ''}
                                            ${item.ingredientes_sem && Array.isArray(item.ingredientes_sem) && item.ingredientes_sem.length > 0 ? `
                                                <br><small class="text-danger">
                                                    <i class="fas fa-minus"></i> ${item.ingredientes_sem.join(', ')}
                                                </small>
                                            ` : ''}
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <button class="btn btn-outline-secondary" type="button" onclick="alterarQuantidade(${pedido.idpedido}, ${item.id}, ${item.quantidade - 1})">-</button>
                                                <input type="number" class="form-control text-center" value="${item.quantidade}" min="1" onchange="alterarQuantidade(${pedido.idpedido}, ${item.id}, this.value)">
                                                <button class="btn btn-outline-secondary" type="button" onclick="alterarQuantidade(${pedido.idpedido}, ${item.id}, ${parseInt(item.quantidade) + 1})">+</button>
                                            </div>
                                        </td>
                                        <td>R$ ${parseFloat(item.valor_unitario).toFixed(2).replace('.', ',')}</td>
                                        <td><strong>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</strong></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removerItem(${pedido.idpedido}, ${item.id})" title="Remover item">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            
                            html += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `;
                        }
                        
                        html += `</div>`;
                        
                        document.getElementById('modalPedidoBody').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('modalPedido')).show();
                    } else {
                        console.error('Erro na resposta:', data);
                        Swal.fire('Erro', data.message || 'Erro ao carregar dados do pedido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido', 'error');
                });
        }

        function atualizarStatus(pedidoId, statusAtual) {
            const statuses = ['Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega'];
            const currentIndex = statuses.indexOf(statusAtual);
            
            if (currentIndex < statuses.length - 1) {
                const novoStatus = statuses[currentIndex + 1];
                atualizarStatusRapido(pedidoId, novoStatus);
            }
        }
        
        function atualizarStatusRapido(pedidoId, novoStatus) {
            console.log('Atualizando status:', pedidoId, novoStatus);
            
            // Fazer chamada AJAX para atualizar status sem confirmação
            const formData = new URLSearchParams();
            formData.append('action', 'atualizar_status');
            formData.append('pedido_id', pedidoId);
            formData.append('status', novoStatus);
            
            fetch('index.php?action=pedidos', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Fechar modal e recarregar página
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalPedido'));
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(() => location.reload(), 500);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao atualizar status do pedido', 'error');
            });
        }

        function emitirNotaFiscal(pedidoId) {
            Swal.fire({
                title: 'Emitir Nota Fiscal',
                html: `
                    <p>Deseja emitir a nota fiscal para o pedido #${pedidoId}?</p>
                    <div class="form-check text-start mt-3">
                        <input class="form-check-input" type="checkbox" id="retainIss" checked>
                        <label class="form-check-label" for="retainIss">
                            Reter ISS
                        </label>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, emitir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#17a2b8',
                preConfirm: () => {
                    return {
                        pedidoId: pedidoId,
                        retainIss: document.getElementById('retainIss').checked
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = {
                        tenant_id: <?php echo $tenant['id']; ?>,
                        filial_id: <?php echo $filial['id'] ?? 'null'; ?>,
                        pedido_id: pedidoId,
                        retain_iss: result.value.retainIss
                    };
                    
                    Swal.fire({
                        title: 'Processando...',
                        text: 'Criando nota fiscal no Asaas',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('mvc/ajax/invoices.php?action=createInvoiceFromOrder', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Nota Fiscal Criada!',
                                    html: `
                                        <p>A nota fiscal foi agendada com sucesso no Asaas.</p>
                                        <p class="text-muted">Acesse <strong>Configurações > Asaas > Ver Notas Fiscais</strong> para emitir a nota.</p>
                                    `,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erro',
                                    text: data.error || 'Erro ao criar nota fiscal',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('Erro', 'Erro ao processar requisição', 'error');
                        });
                }
            });
        }

        function excluirPedido(pedidoId) {
            const userType = <?php echo json_encode($_SESSION['user_type'] ?? 'admin'); ?>;
            const actionText = userType === 'cozinha' ? 'cancelar' : 'excluir';
            const titleText = userType === 'cozinha' ? 'Cancelar Pedido' : 'Excluir Pedido';
            const confirmText = userType === 'cozinha' ? 'Sim, cancelar' : 'Sim, excluir';
            
            Swal.fire({
                title: titleText,
                text: `Deseja realmente ${actionText} o pedido #${pedidoId}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Fazer chamada AJAX para excluir pedido
                    const formData = new URLSearchParams();
                    formData.append('action', 'excluir_pedido');
                    formData.append('pedido_id', pedidoId);
                    
                    fetch('index.php?action=pedidos', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao excluir pedido', 'error');
                    });
                }
            });
        }

        function atualizarPedidos() {
            location.reload();
        }
        
        function consultarStatusPagamento(pedidoId) {
            // Show loading
            Swal.fire({
                title: 'Consultando...',
                text: 'Buscando status atualizado do pagamento no Asaas',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(`mvc/ajax/pedidos.php?action=consultar_pagamento_asaas&pedido_id=${pedidoId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Atualizado',
                        text: `Status do pagamento: ${data.status_pagamento}`,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reload pedido details
                        verPedido(pedidoId);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao consultar status do pagamento'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao consultar status do pagamento'
                });
            });
        }
        
        function salvarObservacao(pedidoId) {
            const observacao = document.getElementById('observacaoPedido').value;
            
            const formData = new URLSearchParams();
            formData.append('action', 'atualizar_observacao');
            formData.append('pedido_id', pedidoId);
            formData.append('observacao', observacao);
            
            fetch('index.php?action=pedidos', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Observação salva com sucesso!', 'success');
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao salvar observação', 'error');
            });
        }
        
        function alterarQuantidade(pedidoId, itemId, novaQuantidade) {
            if (novaQuantidade < 1) {
                novaQuantidade = 1;
            }
            
            const formData = new URLSearchParams();
            formData.append('action', 'alterar_quantidade');
            formData.append('pedido_id', pedidoId);
            formData.append('item_id', itemId);
            formData.append('quantidade', novaQuantidade);
            
            fetch('index.php?action=pedidos', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recarregar modal com dados atualizados
                    verPedido(pedidoId);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao alterar quantidade', 'error');
            });
        }
        
        function removerItem(pedidoId, itemId) {
            Swal.fire({
                title: 'Remover Item',
                text: 'Deseja realmente remover este item do pedido?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new URLSearchParams();
                    formData.append('action', 'remover_item');
                    formData.append('pedido_id', pedidoId);
                    formData.append('item_id', itemId);
                    
                    fetch('index.php?action=pedidos', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Item removido com sucesso!', 'success');
                            // Recarregar modal com dados atualizados
                            verPedido(pedidoId);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao remover item', 'error');
                    });
                }
            });
        }
        
        function editarPedido(pedidoId) {
            // Redirecionar para página de edição de pedido
            window.location.href = `index.php?view=gerar_pedido&editar=${pedidoId}`;
        }
        
        function editarMesa(pedidoId, mesaAtual) {
            // Buscar mesas disponíveis
            fetch('index.php?action=mesas&buscar_mesas=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalEditarMesa(pedidoId, mesaAtual, data.mesas);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao carregar mesas', 'error');
            });
        }
        
        function mostrarModalEditarMesa(pedidoId, mesaAtual, mesas) {
            let html = `
                <div class="mb-3">
                    <label class="form-label">Selecionar Mesa(s)</label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoMesa" id="delivery" value="delivery" ${mesaAtual === '999' ? 'checked' : ''}>
                                <label class="form-check-label" for="delivery">
                                    <i class="fas fa-motorcycle me-2"></i>Delivery
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoMesa" id="mesa" value="mesa" ${mesaAtual !== '999' ? 'checked' : ''}>
                                <label class="form-check-label" for="mesa">
                                    <i class="fas fa-table me-2"></i>Mesa
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="mesasContainer" style="display: none;">
                    <label class="form-label">Mesas Disponíveis</label>
                    <div class="row">
            `;
            
            mesas.forEach(mesa => {
                html += `
                    <div class="col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mesas[]" id="mesa_${mesa.id_mesa}" value="${mesa.id_mesa}" ${mesaAtual === mesa.id_mesa ? 'checked' : ''}>
                            <label class="form-check-label" for="mesa_${mesa.id_mesa}">
                                Mesa ${mesa.id_mesa} (${mesa.status === '1' ? 'Livre' : 'Ocupada'})
                            </label>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: 'Editar Mesa do Pedido',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    // Mostrar/esconder mesas baseado no tipo selecionado
                    document.querySelectorAll('input[name="tipoMesa"]').forEach(radio => {
                        radio.addEventListener('change', function() {
                            const mesasContainer = document.getElementById('mesasContainer');
                            if (this.value === 'mesa') {
                                mesasContainer.style.display = 'block';
                            } else {
                                mesasContainer.style.display = 'none';
                            }
                        });
                    });
                    
                    // Trigger change event para mostrar/esconder inicialmente
                    const selectedTipo = document.querySelector('input[name="tipoMesa"]:checked');
                    if (selectedTipo) {
                        selectedTipo.dispatchEvent(new Event('change'));
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const tipoMesa = document.querySelector('input[name="tipoMesa"]:checked').value;
                    let novaMesa = '';
                    
                    if (tipoMesa === 'delivery') {
                        novaMesa = '999';
                    } else {
                        const mesasSelecionadas = Array.from(document.querySelectorAll('input[name="mesas[]"]:checked')).map(cb => cb.value);
                        if (mesasSelecionadas.length === 0) {
                            Swal.fire('Erro', 'Selecione pelo menos uma mesa', 'error');
                            return;
                        }
                        novaMesa = mesasSelecionadas.join(',');
                    }
                    
                    // Atualizar mesa do pedido
                    const formData = new URLSearchParams();
                    formData.append('action', 'atualizar_mesa');
                    formData.append('pedido_id', pedidoId);
                    formData.append('mesa_id', novaMesa);
                    
                    fetch('index.php?action=pedidos', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            // Fechar modal e recarregar página
                            bootstrap.Modal.getInstance(document.getElementById('modalPedido')).hide();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao atualizar mesa', 'error');
                    });
                }
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarPedidos();
        }, 30000);

        function imprimirPedido(pedidoId) {
            console.log('Imprimindo pedido:', pedidoId);
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Fetch pedido data
            fetch(`mvc/ajax/pedidos.php?buscar_pedido=1&pedido_id=${pedidoId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    const itens = data.itens || [];
                    
                    // Generate print HTML using the same format as gerar_pedido.php
                    let printHtml = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Cupom Fiscal - Pedido #${pedido.idpedido}</title>
                            <style>
                                body { font-family: 'Courier New', monospace; font-size: 11px; margin: 0; padding: 8px; }
                                .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
                                .empresa { font-weight: bold; font-size: 13px; }
                                .endereco { font-size: 9px; }
                                .pedido-info { margin: 8px 0; font-size: 10px; }
                                .item { margin: 3px 0; }
                                .item-nome { font-weight: bold; font-size: 11px; }
                                .item-detalhes { font-size: 10px; margin-left: 8px; }
                                .modificacoes { margin-left: 15px; font-size: 10px; }
                                .adicionado { color: green; }
                                .removido { color: red; }
                                .total { border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; font-weight: bold; font-size: 12px; }
                                .footer { text-align: center; margin-top: 15px; font-size: 9px; }
                                @media print { body { margin: 0; padding: 5px; font-size: 10px; } }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div class="empresa">DIVINO LANCHES</div>
                                <div class="endereco">Rua das Flores, 123 - Centro</div>
                                <div class="endereco">Tel: (11) 99999-9999</div>
                            </div>
                            
                            <div class="pedido-info">
                                <strong>PEDIDO #${pedido.idpedido}</strong><br>
                                Data/Hora: ${pedido.data} ${pedido.hora_pedido}<br>
                                ${pedido.idmesa && pedido.idmesa !== '999' ? `Mesa: ${pedido.idmesa}` : 'DELIVERY'}<br>
                                ${pedido.cliente ? `Cliente: ${pedido.cliente}` : ''}
                                ${pedido.telefone_cliente ? `<br>Telefone: ${pedido.telefone_cliente}` : ''}
                                ${pedido.usuario_nome ? `<br>Atendente: ${pedido.usuario_nome}` : ''}
                            </div>
                            
                            <div class="itens">
                                <strong>ITENS DO PEDIDO:</strong><br>`;
                    
                    itens.forEach(item => {
                        printHtml += `
                            <div class="item">
                                <div class="item-nome">${item.quantidade}x ${item.nome_produto || 'Produto'}</div>
                                <div class="item-detalhes">R$ ${parseFloat(item.valor_unitario).toFixed(2).replace('.', ',')}</div>`;
                        
                        if (item.ingredientes_com && item.ingredientes_com.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            item.ingredientes_com.forEach(ing => {
                                printHtml += `<div class="adicionado">+ ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (item.ingredientes_sem && item.ingredientes_sem.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            item.ingredientes_sem.forEach(ing => {
                                printHtml += `<div class="removido">- ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (item.observacao) {
                            printHtml += `<div class="item-detalhes">Obs: ${item.observacao}</div>`;
                        }
                        
                        printHtml += `</div>`;
                    });
                    
                    printHtml += `
                            </div>
                            
                            <div class="total">
                                <strong>TOTAL: R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</strong>
                            </div>
                            
                            ${pedido.observacao ? `<div class="pedido-info"><strong>Observação:</strong> ${pedido.observacao}</div>` : ''}
                            
                            <div class="footer">
                                Obrigado pela preferência!<br>
                                Volte sempre!<br>
                                Impresso em: ${new Date().toLocaleString('pt-BR')}
                            </div>
                        </body>
                        </html>`;
                    
                    // Write content to print window
                    printWindow.document.write(printHtml);
                    printWindow.document.close();
                    
                    // Print after content loads
                    printWindow.onload = function() {
                        printWindow.print();
                        printWindow.close();
                    };
                    
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido para impressão', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao imprimir pedido:', error);
                Swal.fire('Erro', 'Erro ao imprimir pedido', 'error');
            });
        }
    </script>
    
    <!-- Include sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
