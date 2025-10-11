<?php
/**
 * SOLUÇÃO SIMPLES E DIRETA PARA SEQUÊNCIAS
 */

require_once 'vendor/autoload.php';

echo "<h1>🔧 Corrigindo Sequências Agora</h1>";

try {
    $db = \System\Database::getInstance();
    
    echo "<h2>📊 Status Atual das Sequências:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Tabela</th><th>Sequência</th><th>Valor Atual</th><th>MAX ID</th><th>Status</th></tr>";
    
    $tables = [
        'produtos' => 'produtos_id_seq',
        'categorias' => 'categorias_id_seq', 
        'ingredientes' => 'ingredientes_id_seq',
        'pedido' => 'pedido_idpedido_seq',
        'pedido_itens' => 'pedido_itens_id_seq'
    ];
    
    foreach ($tables as $table => $sequence) {
        // Valor atual da sequência
        $stmt = $db->query("SELECT last_value FROM $sequence");
        $seqValue = $stmt->fetchColumn();
        
        // MAX ID da tabela
        $idColumn = ($table === 'pedido') ? 'idpedido' : 'id';
        $stmt = $db->query("SELECT COALESCE(MAX($idColumn), 0) FROM $table");
        $maxId = $stmt->fetchColumn();
        
        $status = $seqValue > $maxId ? '✅ OK' : '❌ PROBLEMA';
        $statusColor = $seqValue > $maxId ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td>$sequence</td>";
        echo "<td>$seqValue</td>";
        echo "<td>$maxId</td>";
        echo "<td style='color: $statusColor;'>$status</td>";
        echo "</tr>";
        
        // Corrigir se necessário
        if ($seqValue <= $maxId) {
            $newValue = $maxId + 1;
            $db->query("SELECT setval(?, ?, false)", [$sequence, $newValue]);
            echo "<tr><td colspan='5' style='color: blue;'>🔧 Corrigido $sequence: $seqValue → $newValue</td></tr>";
        }
    }
    
    echo "</table>";
    
    echo "<h2>🧪 Teste de Inserção:</h2>";
    
    // Testar categoria
    echo "<p>Testando categoria...</p>";
    $stmt = $db->getConnection()->prepare("INSERT INTO categorias (nome, descricao, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Fix ' . time(), 'Teste após correção', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $categoryId = $db->getConnection()->lastInsertId();
    echo "<p style='color: green;'>✅ Categoria criada com ID: $categoryId</p>";
    
    // Testar produto
    echo "<p>Testando produto...</p>";
    $stmt = $db->getConnection()->prepare("INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Fix Produto ' . time(), 'Teste após correção', 15.99, 14.99, $categoryId, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $productId = $db->getConnection()->lastInsertId();
    echo "<p style='color: green;'>✅ Produto criado com ID: $productId</p>";
    
    // Testar ingrediente
    echo "<p>Testando ingrediente...</p>";
    $stmt = $db->getConnection()->prepare("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Fix Ingrediente ' . time(), 'Teste após correção', 2.50, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $ingredientId = $db->getConnection()->lastInsertId();
    echo "<p style='color: green;'>✅ Ingrediente criado com ID: $ingredientId</p>";
    
    echo "<h2 style='color: green;'>🎉 CORREÇÃO CONCLUÍDA COM SUCESSO!</h2>";
    echo "<p>Agora você pode cadastrar produtos, categorias e ingredientes normalmente!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
