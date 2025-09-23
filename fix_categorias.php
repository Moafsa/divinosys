<?php
require_once 'vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    echo "Fixing categorias table structure...\n";
    
    // Add missing columns to categorias
    $columns = [
        'descricao TEXT',
        'ativo BOOLEAN DEFAULT true',
        'ordem INTEGER DEFAULT 0',
        'parent_id INTEGER REFERENCES categorias(id) ON DELETE CASCADE',
        'imagem VARCHAR(500)'
    ];
    
    foreach ($columns as $column) {
        try {
            $db->query("ALTER TABLE categorias ADD COLUMN IF NOT EXISTS $column");
            echo "✅ Added column: $column\n";
        } catch (Exception $e) {
            echo "⚠️ Column already exists or error: $column - " . $e->getMessage() . "\n";
        }
    }
    
    // Add missing columns to ingredientes
    $ingredientesColumns = [
        'descricao TEXT',
        'ativo BOOLEAN DEFAULT true'
    ];
    
    foreach ($ingredientesColumns as $column) {
        try {
            $db->query("ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS $column");
            echo "✅ Added column to ingredientes: $column\n";
        } catch (Exception $e) {
            echo "⚠️ Column already exists or error: $column - " . $e->getMessage() . "\n";
        }
    }
    
    echo "✅ Table structure fixed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
