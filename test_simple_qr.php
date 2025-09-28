<?php
header('Content-Type: application/json');

try {
    require_once 'system/Config.php';
    require_once 'system/Database.php';
    require_once 'system/WhatsApp/SimpleQRGenerator.php';
    
    $instanceId = 31;
    $phoneNumber = '5554997092223';
    $instanceName = 'Teste';
    
    $qrGenerator = new \System\WhatsApp\SimpleQRGenerator();
    $result = $qrGenerator->generateQRCode($instanceId, $phoneNumber, $instanceName);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
