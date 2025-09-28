<?php
header('Content-Type: application/json');

try {
    require_once 'system/Config.php';
    require_once 'system/Database.php';
    require_once 'system/WhatsApp/BaileysManager.php';
    
    $instanceId = 31; // ID da instância que está sendo testada
    
    $baileysManager = new \System\WhatsApp\BaileysManager();
    $result = $baileysManager->generateQRCode($instanceId);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
