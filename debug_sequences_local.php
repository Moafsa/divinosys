<?php
/**
 * DEBUG: Verificar sequências locais
 */

require_once 'vendor/autoload.php';

echo "🔍 DIAGNOSTICANDO SEQUÊNCIAS LOCAIS\n";
echo "===================================\n\n";

try {
    $db = \System\Database::getInstance();
    
    // Verificar sequências principais
    $tables = [
        'produtos' => 'produtos_id_seq',
        'categorias' => 'categorias_id_seq', 
        'ingredientes' => 'ingredientes_id_seq',
        'mesas' => 'mesas_id_seq',
        'pedido' => 'pedido_idpedido_seq',
        'pedido_itens' => 'pedido_itens_id_seq'
    ];
    
    echo "📊 STATUS DAS SEQUÊNCIAS:\n";
    echo "-------------------------\n";
    
    foreach ($tables as $table => $sequence) {
        try {
            // Valor atual da sequência
            $seqStmt = $db->query("SELECT last_value FROM $sequence");
            $seqValue = $seqStmt->fetchColumn();
            
            // MAX ID da tabela
            $idColumn = ($table === 'pedido') ? 'idpedido' : 'id';
            $maxStmt = $db->query("SELECT COALESCE(MAX($idColumn), 0) FROM $table");
            $maxId = $maxStmt->fetchColumn();
            
            $status = $seqValue > $maxId ? '✅ OK' : '❌ PROBLEMA';
            
            echo sprintf("%-15s | Seq: %-5d | Max: %-5d | %s\n", 
                $table, $seqValue, $maxId, $status);
                
        } catch (Exception $e) {
            echo sprintf("%-15s | ERRO: %s\n", $table, $e->getMessage());
        }
    }
    
    echo "\n🔧 TESTE DE INSERÇÃO:\n";
    echo "--------------------\n";
    
    // Testar inserção de categoria
    try {
        echo "Testando inserção de categoria...\n";
        $stmt = $db->getConnection()->prepare("INSERT INTO categorias (nome, descricao, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Debug ' . time(), 'Teste local', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $categoryId = $db->getConnection()->lastInsertId();
        echo "✅ Categoria criada com ID: $categoryId\n";
        
        // Testar inserção de produto
        echo "Testando inserção de produto...\n";
        $stmt = $db->getConnection()->prepare("INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Debug Produto ' . time(), 'Teste local', 10.99, 9.99, $categoryId, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $productId = $db->getConnection()->lastInsertId();
        echo "✅ Produto criado com ID: $productId\n";
        
        // Testar inserção de ingrediente
        echo "Testando inserção de ingrediente...\n";
        $stmt = $db->getConnection()->prepare("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Debug Ingrediente ' . time(), 'Teste local', 1.50, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $ingredientId = $db->getConnection()->lastInsertId();
        echo "✅ Ingrediente criado com ID: $ingredientId\n";
        
    } catch (Exception $e) {
        echo "❌ ERRO na inserção: " . $e->getMessage() . "\n";
    }
    
    echo "\n📋 STATUS FINAL DAS SEQUÊNCIAS:\n";
    echo "-------------------------------\n";
    
    foreach ($tables as $table => $sequence) {
        try {
            $seqStmt = $db->query("SELECT last_value FROM $sequence");
            $seqValue = $seqStmt->fetchColumn();
            
            $idColumn = ($table === 'pedido') ? 'idpedido' : 'id';
            $maxStmt = $db->query("SELECT COALESCE(MAX($idColumn), 0) FROM $table");
            $maxId = $maxStmt->fetchColumn();
            
            $status = $seqValue > $maxId ? '✅ OK' : '❌ PROBLEMA';
            
            echo sprintf("%-15s | Seq: %-5d | Max: %-5d | %s\n", 
                $table, $seqValue, $maxId, $status);
                
        } catch (Exception $e) {
            echo sprintf("%-15s | ERRO: %s\n", $table, $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}
?>
