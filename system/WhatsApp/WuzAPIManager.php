<?php

namespace System\WhatsApp;

use Exception;

/**
 * Gerenciador da WuzAPI - API WhatsApp moderna em Go
 * Endpoints: QR code, status, mensagens, webhooks
 */
class WuzAPIManager 
{
    private $wuzapiUrl;
    private $apiKey;
    private $webhookUrl;
    
    public function __construct() 
    {
        $this->wuzapiUrl = $_ENV['WUZAPI_URL'] ?? 'http://localhost:8080';
        $this->apiKey = $_ENV['WUZAPI_API_KEY'] ?? '';
        $this->webhookUrl = $_ENV['N8N_WEBHOOK_URL'] ?? '';
    }
    
    /**
     * Criar instância WhatsApp via WuzAPI
     */
    public function createInstance($instanceName, $phoneNumber, $webhookUrl = null) 
    {
        try {
            $webhook = $webhookUrl ?? $this->webhookUrl;
            
            $data = [
                'instance_name' => $instanceName,
                'phone_number' => $phoneNumber,
                'webhook_url' => $webhook,
                'qrcode' => true,
                'status' => 'disconnected'
            ];
            
            $response = $this->makeApiCall('POST', '/api/instance/create', $data);
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'instance_id' => $response['instance_id'],
                    'message' => 'Instância criada com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao criar instância: ' . ($response['message'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::createInstance - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar instância: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar QR code para instância
     */
    public function generateQRCode($instanceId) 
    {
        try {
            $response = $this->makeApiCall('GET', "/api/instance/{$instanceId}/qrcode");
            
            if ($response && isset($response['qrcode'])) {
                return [
                    'success' => true,
                    'qr_code' => $response['qrcode'],
                    'status' => 'connecting',
                    'message' => 'QR code gerado com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao gerar QR code'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::generateQRCode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR code: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status da instância
     */
    public function getInstanceStatus($instanceId) 
    {
        try {
            $response = $this->makeApiCall('GET', "/api/instance/{$instanceId}/status");
            
            if ($response) {
                return [
                    'success' => true,
                    'status' => $response['status'] ?? 'unknown',
                    'connected' => $response['connected'] ?? false,
                    'message' => $response['message'] ?? ''
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao verificar status'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::getInstanceStatus - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao verificar status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensagem via WuzAPI
     */
    public function sendMessage($instanceId, $phoneNumber, $message) 
    {
        try {
            $data = [
                'number' => $phoneNumber,
                'message' => $message,
                'type' => 'text'
            ];
            
            $response = $this->makeApiCall('POST', "/api/instance/{$instanceId}/send", $data);
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['message_id'] ?? null,
                    'message' => 'Mensagem enviada com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao enviar mensagem: ' . ($response['message'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::sendMessage - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Deletar instância
     */
    public function deleteInstance($instanceId) 
    {
        try {
            $response = $this->makeApiCall('DELETE', "/api/instance/{$instanceId}");
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'message' => 'Instância deletada com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao deletar instância: ' . ($response['message'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::deleteInstance - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar instância: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer chamada para API WuzAPI
     */
    private function makeApiCall($method, $endpoint, $data = null) 
    {
        try {
            $url = rtrim($this->wuzapiUrl, '/') . $endpoint;
            
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            if ($this->apiKey) {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL Error: {$error}");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            throw new Exception("HTTP Error: {$httpCode} - {$response}");
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::makeApiCall - Error: " . $e->getMessage());
            throw $e;
        }
    }
}
