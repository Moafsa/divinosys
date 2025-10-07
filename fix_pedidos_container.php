<?php
// Script para aplicar a correção diretamente no container
$containerFile = '/var/www/html/mvc/ajax/pedidos.php';

// Ler o arquivo atual
$content = file_get_contents($containerFile);

if ($content === false) {
    echo "ERRO: Não foi possível ler o arquivo $containerFile\n";
    exit(1);
}

echo "Arquivo lido com sucesso. Tamanho: " . strlen($content) . " bytes\n";

// Aplicar a correção
$oldPattern = 'if (isset($item[\'ingredientes_adicionados\']) && 
                    $item[\'ingredientes_adicionados\'] !== null && 
                    $item[\'ingredientes_adicionados\'] !== \'undefined\' &&
                    is_array($item[\'ingredientes_adicionados\']) && 
                    !empty($item[\'ingredientes_adicionados\'])) {';

$newPattern = 'if (isset($item[\'ingredientes_adicionados\']) && 
                    $item[\'ingredientes_adicionados\'] !== null && 
                    $item[\'ingredientes_adicionados\'] !== \'undefined\' &&
                    $item[\'ingredientes_adicionados\'] !== \'\' &&
                    is_array($item[\'ingredientes_adicionados\']) && 
                    !empty($item[\'ingredientes_adicionados\'])) {';

$content = str_replace($oldPattern, $newPattern, $content);

// Aplicar a mesma correção para ingredientes_removidos
$oldPattern2 = 'if (isset($item[\'ingredientes_removidos\']) && 
                    $item[\'ingredientes_removidos\'] !== null && 
                    $item[\'ingredientes_removidos\'] !== \'undefined\' &&
                    is_array($item[\'ingredientes_removidos\']) && 
                    !empty($item[\'ingredientes_removidos\'])) {';

$newPattern2 = 'if (isset($item[\'ingredientes_removidos\']) && 
                    $item[\'ingredientes_removidos\'] !== null && 
                    $item[\'ingredientes_removidos\'] !== \'undefined\' &&
                    $item[\'ingredientes_removidos\'] !== \'\' &&
                    is_array($item[\'ingredientes_removidos\']) && 
                    !empty($item[\'ingredientes_removidos\'])) {';

$content = str_replace($oldPattern2, $newPattern2, $content);

// Salvar o arquivo
if (file_put_contents($containerFile, $content) === false) {
    echo "ERRO: Não foi possível salvar o arquivo $containerFile\n";
    exit(1);
}

echo "SUCESSO: Correção aplicada no container!\n";
echo "Arquivo salvo com sucesso. Tamanho: " . strlen($content) . " bytes\n";
?>
