-- =====================================================
-- WHATSAPP MESSAGES LOG TABLE
-- =====================================================
-- Stores WhatsApp conversation history for AI context
-- Date: 2025-11-04
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id SERIAL PRIMARY KEY,
    instance_id VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    message_in TEXT,
    message_out TEXT,
    ai_context JSONB,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    processed BOOLEAN DEFAULT false,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_phone ON whatsapp_messages(phone);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_tenant_filial ON whatsapp_messages(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_created_at ON whatsapp_messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_instance ON whatsapp_messages(instance_id);

-- Comments
COMMENT ON TABLE whatsapp_messages IS 'WhatsApp conversation history for AI context and analytics';
COMMENT ON COLUMN whatsapp_messages.message_in IS 'Message received from customer';
COMMENT ON COLUMN whatsapp_messages.message_out IS 'Response sent by AI';
COMMENT ON COLUMN whatsapp_messages.ai_context IS 'AI processing context and metadata';
COMMENT ON COLUMN whatsapp_messages.processed IS 'Whether message was successfully processed';

