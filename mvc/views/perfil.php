<?php
// Client Profile Page
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user
$user = $session->getUser();
$userId = $user['id'] ?? $_SESSION['usuario_global_id'] ?? null;

if (!$userId) {
    header('Location: index.php?view=login');
    exit();
}

// Get user data
$userData = $db->fetch(
    "SELECT * FROM usuarios_globais WHERE id = ?",
    [$userId]
);

// Get user addresses
$addresses = $db->fetchAll(
    "SELECT * FROM enderecos WHERE usuario_global_id = ? AND ativo = true ORDER BY principal DESC, created_at DESC",
    [$userId]
);

// Get user preferences
$preferences = $db->fetch(
    "SELECT * FROM preferencias_cliente WHERE usuario_global_id = ? LIMIT 1",
    [$userId]
);

// Get establishments where user is registered
$establishments = $db->fetchAll(
    "SELECT ue.*, t.nome as tenant_nome, f.nome as filial_nome 
     FROM usuarios_estabelecimento ue
     LEFT JOIN tenants t ON ue.tenant_id = t.id
     LEFT JOIN filiais f ON ue.filial_id = f.id
     WHERE ue.usuario_global_id = ? AND ue.ativo = true",
    [$userId]
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .profile-header {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
        }
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .info-row {
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .info-value {
            color: #333;
        }
        .address-card {
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .address-card.principal {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .badge-principal {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .establishment-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #f8f9ff;
            border: 1px solid #667eea;
            border-radius: 20px;
            margin: 0.25rem;
        }
        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <a href="<?php echo $router->url('cliente_dashboard'); ?>" class="btn btn-primary btn-back">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2><?php echo htmlspecialchars($userData['nome'] ?? 'Cliente'); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></p>
            </div>

            <!-- Personal Information -->
            <h4 class="section-title">
                <i class="fas fa-id-card me-2"></i>Informações Pessoais
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">Nome Completo</div>
                        <div class="info-value" data-field="nome"><?php echo htmlspecialchars($userData['nome'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">CPF</div>
                        <div class="info-value" data-field="cpf"><?php echo htmlspecialchars($userData['cpf'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value" data-field="email"><?php echo htmlspecialchars($userData['email'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">Data de Nascimento</div>
                        <div class="info-value" data-field="data_nascimento">
                            <?php 
                            if (!empty($userData['data_nascimento'])) {
                                echo date('d/m/Y', strtotime($userData['data_nascimento']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">Telefone Principal</div>
                        <div class="info-value" data-field="telefone"><?php echo htmlspecialchars($userData['telefone'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="info-label">Telefone Secundário</div>
                        <div class="info-value" data-field="telefone_secundario"><?php echo htmlspecialchars($userData['telefone_secundario'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <h4 class="section-title mt-4">
                <i class="fas fa-map-marker-alt me-2"></i>Endereços
            </h4>
            <?php if (empty($addresses)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Nenhum endereço cadastrado.
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?php echo $address['principal'] ? 'principal' : ''; ?>">
                        <?php if ($address['principal']): ?>
                            <span class="badge bg-primary badge-principal">Principal</span>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-8">
                                <strong><?php echo htmlspecialchars($address['tipo']); ?></strong>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars($address['logradouro']); ?>, 
                                    <?php echo htmlspecialchars($address['numero']); ?>
                                    <?php if ($address['complemento']): ?>
                                        - <?php echo htmlspecialchars($address['complemento']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars($address['bairro']); ?> - 
                                    <?php echo htmlspecialchars($address['cidade']); ?>/<?php echo htmlspecialchars($address['estado']); ?>
                                </p>
                                <p class="mb-0 text-muted">
                                    CEP: <?php echo htmlspecialchars($address['cep']); ?>
                                </p>
                                <?php if ($address['referencia']): ?>
                                    <p class="mb-0 text-muted">
                                        <small><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($address['referencia']); ?></small>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Establishments -->
            <h4 class="section-title mt-4">
                <i class="fas fa-store me-2"></i>Estabelecimentos Vinculados
            </h4>
            <?php if (empty($establishments)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Nenhum estabelecimento vinculado.
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <?php foreach ($establishments as $est): ?>
                        <div class="establishment-badge">
                            <i class="fas fa-store"></i>
                            <strong><?php echo htmlspecialchars($est['tenant_nome']); ?></strong>
                            <?php if ($est['filial_nome']): ?>
                                - <?php echo htmlspecialchars($est['filial_nome']); ?>
                            <?php endif; ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($est['tipo_usuario']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Preferences -->
            <?php if ($preferences): ?>
                <h4 class="section-title mt-4">
                    <i class="fas fa-cog me-2"></i>Preferências
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Receber Promoções</div>
                            <div class="info-value">
                                <?php echo $preferences['receber_promocoes'] ? 
                                    '<span class="badge bg-success">Sim</span>' : 
                                    '<span class="badge bg-secondary">Não</span>'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Receber Notificações</div>
                            <div class="info-value">
                                <?php echo $preferences['receber_notificacoes'] ? 
                                    '<span class="badge bg-success">Sim</span>' : 
                                    '<span class="badge bg-secondary">Não</span>'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Forma de Pagamento Preferida</div>
                            <div class="info-value"><?php echo htmlspecialchars($preferences['forma_pagamento_preferida'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="mt-4 text-center">
                <a href="<?php echo $router->url('historico_pedidos'); ?>" class="btn btn-primary me-2">
                    <i class="fas fa-history"></i> Ver Histórico de Pedidos
                </a>
                <button class="btn btn-outline-primary" onclick="enableEdit()">
                    <i class="fas fa-edit"></i> Editar Perfil
                </button>
                <button class="btn btn-success d-none" id="saveBtn" onclick="saveProfile()">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <button class="btn btn-secondary d-none" id="cancelBtn" onclick="cancelEdit()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    let editMode = false;
    let originalData = {};

    function enableEdit() {
        editMode = true;
        document.querySelectorAll('.info-value').forEach(el => {
            const field = el.dataset.field;
            if (field) {
                const text = el.textContent.trim();
                originalData[field] = text;
                el.innerHTML = `<input type="text" class="form-control" value="${text}" data-field="${field}">`;
            }
        });
        const editBtn = document.querySelector('button.btn-outline-primary');
        if (editBtn) editBtn.classList.add('d-none');
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) saveBtn.classList.remove('d-none');
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) cancelBtn.classList.remove('d-none');
    }

    function cancelEdit() {
        editMode = false;
        document.querySelectorAll('.info-value input').forEach(el => {
            const field = el.dataset.field;
            el.parentElement.innerHTML = `<span class="info-value" data-field="${field}">${originalData[field]}</span>`;
        });
        document.querySelector('button.btn-outline-primary').classList.remove('d-none');
        document.getElementById('saveBtn').classList.add('d-none');
        document.getElementById('cancelBtn').classList.add('d-none');
    }

    async function saveProfile() {
        const data = {};
        document.querySelectorAll('.info-value input').forEach(el => {
            data[el.dataset.field] = el.value;
        });

        try {
            const formData = new URLSearchParams({
                action: 'atualizar',
                id: '<?php echo $userId; ?>'
            });
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch('index.php?action=clientes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                Swal.fire('Sucesso!', 'Perfil atualizado com sucesso!', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Erro!', result.message || 'Erro ao atualizar perfil', 'error');
            }
        } catch (error) {
            Swal.fire('Erro!', 'Erro ao salvar perfil: ' + error.message, 'error');
        }
    }
    </script>
</body>
</html>








