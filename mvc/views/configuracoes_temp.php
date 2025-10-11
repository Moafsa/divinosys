<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

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
                    <p class="text-muted mb-3">Gerencie usuários internos e clientes do sistema</p>
                    
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
                        <button class="btn btn-outline-info" onclick="abrirModalBuscarCliente()">
                            <i class="fas fa-search me-2"></i>
                            Buscar Cliente
                        </button>
                    </div>
                </div>

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

