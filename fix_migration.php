<?php
/**
 * Fixed Migration Script for Coolify
 * This script properly handles PostgreSQL functions and dollar-quoted strings
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

try {
    echo "Starting fixed database migration...\n";

    $config = Config::getInstance();
    $db = Database::getInstance();

    echo "Database connection established successfully!\n";

    // Check if tables exist
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    
    if (empty($tables)) {
        echo "No tables found. Running initial migration...\n";
        
        // Read schema file
        $schemaFile = '/var/www/html/database/init/01_create_schema.sql';
        if (file_exists($schemaFile)) {
            echo "Found schema file: $schemaFile\n";
            $schema = file_get_contents($schemaFile);
            
            // Parse SQL statements properly, handling dollar-quoted strings
            $statements = parseSqlStatements($schema);
            $executed = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $db->query($statement);
                        $executed++;
                        echo "✅ Executed statement " . $executed . "\n";
                    } catch (Exception $e) {
                        echo "❌ Error executing statement: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }
            
            echo "Schema migration completed! Executed $executed statements\n";
        } else {
            echo "Schema file not found: $schemaFile\n";
            exit(1);
        }
        
        // Read and execute data file
        $dataFile = '/var/www/html/database/init/02_insert_default_data.sql';
        if (file_exists($dataFile)) {
            echo "Found data file: $dataFile\n";
            $data = file_get_contents($dataFile);
            
            // Parse SQL statements properly
            $statements = parseSqlStatements($data);
            $executed = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $db->query($statement);
                        $executed++;
                    } catch (Exception $e) {
                        echo "Warning: Error executing data statement: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }
            
            echo "Data migration completed! Executed $executed statements\n";
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
                    $data = file_get_contents($dataFile);
                    
                    // Parse SQL statements properly
                    $statements = parseSqlStatements($data);
                    $executed = 0;
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            try {
                                $db->query($statement);
                                $executed++;
                            } catch (Exception $e) {
                                echo "Warning: Error executing data statement: " . $e->getMessage() . "\n";
                                echo "Statement: " . substr($statement, 0, 100) . "...\n";
                            }
                        }
                    }
                    
                    echo "Data migration completed! Executed $executed statements\n";
                } else {
                    echo "Data file not found: $dataFile\n";
                }
            } else {
                echo "Data migration not needed. Users already exist.\n";
            }
        } catch (Exception $e) {
            echo "Error checking users: " . $e->getMessage() . "\n";
        }
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

/**
 * Parse SQL statements properly, handling dollar-quoted strings
 */
function parseSqlStatements($sql) {
    $statements = [];
    $current = '';
    $inDollarQuote = false;
    $dollarTag = '';
    $i = 0;
    
    while ($i < strlen($sql)) {
        $char = $sql[$i];
        
        if (!$inDollarQuote) {
            if ($char === '$') {
                // Check for dollar-quoted string start
                $j = $i + 1;
                $tag = '';
                while ($j < strlen($sql) && $sql[$j] !== '$') {
                    $tag .= $sql[$j];
                    $j++;
                }
                if ($j < strlen($sql) && $sql[$j] === '$') {
                    $dollarTag = '$' . $tag . '$';
                    $inDollarQuote = true;
                    $current .= $dollarTag;
                    $i = $j;
                } else {
                    $current .= $char;
                }
            } elseif ($char === ';') {
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        } else {
            // Inside dollar-quoted string
            if (substr($sql, $i, strlen($dollarTag)) === $dollarTag) {
                $inDollarQuote = false;
                $current .= $dollarTag;
                $i += strlen($dollarTag) - 1;
                $dollarTag = '';
            } else {
                $current .= $char;
            }
        }
        
        $i++;
    }
    
    // Add the last statement if it's not empty
    if (trim($current) !== '') {
        $statements[] = $current;
    }
    
    return $statements;
}
?>
