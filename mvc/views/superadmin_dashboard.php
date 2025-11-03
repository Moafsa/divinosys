<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SuperAdmin - Divino Lanches SaaS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: #2d3748;
            min-height: 100vh;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #a0aec0;
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-left-color: #667eea;
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        .main-content {
            padding: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .stat-card.primary .icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.success .icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.warning .icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.info .icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0 5px 0;
        }
        .stat-card .label {
            color: #718096;
            font-size: 14px;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .badge-custom {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .header-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="text-center mb-4">
                    <h4 class="text-white"><i class="fas fa-crown text-warning"></i> SuperAdmin</h4>
                    <small class="text-muted">Divino Lanches SaaS</small>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-section="dashboard">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="tenants">
                            <i class="fas fa-building"></i> Estabelecimentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="plans">
                            <i class="fas fa-tags"></i> Planos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="subscriptions">
                            <i class="fas fa-credit-card"></i> Assinaturas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="payments">
                            <i class="fas fa-money-bill-wave"></i> Pagamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="analytics">
                            <i class="fas fa-chart-line"></i> Análises
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="index.php?view=logout">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <div class="header-bar">
                    <div>
                        <h3 class="mb-0">Painel de Controle</h3>
                        <small class="text-muted">Visão geral do sistema</small>
                    </div>
                    <div>
                        <span class="text-muted me-3"><i class="far fa-clock"></i> <span id="current-time"></span></span>
                        <button class="btn btn-gradient btn-sm" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                    </div>
                </div>

                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section active">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card primary">
                                <div class="d-flex align-items-center">
                                    <div class="icon"><i class="fas fa-building"></i></div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="value" id="total-tenants">0</div>
                                        <div class="label">Estabelecimentos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card success">
                                <div class="d-flex align-items-center">
                                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="value" id="active-subscriptions">0</div>
                                        <div class="label">Assinaturas Ativas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card warning">
                                <div class="d-flex align-items-center">
                                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="value" id="monthly-revenue">R$ 0</div>
                                        <div class="label">Receita Mensal</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card info">
                                <div class="d-flex align-items-center">
                                    <div class="icon"><i class="fas fa-user-clock"></i></div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="value" id="trial-count">0</div>
                                        <div class="label">Contas Trial</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-8">
                            <div class="table-card">
                                <h5 class="mb-3"><i class="fas fa-building text-primary"></i> Últimos Estabelecimentos</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="recent-tenants-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Subdomain</th>
                                                <th>Plano</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="table-card">
                                <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-warning"></i> Alertas</h5>
                                <div id="alerts-container">
                                    <div class="alert alert-warning mb-2">
                                        <small><i class="fas fa-clock"></i> <span id="overdue-payments">0</span> pagamentos vencidos</small>
                                    </div>
                                    <div class="alert alert-info mb-2">
                                        <small><i class="fas fa-hourglass-half"></i> <span id="trial-ending">0</span> trials expirando</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenants Section -->
                <div id="tenants-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4><i class="fas fa-building"></i> Gerenciar Estabelecimentos</h4>
                        <button class="btn btn-gradient" onclick="showCreateTenantModal()">
                            <i class="fas fa-plus"></i> Novo Estabelecimento
                        </button>
                    </div>
                    <div class="table-card">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="tenant-search" placeholder="Buscar estabelecimento...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="tenant-status-filter">
                                    <option value="">Todos os status</option>
                                    <option value="ativo">Ativo</option>
                                    <option value="suspenso">Suspenso</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="tenants-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Subdomain</th>
                                        <th>Plano</th>
                                        <th>Filiais</th>
                                        <th>Usuários</th>
                                        <th>Status</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Plans Section -->
                <div id="plans-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4><i class="fas fa-tags"></i> Gerenciar Planos</h4>
                        <button class="btn btn-gradient" onclick="showCreatePlanModal()">
                            <i class="fas fa-plus"></i> Novo Plano
                        </button>
                    </div>
                    <div class="row" id="plans-container"></div>
                </div>

                <!-- Subscriptions Section -->
                <div id="subscriptions-section" class="content-section">
                    <h4 class="mb-4"><i class="fas fa-credit-card"></i> Gerenciar Assinaturas</h4>
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover" id="subscriptions-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Estabelecimento</th>
                                        <th>Plano</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Próxima Cobrança</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payments Section -->
                <div id="payments-section" class="content-section">
                    <h4 class="mb-4"><i class="fas fa-money-bill-wave"></i> Gerenciar Pagamentos</h4>
                    <div class="table-card">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="payment-search" placeholder="Buscar por estabelecimento, ID ou valor...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="payment-status-filter">
                                    <option value="">Todos os status</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="pago">Pago</option>
                                    <option value="falhou">Falhou</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary w-100" onclick="loadPayments()">
                                    <i class="fas fa-sync-alt me-1"></i>Atualizar
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="payments-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Estabelecimento</th>
                                        <th>Plano</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Vencimento</th>
                                        <th>Pagamento</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Section -->
                <div id="whatsapp-section" class="content-section">
                    <h4 class="mb-4"><i class="fab fa-whatsapp"></i> Gerenciar Instâncias WhatsApp</h4>
                    
                    <div class="table-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-list"></i> Instâncias Ativas</h5>
                            <button class="btn btn-primary" onclick="showCreateWhatsAppInstanceModal()">
                                <i class="fas fa-plus"></i> Nova Instância
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="whatsapp-instances-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Estabelecimento</th>
                                        <th>Filial</th>
                                        <th>Número</th>
                                        <th>Status</th>
                                        <th>QR Code</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div id="analytics-section" class="content-section">
                    <h4 class="mb-4"><i class="fas fa-chart-line"></i> Análises e Relatórios</h4>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="table-card">
                                <canvas id="revenue-chart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Navigation
        $('.nav-link[data-section]').click(function(e) {
            e.preventDefault();
            const section = $(this).data('section');
            
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            
            $('.content-section').removeClass('active');
            $(`#${section}-section`).addClass('active');
            
            loadSectionData(section);
        });

        // Clock
        function updateClock() {
            const now = new Date();
            $('#current-time').text(now.toLocaleTimeString('pt-BR'));
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Load Dashboard Stats
        function loadDashboardStats() {
            $.ajax({
                url: 'index.php?action=getDashboardStats',
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(data) {
                console.log('Dashboard stats loaded:', data);
                $('#total-tenants').text(data.tenants?.total_tenants || 0);
                $('#active-subscriptions').text(data.subscriptions?.ativas || 0);
                $('#monthly-revenue').text('R$ ' + parseFloat(data.subscriptions?.receita_mensal || 0).toFixed(2));
                $('#trial-count').text(data.subscriptions?.trial || 0);
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar stats do dashboard:', error);
                console.error('Response:', xhr.responseText);
                $('#total-tenants').text('0');
                $('#active-subscriptions').text('0');
                $('#monthly-revenue').text('R$ 0,00');
                $('#trial-count').text('0');
            });
        }

        // Load Tenants
        function loadTenants() {
            const search = $('#tenant-search').val();
            const status = $('#tenant-status-filter').val();
            
            $.ajax({
                url: 'index.php?action=listTenants',
                method: 'GET',
                data: {
                    search: search,
                    status: status,
                    limit: 100
                },
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(data) {
                console.log('Tenants loaded:', data);
                let html = '';
                if (Array.isArray(data)) {
                    data.forEach(tenant => {
                        const statusBadge = getStatusBadge(tenant.status);
                        html += `
                            <tr>
                                <td>${tenant.id}</td>
                                <td>${tenant.nome}</td>
                                <td><code>${tenant.subdomain}</code></td>
                                <td>${tenant.plano_nome || 'N/A'}</td>
                                <td>${tenant.total_filiais || 0}</td>
                                <td>${tenant.total_usuarios || 0}</td>
                                <td>${statusBadge}</td>
                                <td>${formatDate(tenant.created_at)}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editTenant(${tenant.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="toggleTenantStatus(${tenant.id})" title="Ativar/Desativar">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteTenant(${tenant.id}, '${tenant.nome}')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#tenants-table tbody').html(html);
                
                // Load recent tenants for dashboard
                if (data.length > 0) {
                    let recentHtml = '';
                    data.slice(0, 5).forEach(tenant => {
                        const statusBadge = getStatusBadge(tenant.status);
                        recentHtml += `
                            <tr>
                                <td>${tenant.nome}</td>
                                <td><code>${tenant.subdomain}</code></td>
                                <td>${tenant.plano_nome || 'N/A'}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editTenant(${tenant.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTenant(${tenant.id}, '${tenant.nome}')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#recent-tenants-table tbody').html(recentHtml);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar tenants:', error);
                console.error('Response:', xhr.responseText);
                $('#tenants-table tbody').html('<tr><td colspan="9" class="text-center text-danger">Erro ao carregar dados</td></tr>');
            });
        }

        // Load Plans
        function loadPlans() {
            $.ajax({
                url: 'index.php?action=listPlans',
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(data) {
                let html = '';
                data.forEach(plan => {
                    html += `
                        <div class="col-md-3 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">${plan.nome}</h5>
                                    <h3 class="text-primary">R$ ${parseFloat(plan.preco_mensal).toFixed(2)}</h3>
                                    <p class="text-muted">/mês</p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> ${plan.max_mesas == -1 ? 'Ilimitado' : plan.max_mesas} mesas</li>
                                        <li><i class="fas fa-check text-success"></i> ${plan.max_usuarios == -1 ? 'Ilimitado' : plan.max_usuarios} usuários</li>
                                        <li><i class="fas fa-check text-success"></i> ${plan.max_produtos == -1 ? 'Ilimitado' : plan.max_produtos} produtos</li>
                                        <li><i class="fas fa-check text-success"></i> ${plan.max_pedidos_mes == -1 ? 'Ilimitado' : plan.max_pedidos_mes} pedidos/mês</li>
                                    </ul>
                                    <button class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="editPlan(${plan.id})">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger w-100" onclick="deletePlan(${plan.id})">
                                        <i class="fas fa-trash"></i> Deletar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $('#plans-container').html(html);
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar planos:', error);
                $('#plans-container').html('<div class="col-12"><div class="alert alert-danger">Erro ao carregar planos</div></div>');
            });
        }

        // Load Subscriptions
        function loadSubscriptions() {
            $.ajax({
                url: 'index.php?action=listSubscriptions',
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(data) {
                let html = '';
                data.forEach(subscription => {
                    const statusBadge = getStatusBadge(subscription.status);
                    html += `
                        <tr>
                            <td>${subscription.id}</td>
                            <td>${subscription.tenant_nome}</td>
                            <td>${subscription.plano_nome}</td>
                            <td>R$ ${parseFloat(subscription.valor).toFixed(2)}</td>
                            <td>${subscription.periodicidade}</td>
                            <td>${subscription.data_inicio}</td>
                            <td>${subscription.data_proxima_cobranca || 'N/A'}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editSubscription(${subscription.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSubscription(${subscription.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#subscriptions-table tbody').html(html);
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar assinaturas:', error);
                $('#subscriptions-table tbody').html('<tr><td colspan="9" class="text-center text-danger">Erro ao carregar dados</td></tr>');
            });
        }

        // Load Payments
        function loadPayments() {
            const status = $('#payment-status-filter').val();
            console.log('🔄 loadPayments() - Carregando pagamentos com filtro:', status);
            
            $.ajax({
                url: 'index.php?action=listPayments',
                method: 'GET',
                data: { status: status },
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(data) {
                console.log('✅ Pagamentos recebidos:', data.length, 'registros');
                console.log('Dados:', data);
                
                // Aplicar filtro de busca
                const searchTerm = $('#payment-search').val().toLowerCase();
                let filteredData = data;
                
                if (searchTerm) {
                    filteredData = data.filter(payment => {
                        const id = String(payment.id);
                        const tenant = (payment.tenant_nome || '').toLowerCase();
                        const valor = String(payment.valor);
                        const plano = (payment.plano_nome || '').toLowerCase();
                        
                        return id.includes(searchTerm) ||
                               tenant.includes(searchTerm) ||
                               valor.includes(searchTerm) ||
                               plano.includes(searchTerm);
                    });
                    
                    console.log(`🔍 Busca por "${searchTerm}": ${filteredData.length} de ${data.length} registros`);
                }
                
                let html = '';
                filteredData.forEach(payment => {
                    const statusBadge = getPaymentStatusBadge(payment.status);
                    // Destacar linhas com faturas pendentes
                    const rowClass = payment.status === 'pendente' ? 'table-warning' : '';
                    html += `
                        <tr class="${rowClass}">
                            <td>${payment.id}</td>
                            <td><strong>${payment.tenant_nome}</strong></td>
                            <td>${payment.plano_nome}</td>
                            <td><strong>R$ ${parseFloat(payment.valor).toFixed(2)}</strong></td>
                            <td>${statusBadge}</td>
                            <td>${formatDate(payment.data_vencimento)}</td>
                            <td>${payment.data_pagamento ? formatDate(payment.data_pagamento) : '-'}</td>
                            <td>
                                ${payment.status === 'pendente' ? `
                                    <button class="btn btn-success" onclick="markPaymentAsPaid(${payment.id})" title="Quitar esta fatura manualmente">
                                        <i class="fas fa-hand-holding-usd me-1"></i>
                                        <strong>Quitar Fatura</strong>
                                    </button>
                                ` : payment.status === 'pago' ? `
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Pago
                                    </span>
                                ` : `
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>${payment.status}
                                    </span>
                                `}
                            </td>
                        </tr>
                    `;
                });
                
                if (html === '') {
                    if (searchTerm) {
                        html = `<tr><td colspan="8" class="text-center text-muted">
                            <i class="fas fa-search me-2"></i>Nenhum resultado encontrado para "${searchTerm}"
                        </td></tr>`;
                    } else {
                        html = '<tr><td colspan="8" class="text-center text-muted">Nenhum pagamento encontrado</td></tr>';
                    }
                }
                
                $('#payments-table tbody').html(html);
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar pagamentos:', error);
                $('#payments-table tbody').html('<tr><td colspan="8" class="text-center text-danger">Erro ao carregar dados</td></tr>');
            });
        }

        // Utility Functions
        function getStatusBadge(status) {
            const badges = {
                'ativo': '<span class="badge bg-success">Ativo</span>',
                'suspenso': '<span class="badge bg-warning">Suspenso</span>',
                'inativo': '<span class="badge bg-secondary">Inativo</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">N/A</span>';
        }

        function getPaymentStatusBadge(status) {
            const badges = {
                'pago': '<span class="badge bg-success">Pago</span>',
                'pendente': '<span class="badge bg-warning">Pendente</span>',
                'falhou': '<span class="badge bg-danger">Falhou</span>',
                'cancelado': '<span class="badge bg-secondary">Cancelado</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">N/A</span>';
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }

        function loadSectionData(section) {
            switch(section) {
                case 'dashboard':
                    loadDashboardStats();
                    loadTenants();
                    break;
                case 'tenants':
                    loadTenants();
                    break;
                case 'plans':
                    loadPlans();
                    break;
                case 'subscriptions':
                    loadSubscriptions();
                    break;
                case 'payments':
                    loadPayments();
                    break;
            }
        }

        function refreshData() {
            const currentSection = $('.content-section.active').attr('id').replace('-section', '');
            loadSectionData(currentSection);
            
            Swal.fire({
                icon: 'success',
                title: 'Atualizado!',
                text: 'Dados atualizados com sucesso',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Initialize
        $(document).ready(function() {
            loadDashboardStats();
            loadTenants();
            loadPlans();
            loadSubscriptions();
            loadPayments();
            
            $('#tenant-search').on('input', loadTenants);
            $('#tenant-status-filter').change(loadTenants);
            $('#payment-search').on('input', loadPayments); // Busca em tempo real
            $('#payment-status-filter').change(loadPayments);
            
            // Section navigation
            $('.nav-link[data-section]').click(function(e) {
                e.preventDefault();
                const section = $(this).data('section');
                
                // Hide all sections
                $('.content-section').removeClass('active');
                
                // Show selected section
                $(`#${section}-section`).addClass('active');
                
                // Update nav active state
                $('.nav-link').removeClass('active');
                $(this).addClass('active');
                
                // Load data for section
                if (section === 'whatsapp') {
                    loadWhatsAppInstances();
                } else if (section === 'payments') {
                    loadPayments(); // Carregar pagamentos ao abrir seção
                } else if (section === 'subscriptions') {
                    loadSubscriptions();
                } else if (section === 'plans') {
                    loadPlans();
                }
            });
            
            // Show dashboard by default
            $('#dashboard-section').addClass('active');
        });

        // CRUD Functions
        function showCreateTenantModal() {
            const modal = `
                <div class="modal fade" id="createTenantModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Novo Estabelecimento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="createTenantForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nome do Estabelecimento *</label>
                                            <input type="text" class="form-control" name="nome" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Subdomain *</label>
                                            <input type="text" class="form-control" name="subdomain" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">CNPJ</label>
                                            <input type="text" class="form-control" name="cnpj">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" class="form-control" name="telefone">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Plano *</label>
                                            <select class="form-select" name="plano_id" required>
                                                <option value="">Selecione um plano</option>
                                            </select>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label">Endereço</label>
                                            <textarea class="form-control" name="endereco" rows="2"></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="saveTenant()">Salvar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (!$('#createTenantModal').length) {
                $('body').append(modal);
            }
            
            // Carregar planos
            $.ajax({
                url: 'index.php?action=listPlans',
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(plans) {
                let options = '<option value="">Selecione um plano</option>';
                plans.forEach(plan => {
                    options += `<option value="${plan.id}">${plan.nome} - R$ ${parseFloat(plan.preco_mensal).toFixed(2)}</option>`;
                });
                $('#createTenantModal select[name="plano_id"]').html(options);
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar planos:', error);
            });
            
            $('#createTenantModal').modal('show');
        }

        function editTenant(id) {
            // Buscar dados do tenant
            $.ajax({
                url: 'index.php?action=getTenant&id=' + id,
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(tenant) {
                const modal = `
                    <div class="modal fade" id="editTenantModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Estabelecimento</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editTenantForm">
                                        <input type="hidden" name="id" value="${tenant.id}">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nome do Estabelecimento *</label>
                                                <input type="text" class="form-control" name="nome" value="${tenant.nome}" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Subdomain *</label>
                                                <input type="text" class="form-control" name="subdomain" value="${tenant.subdomain}" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">CNPJ</label>
                                                <input type="text" class="form-control" name="cnpj" value="${tenant.cnpj || ''}">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Telefone</label>
                                                <input type="text" class="form-control" name="telefone" value="${tenant.telefone || ''}">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="email" value="${tenant.email || ''}">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="ativo" ${tenant.status === 'ativo' ? 'selected' : ''}>Ativo</option>
                                                    <option value="suspenso" ${tenant.status === 'suspenso' ? 'selected' : ''}>Suspenso</option>
                                                    <option value="inativo" ${tenant.status === 'inativo' ? 'selected' : ''}>Inativo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Plano *</label>
                                                <select class="form-select" name="plano_id" id="editTenantPlanoSelect" required>
                                                    <option value="">Carregando...</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Periodicidade da Assinatura *</label>
                                                <select class="form-select" name="periodicidade" id="editTenantPeriodicidade" required>
                                                    <option value="mensal">Mensal</option>
                                                    <option value="semestral">Semestral</option>
                                                    <option value="anual">Anual</option>
                                                </select>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label">Endereço</label>
                                                <textarea class="form-control" name="endereco" rows="2">${tenant.endereco || ''}</textarea>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="updateTenant()">Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (!$('#editTenantModal').length) {
                    $('body').append(modal);
                } else {
                    $('#editTenantModal').remove();
                    $('body').append(modal);
                }
                
                // Carregar planos
                $.ajax({
                    url: 'index.php?action=listPlans',
                    method: 'GET'
                })
                .done(function(plans) {
                    let options = '<option value="">Selecione um plano</option>';
                    plans.forEach(plan => {
                        const selected = tenant.plano_id == plan.id ? 'selected' : '';
                        options += `<option value="${plan.id}" ${selected}>${plan.nome} - R$ ${parseFloat(plan.preco_mensal).toFixed(2)}</option>`;
                    });
                    $('#editTenantPlanoSelect').html(options);
                })
                .fail(function() {
                    $('#editTenantPlanoSelect').html('<option value="">Erro ao carregar planos</option>');
                });
                
                // Buscar periodicidade da assinatura
                $.ajax({
                    url: 'index.php?action=getTenantSubscription&tenant_id=' + id,
                    method: 'GET'
                })
                .done(function(subscription) {
                    if (subscription && subscription.periodicidade) {
                        $('#editTenantPeriodicidade').val(subscription.periodicidade);
                    }
                })
                .fail(function() {
                    console.error('Erro ao carregar assinatura');
                });
                
                $('#editTenantModal').modal('show');
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar tenant:', error);
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao carregar dados do estabelecimento',
                    icon: 'error'
                });
            });
        }

        function toggleTenantStatus(id) {
            Swal.fire({
                title: 'Confirmar Alteração?',
                text: 'Deseja alterar o status deste estabelecimento?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, alterar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'index.php?action=toggleTenantStatus',
                        method: 'POST',
                        data: JSON.stringify({ tenant_id: id }),
                        contentType: 'application/json',
                        xhrFields: {
                            withCredentials: true
                        },
                        crossDomain: false
                    })
                    .done(function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Status alterado com sucesso', 'success');
                                loadTenants();
                            } else {
                                Swal.fire('Erro!', 'Erro ao alterar status', 'error');
                            }
                        })
                        .fail(function(xhr, status, error) {
                            console.error('Erro ao alterar status:', error);
                            Swal.fire('Erro!', 'Erro ao alterar status', 'error');
                        });
                }
            });
        }

        function deleteTenant(id, nome) {
            Swal.fire({
                title: 'Excluir Estabelecimento?',
                html: `Tem certeza que deseja excluir <strong>${nome}</strong>?<br><small class="text-danger">Esta ação não pode ser desfeita!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'index.php?action=deleteTenant',
                        method: 'POST',
                        data: JSON.stringify({ tenant_id: id }),
                        contentType: 'application/json'
                    })
                    .done(function(response) {
                        if (response.success) {
                            Swal.fire('Excluído!', 'Estabelecimento excluído com sucesso', 'success');
                            loadTenants();
                            loadStats();
                        } else {
                            Swal.fire('Erro!', response.error || 'Erro ao excluir estabelecimento', 'error');
                        }
                    })
                    .fail(function() {
                        Swal.fire('Erro!', 'Erro ao excluir estabelecimento', 'error');
                    });
                }
            });
        }

        function showCreatePlanModal() {
            const modal = `
                <div class="modal fade" id="createPlanModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Novo Plano</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="createPlanForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nome do Plano *</label>
                                            <input type="text" class="form-control" name="nome" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Preço Mensal *</label>
                                            <input type="number" class="form-control" name="preco_mensal" step="0.01" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Máx. Mesas (-1 = ilimitado)</label>
                                            <input type="number" class="form-control" name="max_mesas" value="10">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Máx. Usuários (-1 = ilimitado)</label>
                                            <input type="number" class="form-control" name="max_usuarios" value="3">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Máx. Produtos (-1 = ilimitado)</label>
                                            <input type="number" class="form-control" name="max_produtos" value="100">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Máx. Pedidos/mês (-1 = ilimitado)</label>
                                            <input type="number" class="form-control" name="max_pedidos_mes" value="1000">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Máx. Filiais (-1 = ilimitado)</label>
                                            <input type="number" class="form-control" name="max_filiais" value="1">
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold">Recursos Incluídos</label>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="relatorios_basicos" name="recursos[]" value="relatorios_basicos" checked>
                                                        <label class="form-check-label" for="relatorios_basicos">Relatórios Básicos</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="relatorios_avancados" name="recursos[]" value="relatorios_avancados">
                                                        <label class="form-check-label" for="relatorios_avancados">Relatórios Avançados</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="relatorios_customizados" name="recursos[]" value="relatorios_customizados">
                                                        <label class="form-check-label" for="relatorios_customizados">Relatórios Customizados</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="suporte_email" name="recursos[]" value="suporte_email" checked>
                                                        <label class="form-check-label" for="suporte_email">Suporte por Email</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="suporte_whatsapp" name="recursos[]" value="suporte_whatsapp">
                                                        <label class="form-check-label" for="suporte_whatsapp">Suporte por WhatsApp</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="suporte_telefone" name="recursos[]" value="suporte_telefone">
                                                        <label class="form-check-label" for="suporte_telefone">Suporte por Telefone</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="suporte_dedicado" name="recursos[]" value="suporte_dedicado">
                                                        <label class="form-check-label" for="suporte_dedicado">Suporte Dedicado</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="emissao_nfe" name="recursos[]" value="emissao_nfe">
                                                        <label class="form-check-label" for="emissao_nfe">Emissão de Nota Fiscal</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="backup_diario" name="recursos[]" value="backup_diario">
                                                        <label class="form-check-label" for="backup_diario">Backup Diário</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="backup_tempo_real" name="recursos[]" value="backup_tempo_real">
                                                        <label class="form-check-label" for="backup_tempo_real">Backup em Tempo Real</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="api_acesso" name="recursos[]" value="api_acesso">
                                                        <label class="form-check-label" for="api_acesso">Acesso à API</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="white_label" name="recursos[]" value="white_label">
                                                        <label class="form-check-label" for="white_label">White Label</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="integracoes_customizadas" name="recursos[]" value="integracoes_customizadas">
                                                        <label class="form-check-label" for="integracoes_customizadas">Integrações Customizadas</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="savePlan()">Salvar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (!$('#createPlanModal').length) {
                $('body').append(modal);
            }
            
            $('#createPlanModal').modal('show');
        }

        function editPlan(id) {
            // Buscar dados do plano
            $.ajax({
                url: 'index.php?action=getPlan&id=' + id,
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(plan) {
                // Parse recursos if string
                const recursos = typeof plan.recursos === 'string' ? JSON.parse(plan.recursos) : plan.recursos;
                
                const modal = `
                    <div class="modal fade" id="editPlanModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Plano</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editPlanForm">
                                        <input type="hidden" name="id" value="${plan.id}">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nome do Plano *</label>
                                                <input type="text" class="form-control" name="nome" value="${plan.nome}" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Preço Mensal *</label>
                                                <input type="number" class="form-control" name="preco_mensal" value="${plan.preco_mensal}" step="0.01" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Máx. Mesas (-1 = ilimitado)</label>
                                                <input type="number" class="form-control" name="max_mesas" value="${plan.max_mesas}">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Máx. Usuários (-1 = ilimitado)</label>
                                                <input type="number" class="form-control" name="max_usuarios" value="${plan.max_usuarios}">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Máx. Produtos (-1 = ilimitado)</label>
                                                <input type="number" class="form-control" name="max_produtos" value="${plan.max_produtos}">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Máx. Pedidos/mês (-1 = ilimitado)</label>
                                                <input type="number" class="form-control" name="max_pedidos_mes" value="${plan.max_pedidos_mes}">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Máx. Filiais (-1 = ilimitado)</label>
                                                <input type="number" class="form-control" name="max_filiais" value="${plan.max_filiais || 1}">
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label fw-bold">Recursos Incluídos</label>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_relatorios_basicos" name="recursos[]" value="relatorios_basicos" ${recursos?.relatorios_basicos ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_relatorios_basicos">Relatórios Básicos</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_relatorios_avancados" name="recursos[]" value="relatorios_avancados" ${recursos?.relatorios_avancados ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_relatorios_avancados">Relatórios Avançados</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_relatorios_customizados" name="recursos[]" value="relatorios_customizados" ${recursos?.relatorios_customizados ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_relatorios_customizados">Relatórios Customizados</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_suporte_email" name="recursos[]" value="suporte_email" ${recursos?.suporte_email ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_suporte_email">Suporte por Email</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_suporte_whatsapp" name="recursos[]" value="suporte_whatsapp" ${recursos?.suporte_whatsapp ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_suporte_whatsapp">Suporte por WhatsApp</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_suporte_telefone" name="recursos[]" value="suporte_telefone" ${recursos?.suporte_telefone ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_suporte_telefone">Suporte por Telefone</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_suporte_dedicado" name="recursos[]" value="suporte_dedicado" ${recursos?.suporte_dedicado ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_suporte_dedicado">Suporte Dedicado</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_emissao_nfe" name="recursos[]" value="emissao_nfe" ${recursos?.emissao_nfe ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_emissao_nfe">Emissão de Nota Fiscal</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_backup_diario" name="recursos[]" value="backup_diario" ${recursos?.backup_diario ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_backup_diario">Backup Diário</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_backup_tempo_real" name="recursos[]" value="backup_tempo_real" ${recursos?.backup_tempo_real ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_backup_tempo_real">Backup em Tempo Real</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_api_acesso" name="recursos[]" value="api_acesso" ${recursos?.api_acesso ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_api_acesso">Acesso à API</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_white_label" name="recursos[]" value="white_label" ${recursos?.white_label ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_white_label">White Label</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="edit_integracoes_customizadas" name="recursos[]" value="integracoes_customizadas" ${recursos?.integracoes_customizadas ? 'checked' : ''}>
                                                            <label class="form-check-label" for="edit_integracoes_customizadas">Integrações Customizadas</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="updatePlan()">Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (!$('#editPlanModal').length) {
                    $('body').append(modal);
                } else {
                    $('#editPlanModal').remove();
                    $('body').append(modal);
                }
                
                $('#editPlanModal').modal('show');
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar plano:', error);
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao carregar dados do plano',
                    icon: 'error'
                });
            });
        }

        function deletePlan(id) {
            Swal.fire({
                title: 'Confirmar Exclusão?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'mvc/controller/SuperAdminController.php?action=deletePlan&id=' + id,
                        type: 'DELETE',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deletado!', 'Plano removido com sucesso', 'success');
                                loadPlans();
                            } else {
                                Swal.fire('Erro!', response.error || 'Erro ao deletar plano', 'error');
                            }
                        }
                    });
                }
            });
        }

        // Funções de salvamento
        function saveTenant() {
            const formData = new FormData(document.getElementById('createTenantForm'));
            const data = Object.fromEntries(formData.entries());
            
            $.post('mvc/controller/SuperAdminController.php?action=createTenant', 
                JSON.stringify(data),
                function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', 'Estabelecimento criado com sucesso', 'success');
                        $('#createTenantModal').modal('hide');
                        loadTenants();
                    } else {
                        Swal.fire('Erro!', response.error || 'Erro ao criar estabelecimento', 'error');
                    }
                }
            );
        }

        function updateTenant() {
            const formData = new FormData(document.getElementById('editTenantForm'));
            const data = Object.fromEntries(formData.entries());
            
            $.ajax({
                url: 'index.php?action=updateTenant',
                type: 'PUT',
                data: JSON.stringify(data),
                contentType: 'application/json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', 'Estabelecimento atualizado com sucesso', 'success');
                        $('#editTenantModal').modal('hide');
                        loadTenants();
                        loadStats();
                    } else {
                        Swal.fire('Erro!', response.error || 'Erro ao atualizar estabelecimento', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao atualizar tenant:', error);
                    Swal.fire('Erro!', 'Erro ao atualizar estabelecimento', 'error');
                }
            });
        }

        function savePlan() {
            const formData = new FormData(document.getElementById('createPlanForm'));
            const data = {};
            
            // Processar campos normais
            for (const [key, value] of formData.entries()) {
                if (key !== 'recursos[]') {
                    data[key] = value;
                }
            }
            
            // Processar checkboxes de recursos
            const recursos = {};
            formData.getAll('recursos[]').forEach(recurso => {
                recursos[recurso] = true;
            });
            data.recursos = recursos;
            
            $.post('mvc/controller/SuperAdminController.php?action=createPlan', 
                JSON.stringify(data),
                function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', 'Plano criado com sucesso', 'success');
                        $('#createPlanModal').modal('hide');
                        loadPlans();
                    } else {
                        Swal.fire('Erro!', response.error || 'Erro ao criar plano', 'error');
                    }
                }
            );
        }

        function updatePlan() {
            const formData = new FormData(document.getElementById('editPlanForm'));
            const data = {};
            
            // Processar campos normais
            for (const [key, value] of formData.entries()) {
                if (key !== 'recursos[]') {
                    data[key] = value;
                }
            }
            
            // Processar checkboxes de recursos
            const recursos = {};
            formData.getAll('recursos[]').forEach(recurso => {
                recursos[recurso] = true;
            });
            data.recursos = recursos;
            
            $.ajax({
                url: 'index.php?action=updatePlan',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Plano atualizado com sucesso', 'success');
                    $('#editPlanModal').modal('hide');
                    loadPlans();
                } else {
                    Swal.fire('Erro!', response.error || 'Erro ao atualizar plano', 'error');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao atualizar plano:', error);
                Swal.fire('Erro!', 'Erro ao atualizar plano', 'error');
            });
        }

        function editSubscription(id) {
            // Buscar dados da assinatura
            $.ajax({
                url: 'index.php?action=getSubscription&id=' + id,
                method: 'GET',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(subscription) {
                const modal = `
                    <div class="modal fade" id="editSubscriptionModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Assinatura</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editSubscriptionForm">
                                        <input type="hidden" name="id" value="${subscription.id}">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Estabelecimento</label>
                                                <input type="text" class="form-control" value="${subscription.tenant_nome}" readonly>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Plano</label>
                                                <select class="form-select" name="plano_id" required>
                                                    <option value="${subscription.plano_id}">${subscription.plano_nome}</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="ativa" ${subscription.status === 'ativa' ? 'selected' : ''}>Ativa</option>
                                                    <option value="trial" ${subscription.status === 'trial' ? 'selected' : ''}>Trial</option>
                                                    <option value="suspensa" ${subscription.status === 'suspensa' ? 'selected' : ''}>Suspensa</option>
                                                    <option value="cancelada" ${subscription.status === 'cancelada' ? 'selected' : ''}>Cancelada</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Valor</label>
                                                <input type="number" class="form-control" name="valor" value="${subscription.valor}" step="0.01" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Data de Início</label>
                                                <input type="date" class="form-control" name="data_inicio" value="${subscription.data_inicio}" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Próxima Cobrança</label>
                                                <input type="date" class="form-control" name="data_proxima_cobranca" value="${subscription.data_proxima_cobranca}" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Periodicidade</label>
                                                <select class="form-select" name="periodicidade" required>
                                                    <option value="mensal" ${subscription.periodicidade === 'mensal' ? 'selected' : ''}>Mensal</option>
                                                    <option value="trimestral" ${subscription.periodicidade === 'trimestral' ? 'selected' : ''}>Trimestral</option>
                                                    <option value="semestral" ${subscription.periodicidade === 'semestral' ? 'selected' : ''}>Semestral</option>
                                                    <option value="anual" ${subscription.periodicidade === 'anual' ? 'selected' : ''}>Anual</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Trial Até</label>
                                                <input type="date" class="form-control" name="trial_ate" value="${subscription.trial_ate || ''}">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="updateSubscription()">Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (!$('#editSubscriptionModal').length) {
                    $('body').append(modal);
                } else {
                    $('#editSubscriptionModal').remove();
                    $('body').append(modal);
                }
                
                $('#editSubscriptionModal').modal('show');
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao carregar assinatura:', error);
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao carregar dados da assinatura',
                    icon: 'error'
                });
            });
        }

        function updateSubscription() {
            const formData = new FormData(document.getElementById('editSubscriptionForm'));
            const data = Object.fromEntries(formData.entries());
            
            $.ajax({
                url: 'index.php?action=updateSubscription',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                xhrFields: {
                    withCredentials: true
                },
                crossDomain: false
            })
            .done(function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Assinatura atualizada com sucesso', 'success');
                    $('#editSubscriptionModal').modal('hide');
                    loadSubscriptions();
                } else {
                    Swal.fire('Erro!', response.error || 'Erro ao atualizar assinatura', 'error');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Erro ao atualizar assinatura:', error);
                Swal.fire('Erro!', 'Erro ao atualizar assinatura', 'error');
            });
        }

        function deleteSubscription(id) {
            Swal.fire({
                title: 'Confirmar Exclusão?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'index.php?action=deleteSubscription&id=' + id,
                        method: 'DELETE',
                        xhrFields: {
                            withCredentials: true
                        },
                        crossDomain: false
                    })
                    .done(function(response) {
                        if (response) {
                            Swal.fire('Sucesso!', 'Assinatura deletada com sucesso', 'success');
                            loadSubscriptions();
                        } else {
                            Swal.fire('Erro!', 'Erro ao deletar assinatura', 'error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Erro ao deletar assinatura:', error);
                        Swal.fire('Erro!', 'Erro ao deletar assinatura', 'error');
                    });
                }
            });
        }

        function markPaymentAsPaid(id) {
            Swal.fire({
                title: 'Quitar Fatura Manualmente?',
                html: `
                    <p class="mb-3">Esta ação irá:</p>
                    <ul class="text-start">
                        <li>✅ Marcar como <strong>PAGO</strong> no sistema local</li>
                        <li>✅ Reativar a assinatura e o estabelecimento</li>
                        <li>✅ Desbloquear acesso completo do tenant</li>
                    </ul>
                    <div class="alert alert-warning mt-3 mb-2">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Importante:</strong> A cobrança no Asaas continuará ativa. O Asaas irá gerar a próxima cobrança automaticamente conforme a periodicidade da assinatura.
                        </small>
                    </div>
                    <div class="alert alert-info mb-0">
                        <small>
                            <i class="fas fa-lightbulb me-1"></i>
                            <strong>Quando usar:</strong> Cliente pagou fora do sistema (dinheiro, TED, PIX direto, etc) ou acordo especial.
                        </small>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-hand-holding-usd me-2"></i>Sim, Quitar Manualmente',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                width: '650px'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Processando...',
                        text: 'Quitando fatura localmente e reativando estabelecimento...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: 'index.php?action=markPaymentAsPaid',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ payment_id: id }),
                        xhrFields: {
                            withCredentials: true
                        },
                        crossDomain: false
                    })
                    .done(function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Sucesso!',
                                html: `
                                    <p>${response.message}</p>
                                    <p class="text-success mt-2">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Estabelecimento pode criar pedidos novamente!
                                    </p>
                                `,
                                icon: 'success'
                            });
                            loadPayments();
                            loadDashboardStats(); // Atualizar estatísticas
                        } else {
                            Swal.fire('Erro!', response.error || 'Erro ao confirmar pagamento', 'error');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Erro ao confirmar pagamento:', error);
                        Swal.fire('Erro!', 'Erro ao comunicar com o servidor: ' + error, 'error');
                    });
                }
            });
        }
        // WhatsApp Functions
        function loadWhatsAppInstances() {
            $.ajax({
                url: 'index.php?action=listWhatsAppInstances',
                method: 'GET'
            })
            .done(function(data) {
                let html = '';
                if (data.length > 0) {
                    data.forEach(instance => {
                        const statusBadge = instance.status === 'ativo' 
                            ? '<span class="badge bg-success">Conectado</span>'
                            : '<span class="badge bg-warning">Desconectado</span>';
                        
                        html += `
                            <tr>
                                <td>${instance.id}</td>
                                <td>${instance.tenant_nome || 'N/A'}</td>
                                <td>${instance.filial_nome || 'N/A'}</td>
                                <td>${instance.phone_number || 'Não configurado'}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    ${instance.status !== 'ativo' ? 
                                        `<button class="btn btn-sm btn-info" onclick="showQRCode(${instance.id})">
                                            <i class="fas fa-qrcode"></i> Ver QR
                                        </button>` : 
                                        '<span class="text-success"><i class="fas fa-check-circle"></i> Conectado</span>'
                                    }
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="deleteWhatsAppInstance(${instance.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="7" class="text-center">Nenhuma instância WhatsApp cadastrada</td></tr>';
                }
                $('#whatsapp-instances-table tbody').html(html);
            })
            .fail(function() {
                $('#whatsapp-instances-table tbody').html('<tr><td colspan="7" class="text-center text-danger">Erro ao carregar instâncias</td></tr>');
            });
        }

        function showCreateWhatsAppInstanceModal() {
            const modal = `
                <div class="modal fade" id="createWhatsAppInstanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Nova Instância WhatsApp</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="createWhatsAppInstanceForm">
                                    <div class="mb-3">
                                        <label class="form-label">Tipo de Instância *</label>
                                        <select class="form-select" name="instance_type" id="whatsappInstanceType" required>
                                            <option value="superadmin">SuperAdmin (Global - Envio de Faturas)</option>
                                            <option value="establishment">Estabelecimento Específico</option>
                                        </select>
                                    </div>
                                    <div id="establishmentFields" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">Estabelecimento *</label>
                                            <select class="form-select" name="tenant_id" id="whatsappTenantSelect">
                                                <option value="">Carregando...</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Filial *</label>
                                            <select class="form-select" name="filial_id" id="whatsappFilialSelect">
                                                <option value="">Selecione um estabelecimento primeiro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nome da Instância *</label>
                                        <input type="text" class="form-control" name="instance_name" placeholder="Ex: WhatsApp SuperAdmin" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Número de Telefone *</label>
                                        <input type="text" class="form-control" name="phone_number" placeholder="Ex: 5554999999999" required>
                                        <small class="text-muted">Formato: DDI + DDD + Número (sem espaços)</small>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="createWhatsAppInstance()">Criar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (!$('#createWhatsAppInstanceModal').length) {
                $('body').append(modal);
            } else {
                $('#createWhatsAppInstanceModal').remove();
                $('body').append(modal);
            }
            
            // Toggle establishment fields based on instance type
            $('#whatsappInstanceType').on('change', function() {
                if ($(this).val() === 'establishment') {
                    $('#establishmentFields').show();
                    $('#whatsappTenantSelect').attr('required', true);
                    $('#whatsappFilialSelect').attr('required', true);
                } else {
                    $('#establishmentFields').hide();
                    $('#whatsappTenantSelect').removeAttr('required');
                    $('#whatsappFilialSelect').removeAttr('required');
                }
            });
            
            // Carregar lista de tenants
            $.ajax({
                url: 'index.php?action=listTenants',
                method: 'GET'
            })
            .done(function(tenants) {
                let options = '<option value="">Selecione um estabelecimento</option>';
                tenants.forEach(tenant => {
                    options += `<option value="${tenant.id}">${tenant.nome}</option>`;
                });
                $('#whatsappTenantSelect').html(options);
                
                // Ao selecionar tenant, carregar filiais
                $('#whatsappTenantSelect').on('change', function() {
                    const tenantId = $(this).val();
                    if (tenantId) {
                        $.ajax({
                            url: 'index.php?action=getFiliais&tenant_id=' + tenantId,
                            method: 'GET'
                        })
                        .done(function(filiais) {
                            let filialOptions = '<option value="">Selecione uma filial</option>';
                            filiais.forEach(filial => {
                                filialOptions += `<option value="${filial.id}">${filial.nome}</option>`;
                            });
                            $('#whatsappFilialSelect').html(filialOptions);
                        });
                    }
                });
            });
            
            $('#createWhatsAppInstanceModal').modal('show');
        }

        function createWhatsAppInstance() {
            const formData = new FormData(document.getElementById('createWhatsAppInstanceForm'));
            const data = Object.fromEntries(formData.entries());
            
            $.ajax({
                url: 'index.php?action=createWhatsAppInstance',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json'
            })
            .done(function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', 'Instância criada! Escaneie o QR Code para conectar.', 'success');
                    $('#createWhatsAppInstanceModal').modal('hide');
                    loadWhatsAppInstances();
                    if (response.qr_code) {
                        showQRCodeImage(response.qr_code);
                    }
                } else {
                    Swal.fire('Erro!', response.error || 'Erro ao criar instância', 'error');
                }
            })
            .fail(function() {
                Swal.fire('Erro!', 'Erro ao criar instância WhatsApp', 'error');
            });
        }

        function showQRCode(instanceId) {
            $.ajax({
                url: 'index.php?action=getWhatsAppQRCode&instance_id=' + instanceId,
                method: 'GET'
            })
            .done(function(response) {
                if (response.success && response.qr_code) {
                    showQRCodeImage(response.qr_code);
                } else {
                    Swal.fire('Erro!', 'QR Code não disponível', 'error');
                }
            })
            .fail(function() {
                Swal.fire('Erro!', 'Erro ao buscar QR Code', 'error');
            });
        }

        function showQRCodeImage(qrCode) {
            Swal.fire({
                title: 'Escaneie o QR Code',
                html: `<img src="${qrCode}" style="width: 100%; max-width: 400px;" />`,
                width: 500,
                confirmButtonText: 'Fechar'
            });
        }

        function deleteWhatsAppInstance(id) {
            Swal.fire({
                title: 'Excluir Instância?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'index.php?action=deleteWhatsAppInstance',
                        method: 'POST',
                        data: JSON.stringify({ instance_id: id }),
                        contentType: 'application/json'
                    })
                    .done(function(response) {
                        if (response.success) {
                            Swal.fire('Excluído!', 'Instância excluída com sucesso', 'success');
                            loadWhatsAppInstances();
                        } else {
                            Swal.fire('Erro!', response.error || 'Erro ao excluir instância', 'error');
                        }
                    })
                    .fail(function() {
                        Swal.fire('Erro!', 'Erro ao excluir instância', 'error');
                    });
                }
            });
        }

    </script>
</body>
</html>

