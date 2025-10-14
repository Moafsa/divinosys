-- =====================================================
-- MIGRATION: PARTIAL PAYMENT SUPPORT
-- Description: Add support for partial payments on orders
-- Date: 2025-10-11
-- =====================================================

-- 1. Add payment control fields to pedido table
ALTER TABLE pedido 
ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS saldo_devedor DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS status_pagamento VARCHAR(20) DEFAULT 'pendente' CHECK (status_pagamento IN ('pendente', 'parcial', 'quitado'));

-- 2. Update existing pedidos to set payment status based on current status
UPDATE pedido 
SET 
    valor_pago = CASE WHEN status = 'Finalizado' THEN valor_total ELSE 0.00 END,
    saldo_devedor = CASE WHEN status = 'Finalizado' THEN 0.00 ELSE valor_total END,
    status_pagamento = CASE WHEN status = 'Finalizado' THEN 'quitado' ELSE 'pendente' END
WHERE status_pagamento IS NULL;

-- 3. Create or replace pagamentos_pedido table for payment tracking
CREATE TABLE IF NOT EXISTS pagamentos_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    nome_cliente VARCHAR(100),
    telefone_cliente VARCHAR(20),
    descricao TEXT,
    troco_para DECIMAL(10,2),
    troco_devolver DECIMAL(10,2),
    usuario_id INTEGER REFERENCES usuarios(id),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_pedido_status_pagamento ON pedido(status_pagamento);
CREATE INDEX IF NOT EXISTS idx_pedido_saldo_devedor ON pedido(saldo_devedor);
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_pedido_id ON pagamentos_pedido(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_tenant_filial ON pagamentos_pedido(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_created_at ON pagamentos_pedido(created_at);

-- 5. Add comment to tables
COMMENT ON COLUMN pedido.valor_pago IS 'Total amount already paid for this order';
COMMENT ON COLUMN pedido.saldo_devedor IS 'Remaining amount to be paid';
COMMENT ON COLUMN pedido.status_pagamento IS 'Payment status: pendente, parcial (partially paid), quitado (fully paid)';
COMMENT ON TABLE pagamentos_pedido IS 'Stores each partial payment made for orders';

-- 6. Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_pagamentos_pedido_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_pagamentos_pedido_timestamp
    BEFORE UPDATE ON pagamentos_pedido
    FOR EACH ROW
    EXECUTE FUNCTION update_pagamentos_pedido_timestamp();

