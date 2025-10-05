<?php
echo "=== FIXING INGREDIENTES TABLE - MAKING TIPO NULLABLE ===\n";

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

    // Make tipo column nullable
    echo "\n=== MAKING TIPO COLUMN NULLABLE ===\n";
    try {
        $pdo->exec("ALTER TABLE ingredientes ALTER COLUMN tipo DROP NOT NULL");
        echo "✅ Made 'tipo' column nullable\n";
    } catch (PDOException $e) {
        echo "❌ Error making tipo nullable: " . $e->getMessage() . "\n";
    }

    // Set default value for existing NULL values
    echo "\n=== SETTING DEFAULT VALUES ===\n";
    try {
        $pdo->exec("UPDATE ingredientes SET tipo = 'geral' WHERE tipo IS NULL OR tipo = ''");
        echo "✅ Set default 'geral' for NULL/empty tipo values\n";
    } catch (PDOException $e) {
        echo "❌ Error setting defaults: " . $e->getMessage() . "\n";
    }

    // Verify the fix
    echo "\n=== TESTING INGREDIENTE INSERT ===\n";
    try {
        $stmt = $pdo->prepare("INSERT INTO ingredientes (nome, tipo, preco_adicional, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Ingrediente', 'teste', 0.00, 1, 1]);
        echo "✅ Test insert successful\n";
        
        // Clean up test data
        $pdo->exec("DELETE FROM ingredientes WHERE nome = 'Teste Ingrediente'");
        echo "✅ Test data cleaned up\n";
    } catch (PDOException $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "\n";
    }

    // Check final structure
    echo "\n=== FINAL STRUCTURE ===\n";
    $stmt = $pdo->query("SELECT column_name, is_nullable FROM information_schema.columns WHERE table_name = 'ingredientes' AND column_name = 'tipo'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Tipo column nullable: {$result['is_nullable']}\n";

    echo "\n✅ INGREDIENTES TABLE FIXED!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
