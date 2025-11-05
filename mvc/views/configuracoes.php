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

// Get tenant plan and resources
$planoRecursos = [];
if ($tenant && isset($tenant['plano_id'])) {
    $plano = $db->fetch(
        "SELECT recursos FROM planos WHERE id = ?",
        [$tenant['plano_id']]
    );
    if ($plano && !empty($plano['recursos'])) {
        $planoRecursos = is_string($plano['recursos']) 
            ? json_decode($plano['recursos'], true) 
            : $plano['recursos'];
    }
}

// Check if NFe is enabled in plan
$nfeHabilitado = isset($planoRecursos['emissao_nfe']) && $planoRecursos['emissao_nfe'] === true;

// Get current mesa configuration
$numeroMesasAtual = 25; // default
$capacidadeMesaAtual = 4; // default

if ($tenant && $filial) {
    // Count existing mesas
    $mesasExistentes = $db->fetchAll(
        "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY numero::integer",
        [$tenant['id'], $filial['id']]
    );
    
    if (!empty($mesasExistentes)) {
        $numeroMesasAtual = count($mesasExistentes);
        // Get capacity from first mesa (assuming all have same capacity)
        $capacidadeMesaAtual = $mesasExistentes[0]['capacidade'] ?? 4;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
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
        
        .config-card {
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
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>" data-tooltip="Clientes">
                            <i class="fas fa-users"></i>
                            <span>Clientes</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
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
                                <i class="fas fa-cog me-2"></i>
                                Configurações
                            </h2>
                            <p class="text-muted mb-0">Configurações do sistema</p>
                        </div>
                    </div>
                </div>

                <!-- Configurações do Sistema -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-palette me-2"></i>
                        Aparência
                    </h5>
                    <form id="configAparencia">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cor Primária</label>
                                    <input type="color" class="form-control form-control-color" id="corPrimaria" value="<?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome do Estabelecimento</label>
                                    <input type="text" class="form-control" id="nomeEstabelecimento" value="<?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Configurações de Mesas -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-table me-2"></i>
                        Mesas
                    </h5>
                    <form id="configMesas">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Número de Mesas</label>
                                    <input type="number" class="form-control" id="numeroMesas" value="<?php echo $numeroMesasAtual; ?>" min="1" max="50">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Capacidade por Mesa</label>
                                    <input type="number" class="form-control" id="capacidadeMesa" value="<?php echo $capacidadeMesaAtual; ?>" min="1" max="20">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Configurações de Usuários -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>
                        Usuários
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Usuário Atual</label>
                                <input type="text" class="form-control" value="<?php echo $user['login'] ?? 'admin'; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nível de Acesso</label>
                                <input type="text" class="form-control" value="<?php echo $user['nivel'] ?? 'admin'; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary" onclick="alterarSenha()">
                        <i class="fas fa-key me-1"></i>
                        Alterar Senha
                    </button>
                </div>

                <!-- Configurações de Backup -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-database me-2"></i>
                        Backup
                    </h5>
                    <p class="text-muted">Faça backup dos seus dados regularmente</p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-success" onclick="fazerBackup()">
                            <i class="fas fa-download me-1"></i>
                            Fazer Backup
                        </button>
                        <button class="btn btn-outline-warning" onclick="restaurarBackup()">
                            <i class="fas fa-upload me-1"></i>
                            Restaurar Backup
                        </button>
                    </div>
                </div>

                <!-- Configurações de Usuários -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>
                        Gerenciar Usuários
                    </h5>
                    <p class="text-muted mb-3">Gerencie usuários internos do estabelecimento</p>
                    
                    <!-- Lista de Usuários -->
                    <div id="usuariosList" class="mb-3">
                        <!-- Usuários serão carregados aqui via AJAX -->
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="abrirModalNovoUsuario()">
                            <i class="fas fa-plus me-2"></i>
                            Novo Usuário
                        </button>
                    </div>
                </div>

                <!-- Configurações de Filiais - Apenas para filial principal (matriz) -->
                <?php 
                // Apenas a primeira filial (matriz) pode gerenciar outras filiais
                $isFilialPrincipal = false;
                if ($filial && $tenant) {
                    // Buscar a primeira filial deste tenant (matriz)
                    $primeiraFilial = $db->fetch(
                        "SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", 
                        [$tenant['id']]
                    );
                    
                    error_log("configuracoes.php - Filial atual: {$filial['id']}, Primeira filial: " . ($primeiraFilial['id'] ?? 'NULL'));
                    
                    // Se a filial atual é a primeira do tenant, é a matriz
                    if ($primeiraFilial && $filial['id'] == $primeiraFilial['id']) {
                        $isFilialPrincipal = true;
                        error_log("configuracoes.php - É filial principal! Mostrando seção Gerenciar Filiais.");
                    }
                } else if ($tenant && !$filial) {
                    // Se não há filial na sessão, assumir que é a matriz
                    $isFilialPrincipal = true;
                    error_log("configuracoes.php - Sem filial na sessão, assumindo matriz.");
                }
                ?>
                
                <?php if ($isFilialPrincipal): ?>
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-store me-2"></i>
                        Gerenciar Filiais
                    </h5>
                    <p class="text-muted mb-3">Crie filiais como estabelecimentos independentes</p>
                    
                    <!-- Lista de Filiais -->
                    <div id="filiaisList" class="mb-3">
                        <!-- Filiais serão carregadas aqui via AJAX -->
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="abrirModalNovaFilial()">
                            <i class="fas fa-plus me-2"></i>
                            Nova Filial
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Configurações WhatsApp -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fab fa-whatsapp me-2"></i>
                        WhatsApp - WuzAPI
                    </h5>
                    <p class="text-muted mb-3">Configure instâncias do WhatsApp via WuzAPI para gerenciar mensagens do sistema</p>
                    <button class="btn btn-primary" onclick="abrirModalNovaCaixaEntrada()">
                        <i class="fas fa-plus me-2"></i>Nova Instância
                    </button>
                    
                    <!-- Lista de Instâncias -->
                    <div id="caixasEntradaList" class="mt-3">
                        <!-- Instâncias serão carregadas aqui via AJAX -->
                    </div>
                </div>

                <!-- Configurações Asaas -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-credit-card me-2"></i>
                        Integração Asaas
                    </h5>
                    <p class="text-muted mb-3">Configure sua integração com o Asaas para emissão de notas fiscais e cobrança de pedidos</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status da Integração</label>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2" id="asaas-status-badge">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Configurado
                                    </span>
                                    <button class="btn btn-sm btn-outline-primary" onclick="testarConexaoAsaas()">
                                        <i class="fas fa-plug me-1"></i>
                                        Testar Conexão
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ambiente</label>
                                <select class="form-select" id="asaas-environment" onchange="atualizarConfiguracaoAsaas()">
                                    <option value="sandbox">Sandbox (Teste)</option>
                                    <option value="production">Produção</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Chave API</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="asaas-api-key" placeholder="Digite sua chave API do Asaas">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('asaas-api-key')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    Obtenha sua chave API em <a href="https://www.asaas.com/" target="_blank">www.asaas.com</a>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">ID do Cliente</label>
                                <input type="text" class="form-control" id="asaas-customer-id" placeholder="ID do cliente no Asaas">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="asaas-enabled" onchange="atualizarConfiguracaoAsaas()">
                                    <label class="form-check-label" for="asaas-enabled">
                                        Habilitar integração com Asaas
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <button class="btn btn-success" onclick="salvarConfiguracaoAsaas()">
                                    <i class="fas fa-save me-2"></i>
                                    Salvar Configuração
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações Fiscais -->
                    <?php if ($nfeHabilitado): ?>
                    <hr class="my-4">
                    <h6 class="mb-3">
                        <i class="fas fa-file-invoice me-2"></i>
                        Informações Fiscais
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="fiscal-cnpj" placeholder="00.000.000/0000-00" maxlength="18">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Razão Social</label>
                                <input type="text" class="form-control" id="fiscal-razao-social" placeholder="Nome da empresa">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nome Fantasia</label>
                                <input type="text" class="form-control" id="fiscal-nome-fantasia" placeholder="Nome comercial">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Inscrição Estadual</label>
                                <input type="text" class="form-control" id="fiscal-inscricao-estadual" placeholder="Inscrição estadual">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">ID do Serviço Municipal</label>
                                <input type="text" class="form-control" id="fiscal-municipal-service-id" placeholder="ID do serviço municipal">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Código do Serviço Municipal</label>
                                <input type="text" class="form-control" id="fiscal-municipal-service-code" placeholder="Código do serviço municipal">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn btn-info" onclick="salvarInformacoesFiscais()">
                                <i class="fas fa-save me-2"></i>
                                Salvar Informações Fiscais
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-info" onclick="carregarOpcoesMunicipais()">
                                <i class="fas fa-download me-2"></i>
                                Carregar Opções Municipais
                            </button>
                        </div>
                    </div>
                    
                    <!-- Gestão de Notas Fiscais -->
                    <hr class="my-4">
                    <h6 class="mb-3">
                        <i class="fas fa-receipt me-2"></i>
                        Gestão de Notas Fiscais
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <button class="btn btn-primary" onclick="abrirGestaoNotasFiscais()">
                                <i class="fas fa-list me-2"></i>
                                Ver Notas Fiscais
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-success" onclick="criarNotaFiscalPedido()">
                                <i class="fas fa-plus me-2"></i>
                                Criar Nota de Pedido
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary" onclick="abrirConfiguracaoAvancada()">
                                <i class="fas fa-cog me-2"></i>
                                Configuração Avançada
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <hr class="my-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Emissão de Nota Fiscal não disponível no seu plano.</strong>
                        <br>
                        Para habilitar a emissão de notas fiscais, faça upgrade do seu plano em 
                        <a href="index.php?view=gerenciar_faturas" class="alert-link">Gerenciar Faturas</a>.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Configurações de Sistema -->
                <div class="config-card">
                    <h5 class="mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Informações do Sistema
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Versão</label>
                                <input type="text" class="form-control" value="<?php echo $config->get('app.version'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ambiente</label>
                                <input type="text" class="form-control" value="<?php echo $config->get('app.env'); ?>" readonly>
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
        // Salvar configurações de aparência
        document.getElementById('configAparencia').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const corPrimaria = document.getElementById('corPrimaria').value;
            const nomeEstabelecimento = document.getElementById('nomeEstabelecimento').value;
            
            const formData = new FormData();
            formData.append('action', 'salvar_aparencia');
            formData.append('cor_primaria', corPrimaria);
            formData.append('nome_estabelecimento', nomeEstabelecimento);
            
            fetch('index.php?action=configuracoes', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao salvar configurações', 'error');
            });
        });

        // Salvar configurações de mesas
        document.getElementById('configMesas').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const numeroMesas = document.getElementById('numeroMesas').value;
            const capacidadeMesa = document.getElementById('capacidadeMesa').value;
            
            const formData = new FormData();
            formData.append('action', 'salvar_mesas');
            formData.append('numero_mesas', numeroMesas);
            formData.append('capacidade_mesa', capacidadeMesa);
            
            fetch('index.php?action=configuracoes', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao salvar configurações', 'error');
            });
        });

        function alterarSenha() {
            Swal.fire({
                title: 'Alterar Senha',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senhaAtual">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="novaSenha">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmarSenha">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Alterar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const senhaAtual = document.getElementById('senhaAtual').value;
                    const novaSenha = document.getElementById('novaSenha').value;
                    const confirmarSenha = document.getElementById('confirmarSenha').value;
                    
                    if (!senhaAtual || !novaSenha || !confirmarSenha) {
                        Swal.showValidationMessage('Todos os campos são obrigatórios');
                        return false;
                    }
                    
                    if (novaSenha !== confirmarSenha) {
                        Swal.showValidationMessage('As senhas não coincidem');
                        return false;
                    }
                    
                    return { senhaAtual, novaSenha };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Sucesso', 'Senha alterada com sucesso!', 'success');
                }
            });
        }

        function fazerBackup() {
            Swal.fire('Info', 'Funcionalidade de backup será implementada', 'info');
        }

        function restaurarBackup() {
            Swal.fire('Info', 'Funcionalidade de restauração será implementada', 'info');
        }

        // ===== USUÁRIOS FUNCTIONS =====
        
        // Carregar dados ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Carregando página configurações - versão 4.0 (WuzAPI)');
            carregarUsuarios();
            carregarCaixasEntrada();
        });

        function carregarUsuarios() {
            console.log('🔍 Loading users...');
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=listar_usuarios'
            })
                .then(response => {
                    console.log('📡 Response received:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('📊 Data received:', data);
                    const usuariosList = document.getElementById('usuariosList');
                    
                    if (data.success && data.usuarios.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm">';
                        html += '<thead><tr><th>Nome</th><th>Email</th><th>Tipo</th><th>Cargo</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
                        
                        data.usuarios.forEach(usuario => {
                            console.log('Processing user:', usuario);
                            
                            const isAdminPrincipal = usuario.tipo_usuario === 'admin' && usuario.id == 1;
                            const statusText = usuario.ativo ? 'Ativo' : 'Inativo';
                            const statusClass = usuario.ativo ? 'bg-success' : 'bg-danger';
                            const actionStatusText = usuario.ativo ? 'Desativar' : 'Ativar';
                            const actionStatusIcon = usuario.ativo ? 'fa-user-slash' : 'fa-user-check';
                            
                            // Ensure usuario.id is a valid number
                            const usuarioId = parseInt(usuario.id);
                            console.log('User ID parsed:', usuarioId, 'from:', usuario.id);
                            
                            if (isNaN(usuarioId) || usuarioId <= 0) {
                                console.error('Invalid usuario ID:', usuario.id, usuario);
                                return; // Skip this user
                            }
                            
                            html += `
                                <tr>
                                    <td>${usuario.nome}</td>
                                    <td>${usuario.email || '-'}</td>
                                    <td>${usuario.tipo_usuario}</td>
                                    <td>${usuario.cargo || '-'}</td>
                                    <td>
                                        <span class="badge ${statusClass}">
                                            ${statusText}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editarUsuario(${usuarioId})" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        ${!isAdminPrincipal ? `
                                            <button class="btn btn-outline-${usuario.ativo ? 'warning' : 'success'}" onclick="alterarStatusUsuario(${usuarioId}, '${usuario.ativo ? 'true' : 'false'}')" title="${actionStatusText}">
                                                <i class="fas ${actionStatusIcon}"></i>
                                            </button>
                                                <button class="btn btn-outline-danger" onclick="deletarUsuario(${usuarioId}, '${usuario.nome.replace(/'/g, "\\'")}')" title="Deletar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            ` : `
                                                <button class="btn btn-outline-secondary" disabled title="Usuário protegido">
                                                    <i class="fas fa-shield-alt"></i>
                                                </button>
                                            `}
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += '</tbody></table></div>';
                        usuariosList.innerHTML = html;
                    } else {
                        usuariosList.innerHTML = '<p class="text-muted">Nenhum usuário cadastrado.</p>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('usuariosList').innerHTML = '<p class="text-danger">Erro ao carregar usuários.</p>';
                });
        }


        function abrirModalNovoUsuario() {
            Swal.fire({
                title: 'Novo Usuário',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nomeUsuario" placeholder="Nome completo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="emailUsuario" placeholder="email@exemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefoneUsuario" placeholder="11999999999">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Usuário</label>
                        <select class="form-control" id="tipoUsuario">
                            <option value="admin">Administrador</option>
                            <option value="cozinha">Cozinha</option>
                            <option value="garcom">Garçom</option>
                            <option value="caixa">Caixa</option>
                            <option value="entregador">Entregador</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpfUsuario" placeholder="000.000.000-00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="cnpjUsuario" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endereço</label>
                        <textarea class="form-control" id="enderecoUsuario" placeholder="Endereço completo"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Usuário',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const nome = document.getElementById('nomeUsuario').value;
                    const email = document.getElementById('emailUsuario').value;
                    const telefone = document.getElementById('telefoneUsuario').value;
                    const tipoUsuario = document.getElementById('tipoUsuario').value;
                    const cpf = document.getElementById('cpfUsuario').value;
                    const cnpj = document.getElementById('cnpjUsuario').value;
                    const endereco = document.getElementById('enderecoUsuario').value;
                    
                    if (!nome) {
                        Swal.showValidationMessage('Nome é obrigatório');
                        return false;
                    }
                    
                    return { nome, email, telefone, tipo_usuario: tipoUsuario, cpf, cnpj, endereco };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    criarUsuario(result.value);
                }
            });
        }

        function criarUsuario(dados) {
            const formData = new FormData();
            formData.append('nome', dados.nome);
            formData.append('email', dados.email);
            formData.append('telefone', dados.telefone);
            formData.append('tipo_usuario', dados.tipo_usuario);
            formData.append('cpf', dados.cpf);
            formData.append('cnpj', dados.cnpj);
            formData.append('endereco', dados.endereco);

            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=criar_usuario&${new URLSearchParams(formData).toString()}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Usuário criado com sucesso!', 'success').then(() => {
                        carregarUsuarios(); // Recarregar lista de usuários
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao criar usuário', 'error');
            });
        }

        function abrirModalBuscarCliente() {
            Swal.fire({
                title: 'Buscar Cliente',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Termo de busca</label>
                        <input type="text" class="form-control" id="termoBusca" placeholder="Nome, telefone, CPF ou email">
                    </div>
                    <div id="resultadosBusca" class="mt-3" style="max-height: 300px; overflow-y: auto;">
                        <!-- Resultados serão carregados aqui -->
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Buscar',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    // Adicionar evento de busca em tempo real
                    document.getElementById('termoBusca').addEventListener('input', function() {
                        const termo = this.value.trim();
                        if (termo.length >= 2) {
                            buscarClientes(termo);
                        } else {
                            document.getElementById('resultadosBusca').innerHTML = '';
                        }
                    });
                }
            });
        }

        function buscarClientes(termo) {
            const formData = new FormData();
            formData.append('termo', termo);

            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_cliente&${new URLSearchParams(formData).toString()}`
            })
            .then(response => response.json())
            .then(data => {
                const resultadosDiv = document.getElementById('resultadosBusca');
                
                if (data.success && data.clientes.length > 0) {
                    let html = '<h6>Resultados:</h6>';
                    data.clientes.forEach(cliente => {
                        html += `
                            <div class="card mb-2 p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${cliente.nome}</strong><br>
                                        <small class="text-muted">
                                            ${cliente.telefone ? 'Tel: ' + cliente.telefone : ''}
                                            ${cliente.cpf ? ' | CPF: ' + cliente.cpf : ''}
                                            ${cliente.email ? ' | Email: ' + cliente.email : ''}
                                        </small>
                                    </div>
                                    <button class="btn btn-sm btn-primary" onclick="selecionarCliente(${cliente.id})">
                                        Selecionar
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    resultadosDiv.innerHTML = html;
                } else {
                    resultadosDiv.innerHTML = '<p class="text-muted">Nenhum cliente encontrado.</p>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('resultadosBusca').innerHTML = '<p class="text-danger">Erro ao buscar clientes.</p>';
            });
        }

        function selecionarCliente(clienteId) {
            // Implementar ação quando cliente é selecionado
            Swal.fire('Sucesso', 'Cliente selecionado!', 'success');
        }

        function editarUsuario(usuarioId) {
            console.log('✏️ Edit user clicked:', usuarioId);
            
            // Validate usuarioId
            if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
                console.error('❌ Invalid user ID:', usuarioId);
                Swal.fire('Erro', 'ID do usuário inválido', 'error');
                return;
            }
            
            // Buscar dados do usuário
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_usuario&usuario_id=${usuarioId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const usuario = data.usuario;
                    
                    Swal.fire({
                        title: 'Editar Usuário',
                        html: `
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="editNomeUsuario" value="${usuario.nome}" placeholder="Nome completo">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmailUsuario" value="${usuario.email || ''}" placeholder="email@exemplo.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="editTelefoneUsuario" value="${usuario.telefone || ''}" placeholder="11999999999">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo de Usuário</label>
                                <select class="form-control" id="editTipoUsuario">
                                    <option value="admin" ${usuario.tipo_usuario === 'admin' ? 'selected' : ''}>Administrador</option>
                                    <option value="cozinha" ${usuario.tipo_usuario === 'cozinha' ? 'selected' : ''}>Cozinha</option>
                                    <option value="garcom" ${usuario.tipo_usuario === 'garcom' ? 'selected' : ''}>Garçom</option>
                                    <option value="caixa" ${usuario.tipo_usuario === 'caixa' ? 'selected' : ''}>Caixa</option>
                                    <option value="entregador" ${usuario.tipo_usuario === 'entregador' ? 'selected' : ''}>Entregador</option>
                                    <option value="cliente" ${usuario.tipo_usuario === 'cliente' ? 'selected' : ''}>Cliente</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" id="editCpfUsuario" value="${usuario.cpf || ''}" placeholder="000.000.000-00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="editCnpjUsuario" value="${usuario.cnpj || ''}" placeholder="00.000.000/0000-00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Endereço</label>
                                <textarea class="form-control" id="editEnderecoUsuario" placeholder="Endereço completo">${usuario.endereco_completo || ''}</textarea>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Salvar Alterações',
                        cancelButtonText: 'Cancelar',
                        preConfirm: () => {
                            const nome = document.getElementById('editNomeUsuario').value;
                            const email = document.getElementById('editEmailUsuario').value;
                            const telefone = document.getElementById('editTelefoneUsuario').value;
                            const tipoUsuario = document.getElementById('editTipoUsuario').value;
                            const cpf = document.getElementById('editCpfUsuario').value;
                            const cnpj = document.getElementById('editCnpjUsuario').value;
                            const endereco = document.getElementById('editEnderecoUsuario').value;
                            
                            if (!nome) {
                                Swal.showValidationMessage('Nome é obrigatório');
                                return false;
                            }
                            
                            return { 
                                usuario_id: usuarioId,
                                nome, email, telefone, tipo_usuario: tipoUsuario, cpf, cnpj, endereco 
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            salvarEdicaoUsuario(result.value);
                        }
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
            });
        }

        function salvarEdicaoUsuario(dados) {
            const formData = new FormData();
            formData.append('usuario_id', dados.usuario_id);
            formData.append('nome', dados.nome);
            formData.append('email', dados.email);
            formData.append('telefone', dados.telefone);
            formData.append('tipo_usuario', dados.tipo_usuario);
            formData.append('cpf', dados.cpf);
            formData.append('cnpj', dados.cnpj);
            formData.append('endereco', dados.endereco);

            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=editar_usuario&${new URLSearchParams(formData).toString()}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Usuário atualizado com sucesso!', 'success').then(() => {
                        carregarUsuarios(); // Recarregar lista de usuários
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao atualizar usuário', 'error');
            });
        }

        function alterarStatusUsuario(usuarioId, statusAtual) {
            console.log('🔄 Change status clicked:', usuarioId, 'Current status:', statusAtual, 'Type:', typeof statusAtual);
            
            // Convert string to boolean if needed
            let statusBoolean;
            if (typeof statusAtual === 'string') {
                statusBoolean = statusAtual === 'true';
            } else {
                statusBoolean = Boolean(statusAtual);
            }
            
            const novoStatus = !statusBoolean;
            const acao = novoStatus ? 'ativar' : 'desativar';
            const confirmacao = novoStatus ? 'ativar' : 'desativar';
            
            // Validate usuarioId
            if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
                console.error('❌ Invalid user ID:', usuarioId);
                Swal.fire('Erro', 'ID do usuário inválido', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Confirmar Ação',
                text: `Deseja ${acao} este usuário?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Sim, ${acao}`,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: novoStatus ? '#28a745' : '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('usuario_id', usuarioId);
                    formData.append('novo_status', novoStatus ? 'true' : 'false');

                    fetch('mvc/ajax/configuracoes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=alterar_status_usuario&${new URLSearchParams(formData).toString()}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success').then(() => {
                                carregarUsuarios(); // Recarregar lista de usuários
                            });
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao alterar status do usuário', 'error');
                    });
                }
            });
        }

        function deletarUsuario(usuarioId, nomeUsuario) {
            console.log('🗑️ Delete user clicked:', usuarioId, 'Name:', nomeUsuario);
            
            // Validate usuarioId
            if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
                console.error('❌ Invalid user ID:', usuarioId);
                Swal.fire('Erro', 'ID do usuário inválido', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Confirmar Exclusão',
                html: `Deseja realmente deletar o usuário <strong>${nomeUsuario}</strong>?<br><br>
                       <small class="text-danger">Esta ação não pode ser desfeita!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('usuario_id', usuarioId);

                    fetch('mvc/ajax/configuracoes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=deletar_usuario&${new URLSearchParams(formData).toString()}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success').then(() => {
                                carregarUsuarios(); // Recarregar lista de usuários
                            });
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao deletar usuário', 'error');
                    });
                }
            });
        }


        function abrirModalBuscarClienteOld() {
            Swal.fire({
                title: 'Buscar Cliente',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Telefone do Cliente</label>
                        <input type="text" class="form-control" id="telefoneCliente" placeholder="11999999999">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Buscar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const telefone = document.getElementById('telefoneCliente').value;
                    
                    if (!telefone) {
                        Swal.showValidationMessage('Telefone é obrigatório');
                        return false;
                    }
                    
                    return { telefone };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    buscarCliente(result.value.telefone);
                }
            });
        }

        function buscarCliente(telefone) {
            fetch(`mvc/ajax/auth.php?action=buscar_cliente&telefone=${telefone}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Cliente Encontrado',
                            html: `
                                <div class="text-start">
                                    <p><strong>Nome:</strong> ${data.cliente.nome || 'Não informado'}</p>
                                    <p><strong>Telefone:</strong> ${data.cliente.telefone}</p>
                                    <p><strong>Email:</strong> ${data.cliente.email || 'Não informado'}</p>
                                    <p><strong>CPF:</strong> ${data.cliente.cpf || 'Não informado'}</p>
                                    <p><strong>Endereços:</strong> ${data.cliente.enderecos || 'Nenhum'}</p>
                                </div>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Fechar'
                        });
                    } else {
                        Swal.fire('Cliente não encontrado', 'Este telefone não está cadastrado no sistema', 'info');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    Swal.fire('Erro', 'Erro ao buscar cliente', 'error');
                });
        }

        // ===== WUZAPI FUNCTIONS =====

        function abrirModalNovaCaixaEntrada() {
            Swal.fire({
                title: 'Nova Instância WhatsApp',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Nome da Instância</label>
                        <input type="text" class="form-control" id="nomeCaixaEntrada" placeholder="ex: atendimento_loja1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número do WhatsApp</label>
                        <input type="text" class="form-control" id="numeroWhatsApp" placeholder="5511999999999">
                        <small class="form-text text-muted">Inclua o código do país (ex: 5511999999999)</small>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Instância',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const nome = document.getElementById('nomeCaixaEntrada').value;
                    const numero = document.getElementById('numeroWhatsApp').value;
                    
                    if (!nome || !numero) {
                        Swal.showValidationMessage('Nome e número são obrigatórios');
                        return false;
                    }
                    
                    return { instance_name: nome, phone_number: numero };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    criarCaixaEntrada(result.value.instance_name, result.value.phone_number);
                }
            });
        }

        function carregarCaixasEntrada() {
            console.log('Carregando instâncias...');
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=listar_caixas_entrada'
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        console.log('Data.instances:', data.instances);
                        const instancesToShow = data.instances || [];
                        console.log('Instâncias para exibir:', instancesToShow);
                        console.log('Quantidade:', instancesToShow.length);
                        exibirCaixasEntrada(instancesToShow);
                    } else {
                        console.error('Erro ao carregar instâncias:', data.error || data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                });
        }

        function exibirCaixasEntrada(instances) {
            console.log('Exibindo instâncias:', instances);
            const container = document.getElementById('caixasEntradaList');
            
            if (!instances || !Array.isArray(instances) || instances.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhuma instância configurada</p>';
                return;
            }

            let html = '';
            instances.forEach(instance => {
                console.log('Processando instância:', instance);
                const statusClass = instance.status === 'connected' ? 'success' : 'danger';
                const statusText = instance.status === 'connected' ? 'Conectado' : 'Desconectado';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h6 class="mb-1">${instance.instance_name}</h6>
                                    <small class="text-muted">${instance.phone_number}</small>
                                </div>
                                <div class="col-md-3">
                                    <span class="badge bg-${statusClass}">${statusText}</span>
                                </div>
                                <div class="col-md-5 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="conectarCaixaEntrada('${instance.instance_name}', ${instance.id})">
                                        <i class="fas fa-qrcode"></i> Conectar
                                    </button>
                                    <button class="btn btn-sm btn-outline-info me-1" onclick="sincronizarStatus('${instance.instance_name}', ${instance.id})">
                                        <i class="fas fa-sync"></i> Sync
                                    </button>
                                    <button class="btn btn-sm btn-outline-success me-1" onclick="enviarMensagem('${instance.instance_name}', ${instance.id})">
                                        <i class="fas fa-paper-plane"></i> Enviar
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="verStatusInstancia('${instance.instance_name}', ${instance.id})">
                                        <i class="fas fa-info-circle"></i> Status
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletarCaixaEntrada('${instance.instance_name}', ${instance.id})">
                                        <i class="fas fa-trash"></i> Deletar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function criarCaixaEntrada(instanceName, phoneNumber) {
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=criar_caixa_entrada&instance_name=${encodeURIComponent(instanceName)}&phone_number=${encodeURIComponent(phoneNumber)}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        Swal.fire('Sucesso', 'Instância criada com sucesso!', 'success');
                        carregarCaixasEntrada();
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    console.error('Texto recebido:', text);
                    Swal.fire('Erro', 'Resposta inválida do servidor. Verifique o console.', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao criar instância', 'error');
            });
        }

        function conectarCaixaEntrada(nomeCaixa, instanceId) {
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=conectar_caixa_entrada&instance_id=${instanceId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.qr_code) {
                        // QR code disponível - exibir para conectar
                        Swal.fire({
                            title: 'Conectar WhatsApp',
                            html: `
                                <div class="text-center">
                                    <p class="mb-3"><strong>Escaneie o QR code com seu WhatsApp para conectar:</strong></p>
                                    <img src="${data.qr_code}" alt="QR Code" style="max-width: 300px; height: auto; border: 2px solid #25d366; border-radius: 8px; padding: 10px;">
                                    <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; text-align: left;">
                                        <p style="margin: 5px 0; font-size: 14px;"><strong>📱 Instruções:</strong></p>
                                        <p style="margin: 3px 0; font-size: 12px;">1. Abra o WhatsApp no seu celular</p>
                                        <p style="margin: 3px 0; font-size: 12px;">2. Toque em "Dispositivos conectados" (⋮)</p>
                                        <p style="margin: 3px 0; font-size: 12px;">3. Toque em "Conectar um dispositivo"</p>
                                        <p style="margin: 3px 0; font-size: 12px;">4. Escaneie este código</p>
                                    </div>
                                    <p class="text-muted mt-3">💡 Certifique-se que o WhatsApp está atualizado</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Atualizar QR',
                            cancelButtonText: 'Fechar',
                            confirmButtonColor: '#25d366',
                            cancelButtonColor: '#6c757d',
                            width: 'auto'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Atualizar QR code
                                conectarCaixaEntrada(nomeCaixa, instanceId);
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                // Recarregar a lista para atualizar status
                                carregarCaixasEntrada();
                            }
                        });
                    } else if (data.status === 'connected') {
                        // Já conectado
                        Swal.fire({
                            title: 'WhatsApp Conectado!',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
                                    <p class="mb-3">Seu WhatsApp já está conectado e pronto para uso!</p>
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Status:</strong> Conectado<br>
                                        <strong>Pronto para:</strong> Enviar e receber mensagens
                                    </div>
                                </div>
                            `,
                            confirmButtonText: 'Ótimo!',
                            confirmButtonColor: '#25d366',
                            width: 400
                        });
                    } else {
                        // Sem QR code e sem URL - mostrar mensagem informativa
                        const retryAfter = data.retry_after || 5;
                        Swal.fire({
                            title: 'Gerando QR Code...',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin text-info mb-3" style="font-size: 4rem;"></i>
                                    <p class="mb-3">${data.message || 'Aguarde o QR code ser gerado automaticamente'}</p>
                                    <p class="text-muted">Status: ${data.status || 'desconhecido'}</p>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-clock"></i>
                                        <strong>Dica:</strong> O QR code é gerado automaticamente pela WuzAPI. 
                                        Tente novamente em alguns segundos.
                                    </div>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Tentar Novamente',
                            cancelButtonText: 'Fechar',
                            width: 500,
                            timer: retryAfter * 1000,
                            timerProgressBar: true
                        }).then((result) => {
                            if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                                // Tentar novamente
                                conectarCaixaEntrada(nomeCaixa, instanceId);
                            }
                        });
                    }
                } else {
                    Swal.fire('Erro', data.message || 'Erro ao conectar caixa de entrada', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao conectar caixa de entrada', 'error');
            });
        }

        function verStatusInstancia(instanceName, instanceId) {
            Swal.fire({
                title: `Status da Instância: ${instanceName}`,
                html: `
                    <div class="text-center">
                        <i class="fas fa-info-circle text-info mb-3" style="font-size: 3rem;"></i>
                        <p class="mb-3">Verificando status da instância...</p>
                        <div class="alert alert-info">
                            <strong>ID:</strong> ${instanceId}<br>
                            <strong>Nome:</strong> ${instanceName}<br>
                            <strong>Status:</strong> Verificando...
                        </div>
                    </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#25d366'
            });
        }

        function deletarCaixaEntrada(nomeCaixa, instanceId) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: `Tem certeza que deseja deletar a caixa de entrada "${nomeCaixa}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar!',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/configuracoes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=deletar_caixa_entrada&instance_id=${instanceId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Caixa de entrada deletada com sucesso!', 'success');
                            carregarCaixasEntrada();
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao deletar caixa de entrada', 'error');
                    });
                }
            });
        }
        
        function sincronizarStatus(instanceName, instanceId) {
            Swal.fire({
                title: 'Sincronizando Status',
                text: `Sincronizando status da instância "${instanceName}"...`,
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=sincronizar_status&instance_id=${instanceId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Status Sincronizado!',
                        text: `Status atualizado para: ${data.status}`,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        carregarCaixasEntrada();
                    });
                } else {
                    Swal.fire('Erro', data.message || 'Erro ao sincronizar status', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao sincronizar status', 'error');
            });
        }
        
        function enviarMensagem(instanceName, instanceId) {
            Swal.fire({
                title: `Enviar Mensagem - ${instanceName}`,
                html: `
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Número do WhatsApp</label>
                        <input type="text" id="phone_number" class="form-control" placeholder="Ex: 5554999999999" value="5554997092223">
                        <small class="form-text text-muted">Digite o número com código do país (ex: 5554999999999)</small>
                    </div>
                    <div class="mb-3">
                        <label for="message_text" class="form-label">Mensagem</label>
                        <textarea id="message_text" class="form-control" rows="4" placeholder="Digite sua mensagem aqui...">Olá! Esta é uma mensagem de teste do sistema Divino Lanches via WuzAPI.</textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#25d366',
                preConfirm: () => {
                    const phoneNumber = document.getElementById('phone_number').value;
                    const message = document.getElementById('message_text').value;
                    
                    if (!phoneNumber || !message) {
                        Swal.showValidationMessage('Preencha todos os campos');
                        return false;
                    }
                    
                    return { phoneNumber, message };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar mensagem
                    Swal.fire({
                        title: 'Enviando Mensagem',
                        text: 'Enviando mensagem via WhatsApp...',
                        icon: 'info',
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });
                    
                    fetch('mvc/ajax/configuracoes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=enviar_mensagem&instance_id=${instanceId}&phone_number=${encodeURIComponent(result.value.phoneNumber)}&message=${encodeURIComponent(result.value.message)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Mensagem Enviada!',
                                text: 'Sua mensagem foi enviada com sucesso via WhatsApp.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao enviar mensagem', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao enviar mensagem', 'error');
                    });
                }
            });
        }

        // ===== FUNÇÕES PARA GERENCIAR FILIAIS =====
        
        // Carregar filiais ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            carregarFiliais();
        });

        function carregarFiliais() {
            fetch('mvc/ajax/filiais.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=listar_filiais'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    preencherFiliais(data.filiais);
                } else {
                    document.getElementById('filiaisList').innerHTML = '<p class="text-danger">Erro ao carregar filiais.</p>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('filiaisList').innerHTML = '<p class="text-danger">Erro ao carregar filiais.</p>';
            });
        }

        function preencherFiliais(filiais) {
            const container = document.getElementById('filiaisList');
            
            if (filiais.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhuma filial cadastrada.</p>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-sm">';
            html += '<thead><tr><th>Estabelecimento</th><th>Login Admin</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
            
            filiais.forEach(filial => {
                const statusText = filial.status === 'ativo' ? 'Ativo' : 'Inativo';
                const statusClass = filial.status === 'ativo' ? 'bg-success' : 'bg-danger';
                
                html += `
                    <tr>
                        <td><strong>${filial.nome}</strong><br><small class="text-muted">${filial.endereco || 'Endereço não informado'}</small></td>
                        <td><code>${filial.login || 'N/A'}</code></td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="verCredenciaisFilial('${filial.login || ''}')" title="Ver Credenciais">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="excluirFilial(${filial.id}, '${filial.nome.replace(/'/g, "\\'")}')" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function abrirModalNovaFilial() {
            Swal.fire({
                title: 'Nova Filial',
                html: `
                    <div class="mb-3">
                        <label class="form-label">Nome da Filial *</label>
                        <input type="text" class="form-control" id="nomeFilial" placeholder="Ex: Filial Centro">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Login do Usuário *</label>
                        <input type="text" class="form-control" id="loginFilial" placeholder="Ex: filial_centro">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha *</label>
                        <input type="password" class="form-control" id="senhaFilial" placeholder="Senha forte">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" class="form-control" id="nomeCompletoFilial" placeholder="Nome do responsável">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endereço da Filial</label>
                        <textarea class="form-control" id="enderecoFilial" rows="2" placeholder="Endereço completo da filial"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Filial',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#007bff',
                preConfirm: () => {
                    const nomeFilial = document.getElementById('nomeFilial').value;
                    const login = document.getElementById('loginFilial').value;
                    const senha = document.getElementById('senhaFilial').value;
                    const nome = document.getElementById('nomeCompletoFilial').value;
                    const endereco = document.getElementById('enderecoFilial').value;
                    
                    if (!nomeFilial || !login || !senha || !nome) {
                        Swal.showValidationMessage('Preencha todos os campos obrigatórios');
                        return false;
                    }
                    
                    return { nomeFilial, login, senha, nome, endereco };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    criarFilial(result.value);
                }
            });
        }

        function criarFilial(dados) {
            Swal.fire({
                title: 'Criando Filial',
                text: 'Criando usuário para a filial...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            fetch('mvc/ajax/filiais.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=criar_filial&nome_filial=${encodeURIComponent(dados.nomeFilial)}&login=${encodeURIComponent(dados.login)}&senha=${encodeURIComponent(dados.senha)}&nome=${encodeURIComponent(dados.nome)}&endereco=${encodeURIComponent(dados.endereco)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Filial Criada!',
                        html: `
                            <div class="text-start">
                                <p><strong>Estabelecimento:</strong> ${dados.nomeFilial}</p>
                                <p><strong>Login:</strong> <code>${dados.login}</code></p>
                                <p><strong>Senha:</strong> <code>${dados.senha}</code></p>
                                <hr>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Como funciona:</strong><br>
                                    • A filial é um <strong>estabelecimento independente</strong><br>
                                    • Terá seu próprio dashboard <strong>zerado</strong><br>
                                    • Pode configurar cardápio, produtos, etc.<br>
                                    • Acessa o sistema normalmente com estas credenciais
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        carregarFiliais(); // Recarregar lista
                    });
                } else {
                    Swal.fire('Erro', data.message || 'Erro ao criar filial', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao criar filial', 'error');
            });
        }

        function verCredenciaisFilial(login) {
            Swal.fire({
                title: 'Credenciais de Acesso',
                html: `
                    <div class="text-start">
                        <p><strong>URL de Acesso:</strong></p>
                        <code>http://localhost:8080/index.php?view=login_admin</code>
                        <hr>
                        <p><strong>Login:</strong> <code>${login}</code></p>
                        <p><strong>Senha:</strong> <code>123456</code> (padrão)</p>
                        <hr>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Como usar:</strong><br>
                            1. O usuário da filial faz logout do sistema atual<br>
                            2. Acessa a URL acima<br>
                            3. Usa o login e senha para fazer login<br>
                            4. Terá acesso ao sistema da filial (zerado)
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Entendi'
            });
        }

        function excluirFilial(filialId, nome) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                html: `Deseja realmente excluir a filial <strong>${nome}</strong>?<br><br>
                       <small class="text-danger">Esta ação não pode ser desfeita!</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/filiais.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=excluir_filial&filial_id=${filialId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Filial excluída com sucesso!', 'success').then(() => {
                                carregarFiliais(); // Recarregar lista
                            });
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao excluir filial', 'error');
                    });
                }
            });
        }

        // ============================================
        // FUNÇÕES ASAAS
        // ============================================

        // Carregar configuração atual do Asaas
        function carregarConfiguracaoAsaas() {
            const tenantId = <?php echo $tenant['id']; ?>;
            const filialId = <?php echo $filial['id'] ?? 'null'; ?>;
            
            fetch(`mvc/ajax/asaas_config.php?action=getConfig&tenant_id=${tenantId}&filial_id=${filialId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const config = data.data;
                    // Never show API key for security
                    document.getElementById('asaas-customer-id').value = config.asaas_customer_id || '';
                    document.getElementById('asaas-environment').value = config.asaas_environment || 'sandbox';
                    document.getElementById('asaas-enabled').checked = config.asaas_enabled || false;
                    
                    // Update status badge
                    const statusBadge = document.getElementById('asaas-status-badge');
                    if (config.asaas_enabled) {
                        statusBadge.className = 'badge bg-success me-2';
                        statusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Configurado';
                    } else {
                        statusBadge.className = 'badge bg-warning me-2';
                        statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Não Configurado';
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar configuração Asaas:', error);
            });
        }

        // Salvar configuração do Asaas
        function salvarConfiguracaoAsaas() {
            const data = {
                tenant_id: <?php echo $tenant['id']; ?>,
                filial_id: <?php echo $filial['id'] ?? 'null'; ?>,
                asaas_api_key: document.getElementById('asaas-api-key').value,
                asaas_customer_id: document.getElementById('asaas-customer-id').value,
                asaas_environment: document.getElementById('asaas-environment').value,
                asaas_enabled: document.getElementById('asaas-enabled').checked
            };

            fetch('mvc/ajax/asaas_config.php?action=saveConfig', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Configuração do Asaas salva com sucesso!', 'success');
                    carregarConfiguracaoAsaas(); // Recarregar configuração
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao salvar configuração', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao salvar configuração', 'error');
            });
        }

        // Testar conexão com Asaas
        function testarConexaoAsaas() {
            Swal.fire({
                title: 'Testando Conexão...',
                text: 'Aguarde enquanto testamos a conexão com o Asaas',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('mvc/ajax/asaas_config.php?action=testConnection', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Conexão Bem-sucedida!',
                        text: 'Sua integração com o Asaas está funcionando corretamente',
                        icon: 'success',
                        confirmButtonText: 'Ótimo!'
                    });
                } else {
                    Swal.fire({
                        title: 'Erro na Conexão',
                        text: data.error || 'Não foi possível conectar com o Asaas',
                        icon: 'error',
                        confirmButtonText: 'Entendi'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao testar conexão', 'error');
            });
        }

        // Atualizar configuração automaticamente
        function atualizarConfiguracaoAsaas() {
            // Esta função pode ser chamada quando campos são alterados
            // Por enquanto, apenas atualiza o status visual
            const apiKey = document.getElementById('asaas-api-key').value;
            const enabled = document.getElementById('asaas-enabled').checked;
            
            const statusBadge = document.getElementById('asaas-status-badge');
            if (enabled && apiKey) {
                statusBadge.className = 'badge bg-success me-2';
                statusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Configurado';
            } else {
                statusBadge.className = 'badge bg-warning me-2';
                statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Não Configurado';
            }
        }

        // Load fiscal information
        function carregarInformacoesFiscais() {
            const tenantId = <?php echo $tenant['id']; ?>;
            const filialId = <?php echo $filial['id'] ?? 'null'; ?>;
            
            fetch(`mvc/ajax/fiscal_info.php?action=getFiscalInfo&tenant_id=${tenantId}&filial_id=${filialId}&source=db`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const info = data.data;
                    if (document.getElementById('fiscal-cnpj')) {
                        document.getElementById('fiscal-cnpj').value = info.cnpj || '';
                        document.getElementById('fiscal-razao-social').value = info.razao_social || '';
                        document.getElementById('fiscal-nome-fantasia').value = info.nome_fantasia || '';
                        document.getElementById('fiscal-inscricao-estadual').value = info.inscricao_estadual || '';
                        document.getElementById('fiscal-municipal-service-id').value = info.municipal_service_id || '';
                        document.getElementById('fiscal-municipal-service-code').value = info.municipal_service_code || '';
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar informações fiscais:', error);
            });
        }

        // Salvar informações fiscais
        function salvarInformacoesFiscais() {
            const cnpj = document.getElementById('fiscal-cnpj').value;
            const razaoSocial = document.getElementById('fiscal-razao-social').value;
            const municipalServiceId = document.getElementById('fiscal-municipal-service-id').value;
            const municipalServiceCode = document.getElementById('fiscal-municipal-service-code').value;

            if (!cnpj || !razaoSocial || !municipalServiceId || !municipalServiceCode) {
                Swal.fire('Atenção', 'Preencha todos os campos obrigatórios (CNPJ, Razão Social, ID e Código do Serviço Municipal)', 'warning');
                return;
            }

            const data = {
                tenant_id: <?php echo $tenant['id']; ?>,
                filial_id: <?php echo $filial['id'] ?? 'null'; ?>,
                cnpj: cnpj,
                razao_social: razaoSocial,
                nome_fantasia: document.getElementById('fiscal-nome-fantasia').value,
                inscricao_estadual: document.getElementById('fiscal-inscricao-estadual').value,
                municipal_service_id: municipalServiceId,
                municipal_service_code: municipalServiceCode,
                endereco: {
                    logradouro: '',
                    numero: '',
                    complemento: '',
                    bairro: '',
                    cidade: '',
                    uf: '',
                    cep: ''
                }
            };

            Swal.fire({
                title: 'Salvando...',
                text: 'Aguarde enquanto salvamos suas informações fiscais',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('mvc/ajax/fiscal_info.php?action=createOrUpdateFiscalInfo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Informações fiscais salvas com sucesso!', 'success');
                    carregarInformacoesFiscais(); // Reload data
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao salvar informações fiscais', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao salvar informações fiscais', 'error');
            });
        }

        // Carregar opções municipais
        function carregarOpcoesMunicipais() {
            Swal.fire({
                title: 'Carregando Opções Municipais...',
                text: 'Aguarde enquanto carregamos as opções disponíveis',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('mvc/ajax/fiscal_info.php?action=listMunicipalOptions', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Opções Municipais Carregadas',
                        text: 'As opções municipais foram carregadas com sucesso. Verifique os campos de serviço municipal.',
                        icon: 'success',
                        confirmButtonText: 'Ótimo!'
                    });
                } else {
                    Swal.fire({
                        title: 'Erro',
                        text: data.error || 'Não foi possível carregar as opções municipais',
                        icon: 'error',
                        confirmButtonText: 'Entendi'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao carregar opções municipais', 'error');
            });
        }

        // Abrir gestão de notas fiscais
        function abrirGestaoNotasFiscais() {
            window.open('index.php?view=asaas_config', '_blank');
        }

        // Criar nota fiscal de pedido
        function criarNotaFiscalPedido() {
            Swal.fire({
                title: 'Criar Nota Fiscal',
                text: 'Esta funcionalidade abrirá uma lista de pedidos para seleção',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aqui você pode implementar a lógica para selecionar pedidos
                    Swal.fire('Info', 'Funcionalidade será implementada em breve', 'info');
                }
            });
        }

        // Abrir configuração avançada
        function abrirConfiguracaoAvancada() {
            window.open('index.php?view=asaas_config', '_blank');
        }

        // Toggle password visibility
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Formatação de CNPJ
        function formatarCNPJ(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                input.value = value;
            }
        }

        // Adicionar event listeners quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar configuração do Asaas
            carregarConfiguracaoAsaas();
            
            // Carregar informações fiscais
            carregarInformacoesFiscais();
            
            // Adicionar formatação de CNPJ
            const cnpjInput = document.getElementById('fiscal-cnpj');
            if (cnpjInput) {
                cnpjInput.addEventListener('input', function() {
                    formatarCNPJ(this);
                });
            }
        });

    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

