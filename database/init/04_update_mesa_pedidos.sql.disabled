-- Update pedido table to support multiple orders per table
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS mesa_pedido_id VARCHAR(50); -- Unique identifier for each order at a table
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS numero_pessoas INTEGER DEFAULT 1; -- Number of people for payment division
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS valor_por_pessoa DECIMAL(10,2); -- Value per person
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS observacao_pagamento TEXT; -- Payment observation
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS forma_pagamento_detalhada JSONB; -- Detailed payment info

-- Create mesa_pedidos table to group orders by table
CREATE TABLE IF NOT EXISTS mesa_pedidos (
    id SERIAL PRIMARY KEY,
    mesa_id VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'aberta' CHECK (status IN ('aberta', 'fechada', 'paga')),
    total_geral DECIMAL(10,2) DEFAULT 0.00,
    numero_pessoas INTEGER DEFAULT 1,
    observacao TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create pagamentos table for payment tracking
CREATE TABLE IF NOT EXISTS pagamentos (
    id SERIAL PRIMARY KEY,
    mesa_pedido_id INTEGER REFERENCES mesa_pedidos(id) ON DELETE CASCADE,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE CASCADE,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    numero_pessoas INTEGER DEFAULT 1,
    valor_por_pessoa DECIMAL(10,2),
    observacao TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_pedido_mesa_pedido_id ON pedido(mesa_pedido_id);
CREATE INDEX IF NOT EXISTS idx_mesa_pedidos_mesa_id ON mesa_pedidos(mesa_id);
CREATE INDEX IF NOT EXISTS idx_mesa_pedidos_status ON mesa_pedidos(status);
CREATE INDEX IF NOT EXISTS idx_pagamentos_mesa_pedido ON pagamentos(mesa_pedido_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido ON pagamentos(pedido_id);

-- Update existing pedidos to have mesa_pedido_id
UPDATE pedido SET mesa_pedido_id = 'mesa_' || idmesa || '_' || idpedido WHERE mesa_pedido_id IS NULL;

-- Create mesa_pedidos entries for existing orders
INSERT INTO mesa_pedidos (mesa_id, status, total_geral, tenant_id, filial_id, created_at)
SELECT DISTINCT 
    p.idmesa,
    CASE 
        WHEN p.status = 'Finalizado' THEN 'fechada'
        ELSE 'aberta'
    END,
    COALESCE(SUM(pi.valor_total), 0),
    p.tenant_id,
    p.filial_id,
    MIN(p.created_at)
FROM pedido p
LEFT JOIN pedido_itens pi ON p.idpedido = pi.pedido_id
WHERE p.idmesa IS NOT NULL AND p.idmesa != '999'
GROUP BY p.idmesa, p.tenant_id, p.filial_id, p.status
ON CONFLICT DO NOTHING;
