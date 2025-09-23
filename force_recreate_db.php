<?php
/**
 * Force Recreate Database Script
 * This script forces database recreation by dropping and recreating it
 */

echo "=== FORCE RECREATE DATABASE ===\n";

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
    // Connect to PostgreSQL server (not specific database)
    $dsn = "pgsql:host=$host;port=$port";
    echo "Connecting to PostgreSQL server...\n";
    
    // Try different connection methods
    $pdo = null;
    $connectionMethods = [
        ['user' => 'postgres', 'password' => $password],
        ['user' => 'postgres', 'password' => ''],
        ['user' => 'postgres', 'password' => 'postgres'],
    ];
    
    foreach ($connectionMethods as $method) {
        try {
            echo "Trying user: {$method['user']}, password: " . (empty($method['password']) ? 'empty' : 'set') . "\n";
            $pdo = new PDO($dsn, $method['user'], $method['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            echo "Connected successfully with user: {$method['user']}\n";
            break;
        } catch (PDOException $e) {
            echo "Failed: " . $e->getMessage() . "\n";
            continue;
        }
    }
    
    if (!$pdo) {
        throw new Exception("Could not connect to PostgreSQL server with any method");
    }
    
    // Terminate all connections to the database
    echo "Terminating all connections to database '$dbname'...\n";
    $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$dbname' AND pid <> pg_backend_pid();");
    
    // Drop database if exists
    echo "Dropping database '$dbname' if it exists...\n";
    $pdo->exec("DROP DATABASE IF EXISTS \"$dbname\"");
    echo "Database dropped (if it existed).\n";
    
    // Create database
    echo "Creating database '$dbname'...\n";
    $pdo->exec("CREATE DATABASE \"$dbname\"");
    echo "Database created successfully!\n\n";
    
    // Connect to the new database
    echo "Connecting to new database...\n";
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected to new database successfully!\n\n";
    
    // Execute schema
    echo "Executing schema...\n";
    $schemaFile = 'database/init/01_create_schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        $pdo->exec($schema);
        echo "Schema executed successfully!\n";
    } else {
        echo "Schema file not found: $schemaFile\n";
    }
    
    // Execute data
    echo "Executing default data...\n";
    $dataFile = 'database/init/02_insert_default_data.sql';
    if (file_exists($dataFile)) {
        $data = file_get_contents($dataFile);
        $pdo->exec($data);
        echo "Default data executed successfully!\n";
    } else {
        echo "Data file not found: $dataFile\n";
    }
    
    echo "\n=== DATABASE RECREATED SUCCESSFULLY! ===\n";
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
