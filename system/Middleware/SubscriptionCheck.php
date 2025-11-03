<?php

namespace System\Middleware;

use System\Database;
use System\Session;

class SubscriptionCheck
{
    /**
     * Verificar se o tenant tem assinatura ativa e não está vencido
     * Retorna array com status e mensagens
     */
    public static function checkSubscriptionStatus()
    {
        $session = Session::getInstance();
        $db = Database::getInstance();
        
        $tenantId = $session->getTenantId();
        
        if (!$tenantId) {
            return [
                'active' => false,
                'blocked' => true,
                'message' => 'Tenant não identificado',
                'type' => 'error'
            ];
        }
        
        // Buscar assinatura do tenant
        $subscription = $db->fetch("
            SELECT 
                a.*,
                t.status as tenant_status,
                t.nome as tenant_nome
            FROM assinaturas a
            INNER JOIN tenants t ON a.tenant_id = t.id
            WHERE a.tenant_id = ?
            ORDER BY a.created_at DESC
            LIMIT 1
        ", [$tenantId]);
        
        if (!$subscription) {
            return [
                'active' => false,
                'blocked' => true,
                'message' => 'Nenhuma assinatura encontrada para este estabelecimento',
                'type' => 'error'
            ];
        }
        
        // Se tenant está suspenso, bloquear
        if ($subscription['tenant_status'] === 'suspenso' || $subscription['tenant_status'] === 'inativo') {
            return [
                'active' => false,
                'blocked' => true,
                'message' => 'Estabelecimento suspenso. Entre em contato com o suporte.',
                'type' => 'error',
                'subscription' => $subscription
            ];
        }
        
        // Verificar trial
        if ($subscription['trial_ate']) {
            $trialEnd = new \DateTime($subscription['trial_ate']);
            $now = new \DateTime();
            
            // Se trial expirou
            if ($now > $trialEnd) {
                // Verificar se há pagamentos pendentes VENCIDOS (tabela correta: pagamentos_assinaturas)
                $paymentOverdue = $db->fetch("
                    SELECT * FROM pagamentos_assinaturas
                    WHERE tenant_id = ? 
                    AND status = 'pendente'
                    AND data_vencimento < CURRENT_DATE
                    ORDER BY data_vencimento ASC
                    LIMIT 1
                ", [$tenantId]);
                
                if ($paymentOverdue) {
                    // Trial expirado E tem fatura vencida = BLOQUEAR
                    $dueDate = new \DateTime($paymentOverdue['data_vencimento']);
                    $daysOverdue = $now->diff($dueDate)->days;
                    
                    return [
                        'active' => false,
                        'blocked' => true,
                        'trial_expired' => true,
                        'payment_overdue' => true,
                        'days_overdue' => $daysOverdue,
                        'message' => 'Período de teste expirado e há faturas vencidas. Realize o pagamento para continuar.',
                        'type' => 'error',
                        'subscription' => $subscription,
                        'overdue_payment' => $paymentOverdue
                    ];
                } else {
                    // Trial expirado mas SEM faturas vencidas
                    // Verificar se tem faturas futuras (ainda não vencidas)
                    $futurPayment = $db->fetch("
                        SELECT * FROM pagamentos
                        WHERE tenant_id = ? 
                        AND status = 'pendente'
                        AND data_vencimento >= CURRENT_DATE
                        ORDER BY data_vencimento ASC
                        LIMIT 1
                    ", [$tenantId]);
                    
                    if ($futurPayment) {
                        // Tem fatura futura pendente mas não vencida = OK, sem aviso
                        return [
                            'active' => true,
                            'blocked' => false,
                            'trial_expired' => true,
                            'has_future_payment' => true,
                            'message' => null, // Sem mensagem de alerta
                            'type' => 'success',
                            'subscription' => $subscription
                        ];
                    } else {
                        // Sem faturas = tudo OK
                        return [
                            'active' => true,
                            'blocked' => false,
                            'message' => null,
                            'type' => 'success',
                            'subscription' => $subscription
                        ];
                    }
                }
            } else {
                // Trial ainda ativo
                $daysLeft = $now->diff($trialEnd)->days;
                
                // Apenas mostrar aviso se faltam menos de 3 dias
                if ($daysLeft <= 3) {
                    return [
                        'active' => true,
                        'blocked' => false,
                        'in_trial' => true,
                        'trial_days_left' => $daysLeft,
                        'message' => "⏰ Período de teste termina em {$daysLeft} dias! Prepare-se para o primeiro pagamento.",
                        'type' => 'warning',
                        'subscription' => $subscription
                    ];
                } else {
                    // Trial ativo com mais de 3 dias = sem aviso
                    return [
                        'active' => true,
                        'blocked' => false,
                        'in_trial' => true,
                        'trial_days_left' => $daysLeft,
                        'message' => null, // SEM mensagem de alerta
                        'type' => 'success',
                        'subscription' => $subscription
                    ];
                }
            }
        }
        
        // Não está em trial - verificar pagamentos vencidos
        $paymentOverdue = $db->fetch("
            SELECT * FROM pagamentos
            WHERE tenant_id = ? 
            AND status = 'pendente'
            AND data_vencimento < CURRENT_DATE
            ORDER BY data_vencimento ASC
            LIMIT 1
        ", [$tenantId]);
        
        if ($paymentOverdue) {
            // Verificar quantos dias de atraso
            $dueDate = new \DateTime($paymentOverdue['data_vencimento']);
            $now = new \DateTime();
            $daysOverdue = $now->diff($dueDate)->days;
            
            // Se mais de 7 dias de atraso, bloquear
            if ($daysOverdue > 7) {
                return [
                    'active' => false,
                    'blocked' => true,
                    'payment_overdue' => true,
                    'days_overdue' => $daysOverdue,
                    'message' => "Fatura vencida há {$daysOverdue} dias. Realize o pagamento para desbloquear.",
                    'type' => 'error',
                    'subscription' => $subscription,
                    'overdue_payment' => $paymentOverdue
                ];
            } else {
                // Menos de 7 dias - permitir mas avisar
                return [
                    'active' => true,
                    'blocked' => false,
                    'payment_overdue' => true,
                    'days_overdue' => $daysOverdue,
                    'message' => "Você tem uma fatura vencida há {$daysOverdue} dias. Pague para evitar bloqueio.",
                    'type' => 'warning',
                    'subscription' => $subscription,
                    'overdue_payment' => $paymentOverdue
                ];
            }
        }
        
        // Tudo OK
        return [
            'active' => true,
            'blocked' => false,
            'message' => 'Assinatura ativa',
            'type' => 'success',
            'subscription' => $subscription
        ];
    }
    
    /**
     * Bloquear ações críticas se assinatura estiver vencida
     * Retorna true se pode prosseguir, false se bloqueado
     */
    public static function canPerformCriticalAction()
    {
        $status = self::checkSubscriptionStatus();
        
        if ($status['blocked']) {
            error_log("SubscriptionCheck::canPerformCriticalAction - Ação bloqueada: " . $status['message']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Obter mensagem de alerta para exibir no dashboard
     */
    public static function getAlertMessage()
    {
        $status = self::checkSubscriptionStatus();
        
        if ($status['type'] === 'success') {
            return null; // Sem alertas
        }
        
        return [
            'type' => $status['type'],
            'message' => $status['message'],
            'blocked' => $status['blocked'],
            'details' => $status
        ];
    }
}

