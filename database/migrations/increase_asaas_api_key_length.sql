-- =====================================================
-- MIGRATION: INCREASE ASAAS API KEY LENGTH
-- Description: Increase asaas_api_key field length to support longer production keys
-- Date: 2025-11-26
-- =====================================================

-- Increase asaas_api_key length in tenants table
ALTER TABLE tenants 
ALTER COLUMN asaas_api_key TYPE VARCHAR(500);

-- Increase asaas_api_key length in filiais table
ALTER TABLE filiais 
ALTER COLUMN asaas_api_key TYPE VARCHAR(500);

-- Add comments
COMMENT ON COLUMN tenants.asaas_api_key IS 'Asaas API key for this establishment (supports up to 500 characters)';
COMMENT ON COLUMN filiais.asaas_api_key IS 'Asaas API key for this filial (supports up to 500 characters)';

