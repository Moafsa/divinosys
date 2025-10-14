<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Verificar autenticação
if (!$session->isLoggedIn()) {
    header('Location: index.php?view=login');
    exit;
}

// Obter informações do tenant e filial
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Parâmetros de filtro
$tipoFiltro = $_GET['tipo'] ?? '';
$categoriaFiltro = $_GET['categoria'] ?? '';
$contaFiltro = $_GET['conta'] ?? '';
$statusFiltro = $_GET['status'] ?? '';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Construir query com filtros
$whereConditions = ['l.tenant_id = ?', 'l.filial_id = ?'];
$params = [$tenant['id'], $filial['id']];

if (!empty($tipoFiltro)) {
    $whereConditions[] = 'l.tipo_lancamento = ?';
    $params[] = $tipoFiltro;
}

if (!empty($categoriaFiltro)) {
    $whereConditions[] = 'l.categoria_id = ?';
    $params[] = $categoriaFiltro;
}

if (!empty($contaFiltro)) {
    $whereConditions[] = 'l.conta_id = ?';
    $params[] = $contaFiltro;
}

if (!empty($statusFiltro)) {
    $whereConditions[] = 'l.status = ?';
    $params[] = $statusFiltro;
}

$whereConditions[] = 'l.data_lancamento BETWEEN ? AND ?';
$params[] = $dataInicio . ' 00:00:00';
$params[] = $dataFim . ' 23:59:59';

$whereClause = implode(' AND ', $whereConditions);

// Buscar lançamentos
$lancamentos = $db->fetchAll(
    "SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
            co.nome as conta_nome, co.tipo as conta_tipo, u.login as usuario_nome
     FROM lancamentos_financeiros l
     LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
     LEFT JOIN contas_financeiras co ON l.conta_id = co.id
     LEFT JOIN usuarios u ON l.usuario_id = u.id
     WHERE $whereClause
     ORDER BY l.data_lancamento DESC, l.created_at DESC",
    $params
);

// Buscar categorias e contas para filtros
$categorias = $db->fetchAll(
    "SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
    [$tenant['id'], $filial['id']]
);

$contas = $db->fetchAll(
    "SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
    [$tenant['id'], $filial['id']]
);

