<?php
// Script direto para corrigir o pedidos.php

$file = '/var/www/html/mvc/ajax/pedidos.php';
$content = file_get_contents($file);

// Substituir as verificações problemáticas
$content = str_replace(
    'if (!empty($item["ingredientes_adicionados"])) {',
    'if (isset($item["ingredientes_adicionados"]) && 
    $item["ingredientes_adicionados"] !== null && 
    $item["ingredientes_adicionados"] !== "undefined" &&
    $item["ingredientes_adicionados"] !== "" &&
    is_array($item["ingredientes_adicionados"]) && 
    !empty($item["ingredientes_adicionados"])) {',
    $content
);

$content = str_replace(
    'if (!empty($item["ingredientes_removidos"])) {',
    'if (isset($item["ingredientes_removidos"]) && 
    $item["ingredientes_removidos"] !== null && 
    $item["ingredientes_removidos"] !== "undefined" &&
    $item["ingredientes_removidos"] !== "" &&
    is_array($item["ingredientes_removidos"]) && 
    !empty($item["ingredientes_removidos"])) {',
    $content
);

file_put_contents($file, $content);
echo "Correção aplicada!\n";
?>