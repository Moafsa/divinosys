<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Get date range from URL or default to today
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Get relatórios data
$stats = [
    'total_pedidos' => 0,
    'valor_total' => 0,
    'pedidos_delivery' => 0,
    'pedidos_mesa' => 0,
    'produtos_vendidos' => 0
];

$vendas_por_dia = [];
$produtos_mais_vendidos = [];
$status_pedidos = [];

if ($tenant && $filial) {
    // Basic stats
    $stats['total_pedidos'] = $db->count(
        'pedido', 
        'tenant_id = ? AND filial_id = ? AND data BETWEEN ? AND ?', 
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    $valorTotal = $db->fetch(
        "SELECT COALESCE(SUM(valor_total), 0) as total FROM pedido WHERE tenant_id = ? AND filial_id = ? AND data BETWEEN ? AND ?",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    $stats['valor_total'] = $valorTotal['total'] ?? 0;
    
    $stats['pedidos_delivery'] = $db->count(
        'pedido', 
        'tenant_id = ? AND filial_id = ? AND delivery = true AND data BETWEEN ? AND ?', 
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    $stats['pedidos_mesa'] = $db->count(
        'pedido', 
        'tenant_id = ? AND filial_id = ? AND delivery = false AND data BETWEEN ? AND ?', 
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Vendas por dia
    $vendas_por_dia = $db->fetchAll(
        "SELECT data, COUNT(*) as quantidade, COALESCE(SUM(valor_total), 0) as valor 
         FROM pedido 
         WHERE tenant_id = ? AND filial_id = ? AND data BETWEEN ? AND ? 
         GROUP BY data 
         ORDER BY data",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Produtos mais vendidos
    $produtos_mais_vendidos = $db->fetchAll(
        "SELECT p.nome, SUM(pi.quantidade) as total_vendido, SUM(pi.valor_total) as valor_total
         FROM pedido_itens pi
         JOIN produtos p ON pi.produto_id = p.id
         JOIN pedido ped ON pi.pedido_id = ped.idpedido
         WHERE ped.tenant_id = ? AND ped.filial_id = ? AND ped.data BETWEEN ? AND ?
         GROUP BY p.id, p.nome
         ORDER BY total_vendido DESC
         LIMIT 10",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Status dos pedidos
    $status_pedidos = $db->fetchAll(
        "SELECT status, COUNT(*) as quantidade 
         FROM pedido 
         WHERE tenant_id = ? AND filial_id = ? AND data BETWEEN ? AND ?
         GROUP BY status",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filters-card {
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
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
                            <i class="fas fa-chart-line"></i>
                            <span>Financeiro</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
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
                                <i class="fas fa-chart-bar me-2"></i>
                                Relatórios
                            </h2>
                            <p class="text-muted mb-0">Análises e relatórios de vendas</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-success" onclick="exportarExcel()">
                                    <i class="fas fa-file-excel me-1"></i>
                                    Excel
                                </button>
                                <button class="btn btn-outline-danger" onclick="exportarPDF()">
                                    <i class="fas fa-file-pdf me-1"></i>
                                    PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-card">
                    <h5 class="mb-3">
                        <i class="fas fa-filter me-2"></i>
                        Filtros
                    </h5>
                    <form method="GET" action="<?php echo $router->url('relatorios'); ?>">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Data Início</label>
                                <input type="date" class="form-control" name="data_inicio" value="<?php echo $dataInicio; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Data Fim</label>
                                <input type="date" class="form-control" name="data_fim" value="<?php echo $dataFim; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total_pedidos']; ?></div>
                            <div class="stats-label">Total Pedidos</div>
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
                            <div class="stats-number"><?php echo $stats['pedidos_delivery']; ?></div>
                            <div class="stats-label">Delivery</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['pedidos_mesa']; ?></div>
                            <div class="stats-label">Mesa</div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-line me-2"></i>
                                Vendas por Dia
                            </h5>
                            <canvas id="vendasChart" height="100"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-pie me-2"></i>
                                Status dos Pedidos
                            </h5>
                            <canvas id="statusChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tables -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-card">
                            <h5 class="mb-3">
                                <i class="fas fa-trophy me-2"></i>
                                Produtos Mais Vendidos
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Quantidade</th>
                                            <th>Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produtos_mais_vendidos as $produto): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                                <td><?php echo $produto['total_vendido']; ?></td>
                                                <td>R$ <?php echo number_format($produto['valor_total'], 2, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="table-card">
                            <h5 class="mb-3">
                                <i class="fas fa-list me-2"></i>
                                Resumo por Status
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Quantidade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status_pedidos as $status): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $status['status']; ?></span>
                                                </td>
                                                <td><?php echo $status['quantidade']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Vendas Chart
        const vendasCtx = document.getElementById('vendasChart').getContext('2d');
        const vendasData = {
            labels: [<?php echo implode(',', array_map(function($v) { return "'" . date('d/m', strtotime($v['data'])) . "'"; }, $vendas_por_dia)); ?>],
            datasets: [{
                label: 'Valor (R$)',
                data: [<?php echo implode(',', array_column($vendas_por_dia, 'valor')); ?>],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Quantidade',
                data: [<?php echo implode(',', array_column($vendas_por_dia, 'quantidade')); ?>],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        };

        new Chart(vendasCtx, {
            type: 'line',
            data: vendasData,
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['status'] . "'"; }, $status_pedidos)); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($status_pedidos, 'quantidade')); ?>],
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
            }]
        };

        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        function exportarExcel() {
            Swal.fire('Info', 'Funcionalidade de exportação Excel será implementada', 'info');
        }

        function exportarPDF() {
            Swal.fire('Info', 'Funcionalidade de exportação PDF será implementada', 'info');
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
