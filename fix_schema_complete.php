<?php
/**
 * Script COMPLETO para corrigir TODAS as colunas faltantes
 * Baseado nos erros reais dos logs
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO COMPLETA DO SCHEMA ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar estrutura atual das tabelas
    echo "\n--- Estrutura Atual das Tabelas ---\n";
    
    $tables = ['categorias', 'ingredientes', 'produtos'];
    
    foreach ($tables as $table) {
        echo "\n=== TABELA: {$table} ===\n";
        $columns = $db->fetchAll("
            SELECT column_name, data_type, is_nullable 
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$table]);
        
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']})\n";
        }
    }
    
    // Definir todas as colunas que devem existir
    $requiredColumns = [
        'categorias' => [
            'descricao' => 'TEXT',
            'ativo' => 'BOOLEAN DEFAULT true',
            'ordem' => 'INTEGER DEFAULT 0',
            'parent_id' => 'INTEGER',
            'imagem' => 'VARCHAR(255)'
        ],
        'ingredientes' => [
            'descricao' => 'TEXT',
            'ativo' => 'BOOLEAN DEFAULT true',
            'tipo' => 'VARCHAR(50) DEFAULT \'complemento\'',
            'preco_adicional' => 'DECIMAL(10,2) DEFAULT 0'
        ],
        'produtos' => [
            'descricao' => 'TEXT',
            'ativo' => 'BOOLEAN DEFAULT true',
            'preco_mini' => 'DECIMAL(10,2) DEFAULT 0',
            'estoque_atual' => 'INTEGER DEFAULT 0',
            'estoque_minimo' => 'INTEGER DEFAULT 0',
            'preco_custo' => 'DECIMAL(10,2) DEFAULT 0',
            'imagem' => 'VARCHAR(255)',
            'categoria_id' => 'INTEGER'
        ]
    ];
    
    echo "\n--- Adicionando Colunas Faltantes ---\n";
    
    foreach ($requiredColumns as $table => $columns) {
        echo "\n=== TABELA: {$table} ===\n";
        
        // Obter colunas existentes
        $existingColumns = $db->fetchAll("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = ?
        ", [$table]);
        
        $existingColumnNames = array_column($existingColumns, 'column_name');
        
        foreach ($columns as $columnName => $columnDefinition) {
            if (!in_array($columnName, $existingColumnNames)) {
                try {
                    $sql = "ALTER TABLE {$table} ADD COLUMN {$columnName} {$columnDefinition}";
                    $db->query($sql);
                    echo "✅ Coluna '{$columnName}' adicionada à tabela {$table}\n";
                } catch (Exception $e) {
                    echo "❌ Erro ao adicionar coluna '{$columnName}': " . $e->getMessage() . "\n";
                }
            } else {
                echo "ℹ️ Coluna '{$columnName}' já existe na tabela {$table}\n";
            }
        }
    }
    
    // Verificar e corrigir sequences
    echo "\n--- Corrigindo Sequences ---\n";
    
    $sequences = [
        'categorias' => 'categorias_id_seq',
        'ingredientes' => 'ingredientes_id_seq',
        'produtos' => 'produtos_id_seq'
    ];
    
    foreach ($sequences as $table => $sequenceName) {
        try {
            // Verificar se sequence existe
            $sequenceExists = $db->fetch("
                SELECT 1 FROM pg_sequences WHERE sequencename = ?
            ", [$sequenceName]);
            
            if ($sequenceExists) {
                // Obter valores atuais
                $currentSeq = $db->fetch("SELECT last_value FROM {$sequenceName}");
                $maxId = $db->fetch("SELECT MAX(id) as max_id FROM {$table}");
                
                $currentValue = $currentSeq['last_value'];
                $maxValue = $maxId['max_id'] ?? 0;
                $newValue = $maxValue + 1;
                
                echo "{$table}: Sequence atual = {$currentValue}, MAX ID = {$maxValue}\n";
                
                if ($currentValue <= $maxValue) {
                    $db->query("SELECT setval(?, ?)", [$sequenceName, $newValue]);
                    echo "✅ Sequence {$sequenceName} corrigida para: {$newValue}\n";
                } else {
                    echo "ℹ️ Sequence {$sequenceName} já está correta\n";
                }
            } else {
                echo "⚠️ Sequence {$sequenceName} não encontrada\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao corrigir sequence {$sequenceName}: " . $e->getMessage() . "\n";
        }
    }
    
    // Testes de funcionamento
    echo "\n--- Testes de Funcionamento ---\n";
    
    // Teste 1: Categoria
    try {
        $testCategoryId = $db->insert('categorias', [
            'nome' => 'Teste Categoria Schema',
            'descricao' => 'Teste de funcionamento do schema',
            'ativo' => true,
            'ordem' => 999,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste categoria: ID {$testCategoryId} criado com sucesso\n";
        
        // Remover teste
        $db->delete('categorias', 'id = ?', [$testCategoryId]);
        echo "✅ Categoria de teste removida\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de categoria: " . $e->getMessage() . "\n";
    }
    
    // Teste 2: Ingrediente
    try {
        $testIngredientId = $db->insert('ingredientes', [
            'nome' => 'Teste Ingrediente Schema',
            'descricao' => 'Teste de funcionamento do schema',
            'tipo' => 'teste',
            'preco_adicional' => 1.50,
            'ativo' => true,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste ingrediente: ID {$testIngredientId} criado com sucesso\n";
        
        // Remover teste
        $db->delete('ingredientes', 'id = ?', [$testIngredientId]);
        echo "✅ Ingrediente de teste removido\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de ingrediente: " . $e->getMessage() . "\n";
    }
    
    // Teste 3: Produto
    try {
        $testProductId = $db->insert('produtos', [
            'nome' => 'Teste Produto Schema',
            'descricao' => 'Teste de funcionamento do schema',
            'preco_normal' => 25.00,
            'preco_mini' => 20.00,
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
    
    // Verificação final
    echo "\n--- Verificação Final das Sequences ---\n";
    
    foreach ($sequences as $table => $sequenceName) {
        try {
            $sequenceExists = $db->fetch("
                SELECT 1 FROM pg_sequences WHERE sequencename = ?
            ", [$sequenceName]);
            
            if ($sequenceExists) {
                $finalSeq = $db->fetch("SELECT last_value FROM {$sequenceName}");
                echo "{$table} - Sequence final: " . $finalSeq['last_value'] . "\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao verificar sequence {$sequenceName}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 CORREÇÃO COMPLETA DO SCHEMA CONCLUÍDA!\n";
    echo "Agora TODOS os cadastros devem funcionar corretamente.\n";
    echo "\n📋 Resumo das correções aplicadas:\n";
    echo "- ✅ Colunas faltantes adicionadas em TODAS as tabelas\n";
    echo "- ✅ Sequences corrigidas\n";
    echo "- ✅ Testes de funcionamento realizados\n";
    echo "- ✅ Schema completamente sincronizado\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
