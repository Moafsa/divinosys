<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

$opcao = $_GET['opcao'] ?? 'resultados';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro - <?php echo $config->get('app.name'); ?></title>
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
        
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .menu-option {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .menu-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }
        
        .menu-option.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
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
                        <a class="nav-link active" href="<?php echo $router->url('financeiro'); ?>">
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
                                <i class="fas fa-chart-line me-2"></i>
                                Gestão Financeira
                            </h2>
                            <p class="text-muted mb-0">Controle de receitas, despesas e fluxo de caixa</p>
                        </div>
                    </div>
                </div>

                <!-- Menu Options -->
                <div class="menu-card">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="menu-option <?php echo $opcao === 'resultados' ? 'active' : ''; ?>" onclick="selecionarOpcao('resultados')">
                                <i class="fas fa-chart-pie fa-2x mb-2"></i>
                                <h5>Resultados</h5>
                                <p class="mb-0">Receitas vs Despesas</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="menu-option <?php echo $opcao === 'despesas' ? 'active' : ''; ?>" onclick="selecionarOpcao('despesas')">
                                <i class="fas fa-arrow-down fa-2x mb-2"></i>
                                <h5>Despesas</h5>
                                <p class="mb-0">Controle de gastos</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="menu-option <?php echo $opcao === 'entradas' ? 'active' : ''; ?>" onclick="selecionarOpcao('entradas')">
                                <i class="fas fa-arrow-up fa-2x mb-2"></i>
                                <h5>Entradas</h5>
                                <p class="mb-0">Receitas e vendas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content based on selected option -->
                <?php if ($opcao === 'resultados'): ?>
                    <div class="menu-card">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-pie me-2"></i>
                            Resultados Financeiros
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success">R$ 0,00</h3>
                                        <p class="text-muted">Receitas do Mês</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-danger">R$ 0,00</h3>
                                        <p class="text-muted">Despesas do Mês</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($opcao === 'despesas'): ?>
                    <div class="menu-card">
                        <h5 class="mb-3">
                            <i class="fas fa-arrow-down me-2"></i>
                            Controle de Despesas
                        </h5>
                        <p class="text-muted">Funcionalidade de despesas será implementada</p>
                    </div>
                <?php elseif ($opcao === 'entradas'): ?>
                    <div class="menu-card">
                        <h5 class="mb-3">
                            <i class="fas fa-arrow-up me-2"></i>
                            Controle de Entradas
                        </h5>
                        <p class="text-muted">Funcionalidade de entradas será implementada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function selecionarOpcao(opcao) {
            window.location.href = `<?php echo $router->url('financeiro'); ?>&opcao=${opcao}`;
        }
    </script>
</body>
</html>
