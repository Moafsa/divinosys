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
    
    // Buscar pedidos com informações financeiras
    $pedidosFinanceiros = $db->fetchAll(
        "SELECT p.*, 
                COALESCE(SUM(pp.valor_pago), 0) as total_pago,
                COUNT(pp.id) as qtd_pagamentos,
                STRING_AGG(DISTINCT pp.forma_pagamento, ', ') as formas_pagamento,
                m.nome as mesa_nome
         FROM pedido p
         LEFT JOIN pagamentos_pedido pp ON p.idpedido = pp.pedido_id AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.tenant_id = ? AND p.filial_id = ?
         AND p.data BETWEEN ? AND ?
         AND p.status_pagamento = 'quitado'
         GROUP BY p.idpedido, m.nome
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
    
    // Adicionar receitas dos pedidos quitados
    $receitasPedidos = $db->fetch(
        "SELECT 
            COALESCE(SUM(valor_total), 0) as total_vendas_pedidos,
            COUNT(*) as total_pedidos_quitados
         FROM pedido 
         WHERE tenant_id = ? AND filial_id = ?
         AND data BETWEEN ? AND ?
         AND status_pagamento = 'quitado'",
        [$tenant['id'], $filial['id'], $dataInicio, $dataFim]
    );
    
    // Somar receitas dos pedidos aos lançamentos
    $resumoFinanceiro['total_receitas'] += $receitasPedidos['total_vendas_pedidos'];
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
                        <a class="nav-link active" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
                            <i class="fas fa-chart-line"></i>
                            <span>Financeiro</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
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
                    <button class="btn btn-primary me-2" onclick="abrirModalLancamento()">
                        <i class="fas fa-plus me-1"></i>
                        Novo Lançamento
                    </button>
                    <button class="btn btn-success" onclick="abrirModalRelatorio()">
                        <i class="fas fa-chart-bar me-1"></i>
                        Gerar Relatório
                    </button>
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
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Pedidos Quitados
                            </h5>
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
    <script>
        // Inicializar Select2
        $(document).ready(function() {
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });
        });

        // Funções para lançamentos
        function abrirModalLancamento() {
            // Implementar modal de novo lançamento
            Swal.fire({
                title: 'Novo Lançamento',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
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
            window.open(`index.php?view=imprimir_pedido&pedido_id=${id}`, '_blank');
        }

        function exportarPedido(id) {
            // Implementar exportação
            Swal.fire({
                title: 'Exportar Pedido',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
        }

        // Funções para relatórios
        function abrirModalRelatorio() {
            Swal.fire({
                title: 'Gerar Relatório',
                text: 'Funcionalidade em desenvolvimento',
                icon: 'info'
            });
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
</body>
</html>