-- Limpeza das colunas do Chatwoot que não são mais utilizadas
-- Remover colunas relacionadas ao Chatwoot da tabela whatsapp_instances

-- Remover colunas do Chatwoot se existirem
DO $$ 
BEGIN
    -- Verificar e remover coluna chatwoot_account_id
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'whatsapp_instances' 
               AND column_name = 'chatwoot_account_id') THEN
        ALTER TABLE whatsapp_instances DROP COLUMN chatwoot_account_id;
    END IF;
    
    -- Verificar e remover coluna chatwoot_user_id
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'whatsapp_instances' 
               AND column_name = 'chatwoot_user_id') THEN
        ALTER TABLE whatsapp_instances DROP COLUMN chatwoot_user_id;
    END IF;
    
    -- Verificar e remover coluna chatwoot_inbox_id
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'whatsapp_instances' 
               AND column_name = 'chatwoot_inbox_id') THEN
        ALTER TABLE whatsapp_instances DROP COLUMN chatwoot_inbox_id;
    END IF;
    
    -- Verificar e remover coluna chatwoot_created_at
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'whatsapp_instances' 
               AND column_name = 'chatwoot_created_at') THEN
        ALTER TABLE whatsapp_instances DROP COLUMN chatwoot_created_at;
    END IF;
END $$;

-- Remover índices relacionados ao Chatwoot se existirem
DROP INDEX IF EXISTS idx_whatsapp_instances_chatwoot_account;
DROP INDEX IF EXISTS idx_whatsapp_instances_chatwoot_inbox;
DROP INDEX IF EXISTS idx_whatsapp_instances_chatwoot_user;

-- Comentário para documentação
COMMENT ON TABLE whatsapp_instances IS 'Tabela para gerenciar instâncias do WhatsApp via WuzAPI';
COMMENT ON COLUMN whatsapp_instances.wuzapi_instance_id IS 'ID da instância na WuzAPI';
COMMENT ON COLUMN whatsapp_instances.wuzapi_token IS 'Token de autenticação da instância na WuzAPI';
