<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/WhatsApp/BaileysManager.php';

use System\Config;
use System\Database;
use System\WhatsApp\BaileysManager;

header('Content-Type: application/json');

try {
    // Inicializar sistema
    $config = Config::getInstance();
    $db = Database::getInstance();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $baileys = new BaileysManager();
    
    switch($action) {
        case 'send_message':
            $instanceId = $_POST['instance_id'] ?? '';
            $to = $_POST['to'] ?? '';
            $message = $_POST['message'] ?? '';
            $messageType = $_POST['message_type'] ?? 'text';
            $source = $_POST['source'] ?? 'system'; // 'system' ou 'n8n'
            
            if (empty($instanceId) || empty($to) || empty($message)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            if ($source === 'n8n') {
                // Enviar via n8n (Assistente IA)
                $n8nWebhook = $_POST['n8n_webhook'] ?? null;
                $result = $baileys->sendToAssistant($instanceId, $to, $message, $n8nWebhook);
            } else {
                // Enviar direto (Sistema)
                $result = $baileys->sendDirectMessage($instanceId, $to, $message, $messageType);
            }
            
            echo json_encode([
                'success' => true,
                'message_id' => $result['message_id'],
                'status' => $result['status']
            ]);
            break;
            
        case 'get_instance_status':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('Instance ID é obrigatório');
            }
            
            $status = $baileys->getInstanceStatus($instanceId);
            
            echo json_encode([
                'success' => true,
                'status' => $status
            ]);
            break;
            
        case 'webhook_received':
            // Webhook recebido do WhatsApp via Baileys
            $instanceId = $_POST['instance_id'] ?? '';
            $webhookType = $_POST['webhook_type'] ?? '';
            $webhookData = $_POST['webhook_data'] ?? '';
            
            if (empty($instanceId) || empty($webhookType) || empty($webhookData)) {
                throw new Exception('Dados do webhook são obrigatórios');
            }
            
            // Buscar tenant_id e filial_id da instância
            $instance = $db->fetch(
                "SELECT tenant_id, filial_id FROM whatsapp_instances WHERE id = ?",
                [$instanceId]
            );
            
            $tenantIdWebhook = $instance['tenant_id'] ?? null;
            $filialIdWebhook = $instance['filial_id'] ?? null;
            
            if (!$tenantIdWebhook) {
                error_log("whatsapp_n8n.php - AVISO: Instância $instanceId sem tenant_id, usando tenant da sessão");
                $tenantIdWebhook = $context['tenant']['id'] ?? null;
            }
            
            // Salvar webhook no banco
            $webhookId = $db->insert('whatsapp_webhooks', [
                'instance_id' => $instanceId,
                'tenant_id' => $tenantIdWebhook,
                'filial_id' => $filialIdWebhook,
                'webhook_type' => $webhookType,
                'webhook_data' => $webhookData,
                'processed' => false,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'webhook_id' => $webhookId
            ]);
            break;
            
        default:
            throw new Exception('Ação não encontrada: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
