<?php
/**
 * Endpoint para executar migration add_asaas_subscription_id_to_assinaturas
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Add column to assinaturas table
    $results[] = "Adicionando coluna asaas_subscription_id na tabela assinaturas...";
    $db->query("ALTER TABLE assinaturas ADD COLUMN IF NOT EXISTS asaas_subscription_id VARCHAR(255)");
    $results[] = "✅ Coluna 'asaas_subscription_id' adicionada em assinaturas";
    
    // Add index
    try {
        $db->query("CREATE INDEX IF NOT EXISTS idx_assinaturas_asaas_subscription_id ON assinaturas(asaas_subscription_id)");
        $results[] = "✅ Índice criado";
    } catch (Exception $e) {
        $results[] = "⚠️ Aviso ao criar índice (pode já existir): " . $e->getMessage();
    }
    
    // Add comment (optional)
    try {
        $db->query("COMMENT ON COLUMN assinaturas.asaas_subscription_id IS 'ID da assinatura no gateway de pagamento Asaas'");
        $results[] = "✅ Comentário adicionado";
    } catch (Exception $e) {
        $results[] = "⚠️ Aviso ao adicionar comentário (não crítico): " . $e->getMessage();
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

