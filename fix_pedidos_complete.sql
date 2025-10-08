-- =============================================
-- Script COMPLETO para corrigir TODAS as tabelas relacionadas a pedidos
-- - Tabela pedido (coluna observacao e outras)
-- - Tabela pedido_itens (coluna tamanho e outras)
-- - Corrigir problemas de boolean
-- =============================================

-- 1. Adicionar colunas faltantes na tabela 'pedido'
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS observacao TEXT;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS usuario_id INTEGER;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS tipo CHARACTER VARYING(50);
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS cliente_id INTEGER;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS mesa_pedido_id CHARACTER VARYING(255);
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS numero_pessoas INTEGER DEFAULT 1;

-- 2. Adicionar colunas faltantes na tabela 'pedido_itens'
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS tamanho CHARACTER VARYING(50) NOT NULL DEFAULT 'normal';
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS observacao TEXT;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS ingredientes_com TEXT;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS ingredientes_sem TEXT;

-- 3. Corrigir problemas de boolean na tabela pedido
UPDATE pedido SET delivery = false WHERE delivery IS NULL OR delivery = '' OR delivery::text = '';

-- 4. Corrigir sequences
SELECT setval('pedido_idpedido_seq', (SELECT MAX(idpedido) FROM pedido) + 1, false);
SELECT setval('pedido_itens_id_seq', (SELECT MAX(id) FROM pedido_itens) + 1, false);

-- 5. Verificar estrutura final
SELECT 
    'pedido' as tabela,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'pedido' 
ORDER BY ordinal_position;

SELECT 
    'pedido_itens' as tabela,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'pedido_itens' 
ORDER BY ordinal_position;
