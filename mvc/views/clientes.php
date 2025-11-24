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

// Get client data if editing
$editarCliente = null;
$clienteId = $_GET['editar'] ?? '';
if ($clienteId) {
    $editarCliente = $db->fetch(
        "SELECT * FROM usuarios_globais WHERE id = ? AND ativo = true",
        [$clienteId]
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - <?php echo $config->get('app.name'); ?></title>
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
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
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
        
        .cliente-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .cliente-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box input {
            padding-left: 3rem;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: var(--primary-light);
            border: none;
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.8em;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .loading i {
            font-size: 2rem;
            color: var(--primary-color);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <a class="nav-link active" href="<?php echo $router->url('clientes'); ?>" data-tooltip="Clientes">
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
                                <i class="fas fa-users me-2"></i>
                                Clientes
                            </h2>
                                <p class="text-muted mb-0">Gerencie seus clientes e histórico de pedidos</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-outline-primary" onclick="exportarClientes()">
                                        <i class="fas fa-download me-1"></i>
                                        Exportar
                                    </button>
                                    <button class="btn btn-primary" onclick="abrirModalCliente()">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Cliente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card">
                                <div class="h4 mb-1" id="totalClientes">0</div>
                                <div class="small">Total de Clientes</div>
                                        </div>
                                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card">
                                <div class="h4 mb-1" id="clientesAtivos">0</div>
                                <div class="small">Clientes Ativos</div>
                                    </div>
                                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card">
                                <div class="h4 mb-1" id="novosClientes">0</div>
                                <div class="small">Novos Este Mês</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card">
                                <div class="h4 mb-1" id="ticketMedio">R$ 0,00</div>
                                <div class="small">Ticket Médio</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" class="form-control" id="searchClientes" placeholder="Buscar por nome, telefone ou email...">
                                        </div>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="filtroStatus">
                                        <option value="">Todos os status</option>
                                        <option value="ativo">Ativos</option>
                                        <option value="inativo">Inativos</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="filtroOrdenacao">
                                        <option value="nome">Nome</option>
                                        <option value="ultimo_pedido">Último Pedido</option>
                                        <option value="total_gasto">Maior Gasto</option>
                                        <option value="total_pedidos">Mais Pedidos</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div class="loading" id="loadingClientes">
                        <i class="fas fa-spinner"></i>
                        <p>Carregando clientes...</p>
                                        </div>

                    <!-- Clients List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Lista de Clientes
                            </h5>
                                    </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Contato</th>
                                            <th>Pedidos</th>
                                            <th>Total Gasto</th>
                                            <th>Último Pedido</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="clientesTableBody">
                                        <!-- Clientes serão carregados aqui via AJAX -->
                                    </tbody>
                                </table>
                                </div>
                            </div>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Clientes pagination" class="mt-4">
                        <ul class="pagination justify-content-center" id="paginationClientes">
                            <!-- Paginação será gerada aqui -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Cliente -->
    <div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <span id="modalClienteTitulo">Novo Cliente</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCliente">
                        <input type="hidden" id="clienteId">
                        <div class="row">
                            <div class="col-md-6">
                        <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="clienteNome" required>
                        </div>
                            </div>
                            <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control" id="clienteTelefone" placeholder="(11) 99999-9999">
                        </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="clienteEmail">
                        </div>
                            </div>
                            <div class="col-md-6">
                        <div class="mb-3">
                                    <label class="form-label">CPF</label>
                                    <input type="text" class="form-control" id="clienteCpf" placeholder="000.000.000-00">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data de Nascimento</label>
                                    <input type="date" class="form-control" id="clienteDataNascimento">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telefone Secundário</label>
                                    <input type="text" class="form-control" id="clienteTelefoneSecundario" placeholder="(11) 88888-8888">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="clienteObservacoes" rows="3" placeholder="Observações sobre o cliente..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarCliente()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes Cliente -->
    <div class="modal fade" id="modalDetalhesCliente" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        <span id="detalhesClienteNome">Detalhes do Cliente</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Informações do Cliente -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Informações Pessoais</h6>
                                </div>
                                <div class="card-body" id="detalhesClienteInfo">
                                    <!-- Informações serão carregadas aqui -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estatísticas -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Estatísticas</h6>
                                </div>
                                <div class="card-body" id="detalhesClienteStats">
                                    <!-- Estatísticas serão carregadas aqui -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estabelecimentos -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Estabelecimentos</h6>
                                </div>
                                <div class="card-body" id="detalhesClienteEstabelecimentos">
                                    <!-- Estabelecimentos serão carregados aqui -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mt-4" id="clienteTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos" type="button" role="tab">
                                <i class="fas fa-list me-1"></i> Histórico de Pedidos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pagamentos-tab" data-bs-toggle="tab" data-bs-target="#pagamentos" type="button" role="tab">
                                <i class="fas fa-credit-card me-1"></i> Pagamentos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="enderecos-tab" data-bs-toggle="tab" data-bs-target="#enderecos" type="button" role="tab">
                                <i class="fas fa-map-marker-alt me-1"></i> Endereços
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="clienteTabContent">
                        <div class="tab-pane fade show active" id="pedidos" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Data</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Estabelecimento</th>
                                            <th>Relação</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesClientePedidos">
                                        <!-- Pedidos serão carregados aqui -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="pagamentos" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Valor</th>
                                            <th>Forma</th>
                                            <th>Status</th>
                                            <th>Pedido</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesClientePagamentos">
                                        <!-- Pagamentos serão carregados aqui -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="enderecos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Endereços do Cliente</h6>
                                <button class="btn btn-sm btn-primary" onclick="adicionarEndereco()">
                                    <i class="fas fa-plus me-1"></i> Adicionar Endereço
                                </button>
                            </div>
                            <div id="detalhesClienteEnderecos">
                                <!-- Endereços serão carregados aqui -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="editarCliente()">
                        <i class="fas fa-edit me-1"></i> Editar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Endereço -->
    <div class="modal fade" id="modalEndereco" tabindex="-1" aria-hidden="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <span id="modalEnderecoTitulo">Adicionar Endereço</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEndereco">
                        <input type="hidden" id="enderecoId">
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="enderecoTipo">
                                <option value="entrega">Entrega</option>
                                <option value="cobranca">Cobrança</option>
                                <option value="residencial">Residencial</option>
                                <option value="comercial">Comercial</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="enderecoCep" placeholder="00000-000">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" id="enderecoNumero" placeholder="123">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Logradouro</label>
                            <input type="text" class="form-control" id="enderecoLogradouro" placeholder="Rua das Flores">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Complemento</label>
                            <input type="text" class="form-control" id="enderecoComplemento" placeholder="Apto 101">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="enderecoBairro" placeholder="Centro">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="enderecoCidade" placeholder="São Paulo">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">UF</label>
                                    <input type="text" class="form-control" id="enderecoEstado" placeholder="SP" maxlength="2">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referência</label>
                            <textarea class="form-control" id="enderecoReferencia" rows="2" placeholder="Próximo ao shopping..."></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enderecoPrincipal">
                            <label class="form-check-label" for="enderecoPrincipal">
                                Endereço Principal
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEndereco()">
                        <i class="fas fa-save me-1"></i> Salvar Endereço
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let clientes = [];
        let clienteAtual = null;
        let enderecosCliente = [];
        let paginaAtual = 1;
        const itensPorPagina = 20;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            carregarClientes();
            
            // Search functionality
            document.getElementById('searchClientes').addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    carregarClientes();
                }, 500);
            });
            
            // Filter functionality
            document.getElementById('filtroStatus').addEventListener('change', carregarClientes);
            document.getElementById('filtroOrdenacao').addEventListener('change', carregarClientes);
        });

        // Load clients
        function carregarClientes(pagina = 1) {
            paginaAtual = pagina;
            document.getElementById('loadingClientes').style.display = 'block';
            
            const search = document.getElementById('searchClientes').value;
            const status = document.getElementById('filtroStatus').value;
            const ordenacao = document.getElementById('filtroOrdenacao').value;
            
            const params = new URLSearchParams({
                action: 'listar',
                search: search,
                status: status,
                ordenacao: ordenacao,
                limit: itensPorPagina,
                offset: (pagina - 1) * itensPorPagina
            });
            
            fetch(`mvc/ajax/clientes.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        clientes = data.data;
                        renderizarClientes();
                        renderizarPaginacao(data.total || clientes.length);
                        carregarEstatisticas(); // Carregar estatísticas após carregar clientes
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Erro', 'Erro ao carregar clientes', 'error');
                })
                .finally(() => {
                    document.getElementById('loadingClientes').style.display = 'none';
                });
        }

        // Render clients table
        function renderizarClientes() {
            const tbody = document.getElementById('clientesTableBody');
            tbody.innerHTML = '';
            
            if (clientes.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>Nenhum cliente encontrado</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            clientes.forEach(cliente => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                ${cliente.nome.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="fw-bold">${cliente.nome}</div>
                                <small class="text-muted">ID: ${cliente.id}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>
                            ${cliente.telefone ? `<div><i class="fas fa-phone me-1"></i> ${cliente.telefone}</div>` : ''}
                            ${cliente.email ? `<div><i class="fas fa-envelope me-1"></i> ${cliente.email}</div>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-info">${cliente.total_pedidos || 0}</span>
                        ${cliente.pedidos_pagos ? `<br><small class="text-muted">Pagos: ${cliente.pedidos_pagos}</small>` : ''}
                    </td>
                    <td>
                        <strong>R$ ${parseFloat(cliente.total_gasto || 0).toFixed(2).replace('.', ',')}</strong>
                        ${cliente.total_pago ? `<br><small class="text-success">Pago: R$ ${parseFloat(cliente.total_pago).toFixed(2).replace('.', ',')}</small>` : ''}
                    </td>
                    <td>
                        ${cliente.ultimo_pedido ? new Date(cliente.ultimo_pedido).toLocaleDateString('pt-BR') : 'Nunca'}
                    </td>
                    <td>
                        <span class="badge bg-success">Ativo</span>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(${cliente.id})" title="Ver Detalhes">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editarCliente(${cliente.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="desativarCliente(${cliente.id})" title="Desativar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Render pagination
        function renderizarPaginacao(total) {
            const totalPaginas = Math.ceil(total / itensPorPagina);
            const pagination = document.getElementById('paginationClientes');
            pagination.innerHTML = '';
            
            if (totalPaginas <= 1) return;
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${paginaAtual === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="carregarClientes(${paginaAtual - 1})">Anterior</a>`;
            pagination.appendChild(prevLi);
            
            // Page numbers
            for (let i = 1; i <= totalPaginas; i++) {
                if (i === 1 || i === totalPaginas || (i >= paginaAtual - 2 && i <= paginaAtual + 2)) {
                    const li = document.createElement('li');
                    li.className = `page-item ${i === paginaAtual ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link" href="#" onclick="carregarClientes(${i})">${i}</a>`;
                    pagination.appendChild(li);
                } else if (i === paginaAtual - 3 || i === paginaAtual + 3) {
                    const li = document.createElement('li');
                    li.className = 'page-item disabled';
                    li.innerHTML = '<span class="page-link">...</span>';
                    pagination.appendChild(li);
                }
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="carregarClientes(${paginaAtual + 1})">Próximo</a>`;
            pagination.appendChild(nextLi);
        }

        // Load statistics
        function carregarEstatisticas() {
            console.log('Carregando estatísticas para', clientes.length, 'clientes');
            
            // Total de clientes
            const totalClientes = clientes.length;
            document.getElementById('totalClientes').textContent = totalClientes;
            
            // Clientes ativos
            const clientesAtivos = clientes.filter(c => c.ativo !== false).length;
            document.getElementById('clientesAtivos').textContent = clientesAtivos;
            
            // Novos este mês
            const agora = new Date();
            const mesAtual = agora.getMonth();
            const anoAtual = agora.getFullYear();
            
            const novosEsteMes = clientes.filter(c => {
                if (!c.created_at) return false;
                const dataCriacao = new Date(c.created_at);
                return dataCriacao.getMonth() === mesAtual && dataCriacao.getFullYear() === anoAtual;
            }).length;
            document.getElementById('novosClientes').textContent = novosEsteMes;
            
            // Ticket médio
            const totalGasto = clientes.reduce((sum, c) => {
                const gasto = parseFloat(c.total_gasto || 0);
                return sum + gasto;
            }, 0);
            
            const ticketMedio = totalClientes > 0 ? totalGasto / totalClientes : 0;
            document.getElementById('ticketMedio').textContent = `R$ ${ticketMedio.toFixed(2).replace('.', ',')}`;
            
            console.log('Estatísticas calculadas:', {
                totalClientes,
                clientesAtivos,
                novosEsteMes,
                ticketMedio
            });
        }

        // Open client modal
        function abrirModalCliente(clienteId = null) {
            if (clienteId) {
                const cliente = clientes.find(c => c.id === clienteId);
                if (cliente) {
                    document.getElementById('modalClienteTitulo').textContent = 'Editar Cliente';
                    document.getElementById('clienteId').value = cliente.id;
                    document.getElementById('clienteNome').value = cliente.nome;
                    document.getElementById('clienteTelefone').value = cliente.telefone || '';
                    document.getElementById('clienteEmail').value = cliente.email || '';
                    document.getElementById('clienteCpf').value = cliente.cpf || '';
                    document.getElementById('clienteDataNascimento').value = cliente.data_nascimento || '';
                    document.getElementById('clienteTelefoneSecundario').value = cliente.telefone_secundario || '';
                    document.getElementById('clienteObservacoes').value = cliente.observacoes || '';
                }
            } else {
            document.getElementById('modalClienteTitulo').textContent = 'Novo Cliente';
            document.getElementById('formCliente').reset();
                document.getElementById('clienteId').value = '';
        }

            new bootstrap.Modal(document.getElementById('modalCliente')).show();
        }

        // Save client
        function salvarCliente() {
            const formData = new FormData();
            formData.append('action', document.getElementById('clienteId').value ? 'atualizar' : 'criar');
            
            if (document.getElementById('clienteId').value) {
                formData.append('id', document.getElementById('clienteId').value);
            }
            
            formData.append('nome', document.getElementById('clienteNome').value);
            formData.append('telefone', document.getElementById('clienteTelefone').value);
            formData.append('email', document.getElementById('clienteEmail').value);
            formData.append('cpf', document.getElementById('clienteCpf').value);
            formData.append('data_nascimento', document.getElementById('clienteDataNascimento').value);
            formData.append('telefone_secundario', document.getElementById('clienteTelefoneSecundario').value);
            formData.append('observacoes', document.getElementById('clienteObservacoes').value);
            
            fetch('mvc/ajax/clientes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalCliente')).hide();
                    carregarClientes();
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao salvar cliente', 'error');
            });
        }

        // View client details
        function verDetalhes(clienteId) {
            clienteAtual = clientes.find(c => c.id === clienteId);
            if (!clienteAtual) return;
            
            document.getElementById('detalhesClienteNome').textContent = clienteAtual.nome;
            
            // Load client details
            carregarDetalhesCliente(clienteId);
            
            new bootstrap.Modal(document.getElementById('modalDetalhesCliente')).show();
        }

        // Load client details
        function carregarDetalhesCliente(clienteId) {
            // Load client info
            fetch(`mvc/ajax/clientes.php?action=estatisticas&cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderizarDetalhesCliente(data.data);
                    }
                });
            
            // Load orders
            fetch(`mvc/ajax/clientes.php?action=historico_pedidos&cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderizarHistoricoPedidos(data.data);
                    }
                });
            
            // Load payments
            fetch(`mvc/ajax/clientes.php?action=historico_pagamentos&cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderizarHistoricoPagamentos(data.data);
                    }
                });
            
            // Load establishments
            fetch(`mvc/ajax/clientes.php?action=estabelecimentos&cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderizarEstabelecimentos(data.data);
                    }
                });
            
            // Load addresses
            fetch(`mvc/ajax/clientes.php?action=enderecos&cliente_id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderizarEnderecos(data.data);
                    }
                });
        }

        // Render client details
        function renderizarDetalhesCliente(stats) {
            const info = document.getElementById('detalhesClienteInfo');
            info.innerHTML = `
                <div class="mb-2"><strong>Nome:</strong> ${clienteAtual.nome}</div>
                ${clienteAtual.telefone ? `<div class="mb-2"><strong>Telefone:</strong> ${clienteAtual.telefone}</div>` : ''}
                ${clienteAtual.email ? `<div class="mb-2"><strong>Email:</strong> ${clienteAtual.email}</div>` : ''}
                ${clienteAtual.cpf ? `<div class="mb-2"><strong>CPF:</strong> ${clienteAtual.cpf}</div>` : ''}
                ${clienteAtual.data_nascimento ? `<div class="mb-2"><strong>Nascimento:</strong> ${new Date(clienteAtual.data_nascimento).toLocaleDateString('pt-BR')}</div>` : ''}
                ${clienteAtual.observacoes ? `<div class="mb-2"><strong>Observações:</strong> ${clienteAtual.observacoes}</div>` : ''}
            `;
            
            const statsDiv = document.getElementById('detalhesClienteStats');
            statsDiv.innerHTML = `
                <div class="mb-2"><strong>Total de Pedidos:</strong> ${stats.total_pedidos || 0}</div>
                <div class="mb-2"><strong>Total Gasto:</strong> R$ ${parseFloat(stats.total_gasto || 0).toFixed(2).replace('.', ',')}</div>
                <div class="mb-2"><strong>Total Pago:</strong> R$ ${parseFloat(stats.total_pago || 0).toFixed(2).replace('.', ',')}</div>
                <div class="mb-2"><strong>Pedidos Pagos:</strong> ${stats.pedidos_pagos || 0}</div>
                <div class="mb-2"><strong>Ticket Médio:</strong> R$ ${parseFloat(stats.ticket_medio || 0).toFixed(2).replace('.', ',')}</div>
                <div class="mb-2"><strong>Último Pedido:</strong> ${stats.ultimo_pedido ? new Date(stats.ultimo_pedido).toLocaleDateString('pt-BR') : 'Nunca'}</div>
                <div class="mb-2"><strong>Último Pagamento:</strong> ${stats.ultimo_pagamento ? new Date(stats.ultimo_pagamento).toLocaleDateString('pt-BR') : 'Nunca'}</div>
                <div class="mb-2"><strong>Primeiro Pedido:</strong> ${stats.primeiro_pedido ? new Date(stats.primeiro_pedido).toLocaleDateString('pt-BR') : 'Nunca'}</div>
                <div class="mb-2"><strong>Estabelecimentos:</strong> ${stats.estabelecimentos_visitados || 0}</div>
            `;
        }

        // Render order history
        function renderizarHistoricoPedidos(pedidos) {
            const tbody = document.getElementById('detalhesClientePedidos');
            tbody.innerHTML = '';
            
            if (pedidos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum pedido encontrado</td></tr>';
                return;
            }
            
            pedidos.forEach(pedido => {
                const row = document.createElement('tr');
                const relacaoText = pedido.tipo_relacao === 'pagamento' ? 
                    `(Pagou R$ ${parseFloat(pedido.valor_pago_pelo_cliente || 0).toFixed(2).replace('.', ',')})` : 
                    '(Pedido original)';
                
                row.innerHTML = `
                    <td>#${pedido.idpedido}</td>
                    <td>${new Date(pedido.created_at).toLocaleDateString('pt-BR')}</td>
                    <td>R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}</td>
                    <td><span class="badge bg-${getStatusColor(pedido.status_pagamento || pedido.status)}">${pedido.status_pagamento || pedido.status}</span></td>
                    <td>${pedido.tenant_nome || 'N/A'}</td>
                    <td><small class="text-muted">${relacaoText}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesPedido(${pedido.idpedido})" title="Ver detalhes do pedido">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Render payment history
        function renderizarHistoricoPagamentos(pagamentos) {
            const tbody = document.getElementById('detalhesClientePagamentos');
            tbody.innerHTML = '';
            
            if (pagamentos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum pagamento encontrado</td></tr>';
                return;
            }
            
            pagamentos.forEach(pagamento => {
                const row = document.createElement('tr');
                const valor = parseFloat(pagamento.valor_pago || 0);
                const status = 'Confirmado'; // Default status for payments
                const data = pagamento.created_at ? new Date(pagamento.created_at).toLocaleDateString('pt-BR') : 'N/A';
                
                row.innerHTML = `
                    <td>${data}</td>
                    <td>R$ ${valor.toFixed(2).replace('.', ',')}</td>
                    <td>${pagamento.forma_pagamento || 'N/A'}</td>
                    <td><span class="badge bg-success">${status}</span></td>
                    <td>#${pagamento.pedido_id || 'N/A'}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Render establishments with pagination
        function renderizarEstabelecimentos(estabelecimentos) {
            const div = document.getElementById('detalhesClienteEstabelecimentos');
            div.innerHTML = '';
            
            if (estabelecimentos.length === 0) {
                div.innerHTML = '<p class="text-muted">Nenhum estabelecimento visitado</p>';
                return;
            }
            
            // Create container with scroll if more than 3 establishments
            const maxHeight = estabelecimentos.length > 3 ? '300px' : 'auto';
            const container = document.createElement('div');
            container.style.maxHeight = maxHeight;
            container.style.overflowY = maxHeight !== 'auto' ? 'auto' : 'visible';
            container.style.paddingRight = maxHeight !== 'auto' ? '10px' : '0';
            
            // Add scrollbar styling
            if (maxHeight !== 'auto') {
                container.style.scrollbarWidth = 'thin';
                container.style.scrollbarColor = '#6c757d #f8f9fa';
            }
            
            estabelecimentos.forEach(est => {
                const card = document.createElement('div');
                card.className = 'mb-2 p-3 border rounded bg-light';
                
                // Calculate real data
                const totalPedidos = est.total_pedidos || 0;
                const pedidosOriginais = est.pedidos_originais || 0;
                const pedidosPagos = est.pedidos_pagos || 0;
                const totalGasto = est.total_gasto_original || 0;
                const totalPago = est.total_pago || 0;
                const ultimaVisita = est.ultima_visita ? new Date(est.ultima_visita).toLocaleDateString('pt-BR') : 'N/A';
                
                // Build detailed information
                let infoText = `Pedidos: ${totalPedidos}`;
                
                if (pedidosOriginais > 0 && pedidosPagos > 0) {
                    infoText += ` (${pedidosOriginais} originais + ${pedidosPagos} pagos)`;
                } else if (pedidosOriginais > 0) {
                    infoText += ` (${pedidosOriginais} originais)`;
                } else if (pedidosPagos > 0) {
                    infoText += ` (${pedidosPagos} pagos)`;
                }
                
                if (totalGasto > 0 || totalPago > 0) {
                    infoText += ` | `;
                    if (totalGasto > 0) {
                        infoText += `Gasto: R$ ${parseFloat(totalGasto).toFixed(2).replace('.', ',')}`;
                    }
                    if (totalPago > 0) {
                        if (totalGasto > 0) infoText += ` + `;
                        infoText += `Pago: R$ ${parseFloat(totalPago).toFixed(2).replace('.', ',')}`;
                    }
                }
                
                infoText += ` | Última visita: ${ultimaVisita}`;
                
                card.innerHTML = `
                    <div class="fw-bold text-primary">${est.tenant_nome}</div>
                    <small class="text-muted">${infoText}</small>
                `;
                container.appendChild(card);
            });
            
            // Add scroll indicator if needed
            if (maxHeight !== 'auto') {
                const indicator = document.createElement('div');
                indicator.className = 'text-center text-muted mt-2';
                indicator.innerHTML = '<small><i class="fas fa-arrows-alt-v"></i> Role para ver mais estabelecimentos</small>';
                container.appendChild(indicator);
            }
            
            div.appendChild(container);
        }

        // Render addresses
        function renderizarEnderecos(enderecos) {
            const div = document.getElementById('detalhesClienteEnderecos');
            div.innerHTML = '';
            
            // Store addresses for editing
            enderecosCliente = enderecos || [];
            
            if (enderecos.length === 0) {
                div.innerHTML = '<p class="text-muted">Nenhum endereço cadastrado</p>';
                return;
            }
            
            enderecos.forEach(endereco => {
                const card = document.createElement('div');
                card.className = 'mb-2 p-3 border rounded';
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">${endereco.tipo}</div>
                            <div>${endereco.logradouro}, ${endereco.numero}</div>
                            <div>${endereco.bairro} - ${endereco.cidade}/${endereco.estado}</div>
                            <div class="text-muted small">CEP: ${endereco.cep}</div>
                            ${endereco.principal ? '<span class="badge bg-primary">Principal</span>' : ''}
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editarEndereco(${endereco.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="removerEndereco(${endereco.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                div.appendChild(card);
            });
        }

        // Get status color
        function getStatusColor(status) {
            const colors = {
                'Pendente': 'warning',
                'Preparando': 'info',
                'Pronto': 'success',
                'Entregue': 'success',
                'Finalizado': 'success',
                'Cancelado': 'danger',
                'pago': 'success',
                'pendente': 'warning',
                'parcial': 'info',
                'quitado': 'success',
                'cancelado': 'danger'
            };
            return colors[status] || 'secondary';
        }

        // View order details
        function verDetalhesPedido(pedidoId) {
            fetch(`mvc/ajax/clientes.php?action=detalhes_pedido&pedido_id=${pedidoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarDetalhesPedido(data.data);
                    } else {
                        Swal.fire('Erro', data.message || 'Erro ao carregar detalhes do pedido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Erro', 'Erro ao carregar detalhes do pedido', 'error');
                });
        }

        // Show order details modal
        function mostrarDetalhesPedido(pedido) {
            const modalHtml = `
                <div class="modal fade" id="modalDetalhesPedido" tabindex="-1" aria-labelledby="modalDetalhesPedidoLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="modalDetalhesPedidoLabel">
                                    <i class="fas fa-receipt me-2"></i>
                                    Detalhes do Pedido #${pedido.idpedido}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Data:</strong> ${new Date(pedido.created_at).toLocaleDateString('pt-BR')} ${new Date(pedido.created_at).toLocaleTimeString('pt-BR')}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Status:</strong> 
                                        <span class="badge bg-${getStatusColor(pedido.status_pagamento || pedido.status)}">
                                            ${pedido.status_pagamento || pedido.status}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Cliente:</strong> ${pedido.cliente || 'N/A'}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Telefone:</strong> ${pedido.telefone_cliente || 'N/A'}
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Mesa:</strong> ${pedido.mesa_numero || 'N/A'}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Estabelecimento:</strong> ${pedido.tenant_nome || 'N/A'}
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h6><i class="fas fa-list me-1"></i> Itens do Pedido</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Quantidade</th>
                                                <th>Preço Unit.</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${pedido.itens ? pedido.itens.map(item => `
                                                <tr>
                                                    <td>${item.nome_produto}</td>
                                                    <td>${item.quantidade}</td>
                                                    <td>R$ ${parseFloat(item.valor_unitario).toFixed(2).replace('.', ',')}</td>
                                                    <td>R$ ${parseFloat(item.valor_total).toFixed(2).replace('.', ',')}</td>
                                                </tr>
                                            `).join('') : '<tr><td colspan="4" class="text-center text-muted">Nenhum item encontrado</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Valor Total:</strong> R$ ${parseFloat(pedido.valor_total).toFixed(2).replace('.', ',')}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Valor Pago:</strong> R$ ${parseFloat(pedido.valor_pago || 0).toFixed(2).replace('.', ',')}
                                    </div>
                                </div>
                                
                                ${pedido.saldo_devedor > 0 ? `
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>Saldo Devedor:</strong> 
                                            <span class="text-danger">R$ ${parseFloat(pedido.saldo_devedor).toFixed(2).replace('.', ',')}</span>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                ${pedido.forma_pagamento ? `
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>Forma de Pagamento:</strong> ${pedido.forma_pagamento}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                ${pedido.observacao ? `
                                    <hr>
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Observações:</strong><br>
                                            <small class="text-muted">${pedido.observacao}</small>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('modalDetalhesPedido');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhesPedido'));
            modal.show();
            
            // Remove modal from DOM when hidden
            document.getElementById('modalDetalhesPedido').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Deactivate client
        function desativarCliente(clienteId) {
            Swal.fire({
                title: 'Desativar Cliente',
                text: 'Deseja realmente desativar este cliente?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desativar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'desativar');
                    formData.append('id', clienteId);
                    
                    fetch('mvc/ajax/clientes.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            carregarClientes();
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Export clients
        function exportarClientes() {
            Swal.fire('Info', 'Funcionalidade de exportação será implementada em breve', 'info');
        }

        // Edit client
        function editarCliente(clienteId = null) {
            if (clienteId) {
                abrirModalCliente(clienteId);
            } else if (clienteAtual) {
                abrirModalCliente(clienteAtual.id);
                bootstrap.Modal.getInstance(document.getElementById('modalDetalhesCliente')).hide();
            }
        }

        // Add address
        function adicionarEndereco() {
            if (!clienteAtual) {
                Swal.fire('Erro', 'Nenhum cliente selecionado', 'error');
                return;
            }
            
            document.getElementById('formEndereco').reset();
            document.getElementById('enderecoId').value = '';
            document.getElementById('modalEnderecoTitulo').textContent = 'Adicionar Endereço';
            new bootstrap.Modal(document.getElementById('modalEndereco')).show();
        }

        // Edit address
        function editarEndereco(enderecoId) {
            if (!clienteAtual) {
                Swal.fire('Erro', 'Nenhum cliente selecionado', 'error');
                return;
            }

            // Find address in stored addresses
            const endereco = enderecosCliente.find(e => e.id == enderecoId);
            if (!endereco) {
                Swal.fire('Erro', 'Endereço não encontrado', 'error');
                return;
            }

            // Fill form with address data
            document.getElementById('enderecoId').value = endereco.id;
            document.getElementById('enderecoTipo').value = endereco.tipo;
            document.getElementById('enderecoCep').value = endereco.cep || '';
            document.getElementById('enderecoLogradouro').value = endereco.logradouro || '';
            document.getElementById('enderecoNumero').value = endereco.numero || '';
            document.getElementById('enderecoComplemento').value = endereco.complemento || '';
            document.getElementById('enderecoBairro').value = endereco.bairro || '';
            document.getElementById('enderecoCidade').value = endereco.cidade || '';
            document.getElementById('enderecoEstado').value = endereco.estado || '';
            document.getElementById('enderecoReferencia').value = endereco.referencia || '';
            document.getElementById('enderecoPrincipal').checked = endereco.principal || false;

            document.getElementById('modalEnderecoTitulo').textContent = 'Editar Endereço';
            new bootstrap.Modal(document.getElementById('modalEndereco')).show();
        }

        // Save address
        function salvarEndereco() {
            if (!clienteAtual) {
                Swal.fire('Erro', 'Nenhum cliente selecionado', 'error');
                return;
            }

            const enderecoId = document.getElementById('enderecoId').value;
            const isEdit = enderecoId !== '';

            const formData = new FormData();
            formData.append('action', isEdit ? 'atualizar_endereco' : 'adicionar_endereco');
            formData.append('tipo', document.getElementById('enderecoTipo').value);
            formData.append('cep', document.getElementById('enderecoCep').value);
            formData.append('logradouro', document.getElementById('enderecoLogradouro').value);
            formData.append('numero', document.getElementById('enderecoNumero').value);
            formData.append('complemento', document.getElementById('enderecoComplemento').value);
            formData.append('bairro', document.getElementById('enderecoBairro').value);
            formData.append('cidade', document.getElementById('enderecoCidade').value);
            formData.append('estado', document.getElementById('enderecoEstado').value);
            formData.append('referencia', document.getElementById('enderecoReferencia').value);
            formData.append('principal', document.getElementById('enderecoPrincipal').checked);

            if (isEdit) {
                formData.append('id', enderecoId);
            } else {
                formData.append('cliente_id', clienteAtual.id);
            }

            fetch('mvc/ajax/clientes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalEndereco')).hide();
                    carregarDetalhesCliente(clienteAtual.id);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao salvar endereço', 'error');
            });
        }

        // Remove address
        function removerEndereco(enderecoId) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Você não poderá reverter isso!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'remover_endereco');
                    formData.append('id', enderecoId);

                    fetch('mvc/ajax/clientes.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Removido!', data.message, 'success');
                            carregarDetalhesCliente(clienteAtual.id);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao remover endereço', 'error');
                    });
                }
            });
        }
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
</body>
</html>