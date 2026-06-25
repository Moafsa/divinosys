-- Sessões de pedido em andamento via WhatsApp (abandono, expiração, follow-up)
CREATE TABLE IF NOT EXISTS whatsapp_order_sessions (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    instance_id INTEGER REFERENCES whatsapp_instances(id) ON DELETE SET NULL,
    phone VARCHAR(20) NOT NULL,
    customer_name VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    followup_sent_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wos_tenant_phone ON whatsapp_order_sessions(tenant_id, phone);
CREATE INDEX IF NOT EXISTS idx_wos_status_activity ON whatsapp_order_sessions(status, last_activity_at);
CREATE UNIQUE INDEX IF NOT EXISTS idx_wos_open_unique ON whatsapp_order_sessions(tenant_id, filial_id, phone) WHERE status = 'open';
