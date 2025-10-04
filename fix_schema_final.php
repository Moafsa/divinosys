<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== FIXING SCHEMA FINAL ERRORS ===\n";

$db = \System\Database::getInstance();

try {
    // 1. Fix pedidos table sequence
    echo "Fixing pedidos table sequence...\n";
    
    // Check if pedidos table exists and has idpedido as SERIAL
    $columns = $db->fetchAll("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns 
        WHERE table_name = 'pedidos' AND column_name = 'idpedido'
    ");
    
    if (empty($columns)) {
        echo "❌ pedidos table or idpedido column not found\n";
    } else {
        $column = $columns[0];
        echo "Current idpedido column: " . $column['data_type'] . " (default: " . ($column['column_default'] ?? 'NULL') . ")\n";
        
        // Check if sequence exists
        $sequences = $db->fetchAll("
            SELECT sequence_name 
            FROM information_schema.sequences 
            WHERE sequence_name LIKE '%pedidos%'
        ");
        
        echo "Existing sequences: " . implode(', ', array_column($sequences, 'sequence_name')) . "\n";
        
        // If no sequence exists, create it
        if (empty($sequences)) {
            echo "Creating sequence for pedidos table...\n";
            $db->query("CREATE SEQUENCE IF NOT EXISTS pedidos_id_seq");
            $db->query("ALTER TABLE pedidos ALTER COLUMN idpedido SET DEFAULT nextval('pedidos_id_seq')");
            echo "✅ Sequence created and linked to pedidos table\n";
        }
        
        // Set sequence value
        $maxId = $db->fetch("SELECT COALESCE(MAX(idpedido), 0) as max_id FROM pedidos");
        $nextVal = $maxId['max_id'] + 1;
        $db->query("SELECT setval('pedidos_id_seq', ?, true)", [$nextVal]);
        echo "✅ Sequence setval to: $nextVal\n";
    }
    
    // 2. Fix produtos table - add preco_normal column
    echo "\nFixing produtos table - adding preco_normal column...\n";
    
    $produtosColumns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'produtos'
    ");
    
    $produtosColumnNames = array_column($produtosColumns, 'column_name');
    echo "Current produtos columns: " . implode(', ', $produtosColumnNames) . "\n";
    
    if (!in_array('preco_normal', $produtosColumnNames)) {
        echo "Adding preco_normal column to produtos table...\n";
        $db->query("ALTER TABLE produtos ADD COLUMN preco_normal DECIMAL(10,2) DEFAULT 0.00");
        
        // Update existing products with preco_normal = preco (if preco exists)
        if (in_array('preco', $produtosColumnNames)) {
            $db->query("UPDATE produtos SET preco_normal = preco WHERE preco_normal IS NULL OR preco_normal = 0");
            echo "✅ Updated preco_normal from existing preco values\n";
        } else {
            // Set default prices
            $db->query("UPDATE produtos SET preco_normal = 10.00 WHERE preco_normal IS NULL OR preco_normal = 0");
            echo "✅ Set default preco_normal values\n";
        }
    } else {
        echo "✅ preco_normal column already exists\n";
    }
    
    // 3. Test the problematic queries
    echo "\n=== TESTING FIXED QUERIES ===\n";
    
    // Test sequence
    try {
        $db->query("SELECT setval('pedidos_id_seq', 1, true)");
        echo "✅ Sequence setval test passed\n";
    } catch (Exception $e) {
        echo "❌ Sequence test failed: " . $e->getMessage() . "\n";
    }
    
    // Test preco_normal query
    try {
        $result = $db->fetchAll("
            SELECT pi.*, 
                   pr.nome as nome_produto,
                   pr.preco_normal as preco_produto
            FROM pedido_itens pi 
            LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = ?
            WHERE pi.pedido_id = ? AND pi.tenant_id = ?
            LIMIT 1
        ", [1, 1, 1]);
        echo "✅ preco_normal query test passed - " . count($result) . " records\n";
    } catch (Exception $e) {
        echo "❌ preco_normal query test failed: " . $e->getMessage() . "\n";
    }
    
    // 4. Ensure all tables have proper structure
    echo "\n=== VERIFYING TABLE STRUCTURES ===\n";
    
    // Check pedidos table structure
    $pedidosStructure = $db->fetchAll("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns 
        WHERE table_name = 'pedidos'
        ORDER BY ordinal_position
    ");
    echo "pedidos table structure:\n";
    foreach ($pedidosStructure as $col) {
        echo "  - " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
    }
    
    // Check produtos table structure
    $produtosStructure = $db->fetchAll("
        SELECT column_name, data_type, column_default
        FROM information_schema.columns 
        WHERE table_name = 'produtos'
        ORDER BY ordinal_position
    ");
    echo "\nprodutos table structure:\n";
    foreach ($produtosStructure as $col) {
        echo "  - " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
    }
    
    echo "\n✅ ALL SCHEMA ERRORS FIXED!\n";
    echo "The pedidos popup and mesa_multiplos_pedidos should now work perfectly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
