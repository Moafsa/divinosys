-- Insert default plans
INSERT INTO planos (nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, recursos, preco_mensal) VALUES
('Starter', 5, 2, 50, 500, '{"relatorios_basicos": true, "suporte_email": true}', 29.90),
('Professional', 15, 5, 200, 2000, '{"relatorios_avancados": true, "integracao_ifood": true, "suporte_prioritario": true, "backup_automatico": true}', 79.90),
('Enterprise', -1, -1, -1, -1, '{"relatorios_customizados": true, "api_acesso": true, "suporte_dedicado": true, "white_label": true, "integracoes_avancadas": true}', 199.90);

-- Insert default tenant
INSERT INTO tenants (id, nome, subdomain, domain, cnpj, telefone, email, endereco, cor_primaria, status, plano_id) VALUES
(1, 'Divino Lanches', 'divino', 'divinolanches.com', '12345678000199', '(11) 99999-9999', 'contato@divinolanches.com', 'Rua das Flores, 123', '#28a745', 'ativo', 2);

-- Insert default filial
INSERT INTO filiais (tenant_id, nome, endereco, telefone, email, cnpj, status) VALUES
(1, 'Matriz', 'Rua das Flores, 123', '(11) 99999-9999', 'contato@divinolanches.com', '12345678000199', 'ativo');

-- Insert default admin user
INSERT INTO usuarios (login, senha, nivel, pergunta, resposta, tenant_id, filial_id) VALUES
('admin', '$2y$10$bOYm96HqCJ4p7lcazLBpuO0JllFT6UE8PIC/N2qbHgIzenqKqB2WK', 1, 'admin', 'admin', 1, 1);

-- Insert default categories
INSERT INTO categorias (nome, tenant_id, filial_id) VALUES
('XIS', 1, 1),
('Cachorro-Quente', 1, 1),
('Bauru', 1, 1),
('PF e A La Minuta', 1, 1),
('Torrada', 1, 1),
('Rodízio', 1, 1),
('Porções', 1, 1),
('Bebidas', 1, 1),
('Bebidas Alcoólicas', 1, 1),
('Combo', 1, 1);

-- Insert default ingredients
INSERT INTO ingredientes (nome, tipo, preco_adicional, disponivel, tenant_id, filial_id) VALUES
('Pão de Xis', 'pao', 0.00, true, 1, 1),
('Pão de Hot Dog', 'pao', 0.00, true, 1, 1),
('Hambúrguer', 'proteina', 0.00, true, 1, 1),
('Coração de Frango', 'proteina', 0.00, true, 1, 1),
('Filé', 'proteina', 0.00, true, 1, 1),
('Frango', 'proteina', 0.00, true, 1, 1),
('Calabresa', 'proteina', 0.00, true, 1, 1),
('Bacon', 'proteina', 0.00, true, 1, 1),
('Salsicha', 'proteina', 0.00, true, 1, 1),
('Salsicha Vegetariana', 'proteina', 0.00, true, 1, 1),
('Patinho', 'proteina', 0.00, true, 1, 1),
('Alcatra', 'proteina', 0.00, true, 1, 1),
('Coxão Mole', 'proteina', 0.00, true, 1, 1),
('Queijo', 'queijo', 0.00, true, 1, 1),
('Queijo Ralado', 'queijo', 0.00, true, 1, 1),
('Queijo Cheddar', 'queijo', 0.00, true, 1, 1),
('Alface', 'salada', 0.00, true, 1, 1),
('Tomate', 'salada', 0.00, true, 1, 1),
('Cebola', 'salada', 0.00, true, 1, 1),
('Rúcula', 'salada', 0.00, true, 1, 1),
('Tomate Seco', 'salada', 0.00, true, 1, 1),
('Palmito', 'salada', 0.00, true, 1, 1),
('Pepino', 'salada', 0.00, true, 1, 1),
('Salada Mista', 'salada', 0.00, true, 1, 1),
('Maionese', 'molho', 0.00, true, 1, 1),
('Molho', 'molho', 0.00, true, 1, 1),
('Ovo', 'complemento', 0.00, true, 1, 1),
('Presunto', 'complemento', 0.00, true, 1, 1),
('Milho', 'complemento', 0.00, true, 1, 1),
('Ervilha', 'complemento', 0.00, true, 1, 1),
('Batata Palha', 'complemento', 0.00, true, 1, 1),
('Batata Frita', 'complemento', 0.00, true, 1, 1),
('Arroz', 'complemento', 0.00, true, 1, 1),
('Feijão', 'complemento', 0.00, true, 1, 1),
('Azeitona', 'complemento', 0.00, true, 1, 1),
('Ovo de Codorna', 'complemento', 0.00, true, 1, 1),
('Polenta', 'complemento', 0.00, true, 1, 1);

