-- Comando SQL para executar no container PostgreSQL em produção
-- Este script adiciona colunas faltantes sem deletar dados existentes

-- 1. Adicionar colunas faltantes na tabela ingredientes
ALTER TABLE ingredientes 
ADD COLUMN IF NOT EXISTS tenant_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS filial_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;

-- 2. Adicionar colunas faltantes na tabela produto_ingredientes
ALTER TABLE produto_ingredientes 
ADD COLUMN IF NOT EXISTS tenant_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS filial_id INTEGER DEFAULT 1;

-- 3. Atualizar registros existentes com valores padrão (apenas onde estão NULL)
UPDATE ingredientes 
SET tenant_id = 1, filial_id = 1, ativo = true 
WHERE tenant_id IS NULL OR filial_id IS NULL OR ativo IS NULL;

UPDATE produto_ingredientes 
SET tenant_id = 1, filial_id = 1 
WHERE tenant_id IS NULL OR filial_id IS NULL;

-- 4. Criar índices para melhor performance (se não existirem)
CREATE INDEX IF NOT EXISTS idx_ingredientes_tenant_filial ON ingredientes(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_produto_ingredientes_tenant_filial ON produto_ingredientes(tenant_id, filial_id);

-- 5. Verificar se as alterações foram aplicadas
SELECT 'ingredientes' as tabela, COUNT(*) as total_registros FROM ingredientes;
SELECT 'produto_ingredientes' as tabela, COUNT(*) as total_registros FROM produto_ingredientes;

-- 6. Verificar estrutura das tabelas
\d ingredientes;
\d produto_ingredientes;
