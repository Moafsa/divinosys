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

# FORCE CLEAN POSTGRESQL DATA DIRECTORY
echo "Force cleaning PostgreSQL data directory..."
rm -rf /var/lib/postgresql/data/*
echo "PostgreSQL data directory cleaned!"

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
    
    // FORCE RECREATE USER AND DATABASE
    echo "Force recreating user and database...\n";
    
    // Drop user if exists
    try {
        $pdo->exec("DROP USER IF EXISTS $user;");
        echo "Dropped existing $user user.\n";
    } catch (PDOException $e) {
        echo "Error dropping user: " . $e->getMessage() . "\n";
    }
    
    // Create user
    try {
        $pdo->exec("CREATE USER $user WITH PASSWORD '$password' SUPERUSER CREATEDB CREATEROLE;");
        echo "Created $user user!\n";
    } catch (PDOException $e) {
        echo "Error creating user: " . $e->getMessage() . "\n";
    }
    
    // Drop database if exists
    try {
        $pdo->exec("DROP DATABASE IF EXISTS \"$dbname\";");
        echo "Dropped existing database $dbname.\n";
    } catch (PDOException $e) {
        echo "Error dropping database: " . $e->getMessage() . "\n";
    }
    
    // Create database
    try {
        $pdo->exec("CREATE DATABASE \"$dbname\" OWNER $user;");
        echo "Created database $dbname!\n";
    } catch (PDOException $e) {
        echo "Error creating database: " . $e->getMessage() . "\n";
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
