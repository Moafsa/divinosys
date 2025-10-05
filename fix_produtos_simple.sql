-- Fix produtos table
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_normal DECIMAL(10,2) DEFAULT 0.00;

-- Create default categoria if it doesn't exist
INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id) 
SELECT 'Geral', 'Categoria padrão', true, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE id = 1 AND tenant_id = 1 AND filial_id = 1);
