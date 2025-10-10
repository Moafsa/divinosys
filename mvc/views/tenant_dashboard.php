<?php
// Verificar se está autenticado
if (!isset($_SESSION['tenant_id'])) {
    header('Location: index.php?view=login');
    exit;
}

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../middleware/SubscriptionMiddleware.php';

$tenantModel = new Tenant();
$subscriptionModel = new Subscription();
$middleware = new SubscriptionMiddleware();

$tenant_id = $_SESSION['tenant_id'];
$tenant = $tenantModel->getById($tenant_id);
$subscription = $subscriptionModel->getByTenant($tenant_id);
$filiais = $tenantModel->getFiliais($tenant_id);
$usageInfo = $middleware->getUsageInfo();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Estabelecimento - <?php echo htmlspecialchars($tenant['nome']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .usage-bar {
            height: 10px;
            border-radius: 5px;
            background: #e9ecef;
            overflow: hidden;
            margin: 10px 0;
        }
        .usage-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }
        .filial-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .filial-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .subscription-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .subscription-badge.trial {
            background: #fff3cd;
            color: #856404;
        }
        .subscription-badge.ativa {
            background: #d4edda;
            color: #155724;
        }
        .subscription-badge.inadimplente {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-building text-primary"></i> <?php echo htmlspecialchars($tenant['nome']); ?></h2>
                    <p class="text-muted mb-0">
                        <i class="fas fa-link"></i> <?php echo htmlspecialchars($tenant['subdomain']); ?>.divinolanches.com.br
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($subscription): ?>
                        <span class="subscription-badge <?php echo $subscription['status']; ?>">
                            <i class="fas fa-crown"></i> <?php echo htmlspecialchars($subscription['plano_nome']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Informações da Assinatura -->
            <div class="col-md-4">
                <div class="info-card">
                    <h5 class="mb-4"><i class="fas fa-credit-card text-primary"></i> Assinatura</h5>
                    
                    <?php if ($subscription): ?>
                        <div class="mb-3">
                            <strong>Plano:</strong> <?php echo htmlspecialchars($subscription['plano_nome']); ?><br>
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $subscription['status'] == 'ativa' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($subscription['status']); ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Valor:</strong> R$ <?php echo number_format($subscription['valor'], 2, ',', '.'); ?>/mês<br>
                            <strong>Próxima cobrança:</strong> 
                            <?php echo date('d/m/Y', strtotime($subscription['data_proxima_cobranca'])); ?>
                        </div>
                        
                        <?php if ($subscription['status'] == 'trial'): ?>
                            <div class="alert alert-warning">
                                <small>
                                    <i class="fas fa-clock"></i> Período trial até 
                                    <?php echo date('d/m/Y', strtotime($subscription['trial_ate'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-arrow-up"></i> Fazer Upgrade
                        </button>
                        <button class="btn btn-outline-secondary w-100">
                            <i class="fas fa-file-invoice"></i> Ver Faturas
                        </button>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> Nenhuma assinatura ativa
                        </div>
                        <button class="btn btn-primary w-100">
                            <i class="fas fa-shopping-cart"></i> Assinar Agora
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="info-card">
                    <h5 class="mb-3"><i class="fas fa-cog text-primary"></i> Ações Rápidas</h5>
                    <a href="index.php?view=Dashboard1" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-home"></i> Dashboard Principal
                    </a>
                    <button class="btn btn-outline-secondary w-100 mb-2" onclick="editarEstabelecimento()">
                        <i class="fas fa-edit"></i> Editar Dados
                    </button>
                    <button class="btn btn-outline-info w-100">
                        <i class="fas fa-question-circle"></i> Suporte
                    </button>
                </div>
            </div>

            <!-- Uso dos Recursos -->
            <div class="col-md-8">
                <div class="info-card">
                    <h5 class="mb-4"><i class="fas fa-chart-bar text-primary"></i> Uso dos Recursos</h5>
                    
                    <?php if ($usageInfo): ?>
                        <?php foreach ($usageInfo['usage'] as $key => $usage): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-bold text-capitalize"><?php echo str_replace('_', ' ', $key); ?></span>
                                    <span class="text-muted">
                                        <?php echo $usage['usado']; ?> / 
                                        <?php echo $usage['ilimitado'] ? 'Ilimitado' : $usage['limite']; ?>
                                    </span>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-bar-fill" style="width: <?php echo min($usage['porcentagem'], 100); ?>%"></div>
                                </div>
                                <?php if ($usage['porcentagem'] >= 80 && !$usage['ilimitado']): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Atingindo o limite
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Filiais -->
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-store text-primary"></i> Minhas Filiais</h5>
                        <button class="btn btn-sm btn-primary" onclick="adicionarFilial()">
                            <i class="fas fa-plus"></i> Nova Filial
                        </button>
                    </div>
                    
                    <?php if (empty($filiais)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Nenhuma filial cadastrada. 
                            <a href="#" onclick="adicionarFilial(); return false;">Adicione sua primeira filial</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($filiais as $filial): ?>
                            <div class="filial-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($filial['nome']); ?></h6>
                                        <small class="text-muted">
                                            <?php if ($filial['endereco']): ?>
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($filial['endereco']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($filial['telefone']): ?>
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($filial['telefone']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="badge bg-<?php echo $filial['status'] == 'ativo' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($filial['status']); ?>
                                        </span>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editarFilial(<?php echo $filial['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletarFilial(<?php echo $filial['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function adicionarFilial() {
            Swal.fire({
                title: 'Nova Filial',
                html: `
                    <input type="text" id="filial-nome" class="swal2-input" placeholder="Nome da filial">
                    <input type="text" id="filial-endereco" class="swal2-input" placeholder="Endereço">
                    <input type="text" id="filial-telefone" class="swal2-input" placeholder="Telefone">
                    <input type="text" id="filial-email" class="swal2-input" placeholder="Email">
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    return {
                        nome: document.getElementById('filial-nome').value,
                        endereco: document.getElementById('filial-endereco').value,
                        telefone: document.getElementById('filial-telefone').value,
                        email: document.getElementById('filial-email').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'mvc/controller/TenantController.php?action=createFilial',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(result.value),
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Filial criada com sucesso', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Erro!', response.error || 'Erro ao criar filial', 'error');
                            }
                        }
                    });
                }
            });
        }

        function editarFilial(id) {
            // Implementation
        }

        function deletarFilial(id) {
            Swal.fire({
                title: 'Confirmar exclusão?',
                text: 'Esta filial será inativada',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, inativar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'mvc/controller/TenantController.php?action=deleteFilial&id=' + id,
                        method: 'DELETE',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Filial inativada', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Erro!', 'Erro ao inativar filial', 'error');
                            }
                        }
                    });
                }
            });
        }

        function editarEstabelecimento() {
            // Implementation
        }
    </script>
</body>
</html>

