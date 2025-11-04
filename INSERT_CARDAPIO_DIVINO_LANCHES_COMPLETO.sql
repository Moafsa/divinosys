-- ============================================
-- CARDÁPIO COMPLETO DIVINO LANCHES
-- Tenant ID: 5 (MOACIR FERREIRA DOS SANTOS)
-- Filial ID: 4
-- ============================================
-- IMPORTANTE: Execute este SQL apenas UMA VEZ
-- ============================================

BEGIN;

-- ============================================
-- 1. CRIAR CATEGORIAS (10 categorias)
-- ============================================
INSERT INTO categorias (nome, descricao, tenant_id, filial_id, status, created_at, updated_at) VALUES
('XIS', 'Sanduíches XIS', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Cachorro-Quente', 'Cachorros-quentes', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('PF e À La Minuta', 'Pratos feitos e à la minuta', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Bauru', 'Pratos de Bauru', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Torrada', 'Torradas', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Rodízio', 'Rodízio de carnes', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Porções', 'Porções e petiscos', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Bebidas', 'Bebidas diversas', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Doces', 'Doces e chocolates', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('ADICIONAIS', 'Adicionais e complementos avulsos', 5, 4, 'Ativa', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- ============================================
-- 2. CRIAR INGREDIENTES (37 ingredientes)
-- ============================================
-- Tipos permitidos: 'pao', 'proteina', 'queijo', 'salada', 'molho', 'complemento'

DO $$
BEGIN
    INSERT INTO ingredientes (nome, tipo, preco_adicional, disponivel, tenant_id, filial_id, created_at) VALUES
    -- Pães (3)
    ('Pão de Xis', 'pao', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Pão de Hot Dog', 'pao', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Pão Torrado', 'pao', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    
    -- Proteínas (10)
    ('Hambúrguer', 'proteina', 5.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Coração de Frango', 'proteina', 7.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Calabresa', 'proteina', 6.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Bacon', 'proteina', 6.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Filé', 'proteina', 15.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Frango', 'proteina', 6.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Salsicha', 'proteina', 3.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Patinho', 'proteina', 10.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Alcatra', 'proteina', 15.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Coxão Mole', 'proteina', 12.00, true, 5, 4, CURRENT_TIMESTAMP),
    
    -- Queijos (3)
    ('Queijo Fatiado', 'queijo', 3.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Queijo Ralado', 'queijo', 1.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Cheddar', 'queijo', 4.00, true, 5, 4, CURRENT_TIMESTAMP),
    
    -- Saladas (6)
    ('Alface', 'salada', 0.50, true, 5, 4, CURRENT_TIMESTAMP),
    ('Tomate', 'salada', 0.50, true, 5, 4, CURRENT_TIMESTAMP),
    ('Pepino', 'salada', 0.50, true, 5, 4, CURRENT_TIMESTAMP),
    ('Rúcula', 'salada', 2.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Cebola', 'salada', 0.50, true, 5, 4, CURRENT_TIMESTAMP),
    ('Salada Mista', 'salada', 3.00, true, 5, 4, CURRENT_TIMESTAMP),
    
    -- Molhos (2)
    ('Maionese', 'molho', 0.50, true, 5, 4, CURRENT_TIMESTAMP),
    ('Molho Especial', 'molho', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    
    -- Complementos (13)
    ('Ovo', 'complemento', 2.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Presunto', 'complemento', 3.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Milho', 'complemento', 1.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Ervilha', 'complemento', 1.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Palmito', 'complemento', 4.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Tomate Seco', 'complemento', 3.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Batata Palha', 'complemento', 1.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Arroz', 'complemento', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Feijão', 'complemento', 0.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Batata Frita', 'complemento', 5.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Massa', 'complemento', 8.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Polenta', 'complemento', 5.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Azeitona', 'complemento', 2.00, true, 5, 4, CURRENT_TIMESTAMP),
    ('Ovo de Codorna', 'complemento', 1.50, true, 5, 4, CURRENT_TIMESTAMP)
    ON CONFLICT DO NOTHING;
END $$;

-- ============================================
-- 3. CRIAR PRODUTOS (71 produtos total)
-- ============================================
DO $$
DECLARE
    cat_xis_id INTEGER;
    cat_cachorro_id INTEGER;
    cat_pf_id INTEGER;
    cat_bauru_id INTEGER;
    cat_torrada_id INTEGER;
    cat_rodizio_id INTEGER;
    cat_porcoes_id INTEGER;
    cat_bebidas_id INTEGER;
    cat_doces_id INTEGER;
BEGIN
    -- Buscar IDs das categorias
    SELECT id INTO cat_xis_id FROM categorias WHERE nome = 'XIS' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_cachorro_id FROM categorias WHERE nome = 'Cachorro-Quente' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_pf_id FROM categorias WHERE nome = 'PF e À La Minuta' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_bauru_id FROM categorias WHERE nome = 'Bauru' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_torrada_id FROM categorias WHERE nome = 'Torrada' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_rodizio_id FROM categorias WHERE nome = 'Rodízio' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_porcoes_id FROM categorias WHERE nome = 'Porções' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_bebidas_id FROM categorias WHERE nome = 'Bebidas' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;
    SELECT id INTO cat_doces_id FROM categorias WHERE nome = 'Doces' AND tenant_id = 5 AND filial_id = 4 LIMIT 1;

    -- Inserir produtos
    INSERT INTO produtos (codigo, categoria_id, nome, descricao, preco, preco_promocional, created_at, tenant_id, filial_id, updated_at) VALUES
    -- XIS (14 produtos)
    ('XIS001', cat_xis_id, 'XIS DA CASA', 'Pão, hambúrguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 30.00, 27.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS001P', cat_xis_id, 'PROMO XIS DA CASA', 'Pão, hambúrguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 30.00, 24.99, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS002', cat_xis_id, 'XIS CORAÇÃO', 'Pão, coração de frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 35.00, 30.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS003', cat_xis_id, 'XIS DUPLO', 'Pão, 2 hambúrgueres, 2 ovos, 2 presuntos, 2 queijos, milho, ervilha, alface, tomate, maionese', 37.00, 32.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS004', cat_xis_id, 'XIS CALABRESA', 'Pão, hambúrguer, calabresa, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 35.00, 30.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS005', cat_xis_id, 'XIS BACON', 'Pão, hambúrguer, bacon, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 36.00, 31.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS006', cat_xis_id, 'XIS VEGETARIANO', 'Pão, alface, tomate, queijo, palmito, pepino, milho, ervilha, maionese', 30.00, 26.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS007', cat_xis_id, 'XIS FILÉ', 'Pão, filé, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 44.00, 37.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS008', cat_xis_id, 'XIS CEBOLA', 'Pão, hambúrguer, cebola, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 34.00, 30.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS009', cat_xis_id, 'XIS FRANGO', 'Pão, frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 35.00, 30.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS010', cat_xis_id, 'XIS TOMATE SECO COM RÚCULA', 'Pão, filé, rúcula, tomate seco, ovo, presunto, queijo, milho, ervilha, maionese', 45.00, 39.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS011', cat_xis_id, 'XIS ENTREVERO', 'Pão, calabresa, coração, carne, frango, bacon, cebola, ovo, queijo, presunto, alface, tomate, milho, ervilha, maionese', 42.00, 37.00, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('XIS012', cat_xis_id, 'XIS CHAPA', 'Pão, hambúrguer na chapa, ovo, presunto, queijo, alface, tomate, maionese', 28.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Cachorro-Quente (4 produtos)
    ('HOT001', cat_cachorro_id, 'CACHORRO-QUENTE SIMPLES', 'Pão, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha', 23.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('HOT001P', cat_cachorro_id, 'PROMO CACHORRO QUENTE SIMPLES', 'Pão, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha', 23.00, 13.99, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('HOT002', cat_cachorro_id, 'CACHORRO-QUENTE DUPLO', 'Pão, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha', 25.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('HOT002P', cat_cachorro_id, 'PROMO CACHORRO QUENTE DUPLO', 'Pão, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha', 25.00, 15.99, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- PF e À La Minuta (7 produtos)
    ('PF001', cat_pf_id, 'PRATO FEITO DA CASA', 'Patinho, arroz, feijão, batata frita, ovo, salada mista e pão', 32.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('PF002', cat_pf_id, 'PRATO FEITO FILÉ', 'Filé, arroz, feijão, batata frita, ovo, salada mista e pão', 48.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('PF003', cat_pf_id, 'PRATO FEITO COXÃO MOLE', 'Coxão mole, arroz, feijão, batata frita, ovo, salada mista e pão', 40.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('PF004', cat_pf_id, 'PRATO FEITO A MILANESA', 'Bife à milanesa, arroz, feijão, batata frita, ovo, salada mista e pão', 40.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('PF005', cat_pf_id, 'PRATO FEITO KIDS', 'Porção infantil com arroz, feijão, carne e batata', 20.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('ALM001', cat_pf_id, 'À LA MINUTA ALCATRA', 'Bife de alcatra, arroz, feijão, batata frita, ovo, salada mista e pão', 48.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('ALM002', cat_pf_id, 'À LA MINUTA FILÉ', 'Bife de filé, arroz, salada e batata palha ou batata frita', 52.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Bauru (6 produtos)
    ('BAU001', cat_bauru_id, '1/4 BAURU FILÉ (1 PESSOA)', 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz', 65.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BAU002', cat_bauru_id, '1/2 BAURU FILÉ (2 PESSOAS)', 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz', 115.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BAU003', cat_bauru_id, 'BAURU FILÉ (4 PESSOAS)', 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz', 190.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BAU004', cat_bauru_id, '1/4 BAURU ALCATRA (1 PESSOA)', 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz', 60.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BAU005', cat_bauru_id, '1/2 BAURU ALCATRA (2 PESSOAS)', 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz', 100.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BAU006', cat_bauru_id, 'BAURU ALCATRA (4 PESSOAS)', 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz', 175.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Torrada (2 produtos)
    ('TOR001', cat_torrada_id, 'TORRADA AMERICANA', 'Pão de xis, tomate, alface, maionese, 2 fatias de presunto, 2 fatias de queijo e ovo', 26.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('TOR002', cat_torrada_id, 'TORRADA COM BACON', '3 pães, bacon, 2 fatias de presunto, 4 fatias de queijo, alface, tomate e maionese', 30.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Rodízio (3 produtos)
    ('ROD001', cat_rodizio_id, 'RODÍZIO DE BIFES', 'Bife de gado, frango e porco, bauru, arroz, batata frita, massa, salada e pão', 69.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('ROD002', cat_rodizio_id, 'ESPETO CORRIDO ADULTO', 'Espetos variados de carne, acompanhamentos e saladas', 75.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('ROD003', cat_rodizio_id, 'ESPETO CORRIDO CRIANÇA', 'Espetos variados porção infantil com acompanhamentos', 35.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Porções (13 produtos)
    ('POR001', cat_porcoes_id, 'TÁBUA DE FRIOS PEQUENA', 'Azeitona, queijo, palmito, pepino, pão torrado, ovo de codorna e filé', 62.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR002', cat_porcoes_id, 'TÁBUA DE FRIOS MÉDIA', 'Azeitona, queijo, palmito, pepino, pão torrado, ovo de codorna e filé', 100.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR003', cat_porcoes_id, 'TÁBUA DE FRIOS GRANDE', 'Carnes (frango e gado), batata, polenta, queijo, ovo de codorna e cebola', 115.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR004', cat_porcoes_id, 'BATATA FRITA PEQUENA (200G)', 'Batata frita', 20.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR005', cat_porcoes_id, 'BATATA FRITA PEQUENA COM CHEDDAR E BACON', 'Batata frita com cheddar e bacon', 35.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR006', cat_porcoes_id, 'BATATA FRITA GRANDE (400G)', 'Batata frita', 35.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR007', cat_porcoes_id, 'BATATA FRITA GRANDE COM CHEDDAR E BACON', 'Batata frita com cheddar e bacon', 45.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR008', cat_porcoes_id, 'POLENTA FRITA (500G)', 'Polenta frita', 25.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR009', cat_porcoes_id, 'QUEIJO FRITO UN', 'Queijo empanado frito', 4.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR010', cat_porcoes_id, 'BATATA, POLENTA E QUEIJO', 'Batata frita, polenta frita e queijo', 45.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR011', cat_porcoes_id, 'PORÇÃO DE QUEIJO P', 'Porção pequena de queijo empanado', 12.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR012', cat_porcoes_id, 'PORÇÃO DE QUEIJO M', 'Porção média de queijo empanado', 22.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('POR013', cat_porcoes_id, 'PORÇÃO DE QUEIJO G', 'Porção grande de queijo empanado', 32.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Bebidas (27 produtos)
    ('BEB001', cat_bebidas_id, 'ÁGUA MINERAL', 'Água mineral', 5.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB002', cat_bebidas_id, 'ÁGUA TÔNICA', 'Água tônica', 7.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB003', cat_bebidas_id, 'H2O 500ML', 'H2O 500ml', 9.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB004', cat_bebidas_id, 'H2O 1,5L', 'H2O 1,5 litros', 12.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB005', cat_bebidas_id, 'REFRI KS', 'Refrigerante KS', 7.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB006', cat_bebidas_id, 'FRUKI LATA', 'Refrigerante Fruki lata', 7.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB007', cat_bebidas_id, 'REFRIGERANTE (LATA)', 'Refrigerante lata', 8.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB008', cat_bebidas_id, 'REFRIGERANTE 600ML', 'Refrigerante 600ml', 8.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB009', cat_bebidas_id, 'FRUKI 600 ML', 'Refrigerante Fruki 600ml', 8.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB010', cat_bebidas_id, 'REFRIGERANTE 1L', 'Refrigerante 1 litro', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB011', cat_bebidas_id, 'SUCO LATA', 'Suco em lata', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB012', cat_bebidas_id, 'SUCO NATURAL', 'Suco natural', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB013', cat_bebidas_id, 'DOSE', 'Dose de bebida', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB014', cat_bebidas_id, 'CERVEJA', 'Cerveja', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB015', cat_bebidas_id, 'ENERGÉTICO', 'Energético', 12.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB016', cat_bebidas_id, 'ENERGETICO RED BULL', 'Red Bull', 14.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB017', cat_bebidas_id, 'DOSES', 'Doses de bebida', 15.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB018', cat_bebidas_id, 'CERVEJA 600 ML ORIGINAL, AMESTEL', 'Cerveja 600ml Original ou Amstel', 15.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB019', cat_bebidas_id, 'TAÇA DE VINHO', 'Taça de vinho', 15.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB020', cat_bebidas_id, 'CAIPIRA PEQUENA', 'Caipirinha pequena', 15.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB021', cat_bebidas_id, 'CERVEJA 1L', 'Cerveja 1 litro', 18.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB022', cat_bebidas_id, 'REFRIGERANTE 2L', 'Refrigerante 2 litros', 18.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB023', cat_bebidas_id, 'COCA-COLA 2L', 'Coca-Cola 2 litros', 18.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB024', cat_bebidas_id, 'CAIPIRA GRANDE', 'Caipirinha grande', 25.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('BEB025', cat_bebidas_id, 'JARRA DE VINHO', 'Jarra de vinho', 45.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    
    -- Doces (6 produtos)
    ('DOC001', cat_doces_id, 'BOMBOM', 'Bombom unitário', 2.50, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('DOC002', cat_doces_id, 'MENTOS', 'Mentos', 4.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('DOC003', cat_doces_id, 'CHOCOLATES PEQUENOS', 'Chocolates pequenos variados', 4.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('DOC004', cat_doces_id, 'TRUFAS', 'Trufas', 5.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('DOC005', cat_doces_id, 'CHOCOLATE TALENTO GRANDE', 'Chocolate Talento grande', 10.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP),
    ('DOC006', cat_doces_id, 'BARRA DE CHOCOLATE', 'Barra de chocolate', 12.00, NULL, CURRENT_TIMESTAMP, 5, 4, CURRENT_TIMESTAMP)
    ON CONFLICT DO NOTHING;
    
END $$;

-- ============================================
-- 4. VINCULAR INGREDIENTES AOS PRODUTOS XIS
-- ============================================
DO $$
DECLARE
    v_produto_id INTEGER;
    v_ingrediente_id INTEGER;
BEGIN
    -- Função auxiliar inline para vincular ingredientes
    -- XIS DA CASA
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS001' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS CORAÇÃO
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS002' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Coração de Frango', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS DUPLO
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS003' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS CALABRESA
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS004' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Calabresa', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS BACON
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS005' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Bacon', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS VEGETARIANO
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS006' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Alface', 'Tomate', 'Queijo Fatiado', 'Palmito', 'Pepino', 'Milho', 'Ervilha', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS FILÉ
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS007' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Filé', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS CEBOLA
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS008' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Cebola', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS FRANGO
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS009' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Frango', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS TOMATE SECO COM RÚCULA
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS010' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Filé', 'Rúcula', 'Tomate Seco', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Milho', 'Ervilha', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS ENTREVERO
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS011' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Calabresa', 'Coração de Frango', 'Hambúrguer', 'Frango', 'Bacon', 'Cebola', 'Ovo', 'Queijo Fatiado', 'Presunto', 'Alface', 'Tomate', 'Milho', 'Ervilha', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;
    
    -- XIS CHAPA
    SELECT id INTO v_produto_id FROM produtos WHERE codigo = 'XIS012' AND tenant_id = 5 LIMIT 1;
    
    INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional, padrao, tenant_id, filial_id, created_at)
    SELECT v_produto_id, id, true, 0.00, true, 5, 4, CURRENT_TIMESTAMP
    FROM ingredientes 
    WHERE nome IN ('Pão de Xis', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo Fatiado', 'Alface', 'Tomate', 'Maionese')
    AND tenant_id = 5 AND filial_id = 4
    ON CONFLICT DO NOTHING;

END $$;

COMMIT;

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================
SELECT 
    c.nome as categoria,
    COUNT(p.id) as total_produtos
FROM categorias c
LEFT JOIN produtos p ON c.id = p.categoria_id
WHERE c.tenant_id = 5 AND c.filial_id = 4
GROUP BY c.nome
ORDER BY c.nome;

SELECT COUNT(*) as total_ingredientes FROM ingredientes WHERE tenant_id = 5 AND filial_id = 4;
SELECT COUNT(*) as total_vinculos FROM produto_ingredientes WHERE tenant_id = 5 AND filial_id = 4;

-- ============================================
-- RESUMO ESPERADO:
-- ============================================
-- CATEGORIAS: 10
--   1. XIS - Sanduíches XIS
--   2. Cachorro-Quente - Cachorros-quentes
--   3. PF e À La Minuta - Pratos feitos e à la minuta
--   4. Bauru - Pratos de Bauru
--   5. Torrada - Torradas
--   6. Rodízio - Rodízio de carnes
--   7. Porções - Porções e petiscos
--   8. Bebidas - Bebidas diversas
--   9. Doces - Doces e chocolates
--  10. ADICIONAIS - Adicionais e complementos avulsos
--
-- PRODUTOS POR CATEGORIA:
--   - XIS: 13 (XIS DA CASA, PROMO XIS DA CASA, CORAÇÃO, DUPLO, CALABRESA, BACON, VEGETARIANO, FILÉ, CEBOLA, FRANGO, TOMATE SECO, ENTREVERO, CHAPA)
--   - Cachorro-Quente: 4 (SIMPLES, PROMO SIMPLES, DUPLO, PROMO DUPLO)
--   - PF e À La Minuta: 7 (DA CASA, FILÉ, COXÃO MOLE, A MILANESA, KIDS, À LA MINUTA ALCATRA, À LA MINUTA FILÉ)
--   - Bauru: 6 (1/4 FILÉ, 1/2 FILÉ, FILÉ 4P, 1/4 ALCATRA, 1/2 ALCATRA, ALCATRA 4P)
--   - Torrada: 2 (AMERICANA, COM BACON)
--   - Rodízio: 3 (BIFES, ESPETO ADULTO, ESPETO CRIANÇA)
--   - Porções: 13 (TÁBUA P/M/G, BATATA P/G, BATATA CHEDDAR P/G, POLENTA, QUEIJO UN, BATATA+POLENTA+QUEIJO, QUEIJO P/M/G)
--   - Bebidas: 25 (ÁGUA, H2O, REFRIS, FRUKI, COCA, SUCOS, CERVEJAS, ENERGÉTICOS, DOSES, VINHOS, CAIPIRA)
--   - Doces: 6 (BOMBOM, MENTOS, CHOCOLATES, TRUFAS, TALENTO, BARRA)
--   - ADICIONAIS: 0 (categoria vazia para adicionar itens avulsos depois)
--
-- TOTAL PRODUTOS: 73
-- TOTAL INGREDIENTES: 37
-- TOTAL VÍNCULOS: ~130 (apenas produtos XIS)
-- ============================================

-- ============================================
-- COMO EXECUTAR:
-- ============================================
-- OPÇÃO 1 - Portainer/Docker:
--   1. Copie este arquivo para o container
--   2. docker cp INSERT_CARDAPIO_DIVINO_LANCHES_COMPLETO.sql <container-postgres>:/tmp/
--   3. docker exec <container-postgres> psql -U divino_user -d divino_db -f /tmp/INSERT_CARDAPIO_DIVINO_LANCHES_COMPLETO.sql
--
-- OPÇÃO 2 - Console PostgreSQL:
--   1. Acesse console do container postgres
--   2. psql -U divino_user -d divino_db
--   3. Cole este arquivo completo
--   4. Pressione Enter
-- ============================================

