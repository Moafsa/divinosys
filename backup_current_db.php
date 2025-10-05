<?php
// Direct PDO connection
$host = 'localhost';
$port = '5432';
$dbname = 'divino_lanches';
$user = 'postgres';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

echo "=== BACKING UP CURRENT DATABASE ===\n";

try {
    // Get current timestamp for backup filename
    $timestamp = date('Y_m_d_H_i_s');
    $backupFile = "backup_before_restore_$timestamp.sql";
    
    // Export current database schema and data
    $command = "pg_dump -h localhost -U postgres -d divino_lanches > $backupFile";
    
    echo "Running: $command\n";
    
    // Set password via environment variable to avoid prompt
    putenv("PGPASSWORD=divino_password");
    
    // Execute pg_dump
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Backup created successfully: $backupFile\n";
        echo "File size: " . filesize($backupFile) . " bytes\n";
    } else {
        echo "❌ Backup failed with return code: $returnCode\n";
        echo "Output: " . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
