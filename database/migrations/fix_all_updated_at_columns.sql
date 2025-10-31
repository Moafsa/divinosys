-- Comprehensive migration to fix updated_at columns for all tables
-- This ensures all tables have the updated_at column and triggers work properly

-- Add updated_at column to all tables that need it
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE filiais ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE mesas ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing records to have updated_at timestamp
UPDATE produtos SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE categorias SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE filiais SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE usuarios SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE mesas SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE pedido SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;
UPDATE tenants SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL;

-- Create or replace the trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS update_produtos_updated_at ON produtos;
DROP TRIGGER IF EXISTS update_categorias_updated_at ON categorias;
DROP TRIGGER IF EXISTS update_filiais_updated_at ON filiais;
DROP TRIGGER IF EXISTS update_usuarios_updated_at ON usuarios;
DROP TRIGGER IF EXISTS update_mesas_updated_at ON mesas;
DROP TRIGGER IF EXISTS update_pedido_updated_at ON pedido;
DROP TRIGGER IF EXISTS update_tenants_updated_at ON tenants;

-- Create triggers for all tables
CREATE TRIGGER update_produtos_updated_at BEFORE UPDATE ON produtos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_categorias_updated_at BEFORE UPDATE ON categorias FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_filiais_updated_at BEFORE UPDATE ON filiais FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_usuarios_updated_at BEFORE UPDATE ON usuarios FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_mesas_updated_at BEFORE UPDATE ON mesas FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_pedido_updated_at BEFORE UPDATE ON pedido FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_tenants_updated_at BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Add comments for documentation
COMMENT ON COLUMN produtos.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN categorias.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN filiais.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN usuarios.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN mesas.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN pedido.updated_at IS 'Automatically updated timestamp for record modifications';
COMMENT ON COLUMN tenants.updated_at IS 'Automatically updated timestamp for record modifications';
