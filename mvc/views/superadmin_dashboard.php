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
                            <div class="col-md-3">
                                <select class="form-select" id="payment-status-filter">
                                    <option value="">Todos os status</option>
                                    <option value="pago">Pago</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="falhou">Falhou</option>
                                </select>
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
            $.get('mvc/controller/SuperAdminController.php?action=getDashboardStats', function(data) {
                $('#total-tenants').text(data.tenants.total_tenants || 0);
                $('#active-subscriptions').text(data.subscriptions.ativas || 0);
                $('#monthly-revenue').text('R$ ' + (data.subscriptions.receita_mensal || 0).toFixed(2));
                $('#trial-count').text(data.subscriptions.trial || 0);
            });
        }

        // Load Tenants
        function loadTenants() {
            const search = $('#tenant-search').val();
            const status = $('#tenant-status-filter').val();
            
            $.get('mvc/controller/SuperAdminController.php?action=listTenants', {
                search: search,
                status: status,
                limit: 100
            }, function(data) {
                let html = '';
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
                                <button class="btn btn-sm btn-primary" onclick="editTenant(${tenant.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="toggleTenantStatus(${tenant.id})">
                                    <i class="fas fa-power-off"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
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
                                    <button class="btn btn-sm btn-outline-primary" onclick="editTenant(${tenant.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#recent-tenants-table tbody').html(recentHtml);
                }
            });
        }

        // Load Plans
        function loadPlans() {
            $.get('mvc/controller/SuperAdminController.php?action=listPlans', function(data) {
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
            });
        }

        // Load Subscriptions
        function loadSubscriptions() {
            $.get('mvc/controller/SuperAdminController.php?action=listSubscriptions', function(data) {
                // Implementation here
            });
        }

        // Load Payments
        function loadPayments() {
            const status = $('#payment-status-filter').val();
            $.get('mvc/controller/SuperAdminController.php?action=listPayments', { status: status }, function(data) {
                let html = '';
                data.forEach(payment => {
                    const statusBadge = getPaymentStatusBadge(payment.status);
                    html += `
                        <tr>
                            <td>${payment.id}</td>
                            <td>${payment.tenant_nome}</td>
                            <td>${payment.plano_nome}</td>
                            <td>R$ ${parseFloat(payment.valor).toFixed(2)}</td>
                            <td>${statusBadge}</td>
                            <td>${formatDate(payment.data_vencimento)}</td>
                            <td>${payment.data_pagamento ? formatDate(payment.data_pagamento) : '-'}</td>
                            <td>
                                ${payment.status === 'pendente' ? `
                                    <button class="btn btn-sm btn-success" onclick="markPaymentAsPaid(${payment.id})">
                                        <i class="fas fa-check"></i> Marcar como Pago
                                    </button>
                                ` : ''}
                            </td>
                        </tr>
                    `;
                });
                $('#payments-table tbody').html(html);
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
            
            $('#tenant-search').on('input', loadTenants);
            $('#tenant-status-filter').change(loadTenants);
            $('#payment-status-filter').change(loadPayments);
        });

        // CRUD Functions (to be implemented)
        function showCreateTenantModal() {
            // Implementation
        }

        function editTenant(id) {
            // Implementation
        }

        function toggleTenantStatus(id) {
            // Implementation
        }

        function showCreatePlanModal() {
            // Implementation
        }

        function editPlan(id) {
            // Implementation
        }

        function deletePlan(id) {
            // Implementation
        }

        function markPaymentAsPaid(id) {
            Swal.fire({
                title: 'Confirmar Pagamento?',
                text: 'Marcar este pagamento como pago?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('mvc/controller/SuperAdminController.php?action=markPaymentAsPaid', 
                        JSON.stringify({ payment_id: id }),
                        function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Pagamento confirmado', 'success');
                                loadPayments();
                            } else {
                                Swal.fire('Erro!', 'Erro ao confirmar pagamento', 'error');
                            }
                        }
                    );
                }
            });
        }
    </script>
</body>
</html>

