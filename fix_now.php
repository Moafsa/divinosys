<?php
// Direct PDO connection to avoid System class issues
$host = $_ENV['DB_HOST'] ?? 'postgres';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
$user = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

echo "=== QUICK FIX ===\n";

try {
    // 1. Fix sequence
    echo "Fixing sequence...\n";
    $pdo->exec("SELECT setval('pedidos_idpedido_seq', 1, true)");
    echo "✅ Sequence fixed\n";
    
    // 2. Add preco_normal column if missing
    echo "Checking preco_normal column...\n";
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'produtos' AND column_name = 'preco_normal'
    ");
    $columns = $stmt->fetchAll();
    
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE produtos ADD COLUMN preco_normal DECIMAL(10,2) DEFAULT 0.00");
        $pdo->exec("UPDATE produtos SET preco_normal = COALESCE(preco, 10.00) WHERE preco_normal IS NULL OR preco_normal = 0");
        echo "✅ preco_normal column added\n";
    } else {
        echo "✅ preco_normal column exists\n";
    }
    
    // 3. Test queries
    echo "Testing queries...\n";
    
    // Test mesa query
    $stmt = $pdo->query("SELECT * FROM mesas WHERE id_mesa = 1 AND tenant_id = 1 AND filial_id = 1");
    $mesa = $stmt->fetch();
    echo "✅ Mesa query: " . ($mesa ? "Found" : "Not found") . "\n";
    
    // Test pedidos query
    $stmt = $pdo->query("SELECT * FROM pedido WHERE idmesa::varchar = '1' AND tenant_id = 1 AND filial_id = 1");
    $pedidos = $stmt->fetchAll();
    echo "✅ Pedidos query: " . count($pedidos) . " records\n";
    
    // Test produtos query
    $stmt = $pdo->query("SELECT id, nome, preco_normal FROM produtos LIMIT 1");
    $produtos = $stmt->fetchAll();
    echo "✅ Produtos query: " . count($produtos) . " records\n";
    
    echo "\n✅ ALL FIXES APPLIED!\n";
    echo "Dashboard and mesa popup should now work.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
