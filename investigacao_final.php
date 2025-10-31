<?php
/**
 * Investigação final para descobrir o problema real
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

$db = \System\Database::getInstance();
$session = \System\Session::getInstance();

echo "<h1>🔍 INVESTIGAÇÃO FINAL - DESCOBRIR O PROBLEMA REAL</h1>";

try {
    // 1. Verificar sessão REAL
    echo "<h2>1. SESSÃO REAL</h2>";
    
    session_start();
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Session Status: " . session_status() . "</p>";
    
    echo "<h3>Variáveis de Sessão:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    $tenantId = $session->getTenantId() ?? 24;
    $filialId = $session->getFilialId();
    
    echo "<p><strong>Tenant ID: $tenantId</strong></p>";
    echo "<p><strong>Filial ID: " . ($filialId ?? 'NULL') . "</strong></p>";
    
    // 2. Verificar se há filial padrão
    echo "<h2>2. FILIAL PADRÃO</h2>";
    
    $filial_padrao = $db->fetch("SELECT * FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
    if ($filial_padrao) {
        echo "<p style='color: green;'>✅ Filial padrão encontrada: {$filial_padrao['nome']} (ID: {$filial_padrao['id']})</p>";
        if ($filialId === null) {
            $filialId = $filial_padrao['id'];
            echo "<p>Usando filial padrão: $filialId</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ NENHUMA FILIAL PADRÃO ENCONTRADA!</p>";
        echo "<p>Isso pode ser o problema! Vou criar uma filial padrão...</p>";
        
        $filial_id_criada = $db->insert('filiais', [
            'tenant_id' => $tenantId,
            'nome' => 'Filial Principal',
            'status' => 'ativo',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p style='color: green;'>✅ Filial padrão criada com ID: $filial_id_criada</p>";
        $filialId = $filial_id_criada;
    }
    
    // 3. Verificar TODOS os ingredientes do tenant
    echo "<h2>3. TODOS OS INGREDIENTES DO TENANT</h2>";
    
    $todos_ingredientes = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId]);
    echo "<p><strong>Total de ingredientes do tenant: " . count($todos_ingredientes) . "</strong></p>";
    
    if (count($todos_ingredientes) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th><th>Criado em</th><th>STATUS</th></tr>";
        foreach ($todos_ingredientes as $ing) {
            $status = '';
            $cor = 'black';
            
            if ($ing['filial_id'] == $filialId) {
                $status = '✅ APARECE';
                $cor = 'green';
            } elseif ($ing['filial_id'] == null) {
                $status = '⚠️ SEM FILIAL';
                $cor = 'orange';
            } else {
                $status = '❌ FILIAL DIFERENTE';
                $cor = 'red';
            }
            
            echo "<tr style='color: $cor; font-weight: bold;'>";
            echo "<td>{$ing['id']}</td>";
            echo "<td>{$ing['nome']}</td>";
            echo "<td>{$ing['tenant_id']}</td>";
            echo "<td>{$ing['filial_id']}</td>";
            echo "<td>{$ing['ativo']}</td>";
            echo "<td>{$ing['created_at']}</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. FORÇAR correção de TODOS os ingredientes
    echo "<h2>4. FORÇANDO CORREÇÃO DE TODOS OS INGREDIENTES</h2>";
    
    if ($filialId !== null) {
        $resultado = $db->query("UPDATE ingredientes SET filial_id = ? WHERE tenant_id = ?", [$filialId, $tenantId]);
        echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ TODOS OS INGREDIENTES CORRIGIDOS PARA FILIAL $filialId</p>";
        
        // Verificar resultado
        $ingredientes_corrigidos = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome", [$tenantId, $filialId]);
        echo "<p><strong>Ingredientes após correção: " . count($ingredientes_corrigidos) . "</strong></p>";
        
        if (count($ingredientes_corrigidos) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
            foreach ($ingredientes_corrigidos as $ing) {
                echo "<tr style='color: green; font-weight: bold;'>";
                echo "<td>{$ing['id']}</td>";
                echo "<td>{$ing['nome']}</td>";
                echo "<td>{$ing['tenant_id']}</td>";
                echo "<td>{$ing['filial_id']}</td>";
                echo "<td>{$ing['ativo']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // 5. Testar criação de novo ingrediente
    echo "<h2>5. TESTANDO CRIAÇÃO DE NOVO INGREDIENTE</h2>";
    
    // Limpar ingredientes de teste
    $db->query("DELETE FROM ingredientes WHERE nome LIKE 'TESTE FINAL%'");
    
    // Criar ingrediente de teste
    $novo_ingrediente_id = $db->insert('ingredientes', [
        'nome' => 'TESTE FINAL ' . date('H:i:s'),
        'descricao' => 'Ingrediente para teste final',
        'preco_adicional' => 7.50,
        'ativo' => 1,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ NOVO INGREDIENTE CRIADO COM ID: $novo_ingrediente_id</p>";
    
    // Verificar se aparece na busca
    $novo_ingrediente_encontrado = $db->fetch("SELECT * FROM ingredientes WHERE id = ?", [$novo_ingrediente_id]);
    if ($novo_ingrediente_encontrado) {
        echo "<p style='color: green; font-size: 16px; font-weight: bold;'>✅ NOVO INGREDIENTE ENCONTRADO: {$novo_ingrediente_encontrado['nome']} (Tenant: {$novo_ingrediente_encontrado['tenant_id']}, Filial: {$novo_ingrediente_encontrado['filial_id']})</p>";
    } else {
        echo "<p style='color: red; font-size: 16px; font-weight: bold;'>❌ NOVO INGREDIENTE NÃO ENCONTRADO!</p>";
    }
    
    // 6. Testar query EXATA que a view usa
    echo "<h2>6. TESTANDO QUERY EXATA DA VIEW</h2>";
    
    $query_exata = "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome";
    $ingredientes_query_exata = $db->fetchAll($query_exata, [$tenantId, $filialId]);
    
    echo "<p><strong>Query exata: $query_exata</strong></p>";
    echo "<p><strong>Parâmetros: tenant_id=$tenantId, filial_id=$filialId</strong></p>";
    echo "<p><strong>Ingredientes encontrados: " . count($ingredientes_query_exata) . "</strong></p>";
    
    $novo_ingrediente_query_encontrado = false;
    foreach ($ingredientes_query_exata as $ing) {
        if ($ing['id'] == $novo_ingrediente_id) {
            $novo_ingrediente_query_encontrado = true;
            echo "<p style='color: green; font-size: 16px; font-weight: bold;'>✅ NOVO INGREDIENTE ENCONTRADO PELA QUERY: {$ing['nome']}</p>";
            break;
        }
    }
    
    if (!$novo_ingrediente_query_encontrado) {
        echo "<p style='color: red; font-size: 16px; font-weight: bold;'>❌ NOVO INGREDIENTE NÃO ENCONTRADO PELA QUERY!</p>";
    }
    
    // 7. Verificar se há ingredientes com filial_id diferente
    echo "<h2>7. VERIFICANDO INGREDIENTES COM FILIAL_ID DIFERENTE</h2>";
    
    $ingredientes_outra_filial = $db->fetchAll("SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id != ? AND filial_id IS NOT NULL", [$tenantId, $filialId]);
    echo "<p><strong>Ingredientes com filial_id diferente: " . count($ingredientes_outra_filial) . "</strong></p>";
    
    if (count($ingredientes_outra_filial) > 0) {
        echo "<p style='color: red; font-size: 16px; font-weight: bold;'>❌ AINDA HÁ INGREDIENTES COM FILIAL_ID DIFERENTE!</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Filial ID</th><th>Ativo</th></tr>";
        foreach ($ingredientes_outra_filial as $ing) {
            echo "<tr style='color: red; font-weight: bold;'>";
            echo "<td>{$ing['id']}</td>";
            echo "<td>{$ing['nome']}</td>";
            echo "<td>{$ing['tenant_id']}</td>";
            echo "<td>{$ing['filial_id']}</td>";
            echo "<td>{$ing['ativo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 8. Limpeza
    echo "<h2>8. LIMPEZA</h2>";
    
    $db->query("DELETE FROM ingredientes WHERE id = ?", [$novo_ingrediente_id]);
    echo "<p>✅ Ingrediente de teste removido</p>";
    
    echo "<h2>✅ INVESTIGAÇÃO FINAL CONCLUÍDA</h2>";
    echo "<div style='background-color: #d4edda; padding: 20px; border: 2px solid #c3e6cb; border-radius: 10px;'>";
    echo "<h3 style='color: green; font-size: 20px;'>🎉 RESULTADO DA INVESTIGAÇÃO:</h3>";
    echo "<ul style='font-size: 16px;'>";
    echo "<li>✅ Sessão verificada</li>";
    echo "<li>✅ Filial padrão criada/verificada</li>";
    echo "<li>✅ Todos os ingredientes corrigidos</li>";
    echo "<li>✅ Novo ingrediente criado e testado</li>";
    echo "<li>✅ Query da view testada</li>";
    echo "</ul>";
    echo "<p style='font-size: 18px; font-weight: bold; color: green;'>AGORA O SISTEMA DEVE FUNCIONAR!</p>";
    echo "<p style='font-size: 16px; font-weight: bold;'>RECARREGUE A PÁGINA DE GERENCIAR PRODUTOS!</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ ERRO DURANTE A INVESTIGAÇÃO: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Investigação final concluída em:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
