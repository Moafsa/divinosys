<?php
echo "=== FIXING INGREDIENTES CONSTRAINT ===\n";

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

    // Drop the existing constraint
    echo "\n=== DROPPING EXISTING CONSTRAINT ===\n";
    try {
        $pdo->exec("ALTER TABLE ingredientes DROP CONSTRAINT ingredientes_tipo_check");
        echo "✅ Dropped ingredientes_tipo_check constraint\n";
    } catch (PDOException $e) {
        echo "❌ Error dropping constraint: " . $e->getMessage() . "\n";
    }

    // Create a new constraint that allows NULL and the existing values
    echo "\n=== CREATING NEW CONSTRAINT ===\n";
    try {
        $pdo->exec("ALTER TABLE ingredientes ADD CONSTRAINT ingredientes_tipo_check CHECK (tipo IS NULL OR tipo IN ('pao', 'proteina', 'queijo', 'salada', 'molho', 'complemento'))");
        echo "✅ Created new constraint allowing NULL values\n";
    } catch (PDOException $e) {
        echo "❌ Error creating new constraint: " . $e->getMessage() . "\n";
    }

    // Test the fix
    echo "\n=== TESTING INGREDIENTE INSERT ===\n";
    try {
        // Test with NULL tipo
        $stmt = $pdo->prepare("INSERT INTO ingredientes (nome, tipo, preco_adicional, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Ingrediente NULL', NULL, 0.00, 1, 1]);
        echo "✅ Test insert with NULL tipo successful\n";
        
        // Test with valid tipo
        $stmt->execute(['Teste Ingrediente Valid', 'complemento', 0.00, 1, 1]);
        echo "✅ Test insert with valid tipo successful\n";
        
        // Clean up test data
        $pdo->exec("DELETE FROM ingredientes WHERE nome LIKE 'Teste Ingrediente%'");
        echo "✅ Test data cleaned up\n";
    } catch (PDOException $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ INGREDIENTES CONSTRAINT FIXED!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
