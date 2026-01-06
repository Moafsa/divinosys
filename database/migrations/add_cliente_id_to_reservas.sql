-- Add cliente_id column to reservas table
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS cliente_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL;

-- Create index for better query performance
CREATE INDEX IF NOT EXISTS idx_reservas_cliente ON reservas(cliente_id);

-- Add comment
COMMENT ON COLUMN reservas.cliente_id IS 'ID do cliente em usuarios_globais vinculado à reserva';













