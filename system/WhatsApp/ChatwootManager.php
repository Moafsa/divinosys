<?php

class ChatwootManager {
    private $chatwootUrl;
    private $apiKey;
    private $db;
    
    public function __construct() {
        $this->chatwootUrl = $_ENV['CHATWOOT_URL'] ?? 'https://your-chatwoot-instance.com';
        $this->apiKey = $_ENV['CHATWOOT_API_KEY'] ?? '';
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar usuário no Chatwoot para um estabelecimento
     */
    public function createChatwootUser($estabelecimentoId, $nome, $email, $telefone) {
        $userData = [
            'name' => $nome,
            'email' => $email,
            'password' => $this->generatePassword(),
            'role' => 'agent', // ou 'admin' se necessário
            'custom_attributes' => [
                'estabelecimento_id' => $estabelecimentoId,
                'telefone' => $telefone
            ]
        ];
        
        $response = $this->makeApiCall('POST', '/api/v1/accounts/1/agents', $userData);
        
        if ($response && isset($response['id'])) {
            // Salvar dados do usuário no banco
            $this->saveChatwootUser($estabelecimentoId, $response['id'], $email);
            return $response;
        }
        
        return false;
    }
    
    /**
     * Criar inbox do WhatsApp para um estabelecimento
     */
    public function createWhatsAppInbox($estabelecimentoId, $nomeEstabelecimento) {
        $inboxData = [
            'name' => "WhatsApp - {$nomeEstabelecimento}",
            'channel' => [
                'type' => 'whatsapp',
                'phone_number' => $this->getEstabelecimentoPhone($estabelecimentoId),
                'provider' => 'whatsapp_cloud', // ou 'baileys' se usar
                'provider_config' => [
                    'webhook_url' => $this->getWebhookUrl($estabelecimentoId)
                ]
            ]
        ];
        
        $response = $this->makeApiCall('POST', '/api/v1/accounts/1/inboxes', $inboxData);
        
        if ($response && isset($response['id'])) {
            $this->saveChatwootInbox($estabelecimentoId, $response['id']);
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
        $url = rtrim($this->chatwootUrl, '/') . $endpoint;
        
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
        
        error_log("Chatwoot API HTTP Error: {$httpCode} - {$response}");
        return false;
    }
    
    /**
     * Salvar dados do usuário Chatwoot no banco
     */
    private function saveChatwootUser($estabelecimentoId, $chatwootUserId, $email) {
        $sql = "INSERT INTO chatwoot_users (estabelecimento_id, chatwoot_user_id, email, created_at) 
                VALUES (?, ?, ?, NOW()) 
                ON CONFLICT (estabelecimento_id) 
                DO UPDATE SET chatwoot_user_id = ?, email = ?, updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$estabelecimentoId, $chatwootUserId, $email, $chatwootUserId, $email]);
    }
    
    /**
     * Salvar dados do inbox Chatwoot no banco
     */
    private function saveChatwootInbox($estabelecimentoId, $inboxId) {
        $sql = "INSERT INTO chatwoot_inboxes (estabelecimento_id, inbox_id, created_at) 
                VALUES (?, ?, NOW()) 
                ON CONFLICT (estabelecimento_id) 
                DO UPDATE SET inbox_id = ?, updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$estabelecimentoId, $inboxId, $inboxId]);
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
     * Gerar URL do webhook
     */
    private function getWebhookUrl($estabelecimentoId) {
        $baseUrl = $_ENV['APP_URL'] ?? 'https://your-domain.com';
        return "{$baseUrl}/webhook/chatwoot/{$estabelecimentoId}";
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
