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
        "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY numero::integer",
        [$tenant['id'], $filial['id']]
    );
}

// Get pedido ativos grouped by mesa - only truly active orders
$pedido = [];
if ($tenant && $filial) {
    // Get only orders with valid status (remove time restriction to see all active orders)
    $pedido = $db->fetchAll(
        "SELECT p.*, m.numero as mesa_numero, m.id as mesa_id,
                COUNT(p.idpedido) OVER (PARTITION BY p.idmesa) as total_pedido_mesa
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
         ORDER BY p.idmesa, p.created_at ASC",
        [$tenant['id'], $filial['id']]
    );
}

// Group pedido by mesa - use idmesa as key for uniqueness
$pedidoPorMesa = [];
foreach ($pedido as $pedido) {
    $mesaId = $pedido['idmesa']; // Use idmesa for unique identification
    if (!isset($pedidoPorMesa[$mesaId])) {
        $pedidoPorMesa[$mesaId] = [
            'mesa' => [
                'id_mesa' => $pedido['mesa_numero'],
                'nome' => 'Mesa ' . $pedido['mesa_numero']
            ],
            'pedido' => [],
            'total_pedido' => $pedido['total_pedido_mesa'],
            'valor_total' => 0
        ];
    }
    $pedidoPorMesa[$mesaId]['pedido'][] = $pedido;
    $pedidoPorMesa[$mesaId]['valor_total'] += $pedido['valor_total'];
}

// Get stats
$stats = [
    'total_pedido_hoje' => 0,
    'valor_total_hoje' => 0,
    'pedido_pendentes' => 0,
    'mesas_ocupadas' => 0,
    'delivery_pendentes' => 0,
    'faturamento_delivery' => 0
];

