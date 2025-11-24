<?php
/**
 * Script to add exibir_cardapio_online column to produtos table
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    echo "Adding exibir_cardapio_online column to produtos table...\n";
    
    // Add column if not exists
    $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS exibir_cardapio_online BOOLEAN DEFAULT true");
    echo "✓ Column added successfully\n";
    
    // Update existing products to show in menu by default
    $db->query("UPDATE produtos SET exibir_cardapio_online = true WHERE exibir_cardapio_online IS NULL");
    echo "✓ Existing products updated\n";
    
    // Add comment
    try {
        $db->query("COMMENT ON COLUMN produtos.exibir_cardapio_online IS 'Controls if product should be displayed on online menu page'");
        echo "✓ Comment added\n";
    } catch (\Exception $e) {
        // Comment may fail, but that's ok
        echo "⚠ Comment not added (non-critical)\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

