<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

$db = \System\Database::getInstance();

echo "=== VERIFICANDO TABELAS ===\n";

// Verificar todas as tabelas
$tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");

echo "Tabelas encontradas:\n";
foreach ($tables as $table) {
    echo "- " . $table['table_name'] . "\n";
}

echo "\n=== VERIFICANDO ESTRUTURA DA TABELA PEDIDO_ITENS ===\n";
$columns = $db->fetchAll("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'pedido_itens' ORDER BY ordinal_position");

foreach ($columns as $column) {
    echo "- " . $column['column_name'] . " (" . $column['data_type'] . ")\n";
}

echo "\n=== VERIFICANDO DADOS DE UM PEDIDO ===\n";
$pedido = $db->fetch("SELECT * FROM pedido ORDER BY created_at DESC LIMIT 1");
if ($pedido) {
    echo "Último pedido: ID " . $pedido['idpedido'] . "\n";
    
    $itens = $db->fetchAll("SELECT * FROM pedido_itens WHERE pedido_id = ?", [$pedido['idpedido']]);
    echo "Itens do pedido: " . count($itens) . "\n";
    
    foreach ($itens as $item) {
        echo "- Item ID: " . $item['id'] . ", Produto: " . $item['produto_id'] . ", Qtd: " . $item['quantidade'] . "\n";
        echo "  Observação: " . ($item['observacao'] ?? 'Nenhuma') . "\n";
    }
}
?>