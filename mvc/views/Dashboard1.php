<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Debug: Se não tem tenant/filial, usar valores padrão
if (!$tenant) {
    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = 1");
    if ($tenant) {
        $session->setTenant($tenant);
    }
}

if (!$filial) {
    $filial = $db->fetch("SELECT * FROM filiais WHERE id = 1");
    if ($filial) {
        $session->setFilial($filial);
    }
}

// Get mesas data
$mesas = [];
if ($tenant && $filial) {
    $mesas = $db->fetchAll(
        "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id_mesa::integer",
        [$tenant['id'], $filial['id']]
    );
}

// Get pedidos ativos
$pedidos = [];
if ($tenant && $filial) {
    $pedidos = $db->fetchAll(
        "SELECT p.*, m.id_mesa, m.nome as mesa_nome 
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.status NOT IN ('Finalizado', 'Cancelado')
         ORDER BY p.data DESC, p.hora_pedido DESC",
        [$tenant['id'], $filial['id']]
    );
}

// Get stats
$stats = [
    'total_pedidos_hoje' => 0,
    'valor_total_hoje' => 0,
    'pedidos_pendentes' => 0,
    'mesas_ocupadas' => 0
];

if ($tenant && $filial) {
    $stats = [
        'total_pedidos_hoje' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE', [$tenant['id'], $filial['id']]),
        'valor_total_hoje' => $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total FROM pedido WHERE tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE",
            [$tenant['id'], $filial['id']]
        )['total'] ?? 0,
        'pedidos_pendentes' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND status = ?', [$tenant['id'], $filial['id'], 'Pendente']),
        'mesas_ocupadas' => $db->count('mesas', 'tenant_id = ? AND filial_id = ? AND status = ?', [$tenant['id'], $filial['id'], '2'])
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
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
        
        .mesa-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .mesa-card.livre {
            border-color: #28a745;
        }
        
        .mesa-card.ocupada {
            border-color: #dc3545;
        }
        
        .mesa-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .mesa-status.livre {
            background-color: #28a745;
        }
        
        .mesa-status.ocupada {
            background-color: #dc3545;
        }
        
        .mesa-numero {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }
        
        .mesa-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .action-btn {
            background: linear-gradient(45deg, var(--primary-color), #6c757d);
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .action-btn i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .modal-mesa {
            border-radius: 15px;
        }
        
        .modal-mesa .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-mesa .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
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
        
        /* Estilos para a modal da mesa */
        .mesa-details {
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
        .modal-lg {
            max-width: 900px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="<?php echo $router->url('dashboard'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>">
                            <i class="fas fa-plus-circle"></i>
                            Novo Pedido
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>">
                            <i class="fas fa-list"></i>
                            Pedidos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('mesas'); ?>">
                            <i class="fas fa-table"></i>
                            Mesas
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>">
                            <i class="fas fa-motorcycle"></i>
                            Delivery
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>">
                            <i class="fas fa-box"></i>
                            Produtos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>">
                            <i class="fas fa-warehouse"></i>
                            Estoque
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>">
                            <i class="fas fa-chart-line"></i>
                            Financeiro
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>">
                            <i class="fas fa-chart-bar"></i>
                            Relatórios
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>">
                            <i class="fas fa-users"></i>
                            Clientes
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>">
                            <i class="fas fa-cog"></i>
                            Configurações
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </h2>
                            <p class="text-muted mb-0">Bem-vindo, <?php echo htmlspecialchars($user['login'] ?? 'admin'); ?>!</p>
                        </div>
                        <div class="col-md-6">
                            <div class="user-info justify-content-end">
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo htmlspecialchars($filial['nome'] ?? 'Filial Principal'); ?></div>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                                </div>
                                <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #28a745, #20c997);">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['total_pedidos_hoje']; ?></div>
                            <div class="stats-label">Pedidos Hoje</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #007bff, #6610f2);">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stats-number">R$ <?php echo number_format($stats['valor_total_hoje'], 2, ',', '.'); ?></div>
                            <div class="stats-label">Faturamento Hoje</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['pedidos_pendentes']; ?></div>
                            <div class="stats-label">Pendentes</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #dc3545, #e83e8c);">
                                <i class="fas fa-table"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['mesas_ocupadas']; ?></div>
                            <div class="stats-label">Mesas Ocupadas</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt me-2"></i>
                        Ações Rápidas
                    </h5>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo $router->url('gerar_pedido'); ?>" class="action-btn">
                                <i class="fas fa-plus-circle"></i>
                                Novo Pedido
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo $router->url('pedidos'); ?>" class="action-btn">
                                <i class="fas fa-list"></i>
                                Ver Pedidos
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo $router->url('gerenciar_produtos'); ?>" class="action-btn">
                                <i class="fas fa-box"></i>
                                Produtos
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo $router->url('relatorios'); ?>" class="action-btn">
                                <i class="fas fa-chart-bar"></i>
                                Relatórios
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mesas Grid -->
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Status das Mesas
                            </h5>
                            <button class="btn btn-outline-primary btn-sm" onclick="atualizarMesas()">
                                <i class="fas fa-sync-alt me-1"></i>
                                Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row" id="mesasGrid">
                    <?php foreach ($mesas as $mesa): ?>
                        <?php
                        $pedidoMesa = null;
                        foreach ($pedidos as $pedido) {
                            if ($pedido['idmesa'] == $mesa['id_mesa']) {
                                $pedidoMesa = $pedido;
                                break;
                            }
                        }
                        $status = $pedidoMesa ? 'ocupada' : 'livre';
                        ?>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                            <div class="mesa-card <?php echo $status; ?>" onclick="verMesa(<?php echo $mesa['id_mesa']; ?>, <?php echo $mesa['id_mesa']; ?>)">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="mesa-status <?php echo $status; ?>"></span>
                                    <span class="mesa-numero"><?php echo $mesa['id_mesa']; ?></span>
                                </div>
                                <div class="mesa-info">
                                    <?php if ($pedidoMesa): ?>
                                        <div class="fw-bold text-danger">Ocupada</div>
                                        <div>Pedido #<?php echo $pedidoMesa['idpedido']; ?></div>
                                        <div>R$ <?php echo number_format($pedidoMesa['valor_total'], 2, ',', '.'); ?></div>
                                        <div class="small"><?php echo $pedidoMesa['hora_pedido']; ?></div>
                                    <?php else: ?>
                                        <div class="fw-bold text-success">Livre</div>
                                        <div>Disponível</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mesa -->
    <div class="modal fade" id="modalMesa" tabindex="-1">
        <div class="modal-dialog modal-lg modal-mesa">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-table me-2"></i>
                        Mesa <span id="mesaNumero"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalMesaBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function verMesa(mesaId, mesaNumero) {
            document.getElementById('mesaNumero').textContent = mesaNumero;
            
            // Load mesa content via AJAX
            fetch(`index.php?action=dashboard_ajax&ver_mesa=1&mesa_id=${mesaId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalMesaBody').innerHTML = data.html;
                        
                        // Executar o JavaScript inline após inserir o HTML
                        const scripts = document.getElementById('modalMesaBody').getElementsByTagName('script');
                        for (let i = 0; i < scripts.length; i++) {
                            eval(scripts[i].innerHTML);
                        }
                        
                        new bootstrap.Modal(document.getElementById('modalMesa')).show();
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Erro', 'Erro ao carregar dados da mesa', 'error');
                });
        }

        function atualizarMesas() {
            location.reload();
        }

        function fazerPedido(mesaId) {
            window.location.href = `<?php echo $router->url('gerar_pedido'); ?>&mesa=${mesaId}`;
        }

        function verPedido(pedidoId) {
            window.location.href = `<?php echo $router->url('pedidos'); ?>&pedido=${pedidoId}`;
        }

        function fecharMesa(mesaId) {
            Swal.fire({
                title: 'Fechar Mesa',
                text: 'Deseja realmente fechar todos os pedidos desta mesa?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, fechar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buscar pedidos ativos da mesa
                    fetch('index.php?action=pedidos&t=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `fechar_mesa=1&mesa_id=${mesaId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Mesa fechada com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao fechar mesa', 'error');
                    });
                }
            });
        }

        function editarPedido(pedidoId) {
            window.location.href = `index.php?view=gerar_pedido&editar=${pedidoId}`;
        }

        function fecharPedido(pedidoId) {
            let html = '<div class="mb-3">';
            html += '<label class="form-label">Forma de Pagamento</label>';
            html += '<select class="form-select" id="formaPagamento" required>';
            html += '<option value="">Selecione a forma de pagamento</option>';
            html += '<option value="Dinheiro">Dinheiro</option>';
            html += '<option value="Cartão de Débito">Cartão de Débito</option>';
            html += '<option value="Cartão de Crédito">Cartão de Crédito</option>';
            html += '<option value="PIX">PIX</option>';
            html += '<option value="Vale Refeição">Vale Refeição</option>';
            html += '</select>';
            html += '</div>';
            
            html += '<div class="mb-3">';
            html += '<label class="form-label">Troco para (se dinheiro)</label>';
            html += '<input type="number" class="form-control" id="trocoPara" step="0.01" min="0" placeholder="0,00">';
            html += '</div>';
            
            html += '<div class="mb-3">';
            html += '<label class="form-label">Observações do Fechamento</label>';
            html += '<textarea class="form-control" id="observacaoFechamento" rows="2" placeholder="Observações adicionais..."></textarea>';
            html += '</div>';

            Swal.fire({
                title: 'Fechar Pedido',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Fechar Pedido',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const trocoPara = document.getElementById('trocoPara').value;
                    const observacaoFechamento = document.getElementById('observacaoFechamento').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Selecione a forma de pagamento');
                        return false;
                    }
                    
                    return { formaPagamento, trocoPara, observacaoFechamento };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { formaPagamento, trocoPara, observacaoFechamento } = result.value;
                    
                    fetch('index.php?action=pedidos&t=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `fechar_pedido=1&pedido_id=${pedidoId}&forma_pagamento=${encodeURIComponent(formaPagamento)}&troco_para=${trocoPara}&observacao_fechamento=${encodeURIComponent(observacaoFechamento)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Pedido fechado com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao fechar pedido', 'error');
                    });
                }
            });
        }

        function excluirPedido(pedidoId) {
            Swal.fire({
                title: 'Excluir Pedido',
                text: 'Deseja realmente excluir este pedido?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('index.php?action=pedidos&t=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `excluir_pedido=1&pedido_id=${pedidoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Pedido excluído com sucesso!', 'success');
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

        function atualizarStatusRapido(pedidoId, novoStatus) {
            console.log('=== TESTE ATUALIZAR STATUS ===');
            console.log('Pedido ID:', pedidoId);
            console.log('Novo Status:', novoStatus);
            
            // Teste simples primeiro
            alert('Função chamada! Pedido: ' + pedidoId + ', Status: ' + novoStatus);
            
            fetch('index.php?action=pedidos&t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `atualizar_status=1&pedido_id=${pedidoId}&status=${novoStatus}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    Swal.fire('Sucesso', 'Status atualizado com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao atualizar status', 'error');
            });
        }

        function salvarObservacao(pedidoId) {
            const observacao = document.getElementById('observacaoPedido').value;
            
            fetch('index.php?action=pedidos&t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `atualizar_observacao=1&pedido_id=${pedidoId}&observacao=${encodeURIComponent(observacao)}`
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
                Swal.fire('Atenção', 'Quantidade deve ser maior que zero', 'warning');
                return;
            }

            fetch('index.php?action=pedidos&t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `alterar_quantidade=1&pedido_id=${pedidoId}&item_id=${itemId}&quantidade=${novaQuantidade}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Quantidade atualizada com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1000);
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
                    fetch('index.php?action=pedidos&t=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `remover_item=1&pedido_id=${pedidoId}&item_id=${itemId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Item removido com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1000);
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
            let html = '<div class="mb-3">';
            html += '<label class="form-label">Tipo de Pedido</label>';
            html += '<div class="form-check">';
            html += '<input class="form-check-input" type="radio" name="tipoPedido" id="delivery" value="delivery"' + (mesaAtual === '999' || mesaAtual === 999 ? ' checked' : '') + '>';
            html += '<label class="form-check-label" for="delivery">Delivery</label>';
            html += '</div>';
            html += '<div class="form-check">';
            html += '<input class="form-check-input" type="radio" name="tipoPedido" id="mesa" value="mesa"' + (mesaAtual !== '999' && mesaAtual !== 999 ? ' checked' : '') + '>';
            html += '<label class="form-check-label" for="mesa">Mesa</label>';
            html += '</div>';
            html += '</div>';

            html += '<div id="mesasContainer" style="display: none;">';
            html += '<label class="form-label">Selecionar Mesa(s)</label>';
            mesas.forEach(mesa => {
                html += '<div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" name="mesas" id="mesa_' + mesa.id_mesa + '" value="' + mesa.id_mesa + '"' + (mesa.id_mesa == mesaAtual || mesa.id_mesa === mesaAtual ? ' checked' : '') + '>';
                html += '<label class="form-check-label" for="mesa_' + mesa.id_mesa + '">' + mesa.nome + '</label>';
                html += '</div>';
            });
            html += '</div>';

            Swal.fire({
                title: 'Editar Mesa',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const tipoPedido = document.querySelector('input[name="tipoPedido"]:checked').value;
                    let mesaId = '';
                    
                    if (tipoPedido === 'delivery') {
                        mesaId = '999';
                    } else {
                        const mesasSelecionadas = Array.from(document.querySelectorAll('input[name="mesas"]:checked')).map(cb => cb.value);
                        if (mesasSelecionadas.length === 0) {
                            Swal.showValidationMessage('Selecione pelo menos uma mesa');
                            return false;
                        }
                        mesaId = mesasSelecionadas.join(',');
                    }
                    
                    return { tipoPedido, mesaId };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { mesaId } = result.value;
                    
                    fetch('index.php?action=pedidos&t=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `atualizar_mesa=1&pedido_id=${pedidoId}&mesa_id=${mesaId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Mesa atualizada com sucesso!', 'success');
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

            // Mostrar/ocultar container de mesas baseado no tipo selecionado
            document.querySelectorAll('input[name="tipoPedido"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const mesasContainer = document.getElementById('mesasContainer');
                    if (this.value === 'mesa') {
                        mesasContainer.style.display = 'block';
                    } else {
                        mesasContainer.style.display = 'none';
                    }
                });
            });

            // Mostrar container se mesa estiver selecionada
            if (mesaAtual !== '999' && mesaAtual !== 999) {
                document.getElementById('mesasContainer').style.display = 'block';
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarMesas();
        }, 30000);

        // Update time every minute
        setInterval(() => {
            const now = new Date();
            const timeString = now.toLocaleString('pt-BR');
            document.querySelector('.user-info .text-end small').textContent = timeString;
        }, 60000);
    </script>
</body>
</html>
