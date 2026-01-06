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

// Get tenant and filial from user session
if (!$tenant && $user) {
    // Get tenant from user's tenant_id
    $tenantId = $user['tenant_id'] ?? null;
    if ($tenantId) {
        $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        if ($tenant) {
            $session->setTenant($tenant);
        }
    }
}

if (!$filial && $user) {
    // Get filial from user's filial_id
    $filialId = $user['filial_id'] ?? null;
    if ($filialId) {
        $filial = $db->fetch("SELECT * FROM filiais WHERE id = ?", [$filialId]);
        if ($filial) {
            $session->setFilial($filial);
        }
    }
}

// If still no tenant, this is an error - user should not access dashboard
if (!$tenant) {
    error_log("Dashboard: User {$user['id']} has no valid tenant context");
    // Redirect to login or show error
    header('Location: index.php?view=login');
    exit;
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
    error_log("Dashboard: Using tenant as default filial for user {$user['id']}");
}

// Get mesas data
$mesas = [];
if ($tenant) {
    if ($filial) {
        // Matriz user - get mesas for specific filial
        $mesas = $db->fetchAll(
            "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY CASE WHEN numero IS NOT NULL THEN numero ELSE id_mesa::integer END",
            [$tenant['id'], $filial['id']]
        );
    } else {
        // Filial user - get mesas for tenant (filial is the main branch)
        $mesas = $db->fetchAll(
            "SELECT * FROM mesas WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) ORDER BY CASE WHEN numero IS NOT NULL THEN numero ELSE id_mesa::integer END",
            [$tenant['id'], null]
        );
    }
}

