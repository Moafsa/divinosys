<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/WhatsApp/BaileysManager.php';

use System\Config;
use System\Database;
use System\Session;
use System\WhatsApp\BaileysManager;

header('Content-Type: application/json');

try {
    // Inicializar sistema
    $config = Config::getInstance();
    $db = Database::getInstance();
    $session = Session::getInstance();
    
    // Debug temporário
    error_log('WhatsApp Debug - Session: ' . json_encode($_SESSION));
    error_log('WhatsApp Debug - IsLoggedIn: ' . ($session->isLoggedIn() ? 'true' : 'false'));
    
    // Verificar autenticação
    if (!$session->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $tenantId = $session->getTenantId();
    $filialId = $session->get('filial_id');
    
    $baileys = new BaileysManager();
    
    switch($action) {
        case 'criar_instancia':
            $instanceName = $_POST['instance_name'] ?? '';
            $phoneNumber = $_POST['phone_number'] ?? '';
            $n8nWebhook = $_POST['n8n_webhook'] ?? null;
            
            if (empty($instanceName) || empty($phoneNumber)) {
                throw new Exception('Nome da instância e número são obrigatórios');
            }
            
            $instanceId = $baileys->createInstance($tenantId, $filialId, $instanceName, $phoneNumber, $n8nWebhook);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância criada com sucesso!',
                'instance_id' => $instanceId
            ]);
            break;
            
        case 'conectar_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            $qrCode = $baileys->connectInstance($instanceId);
            
            echo json_encode([
                'success' => true,
                'message' => 'QR Code gerado com sucesso!',
                'qr_code' => $qrCode
            ]);
            break;
            
        case 'listar_instancias':
            $instances = $baileys->getInstances($tenantId, $filialId);
            
            echo json_encode([
                'success' => true,
                'instances' => $instances
            ]);
            break;
            
        case 'desconectar_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            $baileys->disconnectInstance($instanceId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância desconectada com sucesso!'
            ]);
            break;
            
        case 'deletar_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            $baileys->deleteInstance($instanceId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância removida com sucesso!'
            ]);
            break;
            
        case 'enviar_mensagem':
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
                'message' => 'Mensagem enviada com sucesso!',
                'message_id' => $result['message_id'],
                'status' => $result['status']
            ]);
            break;
            
        case 'status_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            $status = $baileys->getInstanceStatus($instanceId);
            
            echo json_encode([
                'success' => true,
                'status' => $status
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
