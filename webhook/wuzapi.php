<?php
/**
 * Webhook para receber eventos da WuzAPI
 * Endpoint: /webhook/wuzapi.php
 */

header('Content-Type: application/json');

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Ler dados do webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    error_log("WuzAPI Webhook received: " . json_encode($data));
    
    // Processar evento
    $eventType = $data['event'] ?? 'unknown';
    $instanceId = $data['instance_id'] ?? null;
    
    switch ($eventType) {
        case 'qr':
            handleQREvent($data);
            break;
            
        case 'status':
            handleStatusEvent($data);
            break;
            
        case 'message':
            handleMessageEvent($data);
            break;
            
        default:
            error_log("WuzAPI Webhook - Unknown event type: {$eventType}");
    }
    
    // Responder com sucesso
    echo json_encode(['success' => true, 'message' => 'Event processed']);
    
} catch (Exception $e) {
    error_log("WuzAPI Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Processar evento de QR code
 */
function handleQREvent($data) {
    $instanceId = $data['instance_id'] ?? null;
    $qrCode = $data['qrcode'] ?? null;
    
    if ($instanceId && $qrCode) {
        error_log("WuzAPI QR Event - Instance: {$instanceId}, QR available");
        
        // Atualizar status no banco
        updateInstanceStatus($instanceId, 'connecting', $qrCode);
    }
}

/**
 * Processar evento de status
 */
function handleStatusEvent($data) {
    $instanceId = $data['instance_id'] ?? null;
    $status = $data['status'] ?? 'unknown';
    $connected = $data['connected'] ?? false;
    
    if ($instanceId) {
        error_log("WuzAPI Status Event - Instance: {$instanceId}, Status: {$status}, Connected: " . ($connected ? 'true' : 'false'));
        
        $dbStatus = $connected ? 'connected' : 'disconnected';
        updateInstanceStatus($instanceId, $dbStatus);
    }
}

/**
 * Processar evento de mensagem
 */
function handleMessageEvent($data) {
    $instanceId = $data['instance_id'] ?? null;
    $message = $data['message'] ?? null;
    
    if ($instanceId && $message) {
        error_log("WuzAPI Message Event - Instance: {$instanceId}, Message received");
        
        // Processar mensagem recebida
        processReceivedMessage($instanceId, $message);
    }
}

/**
 * Atualizar status da instância no banco
 */
function updateInstanceStatus($instanceId, $status, $qrCode = null) {
    try {
        require_once __DIR__ . '/../system/Database.php';
        
        $db = \System\Database::getInstance();
        
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($qrCode) {
            $updateData['qr_code'] = $qrCode;
        }
        
        $db->update(
            'whatsapp_instances',
            $updateData,
            'id = ?',
            [$instanceId]
        );
        
        error_log("WuzAPI - Instance {$instanceId} status updated to {$status}");
        
    } catch (Exception $e) {
        error_log("WuzAPI - Error updating instance status: " . $e->getMessage());
    }
}

/**
 * Processar mensagem recebida
 */
function processReceivedMessage($instanceId, $message) {
    try {
        // Aqui você pode processar a mensagem recebida
        // Por exemplo, salvar no banco, enviar para n8n, etc.
        
        error_log("WuzAPI - Processing message for instance {$instanceId}: " . json_encode($message));
        
    } catch (Exception $e) {
        error_log("WuzAPI - Error processing message: " . $e->getMessage());
    }
}
?>
