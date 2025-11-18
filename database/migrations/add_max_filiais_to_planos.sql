-- Adicionar coluna max_filiais à tabela planos
ALTER TABLE planos ADD COLUMN IF NOT EXISTS max_filiais INTEGER DEFAULT 1;

-- Comentário explicativo
COMMENT ON COLUMN planos.max_filiais IS 'Número máximo de filiais permitidas neste plano (-1 = ilimitado)';

-- Atualizar planos existentes com valores padrão
UPDATE planos SET max_filiais = 1 WHERE nome LIKE '%Starter%' OR nome LIKE '%Básico%';
UPDATE planos SET max_filiais = 3 WHERE nome LIKE '%Profissional%' OR nome LIKE '%Professional%';
UPDATE planos SET max_filiais = 10 WHERE nome LIKE '%Business%' OR nome LIKE '%Empresarial%';
UPDATE planos SET max_filiais = -1 WHERE nome LIKE '%Enterprise%' OR nome LIKE '%Ilimitado%';

