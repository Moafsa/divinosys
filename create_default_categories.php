<?php
/**
 * Create default categories for the current tenant
 * Run this script to ensure categories exist before creating products
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Get current tenant and filial
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$tenant || !$filial) {
        echo "No tenant or filial found in session. Using defaults.\n";
        $tenantId = 1;
        $filialId = 1;
    } else {
        $tenantId = $tenant['id'];
        $filialId = $filial['id'];
    }
    
    echo "Creating categories for tenant_id={$tenantId}, filial_id={$filialId}\n";
    
    // Check if categories already exist
    $existingCategories = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ?", 
        [$tenantId, $filialId]
    );
    
    if (!empty($existingCategories)) {
        echo "Categories already exist:\n";
        foreach ($existingCategories as $cat) {
            echo "- {$cat['nome']} (ID: {$cat['id']})\n";
        }
        echo "\nNo need to create new categories.\n";
        exit;
    }
    
    // Create default categories
    $defaultCategories = [
        ['nome' => 'Lanches', 'descricao' => 'Sanduíches e lanches'],
        ['nome' => 'Bebidas', 'descricao' => 'Bebidas não alcoólicas'],
        ['nome' => 'Porções', 'descricao' => 'Porções e petiscos'],
        ['nome' => 'Sobremesas', 'descricao' => 'Doces e sobremesas'],
        ['nome' => 'Bebidas Alcoólicas', 'descricao' => 'Bebidas com álcool'],
        ['nome' => 'Combo', 'descricao' => 'Combos e promoções']
    ];
    
    foreach ($defaultCategories as $cat) {
        $db->query(
            "INSERT INTO categorias (nome, descricao, tenant_id, filial_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$cat['nome'], $cat['descricao'], $tenantId, $filialId]
        );
        echo "✅ Created category: {$cat['nome']}\n";
    }
    
    echo "\nAll categories created successfully!\n";
    echo "You can now create products in the interface.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
