<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$db = \System\Database::getInstance();
$router = \System\Router::getInstance();

$tenant = $session->getTenant();
$filial = $session->getFilial();

if (!$tenant || !$filial) {
    header('Location: index.php?view=login');
    exit;
}

// Buscar admins atuais
$admins = $db->fetchAll(
    "SELECT * FROM whatsapp_admins WHERE tenant_id = ? AND filial_id = ?",
    [$tenant['id'], $filial['id']]
);

// Buscar automações
$automations = $db->fetchAll(
    "SELECT * FROM ai_automations WHERE tenant_id = ? AND filial_id = ?",
    [$tenant['id'], $filial['id']]
);

// Tratar automações e formatar defaults se não existirem
$auto_abandono = array_filter($automations, fn($a) => $a['tipo'] === 'abandono');
$auto_abandono = $auto_abandono ? reset($auto_abandono) : ['ativo' => false, 'tempo_espera' => 30, 'mensagem_template' => "Oi {nome}! Notei que você não finalizou o pedido. Precisa de alguma ajuda?"];

$auto_saudade = array_filter($automations, fn($a) => $a['tipo'] === 'saudade');
$auto_saudade = $auto_saudade ? reset($auto_saudade) : ['ativo' => false, 'tempo_espera' => 15, 'mensagem_template' => "Oi {nome}, sumiu! Que tal um lanche hoje?"];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automações de IA - <?php echo $config->get('app.name'); ?></title>
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
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
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
<body class="bg-light">
    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/components/sidebar.php'; ?>
        
        <!-- Mobile Menu -->
        <?php include __DIR__ . '/components/mobile_menu.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1 p-4 w-100 position-relative">
            <!-- Subscription Alert -->
            <?php include __DIR__ . '/components/subscription_alert.php'; ?>

            <div class="container-fluid">
                <div class="content-wrapper">
                    <!-- Header -->
                    <div class="header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-0">
                                    <i class="fas fa-robot text-primary me-2"></i>
                                    Automações de IA e Permissões
                                </h2>
                                <p class="text-muted mb-0">Configure os lembretes automáticos e administradores do WhatsApp</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Coluna de Admins -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user-shield text-warning me-2"></i>Administradores do WhatsApp</h5>
                                    <small class="text-muted">Telefones autorizados a dar comandos sensíveis para a IA (ex: Quitar Faturas)</small>
                                </div>
                                <div class="card-body">
                                    <form id="formAddAdmin" class="mb-4">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <input type="text" name="nome" class="form-control" placeholder="Nome" required>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" name="telefone" class="form-control" placeholder="Ex: 5511999999999" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Telefone</th>
                                                    <th>Status</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admins as $admin): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($admin['nome']) ?></td>
                                                    <td><?= htmlspecialchars($admin['telefone']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $admin['ativo'] ? 'success' : 'danger' ?>">
                                                            <?= $admin['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="removerAdmin(<?= $admin['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if(empty($admins)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">Nenhum administrador cadastrado.</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Coluna de Follow up -->
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-comments text-success me-2"></i>Gatilhos de Follow-up</h5>
                                    <small class="text-muted">Configure as mensagens automáticas para recuperar clientes.</small>
                                </div>
                                <div class="card-body">
                                    <form id="formAutomations">
                                        <!-- Abandono de Carrinho -->
                                        <div class="border rounded p-3 mb-4 bg-light border-0">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-shopping-cart text-muted me-2"></i> Recuperação de Abandono</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="abandono_ativo" <?= $auto_abandono['ativo'] ? 'checked' : '' ?>>
                                                </div>
                                            </div>
                                            <div class="mb-3 row align-items-center">
                                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">Aguardar (minutos):</label>
                                                <div class="col-sm-4">
                                                    <input type="number" class="form-control form-control-sm" name="abandono_tempo" value="<?= $auto_abandono['tempo_espera'] ?>">
                                                </div>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label text-secondary small">Mensagem (use {nome} para o nome do cliente):</label>
                                                <textarea class="form-control form-control-sm border-0" name="abandono_msg" rows="3"><?= htmlspecialchars($auto_abandono['mensagem_template']) ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Cliente Sumido -->
                                        <div class="border rounded p-3 mb-4 bg-light border-0">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-user-clock text-muted me-2"></i> Mensagem de Saudade</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="saudade_ativo" <?= $auto_saudade['ativo'] ? 'checked' : '' ?>>
                                                </div>
                                            </div>
                                            <div class="mb-3 row align-items-center">
                                                <label class="col-sm-5 col-form-label col-form-label-sm text-secondary">Sem pedir há (dias):</label>
                                                <div class="col-sm-4">
                                                    <input type="number" class="form-control form-control-sm" name="saudade_tempo" value="<?= $auto_saudade['tempo_espera'] ?>">
                                                </div>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label text-secondary small">Mensagem (use {nome}):</label>
                                                <textarea class="form-control form-control-sm border-0" name="saudade_msg" rows="3"><?= htmlspecialchars($auto_saudade['mensagem_template']) ?></textarea>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                                            <i class="fas fa-save me-2"></i> Salvar Automações
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        document.getElementById('formAddAdmin').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'salvar_whatsapp_admin');
            
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else Swal.fire('Erro', data.message, 'error');
            });
        });

        function removerAdmin(id) {
            if(!confirm('Remover este administrador?')) return;
            const formData = new FormData();
            formData.append('action', 'remover_whatsapp_admin');
            formData.append('id', id);
            
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else Swal.fire('Erro', data.message, 'error');
            });
        }

        document.getElementById('formAutomations').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'salvar_ai_automations');
            
            fetch('index.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Automações salvas com sucesso.',
                        icon: 'success',
                        confirmButtonColor: 'var(--primary-color)'
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        });
    </script>
</body>
</html>
