-- Fix ingredientes table columns for Coolify deployment
-- This script ensures the ingredientes table has the required columns

-- Add ativo column if it doesn't exist
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'ingredientes' AND column_name = 'ativo'
    ) THEN
        ALTER TABLE ingredientes ADD COLUMN ativo BOOLEAN DEFAULT true;
    END IF;
END $$;

-- Add descricao column if it doesn't exist
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'ingredientes' AND column_name = 'descricao'
    ) THEN
        ALTER TABLE ingredientes ADD COLUMN descricao TEXT;
    END IF;
END $$;

-- Update existing records
UPDATE ingredientes SET ativo = true WHERE ativo IS NULL;
UPDATE ingredientes SET descricao = nome WHERE descricao IS NULL OR descricao = '';

-- Create index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_ingredientes_ativo ON ingredientes(ativo);

-- Insert default ingredients if they don't exist
INSERT INTO ingredientes (nome, descricao, preco_adicional, tenant_id, filial_id, ativo, tipo) VALUES
('Bacon', 'Bacon crocante', 3.00, 1, 1, true, 'ingrediente'),
('Queijo Extra', 'Porção adicional de queijo', 2.50, 1, 1, true, 'ingrediente'),
('Ovo', 'Ovo frito', 2.00, 1, 1, true, 'ingrediente'),
('Cebola', 'Cebola roxa', 1.50, 1, 1, true, 'ingrediente'),
('Tomate', 'Tomate fresco', 1.50, 1, 1, true, 'ingrediente'),
('Alface', 'Alface americana', 1.00, 1, 1, true, 'ingrediente'),
('Picles', 'Picles de pepino', 1.00, 1, 1, true, 'ingrediente'),
('Maionese', 'Maionese caseira', 1.00, 1, 1, true, 'ingrediente'),
('Ketchup', 'Ketchup Heinz', 1.00, 1, 1, true, 'ingrediente'),
('Mostarda', 'Mostarda Dijon', 1.00, 1, 1, true, 'ingrediente')
ON CONFLICT (nome, tenant_id, filial_id) DO NOTHING;
