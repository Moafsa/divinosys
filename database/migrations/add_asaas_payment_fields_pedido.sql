-- =====================================================
-- MIGRATION: ADD ASAAS PAYMENT FIELDS TO PEDIDO
-- Description: Add Asaas payment reference fields to pedido table
-- Date: 2025-01-15
-- =====================================================

-- Add Asaas payment fields to pedido table
ALTER TABLE pedido 
ADD COLUMN IF NOT EXISTS asaas_payment_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS asaas_payment_url VARCHAR(500),
ADD COLUMN IF NOT EXISTS telefone_cliente VARCHAR(20),
ADD COLUMN IF NOT EXISTS tipo_entrega VARCHAR(20) DEFAULT 'pickup' CHECK (tipo_entrega IN ('pickup', 'delivery'));

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_pedido_asaas_payment_id ON pedido(asaas_payment_id) WHERE asaas_payment_id IS NOT NULL;

-- Add comments
COMMENT ON COLUMN pedido.asaas_payment_id IS 'Asaas payment ID for online payments';
COMMENT ON COLUMN pedido.asaas_payment_url IS 'Asaas payment URL for redirect';
COMMENT ON COLUMN pedido.telefone_cliente IS 'Customer phone number';
COMMENT ON COLUMN pedido.tipo_entrega IS 'Delivery type: pickup or delivery';

