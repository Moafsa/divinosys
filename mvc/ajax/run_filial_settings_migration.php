<?php
/**
 * Endpoint para executar migration create_filial_settings
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Create table
    $results[] = "Criando tabela filial_settings...";
    $db->query("
        CREATE TABLE IF NOT EXISTS filial_settings (
            id SERIAL PRIMARY KEY,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(tenant_id, filial_id, setting_key)
        )
    ");
    $results[] = "✅ Tabela filial_settings criada";
    
    // Create indexes
    try {
        $db->query("CREATE INDEX IF NOT EXISTS idx_filial_settings_tenant_filial ON filial_settings(tenant_id, filial_id)");
        $db->query("CREATE INDEX IF NOT EXISTS idx_filial_settings_key ON filial_settings(setting_key)");
        $results[] = "✅ Índices criados";
    } catch (Exception $e) {
        $results[] = "⚠️ Aviso ao criar índices: " . $e->getMessage();
    }
    
    // Migrate existing cor_primaria
    try {
        $db->query("
            INSERT INTO filial_settings (tenant_id, filial_id, setting_key, setting_value)
            SELECT t.id, f.id, 'cor_primaria', t.cor_primaria
            FROM tenants t
            JOIN filiais f ON f.tenant_id = t.id AND f.id = (SELECT MIN(id) FROM filiais WHERE tenant_id = t.id)
            WHERE t.cor_primaria IS NOT NULL
            ON CONFLICT (tenant_id, filial_id, setting_key) DO NOTHING
        ");
        $results[] = "✅ Dados migrados";
    } catch (Exception $e) {
        $results[] = "⚠️ Aviso ao migrar dados: " . $e->getMessage();
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

