<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$db = \System\Database::getInstance();

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
    <title>Automações de IA - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-robot text-primary"></i> Automações de IA e Permissões</h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="row">
            <!-- Coluna de Admins -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-user-shield text-warning"></i> Administradores do WhatsApp</h5>
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
                                <thead>
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
                                        <td colspan="4" class="text-center text-muted">Nenhum administrador cadastrado.</td>
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
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-comments text-success"></i> Gatilhos de Follow-up</h5>
                        <small class="text-muted">Configure as mensagens automáticas para recuperar clientes.</small>
                    </div>
                    <div class="card-body">
                        <form id="formAutomations">
                            <!-- Abandono de Carrinho -->
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6 class="mb-0">Recuperação de Abandono</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="abandono_ativo" <?= $auto_abandono['ativo'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="mb-2 row">
                                    <label class="col-sm-5 col-form-label col-form-label-sm">Aguardar (minutos):</label>
                                    <div class="col-sm-4">
                                        <input type="number" class="form-control form-control-sm" name="abandono_tempo" value="<?= $auto_abandono['tempo_espera'] ?>">
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label text-muted small">Mensagem (use {nome} para o nome do cliente):</label>
                                    <textarea class="form-control form-control-sm" name="abandono_msg" rows="2"><?= htmlspecialchars($auto_abandono['mensagem_template']) ?></textarea>
                                </div>
                            </div>

                            <!-- Cliente Sumido -->
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6 class="mb-0">Mensagem de Saudade</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="saudade_ativo" <?= $auto_saudade['ativo'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="mb-2 row">
                                    <label class="col-sm-5 col-form-label col-form-label-sm">Sem pedir há (dias):</label>
                                    <div class="col-sm-4">
                                        <input type="number" class="form-control form-control-sm" name="saudade_tempo" value="<?= $auto_saudade['tempo_espera'] ?>">
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label text-muted small">Mensagem (use {nome}):</label>
                                    <textarea class="form-control form-control-sm" name="saudade_msg" rows="2"><?= htmlspecialchars($auto_saudade['mensagem_template']) ?></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Salvar Automações
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                if(data.success) Swal.fire('Sucesso', 'Automações salvas!', 'success');
                else Swal.fire('Erro', data.message, 'error');
            });
        });
    </script>
</body>
</html>
