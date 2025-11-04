<?php

namespace System;

use Exception;

/**
 * n8n AI Service
 * 
 * Replaces direct OpenAI calls with n8n webhook integration
 * Uses MCP (Model Context Protocol) for efficient data retrieval
 */
class N8nAIService
{
    private $config;
    private $session;
    private $webhookUrl;
    private $timeout;
    
    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->session = Session::getInstance();
        
        // Get n8n webhook URL for AI/MCP from environment
        $this->webhookUrl = $this->config->getEnv('AI_N8N_WEBHOOK_URL');
        
        if (empty($this->webhookUrl)) {
            throw new Exception('AI_N8N_WEBHOOK_URL not configured in environment');
        }
        
        $this->timeout = (int) ($this->config->getEnv('AI_N8N_TIMEOUT') ?: 30);
    }
    
    /**
     * Process user message through n8n workflow
     * 
     * @param string $message User message/question
     * @param array $attachments Optional file attachments
     * @param int $tenantId Optional override tenant ID (for webhook context)
     * @param int $filialId Optional override filial ID (for webhook context)
     * @param array $additionalContext Optional additional context data
     * @return array Response from AI
     */
    public function processMessage($message, $attachments = [], $tenantId = null, $filialId = null, $additionalContext = [])
    {
        try {
            // Use provided IDs or get from session
            $tenantId = $tenantId ?? $this->session->getTenantId();
            $filialId = $filialId ?? $this->session->getFilialId();
            $userId = $this->session->getUserId();
            
            // Validate tenant and filial IDs
            if (!$tenantId || !$filialId) {
                throw new Exception('Multi-tenant system requires valid tenant_id and filial_id');
            }
            
            // Get rich context data
            $db = \System\Database::getInstance();
            
            // Get tenant info
            $tenant = $db->fetch("SELECT id, nome, subdomain, cnpj, telefone, email FROM tenants WHERE id = ?", [$tenantId]);
            
            // Get filial info
            $filial = $db->fetch("SELECT id, nome, endereco, telefone FROM filiais WHERE id = ?", [$filialId]);
            
            // Get user info if available
            $user = null;
            if ($userId) {
                $user = $db->fetch("SELECT id, login, nivel FROM usuarios WHERE id = ?", [$userId]);
            }
            
            // Determine message source/type
            $source = $additionalContext['source'] ?? 'web';
            $messageType = $additionalContext['message_type'] ?? 'chat';
            
            // Get business hours and operational info
            $currentHour = (int) date('H');
            $isBusinessHours = ($currentHour >= 9 && $currentHour < 22);
            $dayOfWeek = date('w'); // 0 = Sunday, 6 = Saturday
            
            // Get some statistics for context
            $stats = $db->fetch("
                SELECT 
                    COUNT(DISTINCT CASE WHEN p.data = CURRENT_DATE THEN p.idpedido END) as pedidos_hoje,
                    COUNT(DISTINCT CASE WHEN m.status = '2' THEN m.id_mesa END) as mesas_ocupadas,
                    COUNT(DISTINCT CASE WHEN m.status = '1' THEN m.id_mesa END) as mesas_disponiveis,
                    COUNT(DISTINCT CASE WHEN p.status IN ('Pendente', 'Em Preparo') THEN p.idpedido END) as pedidos_ativos
                FROM mesas m
                LEFT JOIN pedido p ON p.tenant_id = m.tenant_id AND p.filial_id = m.filial_id
                WHERE m.tenant_id = ? AND m.filial_id = ?
            ", [$tenantId, $filialId]);
            
            // Build enriched payload
            $payload = [
                // Core message
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                
                // Context IDs (for MCP queries)
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'user_id' => $userId,
                
                // Rich context
                'context' => [
                    // Business info
                    'tenant' => [
                        'id' => $tenant['id'] ?? $tenantId,
                        'nome' => $tenant['nome'] ?? 'Unknown',
                        'subdomain' => $tenant['subdomain'] ?? '',
                        'telefone' => $tenant['telefone'] ?? '',
                        'email' => $tenant['email'] ?? '',
                        'cnpj' => $tenant['cnpj'] ?? ''
                    ],
                    'filial' => [
                        'id' => $filial['id'] ?? $filialId,
                        'nome' => $filial['nome'] ?? 'Matriz',
                        'endereco' => $filial['endereco'] ?? '',
                        'telefone' => $filial['telefone'] ?? ''
                    ],
                    
                    // User/Agent info
                    'user' => $user ? [
                        'id' => $user['id'],
                        'login' => $user['login'],
                        'nivel' => $user['nivel'],
                        'is_admin' => $user['nivel'] == 1,
                        'role' => $user['nivel'] == 1 ? 'admin' : ($user['nivel'] == 2 ? 'manager' : 'operator')
                    ] : null,
                    
                    // Message metadata
                    'source' => $source, // 'web', 'whatsapp', 'api', 'n8n'
                    'message_type' => $messageType, // 'chat', 'command', 'order', 'query', 'billing'
                    'channel' => $source === 'whatsapp' ? 'whatsapp' : 'web',
                    
                    // Operational context
                    'operational' => [
                        'is_business_hours' => $isBusinessHours,
                        'current_hour' => $currentHour,
                        'day_of_week' => $dayOfWeek,
                        'is_weekend' => in_array($dayOfWeek, [0, 6]),
                        'pedidos_hoje' => (int) ($stats['pedidos_hoje'] ?? 0),
                        'mesas_ocupadas' => (int) ($stats['mesas_ocupadas'] ?? 0),
                        'mesas_disponiveis' => (int) ($stats['mesas_disponiveis'] ?? 0),
                        'pedidos_ativos' => (int) ($stats['pedidos_ativos'] ?? 0)
                    ],
                    
                    // Service type hints (helps AI decide prompt)
                    'service_type' => $this->detectServiceType($message, $source),
                ],
                
                // Customer context (if from WhatsApp)
                'customer' => isset($additionalContext['customer_phone']) ? [
                    'phone' => $additionalContext['customer_phone'] ?? '',
                    'name' => $additionalContext['customer_name'] ?? '',
                    'whatsapp' => $additionalContext['customer_phone'] ?? '',
                    'is_new' => $additionalContext['is_new_customer'] ?? false
                ] : null,
                
                // Session metadata
                'session' => [
                    'conversation_id' => $additionalContext['conversation_id'] ?? uniqid('conv_'),
                    'platform' => $source,
                    'language' => 'pt-BR',
                    'timezone' => 'America/Sao_Paulo'
                ]
            ];
            
            // Merge any additional context
            if (!empty($additionalContext)) {
                foreach ($additionalContext as $key => $value) {
                    if (!isset($payload[$key]) && !in_array($key, ['source', 'message_type', 'customer_phone', 'customer_name', 'conversation_id'])) {
                        $payload[$key] = $value;
                    }
                }
            }
            
            // Add attachment info if present
            if (!empty($attachments)) {
                $payload['attachments'] = array_map(function($attachment) {
                    // Convert file to base64 for n8n processing
                    $fileContent = '';
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $fileContent = base64_encode(file_get_contents($attachment['path']));
                    }
                    
                    return [
                        'name' => $attachment['name'] ?? '',
                        'type' => $attachment['type'] ?? '',
                        'path' => $attachment['path'] ?? '',
                        'content' => $fileContent,
                        'size' => isset($attachment['path']) ? filesize($attachment['path']) : 0
                    ];
                }, $attachments);
            }
            
            // Call n8n webhook
            $response = $this->callN8nWebhook($payload);
            
            // Parse and return response
            return $this->parseN8nResponse($response);
            
        } catch (Exception $e) {
            error_log('N8n AI Service Error: ' . $e->getMessage());
            return [
                'type' => 'error',
                'message' => 'Erro ao processar sua solicitação. Por favor, tente novamente.'
            ];
        }
    }
    
    /**
     * Execute confirmed operation
     * 
     * @param array $operation Operation details
     * @return array Result of operation
     */
    public function executeOperation($operation)
    {
        try {
            // Some operations need to be executed locally
            // (database writes, file operations, etc.)
            
            if (!isset($operation['type'])) {
                throw new Exception('Operation type not specified');
            }
            
            switch ($operation['type']) {
                case 'create_product':
                case 'update_product':
                case 'delete_product':
                case 'create_category':
                case 'create_ingredient':
                case 'create_order':
                    // These need local execution for security
                    return $this->executeLocalOperation($operation);
                    
                default:
                    // Query operations can go through n8n
                    return $this->executeRemoteOperation($operation);
            }
            
        } catch (Exception $e) {
            error_log('Execute Operation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao executar operação: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Call n8n webhook
     * 
     * @param array $payload Request payload
     * @return array Response from n8n
     * @throws Exception On error
     */
    private function callN8nWebhook($payload)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->webhookUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Divino-Lanches/1.0'
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            error_log('n8n HTTP Error ' . $httpCode . ': ' . $response);
            throw new Exception('n8n webhook returned HTTP ' . $httpCode);
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from n8n webhook');
        }
        
        return $decoded;
    }
    
    /**
     * Parse n8n response
     * 
     * @param array $response Raw response from n8n
     * @return array Parsed response
     */
    private function parseN8nResponse($response)
    {
        if (!isset($response['success']) || !$response['success']) {
            return [
                'type' => 'error',
                'message' => $response['message'] ?? 'Erro desconhecido'
            ];
        }
        
        if (!isset($response['response'])) {
            return [
                'type' => 'error',
                'message' => 'Resposta inválida do servidor'
            ];
        }
        
        return $response['response'];
    }
    
    /**
     * Execute operation locally (database writes)
     * 
     * @param array $operation Operation details
     * @return array Result
     */
    private function executeLocalOperation($operation)
    {
        // Use existing OpenAI service for local operations
        // This maintains security for database writes
        $openAIService = new OpenAIService();
        return $openAIService->executeOperation($operation);
    }
    
    /**
     * Execute operation remotely through n8n
     * 
     * @param array $operation Operation details
     * @return array Result
     */
    private function executeRemoteOperation($operation)
    {
        $tenantId = $this->session->getTenantId();
        $filialId = $this->session->getFilialId();
        
        if (!$tenantId || !$filialId) {
            throw new Exception('Multi-tenant system requires valid tenant_id and filial_id from user session');
        }
        
        $payload = [
            'action' => 'execute_operation',
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ];
        
        $response = $this->callN8nWebhook($payload);
        
        return $response['result'] ?? [
            'success' => false,
            'message' => 'Erro ao executar operação'
        ];
    }
    
    /**
     * Detect service type from message content
     * Helps AI choose the right prompt and behavior
     * 
     * @param string $message User message
     * @param string $source Message source
     * @return string Service type
     */
    private function detectServiceType($message, $source)
    {
        $messageLower = mb_strtolower($message);
        
        // Order keywords
        $orderKeywords = ['quero', 'pedir', 'fazer pedido', 'mandar', 'delivery', 'entregar', 'levar'];
        foreach ($orderKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'order';
            }
        }
        
        // Query/Info keywords
        $queryKeywords = ['quanto custa', 'preço', 'valor', 'cardápio', 'menu', 'tem ', 'quais', 'horário', 'aberto'];
        foreach ($queryKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'query';
            }
        }
        
        // Billing keywords
        $billingKeywords = ['pagar', 'dívida', 'débito', 'quanto devo', 'pendente', 'fiado'];
        foreach ($billingKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'billing';
            }
        }
        
        // Management keywords (admin only)
        $managementKeywords = ['cadastrar', 'adicionar produto', 'criar categoria', 'excluir', 'editar'];
        foreach ($managementKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'management';
            }
        }
        
        // Support keywords
        $supportKeywords = ['ajuda', 'suporte', 'problema', 'erro', 'não funciona'];
        foreach ($supportKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'support';
            }
        }
        
        // Default
        return 'chat';
    }
    
    /**
     * Get service health status
     * 
     * @return array Health status
     */
    public function getHealthStatus()
    {
        try {
            // Try to ping n8n webhook health endpoint
            $healthUrl = str_replace('/webhook/', '/webhook-test/', $this->webhookUrl);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $healthUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_NOBODY => true
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return [
                'status' => $httpCode === 200 ? 'online' : 'offline',
                'webhook_url' => $this->webhookUrl,
                'http_code' => $httpCode
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
