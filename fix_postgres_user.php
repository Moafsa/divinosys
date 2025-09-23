<?php
/**
 * Fix PostgreSQL User Script
 * This script creates the postgres user if it doesn't exist
 */

echo "=== FIXING POSTGRESQL USER ===\n";

// Get database connection parameters
$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'divino_lanches';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'divino_password';

echo "Connection parameters:\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Database: $dbname\n";
echo "User: $user\n";
echo "Password: " . (empty($password) ? 'NOT SET' : 'SET') . "\n\n";

try {
    // Try to connect to PostgreSQL server (not specific database)
    $dsn = "pgsql:host=$host;port=$port";
    echo "Attempting to connect to PostgreSQL server...\n";
    
    // First try with postgres user
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "Connected with postgres user successfully!\n";
    } catch (PDOException $e) {
        echo "Failed to connect with postgres user: " . $e->getMessage() . "\n";
        echo "Trying to connect with default user...\n";
        
        // Try with default postgres user (no password)
        try {
            $pdo = new PDO($dsn, 'postgres', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            echo "Connected with default postgres user!\n";
            
            // Create user with password
            echo "Creating postgres user with password...\n";
            $pdo->exec("CREATE USER postgres WITH PASSWORD '$password' SUPERUSER CREATEDB CREATEROLE;");
            echo "User created successfully!\n";
            
        } catch (PDOException $e2) {
            echo "Failed to connect with default user: " . $e2->getMessage() . "\n";
            echo "Trying to connect to template1 database...\n";
            
            // Try connecting to template1 database
            $dsn = "pgsql:host=$host;port=$port;dbname=template1";
            $pdo = new PDO($dsn, 'postgres', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            echo "Connected to template1 database!\n";
            
            // Create user with password
            echo "Creating postgres user with password...\n";
            $pdo->exec("CREATE USER postgres WITH PASSWORD '$password' SUPERUSER CREATEDB CREATEROLE;");
            echo "User created successfully!\n";
        }
    }
    
    // Now try to connect with the correct user
    echo "\nTesting connection with postgres user...\n";
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected to database successfully!\n";
    
    // Check if tables exist
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
    
    if (empty($tables)) {
        echo "No tables found. Creating schema...\n";
        
        // Execute schema
        $schemaFile = 'database/init/01_create_schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            $pdo->exec($schema);
            echo "Schema created successfully!\n";
        }
        
        // Execute data
        $dataFile = 'database/init/02_insert_default_data.sql';
        if (file_exists($dataFile)) {
            $data = file_get_contents($dataFile);
            $pdo->exec($data);
            echo "Default data inserted successfully!\n";
        }
    } else {
        echo "Tables already exist: " . implode(', ', array_column($tables, 'table_name')) . "\n";
    }
    
    echo "\n=== POSTGRESQL USER FIXED SUCCESSFULLY! ===\n";
    echo "You can now try to login with:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
