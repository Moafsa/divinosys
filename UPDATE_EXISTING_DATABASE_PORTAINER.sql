-- ============================================
-- UPDATE PARA BANCOS EXISTENTES NO PORTAINER
-- Execute este script UMA VEZ no banco existente
-- ============================================

-- 1. TABELA PLANOS - Adicionar coluna trial_days
ALTER TABLE planos 
ADD COLUMN IF NOT EXISTS trial_days INTEGER DEFAULT 14;

-- Atualizar planos existentes com valores estratégicos de trial
UPDATE planos SET trial_days = 7 WHERE nome ILIKE '%básico%' OR nome ILIKE '%starter%';
UPDATE planos SET trial_days = 14 WHERE nome ILIKE '%profissional%' OR nome ILIKE '%professional%';
UPDATE planos SET trial_days = 30 WHERE nome ILIKE '%business%' OR preco_mensal BETWEEN 200 AND 400;
UPDATE planos SET trial_days = 60 WHERE nome ILIKE '%enterprise%' OR preco_mensal > 400;
UPDATE planos SET trial_days = 14 WHERE trial_days IS NULL OR trial_days = 0;

-- 2. TABELA PAGAMENTOS_ASSINATURAS - Adicionar colunas faltantes
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS filial_id INTEGER;
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50);
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS gateway_customer_id VARCHAR(255);

-- Atualizar valor_pago nos registros existentes
UPDATE pagamentos_assinaturas 
SET valor_pago = COALESCE(valor, 0.00) 
WHERE valor_pago IS NULL OR valor_pago = 0.00;

-- Comentários
COMMENT ON COLUMN pagamentos_assinaturas.valor IS 'Valor da fatura (valor total a pagar)';
COMMENT ON COLUMN pagamentos_assinaturas.valor_pago IS 'Valor efetivamente pago pelo cliente (pode ser diferente em caso de descontos ou pagamentos parciais)';
COMMENT ON COLUMN pagamentos_assinaturas.filial_id IS 'Referência à filial do tenant (opcional)';
COMMENT ON COLUMN pagamentos_assinaturas.forma_pagamento IS 'Forma de pagamento utilizada (pix, boleto, cartão, etc)';
COMMENT ON COLUMN pagamentos_assinaturas.gateway_customer_id IS 'ID do cliente no gateway de pagamento (Asaas)';

-- 4. Verificar resultado
SELECT id, nome, preco_mensal, trial_days, max_filiais 
FROM planos 
ORDER BY preco_mensal ASC;

-- ============================================
-- RESULTADO ESPERADO:
-- ============================================
-- | id | nome              | preco_mensal | trial_days | max_filiais |
-- |----|-------------------|--------------|------------|-------------|
-- | 1  | Plano Básico      | 49.90        | 7          | 1           |
-- | 2  | Plano Profissional| 99.90        | 14         | 3           |
-- | 3  | Plano Empresarial | 199.90       | 30         | 10          |
-- | 4  | Starter           | 49.90        | 7          | 1           |
-- | 5  | Professional      | 149.90       | 14         | 3           |
-- | 6  | Business          | 299.90       | 30         | 10          |
-- | 7  | Enterprise        | 999.90       | 60         | -1          |
-- ============================================

-- COMO EXECUTAR NO PORTAINER:
-- 1. Acesse o container do PostgreSQL no Portainer
-- 2. Vá em "Console" ou "Exec Console"
-- 3. Execute: psql -U postgres -d divino_lanches
-- 4. Cole este script completo
-- 5. Verifique o resultado com a query SELECT acima

