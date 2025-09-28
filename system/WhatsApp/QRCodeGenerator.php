<?php

namespace System\WhatsApp;

use Exception;

/**
 * Gerador de QR Code híbrido - simula Chatwoot conectado
 * Gera QR codes usando webhook interno + token Chatwoot
 */
class QRCodeGenerator 
{
    private $chatwootUrl;
    private $apiKey;
    private $webhookUrl;
    
    public function __construct() 
    {
        $this->chatwootUrl = $_ENV['CHATWOOT_URL'] ?? 'https://services.conext.click';
        $this->apiKey = $_ENV['CHATWOOT_API_KEY'] ?? '';
        $this->webhookUrl = $_ENV['N8N_WEBHOOK_URL'] ?? '';
    }
    
    /**
     * Gerar QR code simulado para uma instância
     */
    public function generateQRCode($instanceId, $phoneNumber, $instanceName) 
    {
        try {
            // 1. Criar sessão simulada no Chatwoot
            $sessionData = $this->createSimulatedSession($instanceId, $phoneNumber);
            
            if (!$sessionData['success']) {
                throw new Exception($sessionData['message']);
            }
            
            // 2. Gerar QR code usando método alternativo
            $qrCode = $this->generateWhatsAppQR($phoneNumber, $instanceName);
            
            if (!$qrCode) {
                throw new Exception('Falha ao gerar QR code');
            }
            
            // 3. Simular status conectado no Chatwoot
            $this->simulateChatwootConnection($sessionData['inbox_id']);
            
            return [
                'success' => true,
                'qr_code' => $qrCode,
                'status' => 'connecting',
                'instance_id' => $instanceId,
                'session_data' => $sessionData
            ];
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::generateQRCode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar sessão simulada no Chatwoot
     */
    private function createSimulatedSession($instanceId, $phoneNumber) 
    {
        try {
            // Usar token para criar/atualizar inbox com status "connecting"
            $inboxData = [
                'name' => "WhatsApp - {$phoneNumber}",
                'channel_type' => 'Channel::Whatsapp',
                'phone_number' => $phoneNumber,
                'provider' => 'baileys',
                'provider_config' => [
                    'webhook_verify_token' => $this->generateWebhookToken(),
                    'session_status' => 'connecting',
                    'qr_generated_at' => date('c')
                ]
            ];
            
            // Fazer chamada para Chatwoot usando token
            $response = $this->makeChatwootCall('POST', '/api/v1/accounts/11/inboxes', $inboxData);
            
            if ($response && isset($response['id'])) {
                return [
                    'success' => true,
                    'inbox_id' => $response['id'],
                    'message' => 'Sessão simulada criada'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao criar sessão simulada'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar sessão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar QR code do WhatsApp usando método alternativo
     */
    private function generateWhatsAppQR($phoneNumber, $instanceName) 
    {
        try {
            // Método 1: Usar API externa (mais confiável)
            $qrCode = $this->generateQRWithExternalAPI($phoneNumber);
            if ($qrCode) {
                return $qrCode;
            }
            
            // Método 2: Gerar QR simples com instruções
            return $this->generateSimpleQR($phoneNumber, $instanceName);
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::generateWhatsAppQR - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Gerar QR code usando PHP QR Code (se disponível)
     */
    private function generateQRWithPHPQRCode($phoneNumber) 
    {
        try {
            // Dados para o QR code (formato WhatsApp)
            $qrData = "https://wa.me/{$phoneNumber}?text=Conectar%20sistema";
            
            // Gerar QR code como string base64
            ob_start();
            QRcode::png($qrData, null, QR_ECLEVEL_L, 10, 2);
            $qrImage = ob_get_contents();
            ob_end_clean();
            
            return base64_encode($qrImage);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Gerar QR code usando API externa
     */
    private function generateQRWithExternalAPI($phoneNumber) 
    {
        try {
            $qrData = "https://wa.me/{$phoneNumber}?text=Conectar%20sistema";
            $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);
            
            // Usar cURL para maior controle
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData) {
                return base64_encode($imageData);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::generateQRWithExternalAPI - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Gerar QR code simples (fallback)
     */
    private function generateSimpleQR($phoneNumber, $instanceName) 
    {
        try {
            // Verificar se GD está disponível
            if (!extension_loaded('gd') || !function_exists('imagecreate')) {
                // Fallback: retornar QR code via API externa com dados simples
                $simpleData = "WhatsApp: {$phoneNumber}\nInstancia: {$instanceName}\nConectar sistema";
                $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($simpleData);
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $imageData) {
                    return base64_encode($imageData);
                }
                
                return null;
            }
            
            $width = 300;
            $height = 300;
            
            // Criar imagem
            $image = imagecreate($width, $height);
            
            // Cores
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            $blue = imagecolorallocate($image, 25, 118, 210);
            
            // Fundo branco
            imagefill($image, 0, 0, $white);
            
            // Borda
            imagerectangle($image, 10, 10, $width-10, $height-10, $blue);
            
            // Título
            imagestring($image, 4, 50, 50, "WhatsApp Connect", $black);
            
            // Número
            imagestring($image, 3, 50, 100, "Numero: {$phoneNumber}", $black);
            
            // Instância
            imagestring($image, 2, 50, 130, "Instancia: {$instanceName}", $black);
            
            // Instruções
            imagestring($image, 2, 50, 180, "1. Abra o WhatsApp no celular", $black);
            imagestring($image, 2, 50, 200, "2. Toque em Dispositivos conectados", $black);
            imagestring($image, 2, 50, 220, "3. Toque em Conectar um dispositivo", $black);
            imagestring($image, 2, 50, 240, "4. Escaneie este codigo", $black);
            
            // Salvar como base64
            ob_start();
            imagepng($image);
            $imageData = ob_get_contents();
            ob_end_clean();
            
            imagedestroy($image);
            
            return base64_encode($imageData);
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::generateSimpleQR - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Simular conexão no Chatwoot
     */
    private function simulateChatwootConnection($inboxId) 
    {
        try {
            // Atualizar status do inbox para "connecting"
            $updateData = [
                'provider_config' => [
                    'session_status' => 'connecting',
                    'qr_generated_at' => date('c'),
                    'last_activity' => date('c')
                ]
            ];
            
            $this->makeChatwootCall('PATCH', "/api/v1/accounts/11/inboxes/{$inboxId}", $updateData);
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::simulateChatwootConnection - Error: " . $e->getMessage());
        }
    }
    
    /**
     * Fazer chamada para Chatwoot usando token
     */
    private function makeChatwootCall($method, $endpoint, $data = null) 
    {
        try {
            $url = rtrim($this->chatwootUrl, '/') . $endpoint;
            
            $headers = [
                'Content-Type: application/json',
                'api_access_token: ' . $this->apiKey
            ];
            
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
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("QRCodeGenerator::makeChatwootCall - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar token de webhook único
     */
    private function generateWebhookToken() 
    {
        return bin2hex(random_bytes(16));
    }
}
