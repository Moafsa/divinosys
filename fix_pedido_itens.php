<?php
/**
 * Fix pedido_itens table - Add missing filial_id column
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

try {
    echo "=== FIXING PEDIDO_ITENS TABLE ===\n";

    $config = Config::getInstance();
    $db = Database::getInstance();

    echo "Database connection established successfully!\n";

    // Check if filial_id column exists
    $columns = $db->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido_itens' 
        AND column_name = 'filial_id'
    ")->fetchAll();

    if (empty($columns)) {
        echo "Column 'filial_id' does not exist in pedido_itens table.\n";
        echo "Adding filial_id column...\n";
        
        // Add the filial_id column
        $db->query("
            ALTER TABLE pedido_itens 
            ADD COLUMN filial_id INTEGER NOT NULL DEFAULT 1 
            REFERENCES filiais(id) ON DELETE CASCADE
        ");
        
        echo "✅ Column 'filial_id' added successfully!\n";
        
        // Update existing records to use filial_id = 1
        $updated = $db->query("
            UPDATE pedido_itens 
            SET filial_id = 1 
            WHERE filial_id IS NULL OR filial_id = 0
        ")->rowCount();
        
        echo "✅ Updated $updated existing records with filial_id = 1\n";
        
    } else {
        echo "✅ Column 'filial_id' already exists in pedido_itens table.\n";
    }
    
    // Verify the fix
    $tableInfo = $db->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'pedido_itens' 
        ORDER BY ordinal_position
    ")->fetchAll();
    
    echo "\nTable structure after fix:\n";
    foreach ($tableInfo as $column) {
        echo "- {$column['column_name']} ({$column['data_type']}) " . 
             ($column['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    echo "\n✅ PEDIDO_ITENS TABLE FIXED SUCCESSFULLY!\n";
    
} catch (Exception $e) {
    echo "❌ Fix failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
