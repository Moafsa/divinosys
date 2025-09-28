<?php
header('Content-Type: application/json');

try {
    // Teste direto da API externa
    $phoneNumber = '5554997092223';
    $qrData = "https://wa.me/{$phoneNumber}?text=Conectar%20sistema";
    $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);
    
    error_log("Testando API: " . $apiUrl);
    
    // Usar cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $imageData) {
        $base64 = base64_encode($imageData);
        echo json_encode([
            'success' => true,
            'message' => 'QR code gerado com sucesso',
            'http_code' => $httpCode,
            'image_size' => strlen($imageData),
            'base64_size' => strlen($base64),
            'qr_code' => $base64
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Falha ao gerar QR code',
            'http_code' => $httpCode,
            'error' => $error,
            'url' => $apiUrl
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