-- Insert default products
INSERT INTO produtos (codigo, categoria_id, nome, descricao, preco_normal, preco_mini, tenant_id, filial_id) VALUES
('1001', 1, 'XIS DA CASA', 'Pão, hambúrguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 29.00, 26.00, 1, 1),
('1002', 1, 'XIS CORAÇÃO', 'Pão, coração de frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 34.00, 29.00, 1, 1),
('1003', 1, 'XIS DUPLO', 'Pão, 2 hambúrgueres, 2 ovos, 2 presuntos, queijos, milho, ervilha, alface, tomate, maionese', 35.00, 30.00, 1, 1),
('1004', 1, 'XIS CALABRESA', 'Pão, hambúrguer, calabresa, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 30.00, 26.00, 1, 1),
('1005', 1, 'XIS BACON', 'Pão, hambúrguer, bacon, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese', 34.00, 30.00, 1, 1),
('2001', 2, 'CACHORRO-QUENTE SIMPLES', 'Pão, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha', 21.00, NULL, 1, 1),
('2002', 2, 'CACHORRO-QUENTE DUPLO', 'Pão, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha', 23.00, NULL, 1, 1),
('8001', 8, 'ÁGUA MINERAL', NULL, 5.00, NULL, 1, 1),
('8002', 8, 'ÁGUA TÔNICA (LATA)', NULL, 6.00, NULL, 1, 1),
('8003', 8, 'H2O 500ML', NULL, 7.00, NULL, 1, 1);

-- Insert default tables (25 tables)
INSERT INTO mesas (id_mesa, nome, status, tenant_id, filial_id) VALUES
('1', '', '1', 1, 1),
('2', '', '1', 1, 1),
('3', '', '1', 1, 1),
('4', '', '1', 1, 1),
('5', '', '1', 1, 1),
('6', '', '1', 1, 1),
('7', '', '1', 1, 1),
('8', '', '1', 1, 1),
('9', '', '1', 1, 1),
('10', '', '1', 1, 1),
('11', '', '1', 1, 1),
('12', '', '1', 1, 1),
('13', '', '1', 1, 1),
('14', '', '1', 1, 1),
('15', '', '1', 1, 1),
('16', '', '1', 1, 1),
('17', '', '1', 1, 1),
('18', '', '1', 1, 1),
('19', '', '1', 1, 1),
('20', '', '1', 1, 1),
('21', NULL, '1', 1, 1),
('22', NULL, '1', 1, 1),
('23', NULL, '1', 1, 1),
('24', NULL, '1', 1, 1),
('25', NULL, '1', 1, 1);

-- Insert default financial categories
INSERT INTO categorias_financeiras (nome, tipo, descricao, tenant_id, filial_id) VALUES
('Vendas', 'receita', 'Receitas provenientes de vendas', 1, 1),
('Serviços', 'receita', 'Receitas provenientes de serviços', 1, 1),
('Aluguel', 'despesa', 'Despesas com aluguel', 1, 1),
('Salários', 'despesa', 'Despesas com salários', 1, 1),
('Fornecedores', 'despesa', 'Despesas com fornecedores', 1, 1),
('Impostos', 'despesa', 'Despesas com impostos', 1, 1),
('Manutenção', 'despesa', 'Despesas com manutenção', 1, 1),
('Outros', 'receita', 'Outras receitas', 1, 1),
('Outros', 'despesa', 'Outras despesas', 1, 1);

-- Insert default financial account
INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, tenant_id, filial_id) VALUES
('Caixa', 'carteira', 0.00, 0.00, 1, 1);

-- Insert default establishment profile
INSERT INTO perfil_estabelecimento (nome_estabelecimento, cnpj, endereco, telefone, site, mensagem_header, tenant_id, filial_id) VALUES
('Divino Lanches', '12345678000199', 'Rua das Flores, 123', '(11) 99999-9999', 'www.divinolanches.com', 'Bem-vindo ao Divino Lanches!', 1, 1);

-- Insert default color
INSERT INTO cor (id, cor, tenant_id) VALUES
(1, 'info', 1);

-- Insert product ingredients relationships
INSERT INTO produto_ingredientes (produto_id, ingrediente_id, padrao) VALUES
-- XIS DA CASA
(1, 1, true), (1, 3, true), (1, 14, true), (1, 17, true), (1, 18, true), (1, 25, true), (1, 27, true), (1, 28, true), (1, 29, true), (1, 30, true),
-- XIS CORAÇÃO
(2, 1, true), (2, 4, true), (2, 14, true), (2, 17, true), (2, 18, true), (2, 25, true), (2, 27, true), (2, 28, true), (2, 29, true), (2, 30, true),
-- XIS DUPLO
(3, 1, true), (3, 3, true), (3, 14, true), (3, 17, true), (3, 18, true), (3, 25, true), (3, 27, true), (3, 28, true), (3, 29, true), (3, 30, true),
-- XIS CALABRESA
(4, 1, true), (4, 3, true), (4, 7, true), (4, 14, true), (4, 17, true), (4, 18, true), (4, 25, true), (4, 27, true), (4, 28, true), (4, 29, true), (4, 30, true),
-- XIS BACON
(5, 1, true), (5, 3, true), (5, 8, true), (5, 14, true), (5, 17, true), (5, 18, true), (5, 25, true), (5, 27, true), (5, 28, true), (5, 29, true), (5, 30, true),
-- CACHORRO-QUENTE SIMPLES
(6, 2, true), (6, 9, true), (6, 15, true), (6, 25, true), (6, 26, true), (6, 29, true), (6, 30, true), (6, 31, true),
-- CACHORRO-QUENTE DUPLO
(7, 2, true), (7, 9, true), (7, 15, true), (7, 25, true), (7, 26, true), (7, 29, true), (7, 30, true), (7, 31, true);

-- Insert default stock
INSERT INTO estoque (produto_id, estoque_atual, estoque_minimo, tenant_id, filial_id) VALUES
(1, 10.00, 5.00, 1, 1),
(2, 10.00, 5.00, 1, 1),
(3, 10.00, 5.00, 1, 1),
(4, 10.00, 5.00, 1, 1),
(5, 10.00, 5.00, 1, 1),
(6, 10.00, 5.00, 1, 1),
(7, 10.00, 5.00, 1, 1),
(8, 50.00, 10.00, 1, 1),
(9, 50.00, 10.00, 1, 1),
(10, 50.00, 10.00, 1, 1);
