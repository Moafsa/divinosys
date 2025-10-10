<?php
/**
 * Debug script to check categories and products structure
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Get current tenant and filial
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    echo "<h2>Debug: Categories and Products Structure</h2>\n";
    echo "<h3>Current Session Info:</h3>\n";
    echo "Tenant ID: " . ($tenant ? $tenant['id'] : 'NULL') . "\n";
    echo "Filial ID: " . ($filial ? $filial['id'] : 'NULL') . "\n";
    
    if (!$tenant || !$filial) {
        echo "<p style='color: red;'>ERROR: No tenant or filial found in session!</p>\n";
        echo "<p>Trying to use default values (tenant_id=1, filial_id=1)...</p>\n";
        $tenantId = 1;
        $filialId = 1;
    } else {
        $tenantId = $tenant['id'];
        $filialId = $filial['id'];
    }
    
    echo "<h3>1. Checking Categories Table Structure:</h3>\n";
    try {
        $result = $db->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'categorias' ORDER BY ordinal_position");
        $columns = $db->fetchAll($result);
        
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Column</th><th>Type</th><th>Nullable</th></tr>\n";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td></tr>\n";
        }
        echo "</table>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR checking categorias structure: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>2. Checking Categories Data:</h3>\n";
    try {
        $categorias = $db->fetchAll("SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ? ORDER BY id", [$tenantId, $filialId]);
        
        if (empty($categorias)) {
            echo "<p style='color: orange;'>WARNING: No categories found for tenant_id=$tenantId, filial_id=$filialId</p>\n";
            
            // Check if there are any categories at all
            $allCategorias = $db->fetchAll("SELECT * FROM categorias ORDER BY id");
            echo "<p>Total categories in database: " . count($allCategorias) . "</p>\n";
            
            if (!empty($allCategorias)) {
                echo "<h4>All categories in database:</h4>\n";
                echo "<table border='1' style='border-collapse: collapse;'>\n";
                echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th></tr>\n";
                foreach ($allCategorias as $cat) {
                    echo "<tr><td>{$cat['id']}</td><td>{$cat['nome']}</td><td>{$cat['tenant_id']}</td><td>{$cat['filial_id']}</td></tr>\n";
                }
                echo "</table>\n";
            }
        } else {
            echo "<p style='color: green;'>Found " . count($categorias) . " categories:</p>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th></tr>\n";
            foreach ($categorias as $cat) {
                echo "<tr><td>{$cat['id']}</td><td>{$cat['nome']}</td><td>{$cat['tenant_id']}</td><td>{$cat['filial_id']}</td></tr>\n";
            }
            echo "</table>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR checking categorias data: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>3. Checking Products Table Structure:</h3>\n";
    try {
        $result = $db->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'produtos' ORDER BY ordinal_position");
        $columns = $db->fetchAll($result);
        
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Column</th><th>Type</th><th>Nullable</th></tr>\n";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td></tr>\n";
        }
        echo "</table>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR checking produtos structure: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>4. Checking Products Data:</h3>\n";
    try {
        $produtos = $db->fetchAll("SELECT * FROM produtos WHERE tenant_id = ? AND filial_id = ? ORDER BY id", [$tenantId, $filialId]);
        
        echo "<p>Found " . count($produtos) . " products:</p>\n";
        if (!empty($produtos)) {
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>ID</th><th>Nome</th><th>Categoria ID</th><th>Tenant ID</th><th>Filial ID</th></tr>\n";
            foreach ($produtos as $prod) {
                echo "<tr><td>{$prod['id']}</td><td>{$prod['nome']}</td><td>{$prod['categoria_id']}</td><td>{$prod['tenant_id']}</td><td>{$prod['filial_id']}</td></tr>\n";
            }
            echo "</table>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>ERROR checking produtos data: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>5. Testing Product Insert with Null Category:</h3>\n";
    try {
        // Test what happens when we try to insert a product with null categoria_id
        $db->query("
            INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", ['TESTE PRODUTO', 'Descrição teste', 10.00, 0, null, 1, $tenantId, $filialId]);
        
        echo "<p style='color: red;'>ERROR: This should not have worked! categoria_id is NOT NULL but we inserted null.</p>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Expected error when inserting null categoria_id: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>6. Recommendations:</h3>\n";
    echo "<ul>\n";
    echo "<li>If no categories exist, create default categories first</li>\n";
    echo "<li>Add frontend validation to ensure category is selected</li>\n";
    echo "<li>Add backend validation to handle null categoria_id gracefully</li>\n";
    echo "<li>Make categoria_id nullable in database if it should be optional</li>\n";
    echo "</ul>\n";

} catch (Exception $e) {
    echo "<p style='color: red;'>FATAL ERROR: " . $e->getMessage() . "</p>\n";
}
?>
