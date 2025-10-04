-- Insert essential data for Coolify deployment
-- This creates the default tenant, user, and basic data

-- Insert plans
INSERT INTO planos (id, nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, recursos, preco_mensal, created_at) VALUES 
(1, 'Plano Básico', 10, 3, 100, 1000, '{"delivery": true, "relatorios": false, "multi_filiais": false}', 49.90, CURRENT_TIMESTAMP),
(2, 'Plano Profissional', 50, 10, 500, 5000, '{"delivery": true, "relatorios": true, "multi_filiais": true}', 99.90, CURRENT_TIMESTAMP),
(3, 'Plano Empresarial', 200, 50, 2000, 20000, '{"delivery": true, "relatorios": true, "multi_filiais": true, "api": true}', 199.90, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert default tenant
INSERT INTO tenants (id, nome, subdomain, domain, cnpj, telefone, email, endereco, cor_primaria, status, plano_id, created_at, updated_at) VALUES 
(1, 'Divino Lanches', 'divino', 'divinolanches.com.br', '12.345.678/0001-90', '(11) 99999-9999', 'contato@divinolanches.com.br', 'Rua das Flores, 123 - Centro', '#007bff', 'ativo', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert default branch
INSERT INTO filiais (id, tenant_id, nome, endereco, telefone, email, cnpj, status, created_at, updated_at) VALUES 
(1, 1, 'Filial Principal', 'Rua das Flores, 123', '(11) 99999-9999', 'contato@divinolanches.com', '12.345.678/0001-90', 'ativo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert admin user with working password hash for admin123
INSERT INTO usuarios (id, login, senha, nivel, pergunta, resposta, tenant_id, filial_id, created_at, updated_at) VALUES 
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Qual é o nome da sua mãe?', 'Maria', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO UPDATE SET senha = EXCLUDED.senha;

-- Insert default categories
INSERT INTO categorias (id, nome, created_at, tenant_id, filial_id, updated_at) VALUES 
(1, 'Lanches', CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(2, 'Bebidas', CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(3, 'Sobremesas', CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert default ingredients
INSERT INTO ingredientes (id, nome, tipo, preco_adicional, disponivel, created_at, tenant_id, filial_id, updated_at) VALUES 
(1, 'Pão Francês', 'pao', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(2, 'Pão de Hambúrguer', 'pao', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(3, 'Hambúrguer de Carne', 'proteina', 8.50, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(4, 'Frango Grelhado', 'proteina', 7.50, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(5, 'Queijo Cheddar', 'queijo', 2.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(6, 'Queijo Mussarela', 'queijo', 1.50, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(7, 'Alface', 'salada', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(8, 'Tomate', 'salada', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(9, 'Ketchup', 'molho', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(10, 'Mostarda', 'molho', 0.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(11, 'Bacon', 'complemento', 3.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(12, 'Cebola Roxa', 'complemento', 1.00, true, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert default products
INSERT INTO produtos (id, codigo, categoria_id, nome, descricao, preco_normal, preco_mini, created_at, tenant_id, filial_id, updated_at) VALUES 
(1, 'LCH001', 1, 'X-Burger', 'Hambúrguer de carne com queijo, alface e tomate', 15.90, 12.90, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(2, 'LCH002', 1, 'X-Salada', 'Hambúrguer de carne com queijo, alface, tomate e cebola', 17.90, 14.90, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(3, 'LCH003', 1, 'X-Bacon', 'Hambúrguer de carne com queijo, bacon, alface e tomate', 19.90, 16.90, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(4, 'LCH004', 1, 'X-Frango', 'Hambúrguer de frango com queijo, alface e tomate', 16.90, 13.90, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(5, 'BEB001', 2, 'Coca-Cola 350ml', 'Refrigerante Coca-Cola lata', 4.50, NULL, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(6, 'BEB002', 2, 'Suco de Laranja 300ml', 'Suco natural de laranja', 6.50, NULL, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP),
(7, 'SOB001', 3, 'Sorvete de Chocolate', 'Sorvete de chocolate com calda', 8.90, NULL, CURRENT_TIMESTAMP, 1, 1, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert product ingredients
INSERT INTO produto_ingredientes (id, produto_id, ingrediente_id, obrigatorio, created_at) VALUES 
(1, 1, 2, false, CURRENT_TIMESTAMP),
(2, 1, 3, false, CURRENT_TIMESTAMP),
(3, 1, 5, false, CURRENT_TIMESTAMP),
(4, 1, 7, false, CURRENT_TIMESTAMP),
(5, 1, 9, false, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert default tables
INSERT INTO mesas (id, numero, capacidade, status, tenant_id, filial_id, created_at, updated_at) VALUES 
(1, 1, 4, 'livre', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 2, 4, 'livre', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 3, 6, 'livre', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(4, 4, 4, 'livre', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(5, 5, 2, 'livre', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Reset sequences to correct values
SELECT setval('tenants_id_seq', 1, true);
SELECT setval('planos_id_seq', 3, true);
SELECT setval('filiais_id_seq', 1, true);
SELECT setval('usuarios_id_seq', 1, true);
SELECT setval('categorias_id_seq', 3, true);
SELECT setval('ingredientes_id_seq', 12, true);
SELECT setval('produtos_id_seq', 7, true);
SELECT setval('mesas_id_seq', 5, true);
SELECT setval('pedidos_idpedido_seq', 1, true);
SELECT setval('pedido_itens_id_seq', 1, true);
