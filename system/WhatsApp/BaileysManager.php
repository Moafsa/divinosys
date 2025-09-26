<?php

namespace System\WhatsApp;

use System\Database;
use System\Config;
use Exception;

class BaileysManager {
    private $db;
    private $instances = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Criar nova instância WhatsApp
     */
    public function createInstance($instanceName, $phoneNumber, $tenantId, $filialId = 1, $webhookUrl = '') {
        error_log("BaileysManager::createInstance - Criando $instanceName / $phoneNumber");
        
        try {
            // Verificar se nome já existe
            $existing = $this->db->fetch(
                "SELECT id FROM whatsapp_instances WHERE instance_name = ? AND ativo = true",
                [$instanceName]
            );
            if ($existing) {
                throw new Exception("Nome da instância já existe");
            }

            // Verificar se telefone já existe
            $existingPhone = $this->db->fetch(
                "SELECT id FROM whatsapp_instances WHERE phone_number = ? AND ativo = true",
                [$phoneNumber]
            );
            if ($existingPhone) {
                throw new Exception("Número de telefone já registrado");
            }

            // Criar instância
            $this->db->query(
                "INSERT INTO whatsapp_instances (tenant_id, filial_id, instance_name, phone_number, status, webhook_url, ativo) VALUES (?, ?, ?, ?, 'disconnected', ?, true)",
                [$tenantId, $filialId, $instanceName, $phoneNumber, $webhookUrl]
            );

            return [
                'success' => true,
                'message' => 'Instância criada com sucesso'
            ];

        } catch (Exception $e) {
            error_log("BaileysManager::createInstance - Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Listar instâncias
     */
    public function getInstances($tenantId) {
        try {
            $instances = $this->db->fetchAll(
                "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND ativo = true ORDER BY created_at DESC",
                [$tenantId]
            );

            return array_map(function($instance) {
                return [
                    'id' => $instance['id'],
                    'instance_name' => $instance['instance_name'],
                    'phone_number' => $instance['phone_number'],
                    'status' => $instance['status'] === 'connected' ? 'connected' : 'disconnected',
                    'webhook_url' => $instance['webhook_url'],
                    'created_at' => $instance['created_at']
                ];
            }, $instances);

        } catch (Exception $e) {
            error_log("BaileysManager::getInstances - Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deletar instância
     */
    public function deleteInstance($instanceId) {
        try {
            // Delete related records first
            $this->db->query("DELETE FROM whatsapp_messages WHERE instance_id = ?", [$instanceId]);
            $this->db->query("DELETE FROM whatsapp_webhooks WHERE instance_id = ?", [$instanceId]);
            $this->db->query("DELETE FROM whatsapp_instances WHERE id = ?", [$instanceId]);

            return [
                'success' => true,
                'message' => 'Instância deletada com sucesso'
            ];
        } catch (Exception $e) {
            error_log("BaileysManager::deleteInstance - Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Conectar instância (gerar QR)
     */
    public function generateQRCode($instanceId) {
        error_log('BaileysManager::generateQRCode - Iniciando para ID: ' . $instanceId);
        
        try {
            // Obtém instância
            $instance = $this->db->fetch(
                "SELECT instance_name, phone_number, status FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            if (!$instance) {
                throw new Exception('Instância não encontrada');
            }

            $phoneNumber = $instance['phone_number'];
            $instanceName = $instance['instance_name'];  // CRITICO: usar nome da instância
            error_log("📱 Generate QR chama issued for $phoneNumber) inst. $instanceName (ID: $instanceId)");
            
            // 🎯 PRINCIPAL FOCUS -. Use REMOTE BAILYrs HTTP delegíreal!!
            try {  
                return $this->generateBaileysProtocolQR($instanceName, $phoneNumber);  // usar instance_name em vez de ID
            } catch (Exception $e) {
                error_log('Real Baileys HTTP failed: ' . $e->getMessage());
                return $this->generateBasicQR($phoneNumber);  
            }
            
        } catch (Exception $e) {
            error_log('BaileysManager::generateQRCode - ERRO: ' . $e->getMessage());
            return $this->generateBasicQR($phoneNumber);
        }
    }

    /**
     * Generate REAL Baileys QR using HTTP API  
     */
    private function generateBaileysProtocolQR($instanceId, $phoneNumber) {
        error_log("🚀 BaileysManager::generateBaileysProtocolQR - Connecting to REAL Baileys server for $instanceId");
        
        try {
            // Determine Baileys URL based on environment
            $baileysUrl = $this->getBaileysServiceUrl();
            error_log("🔗 Using Baileys URL: $baileysUrl");
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $baileysUrl . '/connect',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'instanceId' => (string)$instanceId,
                    'phoneNumber' => $phoneNumber
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            error_log("🔥 Baileys API response [$httpCode]: " . $response);
            
            if ($curlError) {
                throw new Exception("CURL Error: " . $curlError);
            }
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['success']) && $data['success'] && isset($data['qr_code'])) {
                    error_log('✅ Successfully received QR from Baileys real server!');
                    
                    $this->db->query(
                        "UPDATE whatsapp_instances SET status = 'qrcode', qr_code = ?, session_data = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                        [$data['qr_code'], json_encode($data), $instanceId]
                    );
                    return $data['qr_code'];
                }
            }
            
            throw new Exception("Baileys API returned [$httpCode]: " . $response);
            
        } catch (Exception $e) {
            error_log('❌ REAL Baileys HTTP failed: ' . $e->getMessage());
            error_log('📱 Falling back to basic QR generation');
            return $this->generateBasicQR($phoneNumber);
        }
    }
    
    /**
     * Get Baileys service URL
     */
    private function getBaileysServiceUrl() {
        // Check if running in Docker
        if ($this->isDockerEnvironment()) {
            return 'http://baileys:3000'; // Internal port still 3000
        }
        
        // Development/fallback - updated to 3010 external port
        $configUrl = $_ENV['BAILEYS_SERVICE_URL'] ?? 'http://localhost:3010';
        return $configUrl;
    }
    
    /**
     * Check if running in Docker environment  
     */
    private function isDockerEnvironment() {
        return (file_exists('/.dockerenv') || 
                isset($_ENV['DOCKER_CONTAINER']) || 
                isset($_SERVER['DOCKER_CONTAINER']) ||
                ($_ENV['APP_ENV'] ?? '') === 'production' ||
                ($_ENV['APP_ENV'] ?? '') === 'docker' ||
                isset($_ENV['BAILEYS_SERVICE_URL']));
    }

    /**
     * Generate basic QR fallback 
     */
    private function generateBasicQR($phoneNumber) {
        error_log("🔴 QR fallback desabilitado - QRs inválidos detectados");
        
        // NUNCA mais gerar QRs que redirecionam para conversa
        // Se chegou aqui, é porque há um problema no server Baileys
        
        // Gerar apenas imagem de erro explicativa
        $img = imagecreate(300, 300);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $red = imagecolorallocate($img, 255, 0, 0);
        $black = imagecolorallocate($img, 0, 0, 0);
        
        imagefill($img, 0, 0, $bg);
        
        // Desenhar texto explicativo
        imagestring($img, 2, 10, 50, 'ERRO: Baileys server', $red);
        imagestring($img, 2, 10, 80, 'nao conectou', $black);
        imagestring($img, 2, 10, 110, 'Check server logs', $black);
        
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        
        return base64_encode($png);
    }

    /**
     * Enviar mensagem direto Baileys
     */
    public function sendDirectMessage($phoneNumber, $message) {
        try {
            // Log sent message without full infrastructure
            $this->db->query(
                "INSERT INTO whatsapp_messages (from_number, to_number, message_text, status, source) VALUES (?, ?, ?, 'sent', 'system')",
                ['system', $phoneNumber, $message]
            );

            return [
                'success' => true,
                'message_id' => 'test_' . time(),
                'status' => 'sent'
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Enviar para n8n webhook
     */
    private function sendToN8n($webhookUrl, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erro ao enviar para n8n: ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Obter instância do banco
     */
    private function getInstance($instanceId) {
        return $this->db->fetch(
            "SELECT * FROM whatsapp_instances WHERE id = ?",
            [$instanceId]
        );
    }

    /**
     * Verificar status de conexão da instância no Baileys
     */
    public function checkInstanceStatus($instanceId) {
        try {
            $baileysUrl = $this->getBaileysServiceUrl();
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $baileysUrl . '/status',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                throw new Exception("CURL Error: " . $curlError);
            }
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data;
            }
            
            throw new Exception("Baileys status check failed with code [$httpCode]: " . $response);
            
        } catch (Exception $e) {
            error_log('❌ Baileys status check failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'unavailable'
            ];
        }
    }
    
    /**
     * Send message via Baileys directly
     */
    public function sendBaileysMessage($instanceId, $to, $message, $messageType = 'text') {
        try {
            $baileysUrl = $this->getBaileysServiceUrl();
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $baileysUrl . '/send-message',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'instanceId' => (string)$instanceId,
                    'to' => $to,
                    'message' => $message,
                    'messageType' => $messageType
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                throw new Exception("CURL Error: " . $curlError);
            }
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['success']) && $data['success']) {
                    // Log message in database
                    $this->db->query(
                        "INSERT INTO whatsapp_messages (instance_id, tenant_id, filial_id, message_id, from_number, to_number, message_text, message_type, status, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', 'system')",
                        [$instanceId, $this->getTenantId(), $this->getFilialId(), $data['message_id'] ?? '', 'system', $to, $message, $messageType]
                    );
                    
                    return $data;
                }
            }
            
            throw new Exception("Baileys send message failed with code [$httpCode]: " . $response);
            
        } catch (Exception $e) {
            error_log('❌ Baileys send message failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getTenantId() {
        // Get from session or config
        return 1; // fallback
    }
    
    private function getFilialId() {
        // Get from session or config
        return 1; // fallback
    }
}