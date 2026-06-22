<?php
/**
 * Wuzapi Webhook Handler
 * 
 * Receives messages from WhatsApp via Wuzapi and processes with AI
 */

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/OpenAIService.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';

header('Content-Type: application/json');

try {
    // Get raw input
    $input = file_get_contents('php://input');
    error_log("Wuzapi Webhook - Raw input: " . $input);
    
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Extract data from Wuzapi webhook
    $from = '';
    $message = '';
    $isGroup = false;
    $instanceId = '';
    $messageType = 'text';

    if (isset($data['event']) && isset($data['data']['Info'])) {
        // WuzAPI format
        $from = $data['data']['Info']['MessageSource']['Sender'] ?? '';
        $isGroup = $data['data']['Info']['MessageSource']['IsGroup'] ?? false;
        
        if (isset($data['data']['Message']['conversation'])) {
            $message = $data['data']['Message']['conversation'];
        } elseif (isset($data['data']['Message']['extendedTextMessage']['text'])) {
            $message = $data['data']['Message']['extendedTextMessage']['text'];
        } elseif (isset($data['data']['Message']['audioMessage'])) {
            $messageType = 'audio';
            $message = ''; // Será preenchido com a transcrição
        } else {
            $messageType = 'other'; // non-text
        }
        
        $instanceId = $data['instanceId'] ?? '';
        
        // Ignore messages sent by the bot itself
        if (!empty($data['data']['Info']['MessageSource']['IsFromMe'])) {
            error_log("Wuzapi Webhook - Ignoring message from me");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Self messages ignored']);
            exit;
        }
    } else {
        // Old/Fallback format
        $from = $data['from'] ?? '';
        $message = $data['message'] ?? $data['text'] ?? '';
        $instanceId = $data['instanceId'] ?? $data['instance_id'] ?? '';
        $messageType = $data['type'] ?? 'text';
        $isGroup = $data['isGroup'] ?? false;
    }
    
    error_log("Wuzapi Webhook - From: $from, Instance: $instanceId, Type: $messageType, IsGroup: " . ($isGroup ? 'yes' : 'no'));
    
    // Ignore group messages
    if ($isGroup) {
        error_log("Wuzapi Webhook - Ignoring group message");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Group messages ignored']);
        exit;
    }
    
    // Ignore non-text and non-audio messages for now
    if ($messageType !== 'text' && $messageType !== 'chat' && $messageType !== 'audio') {
        error_log("Wuzapi Webhook - Ignoring non-text/audio message type: $messageType");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Non-text messages ignored']);
        exit;
    }
    
    // Validate required fields
    if (empty($from) || empty($instanceId) || ($messageType !== 'audio' && empty($message))) {
        throw new Exception('Missing required fields: from, message, or instanceId');
    }
    
    // Get database connection
    $db = \System\Database::getInstance();
    
    // Find tenant/filial by WhatsApp instance
    $instance = $db->fetch(
        "SELECT tenant_id, filial_id, nome FROM whatsapp_instances WHERE instance_id = ? OR phone = ?",
        [$instanceId, $instanceId]
    );
    
    if (!$instance) {
        error_log("Wuzapi Webhook - Instance not found: $instanceId");
        
        // Try to find any active instance for fallback
        $instance = $db->fetch(
            "SELECT tenant_id, filial_id, nome FROM whatsapp_instances WHERE ativo = true ORDER BY id LIMIT 1"
        );
        
        if (!$instance) {
            throw new Exception('No active WhatsApp instance found');
        }
        
        error_log("Wuzapi Webhook - Using fallback instance: {$instance['nome']}");
    }
    
    $tenantId = $instance['tenant_id'];
    $filialId = $instance['filial_id'];
    
    error_log("Wuzapi Webhook - Using Tenant: $tenantId, Filial: $filialId");
    
    // Extract phone number (remove @c.us suffix)
    $phoneNumber = str_replace('@c.us', '', $from);
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Check if customer exists, create if not
    $cliente = $db->fetch(
        "SELECT id, nome, telefone FROM clientes WHERE telefone = ? AND tenant_id = ?",
        [$phoneNumber, $tenantId]
    );
    
    if (!$cliente) {
        // Create new customer
        $clienteId = $db->insert('clientes', [
            'nome' => 'Cliente WhatsApp ' . substr($phoneNumber, -4),
            'telefone' => $phoneNumber,
            'whatsapp' => $phoneNumber,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'ativo' => true
        ]);
        
        error_log("Wuzapi Webhook - New customer created: ID $clienteId");
    } else {
        error_log("Wuzapi Webhook - Existing customer: {$cliente['nome']}");
    }
    
    // Check if phone number is Admin
    $isAdmin = $db->exists("whatsapp_admins", "tenant_id = ? AND filial_id = ? AND telefone = ? AND ativo = true", [$tenantId, $filialId, $phoneNumber]);

    // Process message with AI
    $aiService = new \System\OpenAIService();
    
    // Handle audio message
    if ($messageType === 'audio') {
        error_log("Wuzapi Webhook - Recebeu áudio. Tentando transcrever...");
        
        // Aqui deve entrar a lógica para baixar o mediaMessage (via WuzAPI /chat/download)
        // Por ora, vamos simular que o áudio foi baixado para um arquivo temp
        // Se a WuzAPI mandar em base64: $audioData = base64_decode($data['data']['Message']['audioMessage']['fileSha256']);
        $wuzapi = new \System\WhatsApp\WuzAPIManager();
        $messageId = $data['data']['Info']['Id'] ?? '';
        
        // Exemplo: O ideal é ter um $wuzapi->downloadMedia($instance['id'], $messageId)
        // Como não temos a info exata da WuzAPI, vamos salvar e enviar
        // Para testes, o garçom precisaria ter mandado áudio. Se o Whisper falhar, retornará erro amigável.
        
        $tempAudioPath = sys_get_temp_dir() . '/wuzapi_audio_' . uniqid() . '.ogg';
        
        // TODO: Implementar o download real do áudio da WuzAPI para $tempAudioPath
        // file_put_contents($tempAudioPath, $audioRawData);
        
        // Mocking transcription (se falhar o arquivo físico, a IA retorna erro normal)
        $transcription = $aiService->transcribeAudio($tempAudioPath);
        
        if ($transcription['success']) {
            $message = $transcription['text'];
            error_log("Wuzapi Webhook - Áudio transcrito: " . $message);
        } else {
            $message = "O cliente ou administrador enviou um áudio, mas eu não consegui transcrever/baixar. Diga a ele que não conseguiu entender o áudio.";
            error_log("Wuzapi Webhook - Falha na transcrição: " . $transcription['message']);
        }
        
        // Cleanup
        if (file_exists($tempAudioPath)) {
            @unlink($tempAudioPath);
        }
    }
    
    // Add context for AI
    $contextData = [
        'customer_phone' => $phoneNumber,
        'customer_name' => $cliente['nome'] ?? 'Cliente WhatsApp',
        'source' => 'whatsapp',
        'instance_id' => $instanceId,
        'is_admin' => $isAdmin
    ];
    
    $aiResponse = $aiService->processWhatsAppMessage($message, $tenantId, $filialId, $contextData);
    
    if (!$aiResponse['success']) {
        throw new Exception('AI processing failed: ' . ($aiResponse['error'] ?? 'Unknown error'));
    }
    
    // Extract AI response message
    $responseMessage = $aiResponse['response']['message'] ?? $aiResponse['message'] ?? 'Desculpe, não entendi. Pode repetir?';
    
    error_log("Wuzapi Webhook - AI Response: " . substr($responseMessage, 0, 100) . "...");
    
    // Send response back via WhatsApp
    $wuzapi = new \System\WhatsApp\WuzAPIManager();
    // Using $instance['id'] as it expects the DB instance ID to fetch the token
    $sendResult = $wuzapi->sendMessage($instance['id'], $phoneNumber, $responseMessage);
    
    if (!$sendResult['success']) {
        error_log("Wuzapi Webhook - Failed to send response: " . ($sendResult['message'] ?? 'Unknown error'));
    }
    
    // Log interaction
    $db->insert('whatsapp_messages', [
        'instance_id' => $instanceId,
        'phone' => $phoneNumber,
        'message_in' => $message,
        'message_out' => $responseMessage,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'processed' => true,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Message processed and response sent',
        'phone' => $phoneNumber
    ]);
    
} catch (Exception $e) {
    error_log("Wuzapi Webhook - Error: " . $e->getMessage());
    error_log("Wuzapi Webhook - Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}



