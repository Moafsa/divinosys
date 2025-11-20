-- Adicionar colunas de recorrência na tabela lancamentos_financeiros
-- Verificar e adicionar se não existirem

-- Adicionar coluna recorrencia se não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'recorrencia'
    ) THEN
        ALTER TABLE lancamentos_financeiros 
        ADD COLUMN recorrencia VARCHAR(20) CHECK (recorrencia IN ('nenhuma', 'diaria', 'semanal', 'mensal', 'anual'));
    END IF;
END $$;

-- Adicionar coluna data_fim_recorrencia se não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'data_fim_recorrencia'
    ) THEN
        ALTER TABLE lancamentos_financeiros 
        ADD COLUMN data_fim_recorrencia DATE;
    END IF;
END $$;

-- Adicionar coluna data_lancamento se não existir (caso também esteja faltando)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'data_lancamento'
    ) THEN
        ALTER TABLE lancamentos_financeiros 
        ADD COLUMN data_lancamento DATE;
    END IF;
END $$;

-- Se existir coluna com acento (recorrência), renomear para sem acento
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'recorrência'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'recorrencia'
    ) THEN
        ALTER TABLE lancamentos_financeiros 
        RENAME COLUMN "recorrência" TO recorrencia;
    END IF;
END $$;

-- Se existir coluna com acento (data_fim_recorrência), renomear para sem acento
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'data_fim_recorrência'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'lancamentos_financeiros' 
        AND column_name = 'data_fim_recorrencia'
    ) THEN
        ALTER TABLE lancamentos_financeiros 
        RENAME COLUMN "data_fim_recorrência" TO data_fim_recorrencia;
    END IF;
END $$;

