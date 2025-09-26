<?php
// FORCE CLEAN JSON OUTPUT - NO BUFFERING 
ob_start();
while (@ob_end_clean()); // Clear all output buffers

// Disable error reporting to screen
error_reporting(E_ERROR);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/WhatsApp/BaileysManager.php';

use System\Config;
use System\Database;
use System\Session;
use System\WhatsApp\BaileysManager;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

try {
    // ENSURE CLEAN OUTPUT
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    
    // Inicializar sistema
    $config = Config::getInstance();
    $db = Database::getInstance();
    $session = Session::getInstance();
    
    // Log to file instead of output
    $debug = [
        'session_token_exists' => isset($_SESSION['user_id']),
        'logged_in' => $session->isLoggedIn(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    file_put_contents('/tmp/whatsapp_debug.log', json_encode($debug) . PHP_EOL, FILE_APPEND);
    
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
            $n8nWebhook = $_POST['webhook_url'] ?? '';
            
            if (empty($instanceName) || empty($phoneNumber)) {
                throw new Exception('Nome da instância e número são obrigatórios');
            }
            
            $result = $baileys->createInstance($instanceName, $phoneNumber, $tenantId, $filialId, $n8nWebhook);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância criada com sucesso!'
            ]);
            break;
            
        case 'conectar_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            $qrCode = $baileys->generateQRCode($instanceId);
            
            echo json_encode([
                'success' => true,
                'message' => 'QR Code gerado com sucesso!',
                'qr_code' => $qrCode
            ]);
            break;
            
        case 'listar_instancias':
            $instances = $baileys->getInstances($tenantId);
            
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
            
            // Placeholder - não temos disconnect específico ainda
            $db = Database::getInstance();
            $db->query("UPDATE whatsapp_instances SET status = 'disconnected' WHERE id = ?", [$instanceId]);
            
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
            
            if (empty($instanceId) || empty($to) || empty($message)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // Simplificado - apenas enviar
            $result = $baileys->sendDirectMessage($to, $message);
            
            echo json_encode([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'message_id' => $result['message_id']
            ]);
            break;
            
        case 'status_instancia':
            $instanceId = $_POST['instance_id'] ?? '';
            
            if (empty($instanceId)) {
                throw new Exception('ID da instância é obrigatório');
            }
            
            // Simples - apenas status para mostrar
            echo json_encode([
                'success' => true, 
                'status' => 'checking'
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
