<?php
require_once 'vendor/autoload.php';

use System\Database;
use System\Config;

try {
    $db = Database::getInstance();
    
    echo "Adding stock fields to produtos table...\n";
    
    // Add estoque_atual column
    $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS estoque_atual INTEGER DEFAULT 0");
    echo "✅ Added estoque_atual column\n";
    
    // Add estoque_minimo column
    $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS estoque_minimo INTEGER DEFAULT 0");
    echo "✅ Added estoque_minimo column\n";
    
    // Add preco_custo column
    $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_custo DECIMAL(10,2) DEFAULT 0.00");
    echo "✅ Added preco_custo column\n";
    
    echo "All stock fields added successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
