-- Migration consolidada para adicionar campos cliente_id e lembrete_enviado
-- Este script pode ser executado em bancos existentes
-- Para novos bancos, use create_reservas_table.sql que já inclui todos os campos

DO $$
BEGIN
    -- Adicionar cliente_id se não existir
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reservas' AND column_name = 'cliente_id') THEN
        ALTER TABLE reservas
        ADD COLUMN cliente_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL;
        
        CREATE INDEX IF NOT EXISTS idx_reservas_cliente_id ON reservas(cliente_id);
        
        COMMENT ON COLUMN reservas.cliente_id IS 'ID do cliente (usuarios_globais) que fez a reserva';
        
        RAISE NOTICE 'Coluna cliente_id adicionada à tabela reservas.';
    ELSE
        RAISE NOTICE 'Coluna cliente_id já existe na tabela reservas.';
    END IF;
    
    -- Adicionar lembrete_enviado se não existir
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reservas' AND column_name = 'lembrete_enviado') THEN
        ALTER TABLE reservas
        ADD COLUMN lembrete_enviado BOOLEAN DEFAULT FALSE;
        
        CREATE INDEX IF NOT EXISTS idx_reservas_lembrete ON reservas(data_reserva, status, lembrete_enviado) WHERE status = 'confirmada';
        
        COMMENT ON COLUMN reservas.lembrete_enviado IS 'Indica se o lembrete de confirmação foi enviado no dia da reserva';
        
        RAISE NOTICE 'Coluna lembrete_enviado adicionada à tabela reservas.';
    ELSE
        RAISE NOTICE 'Coluna lembrete_enviado já existe na tabela reservas.';
    END IF;
END
$$;













