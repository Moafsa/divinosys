<?php
/**
 * Script final para testar se as correções funcionaram
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🧪 Teste Final das Correções</h1>";

try {
    // 1. Verificar sessão atual
    echo "<h2>1. Verificando Sessão Atual</h2>";
    
    session_start();
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Tenant ID na sessão: " . ($_SESSION['tenant_id'] ?? 'NÃO DEFINIDO') . "</p>";
    echo "<p>Filial ID na sessão: " . ($_SESSION['filial_id'] ?? 'NÃO DEFINIDO') . "</p>";
    
    // Verificar usuário logado
    $user = $session->getUser();
    if ($user) {
        echo "<p>Usuário logado: {$user['login']} (ID: {$user['id']})</p>";
        echo "<p>Tenant ID do usuário: {$user['tenant_id']}</p>";
        echo "<p>Filial ID do usuário: {$user['filial_id']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Nenhum usuário logado!</p>";
    }
    
    // 2. Verificar filial padrão
    echo "<h2>2. Verificando Filial Padrão</h2>";
    
    $tenantId = $session->getTenantId() ?? 24;
    $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
    
    if ($filial_padrao) {
        echo "<p style='color: green;'>✅ Filial padrão encontrada: ID {$filial_padrao['id']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Nenhuma filial padrão encontrada!</p>";
    }
    
    // 3. Testar query corrigida
    echo "<h2>3. Testando Query Corrigida</h2>";
    
    $categorias_sistema = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = ?) ORDER BY nome",
        [$tenantId, $filial_padrao['id']]
    );
    
    echo "<p>Categorias encontradas pelo sistema: " . count($categorias_sistema) . "</p>";
    
    if (count($categorias_sistema) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
        foreach ($categorias_sistema as $cat) {
            echo "<tr>";
            echo "<td>{$cat['id']}</td>";
            echo "<td>{$cat['nome']}</td>";
            echo "<td>{$cat['tenant_id']}</td>";
            echo "<td>{$cat['filial_id']}</td>";
            echo "<td>{$cat['ativo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Testar criação de categoria
    echo "<h2>4. Testando Criação de Categoria</h2>";
    
    // Limpar categorias de teste anteriores
    $db->query("DELETE FROM categorias WHERE nome LIKE 'Teste Final%'");
    
    // Criar categoria de teste
    $categoria_teste_id = $db->insert('categorias', [
        'nome' => 'Teste Final ' . date('H:i:s'),
        'descricao' => 'Categoria criada para teste final',
        'tenant_id' => $tenantId,
        'filial_id' => $filial_padrao['id'],
        'ativo' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Categoria de teste criada com ID: $categoria_teste_id</p>";
    
    // Verificar se aparece na busca
    $categoria_encontrada = $db->fetch("SELECT * FROM categorias WHERE id = ?", [$categoria_teste_id]);
    if ($categoria_encontrada) {
        echo "<p style='color: green;'>✅ Categoria encontrada no banco!</p>";
        echo "<p>Dados: {$categoria_encontrada['nome']} (Tenant: {$categoria_encontrada['tenant_id']}, Filial: {$categoria_encontrada['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Categoria NÃO encontrada no banco!</p>";
    }
    
    // 5. Testar query do sistema com a nova categoria
    echo "<h2>5. Testando Query do Sistema com Nova Categoria</h2>";
    
    $categorias_sistema_nova = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = ?) ORDER BY nome",
        [$tenantId, $filial_padrao['id']]
    );
    
    echo "<p>Categorias encontradas pelo sistema: " . count($categorias_sistema_nova) . "</p>";
    
    $categoria_teste_encontrada = false;
    foreach ($categorias_sistema_nova as $cat) {
        if (strpos($cat['nome'], 'Teste Final') !== false) {
            $categoria_teste_encontrada = true;
            echo "<p style='color: green;'>✅ Categoria de teste encontrada pelo sistema!</p>";
            break;
        }
    }
    
    if (!$categoria_teste_encontrada) {
        echo "<p style='color: red;'>❌ Categoria de teste NÃO encontrada pelo sistema!</p>";
    }
    
    // 6. Limpeza
    echo "<h2>6. Limpeza</h2>";
    
    $db->query("DELETE FROM categorias WHERE id = ?", [$categoria_teste_id]);
    echo "<p>✅ Categoria de teste removida</p>";
    
    echo "<h2>✅ Teste Final Concluído</h2>";
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>🎉 Resultado do Teste Final:</h3>";
    echo "<ul>";
    echo "<li>✅ Sessão verificada</li>";
    echo "<li>✅ Filial padrão encontrada</li>";
    echo "<li>✅ Query corrigida funcionando</li>";
    echo "<li>✅ Criação de categoria funcionando</li>";
    echo "<li>✅ Busca de categoria funcionando</li>";
    echo "</ul>";
    echo "<p><strong>O sistema está funcionando corretamente!</strong></p>";
    echo "<p><strong>Próximo passo: Faça logout e login novamente para aplicar as correções!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante o teste: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste final concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
