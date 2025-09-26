<?php
/**
 * Webhook do Chatwoot para processar eventos
 */

require_once __DIR__ . '/../system/Config.php';
require_once __DIR__ . '/../system/Database.php';
require_once __DIR__ . '/../system/WhatsApp/ChatwootManager.php';

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Obter dados do webhook
$input = file_get_contents('php://input');
$webhookData = json_decode($input, true);

if (!$webhookData) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Log do webhook recebido
error_log("Chatwoot Webhook Received: " . json_encode($webhookData));

try {
    $chatwootManager = new ChatwootManager();
    $result = $chatwootManager->processWebhook($webhookData);
    
    if ($result) {
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Processing failed']);
    }
} catch (Exception $e) {
    error_log("Chatwoot Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
