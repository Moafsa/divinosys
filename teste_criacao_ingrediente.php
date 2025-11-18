<?php
/**
 * Script para testar a criação de ingrediente exatamente como a interface faz
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🧪 Teste: Criação de Ingrediente via Interface</h1>";

try {
    // 1. Verificar sessão atual
    echo "<h2>1. Verificando Sessão Atual</h2>";
    
    session_start();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId();
    
    echo "<p>Tenant ID: $tenantId</p>";
    echo "<p>Filial ID: " . ($filialId ?? 'NULL') . "</p>";
    
    // 2. Simular exatamente o que o AJAX faz
    echo "<h2>2. Simulando Lógica do AJAX</h2>";
    
    // Simular dados do formulário
    $nome = 'Teste AJAX ' . date('H:i:s');
    $descricao = 'Ingrediente criado via AJAX';
    $precoAdicional = 4.50;
    $ativo = 1;
    
    echo "<p>Dados do formulário:</p>";
    echo "<ul>";
    echo "<li>Nome: $nome</li>";
    echo "<li>Descrição: $descricao</li>";
    echo "<li>Preço Adicional: $precoAdicional</li>";
    echo "<li>Ativo: $ativo</li>";
    echo "</ul>";
    
    // 3. Verificar se filialId é null e aplicar fallback
    echo "<h2>3. Verificando Fallback para Filial ID</h2>";
    
    if ($filialId === null) {
        echo "<p style='color: orange;'>⚠️ Filial ID é NULL, aplicando fallback...</p>";
        
        // Aplicar fallback como no AJAX
        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
        $filialId = $filial_padrao ? $filial_padrao['id'] : null;
        
        echo "<p>Filial padrão encontrada: " . ($filialId ?? 'NENHUMA') . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Filial ID já definido: $filialId</p>";
    }
    
    // 4. Criar ingrediente exatamente como o AJAX faz
    echo "<h2>4. Criando Ingrediente como AJAX</h2>";
    
    $ingrediente_id = $db->insert('ingredientes', [
        'nome' => $nome,
        'descricao' => $descricao,
        'preco_adicional' => $precoAdicional,
        'ativo' => $ativo,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Ingrediente criado com ID: $ingrediente_id</p>";
    
    // 5. Verificar se aparece na busca
    echo "<h2>5. Verificando se Aparece na Busca</h2>";
    
    $ingrediente_encontrado = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$ingrediente_id]);
    if ($ingrediente_encontrado) {
        echo "<p style='color: green;'>✅ Ingrediente encontrado na busca direta: {$ingrediente_encontrado['nome']} (Tenant: {$ingrediente_encontrado['tenant_id']}, Filial: {$ingrediente_encontrado['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Ingrediente NÃO encontrado na busca direta!</p>";
    }
    
    // 6. Testar query que a view usa
    echo "<h2>6. Testando Query da View</h2>";
    
    $query_view = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
    $ingredientes_view = $db->fetchAll($query_view, [$tenantId, $filialId]);
    
    echo "<p>Query da view: <code>$query_view</code></p>";
    echo "<p>Parâmetros: tenant_id=$tenantId, filial_id=$filialId</p>";
    echo "<p>Ingredientes encontrados pela view: " . count($ingredientes_view) . "</p>";
    
    $ingrediente_view_encontrado = false;
    foreach ($ingredientes_view as $ing) {
        if ($ing['id'] == $ingrediente_id) {
            $ingrediente_view_encontrado = true;
            echo "<p style='color: green;'>✅ Ingrediente encontrado pela view: {$ing['nome']}</p>";
            break;
        }
    }
    
    if (!$ingrediente_view_encontrado) {
        echo "<p style='color: red;'>❌ Ingrediente NÃO encontrado pela view!</p>";
    }
    
    // 7. Verificar se há ingredientes com filial_id diferente
    echo "<h2>7. Verificando Ingredientes com Filial ID Diferente</h2>";
    
    $ingredientes_outra_filial = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id != ? AND filial_id IS NOT NULL", [$tenantId, $filialId]);
    echo "<p>Ingredientes com filial_id diferente: " . count($ingredientes_outra_filial) . "</p>";
    
    if (count($ingredientes_outra_filial) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th><th>Criado em</th></tr>";
        foreach ($ingredientes_outra_filial as $ing) {
            $cor = (strpos($ing['nome'], 'Teste AJAX') !== false) ? 'orange' : 'black';
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
    
    $db->query("DELETE FROM ingredientes WHERE id = ?", [$ingrediente_id]);
    echo "<p>✅ Ingrediente de teste removido</p>";
    
    echo "<h2>✅ Teste Concluído</h2>";
    echo "<div style='background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>";
    echo "<h3>🔍 Análise do Teste:</h3>";
    echo "<ul>";
    if ($ingrediente_view_encontrado) {
        echo "<li>✅ Ingrediente criado corretamente</li>";
        echo "<li>✅ Ingrediente aparece na view</li>";
        echo "<li>✅ Sistema funcionando</li>";
    } else {
        echo "<li>❌ Ingrediente criado mas não aparece na view</li>";
        echo "<li>❌ Problema na query da view</li>";
        echo "<li>❌ Sistema com problema</li>";
    }
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante o teste: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
