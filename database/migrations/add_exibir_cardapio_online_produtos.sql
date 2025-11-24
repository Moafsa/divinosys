-- Add exibir_cardapio_online field to produtos table
-- This field controls if the product should be displayed on the online menu

ALTER TABLE produtos ADD COLUMN IF NOT EXISTS exibir_cardapio_online BOOLEAN DEFAULT true;

-- Update existing products to show in menu by default
UPDATE produtos SET exibir_cardapio_online = true WHERE exibir_cardapio_online IS NULL;

-- Add comment
COMMENT ON COLUMN produtos.exibir_cardapio_online IS 'Controls if product should be displayed on online menu page';

