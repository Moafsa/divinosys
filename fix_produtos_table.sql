-- Script SQL para corrigir a tabela produtos online
-- Remove a coluna 'preco' problemática e ajusta a estrutura

-- Verificar estrutura atual
SELECT '=== ESTRUTURA ATUAL DA TABELA PRODUTOS ===' as info;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns 
WHERE table_name = 'produtos' 
ORDER BY ordinal_position;

-- Verificar se existe a coluna 'preco' problemática
SELECT '=== VERIFICANDO COLUNA PRECO ===' as info;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'produtos' AND column_name = 'preco')
        THEN 'Coluna preco EXISTE - precisa ser removida'
        ELSE 'Coluna preco NÃO EXISTE - OK'
    END as status_preco;

-- Migrar dados de 'preco' para 'preco_normal' se necessário
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'produtos' AND column_name = 'preco') THEN
        -- Verificar se há dados na coluna preco
        IF EXISTS (SELECT 1 FROM produtos WHERE preco IS NOT NULL) THEN
            -- Migrar dados
            UPDATE produtos SET preco_normal = preco WHERE preco IS NOT NULL AND preco_normal IS NULL;
            RAISE NOTICE 'Dados migrados de preco para preco_normal';
        END IF;
        
        -- Remover a coluna preco
        ALTER TABLE produtos DROP COLUMN preco;
        RAISE NOTICE 'Coluna preco removida com sucesso';
    ELSE
        RAISE NOTICE 'Coluna preco não existe - OK';
    END IF;
END $$;

-- Adicionar colunas faltantes (baseado na estrutura local)
SELECT '=== ADICIONANDO COLUNAS FALTANTES ===' as info;

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS codigo CHARACTER VARYING(255);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS destaque BOOLEAN DEFAULT false;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagens JSONB;

-- Verificar constraints
SELECT '=== VERIFICANDO CONSTRAINTS ===' as info;

SELECT 
    column_name,
    is_nullable,
    CASE 
        WHEN column_name = 'categoria_id' AND is_nullable = 'YES' 
        THEN 'ATENÇÃO: categoria_id permite NULL (pode causar problemas)'
        WHEN column_name = 'categoria_id' AND is_nullable = 'NO'
        THEN 'OK: categoria_id é NOT NULL'
        ELSE 'OK'
    END as constraint_status
FROM information_schema.columns 
WHERE table_name = 'produtos' AND column_name = 'categoria_id';

-- Corrigir sequence
SELECT '=== CORRIGINDO SEQUENCE ===' as info;

DO $$
DECLARE
    current_seq_value BIGINT;
    max_id_value BIGINT;
    new_seq_value BIGINT;
BEGIN
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'produtos_id_seq') THEN
        SELECT last_value INTO current_seq_value FROM produtos_id_seq;
        SELECT COALESCE(MAX(id), 0) INTO max_id_value FROM produtos;
        new_seq_value := max_id_value + 1;
        
        RAISE NOTICE 'Sequence atual: %, MAX ID: %, Novo valor: %', current_seq_value, max_id_value, new_seq_value;
        
        IF current_seq_value <= max_id_value THEN
            PERFORM setval('produtos_id_seq', new_seq_value);
            RAISE NOTICE 'Sequence corrigida para: %', new_seq_value;
        ELSE
            RAISE NOTICE 'Sequence já está correta';
        END IF;
    ELSE
        RAISE NOTICE 'Sequence produtos_id_seq não encontrada';
    END IF;
END $$;

-- Teste de funcionamento (opcional - descomente se quiser testar)
/*
SELECT '=== TESTE DE FUNCIONAMENTO ===' as info;

-- Teste inserção produto
INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, estoque_atual, estoque_minimo, preco_custo, tenant_id, filial_id) 
VALUES ('Teste Produto Corrigido', 'Teste de funcionamento após correção', 25.00, 20.00, 1, true, 10, 5, 15.00, 1, 1);

-- Verificar se foi inserido
SELECT id, nome FROM produtos WHERE nome = 'Teste Produto Corrigido';

-- Remover teste
DELETE FROM produtos WHERE nome = 'Teste Produto Corrigido';
*/

-- Verificar estrutura final
SELECT '=== ESTRUTURA FINAL DA TABELA PRODUTOS ===' as info;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns 
WHERE table_name = 'produtos' 
ORDER BY ordinal_position;

-- Verificar sequence final
SELECT '=== SEQUENCE FINAL ===' as info;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'produtos_id_seq') 
        THEN (SELECT last_value FROM produtos_id_seq)::text
        ELSE 'Não existe'
    END as sequence_final;

SELECT '=== CORREÇÃO DA TABELA PRODUTOS FINALIZADA ===' as resultado;
SELECT 'Agora o cadastro de produtos deve funcionar corretamente!' as mensagem;
