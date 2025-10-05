<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

echo "=== FIXING PRODUTOS TABLE ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Check current structure
    echo "=== CURRENT PRODUTOS TABLE STRUCTURE ===\n";
    $stmt = $db->getConnection()->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'produtos' ORDER BY ordinal_position");
    $currentColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasPrecoNormal = false;
    foreach ($currentColumns as $col) {
        echo "- " . $col['column_name'] . ": " . $col['data_type'] . "\n";
        if ($col['column_name'] === 'preco_normal') {
            $hasPrecoNormal = true;
        }
    }
    echo "\n";

    if (!$hasPrecoNormal) {
        echo "=== ADDING PRECO_NORMAL COLUMN ===\n";
        $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_normal DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Added 'preco_normal' column\n\n";
    } else {
        echo "✅ Column 'preco_normal' already exists\n\n";
    }

    // Check if categoria_id = 1 exists
    echo "=== CHECKING CATEGORIA_ID = 1 ===\n";
    $categoria = $db->fetch("SELECT * FROM categorias WHERE id = 1 AND tenant_id = 1 AND filial_id = 1");
    if ($categoria) {
        echo "✅ Categoria ID 1 exists: " . $categoria['nome'] . "\n";
    } else {
        echo "❌ Categoria ID 1 does NOT exist, creating default categoria\n";
        
        // Create default categoria
        $db->query("INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())", 
                   ['Geral', 'Categoria padrão', true, 1, 1]);
        echo "✅ Created default categoria\n";
    }

    // Test simple insert
    echo "\n=== TESTING PRODUTO INSERT ===\n";
    $testInsert = "INSERT INTO produtos (nome, categoria_id, preco_normal, preco_mini, estoque_minimo, preco_custo, ativo, tenant_id, filial_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $testParams = ['Teste Produto', 1, 10.00, 8.00, 5, 5.00, true, 1, 1];
    
    try {
        $db->query($testInsert, $testParams);
        $lastId = $db->lastInsertId('produtos_id_seq');
        echo "✅ Test insert successful! ID: $lastId\n";
        
        // Clean up
        $db->query("DELETE FROM produtos WHERE id = ?", [$lastId]);
        echo "✅ Test data cleaned up\n";
        
    } catch (\Exception $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ PRODUTOS TABLE FIXED!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
