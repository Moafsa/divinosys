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
        
        // Run categories/products update
        echo "Running categories/products update...\n";
        $updateFile = '/var/www/html/database/init/03_update_categories_products.sql';
        if (file_exists($updateFile)) {
            echo "Found update file: $updateFile\n";
            try {
                $update = file_get_contents($updateFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $update);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing update statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "Categories/Products update completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "Categories/Products update failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Update file not found: $updateFile\n";
        }
        
        // Run mesa pedidos update
        echo "Running mesa pedidos update...\n";
        $mesaPedidosFile = '/var/www/html/database/init/04_update_mesa_pedidos.sql';
        if (file_exists($mesaPedidosFile)) {
            echo "Found mesa pedidos file: $mesaPedidosFile\n";
            try {
                $mesaPedidos = file_get_contents($mesaPedidosFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $mesaPedidos);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing mesa pedidos statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "Mesa pedidos update completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "Mesa pedidos update failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Mesa pedidos file not found: $mesaPedidosFile\n";
        }
        
        // Run usuarios_globais creation
        echo "Running usuarios_globais creation...\n";
        $usuariosGlobaisFile = '/var/www/html/database/init/05_create_usuarios_globais.sql';
        if (file_exists($usuariosGlobaisFile)) {
            echo "Found usuarios_globais file: $usuariosGlobaisFile\n";
            try {
                $usuariosGlobais = file_get_contents($usuariosGlobaisFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $usuariosGlobais);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing usuarios_globais statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "Usuarios_globais creation completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "Usuarios_globais creation failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Usuarios_globais file not found: $usuariosGlobaisFile\n";
        }
        
        // Run WhatsApp tables creation
        echo "Running WhatsApp tables creation...\n";
        $whatsappTablesFile = '/var/www/html/database/init/06_create_whatsapp_tables.sql';
        if (file_exists($whatsappTablesFile)) {
            echo "Found WhatsApp tables file: $whatsappTablesFile\n";
            try {
                $whatsappTables = file_get_contents($whatsappTablesFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $whatsappTables);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing WhatsApp table statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "WhatsApp tables creation completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "WhatsApp tables creation failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "WhatsApp tables file not found: $whatsappTablesFile\n";
        }
        
        // Run Chatwoot tables creation
        echo "Running Chatwoot tables creation...\n";
        $chatwootTablesFile = '/var/www/html/database/init/07_create_chatwoot_tables.sql';
        if (file_exists($chatwootTablesFile)) {
            echo "Found Chatwoot tables file: $chatwootTablesFile\n";
            try {
                $chatwootTables = file_get_contents($chatwootTablesFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $chatwootTables);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Warning: Error executing Chatwoot table statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "Chatwoot tables creation completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "Chatwoot tables creation failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Chatwoot tables file not found: $chatwootTablesFile\n";
        }
        
        // Run Chatwoot columns addition
        echo "Running Chatwoot columns addition...\n";
        $chatwootColumnsFile = '/var/www/html/database/init/08_add_chatwoot_columns.sql';
        if (file_exists($chatwootColumnsFile)) {
            echo "Found Chatwoot columns file: $chatwootColumnsFile\n";
            try {
                $chatwootColumns = file_get_contents($chatwootColumnsFile);
                $statements = array_filter(array_map('trim', explode(';', $chatwootColumns)));
                $executed = 0;
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $db->query($statement);
                        $executed++;
                    }
                }
                echo "Chatwoot columns addition completed! Executed $executed statements\n";
            } catch (Exception $e) {
                echo "Chatwoot columns addition failed: " . $e->getMessage() . "\n";
                // Don't throw - this might be a re-run
            }
        } else {
            echo "Chatwoot columns file not found: $chatwootColumnsFile\n";
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
