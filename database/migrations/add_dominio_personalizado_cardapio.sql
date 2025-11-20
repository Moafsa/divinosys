-- =====================================================
-- MIGRATION: ADD DOMAIN CUSTOMIZATION FOR ONLINE MENU
-- Description: Add custom domain field for online menu
-- Date: 2025-01-15
-- =====================================================

-- Add custom domain field to filiais table
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS dominio_cardapio_online VARCHAR(255);

-- Add comment
COMMENT ON COLUMN filiais.dominio_cardapio_online IS 'Custom domain for online menu (e.g., cardapio.estabelecimento.com.br)';

