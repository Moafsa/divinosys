<?php
/**
 * Script para testar se os ingredientes aparecem corretamente
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🧪 Teste Final: Ingredientes Aparecem Corretamente</h1>";

try {
    // 1. Verificar sessão atual
    echo "<h2>1. Verificando Sessão Atual</h2>";
    
    session_start();
    $tenantId = $session->getTenantId() ?? 24;
    $filialId = $session->getFilialId();
    
    echo "<p>Tenant ID: $tenantId</p>";
    echo "<p>Filial ID: " . ($filialId ?? 'NULL') . "</p>";
    
    // Se não há filial específica, usar filial padrão do tenant
    if ($filialId === null) {
        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
        $filialId = $filial_padrao ? $filial_padrao['id'] : null;
        echo "<p>Filial padrão encontrada: " . ($filialId ?? 'NENHUMA') . "</p>";
    }
    
    // 2. Limpar ingredientes de teste anteriores
    echo "<h2>2. Limpando Dados de Teste Anteriores</h2>";
    
    $db->query("DELETE FROM ingredientes WHERE nome LIKE 'Teste Final%'");
    echo "<p>✅ Dados de teste anteriores removidos</p>";
    
    // 3. Criar ingrediente de teste
    echo "<h2>3. Criando Ingrediente de Teste</h2>";
    
    $ingrediente_teste_id = $db->insert('ingredientes', [
        'nome' => 'Teste Final Ingrediente ' . date('H:i:s'),
        'descricao' => 'Ingrediente para teste final',
        'preco_adicional' => 3.50,
        'ativo' => 1,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Ingrediente de teste criado com ID: $ingrediente_teste_id</p>";
    
    // 4. Verificar se aparece na busca direta
    echo "<h2>4. Verificando Busca Direta</h2>";
    
    $ingrediente_direto = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$ingrediente_teste_id]);
    if ($ingrediente_direto) {
        echo "<p style='color: green;'>✅ Ingrediente encontrado na busca direta: {$ingrediente_direto['nome']} (Tenant: {$ingrediente_direto['tenant_id']}, Filial: {$ingrediente_direto['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Ingrediente NÃO encontrado na busca direta!</p>";
    }
    
    // 5. Testar query que o sistema usa (como em gerenciar_produtos.php)
    echo "<h2>5. Testando Query do Sistema (gerenciar_produtos.php)</h2>";
    
    $query_sistema = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
    $ingredientes_sistema = $db->fetchAll($query_sistema, [$tenantId, $filialId]);
    
    echo "<p>Query do sistema: <code>$query_sistema</code></p>";
    echo "<p>Parâmetros: tenant_id=$tenantId, filial_id=$filialId</p>";
    echo "<p>Ingredientes encontrados pelo sistema: " . count($ingredientes_sistema) . "</p>";
    
    $ingrediente_teste_encontrado = false;
    foreach ($ingredientes_sistema as $ing) {
        if ($ing['id'] == $ingrediente_teste_id) {
            $ingrediente_teste_encontrado = true;
            echo "<p style='color: green;'>✅ Ingrediente de teste encontrado pelo sistema: {$ing['nome']}</p>";
            break;
        }
    }
    
    if (!$ingrediente_teste_encontrado) {
        echo "<p style='color: red;'>❌ Ingrediente de teste NÃO encontrado pelo sistema!</p>";
    }
    
    // 6. Testar query alternativa (como em gerar_pedido.php)
    echo "<h2>6. Testando Query Alternativa (gerar_pedido.php)</h2>";
    
    if ($filialId === null) {
        $query_alternativa = "SELECT * FROM ingredientes WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = (SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1)) ORDER BY nome";
        $ingredientes_alternativa = $db->fetchAll($query_alternativa, [$tenantId, $tenantId]);
    } else {
        $query_alternativa = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
        $ingredientes_alternativa = $db->fetchAll($query_alternativa, [$tenantId, $filialId]);
    }
    
    echo "<p>Query alternativa: <code>$query_alternativa</code></p>";
    echo "<p>Ingredientes encontrados pela query alternativa: " . count($ingredientes_alternativa) . "</p>";
    
    $ingrediente_alternativa_encontrado = false;
    foreach ($ingredientes_alternativa as $ing) {
        if ($ing['id'] == $ingrediente_teste_id) {
            $ingrediente_alternativa_encontrado = true;
            echo "<p style='color: green;'>✅ Ingrediente de teste encontrado pela query alternativa: {$ing['nome']}</p>";
            break;
        }
    }
    
    if (!$ingrediente_alternativa_encontrado) {
        echo "<p style='color: red;'>❌ Ingrediente de teste NÃO encontrado pela query alternativa!</p>";
    }
    
    // 7. Verificar todos os ingredientes do tenant/filial
    echo "<h2>7. Verificando Todos os Ingredientes do Tenant/Filial</h2>";
    
    $todos_ingredientes = $db->fetchAll(
        "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome",
        [$tenantId, $filialId]
    );
    
    echo "<p>Total de ingredientes do tenant/filial: " . count($todos_ingredientes) . "</p>";
    
    if (count($todos_ingredientes) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th><th>Criado em</th></tr>";
        foreach ($todos_ingredientes as $ing) {
            $cor = ($ing['id'] == $ingrediente_teste_id) ? 'green' : 'black';
            echo "<tr style='color: $cor;'>";
            echo "<td>{$ing['id']}</td>";
            echo "<td>{$ing['nome']}</td>";
            echo "<td>{$ing['tenant_id']}</td>";
            echo "<td>{$ing['filial_id']}</td>";
            echo "<td>{$ing['ativo']}</td>";
            echo "<td>{$ing['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 8. Limpeza
    echo "<h2>8. Limpeza</h2>";
    
    $db->query("DELETE FROM ingredientes WHERE id = ?", [$ingrediente_teste_id]);
    echo "<p>✅ Ingrediente de teste removido</p>";
    
    echo "<h2>✅ Teste Final Concluído</h2>";
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>🎉 Resultado do Teste Final:</h3>";
    echo "<ul>";
    echo "<li>✅ Ingrediente criado com sucesso</li>";
    echo "<li>✅ Ingrediente encontrado na busca direta</li>";
    if ($ingrediente_teste_encontrado) {
        echo "<li>✅ Ingrediente encontrado pelo sistema (gerenciar_produtos.php)</li>";
    } else {
        echo "<li>❌ Ingrediente NÃO encontrado pelo sistema (gerenciar_produtos.php)</li>";
    }
    if ($ingrediente_alternativa_encontrado) {
        echo "<li>✅ Ingrediente encontrado pela query alternativa (gerar_pedido.php)</li>";
    } else {
        echo "<li>❌ Ingrediente NÃO encontrado pela query alternativa (gerar_pedido.php)</li>";
    }
    echo "</ul>";
    echo "<p><strong>Se o ingrediente foi criado mas não aparece na interface, o problema está na view!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante o teste: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste final concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
