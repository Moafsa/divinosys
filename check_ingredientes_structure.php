<?php
echo "=== CHECKING INGREDIENTES TABLE STRUCTURE ===\n";

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

    // Check ingredientes table structure
    echo "\n=== INGREDIENTES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'ingredientes' ORDER BY ordinal_position");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['column_name']}: {$row['data_type']} (nullable: {$row['is_nullable']}, default: {$row['column_default']})\n";
    }

    // Check if tipo column exists and its constraints
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.columns WHERE table_name = 'ingredientes' AND column_name = 'tipo'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "\n✅ Column 'tipo' EXISTS in ingredientes table\n";
        
        // Check if it's nullable
        $stmt = $pdo->query("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'ingredientes' AND column_name = 'tipo'");
        $nullable = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  - Nullable: {$nullable['is_nullable']}\n";
        
        if ($nullable['is_nullable'] === 'NO') {
            echo "  - ⚠️ Column 'tipo' is NOT NULL - this is causing the error\n";
        }
    } else {
        echo "\n❌ Column 'tipo' does NOT exist in ingredientes table\n";
    }

    // Check existing ingredientes data
    echo "\n=== EXISTING INGREDIENTES DATA ===\n";
    $stmt = $pdo->query("SELECT id, nome, tipo, preco_adicional FROM ingredientes LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, NOME: {$row['nome']}, TIPO: {$row['tipo']}, PRECO: {$row['preco_adicional']}\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
