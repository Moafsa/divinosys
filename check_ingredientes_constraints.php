<?php
echo "=== CHECKING INGREDIENTES CONSTRAINTS ===\n";

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

    // Check constraints on ingredientes table
    echo "\n=== CHECK CONSTRAINTS ===\n";
    $stmt = $pdo->query("SELECT constraint_name, check_clause FROM information_schema.check_constraints WHERE constraint_name LIKE '%ingredientes%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['constraint_name']}: {$row['check_clause']}\n";
    }

    // Check what tipo values are currently used
    echo "\n=== CURRENT TIPO VALUES ===\n";
    $stmt = $pdo->query("SELECT DISTINCT tipo, COUNT(*) as count FROM ingredientes GROUP BY tipo ORDER BY tipo");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['tipo']}: {$row['count']} records\n";
    }

    // Try to find the constraint definition
    echo "\n=== CONSTRAINT DEFINITION ===\n";
    $stmt = $pdo->query("SELECT conname, pg_get_constraintdef(oid) as definition FROM pg_constraint WHERE conrelid = 'ingredientes'::regclass AND contype = 'c'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['conname']}: {$row['definition']}\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
