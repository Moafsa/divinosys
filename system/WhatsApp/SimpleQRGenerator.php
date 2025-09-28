<?php

namespace System\WhatsApp;

use Exception;

/**
 * Gerador de QR Code simples - apenas API externa
 */
class SimpleQRGenerator 
{
    /**
     * Gerar QR code para uma instância
     */
    public function generateQRCode($instanceId, $phoneNumber, $instanceName) 
    {
        try {
            error_log("SimpleQRGenerator::generateQRCode - Iniciando para instância {$instanceId}");
            
            // Dados para o QR code
            $qrData = "https://wa.me/{$phoneNumber}?text=Conectar%20sistema";
            $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);
            
            error_log("SimpleQRGenerator::generateQRCode - URL: " . $apiUrl);
            
            // Usar cURL para maior controle
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3
            ]);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            error_log("SimpleQRGenerator::generateQRCode - HTTP Code: {$httpCode}, Error: {$error}");
            
            if ($httpCode === 200 && $imageData && strlen($imageData) > 100) {
                $base64 = base64_encode($imageData);
                error_log("SimpleQRGenerator::generateQRCode - QR gerado com sucesso, tamanho: " . strlen($imageData));
                
                return [
                    'success' => true,
                    'qr_code' => $base64,
                    'status' => 'connecting',
                    'instance_id' => $instanceId,
                    'message' => 'QR code gerado com sucesso. Escaneie com seu WhatsApp.'
                ];
            }
            
            error_log("SimpleQRGenerator::generateQRCode - Falha: HTTP {$httpCode}, Error: {$error}");
            return [
                'success' => false,
                'message' => "Falha ao gerar QR code. HTTP: {$httpCode}, Error: {$error}"
            ];
            
        } catch (Exception $e) {
            error_log("SimpleQRGenerator::generateQRCode - Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR code: ' . $e->getMessage()
            ];
        }
    }
}
