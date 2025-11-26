<?php
/**
 * Webhook do Asaas para receber notificações de pagamentos
 * Este arquivo processa eventos do Asaas (pagamentos, assinaturas, etc.)
 */

require_once __DIR__ . '/../system/Database.php';
require_once __DIR__ . '/../mvc/model/Subscription.php';

// Log de entrada
error_log("ASAAS WEBHOOK - Recebido: " . file_get_contents('php://input'));

// Headers esperados do Asaas
header('Content-Type: application/json');

try {
    $db = \System\Database::getInstance();
    
    // Pegar payload do webhook
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Payload inválido']);
        exit;
    }
    
    error_log("ASAAS WEBHOOK - Evento: " . ($data['event'] ?? 'undefined'));
    
    // Processar evento
    $event = $data['event'] ?? null;
    $payment = $data['payment'] ?? null;
    
    if (!$event || !$payment) {
        http_response_code(400);
        echo json_encode(['error' => 'Evento ou pagamento não fornecido']);
        exit;
    }
    
    // Extrair informações do pagamento
    $paymentId = $payment['id'] ?? null;
    $subscriptionId = $payment['subscription'] ?? null;
    $status = $payment['status'] ?? null;
    $value = $payment['value'] ?? 0;
    $dueDate = $payment['dueDate'] ?? null;
    $invoiceUrl = $payment['invoiceUrl'] ?? null;
    $paymentDate = $payment['paymentDate'] ?? null;
    
    error_log("ASAAS WEBHOOK - Payment ID: $paymentId, Subscription: $subscriptionId, Status: $status");
    
    // FIRST: Check if this is a payment for an order (pedido)
    // Look for pedido by asaas_payment_id
    $pedido = $db->fetch(
        "SELECT idpedido, tenant_id, filial_id, valor_total, status_pagamento FROM pedido WHERE asaas_payment_id = ?",
        [$paymentId]
    );
    
    if ($pedido) {
        error_log("ASAAS WEBHOOK - Pedido encontrado: ID={$pedido['idpedido']}, Tenant={$pedido['tenant_id']}, Status atual={$pedido['status_pagamento']}");
        
        // Map Asaas status to internal payment status
        $newStatusPagamento = mapAsaasStatusToPedido($status);
        $valorPago = 0;
        $saldoDevedor = $pedido['valor_total'];
        
        // If payment confirmed/received, mark as paid
        if ($status === 'CONFIRMED' || $status === 'RECEIVED' || $status === 'RECEIVED_IN_CASH') {
            $valorPago = $value;
            $saldoDevedor = max(0, $pedido['valor_total'] - $valorPago);
            $newStatusPagamento = 'quitado';
        } elseif ($status === 'OVERDUE') {
            $newStatusPagamento = 'pendente';
        } elseif ($status === 'REFUNDED' || $status === 'REFUND_REQUESTED') {
            $newStatusPagamento = 'cancelado';
        }
        
        // Update pedido with payment status
        $updateData = [
            'status_pagamento' => $newStatusPagamento,
            'valor_pago' => $valorPago,
            'saldo_devedor' => $saldoDevedor,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // If payment was confirmed, update order status to Finalizado
        if ($newStatusPagamento === 'quitado') {
            $updateData['status'] = 'Finalizado';
        }
        
        $db->update('pedido', $updateData, 'idpedido = ?', [$pedido['idpedido']]);
        
        error_log("ASAAS WEBHOOK - Pedido atualizado: Status={$newStatusPagamento}, Valor Pago={$valorPago}, Saldo Devedor={$saldoDevedor}");
        
        // Continue processing (don't return yet, in case it's also a subscription payment)
    }
    
    // THEN: Check if this is a subscription payment
    if ($subscriptionId) {
        $assinatura = $db->fetch(
            "SELECT * FROM assinaturas WHERE asaas_subscription_id = ?",
            [$subscriptionId]
        );
        
        if ($assinatura) {
            error_log("ASAAS WEBHOOK - Assinatura encontrada: ID={$assinatura['id']}, Tenant={$assinatura['tenant_id']}");
            
            // Verificar se o pagamento já existe
            $existingPayment = $db->fetch(
                "SELECT id FROM pagamentos WHERE gateway_payment_id = ?",
                [$paymentId]
            );
            
            if ($existingPayment) {
                // Atualizar pagamento existente
                error_log("ASAAS WEBHOOK - Atualizando pagamento existente: {$existingPayment['id']}");
                
                $updateData = [
                    'status' => mapAsaasStatus($status),
                    'gateway_response' => json_encode($payment),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($paymentDate) {
                    $updateData['data_pagamento'] = $paymentDate;
                }
                
                $db->update('pagamentos', $updateData, 'id = ?', [$existingPayment['id']]);
            } else {
                // Criar novo registro de pagamento
                error_log("ASAAS WEBHOOK - Criando novo registro de pagamento");
                
                // Buscar primeira filial do tenant
                $filial = $db->fetch(
                    "SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1",
                    [$assinatura['tenant_id']]
                );
                
                $paymentData = [
                    'tenant_id' => $assinatura['tenant_id'],
                    'filial_id' => $filial['id'] ?? null,
                    'assinatura_id' => $assinatura['id'],
                    'valor' => $value,
                    'valor_pago' => ($status === 'CONFIRMED' || $status === 'RECEIVED') ? $value : 0,
                    'forma_pagamento' => 'pix',
                    'status' => mapAsaasStatus($status),
                    'data_vencimento' => $dueDate,
                    'metodo_pagamento' => $payment['billingType'] ?? 'PIX',
                    'gateway_payment_id' => $paymentId,
                    'gateway_customer_id' => $payment['customer'] ?? null,
                    'gateway_response' => json_encode($payment),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if ($paymentDate) {
                    $paymentData['data_pagamento'] = $paymentDate;
                }
                
                $db->insert('pagamentos', $paymentData);
            }
            
            // Atualizar próxima data de cobrança se o pagamento foi confirmado
            if ($status === 'CONFIRMED' || $status === 'RECEIVED') {
                $periodicidade = $assinatura['periodicidade'] ?? 'mensal';
                $nextDate = calculateNextDueDate($periodicidade);
                
                $db->update('assinaturas', [
                    'data_proxima_cobranca' => $nextDate,
                    'status' => 'ativa',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$assinatura['id']]);
                
                error_log("ASAAS WEBHOOK - Próxima cobrança atualizada para: $nextDate");
            }
            
            // Se o pagamento falhou ou venceu
            if ($status === 'OVERDUE') {
                $db->update('assinaturas', [
                    'status' => 'suspensa',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$assinatura['id']]);
                
                error_log("ASAAS WEBHOOK - Assinatura suspensa por falta de pagamento");
            }
            
        } else {
            error_log("ASAAS WEBHOOK - AVISO: Assinatura não encontrada no banco para subscription_id: $subscriptionId");
        }
    }
    
    // Responder sucesso
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook processado']);
    
} catch (Exception $e) {
    error_log("ASAAS WEBHOOK - ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Mapear status do Asaas para status interno (assinaturas)
 */
function mapAsaasStatus($asaasStatus) {
    $statusMap = [
        'PENDING' => 'pendente',
        'CONFIRMED' => 'pago',
        'RECEIVED' => 'pago',
        'OVERDUE' => 'pendente',
        'REFUNDED' => 'cancelado',
        'RECEIVED_IN_CASH' => 'pago',
        'REFUND_REQUESTED' => 'cancelado',
        'CHARGEBACK_REQUESTED' => 'cancelado',
        'CHARGEBACK_DISPUTE' => 'cancelado',
        'AWAITING_CHARGEBACK_REVERSAL' => 'cancelado',
        'DUNNING_REQUESTED' => 'pendente',
        'DUNNING_RECEIVED' => 'pago',
        'AWAITING_RISK_ANALYSIS' => 'pendente'
    ];
    
    return $statusMap[$asaasStatus] ?? 'pendente';
}

/**
 * Mapear status do Asaas para status de pagamento do pedido
 */
function mapAsaasStatusToPedido($asaasStatus) {
    $statusMap = [
        'PENDING' => 'pendente',
        'CONFIRMED' => 'quitado',
        'RECEIVED' => 'quitado',
        'OVERDUE' => 'pendente',
        'REFUNDED' => 'cancelado',
        'RECEIVED_IN_CASH' => 'quitado',
        'REFUND_REQUESTED' => 'cancelado',
        'CHARGEBACK_REQUESTED' => 'cancelado',
        'CHARGEBACK_DISPUTE' => 'cancelado',
        'AWAITING_CHARGEBACK_REVERSAL' => 'cancelado',
        'DUNNING_REQUESTED' => 'pendente',
        'DUNNING_RECEIVED' => 'quitado',
        'AWAITING_RISK_ANALYSIS' => 'pendente'
    ];
    
    return $statusMap[$asaasStatus] ?? 'pendente';
}

/**
 * Calcular próxima data de cobrança
 */
function calculateNextDueDate($periodicidade) {
    $interval = [
        'mensal' => '+1 month',
        'semestral' => '+6 months',
        'anual' => '+1 year'
    ];
    
    $period = $interval[$periodicidade] ?? '+1 month';
    return date('Y-m-d', strtotime($period));
}
