<?php
/**
 * Online Diagnostic Script
 * This script helps diagnose issues with the online deployment
 */

echo "<h2>🔍 Online System Diagnostic</h2>\n";

// Environment Information
echo "<h3>📋 Environment Information:</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Variable</th><th>Value</th></tr>\n";

$envVars = [
    'SERVER_NAME',
    'HTTP_HOST',
    'SERVER_SOFTWARE',
    'PHP_VERSION',
    'DOCUMENT_ROOT',
    'SCRIPT_FILENAME'
];

foreach ($envVars as $var) {
    $value = $_SERVER[$var] ?? 'Not set';
    echo "<tr><td>$var</td><td>$value</td></tr>\n";
}

echo "</table>\n";

// Database Configuration
echo "<h3>🗄️ Database Configuration:</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Variable</th><th>Value</th></tr>\n";

$dbVars = [
    'DB_HOST',
    'DB_PORT', 
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD'
];

foreach ($dbVars as $var) {
    $value = $_ENV[$var] ?? getenv($var) ?: 'Not set';
    if ($var === 'DB_PASSWORD') {
        $value = $value !== 'Not set' ? str_repeat('*', strlen($value)) : 'Not set';
    }
    echo "<tr><td>$var</td><td>$value</td></tr>\n";
}

echo "</table>\n";

// File System Check
echo "<h3>📁 File System Check:</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>File/Directory</th><th>Exists</th><th>Readable</th><th>Path</th></tr>\n";

$files = [
    'config/database.php',
    'system/Database.php',
    'mvc/ajax/produtos_fix.php',
    'mvc/views/gerenciar_produtos.php',
    'database/init/99_fix_sequences.sql'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $readable = $exists && is_readable($file);
    $path = realpath($file) ?: 'Not found';
    
    echo "<tr>";
    echo "<td>$file</td>";
    echo "<td>" . ($exists ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($readable ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>$path</td>";
    echo "</tr>\n";
}

echo "</table>\n";

// Directory Listing
echo "<h3>📂 Current Directory Contents:</h3>\n";
echo "<p>Current directory: " . getcwd() . "</p>\n";
echo "<ul>\n";
foreach (scandir('.') as $item) {
    if ($item !== '.' && $item !== '..') {
        $type = is_dir($item) ? '📁' : '📄';
        echo "<li>$type $item</li>\n";
    }
}
echo "</ul>\n";

// Database Connection Test
echo "<h3>🔌 Database Connection Test:</h3>\n";

$host = $_ENV['DB_HOST'] ?? 'postgres';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'divino_db';
$user = $_ENV['DB_USER'] ?? 'divino_user';
$password = $_ENV['DB_PASSWORD'] ?? 'divino_password';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>\n";
    
    // Test basic query
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "<p>Database version: $version</p>\n";
    
    // Check if tables exist
    echo "<h4>📊 Database Tables:</h4>\n";
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>\n";
    foreach ($tables as $table) {
        echo "<li>📋 $table</li>\n";
    }
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
}

// Network Test
echo "<h3>🌐 Network Test:</h3>\n";

$domains = [
    'divinosys.conext.click',
    'localhost',
    '127.0.0.1'
];

foreach ($domains as $domain) {
    $ip = gethostbyname($domain);
    if ($ip === $domain) {
        echo "<p style='color: red;'>❌ $domain: DNS resolution failed</p>\n";
    } else {
        echo "<p style='color: green;'>✅ $domain: $ip</p>\n";
    }
}

echo "<h3>📝 Recommendations:</h3>\n";
echo "<ul>\n";
echo "<li>If DNS resolution failed, check your domain configuration</li>\n";
echo "<li>If database connection failed, verify environment variables</li>\n";
echo "<li>If files are missing, check your deployment process</li>\n";
echo "<li>Run fix_sequences_online.php after fixing connection issues</li>\n";
echo "</ul>\n";
?>
