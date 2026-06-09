-- Migration: create_taxa_entrega_bairros
-- Description: Criação da tabela para taxas de entrega por bairro

CREATE TABLE IF NOT EXISTS taxa_entrega_bairros (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    bairro VARCHAR(100) NOT NULL,
    taxa DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, bairro)
);

CREATE INDEX IF NOT EXISTS idx_taxa_entrega_bairros_tenant ON taxa_entrega_bairros(tenant_id);
CREATE INDEX IF NOT EXISTS idx_taxa_entrega_bairros_filial ON taxa_entrega_bairros(filial_id);

COMMENT ON TABLE taxa_entrega_bairros IS 'Tabela para gerenciar taxas de entrega específicas por bairro';
