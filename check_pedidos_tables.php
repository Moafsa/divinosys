<?php
echo "=== CHECKING PEDIDOS TABLES ===\n";

// Database connection
$host = 'postgres';
$port = '5432';
$dbname = 'divino_db';
$user = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection established\n";

    // Check all tables that contain 'pedido'
    echo "\n=== TABLES CONTAINING 'PEDIDO' ===\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%pedido%' AND table_schema = 'public' ORDER BY table_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['table_name']}\n";
    }

    // Check if specific tables exist
    $tables_to_check = ['pedidos', 'pedido', 'pedidoss'];
    echo "\n=== CHECKING SPECIFIC TABLES ===\n";
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = '$table' AND table_schema = 'public'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['count'] > 0) {
            echo "✅ Table '$table' EXISTS\n";
            
            // Count records
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  - Records: {$count['count']}\n";
        } else {
            echo "❌ Table '$table' does NOT exist\n";
        }
    }

    // Test the problematic query with different table names
    echo "\n=== TESTING QUERIES WITH DIFFERENT TABLE NAMES ===\n";
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = ? AND filial_id = ?");
            $stmt->execute([1, 1]);
            $count = $stmt->fetchColumn();
            echo "✅ Query with '$table' works: $count records\n";
        } catch (PDOException $e) {
            echo "❌ Query with '$table' failed: " . $e->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
