-- Script SQL para corrigir a tabela pedido online
-- Adiciona coluna 'observacao' e outras colunas faltantes

-- Verificar estrutura atual
SELECT '=== ESTRUTURA ATUAL DA TABELA PEDIDO ===' as info;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns 
WHERE table_name = 'pedido' 
ORDER BY ordinal_position;

-- Verificar se existe a coluna 'observacao'
SELECT '=== VERIFICANDO COLUNA OBSERVACAO ===' as info;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pedido' AND column_name = 'observacao')
        THEN 'Coluna observacao EXISTE - OK'
        ELSE 'Coluna observacao NÃO EXISTE - precisa ser adicionada'
    END as status_observacao;

-- Adicionar colunas faltantes (baseado na estrutura local)
SELECT '=== ADICIONANDO COLUNAS FALTANTES ===' as info;

ALTER TABLE pedido ADD COLUMN IF NOT EXISTS observacao TEXT;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS usuario_id INTEGER;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS tipo CHARACTER VARYING(50);
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS cliente_id INTEGER;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS mesa_pedido_id CHARACTER VARYING(255);
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS numero_pessoas INTEGER;

-- Verificar constraints importantes
SELECT '=== VERIFICANDO CONSTRAINTS ===' as info;

SELECT 
    column_name,
    is_nullable,
    CASE 
        WHEN column_name = 'idpedido' AND is_nullable = 'NO' 
        THEN 'OK: idpedido é NOT NULL'
        WHEN column_name = 'data' AND is_nullable = 'NO'
        THEN 'OK: data é NOT NULL'
        WHEN column_name = 'hora_pedido' AND is_nullable = 'NO'
        THEN 'OK: hora_pedido é NOT NULL'
        WHEN column_name = 'status' AND is_nullable = 'NO'
        THEN 'OK: status é NOT NULL'
        ELSE 'OK'
    END as constraint_status
FROM information_schema.columns 
WHERE table_name = 'pedido' 
AND column_name IN ('idpedido', 'data', 'hora_pedido', 'status');

-- Corrigir sequence
SELECT '=== CORRIGINDO SEQUENCE ===' as info;

DO $$
DECLARE
    current_seq_value BIGINT;
    max_id_value BIGINT;
    new_seq_value BIGINT;
BEGIN
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'pedido_idpedido_seq') THEN
        SELECT last_value INTO current_seq_value FROM pedido_idpedido_seq;
        SELECT COALESCE(MAX(idpedido), 0) INTO max_id_value FROM pedido;
        new_seq_value := max_id_value + 1;
        
        RAISE NOTICE 'Sequence atual: %, MAX ID: %, Novo valor: %', current_seq_value, max_id_value, new_seq_value;
        
        IF current_seq_value <= max_id_value THEN
            PERFORM setval('pedido_idpedido_seq', new_seq_value);
            RAISE NOTICE 'Sequence corrigida para: %', new_seq_value;
        ELSE
            RAISE NOTICE 'Sequence já está correta';
        END IF;
    ELSE
        RAISE NOTICE 'Sequence pedido_idpedido_seq não encontrada';
    END IF;
END $$;

-- Teste de funcionamento (opcional - descomente se quiser testar)
/*
SELECT '=== TESTE DE FUNCIONAMENTO ===' as info;

-- Teste inserção pedido
INSERT INTO pedido (idmesa, cliente, delivery, data, hora_pedido, status, valor_total, observacao, usuario_id, tenant_id, filial_id) 
VALUES (999, 'Cliente Teste', false, CURRENT_DATE, CURRENT_TIME, 'Pendente', 25.00, 'Pedido de teste para verificar funcionamento', 1, 1, 1);

-- Verificar se foi inserido
SELECT idpedido, cliente, observacao FROM pedido WHERE cliente = 'Cliente Teste';

-- Remover teste
DELETE FROM pedido WHERE cliente = 'Cliente Teste';
*/

-- Verificar estrutura final
SELECT '=== ESTRUTURA FINAL DA TABELA PEDIDO ===' as info;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns 
WHERE table_name = 'pedido' 
ORDER BY ordinal_position;

-- Verificar sequence final
SELECT '=== SEQUENCE FINAL ===' as info;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM pg_sequences WHERE sequencename = 'pedido_idpedido_seq') 
        THEN (SELECT last_value FROM pedido_idpedido_seq)::text
        ELSE 'Não existe'
    END as sequence_final;

SELECT '=== CORREÇÃO DA TABELA PEDIDO FINALIZADA ===' as resultado;
SELECT 'Agora a criação de pedidos deve funcionar corretamente!' as mensagem;
