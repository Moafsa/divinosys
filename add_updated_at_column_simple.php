<?php
// Simple script to add updated_at column to produtos table
require_once 'system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    // Add updated_at column if it doesn't exist
    $db->query("
        ALTER TABLE produtos 
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ");
    
    echo "✅ Column updated_at added successfully to produtos table!";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
