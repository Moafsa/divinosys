-- Add Asaas payment columns to pagamentos table
-- Migration: add_asaas_columns_to_pagamentos

-- Add columns for Asaas integration if they don't exist
DO $$ 
BEGIN
    -- assinatura_id
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'assinatura_id'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN assinatura_id INTEGER;
        ALTER TABLE pagamentos ADD CONSTRAINT pagamentos_assinatura_id_fkey 
            FOREIGN KEY (assinatura_id) REFERENCES assinaturas(id) ON DELETE SET NULL;
    END IF;

    -- status (for Asaas payment status: pendente, pago, falhou, cancelado)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'status'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN status VARCHAR(50) DEFAULT 'pendente';
    END IF;

    -- data_vencimento
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'data_vencimento'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN data_vencimento DATE;
    END IF;

    -- data_pagamento
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'data_pagamento'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN data_pagamento TIMESTAMP;
    END IF;

    -- metodo_pagamento (pix, boleto, credit_card)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'metodo_pagamento'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN metodo_pagamento VARCHAR(50);
    END IF;

    -- gateway_payment_id (ID do pagamento no Asaas)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'gateway_payment_id'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN gateway_payment_id VARCHAR(255);
        CREATE INDEX IF NOT EXISTS idx_pagamentos_gateway_payment ON pagamentos(gateway_payment_id);
    END IF;

    -- gateway_customer_id (ID do cliente no Asaas)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'gateway_customer_id'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN gateway_customer_id VARCHAR(255);
    END IF;

    -- gateway_response (JSON completo da resposta do Asaas)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'gateway_response'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN gateway_response TEXT;
    END IF;

    -- valor (valor total do pagamento)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'valor'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN valor NUMERIC(10,2);
    END IF;

    -- updated_at
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
    END IF;

END $$;

-- Comment
COMMENT ON COLUMN pagamentos.assinatura_id IS 'Relaciona o pagamento com uma assinatura de tenant';
COMMENT ON COLUMN pagamentos.status IS 'Status do pagamento: pendente, pago, falhou, cancelado';
COMMENT ON COLUMN pagamentos.gateway_payment_id IS 'ID do pagamento no gateway (Asaas)';
COMMENT ON COLUMN pagamentos.gateway_customer_id IS 'ID do cliente no gateway (Asaas)';
COMMENT ON COLUMN pagamentos.gateway_response IS 'Resposta completa do gateway em JSON';

