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

// Buscar relatórios já gerados
$relatoriosGerados = $db->fetchAll(
    "SELECT * FROM relatorios_financeiros 
     WHERE tenant_id = ? AND filial_id = ? 
     ORDER BY created_at DESC 
     LIMIT 20",
    [$tenant['id'], $filial['id']]
);

// Buscar categorias para filtros
$categorias = $db->fetchAll(
    "SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY tipo, nome",
    [$tenant['id'], $filial['id']]
);

$contas = $db->fetchAll(
    "SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
    [$tenant['id'], $filial['id']]
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Relatórios - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
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
            cursor: pointer;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
        }
        
        .report-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: block;
        }
        
        .report-preview {
            max-height: 500px;
            overflow-y: auto;
        }
        
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
                                Gerar Relatórios Financeiros
                            </h2>
                            <p class="text-muted mb-0">Análise completa de receitas, despesas e fluxo de caixa</p>
                        </div>
                        <div>
                            <a href="<?php echo $router->url('financeiro'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Voltar
                            </a>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="filter-section">
                        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros do Relatório</h5>
                        <form id="filtroRelatorioForm">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Data Início</label>
                                    <input type="date" class="form-control" name="data_inicio" id="data_inicio" value="<?= date('Y-m-01') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data Fim</label>
                                    <input type="date" class="form-control" name="data_fim" id="data_fim" value="<?= date('Y-m-t') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Categoria</label>
                                    <select class="form-select" name="categoria_id" id="categoria_id">
                                        <option value="">Todas as categorias</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>">
                                                <?= htmlspecialchars($categoria['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Conta</label>
                                    <select class="form-select" name="conta_id" id="conta_id">
                                        <option value="">Todas as contas</option>
                                        <?php foreach ($contas as $conta): ?>
                                            <option value="<?= $conta['id'] ?>">
                                                <?= htmlspecialchars($conta['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tipos de Relatórios -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Tipos de Relatórios</h5>
                        </div>
                        
                        <!-- Fluxo de Caixa -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('fluxo_caixa')">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line report-icon text-primary"></i>
                                    <h5>Fluxo de Caixa</h5>
                                    <p class="text-muted">Análise de entradas e saídas por período</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Gráfico de linha</span>
                                        <span>Detalhado</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Receitas por Categoria -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('receitas_categoria')">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-pie report-icon text-success"></i>
                                    <h5>Receitas por Categoria</h5>
                                    <p class="text-muted">Distribuição das receitas por categoria</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Gráfico pizza</span>
                                        <span>Comparativo</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Despesas por Categoria -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('despesas_categoria')">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar report-icon text-warning"></i>
                                    <h5>Despesas por Categoria</h5>
                                    <p class="text-muted">Análise das despesas por categoria</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Gráfico barras</span>
                                        <span>Detalhado</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lucro/Prejuízo -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('lucro_prejuizo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-balance-scale report-icon text-info"></i>
                                    <h5>Lucro/Prejuízo</h5>
                                    <p class="text-muted">Análise de lucratividade do período</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Comparativo</span>
                                        <span>Mensal</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vendas por Período -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('vendas_periodo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-cart report-icon text-primary"></i>
                                    <h5>Vendas por Período</h5>
                                    <p class="text-muted">Evolução das vendas ao longo do tempo</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Gráfico linha</span>
                                        <span>Evolutivo</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Relatório Completo -->
                        <div class="col-md-4 mb-3">
                            <div class="card report-card" onclick="gerarRelatorio('completo')">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt report-icon text-dark"></i>
                                    <h5>Relatório Completo</h5>
                                    <p class="text-muted">Relatório abrangente com todos os dados</p>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>PDF/Excel</span>
                                        <span>Completo</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Área de Visualização -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-area me-2"></i>
                                        Visualização do Relatório
                                    </h5>
                                    <div>
                                        <button class="btn btn-outline-primary btn-sm" onclick="exportarRelatorio()" id="btnExportar" disabled>
                                            <i class="fas fa-download me-1"></i>
                                            Exportar
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="imprimirRelatorio()" id="btnImprimir" disabled>
                                            <i class="fas fa-print me-1"></i>
                                            Imprimir
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="relatorioContent">
                                        <div class="text-center py-5">
                                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">Selecione um tipo de relatório</h5>
                                            <p class="text-muted">Escolha um dos tipos de relatório acima para visualizar os dados</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Relatórios Gerados -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history me-2"></i>
                                        Relatórios Gerados Recentemente
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($relatoriosGerados)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">Nenhum relatório gerado ainda</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Nome</th>
                                                        <th>Tipo</th>
                                                        <th>Período</th>
                                                        <th>Status</th>
                                                        <th>Data</th>
                                                        <th>Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($relatoriosGerados as $relatorio): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($relatorio['nome']) ?></strong>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary">
                                                                    <?= ucfirst($relatorio['tipo']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?= date('d/m/Y', strtotime($relatorio['periodo_inicio'])) ?> - 
                                                                <?= date('d/m/Y', strtotime($relatorio['periodo_fim'])) ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $relatorio['status'] === 'gerado' ? 'success' : ($relatorio['status'] === 'gerando' ? 'warning' : 'danger') ?>">
                                                                    <?= ucfirst($relatorio['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?= date('d/m/Y H:i', strtotime($relatorio['created_at'])) ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <button class="btn btn-sm btn-outline-primary" onclick="visualizarRelatorio(<?= $relatorio['id'] ?>)" title="Visualizar">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-success" onclick="baixarRelatorio(<?= $relatorio['id'] ?>)" title="Baixar">
                                                                        <i class="fas fa-download"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirRelatorio(<?= $relatorio['id'] ?>)" title="Excluir">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
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
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/financeiro.js"></script>
    <script>
        let currentReportData = null;
        let currentReportType = null;

        // Inicializar Select2
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });
        });

        // Gerar relatório
        function gerarRelatorio(tipo) {
            const formData = new FormData(document.getElementById('filtroRelatorioForm'));
            formData.append('action', 'gerar_relatorio');
            formData.append('tipo', tipo);

            // Mostrar loading
            Swal.fire({
                title: 'Gerando Relatório...',
                text: 'Processando dados financeiros...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentReportData = data.data;
                    currentReportType = tipo;
                    exibirRelatorio(data.data, tipo);
                    document.getElementById('btnExportar').disabled = false;
                    document.getElementById('btnImprimir').disabled = false;
                    Swal.close();
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao gerar relatório', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Exibir relatório
        function exibirRelatorio(dados, tipo) {
            const content = document.getElementById('relatorioContent');
            
            switch(tipo) {
                case 'fluxo_caixa':
                    exibirFluxoCaixa(dados);
                    break;
                case 'receitas_categoria':
                    exibirReceitasCategoria(dados);
                    break;
                case 'despesas_categoria':
                    exibirDespesasCategoria(dados);
                    break;
                case 'lucro_prejuizo':
                    exibirLucroPrejuizo(dados);
                    break;
                case 'vendas_periodo':
                    exibirVendasPeriodo(dados);
                    break;
                case 'completo':
                    exibirRelatorioCompleto(dados);
                    break;
                default:
                    content.innerHTML = '<div class="text-center py-5"><h5 class="text-muted">Tipo de relatório não reconhecido</h5></div>';
            }
        }

        // Fluxo de Caixa
        function exibirFluxoCaixa(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5>Total Receitas</h5>
                                <h3>R$ ${dados.total_receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5>Total Despesas</h5>
                                <h3>R$ ${dados.total_despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5>Saldo Líquido</h5>
                                <h3>R$ ${dados.saldo_liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5>Total Lançamentos</h5>
                                <h3>${dados.total_lancamentos}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="fluxoCaixaChart"></canvas>
                </div>
            `;

            // Criar gráfico
            const ctx = document.getElementById('fluxoCaixaChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.periodos,
                    datasets: [{
                        label: 'Receitas',
                        data: dados.receitas,
                        borderColor: '#28a745',
                        backgroundColor: '#28a74520',
                        tension: 0.4
                    }, {
                        label: 'Despesas',
                        data: dados.despesas,
                        borderColor: '#dc3545',
                        backgroundColor: '#dc354520',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Fluxo de Caixa por Período'
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

        // Receitas por Categoria
        function exibirReceitasCategoria(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="receitasChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Detalhamento por Categoria</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dados.categorias.map(cat => `
                                        <tr>
                                            <td>${cat.nome}</td>
                                            <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                            <td>${cat.percentual.toFixed(1)}%</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            // Criar gráfico pizza
            const ctx = document.getElementById('receitasChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: dados.categorias.map(cat => cat.nome),
                    datasets: [{
                        data: dados.categorias.map(cat => cat.valor),
                        backgroundColor: [
                            '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1',
                            '#fd7e14', '#20c997', '#6c757d', '#e83e8c', '#343a40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Receitas por Categoria'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Despesas por Categoria
        function exibirDespesasCategoria(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <canvas id="despesasChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h5>Resumo</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Despesas:</span>
                                    <strong>R$ ${dados.total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Maior Categoria:</span>
                                    <strong>${dados.maior_categoria}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Menor Categoria:</span>
                                    <strong>${dados.menor_categoria}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Criar gráfico barras
            const ctx = document.getElementById('despesasChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.categorias.map(cat => cat.nome),
                    datasets: [{
                        label: 'Despesas',
                        data: dados.categorias.map(cat => cat.valor),
                        backgroundColor: '#dc3545',
                        borderColor: '#dc3545',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Despesas por Categoria'
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

        // Lucro/Prejuízo
        function exibirLucroPrejuizo(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="lucroChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Análise de Lucratividade</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Receitas:</span>
                                    <span class="text-success">R$ ${dados.receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Despesas:</span>
                                    <span class="text-danger">R$ ${dados.despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Lucro/Prejuízo:</strong>
                                    <strong class="${dados.lucro >= 0 ? 'text-success' : 'text-danger'}">
                                        R$ ${dados.lucro.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                                    </strong>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <span>Margem:</span>
                                    <span class="${dados.margem >= 0 ? 'text-success' : 'text-danger'}">
                                        ${dados.margem.toFixed(1)}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Criar gráfico
            const ctx = document.getElementById('lucroChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.periodos,
                    datasets: [{
                        label: 'Lucro/Prejuízo',
                        data: dados.lucros,
                        backgroundColor: dados.lucros.map(val => val >= 0 ? '#28a745' : '#dc3545'),
                        borderColor: dados.lucros.map(val => val >= 0 ? '#28a745' : '#dc3545'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Lucro/Prejuízo por Período'
                        }
                    },
                    scales: {
                        y: {
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

        // Vendas por Período
        function exibirVendasPeriodo(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <canvas id="vendasChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h5>Estatísticas</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Vendas:</span>
                                    <strong>R$ ${dados.total_vendas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Ticket Médio:</span>
                                    <strong>R$ ${dados.ticket_medio.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Pedidos:</span>
                                    <strong>${dados.total_pedidos}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Melhor Dia:</span>
                                    <strong>${dados.melhor_dia}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Criar gráfico
            const ctx = document.getElementById('vendasChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.periodos,
                    datasets: [{
                        label: 'Vendas',
                        data: dados.vendas,
                        borderColor: '#007bff',
                        backgroundColor: '#007bff20',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Vendas por Período'
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

        // Relatório Completo
        function exibirRelatorioCompleto(dados) {
            const content = document.getElementById('relatorioContent');
            content.innerHTML = `
                <div class="report-preview">
                    <h4>Relatório Financeiro Completo</h4>
                    <p class="text-muted">Período: ${dados.periodo_inicio} a ${dados.periodo_fim}</p>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Receitas</h5>
                                    <h3>R$ ${dados.resumo.receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>Despesas</h5>
                                    <h3>R$ ${dados.resumo.despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Saldo Líquido</h5>
                                    <h3>R$ ${dados.resumo.saldo_liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Lançamentos</h5>
                                    <h3>${dados.resumo.total_lancamentos}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5>Top 5 Receitas por Categoria</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dados.top_receitas.map(cat => `
                                        <tr>
                                            <td>${cat.nome}</td>
                                            <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Top 5 Despesas por Categoria</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dados.top_despesas.map(cat => `
                                        <tr>
                                            <td>${cat.nome}</td>
                                            <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        // Exportar relatório
        function exportarRelatorio() {
            if (!currentReportData) {
                Swal.fire('Aviso!', 'Nenhum relatório selecionado', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'exportar_relatorio');
            formData.append('tipo', currentReportType);
            formData.append('dados', JSON.stringify(currentReportData));

            Swal.fire({
                title: 'Exportando...',
                text: 'Preparando arquivo para download...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `relatorio_${currentReportType}_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                Swal.close();
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao exportar relatório', 'error');
            });
        }

        // Imprimir relatório
        function imprimirRelatorio() {
            if (!currentReportData) {
                Swal.fire('Aviso!', 'Nenhum relatório selecionado', 'warning');
                return;
            }

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Relatório Financeiro</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .summary { display: flex; justify-content: space-around; margin: 20px 0; }
                            .summary-item { text-align: center; padding: 10px; border: 1px solid #ddd; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Relatório Financeiro</h1>
                            <p>Gerado em: ${new Date().toLocaleDateString('pt-BR')}</p>
                        </div>
                        <div id="printContent">
                            ${document.getElementById('relatorioContent').innerHTML}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Visualizar relatório salvo
        function visualizarRelatorio(id) {
            Swal.fire({
                title: 'Carregando...',
                text: 'Buscando relatório...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch(`mvc/ajax/financeiro.php?action=visualizar_relatorio&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentReportData = data.data;
                    currentReportType = data.tipo;
                    exibirRelatorio(data.data, data.tipo);
                    document.getElementById('btnExportar').disabled = false;
                    document.getElementById('btnImprimir').disabled = false;
                    Swal.close();
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao carregar relatório', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Baixar relatório
        function baixarRelatorio(id) {
            window.open(`mvc/ajax/financeiro.php?action=baixar_relatorio&id=${id}`, '_blank');
        }

        // Excluir relatório
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
                    fetch('mvc/ajax/financeiro.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=excluir_relatorio&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Excluído!', 'Relatório excluído com sucesso.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Erro!', data.message || 'Erro ao excluir relatório', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>
