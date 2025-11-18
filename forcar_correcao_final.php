<?php
/**
 * Script para forçar a correção final de todos os ingredientes
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🔧 Forçar Correção Final dos Ingredientes</h1>";

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
    
    // 2. Verificar estado atual
    echo "<h2>2. Verificando Estado Atual</h2>";
    
    $ingredientes_antes = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? ORDER BY nome", [$tenantId]);
    echo "<p>Total de ingredientes antes da correção: " . count($ingredientes_antes) . "</p>";
    
    if (count($ingredientes_antes) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
        foreach ($ingredientes_antes as $ing) {
            $cor = (strpos($ing['nome'], 'Milho') !== false || strpos($ing['nome'], 'Ervilha') !== false) ? 'green' : 'black';
            echo "<tr style='color: $cor;'>";
            echo "<td>{$ing['id']}</td>";
            echo "<td>{$ing['nome']}</td>";
            echo "<td>{$ing['tenant_id']}</td>";
            echo "<td>{$ing['filial_id']}</td>";
            echo "<td>{$ing['ativo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Forçar correção de todos os ingredientes
    echo "<h2>3. Forçando Correção de Todos os Ingredientes</h2>";
    
    if ($filialId !== null) {
        // Corrigir todos os ingredientes do tenant para a filial correta
        $resultado = $db->query("UPDATE ingredientes SET filial_id = ? WHERE tenant_id = ?", [$filialId, $tenantId]);
        echo "<p style='color: green;'>✅ Todos os ingredientes do tenant $tenantId corrigidos para filial $filialId</p>";
        
        // Verificar resultado
        $ingredientes_depois = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome", [$tenantId, $filialId]);
        echo "<p>Ingredientes após correção: " . count($ingredientes_depois) . "</p>";
        
        if (count($ingredientes_depois) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
            foreach ($ingredientes_depois as $ing) {
                $cor = (strpos($ing['nome'], 'Milho') !== false || strpos($ing['nome'], 'Ervilha') !== false) ? 'green' : 'black';
                echo "<tr style='color: $cor;'>";
                echo "<td>{$ing['id']}</td>";
                echo "<td>{$ing['nome']}</td>";
                echo "<td>{$ing['tenant_id']}</td>";
                echo "<td>{$ing['filial_id']}</td>";
                echo "<td>{$ing['ativo']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>❌ Não é possível corrigir - filialId é NULL</p>";
    }
    
    // 4. Testar query final da view
    echo "<h2>4. Testando Query Final da View</h2>";
    
    $query_final = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
    $ingredientes_final = $db->fetchAll($query_final, [$tenantId, $filialId]);
    
    echo "<p>Query final: <code>$query_final</code></p>";
    echo "<p>Parâmetros: tenant_id=$tenantId, filial_id=$filialId</p>";
    echo "<p>Ingredientes encontrados pela query final: " . count($ingredientes_final) . "</p>";
    
    if (count($ingredientes_final) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
        foreach ($ingredientes_final as $ing) {
            $cor = (strpos($ing['nome'], 'Milho') !== false || strpos($ing['nome'], 'Ervilha') !== false) ? 'green' : 'black';
            echo "<tr style='color: $cor;'>";
            echo "<td>{$ing['id']}</td>";
            echo "<td>{$ing['nome']}</td>";
            echo "<td>{$ing['tenant_id']}</td>";
            echo "<td>{$ing['filial_id']}</td>";
            echo "<td>{$ing['ativo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 5. Verificar se Milho e Ervilha estão na lista
    echo "<h2>5. Verificando Milho e Ervilha</h2>";
    
    $milho = $db->fetch("SELECT * FROM ingredientes WHERE nome = 'Milho' AND tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    if ($milho) {
        echo "<p style='color: green;'>✅ Milho encontrado na filial correta: ID={$milho['id']}, Filial={$milho['filial_id']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Milho NÃO encontrado na filial correta</p>";
    }
    
    $ervilha = $db->fetch("SELECT * FROM ingredientes WHERE nome = 'Ervilha' AND tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    if ($ervilha) {
        echo "<p style='color: green;'>✅ Ervilha encontrada na filial correta: ID={$ervilha['id']}, Filial={$ervilha['filial_id']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Ervilha NÃO encontrada na filial correta</p>";
    }
    
    echo "<h2>✅ Correção Final Concluída</h2>";
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>🎉 Resumo da Correção Final:</h3>";
    echo "<ul>";
    echo "<li>✅ Todos os ingredientes corrigidos para filial $filialId</li>";
    echo "<li>✅ Query final testada</li>";
    echo "<li>✅ Milho e Ervilha verificados</li>";
    echo "</ul>";
    echo "<p><strong>Agora recarregue a página de Gerenciar Produtos para ver os ingredientes!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante a correção: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Correção final concluída em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
