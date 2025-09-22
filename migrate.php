<?php
/**
 * Database Migration Script for Coolify
 * This script initializes the database with required tables and data
 */

require_once 'system/Config.php';
require_once 'system/Database.php';

use System\Config;
use System\Database;

try {
    echo "Starting database migration...\n";
    
    // Initialize configuration
    $config = Config::getInstance();
    $db = Database::getInstance();
    
    echo "Database connection established successfully!\n";
    
    // Check if tables exist
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    
    if (empty($tables)) {
        echo "No tables found. Running initial migration...\n";
        
        // Read and execute schema file
        $schemaFile = 'database/init/01_create_schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            $db->query($schema);
            echo "Schema created successfully!\n";
        } else {
            echo "Schema file not found: $schemaFile\n";
        }
        
        // Read and execute data file
        $dataFile = 'database/init/02_insert_default_data.sql';
        if (file_exists($dataFile)) {
            $data = file_get_contents($dataFile);
            $db->query($data);
            echo "Default data inserted successfully!\n";
        } else {
            echo "Data file not found: $dataFile\n";
        }
        
        echo "Migration completed successfully!\n";
    } else {
        echo "Tables already exist. Migration not needed.\n";
        echo "Found tables: " . implode(', ', array_column($tables, 'table_name')) . "\n";
    }
    
    // Test login credentials
    echo "Testing login credentials...\n";
    $user = $db->query("SELECT * FROM usuarios WHERE login = 'admin'")->fetch();
    if ($user) {
        echo "Admin user found: " . $user['nome'] . "\n";
    } else {
        echo "Admin user not found!\n";
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
