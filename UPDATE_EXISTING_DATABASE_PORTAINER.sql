-- ============================================
-- UPDATE PARA BANCOS EXISTENTES NO PORTAINER
-- Execute este script UMA VEZ no banco existente
-- ============================================

-- 1. Adicionar coluna trial_days se não existir
ALTER TABLE planos 
ADD COLUMN IF NOT EXISTS trial_days INTEGER DEFAULT 14;

-- 2. Atualizar planos existentes com valores estratégicos de trial
UPDATE planos SET trial_days = 7 WHERE nome ILIKE '%básico%' OR nome ILIKE '%starter%';
UPDATE planos SET trial_days = 14 WHERE nome ILIKE '%profissional%' OR nome ILIKE '%professional%';
UPDATE planos SET trial_days = 30 WHERE nome ILIKE '%business%' OR preco_mensal BETWEEN 200 AND 400;
UPDATE planos SET trial_days = 60 WHERE nome ILIKE '%enterprise%' OR preco_mensal > 400;

-- 3. Garantir que todos os planos tenham trial_days (fallback para 14)
UPDATE planos SET trial_days = 14 WHERE trial_days IS NULL OR trial_days = 0;

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

