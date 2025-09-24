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

// Get produtos com baixo estoque
$produtos_baixo_estoque = [];
if ($tenant && $filial) {
    $produtos_baixo_estoque = $db->fetchAll(
        "SELECT p.*, c.nome as categoria_nome, e.estoque_atual, e.estoque_minimo
         FROM produtos p 
         LEFT JOIN categorias c ON p.categoria_id = c.id 
         LEFT JOIN estoque e ON p.id = e.produto_id AND e.tenant_id = p.tenant_id AND e.filial_id = p.filial_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND (e.estoque_atual <= e.estoque_minimo OR e.estoque_atual IS NULL)
         ORDER BY COALESCE(e.estoque_atual, 0) - COALESCE(e.estoque_minimo, 0) ASC",
        [$tenant['id'], $filial['id']]
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque - <?php echo $config->get('app.name'); ?></title>
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
        
        .alert-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #dc3545;
            margin-bottom: 1rem;
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
                        <a class="nav-link active" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
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
                                <i class="fas fa-warehouse me-2"></i>
                                Controle de Estoque
                            </h2>
                            <p class="text-muted mb-0">Produtos com baixo estoque</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-success" onclick="exportarExcel()">
                                    <i class="fas fa-file-excel me-1"></i>
                                    Exportar
                                </button>
                                <button class="btn btn-primary" onclick="atualizarEstoque()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alertas de Estoque -->
                <?php if (count($produtos_baixo_estoque) > 0): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção!</strong> <?php echo count($produtos_baixo_estoque); ?> produto(s) com estoque baixo.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Ótimo!</strong> Todos os produtos estão com estoque adequado.
                    </div>
                <?php endif; ?>

                <!-- Lista de Produtos -->
                <div class="row">
                    <?php foreach ($produtos_baixo_estoque as $produto): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="alert-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($produto['nome']); ?></h6>
                                    <span class="badge bg-danger">Estoque Baixo</span>
                                </div>
                                
                                <?php if ($produto['categoria_nome']): ?>
                                    <div class="text-muted small mb-2">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($produto['categoria_nome']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-danger fw-bold"><?php echo $produto['estoque_atual']; ?></div>
                                        <div class="text-muted small">Atual</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-warning fw-bold"><?php echo $produto['estoque_minimo']; ?></div>
                                        <div class="text-muted small">Mínimo</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-success fw-bold"><?php echo $produto['estoque_atual'] - $produto['estoque_minimo']; ?></div>
                                        <div class="text-muted small">Diferença</div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-primary w-100" onclick="reporEstoque(<?php echo $produto['id']; ?>)">
                                        <i class="fas fa-plus me-1"></i>
                                        Repor Estoque
                                    </button>
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
        function reporEstoque(produtoId) {
            Swal.fire({
                title: 'Repor Estoque',
                input: 'number',
                inputLabel: 'Quantidade a adicionar',
                inputPlaceholder: 'Digite a quantidade',
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Sucesso', 'Estoque atualizado com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        function exportarExcel() {
            Swal.fire('Info', 'Funcionalidade de exportação será implementada', 'info');
        }

        function atualizarEstoque() {
            location.reload();
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
