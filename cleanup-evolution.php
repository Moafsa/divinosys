<?php
/**
 * Script de Limpeza Completa da Evolution API
 * Remove todas as tabelas e dados relacionados à Evolution API
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

try {
    echo "=== LIMPEZA COMPLETA DA EVOLUTION API ===\n";
    
    $config = Config::getInstance();
    $db = Database::getInstance();
    
    echo "Conectado ao banco de dados...\n";
    
    // Lista de tabelas Evolution para remover
    $evolutionTables = [
        'evolution_instancias',
        'usuarios_globais',
        'usuarios_telefones', 
        'usuarios_enderecos',
        'usuarios_estabelecimento',
        'usuarios_consentimentos_lgpd',
        'usuarios_logs_acesso'
    ];
    
    echo "Removendo tabelas Evolution...\n";
    
    foreach ($evolutionTables as $table) {
        try {
            // Verificar se tabela existe
            $exists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)", [$table]);
            
            if ($exists && $exists['exists']) {
                echo "Removendo tabela: $table\n";
                $db->query("DROP TABLE IF EXISTS $table CASCADE");
                echo "✅ Tabela $table removida\n";
            } else {
                echo "ℹ️  Tabela $table não existe\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao remover tabela $table: " . $e->getMessage() . "\n";
        }
    }
    
    // Remover banco evolution_db se existir
    echo "\nRemovendo banco evolution_db...\n";
    try {
        $db->query("DROP DATABASE IF EXISTS evolution_db");
        echo "✅ Banco evolution_db removido\n";
    } catch (Exception $e) {
        echo "ℹ️  Banco evolution_db não existe ou já foi removido\n";
    }
    
    // Limpar variáveis de ambiente relacionadas
    echo "\n=== LIMPEZA CONCLUÍDA ===\n";
    echo "✅ Todas as tabelas Evolution foram removidas\n";
    echo "✅ Banco evolution_db foi removido\n";
    echo "✅ Sistema limpo para implementação Baileys\n";
    
    echo "\nPróximos passos:\n";
    echo "1. Faça deploy da stack limpa\n";
    echo "2. Implemente Baileys diretamente no sistema\n";
    echo "3. Configure WhatsApp sem dependências externas\n";
    
} catch (Exception $e) {
    echo "❌ Erro durante limpeza: " . $e->getMessage() . "\n";
    exit(1);
}
