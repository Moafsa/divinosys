<?php
/**
 * Investigar o erro de constraint única na tabela ingredientes
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

$db = \System\Database::getInstance();

echo "<h1>🔍 Investigação do Erro de Constraint Única</h1>";

try {
    echo "<h2>1. Verificar Constraints da Tabela Ingredientes</h2>";
    
    // Verificar constraints da tabela ingredientes
    $constraints = $db->fetchAll("
        SELECT 
            tc.constraint_name,
            tc.constraint_type,
            kcu.column_name,
            tc.table_name
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu 
            ON tc.constraint_name = kcu.constraint_name
        WHERE tc.table_name = 'ingredientes'
        ORDER BY tc.constraint_name, kcu.ordinal_position
    ");
    
    echo "<h3>Constraints da tabela ingredientes:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Constraint Name</th><th>Type</th><th>Column</th><th>Table</th></tr>";
    foreach ($constraints as $constraint) {
        echo "<tr>";
        echo "<td>{$constraint['constraint_name']}</td>";
        echo "<td>{$constraint['constraint_type']}</td>";
        echo "<td>{$constraint['column_name']}</td>";
        echo "<td>{$constraint['table_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Verificar Índices da Tabela Ingredientes</h2>";
    
    // Verificar índices
    $indexes = $db->fetchAll("
        SELECT 
            indexname,
            indexdef
        FROM pg_indexes 
        WHERE tablename = 'ingredientes'
        ORDER BY indexname
    ");
    
    echo "<h3>Índices da tabela ingredientes:</h3>";
    foreach ($indexes as $index) {
        echo "<p><strong>{$index['indexname']}:</strong> {$index['indexdef']}</p>";
    }
    
    echo "<h2>3. Verificar Ingredientes Existentes</h2>";
    
    // Verificar ingredientes existentes para o tenant 24
    $ingredientes_existentes = $db->fetchAll("
        SELECT nome, tenant_id, filial_id, COUNT(*) as total
        FROM ingredientes 
        WHERE tenant_id = 24
        GROUP BY nome, tenant_id, filial_id
        HAVING COUNT(*) > 1
        ORDER BY nome
    ");
    
    if (empty($ingredientes_existentes)) {
        echo "<p style='color: green;'>✅ Não há ingredientes duplicados encontrados</p>";
    } else {
        echo "<p style='color: red;'>❌ Ingredientes duplicados encontrados:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Total</th></tr>";
        foreach ($ingredientes_existentes as $ingrediente) {
            echo "<tr>";
            echo "<td>{$ingrediente['nome']}</td>";
            echo "<td>{$ingrediente['tenant_id']}</td>";
            echo "<td>{$ingrediente['filial_id']}</td>";
            echo "<td>{$ingrediente['total']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>4. Verificar Todos os Ingredientes do Tenant 24</h2>";
    
    $todos_ingredientes = $db->fetchAll("
        SELECT nome, tenant_id, filial_id, id
        FROM ingredientes 
        WHERE tenant_id = 24
        ORDER BY nome
    ");
    
    echo "<p>Total de ingredientes para tenant 24: " . count($todos_ingredientes) . "</p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th></tr>";
    foreach ($todos_ingredientes as $ingrediente) {
        echo "<tr>";
        echo "<td>{$ingrediente['id']}</td>";
        echo "<td>{$ingrediente['nome']}</td>";
        echo "<td>{$ingrediente['tenant_id']}</td>";
        echo "<td>{$ingrediente['filial_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>5. Testar Criação de Ingrediente</h2>";
    
    // Tentar criar um ingrediente de teste
    $nome_teste = 'Teste Constraint ' . date('H:i:s');
    
    try {
        $ingrediente_id = $db->insert('ingredientes', [
            'nome' => $nome_teste,
            'descricao' => 'Teste de constraint',
            'preco_adicional' => 1.00,
            'ativo' => 1,
            'tenant_id' => 24,
            'filial_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p style='color: green;'>✅ Ingrediente de teste criado com ID: $ingrediente_id</p>";
        
        // Verificar se foi criado
        $ingrediente_criado = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$ingrediente_id]);
        if ($ingrediente_criado) {
            echo "<p style='color: green;'>✅ Ingrediente encontrado no banco</p>";
            echo "<pre>";
            print_r($ingrediente_criado);
            echo "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao criar ingrediente: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>6. Verificar Estrutura da Tabela Ingredientes</h2>";
    
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'ingredientes' 
        ORDER BY ordinal_position
    ");
    
    echo "<h3>Colunas da tabela ingredientes:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Nullable</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "<td>{$col['column_default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>✅ Investigação Concluída!</h2>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>
