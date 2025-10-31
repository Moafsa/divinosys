<?php
/**
 * Model de Integração com Asaas
 * Gerencia pagamentos via gateway Asaas
 */

use System\Database;

class AsaasPayment {
    private $conn;
    private $apiKey;
    private $apiUrl;
    private $webhookUrl;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
        
        // Configurações do Asaas
        $apiKeyFromEnv = $_ENV['ASAAS_API_KEY'] ?? 'sua_api_key_aqui';
        // Se a chave não começar com $, adicionar (fix para Docker Compose que não aceita $ no .env)
        $this->apiKey = (strpos($apiKeyFromEnv, '$') === 0) ? $apiKeyFromEnv : '$' . $apiKeyFromEnv;
        $this->apiUrl = $_ENV['ASAAS_API_URL'] ?? 'https://sandbox.asaas.com/api/v3';
        $this->webhookUrl = $_ENV['ASAAS_WEBHOOK_URL'] ?? 'https://seu-dominio.com/webhook/asaas.php';
    }
    
    /**
     * Criar cliente no Asaas
     */
    public function createCustomer($tenantData) {
        $data = [
            'name' => $tenantData['nome'],
            'email' => $tenantData['email'],
            'phone' => $tenantData['telefone'],
            'cpfCnpj' => $tenantData['cnpj'],
            'externalReference' => 'tenant_' . $tenantData['id']
        ];
        
        return $this->makeRequest('POST', '/customers', $data);
    }
    
    /**
     * Criar cobrança no Asaas
     */
    public function createCharge($paymentData) {
        $data = [
            'customer' => $paymentData['asaas_customer_id'],
            'billingType' => 'PIX', // PIX, BOLETO, CREDIT_CARD
            'value' => $paymentData['valor'],
            'dueDate' => $paymentData['data_vencimento'],
            'description' => $paymentData['descricao'] ?? 'Assinatura Divino Lanches',
            'externalReference' => 'payment_' . $paymentData['payment_id'],
            'webhook' => $this->webhookUrl
        ];
        
        return $this->makeRequest('POST', '/payments', $data);
    }
    
    /**
     * Criar assinatura recorrente no Asaas
     */
    public function createSubscription($subscriptionData) {
        $data = [
            'customer' => $subscriptionData['asaas_customer_id'],
            'billingType' => 'PIX',
            'value' => $subscriptionData['valor'],
            'nextDueDate' => $subscriptionData['next_due_date'] ?? date('Y-m-d', strtotime('+7 days')),
            'description' => $subscriptionData['descricao'] ?? 'Assinatura Divino Lanches',
            'cycle' => $subscriptionData['cycle'] ?? 'MONTHLY',
            'externalReference' => 'subscription_' . $subscriptionData['subscription_id']
        ];
        
        // Adicionar webhook apenas se configurado
        if ($this->webhookUrl && $this->webhookUrl !== 'https://seu-dominio.com/webhook/asaas.php') {
            $data['webhook'] = $this->webhookUrl;
        }
        
        return $this->makeRequest('POST', '/subscriptions', $data);
    }
    
    /**
     * Buscar cobrança por ID
     */
    public function getCharge($chargeId) {
        return $this->makeRequest('GET', '/payments/' . $chargeId);
    }
    
    /**
     * Buscar assinatura por ID
     */
    public function getSubscription($subscriptionId) {
        return $this->makeRequest('GET', '/subscriptions/' . $subscriptionId);
    }
    
    /**
     * Buscar cobranças de uma assinatura
     */
    public function getSubscriptionPayments($subscriptionId) {
        return $this->makeRequest('GET', '/subscriptions/' . $subscriptionId . '/payments');
    }
    
    /**
     * Atualizar assinatura no Asaas
     */
    public function updateSubscription($subscriptionId, $data) {
        error_log("AsaasPayment::updateSubscription - Iniciando. ID: $subscriptionId, Data: " . json_encode($data));
        
        $updateData = [];
        
        // Campos permitidos para atualização
        if (isset($data['valor'])) {
            $updateData['value'] = $data['valor'];
        }
        if (isset($data['cycle'])) {
            $updateData['cycle'] = $data['cycle']; // MONTHLY, SEMIANNUALLY, YEARLY
        }
        if (isset($data['nextDueDate'])) {
            $updateData['nextDueDate'] = $data['nextDueDate'];
        }
        if (isset($data['descricao'])) {
            $updateData['description'] = $data['descricao'];
        }
        
        error_log("AsaasPayment::updateSubscription - Dados preparados: " . json_encode($updateData));
        
        if (empty($updateData)) {
            error_log("AsaasPayment::updateSubscription - ERRO: Nenhum campo para atualizar");
            return ['success' => false, 'error' => 'Nenhum campo para atualizar'];
        }
        
        $result = $this->makeRequest('PUT', '/subscriptions/' . $subscriptionId, $updateData);
        error_log("AsaasPayment::updateSubscription - Resposta do Asaas: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Cancelar assinatura
     */
    public function cancelSubscription($subscriptionId) {
        error_log("AsaasPayment::cancelSubscription - Cancelando assinatura: $subscriptionId");
        $result = $this->makeRequest('DELETE', '/subscriptions/' . $subscriptionId);
        error_log("AsaasPayment::cancelSubscription - Resposta: " . json_encode($result));
        return $result;
    }
    
    /**
     * Processar webhook do Asaas
     */
    public function processWebhook($payload) {
        $event = $payload['event'];
        $payment = $payload['payment'];
        
        // Buscar pagamento no banco
        $query = "SELECT * FROM pagamentos WHERE gateway_payment_id = $1";
        $result = pg_query_params($this->conn, $query, [$payment['id']]);
        
        if (!$result || pg_num_rows($result) == 0) {
            return ['success' => false, 'message' => 'Pagamento não encontrado'];
        }
        
        $dbPayment = pg_fetch_assoc($result);
        
        switch ($event) {
            case 'PAYMENT_CONFIRMED':
                return $this->handlePaymentConfirmed($dbPayment, $payment);
                
            case 'PAYMENT_RECEIVED':
                return $this->handlePaymentReceived($dbPayment, $payment);
                
            case 'PAYMENT_OVERDUE':
                return $this->handlePaymentOverdue($dbPayment, $payment);
                
            case 'PAYMENT_DELETED':
                return $this->handlePaymentDeleted($dbPayment, $payment);
                
            default:
                return ['success' => false, 'message' => 'Evento não reconhecido'];
        }
    }
    
    /**
     * Processar pagamento confirmado
     */
    private function handlePaymentConfirmed($dbPayment, $asaasPayment) {
        // Atualizar status do pagamento
        $query = "UPDATE pagamentos SET 
                  status = 'pago',
                  data_pagamento = CURRENT_TIMESTAMP,
                  gateway_response = $1,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = $2";
        
        $result = pg_query_params($this->conn, $query, [
            json_encode($asaasPayment),
            $dbPayment['id']
        ]);
        
        if ($result) {
            // Renovar assinatura se necessário
            $this->renewSubscription($dbPayment['tenant_id']);
            
            // Enviar notificação
            $this->sendPaymentNotification($dbPayment['tenant_id'], 'confirmed');
            
            return ['success' => true, 'message' => 'Pagamento confirmado'];
        }
        
        return ['success' => false, 'message' => 'Erro ao atualizar pagamento'];
    }
    
    /**
     * Processar pagamento recebido
     */
    private function handlePaymentReceived($dbPayment, $asaasPayment) {
        // Similar ao confirmed, mas pode ter diferenças específicas
        return $this->handlePaymentConfirmed($dbPayment, $asaasPayment);
    }
    
    /**
     * Processar pagamento vencido
     */
    private function handlePaymentOverdue($dbPayment, $asaasPayment) {
        // Atualizar status para inadimplente
        $query = "UPDATE pagamentos SET 
                  status = 'falhou',
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = $1";
        
        $result = pg_query_params($this->conn, $query, [$dbPayment['id']]);
        
        if ($result) {
            // Suspender tenant se necessário
            $this->suspendTenantIfNeeded($dbPayment['tenant_id']);
            
            // Enviar notificação de vencimento
            $this->sendPaymentNotification($dbPayment['tenant_id'], 'overdue');
            
            return ['success' => true, 'message' => 'Pagamento marcado como vencido'];
        }
        
        return ['success' => false, 'message' => 'Erro ao processar vencimento'];
    }
    
    /**
     * Processar pagamento deletado
     */
    private function handlePaymentDeleted($dbPayment, $asaasPayment) {
        // Atualizar status para cancelado
        $query = "UPDATE pagamentos SET 
                  status = 'cancelado',
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = $1";
        
        $result = pg_query_params($this->conn, $query, [$dbPayment['id']]);
        
        if ($result) {
            // Enviar notificação
            $this->sendPaymentNotification($dbPayment['tenant_id'], 'cancelled');
            
            return ['success' => true, 'message' => 'Pagamento cancelado'];
        }
        
        return ['success' => false, 'message' => 'Erro ao cancelar pagamento'];
    }
    
    /**
     * Renovar assinatura
     */
    private function renewSubscription($tenantId) {
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getByTenant($tenantId);
        
        if ($subscription) {
            // Atualizar data de próxima cobrança
            $nextBilling = date('Y-m-d', strtotime('+1 month'));
            
            $query = "UPDATE assinaturas SET 
                      data_proxima_cobranca = $1,
                      status = 'ativa',
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $2";
            
            pg_query_params($this->conn, $query, [$nextBilling, $subscription['id']]);
        }
    }
    
    /**
     * Suspender tenant se necessário
     */
    private function suspendTenantIfNeeded($tenantId) {
        // Verificar quantos pagamentos estão vencidos
        $query = "SELECT COUNT(*) as vencidos 
                  FROM pagamentos 
                  WHERE tenant_id = $1 AND status = 'falhou' 
                  AND data_vencimento < CURRENT_DATE - INTERVAL '7 days'";
        
        $result = pg_query_params($this->conn, $query, [$tenantId]);
        $row = pg_fetch_assoc($result);
        
        if ($row['vencidos'] >= 2) {
            // Suspender tenant
            $query = "UPDATE tenants SET status = 'suspenso' WHERE id = $1";
            pg_query_params($this->conn, $query, [$tenantId]);
        }
    }
    
    /**
     * Enviar notificação de pagamento
     */
    private function sendPaymentNotification($tenantId, $type) {
        $query = "INSERT INTO notificacoes (tenant_id, tipo, titulo, mensagem, prioridade) VALUES ($1, $2, $3, $4, $5)";
        
        $notifications = [
            'confirmed' => [
                'tipo' => 'pagamento',
                'titulo' => 'Pagamento Confirmado',
                'mensagem' => 'Seu pagamento foi confirmado com sucesso!',
                'prioridade' => 'normal'
            ],
            'overdue' => [
                'tipo' => 'pagamento',
                'titulo' => 'Pagamento Vencido',
                'mensagem' => 'Seu pagamento está vencido. Regularize para continuar usando o sistema.',
                'prioridade' => 'alta'
            ],
            'cancelled' => [
                'tipo' => 'pagamento',
                'titulo' => 'Pagamento Cancelado',
                'mensagem' => 'Seu pagamento foi cancelado.',
                'prioridade' => 'normal'
            ]
        ];
        
        if (isset($notifications[$type])) {
            $notif = $notifications[$type];
            pg_query_params($this->conn, $query, [
                $tenantId,
                $notif['tipo'],
                $notif['titulo'],
                $notif['mensagem'],
                $notif['prioridade']
            ]);
        }
    }
    
    /**
     * Confirmar pagamento manualmente (recebido em dinheiro/manual)
     * Usado pelo SuperAdmin para quitar faturas manualmente
     */
    public function confirmPaymentManually($paymentId, $paymentDate = null, $value = null, $notifyCustomer = false) {
        error_log("AsaasPayment::confirmPaymentManually - Payment ID: $paymentId");
        
        $data = [
            'paymentDate' => $paymentDate ?? date('Y-m-d'),
            'notifyCustomer' => $notifyCustomer
        ];
        
        // Se valor foi especificado (diferente do valor original)
        if ($value !== null) {
            $data['value'] = $value;
        }
        
        $result = $this->makeRequest('POST', '/payments/' . $paymentId . '/receiveInCash', $data);
        error_log("AsaasPayment::confirmPaymentManually - Resposta: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Cancelar/Deletar pagamento no Asaas
     * Usado quando o SuperAdmin quer quitar manualmente mas cancelar a cobrança no Asaas
     */
    public function cancelPayment($paymentId) {
        error_log("AsaasPayment::cancelPayment - Cancelando payment ID: $paymentId");
        
        $result = $this->makeRequest('DELETE', '/payments/' . $paymentId);
        error_log("AsaasPayment::cancelPayment - Resposta: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Fazer requisição para API do Asaas
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $headers = [
            'access_token: ' . $this->apiKey,
            'Content-Type: application/json',
            'User-Agent: DivinoSYS/2.0'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decodedResponse
            ];
        } else {
            return [
                'success' => false,
                'error' => $decodedResponse['errors'] ?? 'Erro na API do Asaas',
                'http_code' => $httpCode
            ];
        }
    }
    
    /**
     * Testar conexão com Asaas
     */
    public function testConnection() {
        $result = $this->makeRequest('GET', '/customers?limit=1');
        return $result;
    }
    
    /**
     * Obter URL da API
     */
    public function getApiUrl() {
        return $this->apiUrl;
    }
    
    /**
     * Obter estatísticas de pagamentos
     */
    public function getPaymentStats($tenantId = null) {
        $query = "SELECT 
                    COUNT(*) as total_pagamentos,
                    COUNT(CASE WHEN status = 'pago' THEN 1 END) as pagamentos_pagos,
                    COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pagamentos_pendentes,
                    COUNT(CASE WHEN status = 'falhou' THEN 1 END) as pagamentos_falhados,
                    COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) as receita_total
                  FROM pagamentos";
        
        $params = [];
        if ($tenantId) {
            $query .= " WHERE tenant_id = $1";
            $params[] = $tenantId;
        }
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
}
