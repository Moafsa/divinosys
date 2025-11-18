<?php
/**
 * Script para instalar suporte a Excel
 * Execute este arquivo uma vez para instalar a biblioteca PhpSpreadsheet
 */

echo "Instalando suporte a Excel...\n";

// Verificar se o Composer está disponível
if (!file_exists('composer.json')) {
    // Criar composer.json
    $composerJson = [
        "require" => [
            "phpoffice/phpspreadsheet" => "^1.29"
        ],
        "autoload" => [
            "psr-4" => [
                "App\\" => "src/"
            ]
        ]
    ];
    
    file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT));
    echo "Arquivo composer.json criado.\n";
}

// Verificar se o Composer está instalado
$composerPath = '';
if (is_executable('composer')) {
    $composerPath = 'composer';
} elseif (is_executable('composer.phar')) {
    $composerPath = 'composer.phar';
} else {
    echo "Composer não encontrado. Instalando manualmente...\n";
    
    // Download direto da biblioteca
    $phpspreadsheetUrl = 'https://github.com/PHPOffice/PhpSpreadsheet/archive/refs/heads/master.zip';
    $zipFile = 'phpspreadsheet.zip';
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $phpspreadsheetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($ch);
        curl_close($ch);
        
        file_put_contents($zipFile, $data);
        echo "PhpSpreadsheet baixado.\n";
        
        // Extrair
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo('./');
                $zip->close();
                echo "PhpSpreadsheet extraído.\n";
                unlink($zipFile);
            }
        }
    } else {
        echo "Erro: cURL não disponível para download automático.\n";
        echo "Por favor, instale manualmente:\n";
        echo "1. Baixe PhpSpreadsheet de: https://github.com/PHPOffice/PhpSpreadsheet\n";
        echo "2. Extraia para a pasta vendor/PhpOffice/PhpSpreadsheet\n";
    }
}

echo "Suporte a Excel instalado!\n";
echo "Agora você pode usar a exportação para Excel.\n";
?>
