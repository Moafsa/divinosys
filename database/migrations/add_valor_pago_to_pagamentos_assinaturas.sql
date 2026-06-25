-- Add missing columns to pagamentos_assinaturas table
-- Migration: add_valor_pago_to_pagamentos_assinaturas
-- Fix for: SQLSTATE[42703]: Undefined column: valor_pago

-- Add missing columns if they don't exist
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

    -- filial_id (referência à filial do tenant)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_assinaturas' AND column_name = 'filial_id'
    ) THEN
        ALTER TABLE pagamentos_assinaturas ADD COLUMN filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL;
    END IF;

    -- forma_pagamento (forma de pagamento utilizada)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_assinaturas' AND column_name = 'forma_pagamento'
    ) THEN
        ALTER TABLE pagamentos_assinaturas ADD COLUMN forma_pagamento VARCHAR(50);
    END IF;

    -- gateway_customer_id (ID do cliente no gateway)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_assinaturas' AND column_name = 'gateway_customer_id'
    ) THEN
        ALTER TABLE pagamentos_assinaturas ADD COLUMN gateway_customer_id VARCHAR(255);
    END IF;
END $$;

-- Comments
COMMENT ON COLUMN pagamentos_assinaturas.valor_pago IS 'Valor efetivamente pago pelo cliente (pode ser diferente de valor em caso de descontos ou pagamentos parciais)';
COMMENT ON COLUMN pagamentos_assinaturas.filial_id IS 'Referência à filial do tenant (opcional)';
COMMENT ON COLUMN pagamentos_assinaturas.forma_pagamento IS 'Forma de pagamento utilizada (pix, boleto, cartão, etc)';
COMMENT ON COLUMN pagamentos_assinaturas.gateway_customer_id IS 'ID do cliente no gateway de pagamento (Asaas)';