// Get reservas data - pending and confirmed reservations
$reservas = [];
if ($tenant) {
    try {
        if ($filial) {
            $reservas = $db->fetchAll(
                "SELECT r.*, m.numero as mesa_numero, m.id_mesa, f.nome as filial_nome
                 FROM reservas r
                 LEFT JOIN mesas m ON r.mesa_id = m.id
                 LEFT JOIN filiais f ON r.filial_id = f.id
                 WHERE r.tenant_id = ? AND r.filial_id = ?
                 AND r.status IN ('pendente', 'confirmada')
                 AND (r.data_reserva >= CURRENT_DATE)
                 ORDER BY r.data_reserva ASC, r.hora_reserva ASC
                 LIMIT 20",
                [$tenant['id'], $filial['id']]
            );
        } else {
            $reservas = $db->fetchAll(
                "SELECT r.*, m.numero as mesa_numero, m.id_mesa, f.nome as filial_nome
                 FROM reservas r
                 LEFT JOIN mesas m ON r.mesa_id = m.id
                 LEFT JOIN filiais f ON r.filial_id = f.id
                 WHERE r.tenant_id = ?
                 AND r.status IN ('pendente', 'confirmada')
                 AND (r.data_reserva >= CURRENT_DATE)
                 ORDER BY r.data_reserva ASC, r.hora_reserva ASC
                 LIMIT 20",
                [$tenant['id']]
            );
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar reservas: " . $e->getMessage());
        $reservas = [];
    }
}

// Get pedido ativos grouped by mesa - only truly active orders
$pedido = [];
if ($tenant) {
    if ($filial) {
        // Matriz user - get orders for specific filial
        $pedido = $db->fetchAll(
            "SELECT p.*, m.numero as mesa_numero, m.id as mesa_id,
                    COUNT(p.idpedido) OVER (PARTITION BY p.idmesa) as total_pedido_mesa
             FROM pedido p 
             LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega')
             AND p.status_pagamento != 'quitado'
             ORDER BY p.idmesa, p.created_at ASC",
            [$tenant['id'], $filial['id']]
        );
    } else {
        // Filial user - get orders for tenant (filial is the main branch)
        $pedido = $db->fetchAll(
            "SELECT p.*, m.numero as mesa_numero, m.id as mesa_id,
                    COUNT(p.idpedido) OVER (PARTITION BY p.idmesa) as total_pedido_mesa
             FROM pedido p 
             LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND (m.filial_id = p.filial_id OR m.filial_id IS NULL)
             WHERE p.tenant_id = ? AND (p.filial_id = ? OR p.filial_id IS NULL)
             AND p.status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega')
             AND p.status_pagamento != 'quitado'
             ORDER BY p.idmesa, p.created_at ASC",
            [$tenant['id'], null]
        );
    }
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
        'total_pedido_hoje' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE AND status IN (?, ?, ?, ?, ?)', [$tenant['id'], $filial['id'], 'Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega']),
        'valor_total_hoje' => $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total FROM pedido WHERE tenant_id = ? AND filial_id = ? AND data = CURRENT_DATE AND status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega', 'Finalizado')",
            [$tenant['id'], $filial['id']]
        )['total'] ?? 0,
        'pedido_pendentes' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND status = ?', [$tenant['id'], $filial['id'], 'Pendente']),
        'mesas_ocupadas' => $db->fetch(
            "SELECT COUNT(DISTINCT p.idmesa) as count 
             FROM pedido p 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega')
             AND p.delivery = false
             AND p.status_pagamento != 'quitado'",
            [$tenant['id'], $filial['id']]
        )['count'] ?? 0,
        'delivery_pendentes' => $db->fetch(
            "SELECT COUNT(*) as count 
             FROM pedido 
             WHERE tenant_id = ? AND filial_id = ? 
             AND (
                 delivery = true 
                 OR (usuario_global_id IS NOT NULL AND tipo_entrega IS NOT NULL AND tipo_entrega IN ('pickup', 'delivery'))
             )
             AND status IN ('Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega')",
            [$tenant['id'], $filial['id']]
        )['count'] ?? 0,
        'faturamento_delivery' => $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total 
             FROM pedido 
             WHERE tenant_id = ? AND filial_id = ? 
             AND (
                 delivery = true 
                 OR (usuario_global_id IS NOT NULL AND tipo_entrega IS NOT NULL AND tipo_entrega IN ('pickup', 'delivery'))
             )
             AND data = CURRENT_DATE 
             AND status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega', 'Finalizado')",
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
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
            --online-order-color: <?php echo $tenant['cor_primaria'] ?? '#dc3545'; ?>;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        
        /* Mobile Menu Fix */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
            }
            
            #sidebar.show {
                transform: translateX(0) !important;
            }
            
            #sidebar-overlay {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(0,0,0,0.5) !important;
                z-index: 1049 !important;
                opacity: 0 !important;
                visibility: hidden !important;
                transition: all 0.3s ease !important;
            }
            
            #sidebar-overlay.show {
                opacity: 1 !important;
                visibility: visible !important;
            }
        }
        
        
        
        .main-content {
            padding: 2rem;
        }
        
        /* Fix para seção de reservas não ficar debaixo da sidebar */
        .main-content {
            position: relative;
            z-index: 1;
        }
        
        .main-content .row {
            position: relative;
            z-index: 1;
        }
        
        .main-content .card {
            position: relative;
            z-index: 1;
        }
        
        /* Garantir que cards de reserva não fiquem atrás da sidebar */
        #reservasContainer .card {
            position: relative;
            z-index: 1;
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
        
        /* Layout Desktop das Mesas - Simples e Funcional */
        .mesas-creative-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            padding: 10px 0;
            max-width: 100%;
        }
        
        .mesa-floating-card {
            background: white;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .mesa-floating-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .mesa-floating-card.livre {
            border-color: #28a745;
        }
        
        .mesa-floating-card.ocupada {
            border-color: #dc3545;
        }

        .mesa-floating-card.online-order {
            border-color: var(--online-order-color);
            background: var(--online-order-color) !important;
            color: white !important;
        }

        .mesa-floating-card.online-order .mesa-icon {
            color: white !important;
        }

        .mesa-floating-card.online-order .mesa-status-text {
            color: white !important;
        }

        .mesa-floating-card.online-order .mesa-number {
            color: white !important;
        }

        .mesa-floating-card.online-order .mesa-details {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .mesa-content {
            width: 100%;
        }
        
        .mesa-icon {
            font-size: 1rem;
            margin-bottom: 4px;
        }
        
        .mesa-floating-card.livre .mesa-icon {
            color: #28a745;
        }
        
        .mesa-floating-card.ocupada .mesa-icon {
            color: #dc3545;
        }
        
        .mesa-number {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        
        .mesa-status-text {
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .mesa-floating-card.livre .mesa-status-text {
            color: #28a745;
        }
        
        .mesa-floating-card.ocupada .mesa-status-text {
            color: #dc3545;
        }
        
        .mesa-details {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 4px;
            margin-top: 4px;
        }
        
        .mesa-pedido {
            font-size: 0.6rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }
        
        .mesa-valor {
            font-size: 0.7rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        
        .mesa-tempo {
            font-size: 0.6rem;
            color: #666;
        }
        
        .mesa-disponivel {
            font-size: 0.6rem;
            color: #28a745;
            font-weight: 500;
        }
        
        /* Responsivo - Manter mobile como estava */
        @media (max-width: 768px) {
            .mesas-creative-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px;
                width: 100%;
                padding: 0 2px;
            }
            
            .mesa-floating-card {
                width: 100% !important;
                margin: 0 !important;
                padding: 8px !important;
                min-height: 100px !important;
                max-width: 100% !important;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .mesa-icon {
                font-size: 1.2rem;
                margin-bottom: 4px;
            }
            
            .mesa-number {
                font-size: 1rem;
                margin-bottom: 2px;
            }
            
            .mesa-status-text {
                font-size: 0.7rem;
                margin-bottom: 4px;
            }
            
            .mesa-details {
                padding: 5px;
                margin-top: 5px;
            }
            
            .mesa-pedido, .mesa-valor {
                font-size: 0.7rem;
                margin-bottom: 2px;
            }
            
            .mesa-tempo {
                font-size: 0.6rem;
            }
            
            .mesa-disponivel {
                font-size: 0.7rem;
            }
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

        .delivery-card-online {
            border: 2px solid var(--online-order-color);
            background: var(--online-order-color) !important;
            color: white !important;
        }

        .delivery-card-online .delivery-id,
        .delivery-card-online .delivery-time,
        .delivery-card-online .delivery-value {
            color: white !important;
        }

        .delivery-card-online .btn {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: white !important;
        }

        .delivery-card-online:hover {
            box-shadow: 0 4px 16px rgba(220, 53, 69, 0.4);
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
            align-items: center;
        }
        
        .delivery-actions .btn {
            flex: 1;
            font-size: 0.8rem;
        }
        
        .delivery-actions .status-select {
            flex: 1;
            font-size: 0.8rem;
            cursor: pointer;
            border: 1px solid #28a745;
            color: #28a745;
        }
        
        .delivery-actions .status-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
    </style>
</head>
<body>
    <!-- Subscription Alert Component -->
    <?php include __DIR__ . '/components/subscription_alert.php'; ?>
    
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
                                    // Buscar pedido de delivery pendentes E pedidos online (cardápio online)
                                    $pedidoDelivery = $db->fetchAll(
                                        "SELECT p.*, u.login as usuario_nome
                                         FROM pedido p 
                                         LEFT JOIN usuarios u ON p.usuario_id = u.id
                                         WHERE p.tenant_id = ? AND p.filial_id = ? 
                                         AND (
                                             p.delivery = true 
                                             OR (p.usuario_global_id IS NOT NULL AND p.tipo_entrega IS NOT NULL AND p.tipo_entrega IN ('pickup', 'delivery'))
                                         )
                                         AND p.status NOT IN ('Entregue', 'Finalizado', 'Cancelado')
                                         AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado')
                                         ORDER BY p.hora_pedido ASC",
                                        [$tenant['id'], $filial['id']]
                                    );
                                    
                                    foreach ($pedidoDelivery as $pedido): 
                                        // Verificar se é pedido do cardápio online
                                        $isOnlineOrder = (
                                            !empty($pedido['usuario_global_id']) && 
                                            !empty($pedido['tipo_entrega']) && 
                                            in_array($pedido['tipo_entrega'], ['pickup', 'delivery'])
                                        );
                                        $deliveryCardClass = $isOnlineOrder ? 'delivery-card delivery-card-online' : 'delivery-card';
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="<?php echo $deliveryCardClass; ?>">
                                            <div class="delivery-header">
                                                <div>
                                                    <div class="delivery-id">#<?php echo $pedido['idpedido']; ?></div>
                                                    <?php if ($isOnlineOrder): ?>
                                                        <span class="badge bg-success" style="font-size: 0.7rem; margin-top: 2px;">
                                                            <i class="fas fa-shopping-cart me-1"></i>Cardápio Online
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="delivery-status">
                                                    <span class="badge bg-<?php 
                                                        switch($pedido['status']) {
                                                            case 'Pendente': echo 'warning'; break;
                                                            case 'Em Preparo': echo 'info'; break;
                                                            case 'Pronto': echo 'success'; break;
                                                            case 'Saiu para Entrega': echo 'primary'; break;
                                                            default: echo 'secondary'; break;
                                                        }
                                                    ?>">
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
                                                <button class="btn btn-sm btn-success" onclick="fecharPedidoDelivery(<?php echo $pedido['idpedido']; ?>)" title="Fechar Pedido">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    Fechar Pedido
                                                </button>
                                                <select class="form-select form-select-sm status-select" onchange="alterarStatusDelivery(<?php echo $pedido['idpedido']; ?>, this.value, '<?php echo $pedido['status']; ?>', this)">
                                                    <option value="">Alterar Status</option>
                                                    <option value="Pendente" <?php echo $pedido['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                    <option value="Em Preparo" <?php echo $pedido['status'] == 'Em Preparo' ? 'selected' : ''; ?>>Em Preparo</option>
                                                    <option value="Pronto" <?php echo $pedido['status'] == 'Pronto' ? 'selected' : ''; ?>>Pronto</option>
                                                    <option value="Saiu para Entrega" <?php echo $pedido['status'] == 'Saiu para Entrega' ? 'selected' : ''; ?>>Saiu para Entrega</option>
                                                    <option value="Entregue" <?php echo $pedido['status'] == 'Entregue' ? 'selected' : ''; ?>>Entregue</option>
                                                </select>
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

                <!-- Layout Criativo das Mesas -->
                <div class="mesas-creative-grid" id="mesasGrid">
                    <?php foreach ($mesas as $mesa): ?>
                        <?php
                        $pedidoMesa = isset($pedidoPorMesa[$mesa['id_mesa']]) ? $pedidoPorMesa[$mesa['id_mesa']] : null;
                        $status = $pedidoMesa ? 'ocupada' : 'livre';

                        // Verificar se é pedido do cardápio online
                        $isOnlineOrder = false;
                        if ($pedidoMesa && !empty($pedidoMesa['pedido'])) {
                            $pedido = $pedidoMesa['pedido'][0];
                            $isOnlineOrder = (
                                !empty($pedido['usuario_global_id']) && 
                                !empty($pedido['tipo_entrega']) && 
                                in_array($pedido['tipo_entrega'], ['pickup', 'delivery'])
                            );
                        }
                        $cardClass = $isOnlineOrder ? 'mesa-floating-card ocupada online-order' : 'mesa-floating-card ' . $status;
                        ?>
                        <div class="<?php echo $cardClass; ?>" onclick="verMesa(<?php echo $mesa['id']; ?>, <?php echo $mesa['id_mesa']; ?>)">
                            <div class="mesa-glow-effect"></div>
                            <div class="mesa-content">
                                <div class="mesa-icon">
                                    <?php if ($status == 'livre'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="mesa-number"><?php echo $mesa['id_mesa']; ?></div>
                                <div class="mesa-status-text"><?php echo ucfirst($status); ?></div>
                                <?php if ($pedidoMesa): ?>
                                    <div class="mesa-details">
                                        <div class="mesa-pedido">#<?php echo $pedidoMesa['pedido'][0]['idpedido']; ?></div>
                                        <div class="mesa-valor">R$ <?php echo number_format($pedidoMesa['valor_total'], 2, ',', '.'); ?></div>
                                        <div class="mesa-tempo"><?php echo $pedidoMesa['pedido'][0]['hora_pedido']; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="mesa-disponivel">
                                        <i class="fas fa-coffee"></i>
                                        <span>Pronta para uso</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservas Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check me-2"></i>
                            Reservas do Cardápio Online
                        </h5>
                        <button class="btn btn-sm btn-light" onclick="atualizarReservas()">
                            <i class="fas fa-sync-alt me-1"></i>
                            Atualizar
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="reservasContainer">
                        <?php if (empty($reservas)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Nenhuma reserva pendente no momento.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($reservas as $reserva): ?>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    $statusText = ucfirst($reserva['status']);
                                    
                                    switch ($reserva['status']) {
                                        case 'pendente':
                                            $statusClass = 'warning';
                                            $statusIcon = 'clock';
                                            break;
                                        case 'confirmada':
                                            $statusClass = 'success';
                                            $statusIcon = 'check-circle';
                                            break;
                                        case 'cancelada':
                                            $statusClass = 'danger';
                                            $statusIcon = 'times-circle';
                                            break;
                                        default:
                                            $statusClass = 'secondary';
                                            $statusIcon = 'info-circle';
                                    }
                                    
                                    $dataReserva = new DateTime($reserva['data_reserva']);
                                    $hoje = new DateTime();
                                    $diasRestantes = $hoje->diff($dataReserva)->days;
                                    $isHoje = $dataReserva->format('Y-m-d') === $hoje->format('Y-m-d');
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 border-start border-<?php echo $statusClass; ?>" style="border-left-width: 4px;">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($reserva['nome']); ?>
                                                    </h6>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="reserva-info">
                                                    <p class="mb-1">
                                                        <i class="fas fa-users text-muted me-2"></i>
                                                        <strong><?php echo $reserva['num_convidados']; ?></strong> 
                                                        <?php echo $reserva['num_convidados'] == 1 ? 'convidado' : 'convidados'; ?>
                                                    </p>
                                                    
                                                    <p class="mb-1">
                                                        <i class="fas fa-calendar text-muted me-2"></i>
                                                        <strong><?php echo $dataReserva->format('d/m/Y'); ?></strong>
                                                        <?php if ($isHoje): ?>
                                                            <span class="badge bg-info ms-2">Hoje</span>
                                                        <?php elseif ($diasRestantes == 1): ?>
                                                            <span class="badge bg-warning ms-2">Amanhã</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    
                                                    <p class="mb-1">
                                                        <i class="fas fa-clock text-muted me-2"></i>
                                                        <strong><?php echo date('H:i', strtotime($reserva['hora_reserva'])); ?></strong>
                                                    </p>
                                                    
                                                    <?php if ($reserva['mesa_numero']): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-table text-muted me-2"></i>
                                                            Mesa <strong><?php echo htmlspecialchars($reserva['mesa_numero']); ?></strong>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <p class="mb-1">
                                                        <i class="fas fa-phone text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($reserva['celular']); ?>
                                                    </p>
                                                    
                                                    <?php if ($reserva['email']): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-envelope text-muted me-2"></i>
                                                            <?php echo htmlspecialchars($reserva['email']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($reserva['instrucoes']): ?>
                                                        <p class="mb-0 mt-2">
                                                            <small class="text-muted">
                                                                <i class="fas fa-comment me-1"></i>
                                                                <?php echo htmlspecialchars($reserva['instrucoes']); ?>
                                                            </small>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                                    <button class="btn btn-sm btn-info" onclick="editarReserva(<?php echo $reserva['id']; ?>)">
                                                        <i class="fas fa-edit me-1"></i>
                                                        Editar
                                                    </button>
                                                    <?php if ($reserva['status'] === 'pendente'): ?>
                                                        <button class="btn btn-sm btn-success flex-fill" onclick="confirmarReserva(<?php echo $reserva['id']; ?>)">
                                                            <i class="fas fa-check me-1"></i>
                                                            Confirmar
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="cancelarReserva(<?php echo $reserva['id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($reserva['status'] === 'confirmada'): ?>
                                                        <button class="btn btn-sm btn-primary flex-fill" onclick="atribuirMesa(<?php echo $reserva['id']; ?>)">
                                                            <i class="fas fa-table me-1"></i>
                                                            Atribuir Mesa
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="cancelarReserva(<?php echo $reserva['id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
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
            
            // Load mesa content via AJAX - use mesaNumero which is the correct id_mesa value
            console.log('Chamando URL:', `index.php?action=mesa_multiplos_pedidos&ver_mesa=1&mesa_id=${mesaNumero}`);
            fetch(`index.php?action=mesa_multiplos_pedidos&ver_mesa=1&mesa_id=${mesaNumero}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Limpar o conteúdo anterior
                        document.getElementById('modalMesaBody').innerHTML = '';
                        
                        // Inserir o HTML completo
                        document.getElementById('modalMesaBody').innerHTML = data.html;
                        
                        // Executar scripts de forma mais segura
                        const scripts = document.getElementById('modalMesaBody').getElementsByTagName('script');
                        for (let i = 0; i < scripts.length; i++) {
                            try {
                                const script = document.createElement('script');
                                script.textContent = scripts[i].innerHTML;
                                document.head.appendChild(script);
                                document.head.removeChild(script);
                            } catch (e) {
                                console.error('Erro ao executar script:', e);
                            }
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
        
        function atualizarStatusVisual(pedidoId, novoStatus) {
            // Atualizar o badge de status no dashboard
            const statusBadge = document.querySelector(`[data-pedido-id="${pedidoId}"] .badge`);
            if (statusBadge) {
                statusBadge.textContent = novoStatus;
                
                // Atualizar a cor do badge baseada no status
                statusBadge.className = 'badge';
                switch(novoStatus) {
                    case 'Pendente':
                        statusBadge.classList.add('bg-warning');
                        break;
                    case 'Em Preparo':
                        statusBadge.classList.add('bg-info');
                        break;
                    case 'Pronto':
                        statusBadge.classList.add('bg-success');
                        break;
                    case 'Saiu para Entrega':
                        statusBadge.classList.add('bg-primary');
                        break;
                    case 'Entregue':
                        statusBadge.classList.add('bg-dark');
                        break;
                    case 'Saiu para Entrega':
                        statusBadge.classList.add('bg-info');
                        break;
                    case 'Finalizado':
                        statusBadge.classList.add('bg-success');
                        break;
                    case 'Cancelado':
                        statusBadge.classList.add('bg-danger');
                        break;
                    default:
                        statusBadge.classList.add('bg-secondary');
                }
            }
            
            // Atualizar o botão de avançar status se existir
            const avancarBtn = document.querySelector(`[data-pedido-id="${pedidoId}"] .btn-avancar-status`);
            if (avancarBtn) {
                const statuses = ['Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega', 'Finalizado'];
                const currentIndex = statuses.indexOf(novoStatus);
                
                if (currentIndex < statuses.length - 1) {
                    const proximoStatus = statuses[currentIndex + 1];
                    avancarBtn.textContent = `Avançar para ${proximoStatus}`;
                    avancarBtn.setAttribute('data-next-status', proximoStatus);
                } else {
                    avancarBtn.textContent = 'Pedido Finalizado';
                    avancarBtn.disabled = true;
                    avancarBtn.classList.add('disabled');
                }
            }
        }
        
        function alterarStatusDelivery(pedidoId, novoStatus, statusAtual, element) {
            // If no status selected or same status, reset dropdown and return
            if (!novoStatus || novoStatus === '' || novoStatus === statusAtual) {
                element.value = statusAtual;
                return;
            }
            
            Swal.fire({
                title: 'Confirmar Alteração',
                text: `Alterar status do pedido #${pedidoId} de "${statusAtual}" para "${novoStatus}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, alterar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
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
                            Swal.fire('Sucesso', 'Status atualizado com sucesso!', 'success');
                            
                            // If status is "Entregue" or "Finalizado", remove card from dashboard
                            if (novoStatus === 'Entregue' || novoStatus === 'Finalizado') {
                                setTimeout(() => {
                                    const card = element.closest('.col-md-6');
                                    if (card) {
                                        card.style.transition = 'opacity 0.5s';
                                        card.style.opacity = '0';
                                        setTimeout(() => card.remove(), 500);
                                    }
                                }, 1000);
                            } else {
                                // Just update the dropdown value
                                setTimeout(() => {
                                    element.value = novoStatus;
                                    // Update status badge
                                    const statusBadge = element.closest('.delivery-card').querySelector('.badge');
                                    if (statusBadge) {
                                        statusBadge.textContent = novoStatus;
                                        updateStatusBadgeColor(statusBadge, novoStatus);
                                    }
                                }, 1000);
                            }
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao atualizar status', 'error');
                            element.value = statusAtual; // Reset dropdown
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao atualizar status', 'error');
                        element.value = statusAtual; // Reset dropdown
                    });
                } else {
                    element.value = statusAtual; // Reset dropdown if cancelled
                }
            });
        }
        
        function updateStatusBadgeColor(badge, status) {
            badge.className = 'badge';
            switch(status) {
                case 'Pendente':
                    badge.classList.add('bg-warning');
                    break;
                case 'Em Preparo':
                    badge.classList.add('bg-info');
                    break;
                case 'Pronto':
                    badge.classList.add('bg-success');
                    break;
                case 'Saiu para Entrega':
                    badge.classList.add('bg-primary');
                    break;
                case 'Entregue':
                    badge.classList.add('bg-dark');
                    break;
                default:
                    badge.classList.add('bg-secondary');
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

        function fecharMesa(mesaId) {
            console.log('Redirecionando para página de fechamento da mesa:', mesaId);
            // Redirecionar para página dedicada em vez de popup
            window.location.href = `index.php?view=fechar_pedido&mesa_id=${mesaId}`;
        }

        function editarPedido(pedidoId) {
            window.location.href = `index.php?view=gerar_pedido&editar=${pedidoId}`;
        }

        function fecharPedido(pedidoId) {
            // Use the new partial payment system
            abrirModalPagamento(pedidoId);
        }

        function fecharPedidoDelivery(pedidoId) {
            // Redirecionar para a página de fechar pedido (mesmo modelo da mesa)
            window.location.href = `index.php?view=fechar_pedido&pedido_id=${pedidoId}`;
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

        // Auto-refresh every 30 seconds - only if no modals are open
        setInterval(() => {
            // Check if any SweetAlert modal is open
            if (!document.querySelector('.swal2-container')) {
                atualizarMesas();
            }
        }, 30000);

        // Update time every minute
        setInterval(() => {
            const now = new Date();
            const timeString = now.toLocaleString('pt-BR');
            document.querySelector('.user-info .text-end small').textContent = timeString;
        }, 60000);
        
        function fecharPedidoIndividual(pedidoId) {
            console.log('Redirecionando para página de fechamento do pedido:', pedidoId);
            // Redirecionar para página dedicada em vez de popup
            window.location.href = `index.php?view=fechar_pedido&pedido_id=${pedidoId}`;
            return;
            
            // Buscar dados reais do pedido primeiro
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=consultar_saldo_pedido&pedido_id=${pedidoId}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Dados do pedido:', data);
                if (data.success && data.pedido) {
                    const pedido = data.pedido;
                    const valorTotal = parseFloat(pedido.valor_total || 0);
                    const valorPago = parseFloat(pedido.valor_pago || 0);
                    const saldoDevedor = parseFloat(pedido.saldo_devedor || valorTotal);
                    
                    // Modal de pagamento parcial com dados reais
            Swal.fire({
                        title: 'Pagamento Parcial',
                html: `
                    <div class="mb-3">
                                <p><strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2).replace('.', ',')}</p>
                                <p><strong>Já Pago:</strong> R$ ${valorPago.toFixed(2).replace('.', ',')}</p>
                                <p class="text-danger"><strong>Saldo Devedor:</strong> R$ ${saldoDevedor.toFixed(2).replace('.', ',')}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                                <select class="form-select" id="formaPagamento" required>
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                        </select>
                    </div>
                            <div class="mb-3">
                                <label class="form-label">Valor a Pagar</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorPagar" step="0.01" min="0" max="${saldoDevedor.toFixed(2)}" value="${saldoDevedor.toFixed(2)}" required style="pointer-events: auto !important; cursor: text !important; background-color: white !important;" tabindex="1">
                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagar').value = '${saldoDevedor.toFixed(2)}'">Saldo Total</button>
                        </div>
                                <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do Cliente (opcional)</label>
                                <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente" style="pointer-events: auto !important; cursor: text !important; background-color: white !important;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                                <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999" style="pointer-events: auto !important; cursor: text !important; background-color: white !important;">
                    </div>
                    <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" id="descricao" rows="2" placeholder="Observações sobre o pagamento..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                        confirmButtonText: 'Registrar Pagamento',
                cancelButtonText: 'Cancelar',
                width: '500px',
                didOpen: () => {
                            // Garantir que todos os campos sejam editáveis
                    setTimeout(() => {
                                // Remover todos os atributos que impedem edição
                        const allInputs = document.querySelectorAll('input, textarea, select');
                        allInputs.forEach(input => {
                                    input.removeAttribute('disabled');
                                    input.removeAttribute('readonly');
                            input.style.pointerEvents = 'auto';
                            input.style.cursor = 'text';
                                    input.style.backgroundColor = 'white';
                                    input.style.color = 'black';
                            input.tabIndex = 1;
                                });
                                
                                // Forçar habilitação específica dos campos principais
                                const valorInput = document.getElementById('valorPagar');
                                const nomeInput = document.getElementById('nomeCliente');
                                const telefoneInput = document.getElementById('telefoneCliente');
                                const descricaoInput = document.getElementById('descricao');
                                
                                if (valorInput) {
                                    valorInput.removeAttribute('readonly');
                                    valorInput.removeAttribute('disabled');
                                    valorInput.style.pointerEvents = 'auto';
                                    valorInput.focus();
                                    valorInput.select();
                                }
                                
                                if (nomeInput) {
                                    nomeInput.removeAttribute('readonly');
                                    nomeInput.removeAttribute('disabled');
                                    nomeInput.style.pointerEvents = 'auto';
                                }
                                
                                if (telefoneInput) {
                                    telefoneInput.removeAttribute('readonly');
                                    telefoneInput.removeAttribute('disabled');
                                    telefoneInput.style.pointerEvents = 'auto';
                                }
                                
                                if (descricaoInput) {
                                    descricaoInput.removeAttribute('readonly');
                                    descricaoInput.removeAttribute('disabled');
                                    descricaoInput.style.pointerEvents = 'auto';
                                }
                                
                                console.log('Campos habilitados para edição');
                            }, 300);
                },
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                            const valorPagar = parseFloat(document.getElementById('valorPagar').value) || 0;
                    const nomeCliente = document.getElementById('nomeCliente').value;
                    const telefoneCliente = document.getElementById('telefoneCliente').value;
                            const descricao = document.getElementById('descricao').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                            if (valorPagar <= 0) {
                                Swal.showValidationMessage('Valor deve ser maior que zero');
                        return false;
                    }
                    
                            if (valorPagar > saldoDevedor + 0.01) { // Tolerância para arredondamento
                                Swal.showValidationMessage('Valor não pode ser maior que o saldo devedor');
                                return false;
                            }
                            
                            return { formaPagamento, valorPagar, nomeCliente, telefoneCliente, descricao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                            formData.append('action', 'registrar_pagamento_parcial');
                            formData.append('pedido_id', pedidoId);
                    formData.append('forma_pagamento', result.value.formaPagamento);
                            formData.append('valor_pago', result.value.valorPagar);
                    formData.append('nome_cliente', result.value.nomeCliente);
                    formData.append('telefone_cliente', result.value.telefoneCliente);
                            formData.append('descricao', result.value.descricao);
                    
                            fetch('index.php?action=pagamentos_parciais', {
                        method: 'POST',
                        headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                                body: new URLSearchParams(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                                console.log('Resposta do servidor:', data);
                        if (data.success) {
                                    if (data.pedido && data.pedido.status_pagamento === 'quitado') {
                                        Swal.fire('Sucesso!', 'Pedido quitado com sucesso!', 'success').then(() => {
                                            location.reload();
                                        });
                                    } else if (data.pedido && data.pedido.saldo_devedor !== undefined) {
                                        Swal.fire('Sucesso!', `Pagamento registrado! Saldo restante: R$ ${data.pedido.saldo_devedor.toFixed(2).replace('.', ',')}`, 'success').then(() => {
                                            location.reload();
                                        });
                        } else {
                                        Swal.fire('Sucesso!', 'Pagamento registrado com sucesso!', 'success').then(() => {
                                            location.reload();
                                        });
                                    }
                                } else {
                                    Swal.fire('Erro!', data.message || data.error || 'Erro desconhecido', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                Swal.fire('Erro!', 'Erro ao processar pagamento', 'error');
                            });
                        }
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao buscar dados do pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar pedido:', error);
                Swal.fire('Erro!', 'Erro ao buscar informações do pedido', 'error');
            });
        }
        
        function toggleValorPago() {
            const formaPagamento = document.getElementById('formaPagamento').value;
            const valorPagoContainer = document.getElementById('valorPagoContainer');
            const trocoContainer = document.getElementById('trocoContainer');
            
            if (formaPagamento === 'Dinheiro') {
                valorPagoContainer.style.display = 'block';
                trocoContainer.style.display = 'block';
            } else if (formaPagamento === 'Fiado') {
                valorPagoContainer.style.display = 'none';
                trocoContainer.style.display = 'none';
            } else {
                // Para cartão e PIX, mostrar valor pago mas sem troco
                valorPagoContainer.style.display = 'block';
                trocoContainer.style.display = 'none';
                // Auto-preenchir com valor total
                document.getElementById('valorPago').value = 41.00;
            }
        }
        
        function calcularTroco(valorTotal) {
            const valorPago = parseFloat(document.getElementById('valorPago').value) || 0;
            const troco = valorPago - valorTotal;
            const trocoInput = document.getElementById('troco');
            
            if (troco >= 0) {
                trocoInput.value = 'R$ ' + troco.toFixed(2).replace('.', ',');
                trocoInput.style.color = '#28a745';
            } else {
                trocoInput.value = 'Valor insuficiente';
                trocoInput.style.color = '#dc3545';
            }
        }
        
        function preencherValorTotal(valorTotal) {
            document.getElementById('valorPago').value = valorTotal;
            calcularTroco(valorTotal);
        }
        
        function buscarCliente() {
            const telefone = document.getElementById('telefoneCliente').value;
            if (telefone.length >= 10) {
                // Aqui você pode implementar a busca do cliente por telefone
                // Por enquanto, apenas um placeholder
                console.log('Buscando cliente com telefone:', telefone);
            }
        }
        
        function calcularValorPorPessoa(valorTotal) {
            const numeroPessoas = parseInt(document.getElementById('numeroPessoas').value) || 1;
            const valorPorPessoa = valorTotal / numeroPessoas;
            const valorPorPessoaDisplay = document.getElementById('valorPorPessoaDisplay');
            if (valorPorPessoaDisplay) {
                valorPorPessoaDisplay.textContent = 'R$ ' + valorPorPessoa.toFixed(2).replace('.', ',');
            }
            
            if (numeroPessoasInput && valorPorPessoaDisplay) {
                const pessoas = parseInt(numeroPessoasInput.value) || 1;
                const valorPorPessoa = valorTotal / pessoas;
                const valorFormatado = 'R$ ' + valorPorPessoa.toFixed(2).replace('.', ',');
                
                console.log('Pessoas:', pessoas);
                console.log('Valor por pessoa:', valorPorPessoa);
                console.log('Valor formatado:', valorFormatado);
                
                // Atualizar o display
                valorPorPessoaDisplay.textContent = valorFormatado;
                
                // Forçar atualização visual para confirmar que mudou
                valorPorPessoaDisplay.style.backgroundColor = '#ffeb3b';
                valorPorPessoaDisplay.style.padding = '2px';
                setTimeout(() => {
                    valorPorPessoaDisplay.style.backgroundColor = '';
                    valorPorPessoaDisplay.style.padding = '';
                }, 500);
                
                console.log('✅ Display atualizado com:', valorPorPessoaDisplay.textContent);
            } else {
                console.log('❌ ERRO: Elementos não encontrados!');
                console.log('numeroPessoasInput:', numeroPessoasInput);
                console.log('valorPorPessoaDisplay:', valorPorPessoaDisplay);
            }
        }
        
        function toggleValorPagoMesa() {
            const formaPagamento = document.getElementById('formaPagamento').value;
            const valorPagoContainer = document.getElementById('valorPagoMesaContainer');
            const trocoContainer = document.getElementById('trocoMesaContainer');
            
            if (formaPagamento === 'Dinheiro') {
                valorPagoContainer.style.display = 'block';
                trocoContainer.style.display = 'block';
            } else if (formaPagamento === 'Fiado') {
                valorPagoContainer.style.display = 'none';
                trocoContainer.style.display = 'none';
            } else {
                // Para cartão e PIX, mostrar valor pago mas sem troco
                valorPagoContainer.style.display = 'block';
                trocoContainer.style.display = 'none';
                // Auto-preenchir com valor total
                document.getElementById('valorPagoMesa').value = 41.00;
            }
        }
        
        function calcularTrocoMesa(valorTotal) {
            const valorPago = parseFloat(document.getElementById('valorPagoMesa').value) || 0;
            const troco = valorPago - valorTotal;
            const trocoInput = document.getElementById('trocoMesa');
            
            if (troco >= 0) {
                trocoInput.value = 'R$ ' + troco.toFixed(2).replace('.', ',');
                trocoInput.style.color = '#28a745';
            } else {
                trocoInput.value = 'Valor insuficiente';
                trocoInput.style.color = '#dc3545';
            }
        }
        
        function preencherValorTotalMesa(valorTotal) {
            document.getElementById('valorPagoMesa').value = valorTotal;
            calcularTrocoMesa(valorTotal);
        }
        
        function buscarClienteMesa() {
            const telefone = document.getElementById('telefoneClienteMesa').value;
            if (telefone.length >= 10) {
                // Aqui você pode implementar a busca do cliente por telefone
                // Por enquanto, apenas um placeholder
                console.log('Buscando cliente com telefone:', telefone);
            }
        }
        
        function fecharMesaCompleta(mesaId) {
            console.log('Redirecionando para página de fechamento da mesa completa:', mesaId);
            // Redirecionar para página dedicada em vez de popup
            window.location.href = `index.php?view=fechar_pedido&mesa_id=${mesaId}`;
        }
        
        function abrirPopupFecharMesa(mesaId, valorTotal) {
            console.log('DEBUG: abrirPopupFecharMesa called for mesaId:', mesaId, 'valorTotal:', valorTotal);
            
            // Buscar o saldo devedor atual da mesa
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=consultar_saldo_mesa&mesa_id=${mesaId}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('DEBUG: Response from consultar_saldo_mesa:', data);
                if (data.success) {
                    const saldoDevedorMesa = data.saldo_devedor_mesa;
                    const valorTotalMesa = data.valor_total_mesa;

                    // Modal de pagamento parcial - MESMA LÓGICA DO FECHAR PEDIDO
                    Swal.fire({
                        title: 'Pagamento Parcial - Mesa',
                        html: `
                            <div class="mb-3">
                                <p><strong>Valor Total da Mesa:</strong> R$ ${valorTotalMesa.toFixed(2).replace('.', ',')}</p>
                                <p><strong>Já Pago:</strong> R$ ${(valorTotalMesa - saldoDevedorMesa).toFixed(2).replace('.', ',')}</p>
                                <p class="text-danger"><strong>Saldo Devedor:</strong> R$ ${saldoDevedorMesa.toFixed(2).replace('.', ',')}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Forma de Pagamento</label>
                                <select class="form-select" id="formaPagamento" required>
                                    <option value="">Selecione a forma de pagamento</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartão Débito">Cartão Débito</option>
                                    <option value="Cartão Crédito">Cartão Crédito</option>
                                    <option value="PIX">PIX</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valor a Pagar</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorPagar" step="0.01" min="0" max="${saldoDevedorMesa.toFixed(2)}" value="${saldoDevedorMesa.toFixed(2)}" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagar').value = '${saldoDevedorMesa.toFixed(2)}'">Saldo Total</button>
                                </div>
                                <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nome do Cliente (opcional)</label>
                                <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefone (opcional)</label>
                                <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" id="descricao" rows="2" placeholder="Observações sobre o pagamento..."></textarea>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Registrar Pagamento',
                        cancelButtonText: 'Cancelar',
                        width: '500px',
                        didOpen: () => {
                            // Garantir que todos os campos sejam editáveis
                            setTimeout(() => {
                                const allInputs = document.querySelectorAll('input, textarea, select');
                                allInputs.forEach(input => {
                                    input.removeAttribute('disabled');
                                    input.removeAttribute('readonly');
                                    input.style.pointerEvents = 'auto';
                                    input.style.cursor = 'text';
                                    input.style.backgroundColor = 'white';
                                    input.style.color = 'black';
                                });
                                
                                const valorInput = document.getElementById('valorPagar');
                                if (valorInput) {
                                    valorInput.focus();
                                    valorInput.select();
                                }
                            }, 300);
                        },
                        preConfirm: () => {
                            const formaPagamento = document.getElementById('formaPagamento').value;
                            const valorPagar = parseFloat(document.getElementById('valorPagar').value) || 0;
                            const nomeCliente = document.getElementById('nomeCliente').value;
                            const telefoneCliente = document.getElementById('telefoneCliente').value;
                            const descricao = document.getElementById('descricao').value;
                            
                            if (!formaPagamento) {
                                Swal.showValidationMessage('Forma de pagamento é obrigatória');
                                return false;
                            }
                            
                            if (valorPagar <= 0) {
                                Swal.showValidationMessage('Valor deve ser maior que zero');
                                return false;
                            }
                            
                            if (valorPagar > saldoDevedorMesa + 0.01) {
                                Swal.showValidationMessage('Valor não pode ser maior que o saldo devedor');
                                return false;
                            }
                            
                            return { formaPagamento, valorPagar, nomeCliente, telefoneCliente, descricao };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const formData = new URLSearchParams();
                            formData.append('action', 'registrar_pagamento_mesa');
                            formData.append('mesa_id', mesaId);
                            formData.append('forma_pagamento', result.value.formaPagamento);
                            formData.append('valor_pago', result.value.valorPagar);
                            formData.append('nome_cliente', result.value.nomeCliente);
                            formData.append('telefone_cliente', result.value.telefoneCliente);
                            formData.append('descricao', result.value.descricao);
                            
                            fetch('index.php?action=pagamentos_parciais', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Resposta do servidor:', data);
                                if (data.success) {
                                    if (data.mesa_liberada) {
                                        Swal.fire('Sucesso!', 'Mesa quitada e liberada com sucesso!', 'success').then(() => {
                                            location.reload();
                                        });
                                    } else if (data.saldo_restante !== undefined) {
                                        Swal.fire('Sucesso!', `Pagamento registrado! Saldo restante: R$ ${data.saldo_restante.toFixed(2).replace('.', ',')}`, 'success').then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire('Sucesso!', 'Pagamento registrado com sucesso!', 'success').then(() => {
                                            location.reload();
                                        });
                                    }
                                } else {
                                    Swal.fire('Erro!', data.message || data.error || 'Erro desconhecido', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                Swal.fire('Erro!', 'Erro ao processar pagamento', 'error');
                            });
                        }
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao buscar saldo da mesa', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar saldo da mesa:', error);
                Swal.fire('Erro!', 'Erro ao buscar informações da mesa', 'error');
            });
        }
        
        // Função de fallback (sistema antigo) - mantida para compatibilidade
        function abrirPopupFecharMesaAntigo(mesaId, valorTotal) {
            Swal.fire({
                title: 'Fechar Mesa Completa',
                html: `
                    <div class="mb-3">
                        <label class="form-label"><strong>Valor Total da Mesa: R$ ${valorTotal.toFixed(2).replace('.', ',')}</strong></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de Pessoas (opcional)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="numeroPessoas" value="1" min="1" oninput="calcularValorPorPessoa(${valorTotal})" style="pointer-events: auto !important; cursor: text !important;">
                            <span class="input-group-text">pessoas</span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Valor por pessoa: <span id="valorPorPessoaDisplay">R$ ${(valorTotal / 1).toFixed(2).replace('.', ',')}</span></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento" onchange="toggleValorPagoMesa()" required>
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                            <option value="Fiado">Fiado (Crédito)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="valorPagoMesaContainer" style="display: none;">
                        <label class="form-label">Valor Pago</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valorPagoMesa" step="0.01" min="0" placeholder="0,00" oninput="calcularTrocoMesa(${valorTotal})">
                            <button type="button" class="btn btn-outline-secondary" onclick="preencherValorTotalMesa(${valorTotal})">Valor Total</button>
                        </div>
                    </div>
                    <div class="mb-3" id="trocoMesaContainer" style="display: none;">
                        <label class="form-label">Troco</label>
                        <input type="text" class="form-control" id="trocoMesa" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do Cliente (opcional)</label>
                        <input type="text" class="form-control" id="nomeClienteMesa" placeholder="Nome do cliente" autocomplete="off" tabindex="1" style="pointer-events: auto !important; cursor: text !important;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                        <input type="text" class="form-control" id="telefoneClienteMesa" placeholder="(11) 99999-9999" onchange="buscarClienteMesa()" autocomplete="off" tabindex="2" style="pointer-events: auto !important; cursor: text !important;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observação</label>
                        <textarea class="form-control" id="observacaoMesa" rows="2" placeholder="Observações sobre o fechamento..." autocomplete="off" tabindex="3" style="pointer-events: auto !important; cursor: text !important;"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Fechar Mesa',
                cancelButtonText: 'Cancelar',
                width: '500px',
                allowOutsideClick: false,
                allowEscapeKey: true,
                focusConfirm: false,
                didOpen: () => {
                    // Garantir que todos os campos sejam editáveis
                    setTimeout(() => {
                        const allInputs = document.querySelectorAll('input, textarea, select');
                        allInputs.forEach(input => {
                            input.disabled = false;
                            input.readOnly = false;
                            input.style.pointerEvents = 'auto';
                            input.style.cursor = 'text';
                            input.tabIndex = 1;
                        });
                        
                        // Focar no primeiro campo
                        const firstInput = document.querySelector('input, textarea, select');
                        if (firstInput) {
                            firstInput.focus();
                            firstInput.click();
                        }
                    }, 100);
                },
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const valorPago = parseFloat(document.getElementById('valorPagoMesa').value) || 0;
                    const nomeCliente = document.getElementById('nomeClienteMesa').value;
                    const telefoneCliente = document.getElementById('telefoneClienteMesa').value;
                    const observacao = document.getElementById('observacaoMesa').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    // Permitir pagamento parcial para todas as formas de pagamento
                    if (formaPagamento === 'Dinheiro' && valorPago > valorTotal) {
                        Swal.showValidationMessage('Valor pago não pode ser maior que o valor total');
                        return false;
                    }
                    
                    // Permitir pagamento parcial para todas as formas de pagamento
                    return { formaPagamento, valorPago, nomeCliente, telefoneCliente, observacao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'fechar_mesa_completa');
                    formData.append('mesa_id', mesaId);
                    formData.append('forma_pagamento', result.value.formaPagamento);
                    formData.append('valor_pago', result.value.valorPago);
                    formData.append('nome_cliente', result.value.nomeCliente);
                    formData.append('telefone_cliente', result.value.telefoneCliente);
                    formData.append('observacoes', result.value.observacao);
                    
                    fetch('index.php?action=mesa_multiplos_pedidos', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
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
        
        function toggleFechamentoOptions() {
            const tipoFechamento = document.getElementById('tipoFechamento').value;
            
            // Ocultar todos os containers
            document.getElementById('simplesContainer').style.display = 'none';
            document.getElementById('divididoContainer').style.display = 'none';
            document.getElementById('mistoContainer').style.display = 'none';
            
            // Mostrar o container correspondente
            if (tipoFechamento === 'simples') {
                document.getElementById('simplesContainer').style.display = 'block';
            } else if (tipoFechamento === 'dividido') {
                document.getElementById('divididoContainer').style.display = 'block';
                calcularValorPorPessoa(parseFloat(document.getElementById('valorPorPessoa').getAttribute('data-total') || 0));
            } else if (tipoFechamento === 'misto') {
                document.getElementById('mistoContainer').style.display = 'block';
            }
        }
        
        
        function calcularTotalMisto(valorTotal) {
            const dinheiro = parseFloat(document.getElementById('valorDinheiro').value) || 0;
            const debito = parseFloat(document.getElementById('valorDebito').value) || 0;
            const credito = parseFloat(document.getElementById('valorCredito').value) || 0;
            const pix = parseFloat(document.getElementById('valorPix').value) || 0;
            const totalInformado = dinheiro + debito + credito + pix;
            
            const totalInput = document.getElementById('totalMisto');
            totalInput.value = 'R$ ' + totalInformado.toFixed(2).replace('.', ',');
            
            if (Math.abs(totalInformado - valorTotal) < 0.01) {
                totalInput.style.color = '#28a745';
            } else {
                totalInput.style.color = '#dc3545';
            }
        }
        
        function calcularTrocoSimples(valorTotal) {
            const valorPago = parseFloat(document.getElementById('valorPagoSimples').value) || 0;
            const troco = valorPago - valorTotal;
            const trocoInput = document.getElementById('trocoSimples');
            
            if (troco >= 0) {
                trocoInput.value = 'R$ ' + troco.toFixed(2).replace('.', ',');
                trocoInput.style.color = '#28a745';
            } else {
                trocoInput.value = 'Valor insuficiente';
                trocoInput.style.color = '#dc3545';
            }
        }
    </script>
    
    <!-- Reservas Management Script -->
    <script>
        function atualizarReservas() {
            location.reload();
        }
        
        function confirmarReserva(reservaId) {
            Swal.fire({
                title: 'Confirmar Reserva',
                text: 'Deseja confirmar esta reserva?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    atualizarStatusReserva(reservaId, 'confirmada');
                }
            });
        }
        
        function cancelarReserva(reservaId) {
            Swal.fire({
                title: 'Cancelar Reserva',
                text: 'Deseja cancelar esta reserva?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, cancelar',
                cancelButtonText: 'Não',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    atualizarStatusReserva(reservaId, 'cancelada');
                }
            });
        }
        
        function atribuirMesa(reservaId) {
            // Get available mesas
            fetch('mvc/ajax/dashboard.php?action=get_mesas')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.mesas) {
                        const mesasOptions = data.mesas.map(m => 
                            `<option value="${m.id}">Mesa ${m.id_mesa} (Capacidade: ${m.capacidade})</option>`
                        ).join('');
                        
                        Swal.fire({
                            title: 'Atribuir Mesa',
                            html: `
                                <select id="mesaSelect" class="form-select">
                                    <option value="">Selecione uma mesa</option>
                                    ${mesasOptions}
                                </select>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Atribuir',
                            cancelButtonText: 'Cancelar',
                            preConfirm: () => {
                                const mesaId = document.getElementById('mesaSelect').value;
                                if (!mesaId) {
                                    Swal.showValidationMessage('Por favor, selecione uma mesa');
                                    return false;
                                }
                                return mesaId;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                atribuirMesaReserva(reservaId, result.value);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Não foi possível carregar as mesas disponíveis'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao carregar mesas'
                    });
                });
        }
        
        function editarReserva(reservaId) {
            // Buscar dados da reserva
            fetch('mvc/ajax/reservas_online.php?action=get_reserva&reserva_id=' + reservaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.reserva) {
                        const reserva = data.reserva;
                        
                        Swal.fire({
                            title: 'Editar Reserva',
                            html: `
                                <div class="text-start">
                                    <div class="mb-3">
                                        <label class="form-label">Nome</label>
                                        <input type="text" id="editNome" class="form-control" value="${reserva.nome || ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Telefone</label>
                                        <input type="tel" id="editCelular" class="form-control" value="${reserva.celular || ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" id="editEmail" class="form-control" value="${reserva.email || ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Número de Convidados</label>
                                        <input type="number" id="editNumConvidados" class="form-control" value="${reserva.num_convidados || 1}" min="1">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Data da Reserva</label>
                                        <input type="date" id="editDataReserva" class="form-control" value="${reserva.data_reserva || ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Hora</label>
                                        <input type="time" id="editHoraReserva" class="form-control" value="${reserva.hora_reserva || ''}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Instruções</label>
                                        <textarea id="editInstrucoes" class="form-control" rows="3">${reserva.instrucoes || ''}</textarea>
                                    </div>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Salvar',
                            cancelButtonText: 'Cancelar',
                            preConfirm: () => {
                                return {
                                    nome: document.getElementById('editNome').value.trim(),
                                    celular: document.getElementById('editCelular').value.trim(),
                                    email: document.getElementById('editEmail').value.trim(),
                                    num_convidados: parseInt(document.getElementById('editNumConvidados').value) || 1,
                                    data_reserva: document.getElementById('editDataReserva').value,
                                    hora_reserva: document.getElementById('editHoraReserva').value,
                                    instrucoes: document.getElementById('editInstrucoes').value.trim()
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                salvarEdicaoReserva(reservaId, result.value);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Não foi possível carregar os dados da reserva'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao carregar dados da reserva'
                    });
                });
        }
        
        function salvarEdicaoReserva(reservaId, dados) {
            fetch('mvc/ajax/reservas_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'editar',
                    reserva_id: reservaId,
                    ...dados
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: 'Reserva atualizada com sucesso!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao atualizar reserva'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao atualizar reserva'
                });
            });
        }
        
        function atualizarStatusReserva(reservaId, status) {
            fetch('mvc/ajax/reservas_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_status',
                    reserva_id: reservaId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: data.message || 'Status da reserva atualizado com sucesso!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao atualizar status da reserva'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao atualizar reserva'
                });
            });
        }
        
        function atribuirMesaReserva(reservaId, mesaId) {
            fetch('mvc/ajax/reservas_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'atribuir_mesa',
                    reserva_id: reservaId,
                    mesa_id: mesaId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: data.message || 'Mesa atribuída com sucesso!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao atribuir mesa'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao atribuir mesa'
                });
            });
        }
    </script>
    
    <!-- Partial Payment System -->
    <script src="assets/js/pagamentos-parciais.js"></script>

    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component (includes AI Chat Widget) -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
</html>
</html>