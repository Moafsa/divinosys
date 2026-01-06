<?php
/**
 * Script para executar a migration de timezone
 */

require 'index.php';

try {
    $db = \System\Database::getInstance();
    $sql = file_get_contents('database/migrations/add_timezone_to_filiais.sql');
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    $executed = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $db->query($statement);
                $executed++;
                echo "✅ Executado: " . substr($statement, 0, 60) . "...\n";
            } catch (Exception $e) {
                // Ignore "column already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'duplicate') === false) {
                    echo "⚠️  Aviso: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "\n✅ Migration de timezone concluída! ($executed statements executados)\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>













