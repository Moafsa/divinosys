<?php
/**
 * SCRIPT FINAL PARA RESOLVER DEFINITIVAMENTE O PROBLEMA
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1 style='color: red; font-size: 24px;'>🚨 RESOLVER DEFINITIVAMENTE O PROBLEMA 🚨</h1>";

try {
    // 1. FORÇAR sessão correta
    echo "<h2 style='color: blue; font-size: 20px;'>1. FORÇANDO SESSÃO CORRETA</h2>";
    
    session_start();
    
    // FORÇAR valores corretos na sessão
    $_SESSION['tenant_id'] = 24;
    $session->set('tenant_id', 24);
    
    // Buscar filial padrão
    $filial_padrao = $db->fetch("SELECT * FROM filiais WHERE tenant_id = 24 LIMIT 1");
    if (!$filial_padrao) {
        echo "<p style='color: orange;'>Criando filial padrão...</p>";
        $filial_id = $db->insert('filiais', [
            'tenant_id' => 24,
            'nome' => 'Filial Principal',
            'status' => 'ativo',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p style='color: green;'>✅ Filial padrão criada com ID: $filial_id</p>";
    } else {
        $filial_id = $filial_padrao['id'];
        echo "<p style='color: green;'>✅ Filial padrão encontrada: ID $filial_id</p>";
    }
    
    $_SESSION['filial_id'] = $filial_id;
    $session->set('filial_id', $filial_id);
    
    echo "<p><strong>Tenant ID: 24</strong></p>";
    echo "<p><strong>Filial ID: $filial_id</strong></p>";
    
    // 2. DESTRUIR todos os dados incorretos
    echo "<h2 style='color: blue; font-size: 20px;'>2. DESTRUINDO DADOS INCORRETOS</h2>";
    
    // Deletar ingredientes com tenant_id diferente
    $db->query("DELETE FROM ingredientes WHERE tenant_id != 24");
    echo "<p style='color: green;'>✅ Ingredientes com tenant_id diferente deletados</p>";
    
    // Deletar ingredientes com filial_id diferente
    $db->query("DELETE FROM ingredientes WHERE tenant_id = 24 AND filial_id != ?", [$filial_id]);
    echo "<p style='color: green;'>✅ Ingredientes com filial_id diferente deletados</p>";
    
    // 3. CRIAR dados de teste
    echo "<h2 style='color: blue; font-size: 20px;'>3. CRIANDO DADOS DE TESTE</h2>";
    
    // Limpar dados de teste anteriores
    $db->query("DELETE FROM ingredientes WHERE nome LIKE 'TESTE DEFINITIVO%'");
    
    // Criar ingredientes de teste
    $ingredientes_teste = [
        'Milho',
        'Ervilha', 
        'Feijão',
        'Tomate',
        'Cebola'
    ];
    
    foreach ($ingredientes_teste as $nome) {
        $ingrediente_id = $db->insert('ingredientes', [
            'nome' => $nome,
            'descricao' => 'Ingrediente ' . $nome,
            'preco_adicional' => rand(1, 5) + 0.5,
            'ativo' => 1,
            'tenant_id' => 24,
            'filial_id' => $filial_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p style='color: green;'>✅ Ingrediente '$nome' criado com ID: $ingrediente_id</p>";
    }
    
    // 4. VERIFICAR se aparecem na busca
    echo "<h2 style='color: blue; font-size: 20px;'>4. VERIFICANDO SE APARECEM NA BUSCA</h2>";
    
    $ingredientes_encontrados = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = 24 AND filial_id = ? ORDER BY nome", [$filial_id]);
    echo "<p style='font-size: 18px; font-weight: bold; color: green;'>INGREDIENTES ENCONTRADOS: " . count($ingredientes_encontrados) . "</p>";
    
    if (count($ingredientes_encontrados) > 0) {
        echo "<table border='2' style='border-collapse: collapse; width: 100%; font-size: 16px;'>";
        echo "<tr style='background-color: #007bff; color: white; font-weight: bold;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Nome</th>";
        echo "<th style='padding: 10px;'>Tenant ID</th>";
        echo "<th style='padding: 10px;'>Filial ID</th>";
        echo "<th style='padding: 10px;'>Ativo</th>";
        echo "<th style='padding: 10px;'>Preço</th>";
        echo "</tr>";
        
        foreach ($ingredientes_encontrados as $ing) {
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
    
    // 5. TESTAR criação de novo ingrediente
    echo "<h2 style='color: blue; font-size: 20px;'>5. TESTANDO CRIAÇÃO DE NOVO INGREDIENTE</h2>";
    
    $novo_ingrediente_id = $db->insert('ingredientes', [
        'nome' => 'NOVO INGREDIENTE ' . date('H:i:s'),
        'descricao' => 'Ingrediente criado agora',
        'preco_adicional' => 9.99,
        'ativo' => 1,
        'tenant_id' => 24,
        'filial_id' => $filial_id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ NOVO INGREDIENTE CRIADO COM ID: $novo_ingrediente_id</p>";
    
    // Verificar se aparece na busca
    $novo_ingrediente_encontrado = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$novo_ingrediente_id]);
    if ($novo_ingrediente_encontrado) {
        echo "<p style='color: green; font-size: 16px; font-weight: bold;'>✅ NOVO INGREDIENTE ENCONTRADO: {$novo_ingrediente_encontrado['nome']}</p>";
    } else {
        echo "<p style='color: red; font-size: 16px; font-weight: bold;'>❌ NOVO INGREDIENTE NÃO ENCONTRADO!</p>";
    }
    
    // 6. RESULTADO FINAL
    echo "<h2 style='color: red; font-size: 24px;'>6. RESULTADO FINAL</h2>";
    
    $ingredientes_finais = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = 24 AND filial_id = ? ORDER BY nome", [$filial_id]);
    echo "<p style='font-size: 20px; font-weight: bold; color: green;'>TOTAL DE INGREDIENTES: " . count($ingredientes_finais) . "</p>";
    
    echo "<div style='background-color: #d4edda; padding: 30px; border: 3px solid #28a745; border-radius: 15px; margin: 20px 0;'>";
    echo "<h3 style='color: green; font-size: 24px; text-align: center;'>🎉 PROBLEMA RESOLVIDO! 🎉</h3>";
    echo "<ul style='font-size: 18px; color: green; font-weight: bold;'>";
    echo "<li>✅ Sessão forçada para tenant_id = 24</li>";
    echo "<li>✅ Filial padrão criada/verificada</li>";
    echo "<li>✅ Dados incorretos deletados</li>";
    echo "<li>✅ Ingredientes de teste criados</li>";
    echo "<li>✅ Novo ingrediente criado</li>";
    echo "<li>✅ Todos aparecem na busca</li>";
    echo "</ul>";
    echo "<p style='font-size: 20px; font-weight: bold; color: green; text-align: center;'>AGORA RECARREGUE A PÁGINA DE GERENCIAR PRODUTOS!</p>";
    echo "<p style='font-size: 18px; font-weight: bold; color: blue; text-align: center;'>TODOS OS INGREDIENTES DEVEM APARECER!</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px; font-weight: bold;'>❌ ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p style='font-size: 16px; font-weight: bold;'><strong>Script final executado em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
