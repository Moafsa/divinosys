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
                    
                    // Split by semicolon and execute each statement
                    $statements = explode(';', $schema);
                    $executed = 0;
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            try {
                                $db->query($statement);
                                $executed++;
                            } catch (Exception $e) {
                                echo "Warning: Error executing statement: " . $e->getMessage() . "\n";
                                echo "Statement: " . substr($statement, 0, 100) . "...\n";
                            }
                        }
                    }
                    
                    echo "Schema created successfully! Executed $executed statements\n";
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
    
        // Fix pedido_itens table if needed
        echo "Checking pedido_itens table structure...\n";
        try {
            $columns = $db->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'pedido_itens' 
                AND column_name = 'filial_id'
            ")->fetchAll();

            if (empty($columns)) {
                echo "Adding missing filial_id column to pedido_itens table...\n";
                
                $db->query("
                    ALTER TABLE pedido_itens 
                    ADD COLUMN filial_id INTEGER NOT NULL DEFAULT 1 
                    REFERENCES filiais(id) ON DELETE CASCADE
                ");
                
                $updated = $db->query("
                    UPDATE pedido_itens 
                    SET filial_id = 1 
                    WHERE filial_id IS NULL OR filial_id = 0
                ")->rowCount();
                
                echo "✅ Added filial_id column and updated $updated records\n";
            } else {
                echo "✅ filial_id column already exists in pedido_itens table\n";
            }
        } catch (Exception $e) {
            echo "Warning: Could not fix pedido_itens table: " . $e->getMessage() . "\n";
        }
        
        // Test login credentials
        echo "Testing login credentials...\n";
        try {
            $user = $db->query("SELECT * FROM usuarios WHERE login = 'admin'")->fetch();
            if ($user) {
                echo "Admin user found: " . ($user['nome'] ?? 'admin') . "\n";
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
