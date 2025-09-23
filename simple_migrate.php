<?php
// Simple migration script
echo "=== SIMPLE MIGRATION SCRIPT ===\n";

try {
    // Load Composer autoloader
    require_once 'vendor/autoload.php';
    
    $config = System\Config::getInstance();
    $db = System\Database::getInstance();
    
    echo "Database connection established!\n";
    
    // Check if usuarios table exists
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    $tableNames = array_column($tables, 'table_name');
    
    echo "Tables found: " . implode(', ', $tableNames) . "\n";
    
    // If no tables exist, run schema migration first
    if (empty($tableNames)) {
        echo "No tables found. Running schema migration first...\n";
        
        $schemaFile = '/var/www/html/database/init/01_create_schema.sql';
        if (file_exists($schemaFile)) {
            echo "Schema file found: $schemaFile\n";
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
            
            echo "✅ Schema migration successful! Executed $executed statements\n";
            
            // Check tables again
            $newTables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
            $newTableNames = array_column($newTables, 'table_name');
            echo "Tables after schema migration: " . implode(', ', $newTableNames) . "\n";
        } else {
            echo "❌ Schema file not found: $schemaFile\n";
            exit(1);
        }
    }
    
    if (in_array('usuarios', $tableNames) || in_array('usuarios', $newTableNames ?? [])) {
        echo "usuarios table exists!\n";
        
        // Check if users exist
        $userCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
        echo "Users in database: $userCount\n";
        
        if ($userCount == 0) {
            echo "No users found. Running data migration...\n";
            
            $dataFile = '/var/www/html/database/init/02_insert_default_data.sql';
            if (file_exists($dataFile)) {
                echo "Data file found: $dataFile\n";
                $data = file_get_contents($dataFile);
                echo "Data file size: " . strlen($data) . " bytes\n";
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $data);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                        } catch (Exception $e) {
                            echo "Error executing statement: " . $e->getMessage() . "\n";
                            echo "Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
                
                echo "Executed $executed statements\n";
                
                // Check users again
                $newUserCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
                echo "Users after migration: $newUserCount\n";
                
                if ($newUserCount > 0) {
                    echo "✅ Migration successful!\n";
                } else {
                    echo "❌ Migration failed - no users created\n";
                }
            } else {
                echo "❌ Data file not found: $dataFile\n";
            }
        } else {
            echo "✅ Users already exist. Migration not needed.\n";
        }
    } else {
        echo "❌ usuarios table does not exist!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
