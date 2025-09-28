<?php
// Teste simples para verificar se há erros PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    echo "Testando QRCodeGenerator...\n";
    
    require_once 'system/Config.php';
    require_once 'system/Database.php';
    require_once 'system/WhatsApp/QRCodeGenerator.php';
    
    echo "Classes carregadas com sucesso\n";
    
    $qrGenerator = new \System\WhatsApp\QRCodeGenerator();
    echo "QRCodeGenerator instanciado\n";
    
    $result = $qrGenerator->generateQRCode(31, '5554997092223', 'Teste');
    echo "QR gerado: " . json_encode($result) . "\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
