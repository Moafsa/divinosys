<?php
/**
 * Gerador de QR Code simples para WhatsApp
 * Não depende de bibliotecas externas
 */

function generateWhatsAppQR($phoneNumber, $message = 'Conectar sistema') {
    try {
        // Dados para o QR code
        $qrData = "https://wa.me/{$phoneNumber}?text=" . urlencode($message);
        
        // Usar API gratuita para gerar QR code
        $apiUrl = "https://api.qrserver.com/v1/create-qr-code/";
        $params = [
            'size' => '300x300',
            'data' => $qrData,
            'format' => 'png',
            'bgcolor' => 'FFFFFF',
            'color' => '000000'
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        
        // Fazer requisição
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $imageData = file_get_contents($url, false, $context);
        
        if ($imageData) {
            return base64_encode($imageData);
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("generateWhatsAppQR - Error: " . $e->getMessage());
        return null;
    }
}

function generateSimpleQRImage($phoneNumber, $instanceName) {
    try {
        $width = 300;
        $height = 300;
        
        // Criar imagem
        $image = imagecreate($width, $height);
        
        // Cores
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $blue = imagecolorallocate($image, 25, 118, 210);
        
        // Fundo branco
        imagefill($image, 0, 0, $white);
        
        // Borda
        imagerectangle($image, 10, 10, $width-10, $height-10, $blue);
        
        // Título
        imagestring($image, 4, 50, 50, "WhatsApp Connect", $black);
        
        // Número
        imagestring($image, 3, 50, 100, "Numero: {$phoneNumber}", $black);
        
        // Instância
        imagestring($image, 2, 50, 130, "Instancia: {$instanceName}", $black);
        
        // Instruções
        imagestring($image, 2, 50, 180, "1. Abra o WhatsApp no celular", $black);
        imagestring($image, 2, 50, 200, "2. Toque em Dispositivos conectados", $black);
        imagestring($image, 2, 50, 220, "3. Toque em Conectar um dispositivo", $black);
        imagestring($image, 2, 50, 240, "4. Escaneie este codigo", $black);
        
        // Salvar como base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return base64_encode($imageData);
        
    } catch (Exception $e) {
        error_log("generateSimpleQRImage - Error: " . $e->getMessage());
        return null;
    }
}

// Teste
if (isset($_GET['test'])) {
    $phone = $_GET['phone'] ?? '5554997092223';
    $name = $_GET['name'] ?? 'Teste';
    
    $qr1 = generateWhatsAppQR($phone);
    $qr2 = generateSimpleQRImage($phone, $name);
    
    echo "<h2>QR Code API Externa:</h2>";
    if ($qr1) {
        echo "<img src='data:image/png;base64,{$qr1}' alt='QR Code'>";
    } else {
        echo "Erro ao gerar QR code via API";
    }
    
    echo "<h2>QR Code Simples:</h2>";
    if ($qr2) {
        echo "<img src='data:image/png;base64,{$qr2}' alt='QR Code'>";
    } else {
        echo "Erro ao gerar QR code simples";
    }
}
?>
