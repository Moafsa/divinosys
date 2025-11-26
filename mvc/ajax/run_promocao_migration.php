<?php
/**
 * Endpoint para executar migration add_promocao_produtos
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Read migration file
    $migrationFile = __DIR__ . '/../../database/migrations/add_promocao_produtos.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    // Split SQL into statements
    $statements = [];
    $currentStatement = '';
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip empty lines and comments
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
            $results[] = "✅ Executado: " . substr($statement, 0, 60) . "...";
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Ignore "already exists" errors for IF NOT EXISTS
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'duplicate') !== false ||
                (strpos($errorMsg, 'column') !== false && strpos($errorMsg, 'already') !== false)) {
                $results[] = "ℹ️  Já existe: " . substr($statement, 0, 60) . "...";
            } else {
                $errors[] = $errorMsg;
                $results[] = "❌ Erro: " . substr($errorMsg, 0, 100);
            }
        }
    }
    
    // Verify columns were added
    $verification = [];
    try {
        $checkPrecoPromocional = $db->fetch("
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
              AND table_name = 'produtos' 
              AND column_name = 'preco_promocional'
            LIMIT 1
        ");
        
        $checkEmPromocao = $db->fetch("
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
              AND table_name = 'produtos' 
              AND column_name = 'em_promocao'
            LIMIT 1
        ");
        
        if ($checkPrecoPromocional && $checkEmPromocao) {
            $verification[] = "✅ Coluna 'preco_promocional' existe";
            $verification[] = "✅ Coluna 'em_promocao' existe";
        } else {
            if (!$checkPrecoPromocional) {
                $verification[] = "⚠️  Coluna 'preco_promocional' não encontrada";
            }
            if (!$checkEmPromocao) {
                $verification[] = "⚠️  Coluna 'em_promocao' não encontrada";
            }
        }
    } catch (Exception $e) {
        $verification[] = "⚠️  Erro ao verificar colunas: " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => empty($errors),
        'message' => 'Migration executada!',
        'executed' => $executed,
        'errors' => $errors,
        'results' => $results,
        'verification' => $verification
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

