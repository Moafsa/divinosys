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
require_once __DIR__ . '/../../system/N8nAIService.php';
require_once __DIR__ . '/../../system/WuzapiService.php';

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
    $from = $data['from'] ?? '';
    $message = $data['message'] ?? $data['text'] ?? '';
    $instanceId = $data['instanceId'] ?? $data['instance_id'] ?? '';
    $messageType = $data['type'] ?? 'text';
    $isGroup = $data['isGroup'] ?? false;
    
    error_log("Wuzapi Webhook - From: $from, Instance: $instanceId, Type: $messageType, IsGroup: " . ($isGroup ? 'yes' : 'no'));
    
    // Ignore group messages
    if ($isGroup) {
        error_log("Wuzapi Webhook - Ignoring group message");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Group messages ignored']);
        exit;
    }
    
    // Ignore non-text messages for now
    if ($messageType !== 'text' && $messageType !== 'chat') {
        error_log("Wuzapi Webhook - Ignoring non-text message type: $messageType");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Non-text messages ignored']);
        exit;
    }
    
    // Validate required fields
    if (empty($from) || empty($message) || empty($instanceId)) {
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
    
    // Process message with AI
    $aiService = new \System\N8nAIService();
    
    // Add context for AI
    $contextData = [
        'customer_phone' => $phoneNumber,
        'customer_name' => $cliente['nome'] ?? 'Cliente WhatsApp',
        'source' => 'whatsapp',
        'instance_id' => $instanceId
    ];
    
    $aiResponse = $aiService->processMessage($message, [], $tenantId, $filialId, $contextData);
    
    if (!$aiResponse['success']) {
        throw new Exception('AI processing failed: ' . ($aiResponse['error'] ?? 'Unknown error'));
    }
    
    // Extract AI response message
    $responseMessage = $aiResponse['response']['message'] ?? $aiResponse['message'] ?? 'Desculpe, não entendi. Pode repetir?';
    
    error_log("Wuzapi Webhook - AI Response: " . substr($responseMessage, 0, 100) . "...");
    
    // Send response back via WhatsApp
    $wuzapi = new \System\WuzapiService();
    $sendResult = $wuzapi->sendMessage($from, $responseMessage);
    
    if (!$sendResult['success']) {
        error_log("Wuzapi Webhook - Failed to send response: " . $sendResult['error']);
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



