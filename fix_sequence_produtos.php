<?php
/**
 * Fix sequence issue for produtos table
 */

require_once 'config/database.php';

try {
    $db = \System\Database::getInstance();
    
    echo "Fixing produtos sequence...\n";
    
    // Get max ID from produtos table
    $maxIdResult = $db->query("SELECT MAX(id) as max_id FROM produtos");
    $maxId = $db->fetch($maxIdResult);
    $maxIdValue = $maxId['max_id'] ?: 0;
    
    echo "Max ID in produtos: $maxIdValue\n";
    
    // Set sequence to max ID + 1
    $newSequenceValue = $maxIdValue + 1;
    $db->query("SELECT setval('produtos_id_seq', $newSequenceValue)");
    
    echo "Sequence set to: $newSequenceValue\n";
    echo "✅ Sequence fixed!\n";
    
    // Verify the fix
    $sequenceResult = $db->query("SELECT last_value FROM produtos_id_seq");
    $sequenceValue = $db->fetch($sequenceResult);
    echo "Current sequence value: " . $sequenceValue['last_value'] . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
