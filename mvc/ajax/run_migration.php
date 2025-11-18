<?php
/**
 * Endpoint para executar migration add_cidade_estado_cep_to_tables
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Add columns to tenants table
    $results[] = "Adicionando colunas na tabela tenants...";
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)");
    $results[] = "✅ Coluna 'cidade' adicionada em tenants";
    
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS estado VARCHAR(2)");
    $results[] = "✅ Coluna 'estado' adicionada em tenants";
    
    $db->query("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS cep VARCHAR(10)");
    $results[] = "✅ Coluna 'cep' adicionada em tenants";
    
    // Add columns to filiais table
    $results[] = "Adicionando colunas na tabela filiais...";
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)");
    $results[] = "✅ Coluna 'cidade' adicionada em filiais";
    
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS estado VARCHAR(2)");
    $results[] = "✅ Coluna 'estado' adicionada em filiais";
    
    $db->query("ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(10)");
    $results[] = "✅ Coluna 'cep' adicionada em filiais";
    
    // Add comments (optional, may fail if already exists)
    try {
        $db->query("COMMENT ON COLUMN tenants.cidade IS 'Cidade do estabelecimento'");
        $db->query("COMMENT ON COLUMN tenants.estado IS 'Estado do estabelecimento (UF)'");
        $db->query("COMMENT ON COLUMN tenants.cep IS 'CEP do estabelecimento'");
        $db->query("COMMENT ON COLUMN filiais.cidade IS 'Cidade da filial'");
        $db->query("COMMENT ON COLUMN filiais.estado IS 'Estado da filial (UF)'");
        $db->query("COMMENT ON COLUMN filiais.cep IS 'CEP da filial'");
        $results[] = "✅ Comentários adicionados";
    } catch (Exception $e) {
        $results[] = "⚠️ Aviso ao adicionar comentários (não crítico): " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration executada com sucesso!',
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

