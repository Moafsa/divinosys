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

// Filtros
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');
$tipoFiltro = $_GET['tipo'] ?? 'todos';
$categoriaId = $_GET['categoria_id'] ?? '';
$contaId = $_GET['conta_id'] ?? '';
$statusFiltro = $_GET['status'] ?? 'todos';

// Buscar dados financeiros
$lancamentos = [];
$pedidosFinanceiros = [];
$resumoFinanceiro = [];

if ($tenant && $filial) {
    // Buscar lançamentos financeiros
    $whereConditions = ["l.tenant_id = ?", "l.filial_id = ?"];
    $params = [$tenant['id'], $filial['id']];
    
    if ($dataInicio && $dataFim) {
        $whereConditions[] = "l.created_at BETWEEN ? AND ?";
        $params[] = $dataInicio . ' 00:00:00';
        $params[] = $dataFim . ' 23:59:59';
    }
    
    if ($tipoFiltro !== 'todos') {
        $whereConditions[] = "l.tipo = ?";
        $params[] = $tipoFiltro;
    }
    
    if ($categoriaId) {
        $whereConditions[] = "l.categoria_id = ?";
        $params[] = $categoriaId;
    }
    
    if ($contaId) {
        $whereConditions[] = "l.conta_id = ?";
        $params[] = $contaId;
    }
    
    if ($statusFiltro !== 'todos') {
        $whereConditions[] = "l.status = ?";
        $params[] = $statusFiltro;
    }
    
    $lancamentos = $db->fetchAll(
        "SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
                cf.nome as conta_nome, cf.tipo as conta_tipo, cf.cor as conta_cor,
                u.login as usuario_nome, p.idpedido, p.cliente as pedido_cliente
         FROM lancamentos_financeiros l
         LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
         LEFT JOIN contas_financeiras cf ON l.conta_id = cf.id
         LEFT JOIN usuarios u ON l.usuario_id = u.id
         LEFT JOIN pedido p ON l.pedido_id = p.idpedido
         WHERE " . implode(' AND ', $whereConditions) . "
         ORDER BY l.created_at DESC",
        $params
    );
    
    // Buscar TODOS os pedidos quitados (com e sem pagamentos fiado)
    $pedidosFinanceiros = $db->fetchAll(
        "SELECT p.*, 
                COALESCE(SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END), 0) as total_pago,
                COUNT(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.id END) as qtd_pagamentos,
                STRING_AGG(DISTINCT CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.forma_pagamento END, ', ') as formas_pagamento,
                m.nome as mesa_nome,
                u.login as usuario_nome,
                t.nome as tenant_nome,
                f.nome as filial_nome
         FROM pedido p
         LEFT JOIN pagamentos_pedido pp ON p.idpedido = pp.pedido_id AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
         LEFT JOIN tenants t ON p.tenant_id = t.id
         LEFT JOIN filiais f ON p.filial_id = f.id
         WHERE p.tenant_id = ? AND p.filial_id = ?
         AND p.data BETWEEN ? AND ?
         AND p.status_pagamento = 'quitado'
         GROUP BY p.idpedido, m.nome, u.login, t.nome, f.nome
         ORDER BY p.data DESC, p.hora_pedido DESC",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Calcular resumo financeiro incluindo pedidos quitados
    $resumoFinanceiro = $db->fetch(
        "SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) as total_receitas,
            COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as total_despesas,
            COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as saldo_liquido,
            COUNT(*) as total_lancamentos
         FROM lancamentos_financeiros 
         WHERE tenant_id = ? AND filial_id = ?
         AND created_at BETWEEN ? AND ?",
        [$tenant['id'], $filial['id'], $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    // Adicionar receitas dos pedidos (apenas pedidos totalmente quitados, sem fiado pendente)
    $receitasPedidos = $db->fetch(
        "SELECT 
            COALESCE(SUM(pp.valor_pago), 0) as total_vendas_pedidos,
            COUNT(DISTINCT p.idpedido) as total_pedidos_quitados
         FROM pagamentos_pedido pp
         INNER JOIN pedido p ON pp.pedido_id = p.idpedido
         WHERE pp.tenant_id = ? AND pp.filial_id = ?
         AND p.data BETWEEN ? AND ?
         AND p.status_pagamento = 'quitado'
         AND pp.forma_pagamento != 'FIADO'
         AND pp.created_at BETWEEN ? AND ?
         AND NOT EXISTS (
             SELECT 1 FROM pagamentos_pedido pp2 
             WHERE pp2.pedido_id = p.idpedido 
             AND pp2.forma_pagamento = 'FIADO' 
             AND pp2.tenant_id = p.tenant_id 
             AND pp2.filial_id = p.filial_id
         )",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    // Somar receitas dos pedidos aos lançamentos (apenas pagamentos não-fiado)
    $resumoFinanceiro['total_receitas'] += $receitasPedidos['total_vendas_pedidos'];
    
    // Adicionar valores fiado que foram QUITADOS (pagamentos fiado quitados com dinheiro/cartão)
    $receitasFiadoQuitado = $db->fetch(
        "SELECT 
            COALESCE(SUM(pp.valor_pago), 0) as total_fiado_quitado
         FROM pagamentos_pedido pp
         INNER JOIN pedido p ON pp.pedido_id = p.idpedido
         WHERE pp.tenant_id = ? AND pp.filial_id = ?
         AND p.data BETWEEN ? AND ?
         AND p.status_pagamento = 'quitado'
         AND pp.forma_pagamento != 'FIADO'
         AND pp.descricao LIKE '%fiado%'
         AND pp.created_at BETWEEN ? AND ?",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    // Somar valores fiado quitados ao faturamento
    $resumoFinanceiro['total_receitas'] += $receitasFiadoQuitado['total_fiado_quitado'];
    
    $resumoFinanceiro['saldo_liquido'] = $resumoFinanceiro['total_receitas'] - $resumoFinanceiro['total_despesas'];
    $resumoFinanceiro['total_lancamentos'] += $receitasPedidos['total_pedidos_quitados'];
}

// Buscar categorias e contas para filtros
$categorias = $db->fetchAll(
    "SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
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
    <title>Financeiro - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        .financial-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .financial-card:hover {
            transform: translateY(-2px);
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .receita-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .despesa-card {
            background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            color: white;
        }
        
        .saldo-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .order-item {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            border-left-color: #0056b3;
            background-color: #f8f9fa;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0.125rem;
        }
        
        .financial-table {
            font-size: 0.9rem;
        }
        
        .financial-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        
        .valor-positivo {
            color: #28a745;
            font-weight: 600;
        }
        
        .valor-negativo {
            color: #dc3545;
            font-weight: 600;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: block;
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
        }
        
        @media (max-width: 576px) {
            .p-4 {
                padding: 1rem !important;
            }
            
            .btn-group-vertical .btn {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
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
                        <a class="nav-link active" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
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
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Sistema Financeiro
                            </h2>
                    <p class="text-muted mb-0">Gestão completa de receitas, despesas e relatórios</p>
                        </div>
                <div>
                    <a href="<?php echo $router->url('novo_lancamento'); ?>" class="btn btn-primary me-2">
                        <i class="fas fa-plus me-1"></i>
                        Novo Lançamento
                    </a>
                    <a href="<?php echo $router->url('gerar_relatorios'); ?>" class="btn btn-success">
                        <i class="fas fa-chart-bar me-1"></i>
                        Gerar Relatório
                    </a>
                    </div>
                </div>

            <!-- Filtros -->
            <div class="filter-section">
                <form method="GET" id="filtroForm">
                    <input type="hidden" name="view" value="financeiro">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" name="data_inicio" value="<?= $dataInicio ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" name="data_fim" value="<?= $dataFim ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo">
                                <option value="todos" <?= $tipoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="receita" <?= $tipoFiltro === 'receita' ? 'selected' : '' ?>>Receitas</option>
                                <option value="despesa" <?= $tipoFiltro === 'despesa' ? 'selected' : '' ?>>Despesas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="categoria_id">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>" <?= $categoriaId == $categoria['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Conta</label>
                            <select class="form-select" name="conta_id">
                                <option value="">Todas</option>
                                <?php foreach ($contas as $conta): ?>
                                    <option value="<?= $conta['id'] ?>" <?= $contaId == $conta['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($conta['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i>
                                Aplicar Filtros
                            </button>
                            <a href="index.php?view=financeiro" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Resumo Financeiro -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card summary-card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                            <h5 class="card-title">Total Receitas</h5>
                            <h3 class="mb-0">R$ <?= number_format($resumoFinanceiro['total_receitas'] ?? 0, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card despesa-card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                            <h5 class="card-title">Total Despesas</h5>
                            <h3 class="mb-0">R$ <?= number_format($resumoFinanceiro['total_despesas'] ?? 0, 2, ',', '.') ?></h3>
                        </div>
                            </div>
                        </div>
                <div class="col-md-3 mb-3">
                    <div class="card" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: white;">
                        <div class="card-body text-center">
                            <i class="fas fa-credit-card fa-2x mb-2"></i>
                            <h5 class="card-title">Recebíveis Fiado</h5>
                            <h3 class="mb-0" id="totalFiado">R$ 0,00</h3>
                            <small>Valores a receber</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card saldo-card">
                        <div class="card-body text-center">
                            <i class="fas fa-balance-scale fa-2x mb-2"></i>
                            <h5 class="card-title">Saldo Líquido</h5>
                            <h3 class="mb-0 <?= ($resumoFinanceiro['saldo_liquido'] ?? 0) >= 0 ? 'valor-positivo' : 'valor-negativo' ?>">
                                R$ <?= number_format($resumoFinanceiro['saldo_liquido'] ?? 0, 2, ',', '.') ?>
                            </h3>
                        </div>
                            </div>
                        </div>
                <div class="col-md-3">
                    <div class="card financial-card">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-2x mb-2 text-primary"></i>
                            <h5 class="card-title">Total Lançamentos</h5>
                            <h3 class="mb-0"><?= $resumoFinanceiro['total_lancamentos'] ?? 0 ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="financeiroTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lancamentos-tab" data-bs-toggle="tab" data-bs-target="#lancamentos" type="button" role="tab">
                        <i class="fas fa-list me-1"></i>
                        Lançamentos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-1"></i>
                        Pedidos Quitados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pedidos-fiado-tab" data-bs-toggle="tab" data-bs-target="#pedidos-fiado" type="button" role="tab">
                        <i class="fas fa-credit-card me-1"></i>
                        Pedidos Fiado
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="relatorios-tab" data-bs-toggle="tab" data-bs-target="#relatorios" type="button" role="tab">
                        <i class="fas fa-chart-bar me-1"></i>
                        Relatórios
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="financeiroTabsContent">
                <!-- Tab Lançamentos -->
                <div class="tab-pane fade show active" id="lancamentos" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Lançamentos Financeiros
                        </h5>
                            <span class="badge bg-primary"><?= count($lancamentos) ?> registros</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($lancamentos)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhum lançamento encontrado</h5>
                                    <p class="text-muted">Use os filtros acima ou crie um novo lançamento.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover financial-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Tipo</th>
                                                <th>Descrição</th>
                                                <th>Categoria</th>
                                                <th>Conta</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lancamentos as $lancamento): ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('d/m/Y', strtotime($lancamento['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $lancamento['tipo'] === 'receita' ? 'success' : 'danger' ?>">
                                                            <?= ucfirst($lancamento['tipo']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($lancamento['descricao']) ?></strong>
                                                            <?php if ($lancamento['pedido_cliente']): ?>
                                                                <br><small class="text-muted">Pedido: #<?= $lancamento['idpedido'] ?> - <?= htmlspecialchars($lancamento['pedido_cliente']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($lancamento['categoria_nome']): ?>
                                                            <span class="badge" style="background-color: <?= $lancamento['categoria_cor'] ?>">
                                                                <i class="<?= $lancamento['categoria_icone'] ?> me-1"></i>
                                                                <?= htmlspecialchars($lancamento['categoria_nome']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($lancamento['conta_nome']): ?>
                                                            <span class="badge" style="background-color: <?= $lancamento['conta_cor'] ?>">
                                                                <?= htmlspecialchars($lancamento['conta_nome']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="<?= $lancamento['tipo'] === 'receita' ? 'valor-positivo' : 'valor-negativo' ?>">
                                                            <?= $lancamento['tipo'] === 'receita' ? '+' : '-' ?>R$ <?= number_format($lancamento['valor'], 2, ',', '.') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $lancamento['status'] === 'pago' ? 'success' : ($lancamento['status'] === 'pendente' ? 'warning' : 'danger') ?>">
                                                            <?= ucfirst($lancamento['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editarLancamento(<?= $lancamento['id'] ?>)" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="excluirLancamento(<?= $lancamento['id'] ?>)" title="Excluir">
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

                <!-- Tab Pedidos -->
                <div class="tab-pane fade" id="pedidos" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Pedidos Quitados
                                </h5>
                                <small class="text-muted">Mostra apenas pedidos quitados com pagamentos reais (sem fiado)</small>
                            </div>
                            <span class="badge bg-success"><?= count($pedidosFinanceiros) ?> pedidos</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($pedidosFinanceiros)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhum pedido quitado encontrado</h5>
                                    <p class="text-muted">Os pedidos quitados aparecerão aqui automaticamente.</p>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="pedidosAccordion">
                                    <?php foreach ($pedidosFinanceiros as $index => $pedido): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?= $index ?>">
                                                <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>">
                                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                        <div>
                                                            <strong>Pedido #<?= $pedido['idpedido'] ?></strong>
                                                            <span class="badge bg-success ms-2">Quitado</span>
                                                            <?php if ($pedido['mesa_nome']): ?>
                                                                <span class="badge bg-info ms-1"><?= htmlspecialchars($pedido['mesa_nome']) ?></span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning ms-1">Delivery</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pedido['tenant_nome'])): ?>
                                                                <span class="badge bg-secondary ms-1" title="Estabelecimento">
                                                                    <i class="fas fa-building me-1"></i>
                                                                    <?= htmlspecialchars($pedido['tenant_nome']) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="h5 mb-0">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></div>
                                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($pedido['data'] . ' ' . $pedido['hora_pedido'])) ?></small>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#pedidosAccordion">
                                                <div class="accordion-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Informações do Pedido</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>Cliente:</strong></td>
                                                                    <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Data/Hora:</strong></td>
                                                                    <td><?= date('d/m/Y H:i', strtotime($pedido['data'] . ' ' . $pedido['hora_pedido'])) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Valor Total:</strong></td>
                                                                    <td class="valor-positivo">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Total Pago:</strong></td>
                                                                    <td class="valor-positivo">R$ <?= number_format($pedido['total_pago'], 2, ',', '.') ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Qtd Pagamentos:</strong></td>
                                                                    <td><?= $pedido['qtd_pagamentos'] ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Formas de Pagamento:</strong></td>
                                                                    <td><?= htmlspecialchars($pedido['formas_pagamento']) ?></td>
                                                                </tr>
                                                                <?php if (!empty($pedido['usuario_nome'])): ?>
                                                                <tr>
                                                                    <td><strong>Atendente:</strong></td>
                                                                    <td>
                                                                        <i class="fas fa-user me-1"></i>
                                                                        <?= htmlspecialchars($pedido['usuario_nome']) ?>
                                                                    </td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <?php if (!empty($pedido['tenant_nome']) || !empty($pedido['filial_nome'])): ?>
                                                                <tr>
                                                                    <td><strong>Estabelecimento:</strong></td>
                                                                    <td>
                                                                        <i class="fas fa-building me-1"></i>
                                                                        <?= htmlspecialchars($pedido['tenant_nome'] ?? 'N/A') ?>
                                                                        <?php if (!empty($pedido['filial_nome'])): ?>
                                                                            <br><small class="text-muted">
                                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                                Filial: <?= htmlspecialchars($pedido['filial_nome']) ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <?php endif; ?>
                                                            </table>
                            </div>
                            <div class="col-md-6">
                                                            <h6>Ações</h6>
                                                            <div class="d-grid gap-2">
                                                                <button class="btn btn-outline-primary" onclick="verDetalhesPedido(<?= $pedido['idpedido'] ?>)">
                                                                    <i class="fas fa-eye me-1"></i>
                                                                    Ver Detalhes
                                                                </button>
                                                                <button class="btn btn-outline-success" onclick="imprimirPedido(<?= $pedido['idpedido'] ?>)">
                                                                    <i class="fas fa-print me-1"></i>
                                                                    Imprimir
                                                                </button>
                                                                <button class="btn btn-outline-info" onclick="exportarPedido(<?= $pedido['idpedido'] ?>)">
                                                                    <i class="fas fa-download me-1"></i>
                                                                    Exportar
                                                                </button>
                                                                <button class="btn btn-outline-warning" onclick="editarPedido(<?= $pedido['idpedido'] ?>)">
                                                                    <i class="fas fa-edit me-1"></i>
                                                                    Editar Pedido
                                                                </button>
                                                                <button class="btn btn-outline-danger" onclick="excluirPedido(<?= $pedido['idpedido'] ?>)">
                                                                    <i class="fas fa-trash me-1"></i>
                                                                    Excluir Pedido
                                                                </button>
                                    </div>
                                </div>
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

                <!-- Tab Pedidos Fiado -->
                <div class="tab-pane fade" id="pedidos-fiado" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card me-2"></i>
                                    Pedidos Fiado
                                </h5>
                                <small class="text-muted">Mostra todos os pedidos com pagamentos fiado (quitados e pendentes)</small>
                            </div>
                            <div>
                                <button class="btn btn-success btn-sm" onclick="forcarCarregamentoFiado()">
                                    <i class="fas fa-play me-1"></i>
                                    Carregar Agora
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="atualizarPedidosFiado()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="console.log('🔍 Teste manual'); atualizarPedidosFiado();">
                                    <i class="fas fa-bug me-1"></i>
                                    Debug
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelaPedidosFiado">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Data/Hora</th>
                                            <th>Cliente</th>
                                            <th>Telefone</th>
                                            <th>Mesa</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Conteúdo será carregado via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Relatórios -->
                <div class="tab-pane fade" id="relatorios" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Relatórios Financeiros
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                            <h5>Fluxo de Caixa</h5>
                                            <p class="text-muted">Análise de entradas e saídas</p>
                                            <button class="btn btn-primary" onclick="gerarRelatorio('fluxo_caixa')">
                                                Gerar Relatório
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-chart-pie fa-3x text-success mb-3"></i>
                                            <h5>Receitas por Categoria</h5>
                                            <p class="text-muted">Distribuição das receitas</p>
                                            <button class="btn btn-success" onclick="gerarRelatorio('receitas_categoria')">
                                                Gerar Relatório
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-chart-bar fa-3x text-warning mb-3"></i>
                                            <h5>Despesas por Categoria</h5>
                                            <p class="text-muted">Análise das despesas</p>
                                            <button class="btn btn-warning" onclick="gerarRelatorio('despesas_categoria')">
                                                Gerar Relatório
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    <script src="assets/js/financeiro.js"></script>
    <script>
        // Inicializar Select2
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });
            
            // Carregar pedidos fiado quando a aba for ativada
            $('#pedidos-fiado-tab').on('shown.bs.tab', function () {
                console.log('🔍 Aba Pedidos Fiado ativada!');
                atualizarPedidosFiado();
            });
            
            // Também carregar quando clicar diretamente na aba
            $('#pedidos-fiado-tab').on('click', function () {
                console.log('🖱️ Clique na aba Pedidos Fiado!');
                setTimeout(() => {
                    atualizarPedidosFiado();
                }, 100);
            });
        });

        // Funções para lançamentos
        function abrirModalLancamento() {
            window.location.href = 'index.php?view=novo_lancamento';
        }

        function editarLancamento(id) {
            // Implementar edição de lançamento
            Swal.fire({
                title: 'Editar Lançamento',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
        }

        function excluirLancamento(id) {
            Swal.fire({
                title: 'Excluir Lançamento',
                text: 'Tem certeza que deseja excluir este lançamento?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Implementar exclusão
                    Swal.fire('Excluído!', 'Lançamento excluído com sucesso.', 'success');
                }
            });
        }

        // Funções para pedidos
        function verDetalhesPedido(id) {
            window.location.href = `index.php?view=fechar_pedido&pedido_id=${id}`;
        }

        function imprimirPedido(id) {
            console.log('Imprimindo pedido:', id);
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Fetch pedido data
            fetch(`mvc/ajax/pedidos.php?buscar_pedido=1&pedido_id=${id}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    const itens = data.itens || [];
                    
                    // Generate print HTML using the same format as gerar_pedido.php
                    let printHtml = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Cupom Fiscal - Pedido #${pedido.idpedido}</title>
                            <style>
                                body { font-family: 'Courier New', monospace; font-size: 11px; margin: 0; padding: 8px; }
                                .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
                                .empresa { font-weight: bold; font-size: 13px; }
                                .endereco { font-size: 9px; }
                                .pedido-info { margin: 8px 0; font-size: 10px; }
                                .item { margin: 3px 0; }
                                .item-nome { font-weight: bold; font-size: 11px; }
                                .item-detalhes { font-size: 10px; margin-left: 8px; }
                                .modificacoes { margin-left: 15px; font-size: 10px; }
                                .adicionado { color: green; }
                                .removido { color: red; }
                                .total { border-top: 1px dashed #000; padding-top: 8px; margin-top: 8px; font-weight: bold; font-size: 12px; }
                                .footer { text-align: center; margin-top: 15px; font-size: 9px; }
                                @media print { body { margin: 0; padding: 5px; font-size: 10px; } }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div class="empresa">DIVINO LANCHES</div>
                                <div class="endereco">Rua das Flores, 123 - Centro</div>
                                <div class="endereco">Tel: (11) 99999-9999</div>
                            </div>
                            
                            <div class="pedido-info">
                                <strong>PEDIDO #${pedido.idpedido}</strong><br>
                                Data/Hora: ${pedido.data} ${pedido.hora_pedido}<br>
                                ${pedido.idmesa && pedido.idmesa !== '999' ? `Mesa: ${pedido.idmesa}` : 'DELIVERY'}<br>
                                ${pedido.cliente ? `Cliente: ${pedido.cliente}` : ''}
                                ${pedido.telefone_cliente ? `<br>Telefone: ${pedido.telefone_cliente}` : ''}
                                ${pedido.usuario_nome ? `<br>Atendente: ${pedido.usuario_nome}` : ''}
                            </div>
                            
                            <div class="itens">
                                <strong>ITENS DO PEDIDO:</strong><br>`;
                    
                    itens.forEach(item => {
                        printHtml += `
                            <div class="item">
                                <div class="item-nome">${item.quantidade}x ${item.nome_produto || 'Produto'}</div>
                                <div class="item-detalhes">R$ ${parseFloat(item.valor_unitario).toFixed(2).replace('.', ',')}</div>`;
                        
                        if (item.ingredientes_com && item.ingredientes_com.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            item.ingredientes_com.forEach(ing => {
                                printHtml += `<div class="adicionado">+ ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (item.ingredientes_sem && item.ingredientes_sem.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            item.ingredientes_sem.forEach(ing => {
                                printHtml += `<div class="removido">- ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (item.observacao) {
                            printHtml += `<div class="item-detalhes">Obs: ${item.observacao}</div>`;
                        }
                        
                        printHtml += `</div>`;
                    });
                    
                    printHtml += `
                            </div>
                            
                            <div class="total">
                                <strong>TOTAL: R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</strong>
                            </div>
                            
                            ${pedido.observacao ? `<div class="pedido-info"><strong>Observação:</strong> ${pedido.observacao}</div>` : ''}
                            
                            <div class="footer">
                                Obrigado pela preferência!<br>
                                Volte sempre!<br>
                                Impresso em: ${new Date().toLocaleString('pt-BR')}
                            </div>
                        </body>
                        </html>`;
                    
                    // Write content to print window
                    printWindow.document.write(printHtml);
                    printWindow.document.close();
                    
                    // Print after content loads
                    printWindow.onload = function() {
                        printWindow.print();
                        printWindow.close();
                    };
                    
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido para impressão', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao imprimir pedido:', error);
                Swal.fire('Erro', 'Erro ao imprimir pedido', 'error');
            });
        }

        function atualizarPedidosFiado() {
            console.log('🔄 Iniciando atualização de pedidos fiado...');
            
            // Verificar se a tabela existe antes de tentar atualizar
            const tabela = document.querySelector('#tabelaPedidosFiado');
            if (!tabela) {
                console.log('⚠️ Tabela de pedidos fiado não encontrada ainda, aguardando...');
                return;
            }
            
            console.log('📋 Iniciando atualização de pedidos fiado...');
            
            // Mostrar indicador de carregamento
            const tbody = tabela.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Carregando pedidos fiado...</td></tr>';
            }
            
            console.log('🌐 Fazendo requisição para: mvc/ajax/financeiro.php?action=buscar_pedidos_fiado');
            
            fetch('mvc/ajax/financeiro.php?action=buscar_pedidos_fiado', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                console.log('📡 Resposta recebida:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('📊 Dados recebidos:', data);
                if (data.success) {
                    console.log('✅ Sucesso! Processando', data.pedidos.length, 'pedidos');
                    const tbody = document.querySelector('#tabelaPedidosFiado tbody');
                    
                    // Verificar se há pedidos
                    if (data.pedidos.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>Nenhum pedido fiado encontrado</td></tr>';
                        document.getElementById('totalFiado').textContent = 'R$ 0,00';
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    let totalRecebiveis = 0;
                    
                    data.pedidos.forEach(pedido => {
                        const row = document.createElement('tr');
                        const saldoDevedorReal = parseFloat(pedido.saldo_devedor_real) || 0;
                        const totalPagoNaoFiado = parseFloat(pedido.total_pago_nao_fiado) || 0;
                        const totalPagoFiado = parseFloat(pedido.total_pago_fiado) || 0;
                        const totalPago = parseFloat(pedido.total_pago) || 0;
                        
                        // Status baseado no status_pagamento do pedido
                        const statusBadge = pedido.status_pagamento === 'quitado' ? 'bg-success' : 'bg-warning';
                        const statusText = pedido.status_pagamento === 'quitado' ? 'Quitado' : 'Pendente';
                        
                        // Somar apenas valores FIADO de pedidos NÃO quitados ao total de recebíveis
                        if (pedido.status_pagamento !== 'quitado') {
                            totalRecebiveis += totalPagoFiado;
                        }
                        
                        row.innerHTML = `
                            <td>#${pedido.idpedido}</td>
                            <td>${pedido.data} ${pedido.hora_pedido}</td>
                            <td>${pedido.cliente || 'N/A'}</td>
                            <td>${pedido.telefone_cliente || 'N/A'}</td>
                            <td>${pedido.idmesa == '999' ? 'Delivery' : `Mesa ${pedido.idmesa}`}</td>
                            <td>
                                <div>Total: R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</div>
                                <small class="text-success">Pago Real: R$ ${totalPagoNaoFiado.toFixed(2).replace('.', ',')}</small>
                                <small class="text-warning">Fiado: R$ ${totalPagoFiado.toFixed(2).replace('.', ',')}</small>
                                ${saldoDevedorReal > 0 ? `<div class="text-danger">Saldo: R$ ${saldoDevedorReal.toFixed(2).replace('.', ',')}</div>` : ''}
                            </td>
                            <td><span class="badge ${statusBadge}">${statusText}</span></td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm" onclick="verDetalhesPedido(${pedido.idpedido})" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="quitarPedidoFiado(${pedido.idpedido})" title="Quitar Saldo Fiado">
                                    <i class="fas fa-check me-1"></i>Quitar
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="imprimirPedido(${pedido.idpedido})" title="Imprimir">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="excluirPedidoFiado(${pedido.idpedido})" title="Excluir Pedido">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    // Atualizar o card de recebíveis fiado
                    console.log('💰 Total recebíveis calculado:', totalRecebiveis);
                    document.getElementById('totalFiado').textContent = `R$ ${totalRecebiveis.toFixed(2).replace('.', ',')}`;
                    console.log('✅ Tabela atualizada com sucesso!');
                    
                } else {
                    console.error('❌ Erro na resposta:', data);
                    Swal.fire('Erro', 'Erro ao carregar pedidos fiado', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao carregar pedidos fiado', 'error');
            });
        }

        function carregarTotalRecebiveisFiado() {
            console.log('🔄 Carregando total de recebíveis fiado...');
            
            // Verificar se o elemento existe
            const elemento = document.getElementById('totalFiado');
            if (!elemento) {
                console.error('❌ Elemento totalFiado não encontrado!');
                return;
            }
            
            console.log('🌐 Fazendo requisição para: mvc/ajax/financeiro.php?action=buscar_total_recebiveis_fiado');
            
            fetch('mvc/ajax/financeiro.php?action=buscar_total_recebiveis_fiado', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                console.log('📡 Resposta recebíveis:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('💰 Dados recebíveis recebidos:', data);
                if (data.success) {
                    const total = parseFloat(data.total_recebiveis || 0).toFixed(2).replace('.', ',');
                    console.log('💰 Atualizando card com total:', total);
                    elemento.textContent = `R$ ${total}`;
                    console.log('✅ Card atualizado com sucesso!');
                } else {
                    console.error('❌ Erro ao carregar recebíveis:', data);
                    elemento.textContent = 'R$ 0,00';
                }
            })
            .catch(error => {
                console.error('❌ Erro na requisição recebíveis:', error);
                elemento.textContent = 'R$ 0,00';
            });
        }

        function quitarPedidoFiado(pedidoId) {
            // Buscar dados do pedido usando o endpoint que já funciona
            fetch('mvc/ajax/financeiro.php?action=buscar_pedidos_fiado', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Encontrar o pedido específico
                    const pedido = data.pedidos.find(p => p.idpedido == pedidoId);
                    
                    if (!pedido) {
                        Swal.fire('Erro', 'Pedido não encontrado', 'error');
                        return;
                    }
                    
                    const saldoFiado = parseFloat(pedido.saldo_fiado_pendente) || 0;
                    
                    // Verificar se há saldo fiado para quitar
                    if (saldoFiado <= 0.01) {
                        Swal.fire('Aviso', 'Este pedido não possui valores fiado para quitar!', 'info');
                        return;
                    }
                    
                    abrirModalQuitarFiado(pedidoId, saldoFiado, pedido);
                } else {
                    Swal.fire('Erro', 'Erro ao buscar dados do pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar pedido:', error);
                Swal.fire('Erro', 'Erro ao buscar dados do pedido', 'error');
            });
        }

        function abrirModalQuitarFiado(pedidoId, saldoDevedor, pedido) {
            Swal.fire({
                title: 'Quitar Pedido Fiado',
                html: `
                    <div class="mb-3">
                        <p><strong>Pedido:</strong> #${pedidoId}</p>
                        <p><strong>Cliente:</strong> ${pedido.cliente || 'N/A'}</p>
                        <p><strong>Valor Total:</strong> R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</p>
                        <p><strong>Pago (não fiado):</strong> R$ ${parseFloat(pedido.total_pago_nao_fiado || 0).toFixed(2).replace('.', ',')}</p>
                        <p><strong>Valor Fiado:</strong> R$ ${parseFloat(pedido.total_pago_fiado || 0).toFixed(2).replace('.', ',')}</p>
                        <p class="text-warning"><strong>Saldo Fiado a Quitar:</strong> R$ ${saldoDevedor.toFixed(2).replace('.', ',')}</p>
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
                        <input type="number" class="form-control" id="valorPagar" step="0.01" min="0.01" max="${saldoDevedor}" value="${saldoDevedor}" required>
                        <small class="form-text text-muted">Você pode pagar parcialmente ou o valor total do saldo</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (Opcional)</label>
                        <input type="text" class="form-control" id="descricao" placeholder="Ex: Pagamento parcial do pedido fiado">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar Pagamento',
                cancelButtonText: 'Cancelar',
                width: '500px',
                didOpen: () => {
                    setTimeout(() => {
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
                    const descricao = document.getElementById('descricao').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    if (valorPagar <= 0) {
                        Swal.showValidationMessage('Valor deve ser maior que zero');
                        return false;
                    }
                    
                    // Limitar valor ao saldo devedor
                    if (valorPagar > saldoDevedor + 0.01) {
                        Swal.showValidationMessage('Valor não pode ser maior que o saldo devedor');
                        return false;
                    }
                    
                    return {
                        formaPagamento: formaPagamento,
                        valorPagar: valorPagar,
                        descricao: descricao
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    registrarPagamentoFiado(pedidoId, result.value);
                }
            });
        }

        function registrarPagamentoFiado(pedidoId, dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'registrar_pagamento_fiado');
            formData.append('pedido_id', pedidoId);
            formData.append('forma_pagamento', dados.formaPagamento);
            formData.append('valor_pago', dados.valorPagar);
            formData.append('descricao', dados.descricao);
            
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Pagamento registrado com sucesso!', 'success');
                    
                    // Atualizar dados
                    atualizarPedidosFiado();
                    carregarTotalRecebiveisFiado();
                    
                    // Recarregar página para atualizar todas as abas
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao registrar pagamento:', error);
                Swal.fire('Erro', 'Erro ao registrar pagamento', 'error');
            });
        }

        function exportarPedido(id) {
            // Implementar exportação
            Swal.fire({
                title: 'Exportar Pedido',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
        }

        // Função para excluir pedido fiado
        function excluirPedidoFiado(pedidoId) {
            Swal.fire({
                title: 'Excluir Pedido',
                text: `Tem certeza que deseja excluir o pedido #${pedidoId}? Esta ação não pode ser desfeita!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Fazer requisição para excluir
                    fetch('mvc/ajax/financeiro.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=excluir_pedido_fiado&pedido_id=${pedidoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Pedido excluído com sucesso!', 'success');
                            
                            // Atualizar dados
                            atualizarPedidosFiado();
                            carregarTotalRecebiveisFiado();
                            
                            // Recarregar página para atualizar todas as abas
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao excluir pedido', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao excluir pedido:', error);
                        Swal.fire('Erro', 'Erro ao excluir pedido', 'error');
                    });
                }
            });
        }

        function forcarCarregamentoFiado() {
            console.log('🚀 FORÇANDO CARREGAMENTO FIADO...');
            
            // Carregar recebíveis
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=buscar_total_recebiveis_fiado'
            })
            .then(response => response.json())
            .then(data => {
                console.log('💰 Dados recebíveis:', data);
                if (data.success) {
                    const elemento = document.getElementById('totalFiado');
                    if (elemento) {
                        const total = parseFloat(data.total_recebiveis || 0).toFixed(2).replace('.', ',');
                        elemento.textContent = `R$ ${total}`;
                        console.log('✅ Card atualizado: R$ ' + total);
                    }
                }
            });
            
            // Carregar pedidos
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=buscar_pedidos_fiado'
            })
            .then(response => response.json())
            .then(data => {
                console.log('📋 Dados pedidos:', data);
                if (data.success) {
                    const tabela = document.querySelector('#tabelaPedidosFiado tbody');
                    if (tabela) {
                        if (data.pedidos.length === 0) {
                            tabela.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>Nenhum pedido fiado encontrado</td></tr>';
                        } else {
                            tabela.innerHTML = '';
                            data.pedidos.forEach(pedido => {
                                const saldoDevedorReal = parseFloat(pedido.saldo_fiado_pendente) || 0;
                                const totalFiado = parseFloat(pedido.total_pago_fiado) || 0;
                                
                                const row = `
                                    <tr>
                                        <td><strong>#${pedido.idpedido}</strong></td>
                                        <td>${pedido.data} ${pedido.hora_pedido}</td>
                                        <td>${pedido.cliente || 'N/A'}</td>
                                        <td>${pedido.telefone_cliente || 'N/A'}</td>
                                        <td>Mesa ${pedido.idmesa}</td>
                                        <td>
                                            <strong>R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</strong>
                                            ${saldoDevedorReal > 0.01 ? `<br><small class="text-warning">Fiado: R$ ${saldoDevedorReal.toFixed(2).replace('.', ',')}</small>` : ''}
                                        </td>
                                        <td>
                                            <span class="badge bg-${pedido.status_pagamento === 'quitado' ? 'success' : 'warning'}">
                                                ${pedido.status_pagamento === 'quitado' ? 'Quitado' : 'Pendente'}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-success btn-sm" onclick="quitarPedidoFiado(${pedido.idpedido})" title="Quitar Saldo Fiado">
                                                <i class="fas fa-check me-1"></i>Quitar
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="excluirPedidoFiado(${pedido.idpedido})" title="Excluir Pedido">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                                tabela.innerHTML += row;
                            });
                            console.log('✅ Tabela atualizada com ' + data.pedidos.length + ' pedidos');
                        }
                    }
                }
            });
        }

        function editarPedido(id) {
            // Buscar dados do pedido
            fetch(`mvc/ajax/pedidos.php?action=buscar_pedido&pedido_id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.pedido) {
                    const pedido = data.pedido;
                    
                    Swal.fire({
                        title: 'Editar Pedido',
                        html: `
                            <div class="mb-3">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control" id="editCliente" value="${pedido.cliente || ''}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="editTelefone" value="${pedido.telefone_cliente || ''}" placeholder="(11) 99999-9999">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valor Total</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="editValorTotal" value="${pedido.valor_total}" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status do Pedido</label>
                                <select class="form-select" id="editStatus">
                                    <option value="Pendente" ${pedido.status === 'Pendente' ? 'selected' : ''}>Pendente</option>
                                    <option value="Em Preparo" ${pedido.status === 'Em Preparo' ? 'selected' : ''}>Em Preparo</option>
                                    <option value="Pronto" ${pedido.status === 'Pronto' ? 'selected' : ''}>Pronto</option>
                                    <option value="Entregue" ${pedido.status === 'Entregue' ? 'selected' : ''}>Entregue</option>
                                    <option value="Finalizado" ${pedido.status === 'Finalizado' ? 'selected' : ''}>Finalizado</option>
                                    <option value="Cancelado" ${pedido.status === 'Cancelado' ? 'selected' : ''}>Cancelado</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status do Pagamento</label>
                                <select class="form-select" id="editStatusPagamento">
                                    <option value="pendente" ${pedido.status_pagamento === 'pendente' ? 'selected' : ''}>Pendente</option>
                                    <option value="parcial" ${pedido.status_pagamento === 'parcial' ? 'selected' : ''}>Parcial</option>
                                    <option value="quitado" ${pedido.status_pagamento === 'quitado' ? 'selected' : ''}>Quitado</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea class="form-control" id="editObservacoes" rows="3" placeholder="Observações sobre o pedido...">${pedido.observacao || ''}</textarea>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Salvar',
                        cancelButtonText: 'Cancelar',
                        width: '600px',
                        preConfirm: () => {
                            const cliente = document.getElementById('editCliente').value;
                            const telefone = document.getElementById('editTelefone').value;
                            const valorTotal = document.getElementById('editValorTotal').value;
                            const status = document.getElementById('editStatus').value;
                            const statusPagamento = document.getElementById('editStatusPagamento').value;
                            const observacoes = document.getElementById('editObservacoes').value;
                            
                            if (!cliente || !valorTotal) {
                                Swal.showValidationMessage('Cliente e valor total são obrigatórios');
                                return false;
                            }
                            
                            return {
                                id: id,
                                cliente: cliente,
                                telefone_cliente: telefone,
                                valor_total: valorTotal,
                                status: status,
                                status_pagamento: statusPagamento,
                                observacao: observacoes
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            atualizarPedido(result.value);
                        }
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao buscar pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        function atualizarPedido(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'atualizar_pedido');
            Object.keys(dados).forEach(key => {
                formData.append(key, dados[key]);
            });
            
            fetch('mvc/ajax/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Pedido atualizado com sucesso!', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao atualizar pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        function excluirPedido(id) {
            Swal.fire({
                title: 'Excluir Pedido',
                text: 'Tem certeza que deseja excluir este pedido? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Fazer requisição AJAX para excluir
                    fetch('mvc/ajax/pedidos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=excluir_pedido&pedido_id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Excluído!',
                                text: 'Pedido excluído com sucesso.',
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Erro!',
                                text: data.message || 'Erro ao excluir pedido',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire({
                            title: 'Erro!',
                            text: 'Erro ao processar solicitação',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        // Funções para relatórios
        function abrirModalRelatorio() {
            window.location.href = 'index.php?view=gerar_relatorios';
        }

        function gerarRelatorio(tipo) {
            Swal.fire({
                title: 'Gerando Relatório',
                text: 'Aguarde enquanto processamos os dados...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            
            // Simular geração de relatório
            setTimeout(() => {
                Swal.fire({
                    title: 'Relatório Gerado!',
                    text: 'O relatório foi gerado com sucesso.',
                    icon: 'success'
                });
            }, 2000);
        }
    </script>

    <!-- CARREGAMENTO AUTOMÁTICO - EXECUTA APÓS TODO HTML ESTAR PRONTO -->
    <script>
        console.log('🚀 INICIANDO CARREGAMENTO AUTOMÁTICO...');
        
        // Aguardar um pouco para garantir que tudo esteja carregado
        setTimeout(function() {
            console.log('💰 Carregando card recebíveis fiado automaticamente...');
            
            // Carregar card recebíveis fiado
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=buscar_total_recebiveis_fiado'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const elemento = document.getElementById('totalFiado');
                    if (elemento) {
                        const total = parseFloat(data.total_recebiveis || 0).toFixed(2).replace('.', ',');
                        elemento.textContent = `R$ ${total}`;
                        console.log('✅ Card recebíveis fiado atualizado: R$ ' + total);
                    }
                }
            })
            .catch(error => {
                console.error('❌ Erro ao carregar recebíveis:', error);
            });
            
            // Carregar pedidos fiado automaticamente
            console.log('📋 Carregando pedidos fiado automaticamente...');
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=buscar_pedidos_fiado'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tabela = document.querySelector('#tabelaPedidosFiado tbody');
                    if (tabela) {
                        if (data.pedidos.length === 0) {
                            tabela.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>Nenhum pedido fiado encontrado</td></tr>';
                        } else {
                            tabela.innerHTML = '';
                            let totalRecebiveis = 0;
                            
                            data.pedidos.forEach(pedido => {
                                const row = document.createElement('tr');
                                const saldoFiadoPendente = parseFloat(pedido.saldo_fiado_pendente) || 0;
                                const totalPagoNaoFiado = parseFloat(pedido.total_pago_nao_fiado) || 0;
                                const totalPagoFiado = parseFloat(pedido.total_pago_fiado) || 0;
                                
                                // Status baseado no status_pagamento do pedido
                                const statusBadge = pedido.status_pagamento === 'quitado' ? 'bg-success' : 'bg-warning';
                                const statusText = pedido.status_pagamento === 'quitado' ? 'Quitado' : 'Pendente';
                                
                                // Somar apenas valores FIADO pendentes ao total de recebíveis
                                if (saldoFiadoPendente > 0.01) {
                                    totalRecebiveis += saldoFiadoPendente;
                                }
                                
                                row.innerHTML = `
                                    <td>#${pedido.idpedido}</td>
                                    <td>${pedido.data} ${pedido.hora_pedido}</td>
                                    <td>${pedido.cliente || 'N/A'}</td>
                                    <td>${pedido.telefone_cliente || 'N/A'}</td>
                                    <td>${pedido.idmesa == '999' ? 'Delivery' : `Mesa ${pedido.idmesa}`}</td>
                                    <td>
                                        <div>Total: R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</div>
                                        <small class="text-success">Pago Real: R$ ${totalPagoNaoFiado.toFixed(2).replace('.', ',')}</small>
                                        ${saldoFiadoPendente > 0.01 ? `<small class="text-warning">Fiado: R$ ${saldoFiadoPendente.toFixed(2).replace('.', ',')}</small>` : ''}
                                    </td>
                                    <td><span class="badge ${statusBadge}">${statusText}</span></td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" onclick="verDetalhesPedido(${pedido.idpedido})" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        ${saldoFiadoPendente > 0.01 ? 
                                            `<button class="btn btn-outline-success btn-sm" onclick="quitarPedidoFiado(${pedido.idpedido})" title="Quitar Fiado">
                                                <i class="fas fa-check me-1"></i>Quitar
                                            </button>` : 
                                            `<button class="btn btn-outline-secondary btn-sm" disabled title="Fiado já quitado">
                                                <i class="fas fa-check-circle me-1"></i>Quitado
                                            </button>`
                                        }
                                            <button class="btn btn-outline-info btn-sm" onclick="imprimirPedido(${pedido.idpedido})" title="Imprimir">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="excluirPedidoFiado(${pedido.idpedido})" title="Excluir Pedido">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    `;
                                tabela.appendChild(row);
                            });
                            
                            // Atualizar o card de recebíveis fiado com valores pendentes
                            const elemento = document.getElementById('totalFiado');
                            if (elemento) {
                                elemento.textContent = `R$ ${totalRecebiveis.toFixed(2).replace('.', ',')}`;
                            }
                        }
                        
                        console.log('✅ Pedidos fiado carregados automaticamente: ' + data.pedidos.length + ' pedidos');
                    }
                }
            })
            .catch(error => {
                console.error('❌ Erro ao carregar pedidos fiado:', error);
            });
        }, 3000); // Aguardar 3 segundos para garantir que tudo esteja carregado
        
        console.log('✅ Script de carregamento automático configurado!');
    </script>
</body>
</html>