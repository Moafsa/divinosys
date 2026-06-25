-- Tabela mesa_pedidos (init 04 falhou parcialmente no deploy original)
CREATE TABLE IF NOT EXISTS mesa_pedidos (
    id SERIAL PRIMARY KEY,
    mesa_id VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'aberta' CHECK (status IN ('aberta', 'fechada', 'paga')),
    total_geral DECIMAL(10,2) DEFAULT 0.00,
    numero_pessoas INTEGER DEFAULT 1,
    observacao TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_mesa_pedidos_mesa_id ON mesa_pedidos(mesa_id);
CREATE INDEX IF NOT EXISTS idx_mesa_pedidos_status ON mesa_pedidos(status);
CREATE INDEX IF NOT EXISTS idx_mesa_pedidos_tenant_filial ON mesa_pedidos(tenant_id, filial_id);
