<?php
/**
 * Script de migração para executar online
 * Este script aplica as migrações necessárias no banco de dados online
 */

// Configurar para mostrar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir arquivos necessários
require_once 'system/Database.php';
require_once 'system/Config.php';

try {
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();
    
    echo "<h1>Migração Online - Divino Lanches</h1>";
    echo "<p>Iniciando migração...</p>";
    
    // 1. Verificar se as tabelas financeiras existem
    echo "<h2>1. Verificando tabelas financeiras...</h2>";
    
    $tabelas_financeiras = [
        'categorias_financeiras',
        'contas_financeiras', 
        'lancamentos_financeiros',
        'anexos_financeiros',
        'relatorios_financeiros'
    ];
    
    foreach ($tabelas_financeiras as $tabela) {
        $existe = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)", [$tabela]);
        if ($existe['exists']) {
            echo "✅ Tabela {$tabela} existe.<br>";
        } else {
            echo "❌ Tabela {$tabela} NÃO existe. Criando...<br>";
            // Executar migração se a tabela não existir
            $migration_sql = file_get_contents('database/migrations/create_financial_system.sql');
            if ($migration_sql) {
                $db->execute($migration_sql);
                echo "✅ Tabela {$tabela} criada.<br>";
            } else {
                echo "❌ Arquivo de migração não encontrado.<br>";
            }
        }
    }
    
    // 2. Aplicar migração de perfil de cliente
    echo "<h2>2. Aplicando migração de perfil de cliente...</h2>";
    
    $migration_file = 'database/migrations/create_cliente_profile_tables.sql';
    if (file_exists($migration_file)) {
        $migration_sql = file_get_contents($migration_file);
        
        // Dividir em comandos individuais
        $commands = array_filter(array_map('trim', explode(';', $migration_sql)));
        
        foreach ($commands as $command) {
            if (!empty($command) && !preg_match('/^--/', $command)) {
                try {
                    $db->execute($command);
                    echo "✅ Comando executado: " . substr($command, 0, 50) . "...<br>";
                } catch (Exception $e) {
                    echo "⚠️ Aviso: " . $e->getMessage() . "<br>";
                }
            }
        }
    } else {
        echo "❌ Arquivo de migração não encontrado.<br>";
    }
    
    // 3. Verificar dados iniciais
    echo "<h2>3. Verificando dados iniciais...</h2>";
    
    try {
        $categorias_count = $db->fetch("SELECT COUNT(*) as total FROM categorias_financeiras WHERE tenant_id = 1 AND filial_id = 1")['total'];
        $contas_count = $db->fetch("SELECT COUNT(*) as total FROM contas_financeiras WHERE tenant_id = 1 AND filial_id = 1")['total'];
        
        echo "📊 Categorias financeiras: {$categorias_count}<br>";
        echo "📊 Contas financeiras: {$contas_count}<br>";
        
        if ($categorias_count == 0) {
            echo "⚠️ Nenhuma categoria encontrada. Criando categorias padrão...<br>";
            // Executar script de criação de categorias
            $categorias_sql = file_get_contents('create_default_categories.php');
            if ($categorias_sql) {
                include 'create_default_categories.php';
                echo "✅ Categorias padrão criadas.<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "⚠️ Erro ao verificar dados: " . $e->getMessage() . "<br>";
    }
    
    // 4. Verificar estrutura do banco
    echo "<h2>4. Verificando estrutura do banco...</h2>";
    
    $tabelas_principais = [
        'usuarios_globais',
        'tenants',
        'filiais',
        'pedido',
        'produtos',
        'categorias'
    ];
    
    foreach ($tabelas_principais as $tabela) {
        try {
            $count = $db->fetch("SELECT COUNT(*) as total FROM {$tabela}")['total'];
            echo "📊 Tabela {$tabela}: {$count} registros<br>";
        } catch (Exception $e) {
            echo "❌ Erro na tabela {$tabela}: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>✅ Migração concluída!</h2>";
    echo "<p><a href='index.php'>Voltar ao sistema</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro na migração:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
