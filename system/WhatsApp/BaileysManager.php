<?php

namespace System\WhatsApp;

use System\Database;
use System\Config;
use Exception;

require_once __DIR__ . '/WuzAPIManager.php';

class BaileysManager {
    private $db;
    private $wuzapiManager;

    public function __construct() {
        $this->db = Database::getInstance();
        error_log("BaileysManager::__construct - Carregando WuzAPIManager");
        $this->wuzapiManager = new WuzAPIManager();
        error_log("BaileysManager::__construct - WuzAPIManager carregado");
    }

    /**
     * Criar nova instância WhatsApp
     */
    public function createInstance($instanceName, $phoneNumber, $tenantId, $filialId = 1, $webhookUrl = '') {
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

            // Criar instância na WuzAPI primeiro
            error_log("BaileysManager::createInstance - Chamando WuzAPIManager::createInstance");
            $wuzapiResult = $this->wuzapiManager->createInstance($instanceName, $phoneNumber, $webhookUrl);
            error_log("BaileysManager::createInstance - Resultado WuzAPI: " . json_encode($wuzapiResult));
            
            if (!$wuzapiResult || !$wuzapiResult['success']) {
                throw new Exception('Falha ao criar instância na WuzAPI: ' . ($wuzapiResult['message'] ?? 'Erro desconhecido'));
            }
            
            // Criar instância no banco com dados da WuzAPI
            $this->db->query(
                "INSERT INTO whatsapp_instances (tenant_id, filial_id, instance_name, phone_number, status, wuzapi_instance_id, wuzapi_token, ativo) VALUES (?, ?, ?, ?, 'disconnected', ?, ?, true)",
                [$tenantId, $filialId, $instanceName, $phoneNumber, $wuzapiResult['instance_id'], $wuzapiResult['token']]
            );
            
            $instanceId = $this->db->lastInsertId();
            
            error_log("BaileysManager::createInstance - Instância criada na WuzAPI com ID: " . $wuzapiResult['instance_id']);

            return [
                'success' => true,
                'message' => 'Instância criada com sucesso',
                'instance_id' => $instanceId,
                'wuzapi_instance_id' => $wuzapiResult['instance_id'],
                'wuzapi_token' => $wuzapiResult['token']
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
            error_log("BaileysManager::getInstances - Tenant ID: " . $tenantId);
            
            $instances = $this->db->fetchAll(
                "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND ativo = true ORDER BY created_at DESC",
                [$tenantId]
            );
            
            error_log("BaileysManager::getInstances - Instâncias encontradas no DB: " . count($instances));

            return array_map(function($instance) {
                return [
                    'id' => $instance['id'],
                    'instance_name' => $instance['instance_name'],
                    'phone_number' => $instance['phone_number'],
                    'status' => $instance['status'] === 'connected' ? 'connected' : 'disconnected',
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
            // Buscar dados da instância
            $instance = $this->db->query("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance) {
                throw new Exception('Instância não encontrada');
            }
            
            // Deletar na WuzAPI usando o wuzapi_instance_id
            try {
                if (!empty($instance['wuzapi_instance_id'])) {
                    $this->wuzapiManager->deleteInstance($instance['wuzapi_instance_id']);
                    error_log("BaileysManager::deleteInstance - Instância deletada na WuzAPI");
                }
            } catch (Exception $e) {
                error_log("Erro ao deletar na WuzAPI: " . $e->getMessage());
            }
            
            // Deletar registros locais
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
     * Gerar QR code via WuzAPI
     */
    public function generateQRCode($instanceId) {
        error_log('BaileysManager::generateQRCode - Iniciando para ID: ' . $instanceId);
        
        try {
            // Buscar instância no banco
            $instance = $this->db->query("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance) {
                throw new Exception('Instância não encontrada');
            }

            // Gerar QR code via WuzAPI
            $qrData = $this->wuzapiManager->generateQRCode($instanceId);
            
            if ($qrData && $qrData['success']) {
                // Atualizar status da instância
                $this->updateInstanceStatus($instanceId, 'connecting');
                
                return [
                    'success' => true,
                    'qr_code' => $qrData['qr_code'],
                    'status' => $qrData['status'],
                    'message' => 'QR code gerado com sucesso. Escaneie com seu WhatsApp.',
                    'instance_id' => $instanceId
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR code via WuzAPI',
                'instance_id' => $instanceId
            ];
            
        } catch (Exception $e) {
            error_log('BaileysManager::generateQRCode - ERRO: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar status da instância via WuzAPI
     */
    public function checkInstanceStatus($instanceId) {
        try {
            $instance = $this->db->fetch(
                "SELECT * FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            if (!$instance) {
                return [
                    'success' => false,
                    'error' => 'Instância não encontrada',
                    'status' => 'unavailable'
                ];
            }
            
            // Verificar status via WuzAPI
            $status = $this->wuzapiManager->getInstanceStatus($instanceId);
            
            return [
                'success' => true,
                'status' => $status['status'] ?? 'unknown',
                'wuzapi_integration' => true
            ];
            
        } catch (Exception $e) {
            error_log('BaileysManager::checkInstanceStatus - Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'unavailable'
            ];
        }
    }
    
    /**
     * Atualizar status da instância
     */
    private function updateInstanceStatus($instanceId, $status) {
        try {
                    $this->db->query(
                "UPDATE whatsapp_instances SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$status, $instanceId]
            );
        } catch (Exception $e) {
            error_log("BaileysManager::updateInstanceStatus - Error: " . $e->getMessage());
        }
    }

    /**
     * Buscar instância por ID
     */
    private function getInstance($instanceId) {
        try {
            return $this->db->fetch(
                "SELECT * FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
        } catch (Exception $e) {
            error_log("BaileysManager::getInstance - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sincronizar status da instância
     */
    public function syncInstanceStatus($instanceId) {
        try {
            error_log("BaileysManager::syncInstanceStatus - Sincronizando status para ID: $instanceId");
            
            return $this->wuzapiManager->syncInstanceStatus($instanceId);
        } catch (Exception $e) {
            error_log("BaileysManager::syncInstanceStatus - Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Enviar mensagem via WuzAPI
     */
    public function sendMessage($instanceId, $phoneNumber, $message) {
        try {
            error_log("BaileysManager::sendMessage - Enviando mensagem para $phoneNumber via instância $instanceId");
            
            return $this->wuzapiManager->sendMessage($instanceId, $phoneNumber, $message);
        } catch (Exception $e) {
            error_log("BaileysManager::sendMessage - Error: " . $e->getMessage());
            throw $e;
        }
    }
}