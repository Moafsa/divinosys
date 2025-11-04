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
            
            // Prepare payload - only send question and context
            $payload = array_merge([
                'message' => $message,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s')
            ], $additionalContext);
            
            // Validate tenant and filial IDs
            if (!$tenantId || !$filialId) {
                throw new Exception('Multi-tenant system requires valid tenant_id and filial_id');
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
