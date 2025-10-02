<?php
/**
 * Database Schema Fix Script for Coolify
 * This script ensures all required tables are created properly
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

try {
    echo "=== DATABASE SCHEMA FIX SCRIPT ===\n";
    echo "Starting database schema fix...\n";
    
    // Debug environment variables
    echo "Environment variables:\n";
    echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
    echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "\n";
    echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "\n";
    echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "\n";
    echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ?: 'NOT SET') . "\n\n";
    
    // Initialize configuration
    $config = Config::getInstance();
    $dbConfig = $config->getDatabaseConfig();
    echo "Database config from Config class:\n";
    print_r($dbConfig);
    
    $db = Database::getInstance();
    echo "Database connection established successfully!\n";
    
    // Check current tables
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    echo "Current tables in database:\n";
    foreach ($tables as $table) {
        echo "- " . $table['table_name'] . "\n";
    }
    
    // Define schema files to execute in order
    $schemaFiles = [
        '/var/www/html/database/init/00_init_database.sql',
        '/var/www/html/database/init/02_create_full_schema.sql',
        '/var/www/html/database/init/01_insert_essential_data.sql'
    ];
    
    foreach ($schemaFiles as $schemaFile) {
        if (file_exists($schemaFile)) {
            echo "\n=== Executing: " . basename($schemaFile) . " ===\n";
            
            try {
                $schema = file_get_contents($schemaFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $schema);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "✅ " . basename($schemaFile) . " executed successfully! ($executed statements)\n";
                
            } catch (Exception $e) {
                echo "❌ Error executing " . basename($schemaFile) . ": " . $e->getMessage() . "\n";
                throw $e;
            }
        } else {
            echo "⚠️  Schema file not found: $schemaFile\n";
        }
    }
    
    // Verify tables were created
    echo "\n=== VERIFICATION ===\n";
    $finalTables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    echo "Final tables in database:\n";
    foreach ($finalTables as $table) {
        echo "- " . $table['table_name'] . "\n";
    }
    
    // Check if usuarios table exists and has data
    try {
        $userCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch();
        echo "\nUsers in database: " . $userCount['count'] . "\n";
        
        if ($userCount['count'] > 0) {
            echo "✅ Admin user created successfully!\n";
        } else {
            echo "❌ No users found in database!\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking usuarios table: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DATABASE SCHEMA FIX COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ Database schema fix failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
