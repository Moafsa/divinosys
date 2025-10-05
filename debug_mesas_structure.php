<?php
require_once 'system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    echo "=== ESTRUTURA DA TABELA MESAS ===" . PHP_EOL;
    $structure = $db->fetchAll("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'mesas' ORDER BY ordinal_position");
    foreach($structure as $col) {
        echo $col['column_name'] . ' - ' . $col['data_type'] . ' - ' . $col['is_nullable'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== DADOS DAS MESAS ===" . PHP_EOL;
    $mesas = $db->fetchAll('SELECT * FROM mesas ORDER BY id');
    foreach($mesas as $mesa) {
        echo 'ID: ' . $mesa['id'] . ', ID_MESA: ' . $mesa['id_mesa'] . ', NUMERO: ' . $mesa['numero'] . ', TENANT_ID: ' . $mesa['tenant_id'] . ', FILIAL_ID: ' . $mesa['filial_id'] . PHP_EOL;
    }
    
    echo PHP_EOL . "=== TESTE DE BUSCA POR ID 3 ===" . PHP_EOL;
    $mesa3 = $db->fetch("SELECT * FROM mesas WHERE id = 3");
    if ($mesa3) {
        echo "Mesa 3 encontrada: " . print_r($mesa3, true) . PHP_EOL;
    } else {
        echo "Mesa 3 NÃO encontrada" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== TESTE DE BUSCA POR ID_MESA 3 ===" . PHP_EOL;
    $mesa3_id_mesa = $db->fetch("SELECT * FROM mesas WHERE id_mesa = '3'");
    if ($mesa3_id_mesa) {
        echo "Mesa com id_mesa=3 encontrada: " . print_r($mesa3_id_mesa, true) . PHP_EOL;
    } else {
        echo "Mesa com id_mesa=3 NÃO encontrada" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . PHP_EOL;
}
?>
