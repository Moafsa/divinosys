-- Tabela para instâncias WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    instance_name VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    status VARCHAR(50) DEFAULT 'disconnected', -- connected, disconnected, qrcode, error
    qr_code TEXT,
    session_data JSONB,
    webhook_url VARCHAR(500),
    n8n_webhook_url VARCHAR(500), -- Para assistente IA
    wuzapi_instance_id VARCHAR(255), -- ID da instância na WuzAPI
    wuzapi_token TEXT, -- Token de autenticação da WuzAPI
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para mensagens WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id SERIAL PRIMARY KEY,
    instance_id INTEGER NOT NULL REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    message_id VARCHAR(255),
    from_number VARCHAR(20),
    to_number VARCHAR(20),
    message_text TEXT,
    message_type VARCHAR(50) DEFAULT 'text', -- text, image, document, audio, video
    status VARCHAR(50) DEFAULT 'sent', -- sent, delivered, read, failed
    source VARCHAR(50) DEFAULT 'system', -- system, n8n, webhook
    direction VARCHAR(10) DEFAULT 'outbound', -- inbound, outbound
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para webhooks recebidos
CREATE TABLE IF NOT EXISTS whatsapp_webhooks (
    id SERIAL PRIMARY KEY,
    instance_id INTEGER REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    webhook_type VARCHAR(50) NOT NULL, -- message, status, qrcode, connection
    webhook_data JSONB NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_tenant_filial ON whatsapp_instances(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_status ON whatsapp_instances(status);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_instance_id ON whatsapp_messages(instance_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_tenant_filial ON whatsapp_messages(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_status ON whatsapp_messages(status);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_source ON whatsapp_messages(source);
CREATE INDEX IF NOT EXISTS idx_whatsapp_webhooks_instance_id ON whatsapp_webhooks(instance_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_webhooks_processed ON whatsapp_webhooks(processed);
