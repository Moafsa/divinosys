-- Add valor_pago column to pagamentos_assinaturas table
-- Migration: add_valor_pago_to_pagamentos_assinaturas
-- Fix for: SQLSTATE[42703]: Undefined column: valor_pago

-- Add valor_pago column if it doesn't exist
DO $$ 
BEGIN
    -- valor_pago (valor efetivamente pago, pode ser diferente de valor em caso de descontos/parciais)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_assinaturas' AND column_name = 'valor_pago'
    ) THEN
        ALTER TABLE pagamentos_assinaturas ADD COLUMN valor_pago DECIMAL(10,2) DEFAULT 0.00;
        
        -- Update existing records: set valor_pago = valor if valor exists and valor_pago is 0
        UPDATE pagamentos_assinaturas 
        SET valor_pago = COALESCE(valor, 0.00) 
        WHERE valor_pago IS NULL OR valor_pago = 0.00;
    END IF;
END $$;

-- Comment
COMMENT ON COLUMN pagamentos_assinaturas.valor_pago IS 'Valor efetivamente pago pelo cliente (pode ser diferente de valor em caso de descontos ou pagamentos parciais)';

