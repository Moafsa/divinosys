<?php
/**
 * Script para executar migration que adiciona cidade, estado e cep nas tabelas tenants e filiais
 */

require_once 'vendor/autoload.php';

use System\Database;

try {
    echo "Executando migration: add_cidade_estado_cep_to_tables...\n\n";
    
    $db = Database::getInstance();
    
    // Add columns to tenants table
    echo "Adicionando colunas na tabela tenants...\n";
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)");
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS estado VARCHAR(2)");
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS cep VARCHAR(10)");
    echo "✅ Colunas adicionadas na tabela tenants\n\n";
    
    // Add columns to filiais table
    echo "Adicionando colunas na tabela filiais...\n";
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)");
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS estado VARCHAR(2)");
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(10)");
    echo "✅ Colunas adicionadas na tabela filiais\n\n";
    
    // Add comments
    echo "Adicionando comentários...\n";
    try {
        $db->query("COMMENT ON COLUMN tenants.cidade IS 'Cidade do estabelecimento'");
        $db->query("COMMENT ON COLUMN tenants.estado IS 'Estado do estabelecimento (UF)'");
        $db->query("COMMENT ON COLUMN tenants.cep IS 'CEP do estabelecimento'");
        $db->query("COMMENT ON COLUMN filiais.cidade IS 'Cidade da filial'");
        $db->query("COMMENT ON COLUMN filiais.estado IS 'Estado da filial (UF)'");
        $db->query("COMMENT ON COLUMN filiais.cep IS 'CEP da filial'");
        echo "✅ Comentários adicionados\n\n";
    } catch (Exception $e) {
        echo "⚠️  Aviso ao adicionar comentários (não crítico): " . $e->getMessage() . "\n\n";
    }
    
    echo "✅ Migration executada com sucesso!\n";
    echo "\nColunas adicionadas:\n";
    echo "- tenants: cidade, estado, cep\n";
    echo "- filiais: cidade, estado, cep\n";
    
} catch (Exception $e) {
    echo "❌ Erro na migration: " . $e->getMessage() . "\n";
    exit(1);
}

