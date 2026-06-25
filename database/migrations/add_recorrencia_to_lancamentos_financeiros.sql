-- Add recorrencia column to lancamentos_financeiros table
-- Migration: add_recorrencia_to_lancamentos_financeiros
-- Fix for: SQLSTATE[42703]: Undefined column: recorrencia

-- Add recorrencia column if it doesn't exist
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' AND column_name = 'recorrencia'
    ) THEN
        ALTER TABLE lancamentos_financeiros ADD COLUMN recorrencia VARCHAR(20) DEFAULT 'nenhuma';
        
        -- Migrate data from recorrência (with accent) to recorrencia (without accent) if exists
        IF EXISTS (
            SELECT 1 FROM information_schema.columns 
            WHERE table_name = 'lancamentos_financeiros' AND column_name = 'recorrência'
        ) THEN
            UPDATE lancamentos_financeiros 
            SET recorrencia = COALESCE("recorrência", 'nenhuma')
            WHERE recorrencia IS NULL OR recorrencia = 'nenhuma';
        END IF;
        
        -- Update existing records: set default value
        UPDATE lancamentos_financeiros 
        SET recorrencia = 'nenhuma' 
        WHERE recorrencia IS NULL;
    END IF;
END $$;

-- Add comment
COMMENT ON COLUMN lancamentos_financeiros.recorrencia IS 'Tipo de recorrência: nenhuma, diaria, semanal, mensal, anual';

