<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

try {
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();
    
    // Testar uma query simples
    $result = $db->fetch("SELECT COUNT(*) as total FROM pedido");
    echo "Total de pedidos: " . $result['total'] . "\n";
    
    // Testar se o pedido 37 existe
    $pedido = $db->fetch("SELECT * FROM pedido WHERE idpedido = ?", [37]);
    if ($pedido) {
        echo "Pedido 37 encontrado: " . json_encode($pedido) . "\n";
    } else {
        echo "Pedido 37 não encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>