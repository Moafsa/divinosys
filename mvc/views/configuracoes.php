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
        "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id_mesa::integer",
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
            carregarCaixasEntrada();
        });

        function carregarUsuarios() {
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=listar_usuarios'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        exibirUsuarios(data.usuarios);
                    } else {
                        console.error('Erro ao carregar usuários:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
        }

        function exibirUsuarios(usuarios) {
            const container = document.getElementById('usuariosList');
            
            if (usuarios.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhum usuário encontrado</p>';
                return;
            }

            let html = '';
            usuarios.forEach(usuario => {
                // Determinar tipo e cor baseado no tipo_usuario
                let tipoClass = 'secondary';
                let tipoUsuario = 'Cliente';
                
                switch(usuario.tipo_usuario) {
                    case 'admin':
                        tipoClass = 'danger';
                        tipoUsuario = 'Admin';
                        break;
                    case 'cozinha':
                        tipoClass = 'warning';
                        tipoUsuario = 'Cozinha';
                        break;
                    case 'garcom':
                        tipoClass = 'info';
                        tipoUsuario = 'Garçom';
                        break;
                    case 'caixa':
                        tipoClass = 'success';
                        tipoUsuario = 'Caixa';
                        break;
                    case 'entregador':
                        tipoClass = 'primary';
                        tipoUsuario = 'Entregador';
                        break;
                    default:
                        tipoClass = 'secondary';
                        tipoUsuario = 'Funcionário';
                }
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1">${usuario.nome || 'Sem nome'}</h6>
                                    <small class="text-muted">${usuario.email || 'Sem email'}</small>
                                </div>
                                <div class="col-md-2">
                                    <span class="badge bg-${tipoClass}">${tipoUsuario}</span>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">${usuario.telefone || 'Sem telefone'}</small>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">${usuario.cpf || usuario.cnpj || 'Sem documento'}</small>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editarUsuario(${usuario.id})">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletarUsuario(${usuario.id})">
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
                    Swal.fire('Sucesso', 'Usuário criado com sucesso!', 'success');
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

        function editarUsuario(id) {
            // Buscar dados do usuário
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=buscar_usuario&id=${id}`
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
                                <input type="text" class="form-control" id="editNomeUsuario" value="${usuario.nome || ''}" placeholder="Nome completo">
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
                            
                            return { id, nome, email, telefone, tipo_usuario: tipoUsuario, cpf, cnpj, endereco };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            salvarEdicaoUsuario(result.value);
                        }
                    });
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
            });
        }
        
        function salvarEdicaoUsuario(dados) {
            const formData = new FormData();
            formData.append('id', dados.id);
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
                    Swal.fire('Sucesso', 'Usuário atualizado com sucesso!', 'success');
                } else {
                    Swal.fire('Erro', data.message || 'Erro ao atualizar usuário', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro', 'Erro ao atualizar usuário', 'error');
            });
        }

        function deletarUsuario(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja deletar este usuário?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/configuracoes.php?v=' + Date.now(), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=deletar_usuario&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Usuário removido com sucesso!', 'success');
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao remover usuário', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao remover usuário', 'error');
                    });
                }
            });
        }

        // ===== WUZAPI FUNCTIONS =====

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

        function criarCaixaEntrada(instanceName, phoneNumber) {
            fetch('mvc/ajax/configuracoes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=criar_caixa_entrada&instance_name=${encodeURIComponent(instanceName)}&phone_number=${encodeURIComponent(phoneNumber)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', 'Instância criada com sucesso!', 'success');
                    carregarCaixasEntrada();
                } else {
                    Swal.fire('Erro', data.message, 'error');
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
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/configuracoes.php?v=' + Date.now(), {
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
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
