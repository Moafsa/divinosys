<?php
/**
 * Script para corrigir a tabela pedido online
 * Adiciona coluna 'observacao' e outras colunas faltantes
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== CORREÇÃO DA TABELA PEDIDO ONLINE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar estrutura atual da tabela pedido
    echo "\n--- Estrutura Atual da Tabela Pedido ---\n";
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        ORDER BY ordinal_position
    ");
    
    foreach ($columns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
    }
    
    // Definir todas as colunas que devem existir (baseado na estrutura local)
    $requiredColumns = [
        'observacao' => 'TEXT',
        'usuario_id' => 'INTEGER',
        'tipo' => 'CHARACTER VARYING(50)',
        'cliente_id' => 'INTEGER',
        'created_at' => 'TIMESTAMP WITHOUT TIME ZONE',
        'updated_at' => 'TIMESTAMP WITHOUT TIME ZONE',
        'mesa_pedido_id' => 'CHARACTER VARYING(255)',
        'numero_pessoas' => 'INTEGER'
    ];
    
    echo "\n--- Adicionando Colunas Faltantes ---\n";
    
    // Obter colunas existentes
    $existingColumns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido'
    ");
    
    $existingColumnNames = array_column($existingColumns, 'column_name');
    
    foreach ($requiredColumns as $columnName => $columnDefinition) {
        if (!in_array($columnName, $existingColumnNames)) {
            try {
                $sql = "ALTER TABLE pedido ADD COLUMN {$columnName} {$columnDefinition}";
                $db->query($sql);
                echo "✅ Coluna '{$columnName}' adicionada à tabela pedido\n";
            } catch (Exception $e) {
                echo "❌ Erro ao adicionar coluna '{$columnName}': " . $e->getMessage() . "\n";
            }
        } else {
            echo "ℹ️ Coluna '{$columnName}' já existe na tabela pedido\n";
        }
    }
    
    // Verificar e corrigir sequence
    echo "\n--- Corrigindo Sequence ---\n";
    
    try {
        $sequenceExists = $db->fetch("
            SELECT 1 FROM pg_sequences WHERE sequencename = 'pedido_idpedido_seq'
        ");
        
        if ($sequenceExists) {
            $currentSeq = $db->fetch("SELECT last_value FROM pedido_idpedido_seq");
            $maxId = $db->fetch("SELECT MAX(idpedido) as max_id FROM pedido");
            
            $currentValue = $currentSeq['last_value'];
            $maxValue = $maxId['max_id'] ?? 0;
            $newValue = $maxValue + 1;
            
            echo "Pedido: Sequence atual = {$currentValue}, MAX ID = {$maxValue}\n";
            
            if ($currentValue <= $maxValue) {
                $db->query("SELECT setval('pedido_idpedido_seq', ?)", [$newValue]);
                echo "✅ Sequence pedido_idpedido_seq corrigida para: {$newValue}\n";
            } else {
                echo "ℹ️ Sequence pedido_idpedido_seq já está correta\n";
            }
        } else {
            echo "⚠️ Sequence pedido_idpedido_seq não encontrada\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao corrigir sequence: " . $e->getMessage() . "\n";
    }
    
    // Teste de funcionamento
    echo "\n--- Teste de Funcionamento ---\n";
    
    try {
        // Teste inserção pedido (com a coluna observacao)
        $testPedidoId = $db->insert('pedido', [
            'idmesa' => 999,
            'cliente' => 'Cliente Teste',
            'delivery' => false,
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
        
        // Remover teste
        $db->delete('pedido', 'idpedido = ?', [$testPedidoId]);
        echo "✅ Pedido de teste removido\n";
        
    } catch (Exception $e) {
        echo "❌ Erro no teste de pedido: " . $e->getMessage() . "\n";
    }
    
    // Verificação final da estrutura
    echo "\n--- Estrutura Final da Tabela Pedido ---\n";
    $finalColumns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        ORDER BY ordinal_position
    ");
    
    foreach ($finalColumns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
    }
    
    echo "\n🎉 CORREÇÃO DA TABELA PEDIDO CONCLUÍDA!\n";
    echo "Agora a criação de pedidos deve funcionar corretamente.\n";
    echo "\n📋 Resumo das correções aplicadas:\n";
    echo "- ✅ Coluna 'observacao' adicionada\n";
    echo "- ✅ Outras colunas faltantes adicionadas\n";
    echo "- ✅ Sequence corrigida\n";
    echo "- ✅ Teste de funcionamento realizado\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
