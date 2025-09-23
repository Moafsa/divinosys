<?php
/**
 * Database Migration Script for Coolify
 * This script initializes the database with required tables and data
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

try {
    echo "Starting database migration...\n";
    
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
    
    // Check if tables exist
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    
    if (empty($tables)) {
        echo "No tables found. Running initial migration...\n";
        
        // Read and execute schema file
        $schemaFile = '/var/www/html/database/init/01_create_schema.sql';
        if (file_exists($schemaFile)) {
            echo "Found schema file: $schemaFile\n";
            try {
                $schema = file_get_contents($schemaFile);
                $db->query($schema);
                echo "Schema created successfully!\n";
            } catch (Exception $e) {
                echo "Schema creation failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        } else {
            echo "Schema file not found: $schemaFile\n";
            echo "Current directory: " . getcwd() . "\n";
            echo "Files in current directory:\n";
            $files = scandir('.');
            foreach ($files as $file) {
                echo "- $file\n";
            }
            echo "Files in database directory:\n";
            if (is_dir('database')) {
                $dbFiles = scandir('database');
                foreach ($dbFiles as $file) {
                    echo "- database/$file\n";
                }
            } else {
                echo "Database directory does not exist!\n";
            }
        }
        
        // Read and execute data file
        $dataFile = '/var/www/html/database/init/02_insert_default_data.sql';
        if (file_exists($dataFile)) {
            echo "Found data file: $dataFile\n";
            try {
                $data = file_get_contents($dataFile);
                echo "Data file size: " . strlen($data) . " bytes\n";
                $db->query($data);
                echo "Default data inserted successfully!\n";
            } catch (Exception $e) {
                echo "Data insertion failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        } else {
            echo "Data file not found: $dataFile\n";
        }
        
        echo "Migration completed successfully!\n";
    } else {
        echo "Tables already exist. Checking if data migration is needed...\n";
        echo "Found tables: " . implode(', ', array_column($tables, 'table_name')) . "\n";
        
        // Check if usuarios table has data
        try {
            $userCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
            echo "Users in database: $userCount\n";
            
            if ($userCount == 0) {
                echo "No users found. Running data migration...\n";
                
                // Read and execute data file
                $dataFile = '/var/www/html/database/init/02_insert_default_data.sql';
                if (file_exists($dataFile)) {
                    echo "Found data file: $dataFile\n";
                    try {
                        $data = file_get_contents($dataFile);
                        echo "Data file size: " . strlen($data) . " bytes\n";
                        $db->query($data);
                        echo "Default data inserted successfully!\n";
                    } catch (Exception $e) {
                        echo "Data insertion failed: " . $e->getMessage() . "\n";
                        throw $e;
                    }
                } else {
                    echo "Data file not found: $dataFile\n";
                }
            } else {
                echo "Data migration not needed. Users already exist.\n";
            }
        } catch (Exception $e) {
            echo "Error checking users: " . $e->getMessage() . "\n";
            echo "This might be because the usuarios table doesn't exist yet.\n";
        }
    }
    
    // Test login credentials
    echo "Testing login credentials...\n";
    try {
        $user = $db->query("SELECT * FROM usuarios WHERE login = 'admin'")->fetch();
        if ($user) {
            echo "Admin user found: " . $user['nome'] . "\n";
        } else {
            echo "Admin user not found!\n";
        }
    } catch (Exception $e) {
        echo "Login test failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
