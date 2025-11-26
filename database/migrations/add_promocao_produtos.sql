-- Add promotional fields to produtos table
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_promocional DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS em_promocao BOOLEAN DEFAULT false;

-- Add comment
COMMENT ON COLUMN produtos.preco_promocional IS 'Preço promocional do produto. Se definido e em_promocao = true, será usado no lugar do preco_normal';
COMMENT ON COLUMN produtos.em_promocao IS 'Indica se o produto está em promoção. Se true e preco_promocional definido, exibe o preço promocional';

