<?php
/**
 * Fix pedido sequence specifically
 * This script fixes the pedido_idpedido_seq sequence issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Fixing pedido_idpedido_seq Sequence</h2>\n";

// Database connection parameters
$host = $_ENV['DB_HOST'] ?? 'postgres';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
$user = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? 'divino_password';

echo "<p>Connecting to database: $dbname@$host:$port</p>\n";

try {
    // Connect directly to PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>\n";
    
    // Check pedido table structure
    echo "<h3>1. Checking pedido table structure</h3>\n";
    
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'pedido' ORDER BY ordinal_position");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Column</th><th>Type</th></tr>\n";
    foreach ($columns as $column) {
        echo "<tr><td>{$column['column_name']}</td><td>{$column['data_type']}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Check current sequence value
    echo "<h3>2. Checking current sequence value</h3>\n";
    
    $stmt = $pdo->query("SELECT last_value FROM pedido_idpedido_seq");
    $currentValue = $stmt->fetchColumn();
    echo "<p>Current sequence value: $currentValue</p>\n";
    
    // Check max ID in pedido table
    echo "<h3>3. Checking max ID in pedido table</h3>\n";
    
    $stmt = $pdo->query("SELECT COALESCE(MAX(idpedido), 0) FROM pedido");
    $maxId = $stmt->fetchColumn();
    echo "<p>Max idpedido in table: $maxId</p>\n";
    
    // Fix sequence if needed
    echo "<h3>4. Fixing sequence</h3>\n";
    
    $newValue = $maxId + 1;
    if ($currentValue < $newValue) {
        $pdo->exec("SELECT setval('pedido_idpedido_seq', $newValue)");
        echo "<p style='color: green;'>✅ Sequence fixed! New value: $newValue</p>\n";
    } else {
        echo "<p style='color: blue;'>✅ Sequence already correct</p>\n";
    }
    
    // Test sequence by inserting a test record
    echo "<h3>5. Testing sequence functionality</h3>\n";
    
    // Check if we have any tenant/filial data
    $stmt = $pdo->query("SELECT id FROM tenants LIMIT 1");
    $tenantId = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT id FROM filiais LIMIT 1");
    $filialId = $stmt->fetchColumn();
    
    if ($tenantId && $filialId) {
        echo "<p>Using tenant_id: $tenantId, filial_id: $filialId</p>\n";
        
        // Insert test pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedido (
                idpedido,
                mesa_id,
                cliente_nome,
                cliente_telefone,
                status,
                total,
                tenant_id,
                filial_id,
                created_at
            ) VALUES (DEFAULT, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            1, // mesa_id
            'TESTE SEQUENCE',
            '11999999999',
            'aberto',
            10.00,
            $tenantId,
            $filialId
        ]);
        
        $testId = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Test pedido created with idpedido: $testId</p>\n";
        
        // Clean up
        $pdo->exec("DELETE FROM pedido WHERE idpedido = $testId");
        echo "<p>🧹 Test pedido cleaned up</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ No tenant/filial data found, skipping test</p>\n";
    }
    
    echo "<h3>✅ pedido_idpedido_seq Fixed Successfully!</h3>\n";
    echo "<p>The pedido sequence is now working correctly.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ ERROR: " . $e->getMessage() . "</p>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
