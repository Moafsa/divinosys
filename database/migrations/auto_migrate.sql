-- Script de migração automática para adicionar colunas faltantes
-- Este script deve ser executado automaticamente no startup do banco

-- Adicionar coluna disponivel na tabela ingredientes se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'ingredientes' AND column_name = 'disponivel') THEN
        ALTER TABLE ingredientes ADD COLUMN disponivel BOOLEAN DEFAULT true;
    END IF;
END $$;

-- Adicionar coluna padrao na tabela produto_ingredientes se não existir
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'produto_ingredientes' AND column_name = 'padrao') THEN
        ALTER TABLE produto_ingredientes ADD COLUMN padrao BOOLEAN DEFAULT true;
    END IF;
END $$;

-- Atualizar registros existentes com valores padrão
UPDATE ingredientes SET disponivel = true WHERE disponivel IS NULL;
UPDATE produto_ingredientes SET padrao = true WHERE padrao IS NULL;

-- Criar índices se não existirem
CREATE INDEX IF NOT EXISTS idx_ingredientes_disponivel ON ingredientes(disponivel);
CREATE INDEX IF NOT EXISTS idx_produto_ingredientes_padrao ON produto_ingredientes(padrao);
