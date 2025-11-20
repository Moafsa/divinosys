-- =====================================================
-- MIGRATION: CARDAPIO ONLINE FIELDS
-- Description: Add fields for online menu functionality
-- Date: 2025-01-15
-- =====================================================

-- Add online menu fields to filiais table
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS cardapio_online_ativo BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS taxa_delivery_fixa DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS usar_calculo_distancia BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS n8n_webhook_distancia VARCHAR(500),
ADD COLUMN IF NOT EXISTS raio_entrega_km DECIMAL(10,2) DEFAULT 5.00,
ADD COLUMN IF NOT EXISTS tempo_medio_preparo INTEGER DEFAULT 30,
ADD COLUMN IF NOT EXISTS horario_funcionamento JSONB DEFAULT '{"segunda": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "terca": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "quarta": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "quinta": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "sexta": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "sabado": {"aberto": true, "inicio": "08:00", "fim": "22:00"}, "domingo": {"aberto": true, "inicio": "08:00", "fim": "22:00"}}'::jsonb,
ADD COLUMN IF NOT EXISTS aceita_pagamento_online BOOLEAN DEFAULT true,
ADD COLUMN IF NOT EXISTS aceita_pagamento_na_hora BOOLEAN DEFAULT true;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_filiais_cardapio_online_ativo ON filiais(cardapio_online_ativo) WHERE cardapio_online_ativo = true;

-- Add comments
COMMENT ON COLUMN filiais.cardapio_online_ativo IS 'Enable/disable online menu for this branch';
COMMENT ON COLUMN filiais.taxa_delivery_fixa IS 'Fixed delivery fee (used if usar_calculo_distancia is false)';
COMMENT ON COLUMN filiais.usar_calculo_distancia IS 'Use n8n webhook to calculate delivery fee based on distance';
COMMENT ON COLUMN filiais.n8n_webhook_distancia IS 'n8n webhook URL for distance calculation';
COMMENT ON COLUMN filiais.raio_entrega_km IS 'Maximum delivery radius in kilometers';
COMMENT ON COLUMN filiais.tempo_medio_preparo IS 'Average preparation time in minutes';
COMMENT ON COLUMN filiais.horario_funcionamento IS 'Opening hours JSON: {day: {aberto: bool, inicio: "HH:mm", fim: "HH:mm"}}';
COMMENT ON COLUMN filiais.aceita_pagamento_online IS 'Accept online payment via Asaas';
COMMENT ON COLUMN filiais.aceita_pagamento_na_hora IS 'Accept payment on delivery/pickup';

