-- =====================================================
-- Fix pagamentos table - Add missing assinatura_id column
-- =====================================================

-- Add assinatura_id column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos' 
        AND column_name = 'assinatura_id'
    ) THEN
        ALTER TABLE pagamentos ADD COLUMN assinatura_id INTEGER REFERENCES assinaturas(id) ON DELETE SET NULL;
        RAISE NOTICE '✅ Column assinatura_id added to pagamentos table';
    ELSE
        RAISE NOTICE 'ℹ️  Column assinatura_id already exists in pagamentos table';
    END IF;
END
$$;

-- Create index for performance
CREATE INDEX IF NOT EXISTS idx_pagamentos_assinatura_id ON pagamentos(assinatura_id);

-- Log completion
DO $$
BEGIN
    RAISE NOTICE '✅ pagamentos table structure fixed';
END
$$;

