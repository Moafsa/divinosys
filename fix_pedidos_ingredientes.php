<?php
// Script para corrigir o problema dos ingredientes undefined no pedidos.php

$file = '/var/www/html/mvc/ajax/pedidos.php';
$content = file_get_contents($file);

// Encontrar e substituir as verificações de ingredientes
$patterns = [
    'if (!empty($item["ingredientes_adicionados"])) {' => 'if (isset($item["ingredientes_adicionados"]) && 
    $item["ingredientes_adicionados"] !== null && 
    $item["ingredientes_adicionados"] !== "undefined" &&
    $item["ingredientes_adicionados"] !== "" &&
    is_array($item["ingredientes_adicionados"]) && 
    !empty($item["ingredientes_adicionados"])) {',
    
    'if (!empty($item["ingredientes_removidos"])) {' => 'if (isset($item["ingredientes_removidos"]) && 
    $item["ingredientes_removidos"] !== null && 
    $item["ingredientes_removidos"] !== "undefined" &&
    $item["ingredientes_removidos"] !== "" &&
    is_array($item["ingredientes_removidos"]) && 
    !empty($item["ingredientes_removidos"])) {'
];

foreach ($patterns as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Adicionar verificação para nome do ingrediente
$content = str_replace(
    'foreach ($item["ingredientes_adicionados"] as $ing) {',
    'foreach ($item["ingredientes_adicionados"] as $ing) {
                if (isset($ing["nome"]) && !empty($ing["nome"])) {',
    $content
);

$content = str_replace(
    'foreach ($item["ingredientes_removidos"] as $ing) {',
    'foreach ($item["ingredientes_removidos"] as $ing) {
                if (isset($ing["nome"]) && !empty($ing["nome"])) {',
    $content
);

// Fechar os ifs
$content = str_replace(
    '                    $ingredientesCom[] = $ing["nome"];',
    '                    $ingredientesCom[] = $ing["nome"];
                }',
    $content
);

$content = str_replace(
    '                    $ingredientesSem[] = $ing["nome"];',
    '                    $ingredientesSem[] = $ing["nome"];
                }',
    $content
);

file_put_contents($file, $content);
echo "Arquivo pedidos.php corrigido com sucesso!\n";
?>
