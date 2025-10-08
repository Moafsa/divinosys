-- Script SQL COMPLETO para corrigir TODAS as colunas faltantes
-- Baseado nos erros reais dos logs do sistema

-- Verificar estrutura atual
SELECT '=== ESTRUTURA ATUAL DAS TABELAS ===' as info;

SELECT 'Categorias:' as tabela;
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'categorias' 
ORDER BY ordinal_position;

SELECT 'Ingredientes:' as tabela;
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'ingredientes' 
ORDER BY ordinal_position;

SELECT 'Produtos:' as tabela;
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'produtos' 
ORDER BY ordinal_position;

-- Adicionar colunas faltantes na tabela categorias
SELECT '=== ADICIONANDO COLUNAS FALTANTES ===' as info;

ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS imagem VARCHAR(255);

-- Adicionar colunas faltantes na tabela ingredientes
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS tipo VARCHAR(50) DEFAULT 'complemento';
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS preco_adicional DECIMAL(10,2) DEFAULT 0;

-- Adicionar colunas faltantes na tabela produtos
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_mini DECIMAL(10,2) DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS estoque_atual INTEGER DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS estoque_minimo INTEGER DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_custo DECIMAL(10,2) DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagem VARCHAR(255);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS categoria_id INTEGER;

-- Verificar estado atual das sequences
SELECT '=== ESTADO ATUAL DAS SEQUENCES ===' as info;

-- Verificar se sequences existem e corrigir
DO $$
BEGIN
    -- Categorias sequence
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'categorias_id_seq') THEN
        PERFORM setval('categorias_id_seq', (SELECT MAX(id) FROM categorias) + 1);
        RAISE NOTICE 'Sequence categorias_id_seq corrigida';
    ELSE
        RAISE NOTICE 'Sequence categorias_id_seq não encontrada';
    END IF;
    
    -- Ingredientes sequence
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'ingredientes_id_seq') THEN
        PERFORM setval('ingredientes_id_seq', (SELECT MAX(id) FROM ingredientes) + 1);
        RAISE NOTICE 'Sequence ingredientes_id_seq corrigida';
    ELSE
        RAISE NOTICE 'Sequence ingredientes_id_seq não encontrada';
    END IF;
    
    -- Produtos sequence
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'produtos_id_seq') THEN
        PERFORM setval('produtos_id_seq', (SELECT MAX(id) FROM produtos) + 1);
        RAISE NOTICE 'Sequence produtos_id_seq corrigida';
    ELSE
        RAISE NOTICE 'Sequence produtos_id_seq não encontrada';
    END IF;
END $$;

-- Verificar sequences após correção
SELECT '=== SEQUENCES APÓS CORREÇÃO ===' as info;

SELECT 
    'categorias' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'categorias_id_seq') 
        THEN (SELECT last_value FROM categorias_id_seq)::text
        ELSE 'Não existe'
    END as sequence_final
UNION ALL
SELECT 
    'ingredientes' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'ingredientes_id_seq') 
        THEN (SELECT last_value FROM ingredientes_id_seq)::text
        ELSE 'Não existe'
    END as sequence_final
UNION ALL
SELECT 
    'produtos' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'produtos_id_seq') 
        THEN (SELECT last_value FROM produtos_id_seq)::text
        ELSE 'Não existe'
    END as sequence_final;

-- Testes de funcionamento (opcional - descomente se quiser testar)
/*
-- Teste categoria
INSERT INTO categorias (nome, descricao, ativo, ordem, tenant_id, filial_id) 
VALUES ('Teste Categoria', 'Teste de funcionamento', true, 999, 1, 1);

SELECT id, nome FROM categorias WHERE nome = 'Teste Categoria';
DELETE FROM categorias WHERE nome = 'Teste Categoria';

-- Teste ingrediente
INSERT INTO ingredientes (nome, descricao, tipo, preco_adicional, ativo, tenant_id, filial_id) 
VALUES ('Teste Ingrediente', 'Teste de funcionamento', 'teste', 1.50, true, 1, 1);

SELECT id, nome FROM ingredientes WHERE nome = 'Teste Ingrediente';
DELETE FROM ingredientes WHERE nome = 'Teste Ingrediente';

-- Teste produto
INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, ativo, estoque_atual, estoque_minimo, preco_custo, tenant_id, filial_id) 
VALUES ('Teste Produto', 'Teste de funcionamento', 25.00, 20.00, true, 10, 5, 15.00, 1, 1);

SELECT id, nome FROM produtos WHERE nome = 'Teste Produto';
DELETE FROM produtos WHERE nome = 'Teste Produto';
*/

SELECT '=== CORREÇÃO COMPLETA DO SCHEMA FINALIZADA ===' as resultado;
SELECT 'Agora TODOS os cadastros devem funcionar corretamente!' as mensagem;
