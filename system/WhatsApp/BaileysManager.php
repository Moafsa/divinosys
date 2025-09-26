<?php
namespace System\WhatsApp;

use System\Database;
use System\Config;

class BaileysManager {
    private $db;
    private $instances = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar nova instância WhatsApp
     */
    public function createInstance($tenantId, $filialId, $instanceName, $phoneNumber, $n8nWebhook = null) {
        // Verificar se nome da instância já existe
        $existing = $this->db->fetch(
            "SELECT id FROM whatsapp_instances WHERE instance_name = ?",
            [$instanceName]
        );
        
        if ($existing) {
            throw new Exception('Nome da instância já existe');
        }
        
        // Criar instância no banco
        $instanceId = $this->db->insert('whatsapp_instances', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'instance_name' => $instanceName,
            'phone_number' => $phoneNumber,
            'status' => 'disconnected',
            'n8n_webhook_url' => $n8nWebhook,
            'ativo' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $instanceId;
    }
    
    /**
     * Conectar instância (gerar QR Code)
     */
    public function connectInstance($instanceId) {
        $instance = $this->getInstance($instanceId);
        if (!$instance) {
            throw new Exception('Instância não encontrada');
        }
        
        // Atualizar status para gerando QR
        $this->db->query(
            "UPDATE whatsapp_instances SET status = 'qrcode', updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $instanceId]
        );
        
        // Gerar QR Code via Node.js
        $qrCode = $this->generateQRCode($instanceId);
        
        // Salvar QR Code no banco
        $this->db->query(
            "UPDATE whatsapp_instances SET qr_code = ?, updated_at = ? WHERE id = ?",
            [$qrCode, date('Y-m-d H:i:s'), $instanceId]
        );
        
        return $qrCode;
    }
    
    /**
     * Enviar mensagem direta (Sistema)
     */
    public function sendDirectMessage($instanceId, $to, $message, $messageType = 'text') {
        // Verificar instância
        $instance = $this->getInstance($instanceId);
        if (!$instance || $instance['status'] !== 'connected') {
            throw new Exception('Instância não está conectada');
        }
        
        // Enviar via Baileys
        $result = $this->sendViaBaileys($instanceId, $to, $message, $messageType);
        
        // Salvar no banco
        $messageId = $this->db->insert('whatsapp_messages', [
            'instance_id' => $instanceId,
            'tenant_id' => $instance['tenant_id'],
            'filial_id' => $instance['filial_id'],
            'message_id' => $result['message_id'] ?? null,
            'to_number' => $to,
            'message_text' => $message,
            'message_type' => $messageType,
            'status' => 'sent',
            'source' => 'system',
            'direction' => 'outbound',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'message_id' => $messageId,
            'status' => 'sent'
        ];
    }
    
    /**
     * Enviar para n8n (Assistente IA)
     */
    public function sendToAssistant($instanceId, $to, $message, $n8nWebhook = null) {
        // Verificar instância
        $instance = $this->getInstance($instanceId);
        if (!$instance || $instance['status'] !== 'connected') {
            throw new Exception('Instância não está conectada');
        }
        
        // Usar webhook da instância se não fornecido
        if (!$n8nWebhook) {
            $n8nWebhook = $instance['n8n_webhook_url'];
        }
        
        if (!$n8nWebhook) {
            throw new Exception('Webhook n8n não configurado');
        }
        
        // Enviar para n8n
        $data = [
            'instance_id' => $instanceId,
            'to' => $to,
            'message' => $message,
            'type' => 'assistant',
            'context' => 'customer_service'
        ];
        
        $this->sendToN8n($n8nWebhook, $data);
        
        // Salvar no banco
        $messageId = $this->db->insert('whatsapp_messages', [
            'instance_id' => $instanceId,
            'tenant_id' => $instance['tenant_id'],
            'filial_id' => $instance['filial_id'],
            'to_number' => $to,
            'message_text' => $message,
            'message_type' => 'assistant',
            'status' => 'processing',
            'source' => 'n8n',
            'direction' => 'outbound',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'message_id' => $messageId,
            'status' => 'processing'
        ];
    }
    
    /**
     * Obter instâncias do tenant
     */
    public function getInstances($tenantId, $filialId = null) {
        $sql = "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND ativo = true";
        $params = [$tenantId];
        
        if ($filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Obter status da instância
     */
    public function getInstanceStatus($instanceId) {
        $instance = $this->getInstance($instanceId);
        return $instance ? $instance['status'] : 'not_found';
    }
    
    /**
     * Desconectar instância
     */
    public function disconnectInstance($instanceId) {
        $this->db->query(
            "UPDATE whatsapp_instances SET status = 'disconnected', qr_code = NULL, session_data = NULL, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $instanceId]
        );
        
        return true;
    }
    
    /**
     * Deletar instância
     */
    public function deleteInstance($instanceId) {
        $this->db->query(
            "UPDATE whatsapp_instances SET ativo = false, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $instanceId]
        );
        
        return true;
    }
    
    /**
     * Enviar via Baileys (Node.js)
     */
    private function sendViaBaileys($instanceId, $to, $message, $messageType) {
        $command = "node " . __DIR__ . "/baileys-sender.js " . 
                   escapeshellarg($instanceId) . " " . 
                   escapeshellarg($to) . " " . 
                   escapeshellarg($message) . " " . 
                   escapeshellarg($messageType);
        
        $output = shell_exec($command);
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            throw new Exception('Erro ao enviar via Baileys: ' . ($result['error'] ?? 'Erro desconhecido'));
        }
        
        return $result;
    }
    
    /**
     * Gerar QR Code via Node.js
     */
    private function generateQRCode($instanceId) {
        $command = "node " . __DIR__ . "/baileys-qr.js " . escapeshellarg($instanceId);
        
        $output = shell_exec($command);
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            throw new Exception('Erro ao gerar QR Code: ' . ($result['error'] ?? 'Erro desconhecido'));
        }
        
        return $result['qr_code'];
    }
    
    /**
     * Enviar para n8n
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
            "SELECT * FROM whatsapp_instances WHERE id = ? AND ativo = true",
            [$instanceId]
        );
    }
}
?>
