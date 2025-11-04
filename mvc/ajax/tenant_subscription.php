<?php
/**
 * AJAX Handler para mudanças de plano do tenant
 */

require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/Plan.php';
require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/AsaasPayment.php';

header('Content-Type: application/json');

try {
    $session = \System\Session::getInstance();
    $db = \System\Database::getInstance();
    
    // Verificar autenticação
    if (!$session->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
    
    $tenantId = $session->getTenantId();
    
    if (!$tenantId) {
        http_response_code(400);
        echo json_encode(['error' => 'Tenant não identificado']);
        exit;
    }
    
    // Detectar ação
    $action = $_GET['action'] ?? 'mudarPlano';
    
    if ($action === 'syncAsaasInvoices') {
        syncAsaasInvoices($tenantId, $db);
        exit;
    }
    
    // Processar mudança de plano
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['plano_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Plano não especificado']);
        exit;
    }
    
    $subscriptionModel = new Subscription();
    $planModel = new Plan();
    $tenantModel = new Tenant();
    $asaasPayment = new AsaasPayment();
    
    // Buscar assinatura atual
    $currentSubscription = $subscriptionModel->getByTenant($tenantId);
    
    if (!$currentSubscription) {
        http_response_code(404);
        echo json_encode(['error' => 'Assinatura não encontrada']);
        exit;
    }
    
    // Buscar novo plano
    $newPlan = $planModel->getById($data['plano_id']);
    
    if (!$newPlan) {
        http_response_code(404);
        echo json_encode(['error' => 'Plano não encontrado']);
        exit;
    }
    
    $periodicidade = $data['periodicidade'] ?? 'mensal';
    
    // Calcular valor com desconto
    // IMPORTANTE: Asaas cobra o valor informado na periodicidade escolhida
    // Se cycle=SEMIANNUALLY e value=269.1, cobra 269.1 a cada 6 meses
    $valorBase = $newPlan['preco_mensal'];
    $valorFinal = $valorBase;
    
    if ($periodicidade === 'semestral') {
        $valorFinal = $valorBase * 6 * 0.9; // R$ 49.90 * 6 * 0.9 = R$ 269.46 (cobrado a cada 6 meses)
    } elseif ($periodicidade === 'anual') {
        $valorFinal = $valorBase * 12 * 0.8; // R$ 49.90 * 12 * 0.8 = R$ 479.04 (cobrado a cada 12 meses)
    }
    
    error_log("tenant_subscription.php - Cálculo de valor: Base=$valorBase, Periodicidade=$periodicidade, Valor Final=$valorFinal");
    
    // Atualizar tenant
    $tenantModel->update($tenantId, ['plano_id' => $data['plano_id']]);
    
    // Atualizar assinatura local
    $subscriptionModel->update($currentSubscription['id'], [
        'plano_id' => $data['plano_id'],
        'valor' => $valorFinal,
        'periodicidade' => $periodicidade
    ]);
    
    // Atualizar no Asaas se for assinatura recorrente
    if (!empty($currentSubscription['asaas_subscription_id'])) {
        if (str_starts_with($currentSubscription['asaas_subscription_id'], 'sub_')) {
            // É assinatura recorrente
            $cycleMap = [
                'mensal' => 'MONTHLY',
                'semestral' => 'SEMIANNUALLY',
                'anual' => 'YEARLY'
            ];
            
            $newCycle = $cycleMap[$periodicidade];
            $oldCycle = $cycleMap[$currentSubscription['periodicidade']] ?? null;
            
            // Verificar se mudou a periodicidade
            if ($newCycle !== $oldCycle && $oldCycle !== null) {
                // MUDOU PERIODICIDADE - Precisa cancelar e criar nova assinatura
                error_log("tenant_subscription.php - Mudança de periodicidade detectada ($oldCycle -> $newCycle). Cancelando assinatura antiga e criando nova.");
                
                // 1. Cancelar assinatura antiga
                $cancelResult = $asaasPayment->cancelSubscription($currentSubscription['asaas_subscription_id']);
                
                if ($cancelResult['success']) {
                    error_log("tenant_subscription.php - Assinatura antiga cancelada com sucesso");
                    
                    // 2. Buscar dados do tenant para criar nova assinatura
                    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
                    
                    // 3. Criar customer no Asaas (formato compatível)
                    $customerData = [
                        'id' => $tenant['id'],
                        'nome' => $tenant['nome'],
                        'email' => $tenant['email'] ?? 'contato@' . $tenant['subdomain'] . '.com',
                        'telefone' => $tenant['telefone'] ?? '',
                        'cnpj' => $tenant['cnpj'] ?? ''
                    ];
                    
                    $asaasCustomer = $asaasPayment->createCustomer($customerData);
                    
                    if ($asaasCustomer['success']) {
                        // 4. Criar nova assinatura
                        $subscriptionData = [
                            'asaas_customer_id' => $asaasCustomer['data']['id'],
                            'valor' => $valorFinal,
                            'descricao' => 'Assinatura ' . $newPlan['nome'] . ' - ' . $tenant['nome'],
                            'subscription_id' => $currentSubscription['id'],
                            'cycle' => $newCycle,
                            'next_due_date' => date('Y-m-d', strtotime('+7 days'))
                        ];
                        
                        $newSubscription = $asaasPayment->createSubscription($subscriptionData);
                        
                        if ($newSubscription['success']) {
                            $newAsaasSubId = $newSubscription['data']['id'];
                            error_log("tenant_subscription.php - Nova assinatura criada no Asaas: $newAsaasSubId");
                            
                            // 5. Atualizar assinatura local com novo ID
                            $subscriptionModel->update($currentSubscription['id'], [
                                'asaas_subscription_id' => $newAsaasSubId
                            ]);
                            
                            // 6. Verificar se já existe fatura pendente para esta assinatura
                            $existingPayment = $db->fetch(
                                "SELECT id FROM pagamentos_assinaturas 
                                 WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
                                 ORDER BY created_at DESC LIMIT 1",
                                [$tenantId, $currentSubscription['id']]
                            );
                            
                            if (!$existingPayment) {
                                // Criar fatura no banco local APENAS se não existir
                                $payment_record = [
                                    'tenant_id' => $tenantId,
                                    'assinatura_id' => $currentSubscription['id'],
                                    'valor' => $valorFinal,
                                    'status' => 'pendente',
                                    'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                                    'metodo_pagamento' => 'pix',
                                    'gateway_payment_id' => $newAsaasSubId,
                                    'gateway_response' => json_encode($newSubscription['data']),
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                
                                $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
                                error_log("tenant_subscription.php - Fatura criada no banco local: ID $payment_id");
                            } else {
                                error_log("tenant_subscription.php - Fatura pendente já existe (ID {$existingPayment['id']}), não criando duplicada");
                            }
                            
                            error_log("tenant_subscription.php - Assinatura recriada com sucesso no Asaas");
                        } else {
                            error_log("tenant_subscription.php - ERRO ao criar nova assinatura: " . json_encode($newSubscription));
                        }
                    } else {
                        error_log("tenant_subscription.php - ERRO ao criar customer: " . json_encode($asaasCustomer));
                    }
                } else {
                    error_log("tenant_subscription.php - ERRO ao cancelar assinatura antiga: " . json_encode($cancelResult));
                }
            } else {
                // SEM MUDANÇA DE PERIODICIDADE - Apenas atualizar valor
                error_log("tenant_subscription.php - Apenas mudança de valor/plano. Atualizando assinatura existente.");
                
                $asaasResult = $asaasPayment->updateSubscription($currentSubscription['asaas_subscription_id'], [
                    'valor' => $valorFinal
                ]);
                
                if ($asaasResult['success']) {
                    error_log("tenant_subscription.php - Assinatura atualizada no Asaas com sucesso");
                } else {
                    error_log("tenant_subscription.php - Erro ao atualizar no Asaas: " . json_encode($asaasResult));
                }
                
                // CRIAR FATURA se não existir (mesmo sem mudança de periodicidade)
                $existingPayment = $db->fetch(
                    "SELECT id FROM pagamentos_assinaturas 
                     WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
                     ORDER BY created_at DESC LIMIT 1",
                    [$tenantId, $currentSubscription['id']]
                );
                
                if (!$existingPayment) {
                    $payment_record = [
                        'tenant_id' => $tenantId,
                        'assinatura_id' => $currentSubscription['id'],
                        'valor' => $valorFinal,
                        'status' => 'pendente',
                        'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                        'metodo_pagamento' => 'pix',
                        'gateway_payment_id' => $currentSubscription['asaas_subscription_id'],
                        'gateway_response' => json_encode(['message' => 'Fatura criada por mudança de plano']),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
                    error_log("tenant_subscription.php - Fatura criada (mudança de plano sem mudança de periodicidade): ID $payment_id");
                }
            }
        } else {
            // É pagamento único antigo - apenas loga, não tenta atualizar
            error_log("tenant_subscription.php - Assinatura antiga (payment ID: {$currentSubscription['asaas_subscription_id']}). Atualização apenas local. Para refletir no Asaas, cadastre um novo estabelecimento.");
        }
    } else {
        error_log("tenant_subscription.php - Sem assinatura no Asaas. Atualização apenas local.");
    }
    
    // CRIAR FATURA se não existir (mesmo sem Asaas)
    $existingPayment = $db->fetch(
        "SELECT id FROM pagamentos_assinaturas 
         WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
         ORDER BY created_at DESC LIMIT 1",
        [$tenantId, $currentSubscription['id']]
    );
    
    if (!$existingPayment) {
        $payment_record = [
            'tenant_id' => $tenantId,
            'assinatura_id' => $currentSubscription['id'],
            'valor' => $valorFinal,
            'status' => 'pendente',
            'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
            'metodo_pagamento' => 'pix',
            'gateway_payment_id' => null,
            'gateway_response' => json_encode(['message' => 'Fatura criada localmente (sem integração Asaas)']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
        error_log("tenant_subscription.php - Fatura criada (modo local, sem Asaas): ID $payment_id");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Plano alterado com sucesso! As próximas cobranças refletirão a mudança.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Sincronizar faturas do Asaas
 */
function syncAsaasInvoices($tenantId, $db) {
    try {
        $subscriptionModel = new Subscription();
        $asaasPayment = new AsaasPayment();
        
        // Buscar assinatura
        $subscription = $subscriptionModel->getByTenant($tenantId);
        
        if (!$subscription) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma assinatura encontrada para este estabelecimento']);
            return;
        }
        
        if (empty($subscription['asaas_subscription_id'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Este estabelecimento não está integrado com o Asaas. Para ativar a integração, cadastre um novo estabelecimento via página de registro.'
            ]);
            return;
        }
        
        // Verificar se é assinatura recorrente
        if (!str_starts_with($subscription['asaas_subscription_id'], 'sub_')) {
            echo json_encode([
                'success' => false, 
                'message' => 'Esta é uma assinatura antiga (pagamento único). Apenas assinaturas recorrentes podem ser sincronizadas. Cadastre um novo estabelecimento para ter assinatura recorrente.'
            ]);
            return;
        }
        
        // Buscar pagamentos da assinatura no Asaas
        $paymentsResult = $asaasPayment->getSubscriptionPayments($subscription['asaas_subscription_id']);
        
        if (!$paymentsResult['success']) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar faturas do Asaas']);
            return;
        }
        
        $paymentsData = $paymentsResult['data'];
        
        // Normalizar estrutura de resposta
        if (isset($paymentsData['data']) && is_array($paymentsData['data'])) {
            $payments = $paymentsData['data'];
        } elseif (is_array($paymentsData)) {
            $payments = $paymentsData;
        } else {
            $payments = [$paymentsData];
        }
        
        $newInvoices = 0;
        $updatedInvoices = 0;
        
        // Buscar primeira filial
        $filial = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
        
        foreach ($payments as $payment) {
            $paymentId = $payment['id'] ?? null;
            
            if (!$paymentId) continue;
            
            // Verificar se já existe (TABELA CORRETA: pagamentos_assinaturas)
            $existing = $db->fetch("SELECT id FROM pagamentos_assinaturas WHERE gateway_payment_id = ?", [$paymentId]);
            
            $paymentData = [
                'status' => mapAsaasStatusLocal($payment['status'] ?? 'PENDING'),
                'gateway_response' => json_encode($payment),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($payment['paymentDate'])) {
                $paymentData['data_pagamento'] = $payment['paymentDate'];
            }
            
            if ($existing) {
                // Atualizar
                $db->update('pagamentos_assinaturas', $paymentData, 'id = ?', [$existing['id']]);
                $updatedInvoices++;
            } else {
                // Criar novo
                $paymentData = array_merge($paymentData, [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filial['id'] ?? null,
                    'assinatura_id' => $subscription['id'],
                    'valor' => $payment['value'] ?? 0,
                    'valor_pago' => ($payment['status'] === 'CONFIRMED' || $payment['status'] === 'RECEIVED') ? ($payment['value'] ?? 0) : 0,
                    'forma_pagamento' => 'pix',
                    'data_vencimento' => $payment['dueDate'] ?? null,
                    'metodo_pagamento' => $payment['billingType'] ?? 'PIX',
                    'gateway_payment_id' => $paymentId,
                    'gateway_customer_id' => $payment['customer'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $db->insert('pagamentos_assinaturas', $paymentData);
                $newInvoices++;
            }
        }
        
        $message = [];
        if ($newInvoices > 0) $message[] = "$newInvoices nova(s) fatura(s)";
        if ($updatedInvoices > 0) $message[] = "$updatedInvoices fatura(s) atualizada(s)";
        
        if (empty($message)) {
            echo json_encode(['success' => true, 'message' => 'Todas as faturas já estão sincronizadas']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Sincronizado: ' . implode(', ', $message)]);
        }
        
    } catch (Exception $e) {
        error_log("syncAsaasInvoices - Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
}

/**
 * Mapear status do Asaas
 */
function mapAsaasStatusLocal($asaasStatus) {
    $statusMap = [
        'PENDING' => 'pendente',
        'CONFIRMED' => 'pago',
        'RECEIVED' => 'pago',
        'OVERDUE' => 'pendente',
        'REFUNDED' => 'cancelado',
        'RECEIVED_IN_CASH' => 'pago'
    ];
    
    return $statusMap[$asaasStatus] ?? 'pendente';
}

