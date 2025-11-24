<?php
require_once __DIR__ . '/system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    // Add column if not exists
    $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS exibir_cardapio_online BOOLEAN DEFAULT true");
    
    // Update existing products
    $db->query("UPDATE produtos SET exibir_cardapio_online = true WHERE exibir_cardapio_online IS NULL");
    
    echo "Migration executed successfully!\n";
    echo "Column 'exibir_cardapio_online' added to produtos table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

