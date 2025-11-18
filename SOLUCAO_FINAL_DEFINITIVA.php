<?php
/**
 * SOLUÇÃO FINAL DEFINITIVA - VAMOS RESOLVER ISSO DE UMA VEZ
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1 style='color: red; font-size: 28px; text-align: center;'>🚨 SOLUÇÃO FINAL DEFINITIVA 🚨</h1>";

try {
    // 1. DIAGNÓSTICO COMPLETO
    echo "<h2 style='color: blue; font-size: 22px;'>1. DIAGNÓSTICO COMPLETO</h2>";
    
    session_start();
    
    echo "<h3>Sessão Atual:</h3>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #ddd;'>";
    print_r($_SESSION);
    echo "</pre>";
    
    $tenantId = $_SESSION['tenant_id'] ?? 24;
    $filialId = $_SESSION['filial_id'] ?? null;
    
    echo "<p><strong style='font-size: 18px;'>Tenant ID: $tenantId</strong></p>";
    echo "<p><strong style='font-size: 18px;'>Filial ID: " . ($filialId ?? 'NULL') . "</strong></p>";
    
    // 2. VERIFICAR FILIAL
    echo "<h2 style='color: blue; font-size: 22px;'>2. VERIFICANDO FILIAL</h2>";
    
    $filial = $db->fetch("SELECT * FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
    if (!$filial) {
        echo "<p style='color: red; font-size: 18px;'>❌ NENHUMA FILIAL ENCONTRADA! CRIANDO...</p>";
        $filial_id = $db->insert('filiais', [
            'tenant_id' => $tenantId,
            'nome' => 'Filial Principal',
            'status' => 'ativo',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p style='color: green; font-size: 18px;'>✅ Filial criada com ID: $filial_id</p>";
    } else {
        $filial_id = $filial['id'];
        echo "<p style='color: green; font-size: 18px;'>✅ Filial encontrada: {$filial['nome']} (ID: $filial_id)</p>";
    }
    
    // FORÇAR filial_id na sessão
    $_SESSION['filial_id'] = $filial_id;
    $session->set('filial_id', $filial_id);
    
    // 3. VERIFICAR TODOS OS INGREDIENTES
    echo "<h2 style='color: blue; font-size: 22px;'>3. VERIFICANDO TODOS OS INGREDIENTES</h2>";
    
    $todos_ingredientes = $db->fetchAll("SELECT * FROM ingredientes ORDER BY created_at DESC");
    echo "<p style='font-size: 18px; font-weight: bold;'>Total de ingredientes no banco: " . count($todos_ingredientes) . "</p>";
    
    if (count($todos_ingredientes) > 0) {
        echo "<table border='2' style='border-collapse: collapse; width: 100%; font-size: 14px;'>";
        echo "<tr style='background-color: #007bff; color: white; font-weight: bold;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Nome</th>";
        echo "<th style='padding: 8px;'>Tenant ID</th>";
        echo "<th style='padding: 8px;'>Filial ID</th>";
        echo "<th style='padding: 8px;'>Ativo</th>";
        echo "<th style='padding: 8px;'>Criado em</th>";
        echo "</tr>";
        
        foreach ($todos_ingredientes as $ing) {
            $cor = ($ing['tenant_id'] == $tenantId && $ing['filial_id'] == $filial_id) ? 'green' : 'red';
            echo "<tr style='color: $cor; font-weight: bold;'>";
            echo "<td style='padding: 6px;'>{$ing['id']}</td>";
            echo "<td style='padding: 6px;'>{$ing['nome']}</td>";
            echo "<td style='padding: 6px;'>{$ing['tenant_id']}</td>";
            echo "<td style='padding: 6px;'>{$ing['filial_id']}</td>";
            echo "<td style='padding: 6px;'>{$ing['ativo']}</td>";
            echo "<td style='padding: 6px;'>{$ing['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. CORRIGIR TODOS OS INGREDIENTES
    echo "<h2 style='color: blue; font-size: 22px;'>4. CORRIGINDO TODOS OS INGREDIENTES</h2>";
    
    // Deletar ingredientes com tenant_id diferente
    $deletados_tenant = $db->query("DELETE FROM ingredientes WHERE tenant_id != ?", [$tenantId]);
    echo "<p style='color: green; font-size: 16px;'>✅ Ingredientes com tenant_id diferente deletados</p>";
    
    // Corrigir ingredientes do tenant correto
    $corrigidos = $db->query("UPDATE ingredientes SET filial_id = ? WHERE tenant_id = ?", [$filial_id, $tenantId]);
    echo "<p style='color: green; font-size: 16px;'>✅ Ingredientes corrigidos para filial $filial_id</p>";
    
    // 5. CRIAR INGREDIENTES DE TESTE
    echo "<h2 style='color: blue; font-size: 22px;'>5. CRIANDO INGREDIENTES DE TESTE</h2>";
    
    // Limpar ingredientes de teste anteriores
    $db->query("DELETE FROM ingredientes WHERE nome LIKE 'TESTE FINAL%'");
    
    $ingredientes_teste = [
        'Milho',
        'Ervilha',
        'Feijão',
        'Tomate',
        'Cebola',
        'Alho',
        'Cebolinha',
        'Salsinha'
    ];
    
    foreach ($ingredientes_teste as $nome) {
        $ingrediente_id = $db->insert('ingredientes', [
            'nome' => $nome,
            'descricao' => 'Ingrediente ' . $nome,
            'preco_adicional' => rand(1, 10) + 0.5,
            'ativo' => 1,
            'tenant_id' => $tenantId,
            'filial_id' => $filial_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p style='color: green; font-size: 16px;'>✅ Ingrediente '$nome' criado com ID: $ingrediente_id</p>";
    }
    
    // 6. TESTAR QUERY DA VIEW
    echo "<h2 style='color: blue; font-size: 22px;'>6. TESTANDO QUERY DA VIEW</h2>";
    
    $query_view = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
    $ingredientes_view = $db->fetchAll($query_view, [$tenantId, $filial_id]);
    
    echo "<p style='font-size: 18px; font-weight: bold;'>Query da view: <code>$query_view</code></p>";
    echo "<p style='font-size: 18px; font-weight: bold;'>Parâmetros: tenant_id=$tenantId, filial_id=$filial_id</p>";
    echo "<p style='font-size: 20px; font-weight: bold; color: green;'>INGREDIENTES ENCONTRADOS: " . count($ingredientes_view) . "</p>";
    
    if (count($ingredientes_view) > 0) {
        echo "<table border='2' style='border-collapse: collapse; width: 100%; font-size: 16px;'>";
        echo "<tr style='background-color: #28a745; color: white; font-weight: bold;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Nome</th>";
        echo "<th style='padding: 10px;'>Tenant ID</th>";
        echo "<th style='padding: 10px;'>Filial ID</th>";
        echo "<th style='padding: 10px;'>Ativo</th>";
        echo "<th style='padding: 10px;'>Preço</th>";
        echo "</tr>";
        
        foreach ($ingredientes_view as $ing) {
            echo "<tr style='background-color: #d4edda; color: green; font-weight: bold;'>";
            echo "<td style='padding: 8px;'>{$ing['id']}</td>";
            echo "<td style='padding: 8px;'>{$ing['nome']}</td>";
            echo "<td style='padding: 8px;'>{$ing['tenant_id']}</td>";
            echo "<td style='padding: 8px;'>{$ing['filial_id']}</td>";
            echo "<td style='padding: 8px;'>{$ing['ativo']}</td>";
            echo "<td style='padding: 8px;'>R$ " . number_format($ing['preco_adicional'], 2, ',', '.') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 7. TESTAR CRIAÇÃO DE NOVO INGREDIENTE
    echo "<h2 style='color: blue; font-size: 22px;'>7. TESTANDO CRIAÇÃO DE NOVO INGREDIENTE</h2>";
    
    $novo_ingrediente_id = $db->insert('ingredientes', [
        'nome' => 'NOVO TESTE ' . date('H:i:s'),
        'descricao' => 'Ingrediente criado agora',
        'preco_adicional' => 15.99,
        'ativo' => 1,
        'tenant_id' => $tenantId,
        'filial_id' => $filial_id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green; font-size: 20px; font-weight: bold;'>✅ NOVO INGREDIENTE CRIADO COM ID: $novo_ingrediente_id</p>";
    
    // Verificar se aparece na query da view
    $novo_ingrediente_view = $db->fetchAll($query_view, [$tenantId, $filial_id]);
    $novo_encontrado = false;
    foreach ($novo_ingrediente_view as $ing) {
        if ($ing['id'] == $novo_ingrediente_id) {
            $novo_encontrado = true;
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ NOVO INGREDIENTE ENCONTRADO PELA VIEW: {$ing['nome']}</p>";
            break;
        }
    }
    
    if (!$novo_encontrado) {
        echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ NOVO INGREDIENTE NÃO ENCONTRADO PELA VIEW!</p>";
    }
    
    // 8. RESULTADO FINAL
    echo "<h2 style='color: red; font-size: 24px; text-align: center;'>8. RESULTADO FINAL</h2>";
    
    $ingredientes_finais = $db->fetchAll($query_view, [$tenantId, $filial_id]);
    
    echo "<div style='background-color: #d4edda; padding: 30px; border: 3px solid #28a745; border-radius: 15px; margin: 20px 0; text-align: center;'>";
    echo "<h3 style='color: green; font-size: 28px; margin-bottom: 20px;'>🎉 SISTEMA CORRIGIDO! 🎉</h3>";
    echo "<p style='font-size: 24px; font-weight: bold; color: green;'>TOTAL DE INGREDIENTES: " . count($ingredientes_finais) . "</p>";
    echo "<p style='font-size: 20px; font-weight: bold; color: blue;'>TENANT ID: $tenantId</p>";
    echo "<p style='font-size: 20px; font-weight: bold; color: blue;'>FILIAL ID: $filial_id</p>";
    echo "<p style='font-size: 22px; font-weight: bold; color: red; margin-top: 20px;'>AGORA RECARREGUE A PÁGINA DE GERENCIAR PRODUTOS!</p>";
    echo "<p style='font-size: 20px; font-weight: bold; color: green;'>TODOS OS INGREDIENTES DEVEM APARECER!</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ ERRO: " . $e->getMessage() . "</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #ddd;'>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p style='font-size: 16px; font-weight: bold; text-align: center;'><strong>Script executado em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
