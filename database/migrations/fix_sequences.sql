-- Fix all sequences to match current max IDs
-- This prevents duplicate key errors after manual inserts or imports

-- Fix produto_ingredientes sequence
SELECT setval('produto_ingredientes_id_seq', COALESCE((SELECT MAX(id) FROM produto_ingredientes), 1), true);

-- Fix produtos sequence
SELECT setval('produtos_id_seq', COALESCE((SELECT MAX(id) FROM produtos), 1), true);

-- Fix categorias sequence
SELECT setval('categorias_id_seq', COALESCE((SELECT MAX(id) FROM categorias), 1), true);

-- Fix ingredientes sequence
SELECT setval('ingredientes_id_seq', COALESCE((SELECT MAX(id) FROM ingredientes), 1), true);

-- Fix pedido sequence
SELECT setval('pedido_idpedido_seq', COALESCE((SELECT MAX(idpedido) FROM pedido), 1), true);

-- Fix pedido_itens sequence
SELECT setval('pedido_itens_id_seq', COALESCE((SELECT MAX(id) FROM pedido_itens), 1), true);

-- Fix tenants sequence
SELECT setval('tenants_id_seq', COALESCE((SELECT MAX(id) FROM tenants), 1), true);

-- Fix filiais sequence
SELECT setval('filiais_id_seq', COALESCE((SELECT MAX(id) FROM filiais), 1), true);

-- Fix usuarios_globais sequence
SELECT setval('usuarios_globais_id_seq', COALESCE((SELECT MAX(id) FROM usuarios_globais), 1), true);

-- Fix usuarios_estabelecimento sequence
SELECT setval('usuarios_estabelecimento_id_seq', COALESCE((SELECT MAX(id) FROM usuarios_estabelecimento), 1), true);

-- Fix clientes sequence (if exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'clientes_id_seq') THEN
        PERFORM setval('clientes_id_seq', COALESCE((SELECT MAX(id) FROM clientes), 1), true);
    END IF;
END $$;

-- Fix mesas sequence (if exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'mesas_id_mesa_seq') THEN
        PERFORM setval('mesas_id_mesa_seq', COALESCE((SELECT MAX(id_mesa) FROM mesas), 1), true);
    END IF;
END $$;

-- Fix assinaturas sequence (if exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'assinaturas_id_seq') THEN
        PERFORM setval('assinaturas_id_seq', COALESCE((SELECT MAX(id) FROM assinaturas), 1), true);
    END IF;
END $$;

-- Log completion
DO $$
BEGIN
    RAISE NOTICE 'All sequences fixed successfully!';
END $$;

