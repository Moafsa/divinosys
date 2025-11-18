<?php
/**
 * Endpoint para executar migration create_financial_system
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Read migration file
    $migrationFile = __DIR__ . '/../../database/migrations/create_financial_system.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split SQL into statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $db->query($statement);
            $results[] = "✅ Executado: " . substr($statement, 0, 60) . "...";
        } catch (Exception $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'duplicate') === false) {
                $results[] = "⚠️ Erro: " . substr($e->getMessage(), 0, 100);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration executada com sucesso!',
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

