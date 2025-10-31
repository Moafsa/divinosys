-- Fix sequences after data import/migration
-- This script ensures all sequences are properly synchronized
-- Run this after any data import or migration

-- Function to fix sequence for a table
CREATE OR REPLACE FUNCTION fix_sequence(table_name TEXT, sequence_name TEXT, id_column TEXT DEFAULT 'id')
RETURNS TEXT AS $$
DECLARE
    max_id INTEGER;
    result TEXT;
BEGIN
    -- Get max ID from table
    EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I', id_column, table_name) INTO max_id;
    
    -- Set sequence to max_id + 1
    EXECUTE format('SELECT setval(%L, %s)', sequence_name, max_id + 1);
    
    result := format('Fixed sequence %s for table %s to %s', sequence_name, table_name, max_id + 1);
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Fix all critical sequences
DO $$
DECLARE
    result TEXT;
BEGIN
    -- Core business tables
    SELECT fix_sequence('produtos', 'produtos_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('categorias', 'categorias_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('ingredientes', 'ingredientes_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('mesas', 'mesas_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('pedido', 'pedido_idpedido_seq', 'idpedido') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('pedido_itens', 'pedido_itens_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('mesa_pedidos', 'mesa_pedidos_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('estoque', 'estoque_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    -- SaaS tables
    SELECT fix_sequence('tenants', 'tenants_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('filiais', 'filiais_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('usuarios', 'usuarios_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('planos', 'planos_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    -- Financial tables
    SELECT fix_sequence('contas_financeiras', 'contas_financeiras_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('categorias_financeiras', 'categorias_financeiras_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    -- WhatsApp tables
    SELECT fix_sequence('evolution_instancias', 'evolution_instancias_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    -- Global users tables
    SELECT fix_sequence('usuarios_globais', 'usuarios_globais_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('usuarios_telefones', 'usuarios_telefones_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    SELECT fix_sequence('usuarios_estabelecimento', 'usuarios_estabelecimento_id_seq') INTO result;
    RAISE NOTICE '%', result;
    
    RAISE NOTICE 'All sequences have been synchronized successfully!';
END $$;

-- Create a function to check sequence status
CREATE OR REPLACE FUNCTION check_sequences()
RETURNS TABLE(
    tbl_name TEXT,
    seq_name TEXT,
    seq_last_value BIGINT,
    tbl_max_id BIGINT,
    seq_status TEXT
) AS $$
BEGIN
    RETURN QUERY
    WITH sequence_info AS (
        SELECT 
            schemaname,
            sequencename,
            last_value as seq_value
        FROM pg_sequences 
        WHERE schemaname = 'public'
        AND sequencename LIKE '%_id_seq'
    ),
    table_info AS (
        SELECT 
            t.table_name as tname,
            CASE 
                WHEN t.table_name = 'pedido' THEN (SELECT COALESCE(MAX(idpedido), 0) FROM pedido)
                ELSE (SELECT COALESCE(MAX(id), 0) FROM information_schema.tables tb 
                      WHERE tb.table_name = si.table_name)
            END as max_id
        FROM information_schema.tables t
        JOIN sequence_info si ON si.sequencename = t.table_name || '_id_seq' OR si.sequencename = t.table_name || '_idpedido_seq'
        WHERE t.table_schema = 'public'
        AND t.table_type = 'BASE TABLE'
    )
    SELECT 
        ti.tname::TEXT,
        si.sequencename::TEXT,
        si.seq_value,
        ti.max_id,
        CASE 
            WHEN si.seq_value >= ti.max_id THEN 'OK'::TEXT
            ELSE 'NEEDS_FIX'::TEXT
        END as seq_status
    FROM sequence_info si
    JOIN table_info ti ON si.sequencename = ti.tname || '_id_seq' OR si.sequencename = ti.tname || '_idpedido_seq'
    ORDER BY ti.tname;
END;
$$ LANGUAGE plpgsql;

-- Show current sequence status (commented out to avoid errors during init)
-- SELECT * FROM check_sequences();

-- Drop the temporary function
DROP FUNCTION IF EXISTS fix_sequence(TEXT, TEXT, TEXT);
