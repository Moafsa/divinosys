<?php
// Cliente Dashboard - Página específica para clientes
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Buscar histórico de pedidos do cliente em todos os estabelecimentos
$pedidosRecent = [];
if ($user && isset($user['id'])) {
    $pedidosRecent = $db->fetchAll("
        SELECT 
            p.idpedido,
            p.cliente,
            p.status,
            p.status_pagamento,
            p.valor_total,
            p.delivery,
            p.data,
            p.hora_pedido,
            t.nome as tenant_nome,
            f.nome as filial_nome
        FROM pedido p
        LEFT JOIN tenants t ON p.tenant_id = t.id
        LEFT JOIN filiais f ON p.filial_id = f.id
        WHERE p.usuario_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ", [$user['id']]);
}

// Estatísticas do cliente
$estatisticas = [
    'total_pedidos' => 0,
    'total_gasto' => 0,
    'pedidos_pendentes' => 0,
    'estabelecimentos' => []
];

if ($user && isset($user['id'])) {
    // Total de pedidos
    $total = $db->fetch("
        SELECT COUNT(*) as total
        FROM pedido
        WHERE usuario_id = ?
    ", [$user['id']]);
    $estatisticas['total_pedidos'] = $total['total'] ?? 0;
    
    // Total gasto
    $gasto = $db->fetch("
        SELECT COALESCE(SUM(valor_pago), 0) as total
        FROM pedido
        WHERE usuario_id = ? AND status_pagamento = 'quitado'
    ", [$user['id']]);
    $estatisticas['total_gasto'] = $gasto['total'] ?? 0;
    
    // Pedidos pendentes
    $pendentes = $db->fetch("
        SELECT COUNT(*) as total
        FROM pedido
        WHERE usuario_id = ? AND status IN ('Pendente', 'Em Preparo', 'Pronto')
    ", [$user['id']]);
    $estatisticas['pedidos_pendentes'] = $pendentes['total'] ?? 0;
    
    // Estabelecimentos visitados
    $estabs = $db->fetchAll("
        SELECT DISTINCT t.id, t.nome, t.logo_url, COUNT(p.idpedido) as total_pedidos
        FROM tenants t
        INNER JOIN pedido p ON t.id = p.tenant_id
        WHERE p.usuario_id = ?
        GROUP BY t.id, t.nome, t.logo_url
        ORDER BY total_pedidos DESC
    ", [$user['id']]);
    $estatisticas['estabelecimentos'] = $estabs;
}

// User is already authenticated by the router
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Cliente - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .welcome-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-center flex-grow-1">
                    <h1 class="display-4 text-primary mb-3">
                        <i class="fas fa-user-circle"></i>
                        Dashboard do Cliente
                    </h1>
                    <p class="lead text-muted">
                        Bem-vindo(a), <strong><?php echo htmlspecialchars($user['login'] ?? 'Cliente'); ?>!</strong>
                    </p>
                </div>
                <div>
                    <a href="<?php echo $router->url('logout'); ?>" class="btn btn-danger btn-lg">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="row">
            <!-- Histórico de Pedidos -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="dashboard-card card h-100 text-center p-4">
                    <div class="card-icon text-primary">
                        <i class="fas fa-history"></i>
                    </div>
                    <h5 class="card-title">Histórico de Pedidos</h5>
                    <p class="card-text">Visualize seus pedidos anteriores e acompanhe o status.</p>
                    <a href="<?php echo $router->url('historico_pedidos'); ?>" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Ver Histórico
                    </a>
                </div>
            </div>

            <!-- Meu Perfil -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="dashboard-card card h-100 text-center p-4">
                    <div class="card-icon text-success">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 class="card-title">Meu Perfil</h5>
                    <p class="card-text">Gerencie suas informações pessoais e preferências.</p>
                    <a href="<?php echo $router->url('perfil'); ?>" class="btn btn-success">
                        <i class="fas fa-edit"></i> Editar Perfil
                    </a>
                </div>
            </div>

            <!-- Novo Pedido -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="dashboard-card card h-100 text-center p-4">
                    <div class="card-icon text-warning">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h5 class="card-title">Novo Pedido</h5>
                    <p class="card-text">Faça um novo pedido rapidamente.</p>
                    <a href="<?php echo $router->url('novo_pedido'); ?>" class="btn btn-warning">
                        <i class="fas fa-shopping-cart"></i> Fazer Pedido
                    </a>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="dashboard-card card text-center p-3 bg-primary text-white">
                    <h3 class="mb-0"><?php echo $estatisticas['total_pedidos']; ?></h3>
                    <small>Total de Pedidos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card card text-center p-3 bg-success text-white">
                    <h3 class="mb-0">R$ <?php echo number_format($estatisticas['total_gasto'], 2, ',', '.'); ?></h3>
                    <small>Total Gasto</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card card text-center p-3 bg-warning text-white">
                    <h3 class="mb-0"><?php echo $estatisticas['pedidos_pendentes']; ?></h3>
                    <small>Pedidos Pendentes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card card text-center p-3 bg-info text-white">
                    <h3 class="mb-0"><?php echo count($estatisticas['estabelecimentos']); ?></h3>
                    <small>Estabelecimentos</small>
                </div>
            </div>
        </div>

        <!-- Pedidos Recentes -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Pedidos Recentes
                        </h5>
                        <a href="<?php echo $router->url('historico_pedidos'); ?>" class="btn btn-sm btn-primary">
                            Ver Todos <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedidosRecent)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i>
                                Você ainda não fez nenhum pedido.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Data/Hora</th>
                                            <th>Estabelecimento</th>
                                            <th>Status</th>
                                            <th>Valor</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidosRecent as $pedido): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pedido['idpedido']); ?></td>
                                                <td>
                                                    <?php 
                                                    $data = new DateTime($pedido['data']);
                                                    $hora = $pedido['hora_pedido'];
                                                    echo $data->format('d/m/Y') . ' ' . $hora;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($pedido['tenant_nome'] ?? 'N/A'); ?>
                                                    <?php if ($pedido['filial_nome']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($pedido['filial_nome']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = 'secondary';
                                                    if ($pedido['status'] === 'Finalizado') $statusClass = 'success';
                                                    elseif ($pedido['status'] === 'Cancelado') $statusClass = 'danger';
                                                    elseif (in_array($pedido['status'], ['Pendente', 'Em Preparo', 'Pronto'])) $statusClass = 'warning';
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars($pedido['status']); ?>
                                                    </span>
                                                    <?php if ($pedido['delivery']): ?>
                                                        <br><small class="text-muted">Delivery</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></strong></td>
                                                <td>
                                                    <a href="<?php echo $router->url('historico_pedidos'); ?>?pedido_id=<?php echo $pedido['idpedido']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>