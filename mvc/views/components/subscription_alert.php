<?php
/**
 * Componente de Alerta de Assinatura
 * Exibe avisos sobre trial, faturas vencidas e bloqueios
 */

require_once __DIR__ . '/../../../system/Middleware/SubscriptionCheck.php';

use System\Middleware\SubscriptionCheck;

// Verificar status da assinatura
$alert = SubscriptionCheck::getAlertMessage();

// Se não há alertas ou mensagem é null, não exibir nada
if (!$alert || !isset($alert['message']) || empty($alert['message'])) {
    return;
}

// Definir classes CSS baseadas no tipo
$alertClass = '';
$iconClass = '';
switch ($alert['type']) {
    case 'error':
        $alertClass = 'alert-danger';
        $iconClass = 'fas fa-exclamation-triangle';
        break;
    case 'warning':
        $alertClass = 'alert-warning';
        $iconClass = 'fas fa-exclamation-circle';
        break;
    case 'info':
        $alertClass = 'alert-info';
        $iconClass = 'fas fa-info-circle';
        break;
    default:
        $alertClass = 'alert-secondary';
        $iconClass = 'fas fa-bell';
}
?>

<style>
/* Banner estreito no topo */
.subscription-alert {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    padding: 0.5rem 1rem;
    margin: 0;
    border-radius: 0;
    border-bottom: 3px solid;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    animation: slideDown 0.3s ease-out;
    max-height: 60px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.subscription-alert.expanded {
    max-height: 300px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-100%);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.subscription-alert.blocked {
    border-bottom-color: #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%);
}

.subscription-alert.warning {
    border-bottom-color: #ffc107;
    background: linear-gradient(135deg, #fffbf0 0%, #fff3cd 100%);
}

.subscription-alert.info {
    border-bottom-color: #0dcaf0;
    background: linear-gradient(135deg, #f0f9ff 0%, #cff4fc 100%);
}

.subscription-alert .alert-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
}

.subscription-alert .alert-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.subscription-alert .alert-heading {
    font-size: 0.9rem;
    margin: 0;
    font-weight: 600;
}

.subscription-alert .alert-message {
    font-size: 0.85rem;
    margin: 0;
}

.subscription-alert .btn-expand {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.subscription-alert .btn-pay {
    font-size: 0.8rem;
    padding: 0.3rem 0.8rem;
}

.subscription-alert .alert-details {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(0,0,0,0.1);
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .subscription-alert {
        padding: 0.4rem 0.8rem;
    }
    
    .subscription-alert .alert-heading {
        font-size: 0.8rem;
    }
    
    .subscription-alert .alert-message {
        font-size: 0.75rem;
    }
}

/* Ajustar conteúdo principal para não ficar embaixo do banner */
body {
    padding-top: 60px;
}
</style>

<div class="subscription-alert alert <?php echo $alertClass; ?> <?php echo $alert['type']; ?> alert-dismissible fade show" role="alert" id="subscriptionAlert">
    <!-- Banner compacto - uma linha -->
    <div class="alert-content">
        <div class="d-flex align-items-center flex-grow-1">
            <i class="<?php echo $iconClass; ?> alert-icon"></i>
            <strong class="alert-heading me-2">
                <?php if ($alert['blocked']): ?>
                    🚫 Bloqueado
                <?php elseif ($alert['type'] === 'warning'): ?>
                    ⚠️ Atenção
                <?php else: ?>
                    ℹ️ Info
                <?php endif; ?>:
            </strong>
            <span class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></span>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($alert['details']['overdue_payment'])): ?>
                <?php $payment = $alert['details']['overdue_payment']; ?>
                <?php if ($payment['gateway_payment_id'] && isset($payment['gateway_response'])): ?>
                    <?php $gatewayData = json_decode($payment['gateway_response'], true); ?>
                    <?php if (isset($gatewayData['invoiceUrl'])): ?>
                        <a href="<?php echo htmlspecialchars($gatewayData['invoiceUrl']); ?>" 
                           target="_blank" 
                           class="btn btn-success btn-sm btn-pay">
                            <i class="fas fa-qrcode me-1"></i>Pagar
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            
            <button type="button" class="btn btn-sm btn-outline-secondary btn-expand" onclick="toggleAlertDetails()">
                <i class="fas fa-chevron-down" id="expandIcon"></i>
            </button>
            
            <?php if (!$alert['blocked']): ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detalhes expansíveis -->
    <div class="alert-details" id="alertDetails" style="display: none;">
        <?php if (isset($alert['details']['in_trial']) && $alert['details']['in_trial']): ?>
            <div class="mb-2">
                📅 <strong>Período de teste:</strong> <?php echo $alert['details']['trial_days_left']; ?> dias restantes
            </div>
        <?php endif; ?>
        
        <?php if (isset($alert['details']['overdue_payment'])): ?>
            <?php $payment = $alert['details']['overdue_payment']; ?>
            <div class="mb-2">
                💳 <strong>Fatura:</strong> R$ <?php echo number_format($payment['valor'], 2, ',', '.'); ?> 
                | 📆 <strong>Vencimento:</strong> <?php echo date('d/m/Y', strtotime($payment['data_vencimento'])); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($alert['blocked']): ?>
            <div>
                <strong>Ações bloqueadas:</strong> Criar pedidos, produtos, usuários
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAlertDetails() {
    const details = document.getElementById('alertDetails');
    const icon = document.getElementById('expandIcon');
    const alert = document.getElementById('subscriptionAlert');
    
    if (details.style.display === 'none') {
        details.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        alert.classList.add('expanded');
    } else {
        details.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        alert.classList.remove('expanded');
    }
}

// Sistema de "não mostrar por 24h"
document.addEventListener('DOMContentLoaded', function() {
    const alertElement = document.getElementById('subscriptionAlert');
    
    if (!alertElement) return;
    
    const alertType = '<?php echo $alert['type']; ?>';
    const isBlocked = <?php echo $alert['blocked'] ? 'true' : 'false'; ?>;
    
    // Se bloqueado, SEMPRE mostrar (não pode esconder)
    if (isBlocked) {
        return;
    }
    
    // Verificar se foi fechado nas últimas 24h
    const dismissedUntil = localStorage.getItem('subscription_alert_dismissed_until');
    const now = Date.now();
    
    if (dismissedUntil && now < parseInt(dismissedUntil)) {
        // Ainda está dentro das 24h, esconder
        alertElement.style.display = 'none';
        document.body.style.paddingTop = '0';
        console.log('Alerta escondido até:', new Date(parseInt(dismissedUntil)));
        return;
    }
    
    // Quando fechar o alerta, salvar timestamp + 24h
    const closeBtn = alertElement.querySelector('.btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            const hideUntil = now + (24 * 60 * 60 * 1000); // 24 horas
            localStorage.setItem('subscription_alert_dismissed_until', hideUntil.toString());
            document.body.style.paddingTop = '0';
            console.log('Alerta escondido por 24h até:', new Date(hideUntil));
        });
    }
});
</script>

