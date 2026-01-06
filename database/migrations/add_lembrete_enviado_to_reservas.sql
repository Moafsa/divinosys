-- Add lembrete_enviado column to reservas table
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'reservas' AND column_name = 'lembrete_enviado') THEN
        ALTER TABLE reservas
        ADD COLUMN lembrete_enviado BOOLEAN DEFAULT FALSE;
        
        CREATE INDEX IF NOT EXISTS idx_reservas_lembrete ON reservas(data_reserva, status, lembrete_enviado) WHERE status = 'confirmada';
        
        COMMENT ON COLUMN reservas.lembrete_enviado IS 'Indica se o lembrete de confirmação foi enviado no dia da reserva';
        
        RAISE NOTICE 'Coluna lembrete_enviado adicionada à tabela reservas.';
    ELSE
        RAISE NOTICE 'Coluna lembrete_enviado já existe na tabela reservas. Nenhuma alteração feita.';
    END IF;
END
$$;













