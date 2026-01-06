<?php
/**
 * Script para executar a migration que adiciona cliente_id à tabela reservas
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Migration: Adicionar cliente_id à tabela reservas</h1>";

try {
    $db = \System\Database::getInstance();
    
    // Read migration file
    $migrationFile = __DIR__ . '/database/migrations/add_cliente_id_to_reservas.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    echo "<p>Lendo arquivo: $migrationFile</p>";
    
    // Split SQL into statements
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
    
    echo "<p>Encontrados " . count($statements) . " statements SQL</p>";
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $db->query($statement);
            $executed++;
            echo "<p style='color: green;'>✅ Statement " . ($index + 1) . " executado com sucesso</p>";
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            // Ignore "already exists" errors
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'duplicate') !== false ||
                strpos($errorMsg, 'column') !== false && strpos($errorMsg, 'already') !== false) {
                echo "<p style='color: orange;'>⚠️ Statement " . ($index + 1) . " já existe (ignorado): " . htmlspecialchars(substr($errorMsg, 0, 100)) . "</p>";
            } else {
                $errors[] = $errorMsg;
                echo "<p style='color: red;'>❌ Erro no statement " . ($index + 1) . ": " . htmlspecialchars($errorMsg) . "</p>";
            }
        }
    }
    
    // Verify column was added
    try {
        $columnExists = $db->fetch(
            "SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = 'reservas' 
                AND column_name = 'cliente_id'
            ) as exists"
        );
        
        if ($columnExists && ($columnExists['exists'] === true || $columnExists['exists'] === 't' || $columnExists['exists'] === 1)) {
            echo "<p style='color: green; font-weight: bold;'>✅ Coluna 'cliente_id' adicionada/verificada com sucesso!</p>";
        } else {
            echo "<p style='color: red;'>⚠️ Coluna 'cliente_id' não foi encontrada após a execução</p>";
        }
    } catch (\Exception $e) {
        echo "<p style='color: orange;'>⚠️ Erro ao verificar coluna: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>Resumo</h2>";
    echo "<p>Statements executados: $executed</p>";
    echo "<p>Erros: " . count($errors) . "</p>";
    
    if (count($errors) > 0) {
        echo "<h3>Erros encontrados:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (\Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}













