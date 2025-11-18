-- =====================================================
-- ADD ASAAS_SUBSCRIPTION_ID TO ASSINATURAS
-- =====================================================
-- Data: 2025-11-17
-- Descrição: Adiciona coluna asaas_subscription_id na tabela assinaturas para integração com Asaas

-- Add column to assinaturas table
ALTER TABLE assinaturas 
ADD COLUMN IF NOT EXISTS asaas_subscription_id VARCHAR(255);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_assinaturas_asaas_subscription_id ON assinaturas(asaas_subscription_id);

-- Add comment for documentation
COMMENT ON COLUMN assinaturas.asaas_subscription_id IS 'ID da assinatura no gateway de pagamento Asaas';

