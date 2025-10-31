-- =====================================================
-- MIGRATION: ASAAS ESTABLISHMENT CONFIGURATION
-- Description: Add Asaas configuration per establishment/filial
-- Date: 2025-01-15
-- =====================================================

-- 1. Add Asaas configuration to tenants table
ALTER TABLE tenants 
ADD COLUMN IF NOT EXISTS asaas_api_key VARCHAR(255),
ADD COLUMN IF NOT EXISTS asaas_api_url VARCHAR(255) DEFAULT 'https://sandbox.asaas.com/api/v3',
ADD COLUMN IF NOT EXISTS asaas_customer_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS asaas_webhook_token VARCHAR(255),
ADD COLUMN IF NOT EXISTS asaas_environment VARCHAR(20) DEFAULT 'sandbox' CHECK (asaas_environment IN ('sandbox', 'production')),
ADD COLUMN IF NOT EXISTS asaas_enabled BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS asaas_fiscal_info JSONB,
ADD COLUMN IF NOT EXISTS asaas_municipal_service_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS asaas_municipal_service_code VARCHAR(100);

-- 2. Add Asaas configuration to filiais table
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS asaas_api_key VARCHAR(255),
ADD COLUMN IF NOT EXISTS asaas_customer_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS asaas_enabled BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS asaas_fiscal_info JSONB,
ADD COLUMN IF NOT EXISTS asaas_municipal_service_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS asaas_municipal_service_code VARCHAR(100);

-- 3. Create table for invoice management
CREATE TABLE IF NOT EXISTS notas_fiscais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    asaas_invoice_id VARCHAR(100) NOT NULL,
    asaas_payment_id VARCHAR(100),
    numero_nota VARCHAR(50),
    serie_nota VARCHAR(10),
    chave_acesso VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'issued', 'cancelled', 'error')),
    valor_total DECIMAL(10,2) NOT NULL,
    valor_impostos DECIMAL(10,2) DEFAULT 0.00,
    data_emissao TIMESTAMP,
    data_cancelamento TIMESTAMP,
    xml_content TEXT,
    pdf_url VARCHAR(500),
    observacoes TEXT,
    asaas_response JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(asaas_invoice_id)
);

-- 4. Create table for fiscal information management
CREATE TABLE IF NOT EXISTS informacoes_fiscais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    cnpj VARCHAR(18) NOT NULL,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    inscricao_estadual VARCHAR(50),
    inscricao_municipal VARCHAR(50),
    endereco JSONB NOT NULL, -- {logradouro, numero, complemento, bairro, cidade, uf, cep}
    contato JSONB, -- {telefone, email, site}
    regime_tributario VARCHAR(50),
    optante_simples_nacional BOOLEAN DEFAULT false,
    municipal_service_id VARCHAR(100),
    municipal_service_code VARCHAR(100),
    municipal_service_name VARCHAR(255),
    nbs_codes JSONB, -- Array of NBS codes
    active BOOLEAN DEFAULT true,
    asaas_sync_status VARCHAR(20) DEFAULT 'pending' CHECK (asaas_sync_status IN ('pending', 'synced', 'error')),
    asaas_response JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, cnpj)
);

-- 5. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_tenants_asaas_enabled ON tenants(asaas_enabled);
CREATE INDEX IF NOT EXISTS idx_filiais_asaas_enabled ON filiais(asaas_enabled);
CREATE INDEX IF NOT EXISTS idx_notas_fiscais_tenant_id ON notas_fiscais(tenant_id);
CREATE INDEX IF NOT EXISTS idx_notas_fiscais_filial_id ON notas_fiscais(filial_id);
CREATE INDEX IF NOT EXISTS idx_notas_fiscais_status ON notas_fiscais(status);
CREATE INDEX IF NOT EXISTS idx_notas_fiscais_asaas_invoice_id ON notas_fiscais(asaas_invoice_id);
CREATE INDEX IF NOT EXISTS idx_informacoes_fiscais_tenant_id ON informacoes_fiscais(tenant_id);
CREATE INDEX IF NOT EXISTS idx_informacoes_fiscais_filial_id ON informacoes_fiscais(filial_id);
CREATE INDEX IF NOT EXISTS idx_informacoes_fiscais_cnpj ON informacoes_fiscais(cnpj);

-- 6. Add comments for documentation
COMMENT ON TABLE notas_fiscais IS 'Invoice management for Asaas integration';
COMMENT ON TABLE informacoes_fiscais IS 'Fiscal information for each establishment/filial';
COMMENT ON COLUMN tenants.asaas_api_key IS 'Asaas API key for this establishment';
COMMENT ON COLUMN tenants.asaas_enabled IS 'Whether Asaas integration is enabled for this establishment';
COMMENT ON COLUMN filiais.asaas_api_key IS 'Asaas API key for this filial (inherits from tenant if null)';
COMMENT ON COLUMN filiais.asaas_enabled IS 'Whether Asaas integration is enabled for this filial';
