<?php
echo "=== CHECKING MESA 256 ===\n";

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

    // Check mesa with id 256
    echo "\n=== CHECKING MESA ID 256 ===\n";
    $stmt = $pdo->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt->execute([256]);
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mesa) {
        echo "✅ Mesa 256 found:\n";
        foreach ($mesa as $key => $value) {
            echo "  - $key: $value\n";
        }
    } else {
        echo "❌ Mesa 256 NOT found\n";
    }

    // Check all mesas to see what's available
    echo "\n=== ALL MESAS ===\n";
    $stmt = $pdo->query("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, ID_MESA: {$row['id_mesa']}, NUMERO: {$row['numero']}, NOME: {$row['nome']}, STATUS: {$row['status']}\n";
    }

    // Check if there are any pedidos for mesa 256
    echo "\n=== PEDIDOS FOR MESA 256 ===\n";
    $stmt = $pdo->prepare("SELECT * FROM pedido WHERE idmesa = ?");
    $stmt->execute(['256']);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($pedidos) {
        echo "✅ Found " . count($pedidos) . " pedidos for mesa 256:\n";
        foreach ($pedidos as $pedido) {
            echo "  - Pedido ID: {$pedido['idpedido']}, Status: {$pedido['status']}, Valor: {$pedido['valor_total']}\n";
        }
    } else {
        echo "❌ No pedidos found for mesa 256\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
