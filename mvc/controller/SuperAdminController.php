<?php
/**
 * Controller do SuperAdministrador
 * Gerencia todas as operações administrativas do sistema SaaS
 */

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Plan.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/Payment.php';
require_once __DIR__ . '/../model/AsaasPayment.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';

class SuperAdminController {
    private $tenantModel;
    private $planModel;
    private $subscriptionModel;
    private $paymentModel;
    private $asaasPayment;
    
    public function __construct() {
        // Verificar se é superadmin
        if (!isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        $this->tenantModel = new Tenant();
        $this->planModel = new Plan();
        $this->subscriptionModel = new Subscription();
        $this->paymentModel = new Payment();
        $this->asaasPayment = new AsaasPayment();
    }
    
    /**
     * Obter estatísticas do dashboard
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Executar queries individualmente para evitar conflitos
            try {
                $stats['tenants'] = $this->tenantModel->getStats();
            } catch (Exception $e) {
                $stats['tenants'] = ['error' => $e->getMessage()];
            }
            
            try {
                $stats['subscriptions'] = $this->subscriptionModel->getStats();
            } catch (Exception $e) {
                $stats['subscriptions'] = ['error' => $e->getMessage()];
            }
            
            try {
                $stats['payments'] = [
                    'hoje' => $this->paymentModel->getStats('hoje'),
                    'semana' => $this->paymentModel->getStats('semana'),
                    'mes' => $this->paymentModel->getStats('mes')
                ];
            } catch (Exception $e) {
                $stats['payments'] = ['error' => $e->getMessage()];
            }
            
            header('Content-Type: application/json');
            echo json_encode($stats);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Listar tenants
     */
    public function listTenants() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'plano_id' => $_GET['plano_id'] ?? null,
            'search' => $_GET['search'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $tenants = $this->tenantModel->getAll($filters);
        
        header('Content-Type: application/json');
        echo json_encode($tenants);
    }
    
    /**
     * Criar tenant
     */
    public function createTenant() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar dados
        if (empty($data['nome']) || empty($data['subdomain']) || empty($data['plano_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            return;
        }
        
        // Verificar se subdomain está disponível
        if (!$this->tenantModel->isSubdomainAvailable($data['subdomain'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain já está em uso']);
            return;
        }
        
        $tenant_id = $this->tenantModel->create($data);
        
        if ($tenant_id) {
            // Criar assinatura
            $plano = $this->planModel->getById($data['plano_id']);
            $subscription_data = [
                'tenant_id' => $tenant_id,
                'plano_id' => $data['plano_id'],
                'valor' => $plano['preco_mensal'],
                'status' => $data['trial'] ?? false ? 'trial' : 'ativa'
            ];
            
            $this->subscriptionModel->create($subscription_data);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'tenant_id' => $tenant_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar tenant']);
        }
    }
    
    /**
     * Buscar tenant por ID
     */
    public function getTenant() {
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $tenant = $this->tenantModel->getById($id);
        
        if ($tenant) {
            header('Content-Type: application/json');
            echo json_encode($tenant);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Tenant não encontrado']);
        }
    }
    
    /**
     * Buscar assinatura do tenant
     */
    public function getTenantSubscription() {
        $tenantId = $_GET['tenant_id'] ?? null;
        
        if (!$tenantId) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID não fornecido']);
            return;
        }
        
        $subscription = $this->subscriptionModel->getByTenant($tenantId);
        
        header('Content-Type: application/json');
        echo json_encode($subscription);
    }
    
    /**
     * Atualizar tenant
     */
    public function updateTenant() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        // Atualizar dados do tenant
        $result = $this->tenantModel->update($data['id'], $data);
        
        // Se mudou a periodicidade ou plano, atualizar a assinatura também
        error_log("SuperAdminController::updateTenant - Verificando se precisa atualizar assinatura. Periodicidade: " . ($data['periodicidade'] ?? 'não definida') . ", Plano ID: " . ($data['plano_id'] ?? 'não definido'));
        
        if (isset($data['periodicidade']) || isset($data['plano_id'])) {
            $subscription = $this->subscriptionModel->getByTenant($data['id']);
            error_log("SuperAdminController::updateTenant - Assinatura encontrada: " . ($subscription ? 'SIM (ID: ' . $subscription['id'] . ')' : 'NÃO'));
            
            if ($subscription) {
                $updateSubscriptionData = [];
                
                if (isset($data['periodicidade'])) {
                    $updateSubscriptionData['periodicidade'] = $data['periodicidade'];
                }
                
                // Se mudou o plano, atualizar o valor também
                if (isset($data['plano_id'])) {
                    $plano = $this->planModel->getById($data['plano_id']);
                    if ($plano) {
                        $updateSubscriptionData['plano_id'] = $data['plano_id'];
                        $updateSubscriptionData['valor'] = $plano['preco_mensal'];
                    }
                }
                
                // Atualizar localmente
                $this->subscriptionModel->update($subscription['id'], $updateSubscriptionData);
                
                // Atualizar no Asaas também se tiver assinatura ativa
                if (!empty($subscription['asaas_subscription_id'])) {
                    if (str_starts_with($subscription['asaas_subscription_id'], 'sub_')) {
                        // É assinatura recorrente
                        $cycleMap = [
                            'mensal' => 'MONTHLY',
                            'semestral' => 'SEMIANNUALLY',
                            'anual' => 'YEARLY'
                        ];
                        
                        $newCycle = isset($updateSubscriptionData['periodicidade']) ? $cycleMap[$updateSubscriptionData['periodicidade']] : null;
                        $oldCycle = isset($subscription['periodicidade']) ? $cycleMap[$subscription['periodicidade']] : null;
                        
                        // Verificar se mudou a periodicidade
                        if ($newCycle && $oldCycle && $newCycle !== $oldCycle) {
                            // MUDOU PERIODICIDADE - Precisa cancelar e criar nova assinatura
                            error_log("SuperAdminController::updateTenant - Mudança de periodicidade detectada ($oldCycle -> $newCycle). Cancelando assinatura antiga e criando nova.");
                            
                            // 1. Cancelar assinatura antiga
                            $cancelResult = $this->asaasPayment->cancelSubscription($subscription['asaas_subscription_id']);
                            
                            if ($cancelResult['success']) {
                                error_log("SuperAdminController::updateTenant - Assinatura antiga cancelada com sucesso");
                                
                                // 2. Buscar dados do tenant
                                $db = \System\Database::getInstance();
                                $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$data['id']]);
                                
                                // 3. Buscar plano atualizado
                                $plano = $this->planModel->getById($updateSubscriptionData['plano_id'] ?? $subscription['plano_id']);
                                
                                // 4. Criar customer no Asaas
                                $customerData = [
                                    'id' => $tenant['id'],
                                    'nome' => $tenant['nome'],
                                    'email' => $tenant['email'] ?? 'contato@' . $tenant['subdomain'] . '.com',
                                    'telefone' => $tenant['telefone'] ?? '',
                                    'cnpj' => $tenant['cnpj'] ?? ''
                                ];
                                
                                $asaasCustomer = $this->asaasPayment->createCustomer($customerData);
                                
                                if ($asaasCustomer['success']) {
                                    // 5. Criar nova assinatura
                                    $subscriptionData = [
                                        'asaas_customer_id' => $asaasCustomer['data']['id'],
                                        'valor' => $updateSubscriptionData['valor'] ?? $subscription['valor'],
                                        'descricao' => 'Assinatura ' . $plano['nome'] . ' - ' . $tenant['nome'],
                                        'subscription_id' => $subscription['id'],
                                        'cycle' => $newCycle,
                                        'next_due_date' => date('Y-m-d', strtotime('+7 days'))
                                    ];
                                    
                                    $newSubscription = $this->asaasPayment->createSubscription($subscriptionData);
                                    
                                    if ($newSubscription['success']) {
                                        $newAsaasSubId = $newSubscription['data']['id'];
                                        error_log("SuperAdminController::updateTenant - Nova assinatura criada no Asaas: $newAsaasSubId");
                                        
                                        // 6. Atualizar assinatura local com novo ID
                                        $this->subscriptionModel->update($subscription['id'], [
                                            'asaas_subscription_id' => $newAsaasSubId
                                        ]);
                                        
                                        // 7. Verificar se já existe fatura pendente para esta assinatura
                                        $existingPayment = $db->fetch(
                                            "SELECT id FROM pagamentos_assinaturas 
                                             WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
                                             ORDER BY created_at DESC LIMIT 1",
                                            [$data['id'], $subscription['id']]
                                        );
                                        
                                        if (!$existingPayment) {
                                            // Criar fatura no banco local APENAS se não existir
                                            $valorFatura = $updateSubscriptionData['valor'] ?? $subscription['valor'];
                                            $payment_record = [
                                                'tenant_id' => $data['id'],
                                                'assinatura_id' => $subscription['id'],
                                                'valor' => $valorFatura,
                                                'status' => 'pendente',
                                                'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                                                'metodo_pagamento' => 'pix',
                                                'gateway_payment_id' => $newAsaasSubId,
                                                'gateway_response' => json_encode($newSubscription['data']),
                                                'created_at' => date('Y-m-d H:i:s')
                                            ];
                                            
                                            $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
                                            error_log("SuperAdminController::updateTenant - Fatura criada no banco local: ID $payment_id");
                                        } else {
                                            error_log("SuperAdminController::updateTenant - Fatura pendente já existe (ID {$existingPayment['id']}), não criando duplicada");
                                        }
                                        
                                        error_log("SuperAdminController::updateTenant - Assinatura recriada com sucesso no Asaas");
                                    } else {
                                        error_log("SuperAdminController::updateTenant - ERRO ao criar nova assinatura: " . json_encode($newSubscription));
                                    }
                                } else {
                                    error_log("SuperAdminController::updateTenant - ERRO ao criar customer: " . json_encode($asaasCustomer));
                                }
                            } else {
                                error_log("SuperAdminController::updateTenant - ERRO ao cancelar assinatura antiga: " . json_encode($cancelResult));
                            }
                        } else {
                            // SEM MUDANÇA DE PERIODICIDADE - Apenas atualizar valor
                            error_log("SuperAdminController::updateTenant - Apenas mudança de valor/plano. Atualizando assinatura existente.");
                            
                            $asaasUpdateData = [];
                            if (isset($updateSubscriptionData['valor'])) {
                                $asaasUpdateData['valor'] = $updateSubscriptionData['valor'];
                            }
                            
                            if (!empty($asaasUpdateData)) {
                                $asaasResult = $this->asaasPayment->updateSubscription($subscription['asaas_subscription_id'], $asaasUpdateData);
                                
                                if ($asaasResult['success']) {
                                    error_log("SuperAdminController::updateTenant - Assinatura atualizada no Asaas com sucesso");
                                } else {
                                    error_log("SuperAdminController::updateTenant - Erro ao atualizar no Asaas: " . json_encode($asaasResult));
                                }
                            }
                            
                            // CRIAR FATURA se não existir (mesmo sem mudança de periodicidade)
                            $existingPayment = $db->fetch(
                                "SELECT id FROM pagamentos_assinaturas 
                                 WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
                                 ORDER BY created_at DESC LIMIT 1",
                                [$data['id'], $subscription['id']]
                            );
                            
                            if (!$existingPayment) {
                                $valorFatura = $updateSubscriptionData['valor'] ?? $subscription['valor'];
                                $payment_record = [
                                    'tenant_id' => $data['id'],
                                    'assinatura_id' => $subscription['id'],
                                    'valor' => $valorFatura,
                                    'status' => 'pendente',
                                    'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                                    'metodo_pagamento' => 'pix',
                                    'gateway_payment_id' => $subscription['asaas_subscription_id'],
                                    'gateway_response' => json_encode(['message' => 'Fatura criada por mudança de plano (SuperAdmin)']),
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                
                                $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
                                error_log("SuperAdminController::updateTenant - Fatura criada (mudança sem periodicidade): ID $payment_id");
                            }
                        }
                    } else {
                        // É pagamento único - não pode editar, avisar
                        error_log("SuperAdminController::updateTenant - Assinatura antiga (payment ID: {$subscription['asaas_subscription_id']}). Não é possível editar no Asaas. Crie uma nova assinatura recorrente.");
                    }
                } else {
                    // Sem assinatura no Asaas - criar fatura local
                    error_log("SuperAdminController::updateTenant - Sem assinatura no Asaas. Criando fatura local.");
                    
                    $existingPayment = $db->fetch(
                        "SELECT id FROM pagamentos_assinaturas 
                         WHERE tenant_id = ? AND assinatura_id = ? AND status = 'pendente' 
                         ORDER BY created_at DESC LIMIT 1",
                        [$data['id'], $subscription['id']]
                    );
                    
                    if (!$existingPayment) {
                        $valorFatura = $updateSubscriptionData['valor'] ?? $subscription['valor'];
                        $payment_record = [
                            'tenant_id' => $data['id'],
                            'assinatura_id' => $subscription['id'],
                            'valor' => $valorFatura,
                            'status' => 'pendente',
                            'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                            'metodo_pagamento' => 'pix',
                            'gateway_payment_id' => null,
                            'gateway_response' => json_encode(['message' => 'Fatura criada localmente (sem integração Asaas - SuperAdmin)']),
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $payment_id = $db->insert('pagamentos_assinaturas', $payment_record);
                        error_log("SuperAdminController::updateTenant - Fatura criada (modo local): ID $payment_id");
                    }
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Excluir tenant
     */
    public function deleteTenant() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['tenant_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID não fornecido']);
            return;
        }
        
        try {
            $db = \System\Database::getInstance();
            
            // Deletar em cascata
            $db->delete('tenants', 'id = ?', [$data['tenant_id']]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Estabelecimento excluído com sucesso']);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Suspender/Reativar tenant
     */
    public function toggleTenantStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['tenant_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID não fornecido']);
            return;
        }
        
        $tenant = $this->tenantModel->getById($data['tenant_id']);
        $new_status = $tenant['status'] == 'ativo' ? 'suspenso' : 'ativo';
        
        $result = $this->tenantModel->update($data['tenant_id'], ['status' => $new_status]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result, 'new_status' => $new_status]);
    }
    
    /**
     * Listar planos
     */
    public function listPlans() {
        $plans = $this->planModel->getAll();
        
        header('Content-Type: application/json');
        echo json_encode($plans);
    }
    
    /**
     * Buscar plano por ID
     */
    public function getPlan() {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do plano é obrigatório']);
            return;
        }
        
        $plan = $this->planModel->getById($id);
        
        if (!$plan) {
            http_response_code(404);
            echo json_encode(['error' => 'Plano não encontrado']);
            return;
        }
        
        header('Content-Type: application/json');
        echo json_encode($plan);
    }
    
    /**
     * Criar plano
     */
    public function createPlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $plan_id = $this->planModel->create($data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$plan_id, 'plan_id' => $plan_id]);
    }
    
    /**
     * Atualizar plano
     */
    public function updatePlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->planModel->update($data['id'], $data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Deletar plano
     */
    public function deletePlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->planModel->delete($id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Buscar assinatura por ID
     */
    public function getSubscription() {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da assinatura é obrigatório']);
            return;
        }

        $subscription = $this->subscriptionModel->getById($id);

        if (!$subscription) {
            http_response_code(404);
            echo json_encode(['error' => 'Assinatura não encontrada']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($subscription);
    }
    
    /**
     * Atualizar assinatura
     */
    public function updateSubscription() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->subscriptionModel->update($data['id'], $data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Deletar assinatura
     */
    public function deleteSubscription() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->subscriptionModel->delete($id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Listar pagamentos
     */
    public function listPayments() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'tenant_id' => $_GET['tenant_id'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $payments = $this->paymentModel->getAll($filters);
        
        header('Content-Type: application/json');
        echo json_encode($payments);
    }
    
    /**
     * Marcar pagamento como pago manualmente
     */
    public function markPaymentAsPaid() {
        // Garantir que não há output antes do JSON
        ob_clean();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        error_log("SuperAdminController::markPaymentAsPaid - Dados recebidos: " . json_encode($data));
        
        if (empty($data['payment_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID não fornecido']);
            exit;
        }
        
        $db = \System\Database::getInstance();
        
        // Buscar pagamento no banco local
        $payment = $db->fetch("SELECT * FROM pagamentos_assinaturas WHERE id = ?", [$data['payment_id']]);
        
        if (!$payment) {
            error_log("SuperAdminController::markPaymentAsPaid - Pagamento não encontrado: {$data['payment_id']}");
            http_response_code(404);
            echo json_encode(['error' => 'Pagamento não encontrado']);
            exit;
        }
        
        error_log("SuperAdminController::markPaymentAsPaid - Pagamento encontrado: ID {$payment['id']}, Status: {$payment['status']}");
        
        try {
            // IMPORTANTE: Não mexer no Asaas ao quitar manualmente
            // Motivo: O Asaas continua gerando cobranças recorrentes mesmo se deletarmos uma
            // Se o cliente pagou FORA do sistema (dinheiro, TED, etc), apenas marcamos como pago localmente
            // A cobrança no Asaas pode ser ignorada ou deletada manualmente pelo admin se necessário
            
            error_log("SuperAdminController::markPaymentAsPaid - Quitando pagamento ID {$data['payment_id']} localmente (sem alterar Asaas)");
            
            // Log do gateway_payment_id para referência
            if (!empty($payment['gateway_payment_id'])) {
                error_log("SuperAdminController::markPaymentAsPaid - Gateway Payment ID no Asaas: {$payment['gateway_payment_id']} (fica ativo no Asaas, mas ignorado localmente)");
            }
            
            // Atualizar no banco local
            error_log("SuperAdminController::markPaymentAsPaid - Chamando paymentModel->markAsPaid({$data['payment_id']}, 'manual')");
            
            $result = $this->paymentModel->markAsPaid($data['payment_id'], 'manual');
            
            error_log("SuperAdminController::markPaymentAsPaid - Resultado markAsPaid: " . ($result ? 'TRUE' : 'FALSE'));
            
            if (!$result) {
                throw new \Exception('Erro ao atualizar status do pagamento no banco de dados');
            }
            
            // Atualizar status da assinatura para 'ativa' se estava suspensa
            if ($payment['assinatura_id']) {
                error_log("SuperAdminController::markPaymentAsPaid - Reativando assinatura {$payment['assinatura_id']}");
                
                $db->update('assinaturas', [
                    'status' => 'ativa',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$payment['assinatura_id']]);
                
                // Atualizar status do tenant para 'ativo'
                if ($payment['tenant_id']) {
                    error_log("SuperAdminController::markPaymentAsPaid - Reativando tenant {$payment['tenant_id']}");
                    
                    $db->update('tenants', [
                        'status' => 'ativo',
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$payment['tenant_id']]);
                }
            }
            
            error_log("SuperAdminController::markPaymentAsPaid - Tudo OK! Retornando sucesso.");
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Pagamento quitado com sucesso! Estabelecimento reativado.'
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("SuperAdminController::markPaymentAsPaid - EXCEPTION: " . $e->getMessage());
            error_log("SuperAdminController::markPaymentAsPaid - Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Testar conexão com Asaas
     */
    public function testAsaasConnection() {
        $result = $this->asaasPayment->testConnection();
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Obter estatísticas de pagamentos do Asaas
     */
    public function getAsaasStats() {
        $tenantId = $_GET['tenant_id'] ?? null;
        $stats = $this->asaasPayment->getPaymentStats($tenantId);
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Criar cobrança no Asaas
     */
    public function createAsaasCharge() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['tenant_id']) || empty($data['valor'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            return;
        }
        
        // Buscar dados do tenant
        $tenant = $this->tenantModel->getById($data['tenant_id']);
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['error' => 'Tenant não encontrado']);
            return;
        }
        
        // Criar cliente no Asaas se não existir
        if (empty($tenant['asaas_customer_id'])) {
            $customerResult = $this->asaasPayment->createCustomer($tenant);
            if (!$customerResult['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Erro ao criar cliente no Asaas']);
                return;
            }
            $tenant['asaas_customer_id'] = $customerResult['data']['id'];
        }
        
        // Criar cobrança
        $chargeData = [
            'asaas_customer_id' => $tenant['asaas_customer_id'],
            'valor' => $data['valor'],
            'data_vencimento' => $data['data_vencimento'] ?? date('Y-m-d', strtotime('+7 days')),
            'descricao' => $data['descricao'] ?? 'Assinatura Divino Lanches',
            'payment_id' => $data['payment_id'] ?? null
        ];
        
        $result = $this->asaasPayment->createCharge($chargeData);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Listar assinaturas
     */
    public function listSubscriptions() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'tenant_id' => $_GET['tenant_id'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $subscriptions = $this->subscriptionModel->getAll($filters);
        
        header('Content-Type: application/json');
        echo json_encode($subscriptions);
    }
    
    /**
     * Listar instâncias WhatsApp
     */
    public function listWhatsAppInstances() {
        $db = \System\Database::getInstance();
        
        // Conectar ao banco da WuzAPI para pegar o status real
        try {
            $wuzapiDb = new \PDO(
                "pgsql:host=postgres;port=5432;dbname=wuzapi",
                "wuzapi",
                "wuzapi"
            );
        } catch (\PDOException $e) {
            error_log("SuperAdminController::listWhatsAppInstances - Erro ao conectar WuzAPI DB: " . $e->getMessage());
            $wuzapiDb = null;
        }
        
        $instances = $db->fetchAll("
            SELECT 
                wi.id,
                wi.tenant_id,
                wi.filial_id,
                wi.instance_name,
                wi.phone_number,
                wi.status,
                wi.wuzapi_instance_id,
                t.nome as tenant_nome,
                f.nome as filial_nome
            FROM whatsapp_instances wi
            LEFT JOIN tenants t ON wi.tenant_id = t.id
            LEFT JOIN filiais f ON wi.filial_id = f.id
            ORDER BY wi.created_at DESC
        ");
        
        // Sincronizar status com WuzAPI
        if ($wuzapiDb) {
            foreach ($instances as &$instance) {
                if ($instance['wuzapi_instance_id']) {
                    $stmt = $wuzapiDb->prepare("SELECT jid FROM users WHERE id = ?");
                    $stmt->execute([$instance['wuzapi_instance_id']]);
                    $wuzapiUser = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    // Se tem JID, está conectado
                    if ($wuzapiUser && !empty($wuzapiUser['jid'])) {
                        $instance['status'] = 'ativo';
                        // Atualizar no banco local
                        $db->update('whatsapp_instances', ['status' => 'ativo'], 'id = ?', [$instance['id']]);
                    }
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($instances);
    }
    
    /**
     * Criar instância WhatsApp
     */
    public function createWhatsAppInstance() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar com base no tipo de instância
        $isSuperAdmin = ($data['instance_type'] ?? 'establishment') === 'superadmin';
        
        if (empty($data['instance_name']) || empty($data['phone_number'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            return;
        }
        
        if (!$isSuperAdmin && (empty($data['tenant_id']) || empty($data['filial_id']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Estabelecimento e filial são obrigatórios para instâncias específicas']);
            return;
        }
        
        try {
            $wuzapi = new \System\WhatsApp\WuzAPIManager();
            $result = $wuzapi->createInstance(
                $data['instance_name'],
                $data['phone_number'],
                '' // webhook será configurado depois
            );
            
            if ($result['success']) {
                // Salvar no banco
                $db = \System\Database::getInstance();
                $instanceData = [
                    'instance_name' => $data['instance_name'],
                    'phone_number' => $data['phone_number'],
                    'wuzapi_token' => $result['token'],
                    'wuzapi_instance_id' => $result['instance_id'],
                    'status' => 'pendente',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Para instâncias do SuperAdmin, tenant_id e filial_id são NULL
                if ($isSuperAdmin) {
                    $instanceData['tenant_id'] = null;
                    $instanceData['filial_id'] = null;
                    $instanceData['is_superadmin'] = true;
                } else {
                    $instanceData['tenant_id'] = $data['tenant_id'];
                    $instanceData['filial_id'] = $data['filial_id'];
                    $instanceData['is_superadmin'] = false;
                }
                
                $instanceId = $db->insert('whatsapp_instances', $instanceData);
                
                // Buscar QR Code
                $qrResult = $wuzapi->getQRCode($result['token']);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'instance_id' => $instanceId,
                    'qr_code' => $qrResult['qr_code'] ?? null
                ]);
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter QR Code de instância WhatsApp
     */
    public function getWhatsAppQRCode() {
        $instanceId = $_GET['instance_id'] ?? null;
        
        if (!$instanceId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da instância não fornecido']);
            return;
        }
        
        try {
            // Usar WuzAPIManager diretamente (igual ao resto do sistema)
            $wuzapi = new \System\WhatsApp\WuzAPIManager();
            $result = $wuzapi->generateQRCode($instanceId);
            
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Excluir instância WhatsApp
     */
    public function deleteWhatsAppInstance() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['instance_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da instância não fornecido']);
            return;
        }
        
        try {
            $db = \System\Database::getInstance();
            
            // Buscar dados da instância antes de deletar
            $instance = $db->fetch("SELECT wuzapi_instance_id FROM whatsapp_instances WHERE id = ?", [$data['instance_id']]);
            
            if ($instance && $instance['wuzapi_instance_id']) {
                // Deletar da WuzAPI também
                try {
                    $wuzapiDb = new \PDO(
                        "pgsql:host=postgres;port=5432;dbname=wuzapi",
                        "wuzapi",
                        "wuzapi"
                    );
                    
                    $stmt = $wuzapiDb->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$instance['wuzapi_instance_id']]);
                    
                    error_log("SuperAdminController::deleteWhatsAppInstance - Instância deletada da WuzAPI: " . $instance['wuzapi_instance_id']);
                } catch (\PDOException $e) {
                    error_log("SuperAdminController::deleteWhatsAppInstance - Erro ao deletar da WuzAPI: " . $e->getMessage());
                }
            }
            
            // Deletar do banco local
            $db->delete('whatsapp_instances', 'id = ?', [$data['instance_id']]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Listar filiais de um tenant
     */
    public function getFiliais() {
        $tenantId = $_GET['tenant_id'] ?? null;
        
        if (!$tenantId) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID não fornecido']);
            return;
        }
        
        $filiais = $this->tenantModel->getFiliais($tenantId);
        
        header('Content-Type: application/json');
        echo json_encode($filiais);
    }
}

// Roteamento
if (basename($_SERVER['PHP_SELF']) == 'SuperAdminController.php') {
    $controller = new SuperAdminController();
    $action = $_GET['action'] ?? 'getDashboardStats';
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ação não encontrada']);
    }
}

