<?php
/**
 * Script para corrigir duplicatas nas categorias e contas financeiras
 * e verificar erros nos relatórios
 */

require_once 'system/Database.php';
require_once 'system/Config.php';

$config = \System\Config::getInstance();
$db = \System\Database::getInstance();

echo "=== CORREÇÃO DE DUPLICATAS FINANCEIRAS ===\n\n";

try {
    // 1. Verificar duplicatas nas categorias
    echo "1. Verificando categorias duplicadas...\n";
    $categorias_duplicadas = $db->fetchAll("
        SELECT nome, tipo, tenant_id, filial_id, COUNT(*) as total
        FROM categorias_financeiras 
        GROUP BY nome, tipo, tenant_id, filial_id 
        HAVING COUNT(*) > 1
    ");
    
    if (!empty($categorias_duplicadas)) {
        echo "   Encontradas " . count($categorias_duplicadas) . " categorias duplicadas:\n";
        foreach ($categorias_duplicadas as $cat) {
            echo "   - {$cat['nome']} ({$cat['tipo']}) - {$cat['total']} duplicatas\n";
        }
        
        // Remover duplicatas, mantendo apenas a primeira
        echo "   Removendo duplicatas...\n";
        $db->execute("
            DELETE FROM categorias_financeiras 
            WHERE id NOT IN (
                SELECT MIN(id) 
                FROM categorias_financeiras 
                GROUP BY nome, tipo, tenant_id, filial_id
            )
        ");
        echo "   ✅ Categorias duplicadas removidas.\n";
    } else {
        echo "   ✅ Nenhuma categoria duplicada encontrada.\n";
    }
    
    // 2. Verificar duplicatas nas contas
    echo "\n2. Verificando contas duplicadas...\n";
    $contas_duplicadas = $db->fetchAll("
        SELECT nome, tipo, tenant_id, filial_id, COUNT(*) as total
        FROM contas_financeiras 
        GROUP BY nome, tipo, tenant_id, filial_id 
        HAVING COUNT(*) > 1
    ");
    
    if (!empty($contas_duplicadas)) {
        echo "   Encontradas " . count($contas_duplicadas) . " contas duplicadas:\n";
        foreach ($contas_duplicadas as $conta) {
            echo "   - {$conta['nome']} ({$conta['tipo']}) - {$conta['total']} duplicatas\n";
        }
        
        // Remover duplicatas, mantendo apenas a primeira
        echo "   Removendo duplicatas...\n";
        $db->execute("
            DELETE FROM contas_financeiras 
            WHERE id NOT IN (
                SELECT MIN(id) 
                FROM contas_financeiras 
                GROUP BY nome, tipo, tenant_id, filial_id
            )
        ");
        echo "   ✅ Contas duplicadas removidas.\n";
    } else {
        echo "   ✅ Nenhuma conta duplicada encontrada.\n";
    }
    
    // 3. Verificar se as tabelas financeiras existem
    echo "\n3. Verificando estrutura das tabelas financeiras...\n";
    
    $tabelas_necessarias = [
        'categorias_financeiras',
        'contas_financeiras', 
        'lancamentos_financeiros',
        'anexos_financeiros',
        'relatorios_financeiros'
    ];
    
    foreach ($tabelas_necessarias as $tabela) {
        $existe = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)", [$tabela]);
        if ($existe['exists']) {
            echo "   ✅ Tabela {$tabela} existe.\n";
        } else {
            echo "   ❌ Tabela {$tabela} NÃO existe. Criando...\n";
            // Executar migração se a tabela não existir
            $migration_sql = file_get_contents('database/migrations/create_financial_system.sql');
            $db->execute($migration_sql);
            echo "   ✅ Tabela {$tabela} criada.\n";
        }
    }
    
    // 4. Verificar dados iniciais
    echo "\n4. Verificando dados iniciais...\n";
    
    $categorias_count = $db->fetch("SELECT COUNT(*) as total FROM categorias_financeiras WHERE tenant_id = 1 AND filial_id = 1")['total'];
    $contas_count = $db->fetch("SELECT COUNT(*) as total FROM contas_financeiras WHERE tenant_id = 1 AND filial_id = 1")['total'];
    
    echo "   Categorias: {$categorias_count}\n";
    echo "   Contas: {$contas_count}\n";
    
    // 5. Testar API de relatórios
    echo "\n5. Testando API de relatórios...\n";
    
    // Simular requisição para gerar relatório
    $_POST['action'] = 'gerar_relatorio';
    $_POST['tipo'] = 'fluxo_caixa';
    $_POST['data_inicio'] = '2025-10-01';
    $_POST['data_fim'] = '2025-10-31';
    
    // Capturar output da API
    ob_start();
    include 'mvc/ajax/financeiro.php';
    $api_output = ob_get_clean();
    
    $api_response = json_decode($api_output, true);
    
    if ($api_response && isset($api_response['success'])) {
        if ($api_response['success']) {
            echo "   ✅ API de relatórios funcionando corretamente.\n";
        } else {
            echo "   ❌ Erro na API: " . ($api_response['message'] ?? 'Erro desconhecido') . "\n";
        }
    } else {
        echo "   ❌ Resposta inválida da API: " . substr($api_output, 0, 200) . "...\n";
    }
    
    // 6. Verificar dependências JavaScript
    echo "\n6. Verificando dependências JavaScript...\n";
    
    $js_files = [
        'assets/js/financeiro.js',
        'assets/js/sidebar.js'
    ];
    
    foreach ($js_files as $js_file) {
        if (file_exists($js_file)) {
            echo "   ✅ {$js_file} existe.\n";
        } else {
            echo "   ❌ {$js_file} NÃO existe.\n";
        }
    }
    
    // 7. Verificar bibliotecas externas
    echo "\n7. Verificando bibliotecas externas...\n";
    
    $cdn_libs = [
        'jQuery' => 'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
        'Bootstrap' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        'Chart.js' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
        'Select2' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        'SweetAlert2' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11'
    ];
    
    foreach ($cdn_libs as $lib => $url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'HEAD'
            ]
        ]);
        
        $headers = @get_headers($url, 1, $context);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "   ✅ {$lib} disponível.\n";
        } else {
            echo "   ❌ {$lib} não disponível.\n";
        }
    }
    
    echo "\n=== CORREÇÃO CONCLUÍDA ===\n";
    echo "✅ Duplicatas removidas\n";
    echo "✅ Estrutura verificada\n";
    echo "✅ API testada\n";
    echo "✅ Dependências verificadas\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
