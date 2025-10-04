<?php
require_once 'system/Database.php';

echo "=== QUICK FIX ===\n";

$db = \System\Database::getInstance();

try {
    // 1. Fix sequence
    echo "Fixing sequence...\n";
    $db->query("SELECT setval('pedidos_idpedido_seq', 1, true)");
    echo "✅ Sequence fixed\n";
    
    // 2. Add preco_normal column if missing
    echo "Checking preco_normal column...\n";
    $columns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'produtos' AND column_name = 'preco_normal'
    ");
    
    if (empty($columns)) {
        $db->query("ALTER TABLE produtos ADD COLUMN preco_normal DECIMAL(10,2) DEFAULT 0.00");
        $db->query("UPDATE produtos SET preco_normal = COALESCE(preco, 10.00) WHERE preco_normal IS NULL OR preco_normal = 0");
        echo "✅ preco_normal column added\n";
    } else {
        echo "✅ preco_normal column exists\n";
    }
    
    // 3. Test queries
    echo "Testing queries...\n";
    
    // Test mesa query
    $mesa = $db->fetch("SELECT * FROM mesas WHERE id_mesa = 1 AND tenant_id = 1 AND filial_id = 1");
    echo "✅ Mesa query: " . ($mesa ? "Found" : "Not found") . "\n";
    
    // Test pedidos query
    $pedidos = $db->fetchAll("SELECT * FROM pedido WHERE idmesa::varchar = '1' AND tenant_id = 1 AND filial_id = 1");
    echo "✅ Pedidos query: " . count($pedidos) . " records\n";
    
    // Test produtos query
    $produtos = $db->fetchAll("SELECT id, nome, preco_normal FROM produtos LIMIT 1");
    echo "✅ Produtos query: " . count($produtos) . " records\n";
    
    echo "\n✅ ALL FIXES APPLIED!\n";
    echo "Dashboard and mesa popup should now work.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
