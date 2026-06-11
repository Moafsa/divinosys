<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

try {
    $db = \System\Database::getInstance();
    $pdo = $db->getConnection();

    // 1. Add columns to clientes_fiado
    $pdo->exec("ALTER TABLE clientes_fiado ADD COLUMN IF NOT EXISTS cobranca_automatica BOOLEAN DEFAULT false");
    $pdo->exec("ALTER TABLE clientes_fiado ADD COLUMN IF NOT EXISTS cobranca_frequencia VARCHAR(20) DEFAULT 'mensal'");

    // 2. Create whatsapp_admins table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_admins (
            id SERIAL PRIMARY KEY,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            telefone VARCHAR(20) NOT NULL,
            nome VARCHAR(100),
            ativo BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // 3. Create ai_automations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_automations (
            id SERIAL PRIMARY KEY,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            tipo VARCHAR(50) NOT NULL, -- 'abandono', 'saudade', etc
            ativo BOOLEAN DEFAULT false,
            tempo_espera INTEGER NOT NULL, -- em minutos (abandono) ou dias (saudade)
            mensagem_template TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo json_encode(['success' => true, 'message' => 'Migration completed successfully']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
