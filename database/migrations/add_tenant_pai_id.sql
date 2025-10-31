-- Adicionar coluna tenant_pai_id para vincular filiais ao estabelecimento principal
ALTER TABLE tenants ADD COLUMN tenant_pai_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE;

-- Criar índice para melhor performance
CREATE INDEX idx_tenants_tenant_pai_id ON tenants(tenant_pai_id);

-- Comentário explicativo
COMMENT ON COLUMN tenants.tenant_pai_id IS 'ID do tenant pai (estabelecimento principal) para filiais';













