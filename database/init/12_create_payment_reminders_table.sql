-- =====================================================
-- PAYMENT REMINDERS TABLE
-- =====================================================
-- Tabela para agendar lembretes de pagamento via WhatsApp
-- Envia mensagens automáticas quando fatura é gerada no Asaas
-- Agenda lembretes após 10 minutos se pagamento não foi concluído
-- Date: 2025-01-XX
-- =====================================================

-- Tabela para agendar lembretes de pagamento via WhatsApp
CREATE TABLE IF NOT EXISTS payment_reminders (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    asaas_payment_id VARCHAR(255),
    cliente_telefone VARCHAR(20) NOT NULL,
    cliente_nome VARCHAR(255),
    valor_total DECIMAL(10,2),
    payment_url TEXT,
    pix_copy_paste TEXT,
    billing_type VARCHAR(50), -- PIX, CREDIT_CARD, BOLETO
    reminder_type VARCHAR(50) DEFAULT 'initial', -- initial, followup
    scheduled_for TIMESTAMP NOT NULL,
    sent_at TIMESTAMP,
    status VARCHAR(50) DEFAULT 'pending', -- pending, sent, cancelled, failed
    error_message TEXT,
    whatsapp_instance_id INTEGER REFERENCES whatsapp_instances(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_payment_reminders_scheduled ON payment_reminders(scheduled_for, status);
CREATE INDEX IF NOT EXISTS idx_payment_reminders_pedido ON payment_reminders(pedido_id);
CREATE INDEX IF NOT EXISTS idx_payment_reminders_payment ON payment_reminders(asaas_payment_id);
CREATE INDEX IF NOT EXISTS idx_payment_reminders_status ON payment_reminders(status);
CREATE INDEX IF NOT EXISTS idx_payment_reminders_tenant_filial ON payment_reminders(tenant_id, filial_id);

-- Comentários
COMMENT ON TABLE payment_reminders IS 'Agenda lembretes de pagamento via WhatsApp para clientes';
COMMENT ON COLUMN payment_reminders.reminder_type IS 'Tipo: initial (mensagem inicial) ou followup (lembrete após 10 min)';
COMMENT ON COLUMN payment_reminders.scheduled_for IS 'Data/hora agendada para envio do lembrete';
COMMENT ON COLUMN payment_reminders.status IS 'Status: pending (aguardando), sent (enviado), cancelled (cancelado), failed (falhou)';
COMMENT ON COLUMN payment_reminders.billing_type IS 'Tipo de pagamento: PIX, CREDIT_CARD, BOLETO';
COMMENT ON COLUMN payment_reminders.pix_copy_paste IS 'Código PIX copia e cola (se disponível)';













