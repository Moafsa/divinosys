-- Update categories table to support hierarchy and images
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES categorias(id) ON DELETE CASCADE;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS imagem VARCHAR(500);
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;

-- Update produtos table to support images
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagem VARCHAR(500);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagens JSONB; -- Multiple images
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS destaque BOOLEAN DEFAULT false;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;

-- Create ingredientes table if not exists
CREATE TABLE IF NOT EXISTS ingredientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco_adicional DECIMAL(10,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add tipo column to existing ingredientes table if it doesn't exist
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS tipo VARCHAR(50) DEFAULT 'ingrediente';

-- Create produto_ingredientes table (many-to-many)
CREATE TABLE IF NOT EXISTS produto_ingredientes (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    ingrediente_id INTEGER NOT NULL REFERENCES ingredientes(id) ON DELETE CASCADE,
    obrigatorio BOOLEAN DEFAULT false,
    preco_adicional DECIMAL(10,2) DEFAULT 0.00,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(produto_id, ingrediente_id)
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_categorias_parent ON categorias(parent_id);
CREATE INDEX IF NOT EXISTS idx_categorias_ativo ON categorias(ativo);
CREATE INDEX IF NOT EXISTS idx_produtos_ativo ON produtos(ativo);
CREATE INDEX IF NOT EXISTS idx_produtos_destaque ON produtos(destaque);
CREATE INDEX IF NOT EXISTS idx_ingredientes_ativo ON ingredientes(ativo);
CREATE INDEX IF NOT EXISTS idx_produto_ingredientes_produto ON produto_ingredientes(produto_id);
CREATE INDEX IF NOT EXISTS idx_produto_ingredientes_ingrediente ON produto_ingredientes(ingrediente_id);

-- Insert default categories
INSERT INTO categorias (nome, descricao, tenant_id, filial_id, ativo, ordem) VALUES
('Lanches', 'Hambúrgueres, Xis e Sanduíches', 1, 1, true, 1),
('Bebidas', 'Refrigerantes, Sucos e Águas', 1, 1, true, 2),
('Acompanhamentos', 'Batatas, Saladas e Outros', 1, 1, true, 3),
('Sobremesas', 'Doces, Sorvetes e Tortas', 1, 1, true, 4)
ON CONFLICT DO NOTHING;

-- Insert subcategories for Lanches
INSERT INTO categorias (nome, descricao, parent_id, tenant_id, filial_id, ativo, ordem) VALUES
('Hambúrgueres', 'Hambúrgueres Tradicionais', 1, 1, 1, true, 1),
('Xis', 'Xis da Casa e Especiais', 1, 1, 1, true, 2),
('Sanduíches', 'Sanduíches Naturais e Quentes', 1, 1, 1, true, 3)
ON CONFLICT DO NOTHING;

-- Insert subcategories for Bebidas
INSERT INTO categorias (nome, descricao, parent_id, tenant_id, filial_id, ativo, ordem) VALUES
('Refrigerantes', 'Coca-Cola, Pepsi e Outros', 2, 1, 1, true, 1),
('Sucos', 'Sucos Naturais e Industrializados', 2, 1, 1, true, 2),
('Águas', 'Água Mineral e Saborizada', 2, 1, 1, true, 3)
ON CONFLICT DO NOTHING;

-- Insert default ingredients
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
ON CONFLICT DO NOTHING;
