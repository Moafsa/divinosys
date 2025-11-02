<?php
/**
 * View de Gerenciamento de Faturas
 * Lista histórico de faturas e permite upgrade/downgrade de plano
 */

try {
    $session = \System\Session::getInstance();
    $db = \System\Database::getInstance();

    // Ensure tenant and filial context
    $context = \System\TenantHelper::ensureTenantContext();
    $tenant = $context['tenant'];
    $filial = $context['filial'];
    $user = $session->getUser();

    if (!$tenant) {
        header('Location: index.php?view=login');
        exit;
    }
} catch (Exception $e) {
    error_log("gerenciar_faturas.php - Erro: " . $e->getMessage());
    echo "<!DOCTYPE html><html><head><title>Erro</title></head><body>";
    echo "<h1>Erro ao carregar página de faturas</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit;
}

// Buscar assinatura ativa
$assinatura = $db->fetch("
    SELECT a.*, p.nome as plano_nome, p.preco_mensal, p.recursos
    FROM assinaturas a
    LEFT JOIN planos p ON a.plano_id = p.id
    WHERE a.tenant_id = ?
    ORDER BY a.created_at DESC
    LIMIT 1
", [$tenant['id']]);

// Buscar todos os planos disponíveis
$planos = $db->fetchAll("SELECT * FROM planos ORDER BY preco_mensal ASC");

// Buscar histórico de faturas (TABELA CORRETA: pagamentos_assinaturas)
$faturas = $db->fetchAll("
    SELECT 
        p.*,
        a.periodicidade
    FROM pagamentos_assinaturas p
    LEFT JOIN assinaturas a ON p.assinatura_id = a.id
    WHERE p.tenant_id = ?
    ORDER BY p.created_at DESC
", [$tenant['id']]);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Faturas - <?php echo htmlspecialchars($tenant['nome']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .main-container {
            margin-left: 250px; /* Width of sidebar */
            padding: 20px;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
            }
        }
        .plan-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .plan-card.current {
            border: 3px solid #667eea;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .plan-card .plan-name {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .plan-card .plan-price {
            font-size: 36px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 5px;
        }
        .plan-card .plan-period {
            color: #718096;
            margin-bottom: 20px;
        }
        .plan-features {
            list-style: none;
            padding: 0;
        }
        .plan-features li {
            padding: 8px 0;
            color: #4a5568;
        }
        .plan-features li i {
            color: #48bb78;
            margin-right: 10px;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/components/sidebar.php'; ?>

    <div class="main-container">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-white mb-3">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        Gerenciar Faturas e Assinatura
                    </h2>
                </div>
            </div>

            <!-- Warning for subscriptions without Asaas integration -->
            <?php if ($assinatura): ?>
                <?php if (empty($assinatura['asaas_subscription_id'])): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-3" style="font-size: 24px;"></i>
                            <div>
                                <strong>Modo Local</strong><br>
                                <small>Esta assinatura não está integrada com o Asaas. Mudanças de plano e faturas serão gerenciadas apenas localmente. Para ativar integração automática, cadastre um novo estabelecimento via página de registro.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif (!str_starts_with($assinatura['asaas_subscription_id'], 'sub_')): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-3" style="font-size: 24px;"></i>
                            <div>
                                <strong>Assinatura Antiga Detectada</strong><br>
                                <small>Esta assinatura foi criada no modelo antigo (pagamento único). Mudanças de plano e periodicidade serão aplicadas apenas localmente. Para ter sincronização automática com o Asaas, cadastre um novo estabelecimento.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Current Plan -->
            <?php if ($assinatura): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="plan-card current">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="plan-name">
                                    <i class="fas fa-star text-warning me-2"></i>
                                    Plano Atual: <?php echo htmlspecialchars($assinatura['plano_nome']); ?>
                                </div>
                                <div class="plan-price">
                                    R$ <?php echo number_format($assinatura['valor'], 2, ',', '.'); ?>
                                    <span style="font-size: 18px; color: #718096;">/ <?php echo $assinatura['periodicidade']; ?></span>
                                </div>
                                <div class="plan-period">
                                    <strong>Status:</strong> 
                                    <?php
                                    $statusColors = [
                                        'ativa' => 'success',
                                        'trial' => 'info',
                                        'suspensa' => 'warning',
                                        'cancelada' => 'danger'
                                    ];
                                    $statusColor = $statusColors[$assinatura['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst($assinatura['status']); ?></span>
                                </div>
                                <?php if ($assinatura['data_proxima_cobranca']): ?>
                                <div class="text-muted">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Próxima cobrança: <?php echo date('d/m/Y', strtotime($assinatura['data_proxima_cobranca'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <button class="btn btn-primary" onclick="showChangePlanModal()">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    Mudar Plano
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>


            <!-- Invoices History -->
            <div class="row">
                <div class="col-12">
                    <div class="table-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Histórico de Faturas
                            </h4>
                            <button class="btn btn-outline-primary btn-sm" onclick="syncInvoices()">
                                <i class="fas fa-sync me-1"></i>
                                Sincronizar Faturas
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                <th>#</th>
                                <th>Data Criação</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Periodicidade</th>
                                <th>Status</th>
                                <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($faturas)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Nenhuma fatura encontrada
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($faturas as $fatura): ?>
                                    <tr>
                                        <td>#<?php echo $fatura['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($fatura['created_at'])); ?></td>
                                        <td><?php echo $fatura['data_vencimento'] ? date('d/m/Y', strtotime($fatura['data_vencimento'])) : '-'; ?></td>
                                        <td>R$ <?php echo number_format($fatura['valor'] ?? $fatura['valor_pago'], 2, ',', '.'); ?></td>
                                        <td><?php echo ucfirst($fatura['periodicidade'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pendente' => 'warning',
                                                'pago' => 'success',
                                                'falhou' => 'danger',
                                                'cancelado' => 'secondary'
                                            ];
                                            $statusColor = $statusColors[$fatura['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($fatura['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($fatura['status'] === 'pendente'): ?>
                                                <?php
                                                $gatewayData = json_decode($fatura['gateway_response'], true);
                                                $invoiceUrl = $gatewayData['invoiceUrl'] ?? null;
                                                ?>
                                                <?php if ($invoiceUrl): ?>
                                                <a href="<?php echo htmlspecialchars($invoiceUrl); ?>" target="_blank" class="btn btn-sm btn-success">
                                                    <i class="fas fa-qrcode me-1"></i>Pagar
                                                </a>
                                                <?php endif; ?>
                                            <?php elseif ($fatura['status'] === 'pago'): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>Pago em <?php echo date('d/m/Y', strtotime($fatura['data_pagamento'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        function showChangePlanModal() {
            const planos = <?php echo json_encode($planos); ?>;
            const currentPlanId = <?php echo $assinatura['plano_id'] ?? 'null'; ?>;
            
            let planosHtml = '<div class="text-start mb-3">';
            planosHtml += '<label class="form-label fw-bold">Selecione o Plano:</label>';
            planosHtml += '<div class="list-group">';
            
            planos.forEach(plano => {
                const isCurrent = plano.id == currentPlanId;
                const price = parseFloat(plano.preco_mensal);
                const currentPrice = <?php echo $assinatura['valor'] ?? 0; ?>;
                const isUpgrade = price > currentPrice;
                
                planosHtml += `
                    <label class="list-group-item list-group-item-action ${isCurrent ? 'active' : ''}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <input type="radio" name="plano_id" value="${plano.id}" ${isCurrent ? 'checked disabled' : ''} class="me-2">
                                <strong>${plano.nome}</strong>
                                ${isCurrent ? '<span class="badge bg-success ms-2">Atual</span>' : ''}
                                ${!isCurrent && isUpgrade ? '<span class="badge bg-primary ms-2">↑ Upgrade</span>' : ''}
                                ${!isCurrent && !isUpgrade ? '<span class="badge bg-warning ms-2">↓ Downgrade</span>' : ''}
                            </div>
                            <div class="text-end">
                                <strong class="text-primary">R$ ${price.toFixed(2)}</strong>
                                <small class="d-block text-muted">/mês</small>
                            </div>
                        </div>
                        <small class="d-block mt-2 text-muted">
                            <i class="fas fa-check text-success me-1"></i> ${plano.max_mesas == -1 ? 'Mesas ilimitadas' : plano.max_mesas + ' mesas'}
                            &nbsp;•&nbsp;
                            <i class="fas fa-check text-success me-1"></i> ${plano.max_usuarios == -1 ? 'Usuários ilimitados' : plano.max_usuarios + ' usuários'}
                            &nbsp;•&nbsp;
                            <i class="fas fa-check text-success me-1"></i> ${plano.max_produtos == -1 ? 'Produtos ilimitados' : plano.max_produtos + ' produtos'}
                        </small>
                    </label>
                `;
            });
            
            planosHtml += '</div></div>';
            
            planosHtml += `
                <div class="mb-3">
                    <label class="form-label fw-bold">Periodicidade:</label>
                    <select id="changePlanPeriod" class="form-select">
                        <option value="mensal">Mensal</option>
                        <option value="semestral">Semestral (-10%)</option>
                        <option value="anual">Anual (-20%)</option>
                    </select>
                </div>
            `;
            
            Swal.fire({
                title: 'Mudar Plano de Assinatura',
                html: planosHtml,
                width: 700,
                showCancelButton: true,
                confirmButtonText: 'Confirmar Mudança',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const selectedPlan = document.querySelector('input[name="plano_id"]:checked');
                    if (!selectedPlan) {
                        Swal.showValidationMessage('Por favor, selecione um plano');
                        return false;
                    }
                    return {
                        plano_id: parseInt(selectedPlan.value),
                        periodicidade: document.getElementById('changePlanPeriod').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Fazer chamada AJAX para mudar o plano
                    $.ajax({
                        url: 'index.php?action=mudarPlano',
                        method: 'POST',
                        data: JSON.stringify(result.value),
                        contentType: 'application/json'
                    })
                    .done(function(response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', 'Plano alterado com sucesso!', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Erro!', response.error || 'Erro ao alterar plano', 'error');
                        }
                    })
                    .fail(function() {
                        Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
                    });
                }
            });
        }


        function syncInvoices() {
            Swal.fire({
                title: 'Sincronizando...',
                text: 'Buscando novas faturas do Asaas',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'index.php?action=syncAsaasInvoices',
                method: 'POST'
            })
            .done(function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('Sucesso!', response.message || 'Faturas sincronizadas!', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Aviso', response.message || 'Nenhuma nova fatura encontrada', 'info');
                }
            })
            .fail(function() {
                Swal.close();
                Swal.fire('Erro!', 'Erro ao sincronizar faturas', 'error');
            });
        }
    </script>
</body>
</html>
