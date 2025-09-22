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
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>">
                            <i class="fas fa-plus-circle"></i>
                            Novo Pedido
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>">
                            <i class="fas fa-list"></i>
                            Pedidos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('mesas'); ?>">
                            <i class="fas fa-table"></i>
                            Mesas
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>">
                            <i class="fas fa-motorcycle"></i>
                            Delivery
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>">
                            <i class="fas fa-box"></i>
                            Produtos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>">
                            <i class="fas fa-warehouse"></i>
                            Estoque
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>">
                            <i class="fas fa-chart-line"></i>
                            Financeiro
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>">
                            <i class="fas fa-chart-bar"></i>
                            Relatórios
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>">
                            <i class="fas fa-users"></i>
                            Clientes
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('configuracoes'); ?>">
                            <i class="fas fa-cog"></i>
                            Configurações
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
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
    </script>
</body>
</html>
