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
        <div class="welcome-header text-center">
            <h1 class="display-4 text-primary mb-3">
                <i class="fas fa-user-circle"></i>
                Dashboard do Cliente
            </h1>
            <p class="lead text-muted">
                Bem-vindo(a), <strong><?php echo htmlspecialchars($user['login'] ?? 'Cliente'); ?>!</strong>
            </p>
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

        <!-- Recent Orders Preview -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Pedidos Recentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Seus pedidos recentes aparecerão aqui. 
                            <a href="<?php echo $router->url('historico_pedidos'); ?>" class="alert-link">
                                Ver histórico completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>