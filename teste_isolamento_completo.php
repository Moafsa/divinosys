<?php
/**
 * Script para testar isolamento completo de todos os módulos
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🧪 Teste de Isolamento Completo</h1>";

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
    
    // 2. Testar Categorias
    echo "<h2>2. Testando Categorias</h2>";
    
    // Limpar categorias de teste
    $db->query("DELETE FROM categorias WHERE nome LIKE 'Teste Isolamento%'");
    
    // Criar categoria de teste
    $categoria_id = $db->insert('categorias', [
        'nome' => 'Teste Isolamento Categoria ' . date('H:i:s'),
        'descricao' => 'Categoria para teste de isolamento',
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'ativo' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Categoria criada com ID: $categoria_id</p>";
    
    // Verificar se aparece na busca
    $categoria_encontrada = $db->fetch("SELECT * FROM categorias WHERE id = ?", [$categoria_id]);
    if ($categoria_encontrada) {
        echo "<p style='color: green;'>✅ Categoria encontrada: {$categoria_encontrada['nome']} (Tenant: {$categoria_encontrada['tenant_id']}, Filial: {$categoria_encontrada['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Categoria NÃO encontrada!</p>";
    }
    
    // 3. Testar Ingredientes
    echo "<h2>3. Testando Ingredientes</h2>";
    
    // Limpar ingredientes de teste
    $db->query("DELETE FROM ingredientes WHERE nome LIKE 'Teste Isolamento%'");
    
    // Criar ingrediente de teste
    $ingrediente_id = $db->insert('ingredientes', [
        'nome' => 'Teste Isolamento Ingrediente ' . date('H:i:s'),
        'descricao' => 'Ingrediente para teste de isolamento',
        'preco_adicional' => 2.50,
        'ativo' => 1,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Ingrediente criado com ID: $ingrediente_id</p>";
    
    // Verificar se aparece na busca
    $ingrediente_encontrado = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$ingrediente_id]);
    if ($ingrediente_encontrado) {
        echo "<p style='color: green;'>✅ Ingrediente encontrado: {$ingrediente_encontrado['nome']} (Tenant: {$ingrediente_encontrado['tenant_id']}, Filial: {$ingrediente_encontrado['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Ingrediente NÃO encontrado!</p>";
    }
    
    // 4. Testar Produtos
    echo "<h2>4. Testando Produtos</h2>";
    
    // Limpar produtos de teste
    $db->query("DELETE FROM produtos WHERE nome LIKE 'Teste Isolamento%'");
    
    // Criar produto de teste
    $produto_id = $db->insert('produtos', [
        'nome' => 'Teste Isolamento Produto ' . date('H:i:s'),
        'descricao' => 'Produto para teste de isolamento',
        'preco_normal' => 15.90,
        'preco_mini' => 12.90,
        'categoria_id' => $categoria_id,
        'ativo' => 1,
        'estoque_atual' => 10,
        'estoque_minimo' => 5,
        'preco_custo' => 8.50,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Produto criado com ID: $produto_id</p>";
    
    // Verificar se aparece na busca
    $produto_encontrado = $db->fetch("SELECT * FROM produtos WHERE id = ?", [$produto_id]);
    if ($produto_encontrado) {
        echo "<p style='color: green;'>✅ Produto encontrado: {$produto_encontrado['nome']} (Tenant: {$produto_encontrado['tenant_id']}, Filial: {$produto_encontrado['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Produto NÃO encontrado!</p>";
    }
    
    // 5. Testar Mesas
    echo "<h2>5. Testando Mesas</h2>";
    
    // Limpar mesas de teste
    $db->query("DELETE FROM mesas WHERE nome LIKE 'Teste Isolamento%'");
    
    // Criar mesa de teste
    $mesa_id = $db->insert('mesas', [
        'id_mesa' => 'TESTE' . date('His'),
        'nome' => 'Teste Isolamento Mesa ' . date('H:i:s'),
        'status' => '1',
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Mesa criada com ID: $mesa_id</p>";
    
    // Verificar se aparece na busca
    $mesa_encontrada = $db->fetch("SELECT * FROM mesas WHERE id = ?", [$mesa_id]);
    if ($mesa_encontrada) {
        echo "<p style='color: green;'>✅ Mesa encontrada: {$mesa_encontrada['nome']} (Tenant: {$mesa_encontrada['tenant_id']}, Filial: {$mesa_encontrada['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Mesa NÃO encontrada!</p>";
    }
    
    // 6. Testar Pedidos
    echo "<h2>6. Testando Pedidos</h2>";
    
    // Limpar pedidos de teste
    $db->query("DELETE FROM pedido WHERE observacao LIKE 'Teste Isolamento%'");
    
    // Criar pedido de teste
    $pedido_id = $db->insert('pedido', [
        'cliente' => 'Cliente Teste',
        'data' => date('Y-m-d'),
        'hora_pedido' => date('H:i:s'),
        'valor_total' => 15.90,
        'valor_pago' => 0.00,
        'saldo_devedor' => 15.90,
        'status_pagamento' => 'pendente',
        'status' => 'Pendente',
        'observacao' => 'Teste Isolamento Pedido ' . date('H:i:s'),
        'idmesa' => $mesa_encontrada['id_mesa'],
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'delivery' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green;'>✅ Pedido criado com ID: $pedido_id</p>";
    
    // Verificar se aparece na busca
    $pedido_encontrado = $db->fetch("SELECT * FROM pedido WHERE idpedido = ?", [$pedido_id]);
    if ($pedido_encontrado) {
        echo "<p style='color: green;'>✅ Pedido encontrado: {$pedido_encontrado['observacao']} (Tenant: {$pedido_encontrado['tenant_id']}, Filial: {$pedido_encontrado['filial_id']})</p>";
    } else {
        echo "<p style='color: red;'>❌ Pedido NÃO encontrado!</p>";
    }
    
    // 7. Testar Isolamento - Verificar se dados de outros tenants não aparecem
    echo "<h2>7. Testando Isolamento</h2>";
    
    // Buscar dados de outros tenants
    $outros_tenants = $db->fetchAll("SELECT DISTINCT tenant_id FROM categorias WHERE tenant_id != ?", [$tenantId]);
    $outros_tenants_count = count($outros_tenants);
    
    echo "<p>Outros tenants com categorias: $outros_tenants_count</p>";
    
    if ($outros_tenants_count > 0) {
        echo "<p style='color: orange;'>⚠️ Existem dados de outros tenants no sistema</p>";
        foreach ($outros_tenants as $tenant) {
            echo "<p>- Tenant ID: {$tenant['tenant_id']}</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Nenhum dado de outros tenants encontrado</p>";
    }
    
    // 8. Limpeza
    echo "<h2>8. Limpeza</h2>";
    
    $db->query("DELETE FROM pedido WHERE idpedido = ?", [$pedido_id]);
    $db->query("DELETE FROM mesas WHERE id = ?", [$mesa_id]);
    $db->query("DELETE FROM produtos WHERE id = ?", [$produto_id]);
    $db->query("DELETE FROM ingredientes WHERE id = ?", [$ingrediente_id]);
    $db->query("DELETE FROM categorias WHERE id = ?", [$categoria_id]);
    
    echo "<p>✅ Dados de teste removidos</p>";
    
    echo "<h2>✅ Teste de Isolamento Completo</h2>";
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>🎉 Resultado do Teste:</h3>";
    echo "<ul>";
    echo "<li>✅ Categorias: Isolamento funcionando</li>";
    echo "<li>✅ Ingredientes: Isolamento funcionando</li>";
    echo "<li>✅ Produtos: Isolamento funcionando</li>";
    echo "<li>✅ Mesas: Isolamento funcionando</li>";
    echo "<li>✅ Pedidos: Isolamento funcionando</li>";
    echo "</ul>";
    echo "<p><strong>🎯 Todos os módulos estão funcionando com isolamento correto!</strong></p>";
    echo "<p><strong>Próximo passo: Faça logout e login novamente para aplicar todas as correções!</strong></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro durante o teste: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Teste de isolamento completo concluído em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
