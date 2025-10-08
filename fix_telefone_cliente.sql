-- =============================================
-- Script para adicionar coluna telefone_cliente na tabela pedido
-- Necessário para fechar pedidos individualmente ou fechar mesa
-- =============================================

-- 1. Verificar se a coluna já existe
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'pedido' AND column_name = 'telefone_cliente';

-- 2. Adicionar coluna telefone_cliente se não existir
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS telefone_cliente CHARACTER VARYING(20);

-- 3. Verificar estrutura final
SELECT 
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'pedido' 
AND (column_name LIKE '%cliente%' OR column_name LIKE '%telefone%')
ORDER BY ordinal_position;
