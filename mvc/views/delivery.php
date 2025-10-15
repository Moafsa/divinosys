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

// Get delivery pedidos
$pedidos = [];
if ($tenant && $filial) {
    $pedidos = $db->fetchAll(
        "SELECT p.*, u.login as usuario_nome
         FROM pedido p 
         LEFT JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.delivery = true
         AND p.data >= CURRENT_DATE - INTERVAL '7 days'
         AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado')
         ORDER BY p.hora_pedido DESC",
        [$tenant['id'], $filial['id']]
    );
}

// Group by status
$pedidos_por_status = [
    'Pendente' => [],
    'Em Preparo' => [],
    'Pronto' => [],
    'Saiu para Entrega' => [],
    'Entregue' => [],
    'Cancelado' => []
];

foreach ($pedidos as $pedido) {
    $status = $pedido['status'] ?? 'Pendente';
    if (isset($pedidos_por_status[$status])) {
        $pedidos_por_status[$status][] = $pedido;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
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
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
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
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar collapsed" id="sidebar">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="sidebar-content">
                    <div class="sidebar-brand">
                        <div class="brand-icon text-white">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>" data-tooltip="Dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>" data-tooltip="Pedidos">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('delivery'); ?>" data-tooltip="Delivery">
                            <i class="fas fa-motorcycle"></i>
                            <span>Delivery</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>" data-tooltip="Produtos">
                            <i class="fas fa-box"></i>
                            <span>Produtos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
                            <i class="fas fa-chart-line"></i>
                            <span>Financeiro</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>" data-tooltip="Clientes">
                            <i class="fas fa-users"></i>
                            <span>Clientes</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content expanded">
                <div class="content-wrapper">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-motorcycle me-2"></i>
                                Delivery
                            </h2>
                            <p class="text-muted mb-0">Gerenciamento de pedidos de delivery</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-primary" onclick="atualizarDelivery()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                                <a href="<?php echo $router->url('gerar_pedido'); ?>&mesa=Delivery" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Delivery
                                </a>
                            </div>
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
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="fw-bold text-primary">#<?php echo $pedido['idpedido']; ?></div>
                                                <div class="text-muted small"><?php echo $pedido['hora_pedido']; ?></div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <i class="fas fa-motorcycle me-1 text-warning"></i>
                                                <span class="fw-bold">Delivery</span>
                                            </div>
                                            
                                            <div class="h5 text-success mb-2">
                                                R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?>
                                            </div>
                                            
                                            <?php if ($pedido['cliente']): ?>
                                                <div class="text-muted small mb-2">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($pedido['cliente']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <?php if ($status === 'Entregue'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); fecharPedidoDelivery(<?php echo $pedido['idpedido']; ?>)" title="Fechar Pedido">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="event.stopPropagation(); imprimirPedidoDelivery(<?php echo $pedido['idpedido']; ?>)" title="Imprimir Pedido">
                                                    <i class="fas fa-print"></i>
                                                </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Partial Payment System -->
    <script src="assets/js/pagamentos-parciais.js"></script>
    
    <script>
        function verPedido(pedidoId) {
            console.log('Buscando pedido:', pedidoId);
            // Buscar dados do pedido via AJAX
            fetch('mvc/ajax/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_pedido&pedido_id=${pedidoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    const itens = data.itens || [];
                    console.log('Dados recebidos:', data);
                    console.log('Pedido:', pedido);
                    console.log('Itens:', itens);
                    
                    // Construir HTML do popup
                    let itensHtml = '';
                    itens.forEach(item => {
                        let ingredientesHtml = '';
                        if (item.ingredientes_com && item.ingredientes_com.length > 0) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-success">+ ' + item.ingredientes_com.join(', ') + '</small></div>';
                        }
                        if (item.ingredientes_sem && item.ingredientes_sem.length > 0) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-danger">- ' + item.ingredientes_sem.join(', ') + '</small></div>';
                        }
                        if (item.observacao) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-info">Obs: ' + item.observacao + '</small></div>';
                        }
                        
                        itensHtml += `
                            <tr>
                                <td>${item.quantidade}x</td>
                                <td>${item.nome_produto || 'Produto não encontrado'}</td>
                                <td>R$ ${parseFloat(item.valor_unitario || 0).toFixed(2)}</td>
                                <td>R$ ${parseFloat(item.valor_total || 0).toFixed(2)}</td>
                            </tr>
                            ${ingredientesHtml ? '<tr><td colspan="4">' + ingredientesHtml + '</td></tr>' : ''}
                        `;
                    });
                    
                    const popupHtml = `
                        <div class="modal fade" id="modalPedido" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Pedido #${pedido.idpedido}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Cliente:</strong> ${pedido.cliente || 'Cliente Mesa'}<br>
                                                <strong>Data:</strong> ${pedido.data}<br>
                                                <strong>Hora:</strong> ${pedido.hora_pedido}
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Status:</strong> <span class="badge bg-primary">${pedido.status}</span><br>
                                                <strong>Valor Total:</strong> R$ ${parseFloat(pedido.valor_total).toFixed(2)}
                                            </div>
                                        </div>
                                        
                                        <h6>Itens do Pedido:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Qtd</th>
                                                        <th>Produto</th>
                                                        <th>Preço Unit.</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${itensHtml}
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        ${pedido.observacao ? '<div class="mt-3"><strong>Observação:</strong> ' + pedido.observacao + '</div>' : ''}
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-success" onclick="imprimirPedidoDelivery(${pedido.idpedido})">
                                            <i class="fas fa-print me-1"></i>
                                            Imprimir
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="editarPedido(${pedido.idpedido})">
                                            <i class="fas fa-edit me-1"></i>
                                            Editar Pedido
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remover modal existente se houver
                    const existingModal = document.getElementById('modalPedido');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Adicionar novo modal
                    document.body.insertAdjacentHTML('beforeend', popupHtml);
                    
                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('modalPedido'));
                    modal.show();
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do pedido', 'error');
            });
        }

        function editarPedido(pedidoId) {
            // Fechar modal atual
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalPedido'));
            if (modal) {
                modal.hide();
            }
            
            // Redirecionar para página de edição
            window.location.href = `<?php echo $router->url('gerar_pedido'); ?>&editar=${pedidoId}`;
        }
        
        function atualizarStatus(pedidoId, statusAtual) {
            const statuses = ['Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega', 'Entregue'];
            const currentIndex = statuses.indexOf(statusAtual);
            
            if (currentIndex < statuses.length - 1) {
                const novoStatus = statuses[currentIndex + 1];
                
                // Atualizar status diretamente sem confirmação
                fetch('mvc/ajax/pedidos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=atualizar_status&pedido_id=${pedidoId}&status=${novoStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recarregar a página para mostrar o novo status
                        location.reload();
                    } else {
                        console.error('Erro ao atualizar status:', data.message);
                        // Mostrar erro apenas no console, sem popup
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
            }
        }

        function excluirPedido(pedidoId) {
            Swal.fire({
                title: 'Excluir Pedido',
                text: `Deseja realmente excluir o pedido #${pedidoId}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/pedidos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=excluir_pedido&pedido_id=${pedidoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Pedido excluído com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao excluir pedido', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao excluir pedido', 'error');
                    });
                }
            });
        }

        function fecharPedidoDelivery(pedidoId) {
            // Redirecionar para a página de fechar pedido (mesmo modelo da mesa)
            window.location.href = `index.php?view=fechar_pedido&pedido_id=${pedidoId}`;
        }

        function atualizarDelivery() {
            location.reload();
        }

        function imprimirPedidoDelivery(pedidoId) {
            console.log('Imprimindo pedido delivery:', pedidoId);
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Fetch pedido data
            fetch('mvc/ajax/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=buscar_pedido&pedido_id=${pedidoId}`
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
                            <title>Cupom Fiscal - Pedido Delivery #${pedido.idpedido}</title>
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
                                .delivery-info { background-color: #f0f8f0; padding: 8px; margin: 8px 0; border: 1px dashed #000; font-size: 10px; }
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
                                <strong>DELIVERY</strong><br>
                                ${pedido.cliente ? `Cliente: ${pedido.cliente}` : ''}
                                ${pedido.telefone_cliente ? `<br>Telefone: ${pedido.telefone_cliente}` : ''}
                                ${pedido.usuario_nome ? `<br>Atendente: ${pedido.usuario_nome}` : ''}
                            </div>
                            
                            <div class="delivery-info">
                                <strong>🚚 ENTREGA</strong><br>
                                ${pedido.cliente || 'Cliente Delivery'}
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
                                <strong>🚚 DELIVERY</strong><br>
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

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarDelivery();
        }, 30000);
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
