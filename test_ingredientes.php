<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

$db = \System\Database::getInstance();

echo "=== TESTANDO INGREDIENTES NO BANCO ===\n";

// Verificar último pedido
$pedido = $db->fetch("SELECT * FROM pedido ORDER BY created_at DESC LIMIT 1");
if ($pedido) {
    echo "Último pedido: ID " . $pedido['idpedido'] . "\n";
    
    $itens = $db->fetchAll("SELECT * FROM pedido_itens WHERE pedido_id = ?", [$pedido['idpedido']]);
    echo "Itens do pedido: " . count($itens) . "\n";
    
    foreach ($itens as $item) {
        echo "\n- Item ID: " . $item['id'] . ", Produto: " . $item['produto_id'] . "\n";
        echo "  Ingredientes COM: '" . ($item['ingredientes_com'] ?? 'NULL') . "'\n";
        echo "  Ingredientes SEM: '" . ($item['ingredientes_sem'] ?? 'NULL') . "'\n";
        echo "  Observação: '" . ($item['observacao'] ?? 'NULL') . "'\n";
    }
} else {
    echo "Nenhum pedido encontrado\n";
}

echo "\n=== TESTANDO ESTRUTURA DA TABELA ===\n";
$columns = $db->fetchAll("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'pedido_itens' AND column_name LIKE '%ingrediente%' ORDER BY ordinal_position");

foreach ($columns as $column) {
    echo "- " . $column['column_name'] . " (" . $column['data_type'] . ") - Nullable: " . $column['is_nullable'] . "\n";
}
?>
