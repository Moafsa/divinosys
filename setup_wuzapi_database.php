<?php
/**
 * Setup WuzAPI Database and User
 * 
 * This script creates the separate database and user for WuzAPI
 * Runs automatically on container startup (via docker/start-production.sh)
 */

echo "\n🔧 Setting up WuzAPI database...\n";

// Connect to PostgreSQL as superuser
$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$mainDb = getenv('DB_NAME') ?: 'divino_db';
$mainUser = getenv('DB_USER') ?: 'divino_user';
$mainPassword = getenv('DB_PASSWORD') ?: 'divino_password';

try {
    // Connect to main database first
    $dsn = "pgsql:host=$host;port=$port;dbname=$mainDb";
    $pdo = new PDO($dsn, $mainUser, $mainPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Connected to PostgreSQL\n";
    
    // Check if wuzapi user exists
    $result = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = 'wuzapi'")->fetch();
    
    if (!$result) {
        echo "📝 Creating user 'wuzapi'...\n";
        $pdo->exec("CREATE USER wuzapi WITH PASSWORD 'wuzapi'");
        echo "✅ User 'wuzapi' created\n";
    } else {
        echo "ℹ️  User 'wuzapi' already exists\n";
        // Update password just in case
        $pdo->exec("ALTER USER wuzapi WITH PASSWORD 'wuzapi'");
        echo "✅ Password updated\n";
    }
    
    // Check if wuzapi database exists
    $result = $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'wuzapi'")->fetch();
    
    if (!$result) {
        echo "📝 Creating database 'wuzapi'...\n";
        $pdo->exec("CREATE DATABASE wuzapi OWNER wuzapi");
        echo "✅ Database 'wuzapi' created\n";
    } else {
        echo "ℹ️  Database 'wuzapi' already exists\n";
    }
    
    // Grant permissions
    echo "📝 Granting permissions...\n";
    $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi");
    echo "✅ Permissions granted\n";
    
    echo "\n✅ WuzAPI database setup complete!\n";
    echo "   Database: wuzapi\n";
    echo "   User: wuzapi\n";
    echo "   Password: wuzapi\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "⚠️  This is not critical - WuzAPI will be configured on first use\n\n";
    exit(0); // Don't fail the deployment
}

