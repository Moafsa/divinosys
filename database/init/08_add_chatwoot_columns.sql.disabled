-- Add Chatwoot columns to whatsapp_instances table
-- Migration: 08_add_chatwoot_columns.sql

-- Add Chatwoot reference columns to whatsapp_instances table
ALTER TABLE whatsapp_instances 
ADD COLUMN IF NOT EXISTS chatwoot_account_id INTEGER,
ADD COLUMN IF NOT EXISTS chatwoot_user_id INTEGER,
ADD COLUMN IF NOT EXISTS chatwoot_inbox_id INTEGER,
ADD COLUMN IF NOT EXISTS chatwoot_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_chatwoot_account ON whatsapp_instances(chatwoot_account_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_chatwoot_user ON whatsapp_instances(chatwoot_user_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_chatwoot_inbox ON whatsapp_instances(chatwoot_inbox_id);

-- Add comments for documentation
COMMENT ON COLUMN whatsapp_instances.chatwoot_account_id IS 'ID da conta criada no Chatwoot';
COMMENT ON COLUMN whatsapp_instances.chatwoot_user_id IS 'ID do usuário criado no Chatwoot';
COMMENT ON COLUMN whatsapp_instances.chatwoot_inbox_id IS 'ID do inbox do WhatsApp criado no Chatwoot';
COMMENT ON COLUMN whatsapp_instances.chatwoot_created_at IS 'Data de criação da integração com Chatwoot';
