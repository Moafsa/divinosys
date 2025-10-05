<?php
echo "=== FIXING MESAS TABLE - ADDING NUMERO COLUMN ===\n";

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

    // Add numero column
    echo "\n=== ADDING NUMERO COLUMN ===\n";
    try {
        $pdo->exec("ALTER TABLE mesas ADD COLUMN numero INTEGER");
        echo "✅ Added 'numero' column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ Column 'numero' already exists\n";
        } else {
            echo "❌ Error adding 'numero': " . $e->getMessage() . "\n";
        }
    }

    // Update numero column with id_mesa values converted to integer
    echo "\n=== UPDATING NUMERO VALUES ===\n";
    try {
        $pdo->exec("UPDATE mesas SET numero = id_mesa::integer WHERE id_mesa ~ '^[0-9]+$'");
        echo "✅ Updated numero values from id_mesa\n";
    } catch (PDOException $e) {
        echo "❌ Error updating numero: " . $e->getMessage() . "\n";
    }

    // Set default values for any remaining NULL values
    try {
        $pdo->exec("UPDATE mesas SET numero = id WHERE numero IS NULL");
        echo "✅ Set default numero values from id\n";
    } catch (PDOException $e) {
        echo "❌ Error setting default numero: " . $e->getMessage() . "\n";
    }

    // Verify the fix
    echo "\n=== TESTING FIXED QUERY ===\n";
    try {
        $stmt = $pdo->prepare("SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY numero::integer");
        $stmt->execute([1, 1]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Query executed successfully! Found " . count($results) . " records\n";
        
        // Show sample data
        echo "\n=== SAMPLE DATA ===\n";
        foreach (array_slice($results, 0, 3) as $row) {
            echo "ID: {$row['id']}, ID_MESA: {$row['id_mesa']}, NUMERO: {$row['numero']}, NOME: {$row['nome']}\n";
        }
    } catch (PDOException $e) {
        echo "❌ Query still failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ MESAS TABLE FIXED!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
