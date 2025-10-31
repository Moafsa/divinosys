-- =====================================================
-- MIGRATION: FIX pagamentos_pedido table
-- Description: Add missing usuario_global_id field to pagamentos_pedido table
-- Date: 2025-10-20
-- =====================================================

-- Add usuario_global_id column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_pedido' 
        AND column_name = 'usuario_global_id'
    ) THEN
        ALTER TABLE pagamentos_pedido 
        ADD COLUMN usuario_global_id INTEGER REFERENCES usuarios_globais(id);
        
        RAISE NOTICE 'Campo usuario_global_id adicionado à tabela pagamentos_pedido';
    ELSE
        RAISE NOTICE 'Campo usuario_global_id já existe na tabela pagamentos_pedido';
    END IF;
END $$;

-- Create index for performance
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_usuario_global_id ON pagamentos_pedido(usuario_global_id);

-- Update existing records to set usuario_global_id based on pedido.usuario_global_id
UPDATE pagamentos_pedido 
SET usuario_global_id = p.usuario_global_id
FROM pedido p 
WHERE pagamentos_pedido.pedido_id = p.idpedido 
AND pagamentos_pedido.usuario_global_id IS NULL;













