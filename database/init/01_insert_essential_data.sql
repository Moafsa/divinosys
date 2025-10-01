-- Insert essential data for system to work
-- Minimal data to avoid timeouts

-- Insert default plans
INSERT INTO planos (id, nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, recursos, preco_mensal) VALUES
(1, 'Starter', 5, 2, 50, 500, '{"relatorios_basicos": true}', 29.90),
(2, 'Professional', 15, 5, 200, 2000, '{"relatorios_avancados": true}', 79.90),
(3, 'Enterprise', -1, -1, -1, -1, '{"relatorios_customizados": true}', 199.90)
ON CONFLICT (id) DO NOTHING;

-- Insert default tenant
INSERT INTO tenants (id, nome, subdomain, domain, cnpj, telefone, email, endereco, cor_primaria, status, plano_id) VALUES
(1, 'Divino Lanches', 'divino', 'divinolanches.com', '12345678000199', '(11) 99999-9999', 'contato@divinolanches.com', 'Rua das Flores, 123', '#28a745', 'ativo', 2)
ON CONFLICT (id) DO NOTHING;

-- Insert default filial
INSERT INTO filiais (id, tenant_id, nome, endereco, telefone, email, cnpj, status) VALUES
(1, 1, 'Matriz', 'Rua das Flores, 123', '(11) 99999-9999', 'contato@divinolanches.com', '12345678000199', 'ativo')
ON CONFLICT (id) DO NOTHING;

-- Insert default admin user
INSERT INTO usuarios (id, login, senha, nivel, pergunta, resposta, tenant_id, filial_id) VALUES
(1, 'admin', '$2y$10$bOYm96HqCJ4p7lcazLBpuO0JllFT6UE8PIC/N2qbHgIzenqKqB2WK', 1, 'admin', 'admin', 1, 1)
ON CONFLICT (id) DO NOTHING;
