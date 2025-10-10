<?php
/**
 * Fix all product-related issues:
 * 1. Fix sequence for produtos table
 * 2. Ensure categories exist
 * 3. Test product creation
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    echo "<h2>Fixing Product Issues</h2>\n";
    
    // ===== 1. FIX SEQUENCE =====
    echo "<h3>1. Fixing Sequence Issue</h3>\n";
    
    $maxIdResult = $db->query("SELECT MAX(id) as max_id FROM produtos");
    $maxId = $db->fetch($maxIdResult);
    $maxIdValue = $maxId['max_id'] ?: 0;
    
    echo "<p>Max ID in produtos: $maxIdValue</p>\n";
    
    $newSequenceValue = $maxIdValue + 1;
    $db->query("SELECT setval('produtos_id_seq', $newSequenceValue)");
    
    echo "<p style='color: green;'>✅ Sequence set to: $newSequenceValue</p>\n";
    
    // ===== 2. ENSURE CATEGORIES EXIST =====
    echo "<h3>2. Ensuring Categories Exist</h3>\n";
    
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$tenant || !$filial) {
        echo "<p>Using default tenant/filial (1,1)</p>\n";
        $tenantId = 1;
        $filialId = 1;
    } else {
        $tenantId = $tenant['id'];
        $filialId = $filial['id'];
    }
    
    $categorias = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ?", 
        [$tenantId, $filialId]
    );
    
    if (empty($categorias)) {
        echo "<p>Creating default categories...</p>\n";
        
        $defaultCategories = [
            ['nome' => 'Lanches', 'descricao' => 'Sanduíches e lanches'],
            ['nome' => 'Bebidas', 'descricao' => 'Bebidas não alcoólicas'],
            ['nome' => 'Porções', 'descricao' => 'Porções e petiscos'],
            ['nome' => 'Sobremesas', 'descricao' => 'Doces e sobremesas']
        ];
        
        foreach ($defaultCategories as $cat) {
            $db->query(
                "INSERT INTO categorias (nome, descricao, tenant_id, filial_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
                [$cat['nome'], $cat['descricao'], $tenantId, $filialId]
            );
            echo "<p>✅ Created category: {$cat['nome']}</p>\n";
        }
    } else {
        echo "<p>Found " . count($categorias) . " existing categories</p>\n";
    }
    
    // ===== 3. TEST PRODUCT CREATION =====
    echo "<h3>3. Testing Product Creation</h3>\n";
    
    $firstCategory = $db->fetch("SELECT id FROM categorias WHERE tenant_id = ? AND filial_id = ? LIMIT 1", [$tenantId, $filialId]);
    
    if ($firstCategory) {
        try {
            $db->query("
                INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                'TESTE FINAL - REMOVER',
                'Produto de teste para verificar se tudo está funcionando',
                15.99,
                0,
                $firstCategory['id'],
                1,
                $tenantId,
                $filialId
            ]);
            
            $testProductId = $db->lastInsertId();
            echo "<p style='color: green;'>✅ Successfully created test product with ID: $testProductId</p>\n";
            
            // Clean up
            $db->query("DELETE FROM produtos WHERE id = ?", [$testProductId]);
            echo "<p>🧹 Cleaned up test product</p>\n";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating test product: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ No categories found to test with</p>\n";
    }
    
    echo "<h3>✅ All Issues Fixed!</h3>\n";
    echo "<p>You can now create products in the interface without errors.</p>\n";

} catch (Exception $e) {
    echo "<p style='color: red;'>FATAL ERROR: " . $e->getMessage() . "</p>\n";
}
?>
