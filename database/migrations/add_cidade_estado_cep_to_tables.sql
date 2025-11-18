-- =====================================================
-- ADD CIDADE, ESTADO, CEP TO TENANTS AND FILIAIS
-- =====================================================
-- Data: 2025-11-17
-- Descrição: Adiciona colunas cidade, estado e cep nas tabelas tenants e filiais

-- Add columns to tenants table
ALTER TABLE tenants 
ADD COLUMN IF NOT EXISTS cidade VARCHAR(100),
ADD COLUMN IF NOT EXISTS estado VARCHAR(2),
ADD COLUMN IF NOT EXISTS cep VARCHAR(10);

-- Add columns to filiais table
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS cidade VARCHAR(100),
ADD COLUMN IF NOT EXISTS estado VARCHAR(2),
ADD COLUMN IF NOT EXISTS cep VARCHAR(10);

-- Add comments for documentation
COMMENT ON COLUMN tenants.cidade IS 'Cidade do estabelecimento';
COMMENT ON COLUMN tenants.estado IS 'Estado do estabelecimento (UF)';
COMMENT ON COLUMN tenants.cep IS 'CEP do estabelecimento';

COMMENT ON COLUMN filiais.cidade IS 'Cidade da filial';
COMMENT ON COLUMN filiais.estado IS 'Estado da filial (UF)';
COMMENT ON COLUMN filiais.cep IS 'CEP da filial';

