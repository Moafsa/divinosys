-- Add pedido_id column to notas_fiscais table if it doesn't exist
-- This ensures the column is added in new deployments

-- Check if column exists and add if missing
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'notas_fiscais' 
        AND column_name = 'pedido_id'
    ) THEN
        ALTER TABLE notas_fiscais 
        ADD COLUMN pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL;
        
        -- Add comment
        COMMENT ON COLUMN notas_fiscais.pedido_id IS 'Reference to the order this invoice belongs to';
        
        RAISE NOTICE 'Column pedido_id added to notas_fiscais table';
    ELSE
        RAISE NOTICE 'Column pedido_id already exists in notas_fiscais table';
    END IF;
END $$;
