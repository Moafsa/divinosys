<?php
/**
 * Debug sequence issue for produtos table
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    
    echo "<h2>Debug: Sequence Issue for Produtos Table</h2>\n";
    
    // Check current sequence value
    $sequenceResult = $db->query("SELECT last_value FROM produtos_id_seq");
    $sequenceValue = $db->fetch($sequenceResult);
    echo "<h3>Current Sequence Value:</h3>\n";
    echo "produtos_id_seq last_value: " . $sequenceValue['last_value'] . "\n";
    
    // Check max ID in produtos table
    $maxIdResult = $db->query("SELECT MAX(id) as max_id FROM produtos");
    $maxId = $db->fetch($maxIdResult);
    echo "<h3>Max ID in produtos table:</h3>\n";
    echo "MAX(id): " . ($maxId['max_id'] ?: 'NULL') . "\n";
    
    // Check if there are any gaps or duplicates
    $allIds = $db->fetchAll("SELECT id FROM produtos ORDER BY id");
    echo "<h3>All IDs in produtos table:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th></tr>\n";
    foreach ($allIds as $row) {
        echo "<tr><td>{$row['id']}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Check for duplicates
    $duplicates = $db->fetchAll("SELECT id, COUNT(*) as count FROM produtos GROUP BY id HAVING COUNT(*) > 1");
    if (!empty($duplicates)) {
        echo "<h3 style='color: red;'>DUPLICATE IDs FOUND:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Count</th></tr>\n";
        foreach ($duplicates as $dup) {
            echo "<tr><td>{$dup['id']}</td><td>{$dup['count']}</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<h3 style='color: green;'>No duplicate IDs found.</h3>\n";
    }
    
    // Check sequence vs max ID
    $sequenceVal = $sequenceValue['last_value'];
    $maxIdVal = $maxId['max_id'] ?: 0;
    
    echo "<h3>Sequence Analysis:</h3>\n";
    if ($sequenceVal < $maxIdVal) {
        echo "<p style='color: red;'>PROBLEM: Sequence value ($sequenceVal) is LESS than max ID ($maxIdVal)</p>\n";
        echo "<p>This will cause duplicate key violations when inserting new products.</p>\n";
        
        echo "<h3>Fix Sequence:</h3>\n";
        $newSequenceValue = $maxIdVal + 1;
        echo "<p>Setting sequence to: $newSequenceValue</p>\n";
        
        try {
            $db->query("SELECT setval('produtos_id_seq', $newSequenceValue)");
            echo "<p style='color: green;'>✅ Sequence fixed! New value: $newSequenceValue</p>\n";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error fixing sequence: " . $e->getMessage() . "</p>\n";
        }
        
    } elseif ($sequenceVal == $maxIdVal) {
        echo "<p style='color: orange;'>Sequence value equals max ID. This might cause issues if the next insert tries to use the same ID.</p>\n";
        $newSequenceValue = $maxIdVal + 1;
        echo "<p>Setting sequence to: $newSequenceValue</p>\n";
        
        try {
            $db->query("SELECT setval('produtos_id_seq', $newSequenceValue)");
            echo "<p style='color: green;'>✅ Sequence updated to: $newSequenceValue</p>\n";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error updating sequence: " . $e->getMessage() . "</p>\n";
        }
        
    } else {
        echo "<p style='color: green;'>Sequence value is higher than max ID. This should be fine.</p>\n";
    }
    
    // Test inserting a product
    echo "<h3>Testing Product Insert:</h3>\n";
    try {
        $testResult = $db->query("
            INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ", [
            'TESTE SEQUENCE - REMOVER',
            'Teste de sequência',
            10.00,
            0,
            1, // Assumindo que existe categoria com ID 1
            1,
            1,
            1
        ]);
        
        $newId = $db->lastInsertId();
        echo "<p style='color: green;'>✅ Successfully inserted test product with ID: $newId</p>\n";
        
        // Clean up
        $db->query("DELETE FROM produtos WHERE id = ?", [$newId]);
        echo "<p>🧹 Cleaned up test product</p>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error inserting test product: " . $e->getMessage() . "</p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>FATAL ERROR: " . $e->getMessage() . "</p>\n";
}
?>
