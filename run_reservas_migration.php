<?php
/**
 * Script para executar a migration de reservas
 */

require_once __DIR__ . '/vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    echo "========================================\n";
    echo "EXECUTANDO MIGRATION: create_reservas_table\n";
    echo "========================================\n\n";
    
    // Read migration file
    $migrationFile = __DIR__ . '/database/migrations/create_reservas_table.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    echo "Lendo arquivo: $migrationFile\n";
    echo "Tamanho: " . strlen($sql) . " bytes\n\n";
    
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
    
    echo "Encontrados " . count($statements) . " statements SQL\n\n";
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            echo "Executando statement " . ($index + 1) . "...\n";
            $db->query($statement);
            $executed++;
            echo "✅ Statement " . ($index + 1) . " executado com sucesso\n";
        } catch (Exception $e) {
            // Ignore "already exists" errors for CREATE TABLE IF NOT EXISTS
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'duplicate key') !== false ||
                strpos($errorMsg, 'duplicate') !== false) {
                echo "⚠️  Statement " . ($index + 1) . " já existe (ignorado)\n";
            } else {
                $errors[] = "Statement " . ($index + 1) . ": " . $errorMsg;
                echo "❌ Erro no statement " . ($index + 1) . ": " . $errorMsg . "\n";
            }
        }
    }
    
    echo "\n========================================\n";
    echo "RESULTADO\n";
    echo "========================================\n";
    echo "Statements executados: $executed\n";
    echo "Erros: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErros encontrados:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    // Verify table was created
    try {
        $tableExists = $db->fetch("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'reservas'
            )
        ");
        
        if ($tableExists && ($tableExists['exists'] === true || $tableExists['exists'] === 't')) {
            echo "\n✅ Tabela 'reservas' criada/verificada com sucesso!\n";
            
            // Check columns
            $columns = $db->fetchAll("
                SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_name = 'reservas'
                ORDER BY ordinal_position
            ");
            
            echo "\nColunas da tabela:\n";
            foreach ($columns as $column) {
                echo "  - {$column['column_name']} ({$column['data_type']})\n";
            }
        } else {
            echo "\n⚠️  Tabela 'reservas' não foi encontrada após a execução\n";
        }
    } catch (Exception $e) {
        echo "\n⚠️  Erro ao verificar tabela: " . $e->getMessage() . "\n";
    }
    
    echo "\n========================================\n";
    echo "✅ MIGRATION CONCLUÍDA\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "❌ ERRO NA MIGRATION\n";
    echo "========================================\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

