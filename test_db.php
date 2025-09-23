<?php
// Test database connection and migration
echo "<h1>Database Test - Divino Lanches</h1>";

try {
    // Load Composer autoloader
    require_once 'vendor/autoload.php';
    
    // Load environment variables
    $config = System\Config::getInstance();
    
    echo "<h2>Environment Variables:</h2>";
    echo "<pre>";
    echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
    echo "DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "\n";
    echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
    echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
    echo "DB_PASSWORD: " . (isset($_ENV['DB_PASSWORD']) ? 'SET' : 'NOT SET') . "\n";
    echo "</pre>";
    
    echo "<h2>Database Configuration:</h2>";
    $dbConfig = $config->getDatabaseConfig();
    echo "<pre>";
    print_r($dbConfig);
    echo "</pre>";
    
    echo "<h2>Database Connection Test:</h2>";
    $db = System\Database::getInstance();
    $connection = $db->getConnection();
    echo "✅ Database connection successful!<br>";
    
    echo "<h2>Tables Check:</h2>";
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    echo "Found " . count($tables) . " tables:<br>";
    foreach ($tables as $table) {
        echo "- " . $table['table_name'] . "<br>";
    }
    
    if (in_array(['table_name' => 'usuarios'], $tables)) {
        echo "<h2>Users Check:</h2>";
        $userCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
        echo "Users in database: $userCount<br>";
        
        if ($userCount > 0) {
            $users = $db->query("SELECT id, nome, login FROM usuarios LIMIT 5")->fetchAll();
            echo "<h3>First 5 users:</h3>";
            echo "<pre>";
            print_r($users);
            echo "</pre>";
        }
    }
    
    echo "<h2>Migration Files Check:</h2>";
    $schemaFile = '/var/www/html/database/init/01_create_schema.sql';
    $dataFile = '/var/www/html/database/init/02_insert_default_data.sql';
    
    echo "Schema file exists: " . (file_exists($schemaFile) ? '✅ YES' : '❌ NO') . "<br>";
    echo "Data file exists: " . (file_exists($dataFile) ? '✅ YES' : '❌ NO') . "<br>";
    
    if (file_exists($dataFile)) {
        $dataSize = filesize($dataFile);
        echo "Data file size: $dataSize bytes<br>";
        
        if ($dataSize > 0) {
            echo "<h3>First 500 characters of data file:</h3>";
            $dataContent = file_get_contents($dataFile);
            echo "<pre>" . htmlspecialchars(substr($dataContent, 0, 500)) . "...</pre>";
        }
    }
    
    echo "<h2>Manual Migration Test:</h2>";
    
    // First, run schema migration if no tables exist
    if (count($tables) == 0) {
        echo "No tables found. Running schema migration first...<br>";
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
                        echo "Warning: Error executing statement: " . $e->getMessage() . "<br>";
                        echo "Statement: " . substr($statement, 0, 100) . "...<br>";
                    }
                }
            }
            
            echo "✅ Schema migration successful! Executed $executed statements<br>";
            
            // Check tables again
            $newTables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
            echo "Tables after schema migration: " . count($newTables) . "<br>";
            foreach ($newTables as $table) {
                echo "- " . $table['table_name'] . "<br>";
            }
        } catch (Exception $e) {
            echo "❌ Schema migration failed: " . $e->getMessage() . "<br>";
        }
    }
    
    // Then run data migration if no users exist
    if (file_exists($dataFile)) {
        try {
            $userCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
            echo "Users in database: $userCount<br>";
            
            if ($userCount == 0) {
                echo "No users found. Running data migration...<br>";
                $data = file_get_contents($dataFile);
                
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
                            echo "Warning: Error executing statement: " . $e->getMessage() . "<br>";
                            echo "Statement: " . substr($statement, 0, 100) . "...<br>";
                        }
                    }
                }
                
                echo "✅ Data migration successful! Executed $executed statements<br>";
                
                // Check users again
                $newUserCount = $db->query("SELECT COUNT(*) as count FROM usuarios")->fetch()['count'];
                echo "Users after migration: $newUserCount<br>";
            } else {
                echo "Users already exist. Data migration not needed.<br>";
            }
        } catch (Exception $e) {
            echo "❌ Data migration failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Data file not found: $dataFile<br>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<h3>Stack trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
