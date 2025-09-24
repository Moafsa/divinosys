<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

use System\Config;
use System\Database;

echo "Fixing produto_ingredientes table...\n";

try {
    $db = Database::getInstance();

    // Add missing columns
    $db->query("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS tenant_id INTEGER NOT NULL DEFAULT 1;");
    echo "✅ Added tenant_id column\n";
    
    $db->query("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS filial_id INTEGER NOT NULL DEFAULT 1;");
    echo "✅ Added filial_id column\n";
    
    // Add primary key if missing
    $db->query("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS id SERIAL PRIMARY KEY;");
    echo "✅ Added id column\n";
    
    echo "✅ Table produto_ingredientes fixed successfully\n";

} catch (\Exception $e) {
    error_log("Error fixing table: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
