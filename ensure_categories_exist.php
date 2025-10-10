<?php
/**
 * Ensure categories exist for the current tenant
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Get current tenant and filial
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$tenant || !$filial) {
        echo "Using default tenant/filial (1,1)\n";
        $tenantId = 1;
        $filialId = 1;
    } else {
        $tenantId = $tenant['id'];
        $filialId = $filial['id'];
    }
    
    echo "Checking categories for tenant_id=$tenantId, filial_id=$filialId\n";
    
    // Check if categories exist
    $categorias = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ?", 
        [$tenantId, $filialId]
    );
    
    if (empty($categorias)) {
        echo "No categories found. Creating default categories...\n";
        
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
            echo "✅ Created category: {$cat['nome']}\n";
        }
    } else {
        echo "Found " . count($categorias) . " existing categories:\n";
        foreach ($categorias as $cat) {
            echo "- {$cat['nome']} (ID: {$cat['id']})\n";
        }
    }
    
    echo "✅ Categories ready!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