if ($tenant && $filial) {
    // After cleanup, all stats should be 0 or based on truly active orders
    $stats = [
        'total_pedido_hoje' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE AND status IN (?, ?, ?, ?)', [$tenant['id'], $filial['id'], 'Pendente', 'Preparando', 'Pronto', 'Entregue']),
        'valor_total_hoje' => $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total FROM pedido WHERE tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')",
            [$tenant['id'], $filial['id']]
        )['total'] ?? 0,
        'pedido_pendentes' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND status = ?', [$tenant['id'], $filial['id'], 'Pendente']),
        'mesas_ocupadas' => $db->fetch(
            "SELECT COUNT(DISTINCT p.idmesa) as count 
             FROM pedido p 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
             AND p.delivery = false",
            [$tenant['id'], $filial['id']]
        )['count'] ?? 0,
        'delivery_pendentes' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND delivery = true AND status IN (?, ?)', [$tenant['id'], $filial['id'], 'Pendente', 'Preparando']),
        'faturamento_delivery' => $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total 
             FROM pedido 
             WHERE tenant_id = ? AND filial_id = ? 
             AND delivery = true 
             AND data = CURRENT_DATE 
             AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')",
            [$tenant['id'], $filial['id']]
        )['total'] ?? 0
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
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        
        .sidebar.collapsed {
            width: 70px !important;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link {
            text-align: center;
            padding: 0.75rem 0.5rem;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .sidebar.collapsed .sidebar-brand {
            text-align: center;
        }
        
        .sidebar.collapsed .sidebar-brand h4 {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-brand .brand-icon {
            display: block !important;
            font-size: 1.5rem;
        }
        
        .sidebar-brand .brand-icon {
            display: none;
        }
        
        .main-content {
            margin-left: 250px;
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: flex;
            }
        }
        
        @media (min-width: 769px) {
            .sidebar-toggle {
                display: none;
            }
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
            display: inline-block;
            margin-left: 0.5rem;
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
        
        /* Delivery Cards */
        .delivery-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .delivery-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .delivery-id {
            font-weight: 600;
            color: #495057;
            font-size: 1.1rem;
        }
        
        .delivery-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .delivery-time, .delivery-value {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .delivery-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .delivery-actions .btn {
            flex: 1;
            font-size: 0.8rem;
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
                        <a class="nav-link active" href="<?php echo $router->url('dashboard'); ?>" data-tooltip="Dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>" data-tooltip="Novo Pedido">
                            <i class="fas fa-plus-circle"></i>
                            <span>Novo Pedido</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>" data-tooltip="Pedidos">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('mesas'); ?>" data-tooltip="Mesas">
                            <i class="fas fa-table"></i>
                            <span>Mesas</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>" data-tooltip="Delivery">
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
                            <div class="stats-number"><?php echo $stats['total_pedido_hoje']; ?></div>
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
                            <div class="stats-number"><?php echo $stats['pedido_pendentes']; ?></div>
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
                    
                    <!-- Delivery Cards -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #17a2b8, #20c997);">
                                <i class="fas fa-motorcycle"></i>
                            </div>
                            <div class="stats-number"><?php echo $stats['delivery_pendentes']; ?></div>
                            <div class="stats-label">Delivery Pendente</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon" style="background: linear-gradient(45deg, #6f42c1, #e83e8c);">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stats-number">R$ <?php echo number_format($stats['faturamento_delivery'], 2, ',', '.'); ?></div>
                            <div class="stats-label">Faturamento Delivery</div>
                        </div>
                    </div>
                </div>

                <!-- Pedidos de Delivery -->
                <?php if ($stats['delivery_pendentes'] > 0): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-motorcycle me-2"></i>
                                    Pedidos de Delivery Pendentes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    // Buscar pedido de delivery pendentes
                                    $pedidoDelivery = $db->fetchAll(
                                        "SELECT p.*, u.login as usuario_nome
                                         FROM pedido p 
                                         LEFT JOIN usuarios u ON p.usuario_id = u.id
                                         WHERE p.tenant_id = ? AND p.filial_id = ? 
                                         AND p.delivery = true 
                                         AND p.status IN ('Pendente', 'Em Preparo')
                                         ORDER BY p.hora_pedido ASC",
                                        [$tenant['id'], $filial['id']]
                                    );
                                    
                                    foreach ($pedidoDelivery as $pedido): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="delivery-card">
                                            <div class="delivery-header">
                                                <div class="delivery-id">#<?php echo $pedido['idpedido']; ?></div>
                                                <div class="delivery-status">
                                                    <span class="badge bg-<?php echo $pedido['status'] === 'Pendente' ? 'warning' : 'info'; ?>">
                                                        <?php echo $pedido['status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="delivery-info">
                                                <div class="delivery-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($pedido['hora_pedido'])); ?>
                                                </div>
                                                <div class="delivery-value">
                                                    <i class="fas fa-dollar-sign me-1"></i>
                                                    R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?>
                                                </div>
                                            </div>
                                            <div class="delivery-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="verPedidoDelivery(<?php echo $pedido['idpedido']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Ver Detalhes
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="atualizarStatusDelivery(<?php echo $pedido['idpedido']; ?>, '<?php echo $pedido['status']; ?>')">
                                                    <i class="fas fa-arrow-right me-1"></i>
                                                    Avançar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
                            <a href="<?php echo $router->url('pedido'); ?>" class="action-btn">
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
                        $pedidoMesa = isset($pedidoPorMesa[$mesa['id_mesa']]) ? $pedidoPorMesa[$mesa['id_mesa']] : null;
                        $status = $pedidoMesa ? 'ocupada' : 'livre';
                        ?>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                            <div class="mesa-card <?php echo $status; ?>" onclick="verMesa(<?php echo $mesa['id']; ?>, <?php echo $mesa['id_mesa']; ?>)">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="mesa-status <?php echo $status; ?>"></span>
                                    <span class="mesa-numero">Mesa <?php echo $mesa['numero']; ?></span>
                                </div>
                                <div class="mesa-info">
                                    <?php if ($pedidoMesa): ?>
                                        <div class="fw-bold text-danger">Ocupada</div>
                                        <?php if ($pedidoMesa['total_pedido'] > 1): ?>
                                            <div><?php echo $pedidoMesa['total_pedido']; ?> Pedidos</div>
                                        <?php else: ?>
                                            <div>Pedido #<?php echo $pedidoMesa['pedido'][0]['idpedido']; ?></div>
                                        <?php endif; ?>
                                        <div>R$ <?php echo number_format($pedidoMesa['valor_total'], 2, ',', '.'); ?></div>
                                        <div class="small"><?php echo $pedidoMesa['pedido'][0]['hora_pedido']; ?></div>
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
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            } else {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }
        
        // Mobile sidebar functionality
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleButton = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });
        function verMesa(mesaId, mesaNumero) {
            document.getElementById('mesaNumero').textContent = mesaNumero;
            
            // Load mesa content via AJAX - use mesaNumero which is the correct id_mesa value
            fetch(`index.php?action=mesa_multiplos_pedidos&ver_mesa=1&mesa_id=${mesaNumero}`, {
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
            window.location.href = `<?php echo $router->url('pedido'); ?>&pedido=${pedidoId}`;
        }
        
        function verPedidoDelivery(pedidoId) {
            console.log('Buscando pedido delivery:', pedidoId);
            // Buscar dados do pedido via AJAX
            fetch('mvc/ajax/pedido.php', {
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
                        <div class="modal fade" id="modalPedidoDelivery" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Pedido Delivery #${pedido.idpedido}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Cliente:</strong> ${pedido.cliente || 'Cliente Delivery'}<br>
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
                                        <button type="button" class="btn btn-primary" onclick="editarPedidoDelivery(${pedido.idpedido})">
                                            <i class="fas fa-edit me-1"></i>
                                            Editar Pedido
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="excluirPedidoDelivery(${pedido.idpedido})">
                                            <i class="fas fa-trash me-1"></i>
                                            Excluir Pedido
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remover modal existente se houver
                    const existingModal = document.getElementById('modalPedidoDelivery');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Adicionar novo modal
                    document.body.insertAdjacentHTML('beforeend', popupHtml);
                    
                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('modalPedidoDelivery'));
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
        
        function atualizarStatusDelivery(pedidoId, statusAtual) {
            const statuses = ['Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega', 'Entregue'];
            const currentIndex = statuses.indexOf(statusAtual);
            
            if (currentIndex < statuses.length - 1) {
                const novoStatus = statuses[currentIndex + 1];
                
                Swal.fire({
                    title: 'Confirmar Alteração',
                    text: `Alterar status do pedido #${pedidoId} de "${statusAtual}" para "${novoStatus}"?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, alterar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('mvc/ajax/pedido.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=atualizar_status&pedido_id=${pedidoId}&status=${novoStatus}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Sucesso', 'Status atualizado com sucesso!', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                Swal.fire('Erro', data.message || 'Erro ao atualizar status', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            Swal.fire('Erro', 'Erro ao atualizar status', 'error');
                        });
                    }
                });
            } else {
                Swal.fire('Info', 'Pedido já está no status final', 'info');
            }
        }
        
        function editarPedidoDelivery(pedidoId) {
            // Fechar modal atual
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalPedidoDelivery'));
            if (modal) {
                modal.hide();
            }
            
            // Redirecionar para página de edição
            window.location.href = `<?php echo $router->url('gerar_pedido'); ?>&editar=${pedidoId}`;
        }
        
        function excluirPedidoDelivery(pedidoId) {
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
                    fetch('mvc/ajax/pedido.php', {
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

        function fecharMesa(mesaId) {
            Swal.fire({
                title: 'Fechar Mesa',
                text: 'Deseja realmente fechar todos os pedido desta mesa?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, fechar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buscar pedido ativos da mesa
                    fetch('index.php?action=pedido&t=' + Date.now(), {
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
                    
                    fetch('index.php?action=pedido&t=' + Date.now(), {
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
                    fetch('index.php?action=pedido&t=' + Date.now(), {
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
            
            fetch('index.php?action=pedido&t=' + Date.now(), {
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
            
            fetch('index.php?action=pedido&t=' + Date.now(), {
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

            fetch('index.php?action=pedido&t=' + Date.now(), {
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
                    fetch('index.php?action=pedido&t=' + Date.now(), {
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
                html += '<input class="form-check-input" type="checkbox" name="mesas" id="mesa_' + mesa.numero + '" value="' + mesa.numero + '"' + (mesa.numero == mesaAtual || mesa.numero === mesaAtual ? ' checked' : '') + '>';
                html += '<label class="form-check-label" for="mesa_' + mesa.numero + '">' + mesa.numero + '</label>';
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
                    
                    fetch('index.php?action=pedido&t=' + Date.now(), {
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
        
        function fecharPedidoIndividual(pedidoId) {
            Swal.fire({
                title: 'Fechar Pedido Individual',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento">
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de Pessoas</label>
                        <input type="number" class="form-control" id="numeroPessoas" value="1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" rows="2"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Fechar Pedido',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const numeroPessoas = document.getElementById('numeroPessoas').value;
                    const observacao = document.getElementById('observacao').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    return { formaPagamento, numeroPessoas, observacao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('fechar_pedido', '1');
                    formData.append('pedido_id', pedidoId);
                    formData.append('forma_pagamento', result.value.formaPagamento);
                    formData.append('numero_pessoas', result.value.numeroPessoas);
                    formData.append('observacao', result.value.observacao);
                    
                    fetch('index.php?action=mesa_multiplos_pedidos', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso!', data.message, 'success');
                            // Recarregar o modal da mesa
                            const mesaId = document.getElementById('mesaNumero').textContent;
                            verMesa(mesaId, mesaId);
                        } else {
                            Swal.fire('Erro!', data.message, 'error');
                        }
                    });
                }
            });
        }
        
        function fecharMesaCompleta(mesaId) {
            Swal.fire({
                title: 'Fechar Mesa Completa',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento">
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de Pessoas</label>
                        <input type="number" class="form-control" id="numeroPessoas" value="1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" rows="2"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Fechar Mesa',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const numeroPessoas = document.getElementById('numeroPessoas').value;
                    const observacao = document.getElementById('observacao').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    return { formaPagamento, numeroPessoas, observacao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('fechar_mesa', '1');
                    formData.append('mesa_id', mesaId);
                    formData.append('forma_pagamento', result.value.formaPagamento);
                    formData.append('numero_pessoas', result.value.numeroPessoas);
                    formData.append('observacao', result.value.observacao);
                    
                    fetch('index.php?action=mesa_multiplos_pedidos', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso!', data.message, 'success');
                            // Fechar modal e recarregar página
                            bootstrap.Modal.getInstance(document.getElementById('modalMesa')).hide();
                            location.reload();
                        } else {
                            Swal.fire('Erro!', data.message, 'error');
                        }
                    });
                }
            });
        }
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
