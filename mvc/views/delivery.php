<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Get delivery pedidos
$pedidos = [];
if ($tenant && $filial) {
    $pedidos = $db->fetchAll(
        "SELECT p.*, u.login as usuario_nome
         FROM pedido p 
         LEFT JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.delivery = true
         AND p.data = CURRENT_DATE
         ORDER BY p.hora_pedido DESC",
        [$tenant['id'], $filial['id']]
    );
}

// Group by status
$pedidos_por_status = [
    'Pendente' => [],
    'Em Preparo' => [],
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
        .column-header.preparo { background: linear-gradient(45deg, #17a2b8, #20c997); }
        .column-header.entrega { background: linear-gradient(45deg, #6f42c1, #e83e8c); }
        .column-header.entregue { background: linear-gradient(45deg, #28a745, #20c997); }
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
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>">
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
                        <a class="nav-link active" href="<?php echo $router->url('delivery'); ?>">
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
                                <a href="<?php echo $router->url('gerar_pedido'); ?>" class="btn btn-primary">
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
    <script>
        function verPedido(pedidoId) {
            window.location.href = `<?php echo $router->url('pedidos'); ?>&pedido=${pedidoId}`;
        }

        function atualizarStatus(pedidoId, statusAtual) {
            const statuses = ['Pendente', 'Em Preparo', 'Saiu para Entrega', 'Entregue'];
            const currentIndex = statuses.indexOf(statusAtual);
            
            if (currentIndex < statuses.length - 1) {
                const novoStatus = statuses[currentIndex + 1];
                
                Swal.fire({
                    title: 'Atualizar Status',
                    text: `Mover pedido #${pedidoId} para "${novoStatus}"?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, atualizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire('Sucesso', 'Status atualizado com sucesso!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    }
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
                    Swal.fire('Sucesso', 'Pedido excluído com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        function atualizarDelivery() {
            location.reload();
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarDelivery();
        }, 30000);
    </script>
</body>
</html>
