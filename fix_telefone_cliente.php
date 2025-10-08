<?php
/**
 * Script para adicionar coluna telefone_cliente na tabela pedido
 * Necessário para fechar pedidos individualmente ou fechar mesa
 */

// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    echo "=== ADIÇÃO DA COLUNA TELEFONE_CLIENTE ===\n\n";
    
    // Conectar ao banco
    $db = \System\Database::getInstance();
    echo "✅ Conectado ao banco de dados\n";
    
    // Verificar se a coluna já existe
    echo "\n--- Verificando Coluna telefone_cliente ---\n";
    $columnExists = $db->fetch("
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'pedido' AND column_name = 'telefone_cliente'
    ");
    
    if ($columnExists) {
        echo "ℹ️ Coluna 'telefone_cliente' já existe na tabela pedido\n";
    } else {
        echo "⚠️ Coluna 'telefone_cliente' NÃO existe na tabela pedido\n";
        
        // Adicionar a coluna
        echo "\n--- Adicionando Coluna telefone_cliente ---\n";
        try {
            $db->query("ALTER TABLE pedido ADD COLUMN telefone_cliente CHARACTER VARYING(20)");
            echo "✅ Coluna 'telefone_cliente' adicionada com sucesso\n";
        } catch (Exception $e) {
            echo "❌ Erro ao adicionar coluna: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    // Verificar estrutura atual
    echo "\n--- Estrutura Atual da Tabela Pedido ---\n";
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        AND (column_name LIKE '%cliente%' OR column_name LIKE '%telefone%')
        ORDER BY ordinal_position
    ");
    
    foreach ($columns as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) - Nullable: {$col['is_nullable']} - Default: " . ($col['column_default'] ?? 'NULL') . "\n";
    }
    
    // Teste de funcionamento
    echo "\n--- Teste de Funcionamento ---\n";
    try {
        // Buscar um pedido existente
        $pedido = $db->fetch("SELECT * FROM pedido LIMIT 1");
        
        if ($pedido) {
            echo "✅ Pedido encontrado para teste: ID {$pedido['idpedido']}\n";
            
            // Testar UPDATE com telefone_cliente
            $testUpdate = $db->query("
                UPDATE pedido 
                SET telefone_cliente = ? 
                WHERE idpedido = ?
            ", ['11999999999', $pedido['idpedido']]);
            
            echo "✅ Teste de UPDATE com telefone_cliente bem-sucedido\n";
            
            // Limpar o teste
            $db->query("
                UPDATE pedido 
                SET telefone_cliente = NULL 
                WHERE idpedido = ?
            ", [$pedido['idpedido']]);
            
            echo "✅ Teste limpo com sucesso\n";
        } else {
            echo "⚠️ Nenhum pedido encontrado para teste\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro no teste: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 CORREÇÃO CONCLUÍDA!\n";
    echo "A coluna 'telefone_cliente' está disponível na tabela pedido.\n";
    echo "Agora fechar pedidos individualmente ou fechar mesa deve funcionar.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
