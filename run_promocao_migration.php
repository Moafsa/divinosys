<?php
/**
 * Script para executar migration add_promocao_produtos
 */

require_once __DIR__ . '/vendor/autoload.php';

use System\Database;

try {
    echo "========================================\n";
    echo "EXECUTANDO MIGRATION: add_promocao_produtos\n";
    echo "========================================\n\n";
    
    $db = Database::getInstance();
    
    // Read migration file
    $migrationFile = __DIR__ . '/database/migrations/add_promocao_produtos.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    echo "📄 Lendo arquivo: $migrationFile\n";
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    // Split SQL into statements (handle PostgreSQL comments and statements)
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
    
    echo "📝 Encontradas " . count($statements) . " declarações SQL\n\n";
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            echo "⏳ Executando declaração " . ($index + 1) . "...\n";
            $db->query($statement);
            $executed++;
            echo "✅ Declaração " . ($index + 1) . " executada com sucesso\n";
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Ignore "already exists" errors for IF NOT EXISTS
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'duplicate') !== false ||
                strpos($errorMsg, 'column') !== false && strpos($errorMsg, 'already') !== false) {
                echo "ℹ️  Declaração " . ($index + 1) . " já foi executada anteriormente (ignorando)\n";
            } else {
                $errors[] = "Erro na declaração " . ($index + 1) . ": " . $errorMsg;
                echo "❌ Erro na declaração " . ($index + 1) . ": " . $errorMsg . "\n";
            }
        }
    }
    
    echo "\n========================================\n";
    if (empty($errors)) {
        echo "✅ MIGRATION EXECUTADA COM SUCESSO!\n";
        echo "   Declarações executadas: $executed\n";
    } else {
        echo "⚠️  MIGRATION EXECUTADA COM AVISOS\n";
        echo "   Declarações executadas: $executed\n";
        echo "   Erros encontrados: " . count($errors) . "\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
    echo "========================================\n";
    
    // Verify columns were added
    echo "\n🔍 Verificando colunas criadas...\n";
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
            echo "✅ Colunas verificadas: preco_promocional e em_promocao existem na tabela produtos\n";
        } else {
            echo "⚠️  Algumas colunas podem não ter sido criadas\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Não foi possível verificar as colunas: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "❌ ERRO AO EXECUTAR MIGRATION\n";
    echo "========================================\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    if (php_sapi_name() === 'cli') {
        exit(1);
    }
}

