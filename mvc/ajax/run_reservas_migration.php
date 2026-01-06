<?php
/**
 * Endpoint para executar migration create_reservas_table
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Read migration file
    $migrationFile = __DIR__ . '/../../database/migrations/create_reservas_table.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    // Split SQL into statements (handling multi-line statements)
    $statements = [];
    $currentStatement = '';
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip empty lines and single-line comments
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        // If line ends with semicolon, it's a complete statement
        if (substr(rtrim($line), -1) === ';') {
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $db->query($statement);
            $executed++;
            $results[] = "✅ Executado: " . substr($statement, 0, 80) . "...";
        } catch (Exception $e) {
            // Ignore "already exists" errors for CREATE TABLE IF NOT EXISTS
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'duplicate key') !== false ||
                strpos($errorMsg, 'duplicate') !== false) {
                $results[] = "⚠️ Já existe (ignorado): " . substr($statement, 0, 60) . "...";
            } else {
                $errors[] = $errorMsg;
                $results[] = "❌ Erro: " . substr($errorMsg, 0, 100);
            }
        }
    }
    
    // Verify table was created
    $tableExists = false;
    $columns = [];
    try {
        $tableCheck = $db->fetch("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'reservas'
            ) as exists
        ");
        
        if ($tableCheck && ($tableCheck['exists'] === true || $tableCheck['exists'] === 't')) {
            $tableExists = true;
            
            // Get columns
            $columns = $db->fetchAll("
                SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_name = 'reservas'
                ORDER BY ordinal_position
            ");
        }
    } catch (Exception $e) {
        // Ignore verification errors
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration executada com sucesso!',
        'executed' => $executed,
        'errors' => count($errors),
        'table_exists' => $tableExists,
        'columns' => $columns,
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}













