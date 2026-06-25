-- Personaliza????o da IA por inst??ncia WhatsApp
ALTER TABLE whatsapp_instances
    ADD COLUMN IF NOT EXISTS ai_config JSONB DEFAULT NULL;

COMMENT ON COLUMN whatsapp_instances.ai_config IS 'Configura????o do prompt da IA: assistant_name, business_name, tone, custom_instructions';
-- Personaliza????o da IA por inst??ncia WhatsApp
ALTER TABLE whatsapp_instances
    ADD COLUMN IF NOT EXISTS ai_config JSONB DEFAULT NULL;

COMMENT ON COLUMN whatsapp_instances.ai_config IS 'Configura????o do prompt da IA: assistant_name, business_name, tone, custom_instructions';
