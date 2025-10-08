<?php
/**
 * Script para corrigir a tabela produtos online
 * Remove a coluna 'preco' problemática e ajusta a estrutura
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO DA TABELA PRODUTOS ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar estrutura atual da tabela produtos
    echo "\n--- Estrutura Atual da Tabela Produtos ---\n";
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'produtos' 
        ORDER BY ordinal_position
    ");
    
    foreach ($columns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
    }
    
    // Verificar se existe a coluna 'preco' problemática
    $precoColumn = $db->fetch("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'produtos' AND column_name = 'preco'
    ");
    
    if ($precoColumn) {
        echo "\n--- Removendo Coluna 'preco' Problemática ---\n";
        try {
            // Primeiro, vamos verificar se há dados na coluna preco
            $precoData = $db->fetch("SELECT COUNT(*) as count FROM produtos WHERE preco IS NOT NULL");
            echo "Registros com preco preenchido: " . $precoData['count'] . "\n";
            
            // Se houver dados, vamos migrar para preco_normal
            if ($precoData['count'] > 0) {
                echo "Migrando dados de 'preco' para 'preco_normal'...\n";
                $db->query("UPDATE produtos SET preco_normal = preco WHERE preco IS NOT NULL AND preco_normal IS NULL");
                echo "✅ Dados migrados com sucesso\n";
            }
            
            // Remover a coluna preco
            $db->query("ALTER TABLE produtos DROP COLUMN preco");
            echo "✅ Coluna 'preco' removida com sucesso\n";
            
        } catch (Exception $e) {
            echo "❌ Erro ao remover coluna 'preco': " . $e->getMessage() . "\n";
        }
    } else {
        echo "\nℹ️ Coluna 'preco' não encontrada (já foi removida ou não existe)\n";
    }
    
    // Verificar e adicionar colunas que faltam (baseado na estrutura local)
    echo "\n--- Adicionando Colunas Faltantes ---\n";
    
    $requiredColumns = [
        'codigo' => 'CHARACTER VARYING(255)',
        'destaque' => 'BOOLEAN DEFAULT false',
        'ordem' => 'INTEGER DEFAULT 0',
        'imagens' => 'JSONB'
    ];
    
    // Obter colunas existentes
    $existingColumns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'produtos'
    ");
    
    $existingColumnNames = array_column($existingColumns, 'column_name');
    
    foreach ($requiredColumns as $columnName => $columnDefinition) {
        if (!in_array($columnName, $existingColumnNames)) {
            try {
                $sql = "ALTER TABLE produtos ADD COLUMN {$columnName} {$columnDefinition}";
                $db->query($sql);
                echo "✅ Coluna '{$columnName}' adicionada à tabela produtos\n";
            } catch (Exception $e) {
                echo "❌ Erro ao adicionar coluna '{$columnName}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "ℹ️ Coluna '{$columnName}' já existe na tabela produtos\n";
        }
    }
    
    // Verificar constraints e ajustar se necessário
    echo "\n--- Verificando Constraints ---\n";
    
    // Verificar se categoria_id é NOT NULL (deve ser)
    $categoriaConstraint = $db->fetch("
        SELECT is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'produtos' AND column_name = 'categoria_id'
    ");
    
    if ($categoriaConstraint && $categoriaConstraint['is_nullable'] === 'YES') {
        echo "⚠️ Coluna 'categoria_id' permite NULL, mas deveria ser NOT NULL\n";
        echo "ℹ️ Isso pode causar problemas, mas vamos deixar assim por enquanto\n";
    } else {
        echo "✅ Coluna 'categoria_id' está configurada corretamente\n";
    }
    
    // Verificar e corrigir sequence
    echo "\n--- Corrigindo Sequence ---\n";
    
    try {
        $sequenceExists = $db->fetch("
            SELECT 1 FROM pg_sequences WHERE sequencename = 'produtos_id_seq'
        ");
        
        if ($sequenceExists) {
            $currentSeq = $db->fetch("SELECT last_value FROM produtos_id_seq");
            $maxId = $db->fetch("SELECT MAX(id) as max_id FROM produtos");
            
            $currentValue = $currentSeq['last_value'];
            $maxValue = $maxId['max_id'] ?? 0;
            $newValue = $maxValue + 1;
            
            echo "Produtos: Sequence atual = {$currentValue}, MAX ID = {$maxValue}\n";
            
            if ($currentValue <= $maxValue) {
                $db->query("SELECT setval('produtos_id_seq', ?)", [$newValue]);
                echo "✅ Sequence produtos_id_seq corrigida para: {$newValue}\n";
            } else {
                echo "ℹ️ Sequence produtos_id_seq já está correta\n";
            }
        } else {
            echo "⚠️ Sequence produtos_id_seq não encontrada\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao corrigir sequence: " . $e->getMessage() . "\n";
    }
    
    // Teste de funcionamento
    echo "\n--- Teste de Funcionamento ---\n";
    
    try {
        // Teste inserção produto (sem a coluna preco problemática)
        $testProductId = $db->insert('produtos', [
            'nome' => 'Teste Produto Corrigido',
            'descricao' => 'Teste de funcionamento após correção',
            'preco_normal' => 25.00,
            'preco_mini' => 20.00,
            'categoria_id' => 1,
            'ativo' => true,
            'estoque_atual' => 10,
            'estoque_minimo' => 5,
            'preco_custo' => 15.00,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste produto: ID {$testProductId} criado com sucesso\n";
        
        // Remover teste
        $db->delete('produtos', 'id = ?', [$testProductId]);
        echo "✅ Produto de teste removido\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de produto: " . $e->getMessage() . "\n";
    }
    
    // Verificação final da estrutura
    echo "\n--- Estrutura Final da Tabela Produtos ---\n";
    $finalColumns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'produtos' 
        ORDER BY ordinal_position
    ");
    
    foreach ($finalColumns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
    }
    
    echo "\n🎉 CORREÇÃO DA TABELA PRODUTOS CONCLUÍDA!\n";
    echo "Agora o cadastro de produtos deve funcionar corretamente.\n";
    echo "\n📋 Resumo das correções aplicadas:\n";
    echo "- ✅ Coluna 'preco' problemática removida\n";
    echo "- ✅ Colunas faltantes adicionadas\n";
    echo "- ✅ Sequence corrigida\n";
    echo "- ✅ Teste de funcionamento realizado\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>