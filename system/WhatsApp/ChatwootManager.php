<?php

namespace System\WhatsApp;

use System\Database;
use Exception;

class ChatwootManager {
    private $chatwootUrl;
    private $apiKey;
    private $db;
    
    public function __construct() {
        // Valores hardcoded temporariamente para debug
        $this->chatwootUrl = 'https://services.conext.click/';
        $this->apiKey = 'WyDnNvfhwHEhvGpQ4cGzvaQM';
        $this->db = Database::getInstance();
        
        error_log("ChatwootManager::__construct - URL: $this->chatwootUrl, API Key: " . substr($this->apiKey, 0, 10) . "...");
    }
    
    /**
     * Criar conta completa no Chatwoot (usar conta existente #11 + usuário + inbox + webhook)
     */
    public function createCompleteChatwootSetup($estabelecimentoId, $nomeEstabelecimento, $email, $telefone) {
        try {
            // Usar conta hardcoded temporariamente
            $accountId = 11;
            
            // 1. Criar usuário na conta
            $user = $this->createChatwootUser($accountId, $nomeEstabelecimento, $email, $telefone);
            if (!$user) {
                throw new Exception('Falha ao criar usuário no Chatwoot');
            }
            
            // 2. Criar inbox do WhatsApp com Baileys
            $inbox = $this->createWhatsAppInbox($accountId, $nomeEstabelecimento, $telefone);
            if (!$inbox) {
                throw new Exception('Falha ao criar inbox WhatsApp no Chatwoot');
            }
            
            // 3. Adicionar agente como colaborador do inbox
            $this->addAgentToInbox($accountId, $inbox['id'], $user['id']);
            
            // 4. Salvar dados no banco local
            $this->saveChatwootUser($estabelecimentoId, $user['id'], $email);
            $this->saveChatwootInbox($estabelecimentoId, $inbox['id'], $telefone);
            
            return [
                'success' => true,
                'account_id' => $accountId,
                'user' => $user,
                'inbox' => $inbox
            ];
            
        } catch (Exception $e) {
            error_log("ChatwootManager::createCompleteChatwootSetup - Error: " . $e->getMessage());
            error_log("ChatwootManager::createCompleteChatwootSetup - Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Criar conta no Chatwoot
     */
    private function createChatwootAccount($nomeEstabelecimento) {
        $accountData = [
            'name' => $nomeEstabelecimento,
            'status' => 'active',
            'domain' => strtolower(str_replace(' ', '-', $nomeEstabelecimento)) . '-' . uniqid()
        ];
        
        $response = $this->makeApiCall('POST', '/api/v1/accounts', $accountData);
        
        if ($response && isset($response['id'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Criar usuário no Chatwoot para uma conta específica
     */
    public function createChatwootUser($accountId, $nome, $email, $telefone) {
        $userData = [
            'name' => $nome,
            'email' => $email,
            'password' => $this->generatePassword(),
            'role' => 'agent',
            'confirmed' => true,
            'verified' => true
        ];
        
        $response = $this->makeApiCall('POST', "/api/v1/accounts/{$accountId}/agents", $userData);
        
        if ($response && isset($response['id']) && !isset($response['error'])) {
            return $response;
        }
        return false;
    }
    
    /**
     * Criar inbox do WhatsApp para uma conta específica
     */
    public function createWhatsAppInbox($accountId, $nomeEstabelecimento, $telefone) {
        $inboxData = [
            'name' => "WhatsApp - {$nomeEstabelecimento}",
            'channel' => [
                'type' => 'whatsapp',
                'phone_number' => $telefone,
                'provider' => 'baileys'
            ]
        ];
        
        $response = $this->makeApiCall('POST', "/api/v1/accounts/{$accountId}/inboxes", $inboxData);
        
        if ($response && isset($response['id'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Enviar mensagem via Chatwoot
     */
    public function sendMessage($conversationId, $message, $messageType = 'outgoing') {
        $messageData = [
            'content' => $message,
            'message_type' => $messageType,
            'private' => false
        ];
        
        return $this->makeApiCall('POST', "/api/v1/accounts/1/conversations/{$conversationId}/messages", $messageData);
    }
    
    /**
     * Obter conversas de um estabelecimento
     */
    public function getConversations($estabelecimentoId, $status = 'open') {
        $chatwootUserId = $this->getChatwootUserId($estabelecimentoId);
        
        if (!$chatwootUserId) {
            return [];
        }
        
        $response = $this->makeApiCall('GET', "/api/v1/accounts/1/conversations?assignee_id={$chatwootUserId}&status={$status}");
        
        return $response['data'] ?? [];
    }
    
    /**
     * Processar webhook do Chatwoot
     */
    public function processWebhook($webhookData) {
        if (!isset($webhookData['event'])) {
            return false;
        }
        
        switch ($webhookData['event']) {
            case 'message_created':
                return $this->handleNewMessage($webhookData);
            case 'conversation_created':
                return $this->handleNewConversation($webhookData);
            case 'conversation_status_changed':
                return $this->handleConversationStatusChange($webhookData);
        }
        
        return true;
    }
    
    /**
     * Fazer chamada para API do Chatwoot
     */
    private function makeApiCall($method, $endpoint, $data = null) {
        // Se for URL absoluta, usar diretamente
        if (strpos($endpoint, 'http') === 0) {
            $url = $endpoint;
        } else {
            $url = rtrim($this->chatwootUrl, '/') . $endpoint;
        }
        
        $headers = [
            'Content-Type: application/json',
            'api_access_token: ' . $this->apiKey
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && $data) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("Chatwoot API Error: {$error}");
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        // Log detailed error information
        $decodedResponse = json_decode($response, true);
        $errorMessage = $decodedResponse['message'] ?? $decodedResponse['errors'][0] ?? $response;
        
        error_log("Chatwoot API HTTP Error: {$httpCode} - {$errorMessage}");
        
        // Return error details for debugging
        return [
            'error' => true,
            'http_code' => $httpCode,
            'message' => $errorMessage,
            'raw_response' => $response
        ];
    }
    
    
    /**
     * Salvar dados do usuário Chatwoot no banco
     */
    private function saveChatwootUser($estabelecimentoId, $chatwootUserId, $email) {
        try {
            $sql = "INSERT INTO chatwoot_users (estabelecimento_id, chatwoot_user_id, email, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE chatwoot_user_id = VALUES(chatwoot_user_id), updated_at = NOW()";
            
            $this->db->query($sql, [$estabelecimentoId, $chatwootUserId, $email]);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao salvar usuário Chatwoot: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salvar dados do inbox Chatwoot no banco
     */
    private function saveChatwootInbox($estabelecimentoId, $chatwootInboxId, $telefone) {
        try {
            $sql = "INSERT INTO chatwoot_inboxes (estabelecimento_id, chatwoot_inbox_id, telefone, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE chatwoot_inbox_id = VALUES(chatwoot_inbox_id), updated_at = NOW()";
            
            $this->db->query($sql, [$estabelecimentoId, $chatwootInboxId, $telefone]);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao salvar inbox Chatwoot: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método antigo mantido para compatibilidade
     */
    private function saveChatwootUserOld($estabelecimentoId, $chatwootUserId, $email) {
        $sql = "INSERT INTO chatwoot_users (estabelecimento_id, chatwoot_user_id, email, created_at) 
                VALUES (?, ?, ?, NOW()) 
                ON CONFLICT (estabelecimento_id) 
                DO UPDATE SET chatwoot_user_id = ?, email = ?, updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$estabelecimentoId, $chatwootUserId, $email, $chatwootUserId, $email]);
    }
    
    
    /**
     * Deletar usuário no Chatwoot
     */
    public function deleteUser($userId) {
        try {
            $response = $this->makeApiCall('DELETE', "/api/v1/accounts/11/agents/{$userId}");
            return $response !== false;
        } catch (Exception $e) {
            error_log("ChatwootManager::deleteUser - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletar inbox no Chatwoot
     */
    public function deleteInbox($inboxId) {
        try {
            $response = $this->makeApiCall('DELETE', "/api/v1/accounts/11/inboxes/{$inboxId}");
            return $response !== false;
        } catch (Exception $e) {
            error_log("ChatwootManager::deleteInbox - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Adicionar agente como colaborador do inbox
     */
    public function addAgentToInbox($accountId, $inboxId, $agentId) {
        try {
            $data = ['user_id' => $agentId];
            $response = $this->makeApiCall('POST', "/api/v1/accounts/{$accountId}/inboxes/{$inboxId}/members", $data);
            return $response !== false;
        } catch (Exception $e) {
            error_log("ChatwootManager::addAgentToInbox - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar QR code via Chatwoot (baseado na implementação fazer-ai)
     */
    public function generateQRCodeForInbox($accountId, $inboxId) {
        try {
            // Buscar configuração do inbox
            $inboxResponse = $this->makeApiCall('GET', "/api/v1/accounts/{$accountId}/inboxes/{$inboxId}");
            
            if ($inboxResponse && isset($inboxResponse['provider_connection'])) {
                $connection = $inboxResponse['provider_connection'];
                
                // Se já está conectado
                if ($connection['connection']) {
                    return [
                        'success' => true,
                        'qr_code' => null,
                        'status' => 'connected',
                        'connection' => true,
                        'message' => 'WhatsApp já está conectado'
                    ];
                }
                
                // Se tem erro, mostrar erro
                if ($connection['error']) {
                    return [
                        'success' => false,
                        'qr_code' => null,
                        'status' => 'error',
                        'connection' => false,
                        'message' => 'Erro na conexão: ' . $connection['error']
                    ];
                }
                
                // Se não conectado e sem erro, tentar obter QR code
                if (isset($connection['qr_data_url']) && $connection['qr_data_url']) {
                    // Fazer requisição interna para obter QR code
                    $qrResponse = $this->makeInternalQRRequest($connection['qr_data_url']);
                    
                    if ($qrResponse && isset($qrResponse['qr_code'])) {
                        return [
                            'success' => true,
                            'qr_code' => $qrResponse['qr_code'],
                            'status' => 'connecting',
                            'connection' => false,
                            'message' => 'QR code gerado com sucesso'
                        ];
                    }
                }
                
                // Se chegou aqui, inbox existe mas não tem QR code ainda
                // No fazer-ai/chatwoot, o QR code é gerado automaticamente
                return [
                    'success' => true,
                    'qr_code' => null,
                    'status' => 'disconnected',
                    'connection' => false,
                    'message' => 'Aguarde alguns segundos e tente novamente. O QR code está sendo gerado automaticamente.',
                    'retry_after' => 5
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Inbox não encontrado ou configuração inválida'
            ];
            
        } catch (Exception $e) {
            error_log("ChatwootManager::generateQRCodeForInbox - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR code: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer requisição interna para obter QR code
     */
    private function makeInternalQRRequest($qrUrl) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $qrUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'api_access_token: ' . $this->apiKey
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return json_decode($response, true);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("ChatwootManager::makeInternalQRRequest - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter ID do usuário Chatwoot para um estabelecimento
     */
    private function getChatwootUserId($estabelecimentoId) {
        $sql = "SELECT chatwoot_user_id FROM chatwoot_users WHERE estabelecimento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$estabelecimentoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['chatwoot_user_id'] : null;
    }
    
    /**
     * Obter telefone do estabelecimento
     */
    private function getEstabelecimentoPhone($estabelecimentoId) {
        $sql = "SELECT telefone FROM filiais WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$estabelecimentoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['telefone'] : null;
    }
    
    /**
     * Configurar webhook para o inbox
     */
    private function configureWebhook($accountId, $inboxId, $estabelecimentoId) {
        $webhookUrl = $this->getWebhookUrl($accountId);
        
        $webhookData = [
            'webhook_url' => $webhookUrl,
            'subscriptions' => ['message_created', 'message_updated', 'conversation_created', 'conversation_updated']
        ];
        
        $response = $this->makeApiCall('POST', "/api/v1/accounts/{$accountId}/inboxes/{$inboxId}/webhooks", $webhookData);
        
        // Garantir que sempre retorna um array com webhook_url
        if ($response && !isset($response['webhook_url'])) {
            $response['webhook_url'] = $webhookUrl;
        }
        
        return $response ?: ['webhook_url' => $webhookUrl];
    }
    
    /**
     * Gerar URL do webhook
     */
    private function getWebhookUrl($accountId) {
        // Usar webhook do n8n do .env para IA, senão usar webhook interno
        $n8nWebhook = $_ENV['N8N_WEBHOOK_URL'] ?? '';
        if (!empty($n8nWebhook)) {
            return $n8nWebhook;
        }
        
        // Fallback para webhook interno
        $baseUrl = $_ENV['APP_URL'] ?? 'https://your-domain.com';
        return "{$baseUrl}/webhook/chatwoot/{$accountId}";
    }
    
    /**
     * Gerar senha aleatória
     */
    private function generatePassword($length = 12) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Processar nova mensagem recebida
     */
    private function handleNewMessage($webhookData) {
        $message = $webhookData['data']['message'] ?? null;
        $conversation = $webhookData['data']['conversation'] ?? null;
        
        if (!$message || !$conversation) {
            return false;
        }
        
        // Processar mensagem recebida
        $this->processReceivedMessage($message, $conversation);
        
        return true;
    }
    
    /**
     * Processar nova conversa
     */
    private function handleNewConversation($webhookData) {
        $conversation = $webhookData['data']['conversation'] ?? null;
        
        if (!$conversation) {
            return false;
        }
        
        // Processar nova conversa
        $this->processNewConversation($conversation);
        
        return true;
    }
    
    /**
     * Processar mudança de status da conversa
     */
    private function handleConversationStatusChange($webhookData) {
        $conversation = $webhookData['data']['conversation'] ?? null;
        
        if (!$conversation) {
            return false;
        }
        
        // Processar mudança de status
        $this->processConversationStatusChange($conversation);
        
        return true;
    }
    
    /**
     * Processar mensagem recebida
     */
    private function processReceivedMessage($message, $conversation) {
        // Implementar lógica para processar mensagem recebida
        // Ex: salvar no banco, enviar para n8n, etc.
    }
    
    /**
     * Processar nova conversa
     */
    private function processNewConversation($conversation) {
        // Implementar lógica para processar nova conversa
        // Ex: notificar administradores, configurar automações, etc.
    }
    
    /**
     * Processar mudança de status da conversa
     */
    private function processConversationStatusChange($conversation) {
        // Implementar lógica para processar mudança de status
        // Ex: atualizar banco de dados, notificar, etc.
    }
}
