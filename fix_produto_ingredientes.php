<?php
echo "=== FIXING PRODUTO_INGREDIENTES TABLE ===\n";

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

    // Check current structure
    echo "\n=== CURRENT TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'produto_ingredientes' ORDER BY ordinal_position");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['column_name']}: {$row['data_type']}\n";
    }

    // Add missing columns
    echo "\n=== ADDING MISSING COLUMNS ===\n";
    
    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN obrigatorio BOOLEAN DEFAULT FALSE");
        echo "✅ Added 'obrigatorio' column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ Column 'obrigatorio' already exists\n";
        } else {
            echo "❌ Error adding 'obrigatorio': " . $e->getMessage() . "\n";
        }
    }

    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN preco_adicional DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Added 'preco_adicional' column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ Column 'preco_adicional' already exists\n";
        } else {
            echo "❌ Error adding 'preco_adicional': " . $e->getMessage() . "\n";
        }
    }

    // Verify final structure
    echo "\n=== FINAL TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'produto_ingredientes' ORDER BY ordinal_position");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['column_name']}: {$row['data_type']}\n";
    }

    // Test the problematic query
    echo "\n=== TESTING PROBLEMATIC QUERY ===\n";
    try {
        $stmt = $pdo->prepare("SELECT i.*, pi.obrigatorio, pi.preco_adicional FROM ingredientes i JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id WHERE pi.produto_id = ? AND i.tenant_id = ? AND i.filial_id = ?");
        $stmt->execute([9, 1, 1]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Query executed successfully! Found " . count($results) . " records\n";
    } catch (PDOException $e) {
        echo "❌ Query failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ PRODUTO_INGREDIENTES TABLE FIXED!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>