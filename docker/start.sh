#!/bin/bash

echo "=== DIVINO LANCHES STARTUP SCRIPT ==="

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
until pg_isready -h postgres -p 5432 -U postgres; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done

echo "PostgreSQL is ready!"

# Wait a bit more for PostgreSQL to fully initialize
sleep 5

# Try to fix database connection
echo "Attempting to fix database connection..."

# Create a simple PHP script to test and fix database
cat > /tmp/fix_db.php << 'EOF'
<?php
// Simple database fix script
$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'divino_lanches';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'divino_password';

echo "Testing database connection...\n";

try {
    // Try to connect to PostgreSQL server with different methods
    $dsn = "pgsql:host=$host;port=$port";
    
    // First try with postgres user and no password
    try {
        $pdo = new PDO($dsn, 'postgres', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "Connected with postgres user (no password)!\n";
    } catch (PDOException $e) {
        echo "Failed with postgres user (no password): " . $e->getMessage() . "\n";
        
        // Try with postgres user and password
        try {
            $pdo = new PDO($dsn, 'postgres', '$password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            echo "Connected with postgres user (with password)!\n";
        } catch (PDOException $e2) {
            echo "Failed with postgres user (with password): " . $e2->getMessage() . "\n";
            
            // Try connecting to template1 database
            $dsn = "pgsql:host=$host;port=$port;dbname=template1";
            $pdo = new PDO($dsn, 'postgres', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            echo "Connected to template1 database!\n";
        }
    }
    
    echo "Connected to PostgreSQL server!\n";
    
    // Create user if it doesn't exist
    try {
        $pdo->exec("CREATE USER postgres WITH PASSWORD '$password' SUPERUSER CREATEDB CREATEROLE;");
        echo "Created postgres user!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "Postgres user already exists.\n";
        } else {
            echo "Error creating user: " . $e->getMessage() . "\n";
        }
    }
    
    // Create database if it doesn't exist
    try {
        $pdo->exec("CREATE DATABASE \"$dbname\";");
        echo "Created database $dbname!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "Database $dbname already exists.\n";
        } else {
            echo "Error creating database: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Database setup completed!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
EOF

# Run the fix script
php /tmp/fix_db.php

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