// Calcular totais
$totais = $db->fetch(
    "SELECT 
        COALESCE(SUM(CASE WHEN tipo_lancamento = 'receita' THEN valor ELSE 0 END), 0) as total_receitas,
        COALESCE(SUM(CASE WHEN tipo_lancamento = 'despesa' THEN valor ELSE 0 END), 0) as total_despesas,
        COALESCE(SUM(CASE WHEN tipo_lancamento = 'receita' THEN valor ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN tipo_lancamento = 'despesa' THEN valor ELSE 0 END), 0) as saldo_liquido,
        COUNT(*) as total_lancamentos
     FROM lancamentos_financeiros 
     WHERE $whereClause",
    $params
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamentos Financeiros - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        .summary-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
        }
        
        .summary-card.receitas {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .summary-card.despesas {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        
        .summary-card.saldo {
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
        }
        
        .lancamento-item {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .lancamento-item.receita {
            border-left-color: #28a745;
        }
        
        .lancamento-item.despesa {
            border-left-color: #dc3545;
        }
        
        .lancamento-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
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
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('lancamentos'); ?>" data-tooltip="Lançamentos">
                            <i class="fas fa-receipt"></i>
                            <span>Lançamentos</span>
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
                                <i class="fas fa-receipt text-primary me-2"></i>
                                Lançamentos Financeiros
                            </h2>
                            <p class="text-muted mb-0">Gerencie entradas e saídas financeiras</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="abrirModalFiltros()">
                                <i class="fas fa-filter me-1"></i>
                                Filtros
                            </button>
                            <button class="btn btn-success" onclick="abrirModalNovoLancamento()">
                                <i class="fas fa-plus me-1"></i>
                                Novo Lançamento
                            </button>
                        </div>
                    </div>

                    <!-- Cards de Resumo -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card summary-card receitas">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1">R$ <?= number_format($totais['total_receitas'], 2, ',', '.') ?></h3>
                                            <p class="mb-0">Total Receitas</p>
                                        </div>
                                        <div class="fs-1">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card despesas">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1">R$ <?= number_format($totais['total_despesas'], 2, ',', '.') ?></h3>
                                            <p class="mb-0">Total Despesas</p>
                                        </div>
                                        <div class="fs-1">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card saldo">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="mb-1">R$ <?= number_format($totais['saldo_liquido'], 2, ',', '.') ?></h3>
                                            <p class="mb-0">Saldo Líquido</p>
                                        </div>
                                        <div class="fs-1">
                                            <i class="fas fa-balance-scale"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Lançamentos -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Lançamentos (<?= $totais['total_lancamentos'] ?> registros)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($lancamentos)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhum lançamento encontrado</h5>
                                    <p class="text-muted">Clique em "Novo Lançamento" para começar</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($lancamentos as $lancamento): ?>
                                        <div class="list-group-item lancamento-item <?= $lancamento['tipo_lancamento'] ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-1">
                                                    <div class="text-center">
                                                        <i class="<?= $lancamento['categoria_icone'] ?? 'fas fa-tag' ?> fa-2x" 
                                                           style="color: <?= $lancamento['categoria_cor'] ?? '#007bff' ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <h6 class="mb-1"><?= htmlspecialchars($lancamento['descricao']) ?></h6>
                                                    <small class="text-muted">
                                                        <i class="<?= $lancamento['categoria_icone'] ?? 'fas fa-tag' ?> me-1"></i>
                                                        <?= htmlspecialchars($lancamento['categoria_nome'] ?? 'Sem categoria') ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-2">
                                                    <small class="text-muted">Data</small>
                                                    <div><?= date('d/m/Y', strtotime($lancamento['data_lancamento'])) ?></div>
                                                </div>
                                                <div class="col-md-2">
                                                    <small class="text-muted">Conta</small>
                                                    <div><?= htmlspecialchars($lancamento['conta_nome'] ?? 'Sem conta') ?></div>
                                                </div>
                                                <div class="col-md-2">
                                                    <small class="text-muted">Valor</small>
                                                    <div class="fw-bold <?= $lancamento['tipo_lancamento'] == 'receita' ? 'text-success' : 'text-danger' ?>">
                                                        <?= $lancamento['tipo_lancamento'] == 'receita' ? '+' : '-' ?>
                                                        R$ <?= number_format($lancamento['valor'], 2, ',', '.') ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <span class="badge status-badge 
                                                        <?= $lancamento['status'] == 'confirmado' ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= ucfirst($lancamento['status']) ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-1">
                                                    <div class="btn-group-vertical btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="editarLancamento(<?= $lancamento['id'] ?>)" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="excluirLancamento(<?= $lancamento['id'] ?>)" title="Excluir">
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        // Função para abrir modal de filtros
        function abrirModalFiltros() {
            Swal.fire({
                title: 'Filtros',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Tipo de Lançamento</label>
                        <select class="form-select" id="filtroTipo">
                            <option value="">Todos</option>
                            <option value="receita" <?= $tipoFiltro == 'receita' ? 'selected' : '' ?>>Receita</option>
                            <option value="despesa" <?= $tipoFiltro == 'despesa' ? 'selected' : '' ?>>Despesa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" id="filtroCategoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" <?= $categoriaFiltro == $categoria['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Conta</label>
                        <select class="form-select" id="filtroConta">
                            <option value="">Todas</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?= $conta['id'] ?>" <?= $contaFiltro == $conta['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($conta['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="filtroStatus">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $statusFiltro == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="confirmado" <?= $statusFiltro == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="filtroDataInicio" value="<?= $dataInicio ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="filtroDataFim" value="<?= $dataFim ?>">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Aplicar Filtros',
                cancelButtonText: 'Cancelar',
                width: '500px',
                preConfirm: () => {
                    const params = new URLSearchParams();
                    const tipo = document.getElementById('filtroTipo').value;
                    const categoria = document.getElementById('filtroCategoria').value;
                    const conta = document.getElementById('filtroConta').value;
                    const status = document.getElementById('filtroStatus').value;
                    const dataInicio = document.getElementById('filtroDataInicio').value;
                    const dataFim = document.getElementById('filtroDataFim').value;
                    
                    if (tipo) params.append('tipo', tipo);
                    if (categoria) params.append('categoria', categoria);
                    if (conta) params.append('conta', conta);
                    if (status) params.append('status', status);
                    if (dataInicio) params.append('data_inicio', dataInicio);
                    if (dataFim) params.append('data_fim', dataFim);
                    
                    window.location.href = 'index.php?view=lancamentos&' + params.toString();
                }
            });
        }

        // Função para abrir modal de novo lançamento
        function abrirModalNovoLancamento() {
            Swal.fire({
                title: 'Novo Lançamento',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Tipo de Lançamento</label>
                        <select class="form-select" id="tipoLancamento" required>
                            <option value="">Selecione o tipo</option>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" id="descricao" placeholder="Descrição do lançamento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valor" step="0.01" min="0" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="datetime-local" class="form-control" id="dataLancamento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select class="form-select" id="categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>" data-tipo="<?= $categoria['tipo'] ?>">
                                    <?= htmlspecialchars($categoria['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Conta</label>
                        <select class="form-select" id="conta" required>
                            <option value="">Selecione uma conta</option>
                            <?php foreach ($contas as $conta): ?>
                                <option value="<?= $conta['id'] ?>"><?= htmlspecialchars($conta['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status">
                            <option value="confirmado">Confirmado</option>
                            <option value="pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" rows="3" placeholder="Observações adicionais..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                width: '600px',
                didOpen: () => {
                    // Definir data atual
                    const now = new Date();
                    const dataAtual = now.toISOString().slice(0, 16);
                    document.getElementById('dataLancamento').value = dataAtual;
                    
                    // Filtrar categorias por tipo
                    document.getElementById('tipoLancamento').addEventListener('change', function() {
                        const tipo = this.value;
                        const categoriaSelect = document.getElementById('categoria');
                        const options = categoriaSelect.querySelectorAll('option');
                        
                        options.forEach(option => {
                            if (option.value === '') {
                                option.style.display = 'block';
                                return;
                            }
                            
                            const categoriaTipo = option.getAttribute('data-tipo');
                            if (categoriaTipo === tipo) {
                                option.style.display = 'block';
                            } else {
                                option.style.display = 'none';
                            }
                        });
                        
                        categoriaSelect.value = '';
                    });
                },
                preConfirm: () => {
                    const tipo = document.getElementById('tipoLancamento').value;
                    const descricao = document.getElementById('descricao').value;
                    const valor = document.getElementById('valor').value;
                    const data = document.getElementById('dataLancamento').value;
                    const categoria = document.getElementById('categoria').value;
                    const conta = document.getElementById('conta').value;
                    const status = document.getElementById('status').value;
                    const observacoes = document.getElementById('observacoes').value;
                    
                    if (!tipo || !descricao || !valor || !data || !categoria || !conta) {
                        Swal.showValidationMessage('Todos os campos obrigatórios devem ser preenchidos');
                        return false;
                    }
                    
                    return {
                        tipo_lancamento: tipo,
                        descricao: descricao,
                        valor: valor,
                        data_lancamento: data,
                        categoria_id: categoria,
                        conta_id: conta,
                        status: status,
                        observacoes: observacoes
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    salvarLancamento(result.value);
                }
            });
        }

        // Função para salvar lançamento
        function salvarLancamento(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'criar_lancamento');
            Object.keys(dados).forEach(key => {
                formData.append(key, dados[key]);
            });
            
            fetch('mvc/ajax/lancamentos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Lançamento criado com sucesso!', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao criar lançamento', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Função para editar lançamento
        function editarLancamento(id) {
            // Buscar dados do lançamento
            fetch(`mvc/ajax/lancamentos.php?action=buscar_lancamento&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lancamento = data.lancamento;
                    
                    Swal.fire({
                        title: 'Editar Lançamento',
                        html: `
                            <div class="mb-3">
                                <label class="form-label">Tipo de Lançamento</label>
                                <select class="form-select" id="editTipoLancamento" required>
                                    <option value="receita" ${lancamento.tipo_lancamento === 'receita' ? 'selected' : ''}>Receita</option>
                                    <option value="despesa" ${lancamento.tipo_lancamento === 'despesa' ? 'selected' : ''}>Despesa</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" class="form-control" id="editDescricao" value="${lancamento.descricao}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valor</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="editValor" value="${lancamento.valor}" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data</label>
                                <input type="datetime-local" class="form-control" id="editDataLancamento" value="${lancamento.data_lancamento}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" id="editCategoria" required>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>" data-tipo="<?= $categoria['tipo'] ?>" ${lancamento.categoria_id == <?= $categoria['id'] ?> ? 'selected' : ''}>
                                            <?= htmlspecialchars($categoria['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Conta</label>
                                <select class="form-select" id="editConta" required>
                                    <?php foreach ($contas as $conta): ?>
                                        <option value="<?= $conta['id'] ?>" ${lancamento.conta_id == <?= $conta['id'] ?> ? 'selected' : ''}>
                                            <?= htmlspecialchars($conta['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="editStatus">
                                    <option value="confirmado" ${lancamento.status === 'confirmado' ? 'selected' : ''}>Confirmado</option>
                                    <option value="pendente" ${lancamento.status === 'pendente' ? 'selected' : ''}>Pendente</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea class="form-control" id="editObservacoes" rows="3">${lancamento.observacoes || ''}</textarea>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Salvar',
                        cancelButtonText: 'Cancelar',
                        width: '600px',
                        preConfirm: () => {
                            const tipo = document.getElementById('editTipoLancamento').value;
                            const descricao = document.getElementById('editDescricao').value;
                            const valor = document.getElementById('editValor').value;
                            const data = document.getElementById('editDataLancamento').value;
                            const categoria = document.getElementById('editCategoria').value;
                            const conta = document.getElementById('editConta').value;
                            const status = document.getElementById('editStatus').value;
                            const observacoes = document.getElementById('editObservacoes').value;
                            
                            if (!tipo || !descricao || !valor || !data || !categoria || !conta) {
                                Swal.showValidationMessage('Todos os campos obrigatórios devem ser preenchidos');
                                return false;
                            }
                            
                            return {
                                id: id,
                                tipo_lancamento: tipo,
                                descricao: descricao,
                                valor: valor,
                                data_lancamento: data,
                                categoria_id: categoria,
                                conta_id: conta,
                                status: status,
                                observacoes: observacoes
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            atualizarLancamento(result.value);
                        }
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao buscar lançamento', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Função para atualizar lançamento
        function atualizarLancamento(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'atualizar_lancamento');
            Object.keys(dados).forEach(key => {
                formData.append(key, dados[key]);
            });
            
            fetch('mvc/ajax/lancamentos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Lançamento atualizado com sucesso!', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao atualizar lançamento', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Função para excluir lançamento
        function excluirLancamento(id) {
            Swal.fire({
                title: 'Excluir Lançamento',
                text: 'Tem certeza que deseja excluir este lançamento? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new URLSearchParams();
                    formData.append('action', 'excluir_lancamento');
                    formData.append('id', id);
                    
                    fetch('mvc/ajax/lancamentos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Excluído!', 'Lançamento excluído com sucesso.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Erro!', data.message || 'Erro ao excluir lançamento', 'error');
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
