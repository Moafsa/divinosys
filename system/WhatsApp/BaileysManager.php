<?php

namespace System\WhatsApp;

use System\Database;
use System\Config;
use Exception;

require_once __DIR__ . '/ChatwootManager.php';

class BaileysManager {
    private $db;
    private $chatwootManager;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->chatwootManager = new ChatwootManager();
    }

    /**
     * Criar nova instância WhatsApp
     */
    public function createInstance($instanceName, $phoneNumber, $tenantId, $filialId = 1, $webhookUrl = '', $email = '') {
        error_log("BaileysManager::createInstance - Criando $instanceName / $phoneNumber");
        
        try {
            // Garantir valores padrão válidos
            $tenantId = $tenantId ?: 1;
            $filialId = $filialId ?: 1;
            
            error_log("BaileysManager::createInstance - Tenant: $tenantId, Filial: $filialId, Webhook: $webhookUrl");
            // Formatar telefone com + se necessário
            if (!str_starts_with($phoneNumber, '+')) {
                $phoneNumber = '+' . $phoneNumber;
            }
            
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

            // Criar instância no banco primeiro
            $this->db->query(
                "INSERT INTO whatsapp_instances (tenant_id, filial_id, instance_name, phone_number, status, webhook_url, ativo) VALUES (?, ?, ?, ?, 'disconnected', ?, true)",
                [$tenantId, $filialId, $instanceName, $phoneNumber, $webhookUrl]
            );
            
            $instanceId = $this->db->lastInsertId();
            
            // Criar setup completo no Chatwoot se email fornecido
            if (!empty($email)) {
                $chatwootSetup = $this->chatwootManager->createCompleteChatwootSetup(
                    $filialId,
                    $instanceName,
                    $email,
                    $phoneNumber
                );
                
                if ($chatwootSetup && $chatwootSetup['success']) {
                    // Salvar referências do Chatwoot no banco
                    $this->db->query(
                        "UPDATE whatsapp_instances SET 
                         status = 'connected',
                         chatwoot_account_id = ?,
                         chatwoot_user_id = ?,
                         chatwoot_inbox_id = ?,
                         webhook_url = ?
                         WHERE id = ?",
                        [
                            $chatwootSetup['account_id'],
                            $chatwootSetup['user']['id'],
                            $chatwootSetup['inbox']['id'],
                            $chatwootSetup['webhook']['webhook_url'] ?? '',
                            $instanceId
                        ]
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'Caixa de entrada criada com sucesso no Chatwoot',
                        'instance_id' => $instanceId,
                        'chatwoot_setup' => $chatwootSetup
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Instância criada com sucesso',
                'instance_id' => $instanceId
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
     * Conectar instância via Chatwoot
     */
    public function generateQRCode($instanceId) {
        error_log('BaileysManager::generateQRCode - Iniciando para ID: ' . $instanceId);
        
        try {
            // Obtém instância
            $instance = $this->db->fetch(
                "SELECT instance_name, phone_number, status, filial_id FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            if (!$instance) {
                throw new Exception('Instância não encontrada');
            }

            $phoneNumber = $instance['phone_number'];
            $instanceName = $instance['instance_name'];
            $filialId = $instance['filial_id'];
            
            error_log("📱 Generate QR via Chatwoot for $phoneNumber) inst. $instanceName (ID: $instanceId)");
            
            // Criar usuário no Chatwoot se não existir
            $chatwootUser = $this->chatwootManager->createChatwootUser(
                $filialId,
                $instanceName,
                "whatsapp_{$instanceName}@divinolanches.com",
                $phoneNumber
            );
            
            if (!$chatwootUser) {
                throw new Exception('Falha ao criar usuário no Chatwoot');
            }
            
            // Criar inbox do WhatsApp
            $inbox = $this->chatwootManager->createWhatsAppInbox($filialId, $instanceName);
            
            if (!$inbox) {
                throw new Exception('Falha ao criar inbox no Chatwoot');
            }
            
            // Atualizar status da instância
            $this->db->query(
                "UPDATE whatsapp_instances SET status = 'connected', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$instanceId]
            );
            
            // Retornar URL do Chatwoot para o estabelecimento
            $chatwootUrl = $_ENV['CHATWOOT_URL'] ?? 'https://your-chatwoot-instance.com';
            return [
                'success' => true,
                'chatwoot_url' => $chatwootUrl,
                'user_id' => $chatwootUser['id'],
                'inbox_id' => $inbox['id'],
                'message' => 'WhatsApp conectado via Chatwoot'
            ];
            
        } catch (Exception $e) {
            error_log('BaileysManager::generateQRCode - ERRO: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar mensagem via Chatwoot
     */
    public function sendMessage($instanceId, $to, $message, $messageType = 'text') {
        try {
            // Obter dados da instância
            $instance = $this->db->fetch(
                "SELECT filial_id FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            if (!$instance) {
                throw new Exception('Instância não encontrada');
            }
            
            $filialId = $instance['filial_id'];
            
            // Obter conversa ativa para este estabelecimento
            $conversations = $this->chatwootManager->getConversations($filialId);
            
            if (empty($conversations)) {
                throw new Exception('Nenhuma conversa ativa encontrada');
            }
            
            $conversationId = $conversations[0]['id'];
            
            // Enviar mensagem via Chatwoot
            $result = $this->chatwootManager->sendMessage($conversationId, $message, 'outgoing');
            
            if ($result) {
                // Log da mensagem no banco
                $this->db->query(
                    "INSERT INTO whatsapp_messages (instance_id, tenant_id, filial_id, from_number, to_number, message_text, message_type, status, source) VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', 'chatwoot')",
                    [$instanceId, $this->getTenantId(), $filialId, 'system', $to, $message, $messageType]
                );
                
                return [
                    'success' => true,
                    'message_id' => $result['id'] ?? 'chatwoot_' . time(),
                    'status' => 'sent'
                ];
            }
            
            throw new Exception('Falha ao enviar mensagem via Chatwoot');
            
        } catch (Exception $e) {
            error_log('❌ Chatwoot send message failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter status da instância via Chatwoot
     */
    public function checkInstanceStatus($instanceId) {
        try {
            $instance = $this->db->fetch(
                "SELECT filial_id FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            if (!$instance) {
                return [
                    'success' => false,
                    'error' => 'Instância não encontrada',
                    'status' => 'unavailable'
                ];
            }
            
            $filialId = $instance['filial_id'];
            $conversations = $this->chatwootManager->getConversations($filialId);
            
            return [
                'success' => true,
                'status' => 'connected',
                'conversations_count' => count($conversations),
                'chatwoot_integration' => true
            ];
            
        } catch (Exception $e) {
            error_log('❌ Chatwoot status check failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'unavailable'
            ];
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
    
    private function getTenantId() {
        // Get from session or config
        return 1; // fallback
    }
    
    private function getFilialId() {
        // Get from session or config
        return 1; // fallback
    }
}