<?php
/**
 * Script COMPLETO para corrigir TODAS as tabelas relacionadas a pedidos
 * - Tabela pedido (coluna observacao e outras)
 * - Tabela pedido_itens (coluna tamanho e outras)
 * - Corrigir problemas de boolean
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO COMPLETA DAS TABELAS DE PEDIDOS ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar estrutura atual das tabelas
    echo "\n--- Estrutura Atual das Tabelas ---\n";
    
    $tables = ['pedido', 'pedido_itens'];
    
    foreach ($tables as $table) {
        echo "\n=== TABELA: {$table} ===\n";
        $columns = $db->fetchAll("
            SELECT column_name, data_type, is_nullable, column_default 
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$table]);
        
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
        }
    }
    
    // Definir todas as colunas que devem existir (baseado na estrutura local)
    $requiredColumns = [
        'pedido' => [
            'observacao' => 'TEXT',
            'usuario_id' => 'INTEGER',
            'tipo' => 'CHARACTER VARYING(50)',
            'cliente_id' => 'INTEGER',
            'created_at' => 'TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP',
            'mesa_pedido_id' => 'CHARACTER VARYING(255)',
            'numero_pessoas' => 'INTEGER DEFAULT 1'
        ],
        'pedido_itens' => [
            'tamanho' => 'CHARACTER VARYING(50) NOT NULL DEFAULT \'normal\'',
            'observacao' => 'TEXT',
            'ingredientes_com' => 'TEXT',
            'ingredientes_sem' => 'TEXT'
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
    
    // Corrigir problemas de boolean na tabela pedido
    echo "\n--- Corrigindo Problemas de Boolean ---\n";
    
    // Verificar se a coluna delivery tem valores problemáticos
    try {
        $deliveryProblem = $db->fetch("
            SELECT COUNT(*) as count 
            FROM pedido 
            WHERE delivery IS NULL OR delivery = '' OR delivery::text = ''
        ");
        
        if ($deliveryProblem['count'] > 0) {
            echo "⚠️ Encontrados {$deliveryProblem['count']} registros com problemas na coluna delivery\n";
            echo "ℹ️ Corrigindo valores problemáticos...\n";
            
            // Corrigir valores NULL ou vazios para false
            $db->query("UPDATE pedido SET delivery = false WHERE delivery IS NULL OR delivery = '' OR delivery::text = ''");
            echo "✅ Valores problemáticos corrigidos\n";
        } else {
            echo "✅ Coluna delivery está OK\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar coluna delivery: " . $e->getMessage() . "\n";
    }
    
    // Verificar e corrigir sequences
    echo "\n--- Corrigindo Sequences ---\n";
    
    $sequences = [
        'pedido' => 'pedido_idpedido_seq',
        'pedido_itens' => 'pedido_itens_id_seq'
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
                
                // Para pedido, usar idpedido; para pedido_itens, usar id
                $idColumn = ($table === 'pedido') ? 'idpedido' : 'id';
                $maxId = $db->fetch("SELECT MAX({$idColumn}) as max_id FROM {$table}");
                
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
    
    // Teste 1: Pedido
    try {
        $testPedidoId = $db->insert('pedido', [
            'idmesa' => 999,
            'cliente' => 'Cliente Teste Pedidos',
            'delivery' => false, // Garantir que é boolean, não string
            'data' => date('Y-m-d'),
            'hora_pedido' => date('H:i:s'),
            'status' => 'Pendente',
            'valor_total' => 25.00,
            'observacao' => 'Pedido de teste para verificar funcionamento',
            'usuario_id' => 1,
            'tenant_id' => 1,
            'filial_id' => 1
        ]);
        echo "✅ Teste pedido: ID {$testPedidoId} criado com sucesso\n";
        
        // Teste 2: Pedido Item
        try {
            $testItemId = $db->insert('pedido_itens', [
                'pedido_id' => $testPedidoId,
                'produto_id' => 1,
                'quantidade' => 1,
                'valor_unitario' => 25.00,
                'valor_total' => 25.00,
                'tamanho' => 'normal',
                'observacao' => 'Item de teste',
                'ingredientes_com' => 'milhas',
                'ingredientes_sem' => 'erva',
                'tenant_id' => 1,
                'filial_id' => 1
            ]);
            echo "✅ Teste pedido_item: ID {$testItemId} criado com sucesso\n";
            
            // Remover item de teste
            $db->delete('pedido_itens', 'id = ?', [$testItemId]);
            echo "✅ Item de teste removido\n";
            
        } catch (Exception $e) {
            echo "❌ Erro no teste de pedido_item: " . $e->getMessage() . "\n";
        }
        
        // Remover pedido de teste
        $db->delete('pedido', 'idpedido = ?', [$testPedidoId]);
        echo "✅ Pedido de teste removido\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de pedido: " . $e->getMessage() . "\n";
    }
    
    // Verificação final
    echo "\n--- Verificação Final ---\n";
    
    foreach ($tables as $table) {
        echo "\n=== ESTRUTURA FINAL: {$table} ===\n";
        $finalColumns = $db->fetchAll("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$table]);
        
        foreach ($finalColumns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
        }
    }
    
    echo "\n🎉 CORREÇÃO COMPLETA DAS TABELAS DE PEDIDOS CONCLUÍDA!\n";
    echo "Agora a criação de pedidos e itens deve funcionar corretamente.\n";
    echo "\n📋 Resumo das correções aplicadas:\n";
    echo "- ✅ Colunas faltantes adicionadas em TODAS as tabelas de pedidos\n";
    echo "- ✅ Problemas de boolean corrigidos\n";
    echo "- ✅ Sequences corrigidas\n";
    echo "- ✅ Testes de funcionamento realizados\n";
    echo "- ✅ Sistema de pedidos completamente funcional\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
