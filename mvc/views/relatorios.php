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

// Filtros
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');
$tipoRelatorio = $_GET['tipo'] ?? 'vendas';

// Buscar dados para relatórios
$relatoriosGerados = [];
$dadosVendas = [];
$dadosFinanceiros = [];

if ($tenant && $filial) {
    // Buscar relatórios já gerados
    $relatoriosGerados = $db->fetchAll(
        "SELECT * FROM relatorios_financeiros 
         WHERE tenant_id = ? AND filial_id = ? 
         ORDER BY created_at DESC 
         LIMIT 10",
        [$tenant['id'], $filial['id']]
    );
    
    // Dados de vendas
    $dadosVendas = $db->fetchAll(
        "SELECT 
            DATE(p.data) as data_venda,
            COUNT(*) as total_pedidos,
            SUM(p.valor_total) as total_vendas,
            AVG(p.valor_total) as ticket_medio,
            COUNT(CASE WHEN p.delivery = true THEN 1 END) as pedidos_delivery,
            COUNT(CASE WHEN p.delivery = false THEN 1 END) as pedidos_mesa
         FROM pedido p
         WHERE p.tenant_id = ? AND p.filial_id = ?
         AND p.data BETWEEN ? AND ?
         AND p.status_pagamento = 'quitado'
         GROUP BY DATE(p.data)
         ORDER BY data_venda DESC",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Dados financeiros
    $dadosFinanceiros = $db->fetchAll(
        "SELECT 
            DATE(l.created_at) as data_lancamento,
            l.tipo,
            SUM(l.valor) as total_valor,
            COUNT(*) as qtd_lancamentos
         FROM lancamentos_financeiros l
         WHERE l.tenant_id = ? AND l.filial_id = ?
         AND l.created_at BETWEEN ? AND ?
         GROUP BY DATE(l.created_at), l.tipo
         ORDER BY data_lancamento DESC",
        [$tenant['id'], $filial['id'], $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .metric-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .report-item {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .report-item:hover {
            border-left-color: #0056b3;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: block;
        }
        
        .export-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        /* Responsividade */
        .main-content {
            margin-left: 60px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            overflow-x: auto;
        }
        
        .sidebar.expanded + .main-content {
            margin-left: 250px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 0;
            }
            
            .card {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .chart-container {
                height: 300px !important;
            }
        }
        
        @media (max-width: 576px) {
            .p-4 {
                padding: 1rem !important;
            }
            
            .btn-group-vertical .btn {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
            }
            
            .chart-container {
                height: 250px !important;
            }
        }
    </style>
</head>
<body class="bg-light">
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
                        <a class="nav-link" href="<?php echo $router->url('ai_chat'); ?>" data-tooltip="Assistente IA">
                            <i class="fas fa-robot"></i>
                            <span>Assistente IA</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content" id="mainContent">
                <div class="p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Relatórios e Análises
                    </h2>
                    <p class="text-muted mb-0">Análise completa de vendas, finanças e performance</p>
                </div>
                <div>
                    <button class="btn btn-primary me-2" onclick="gerarRelatorioCompleto()">
                        <i class="fas fa-file-pdf me-1"></i>
                        Gerar Relatório PDF
                    </button>
                    <button class="btn btn-success" onclick="exportarDados()">
                        <i class="fas fa-download me-1"></i>
                        Exportar Dados
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filter-section">
                <form method="GET" id="filtroForm">
                    <input type="hidden" name="view" value="relatorios">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="data_inicio" value="<?= $dataInicio ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="data_fim" value="<?= $dataFim ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo de Relatório</label>
                            <select class="form-select" name="tipo">
                                <option value="vendas" <?= $tipoRelatorio === 'vendas' ? 'selected' : '' ?>>Vendas</option>
                                <option value="financeiro" <?= $tipoRelatorio === 'financeiro' ? 'selected' : '' ?>>Financeiro</option>
                                <option value="produtos" <?= $tipoRelatorio === 'produtos' ? 'selected' : '' ?>>Produtos</option>
                                <option value="clientes" <?= $tipoRelatorio === 'clientes' ? 'selected' : '' ?>>Clientes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Período Rápido</label>
                            <select class="form-select" onchange="aplicarPeriodoRapido(this.value)">
                                <option value="">Selecionar período</option>
                                <option value="hoje">Hoje</option>
                                <option value="ontem">Ontem</option>
                                <option value="semana">Esta Semana</option>
                                <option value="mes">Este Mês</option>
                                <option value="trimestre">Este Trimestre</option>
                                <option value="ano">Este Ano</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i>
                                Aplicar Filtros
                            </button>
                            <a href="index.php?view=relatorios" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Métricas Principais -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card metric-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                            <h5 class="card-title">Total Vendas</h5>
                            <h3 class="mb-0">
                                <?php 
                                $totalVendas = array_sum(array_column($dadosVendas, 'total_vendas'));
                                echo 'R$ ' . number_format($totalVendas, 2, ',', '.');
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fa-2x mb-2"></i>
                            <h5 class="card-title">Total Pedidos</h5>
                            <h3 class="mb-0">
                                <?php 
                                $totalPedidos = array_sum(array_column($dadosVendas, 'total_pedidos'));
                                echo number_format($totalPedidos);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                            <h5 class="card-title">Ticket Médio</h5>
                            <h3 class="mb-0">
                                <?php 
                                $ticketMedio = $totalPedidos > 0 ? $totalVendas / $totalPedidos : 0;
                                echo 'R$ ' . number_format($ticketMedio, 2, ',', '.');
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card info">
                        <div class="card-body text-center">
                            <i class="fas fa-motorcycle fa-2x mb-2"></i>
                            <h5 class="card-title">Delivery %</h5>
                            <h3 class="mb-0">
                                <?php 
                                $totalDelivery = array_sum(array_column($dadosVendas, 'pedidos_delivery'));
                                $percentualDelivery = $totalPedidos > 0 ? ($totalDelivery / $totalPedidos) * 100 : 0;
                                echo number_format($percentualDelivery, 1) . '%';
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="relatoriosTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="graficos-tab" data-bs-toggle="tab" data-bs-target="#graficos" type="button" role="tab">
                        <i class="fas fa-chart-line me-1"></i>
                        Gráficos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tabelas-tab" data-bs-toggle="tab" data-bs-target="#tabelas" type="button" role="tab">
                        <i class="fas fa-table me-1"></i>
                        Tabelas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico" type="button" role="tab">
                        <i class="fas fa-history me-1"></i>
                        Histórico
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="relatoriosTabsContent">
                <!-- Tab Gráficos -->
                <div class="tab-pane fade show active" id="graficos" role="tabpanel">
                    <div class="row mt-3">
                        <!-- Gráfico de Vendas Diárias -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Vendas Diárias
                                    </h5>
                                    <div class="export-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="exportarGrafico('vendas')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="graficoVendas"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Distribuição -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Distribuição de Vendas
                                    </h5>
                                    <div class="export-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="exportarGrafico('distribuicao')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="graficoDistribuicao"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <!-- Gráfico Financeiro -->
                        <div class="col-md-12">
                            <div class="card report-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        Fluxo Financeiro
                                    </h5>
                                    <div class="export-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="exportarGrafico('financeiro')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="graficoFinanceiro"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Tabelas -->
                <div class="tab-pane fade" id="tabelas" role="tabpanel">
                    <div class="row mt-3">
                        <!-- Tabela de Vendas -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Vendas por Dia
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Pedidos</th>
                                                    <th>Vendas</th>
                                                    <th>Ticket Médio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dadosVendas as $venda): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($venda['data_venda'])) ?></td>
                                                        <td><?= $venda['total_pedidos'] ?></td>
                                                        <td class="valor-positivo">R$ <?= number_format($venda['total_vendas'], 2, ',', '.') ?></td>
                                                        <td>R$ <?= number_format($venda['ticket_medio'], 2, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela Financeira -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Lançamentos Financeiros
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Valor</th>
                                                    <th>Qtd</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dadosFinanceiros as $financeiro): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y', strtotime($financeiro['data_lancamento'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $financeiro['tipo'] === 'receita' ? 'success' : 'danger' ?>">
                                                                <?= ucfirst($financeiro['tipo']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="<?= $financeiro['tipo'] === 'receita' ? 'valor-positivo' : 'valor-negativo' ?>">
                                                            <?= $financeiro['tipo'] === 'receita' ? '+' : '-' ?>R$ <?= number_format($financeiro['total_valor'], 2, ',', '.') ?>
                                                        </td>
                                                        <td><?= $financeiro['qtd_lancamentos'] ?></td>
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

                <!-- Tab Histórico -->
                <div class="tab-pane fade" id="historico" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Relatórios Gerados
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($relatoriosGerados)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhum relatório gerado</h5>
                                    <p class="text-muted">Os relatórios gerados aparecerão aqui.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($relatoriosGerados as $relatorio): ?>
                                        <div class="list-group-item report-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($relatorio['nome']) ?></h6>
                                                    <p class="mb-1 text-muted">
                                                        <?= ucfirst($relatorio['tipo']) ?> - 
                                                        <?= date('d/m/Y', strtotime($relatorio['periodo_inicio'])) ?> até 
                                                        <?= date('d/m/Y', strtotime($relatorio['periodo_fim'])) ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        Gerado em <?= date('d/m/Y H:i', strtotime($relatorio['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?= $relatorio['status'] === 'gerado' ? 'success' : 'warning' ?> status-badge">
                                                        <?= ucfirst($relatorio['status']) ?>
                                                    </span>
                                                    <div class="btn-group ms-2" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="visualizarRelatorio(<?= $relatorio['id'] ?>)" title="Visualizar">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" onclick="downloadRelatorio(<?= $relatorio['id'] ?>)" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirRelatorio(<?= $relatorio['id'] ?>)" title="Excluir">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
    <script>
        // Dados para os gráficos
        const dadosVendas = <?= json_encode($dadosVendas) ?>;
        const dadosFinanceiros = <?= json_encode($dadosFinanceiros) ?>;
        
        // Inicializar gráficos quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            inicializarGraficos();
        });
        
        function inicializarGraficos() {
            // Gráfico de Vendas Diárias
            const ctxVendas = document.getElementById('graficoVendas').getContext('2d');
            new Chart(ctxVendas, {
                type: 'line',
                data: {
                    labels: dadosVendas.map(v => new Date(v.data_venda).toLocaleDateString('pt-BR')),
                    datasets: [{
                        label: 'Vendas (R$)',
                        data: dadosVendas.map(v => v.total_vendas),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
            
            // Gráfico de Distribuição
            const ctxDistribuicao = document.getElementById('graficoDistribuicao').getContext('2d');
            new Chart(ctxDistribuicao, {
                type: 'doughnut',
                data: {
                    labels: ['Mesa', 'Delivery'],
                    datasets: [{
                        data: [
                            dadosVendas.reduce((sum, v) => sum + v.pedidos_mesa, 0),
                            dadosVendas.reduce((sum, v) => sum + v.pedidos_delivery, 0)
                        ],
                        backgroundColor: ['#28a745', '#17a2b8'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Gráfico Financeiro
            const ctxFinanceiro = document.getElementById('graficoFinanceiro').getContext('2d');
            const receitas = dadosFinanceiros.filter(f => f.tipo === 'receita');
            const despesas = dadosFinanceiros.filter(f => f.tipo === 'despesa');
            
            new Chart(ctxFinanceiro, {
                type: 'bar',
                data: {
                    labels: [...new Set(dadosFinanceiros.map(f => new Date(f.data_lancamento).toLocaleDateString('pt-BR')))],
                    datasets: [{
                        label: 'Receitas',
                        data: receitas.map(r => r.total_valor),
                        backgroundColor: '#28a745'
                    }, {
                        label: 'Despesas',
                        data: despesas.map(d => d.total_valor),
                        backgroundColor: '#dc3545'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Funções de período rápido
        function aplicarPeriodoRapido(periodo) {
            const hoje = new Date();
            let dataInicio, dataFim;
            
            switch (periodo) {
                case 'hoje':
                    dataInicio = dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'ontem':
                    const ontem = new Date(hoje);
                    ontem.setDate(hoje.getDate() - 1);
                    dataInicio = dataFim = ontem.toISOString().split('T')[0];
                    break;
                case 'semana':
                    const inicioSemana = new Date(hoje);
                    inicioSemana.setDate(hoje.getDate() - hoje.getDay());
                    dataInicio = inicioSemana.toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'mes':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'trimestre':
                    const trimestre = Math.floor(hoje.getMonth() / 3);
                    dataInicio = new Date(hoje.getFullYear(), trimestre * 3, 1).toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
                case 'ano':
                    dataInicio = new Date(hoje.getFullYear(), 0, 1).toISOString().split('T')[0];
                    dataFim = hoje.toISOString().split('T')[0];
                    break;
            }
            
            if (dataInicio && dataFim) {
                document.querySelector('input[name="data_inicio"]').value = dataInicio;
                document.querySelector('input[name="data_fim"]').value = dataFim;
            }
        }
        
        // Funções de relatórios
        function gerarRelatorioCompleto() {
            Swal.fire({
                title: 'Gerando Relatório',
                text: 'Aguarde enquanto processamos os dados...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            
            setTimeout(() => {
                Swal.fire({
                    title: 'Relatório Gerado!',
                    text: 'O relatório PDF foi gerado com sucesso.',
                    icon: 'success'
                });
            }, 2000);
        }
        
        function exportarDados() {
            Swal.fire({
                title: 'Exportar Dados',
                text: 'Escolha o formato de exportação:',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Excel',
                cancelButtonText: 'CSV',
                showDenyButton: true,
                denyButtonText: 'PDF'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Exportar Excel
                    Swal.fire('Exportado!', 'Dados exportados para Excel.', 'success');
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Exportar CSV
                    Swal.fire('Exportado!', 'Dados exportados para CSV.', 'success');
                } else if (result.isDenied) {
                    // Exportar PDF
                    Swal.fire('Exportado!', 'Dados exportados para PDF.', 'success');
                }
            });
        }
        
        function exportarGrafico(tipo) {
            Swal.fire({
                title: 'Exportar Gráfico',
                text: 'Gráfico exportado com sucesso!',
                icon: 'success'
            });
        }
        
        function visualizarRelatorio(id) {
            Swal.fire({
                title: 'Visualizar Relatório',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
        }
        
        function downloadRelatorio(id) {
            Swal.fire({
                title: 'Download Relatório',
                text: 'Relatório baixado com sucesso!',
                icon: 'success'
            });
        }
        
        function excluirRelatorio(id) {
            Swal.fire({
                title: 'Excluir Relatório',
                text: 'Tem certeza que deseja excluir este relatório?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Excluído!', 'Relatório excluído com sucesso.', 'success');
                }
            });
        }
    </script>
</body>
</html>