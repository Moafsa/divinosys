-- Fix missing columns in ingredientes and produto_ingredientes tables
-- This migration adds missing columns that are causing 500 errors

-- Add missing columns to ingredientes table
ALTER TABLE ingredientes 
ADD COLUMN IF NOT EXISTS tenant_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS filial_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;

-- Add missing columns to produto_ingredientes table  
ALTER TABLE produto_ingredientes 
ADD COLUMN IF NOT EXISTS tenant_id INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS filial_id INTEGER DEFAULT 1;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_ingredientes_tenant_filial ON ingredientes(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_produto_ingredientes_tenant_filial ON produto_ingredientes(tenant_id, filial_id);

-- Update existing records to have default values
UPDATE ingredientes SET tenant_id = 1, filial_id = 1, ativo = true WHERE tenant_id IS NULL OR filial_id IS NULL OR ativo IS NULL;
UPDATE produto_ingredientes SET tenant_id = 1, filial_id = 1 WHERE tenant_id IS NULL OR filial_id IS NULL;
