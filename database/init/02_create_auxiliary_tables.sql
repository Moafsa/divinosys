-- Create auxiliary tables
-- This script creates supporting tables that are not part of the core schema

-- Create estoque table
CREATE TABLE IF NOT EXISTS estoque (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    estoque_atual DECIMAL(10,2) DEFAULT 0.00,
    estoque_minimo DECIMAL(10,2) DEFAULT 0.00,
    preco_custo DECIMAL(10,2) DEFAULT NULL,
    marca VARCHAR(100) DEFAULT NULL,
    fornecedor VARCHAR(100) DEFAULT NULL,
    data_compra DATE DEFAULT NULL,
    data_validade DATE DEFAULT NULL,
    unidade VARCHAR(10) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create log_pedidos table
CREATE TABLE IF NOT EXISTS log_pedidos (
    id SERIAL PRIMARY KEY,
    idpedido INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    status_anterior VARCHAR(50) DEFAULT NULL,
    novo_status VARCHAR(50) DEFAULT NULL,
    usuario VARCHAR(100) DEFAULT NULL,
    data_alteracao TIMESTAMP NOT NULL
);

-- Create entregadores table
CREATE TABLE IF NOT EXISTS entregadores (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Ativo',
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create movimentacoes_financeiras table
CREATE TABLE IF NOT EXISTS movimentacoes_financeiras (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER DEFAULT NULL REFERENCES pedido(idpedido) ON DELETE SET NULL,
    tipo VARCHAR(20) NOT NULL,
    categoria_id INTEGER NOT NULL,
    conta_id INTEGER NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_movimentacao DATE NOT NULL,
    data_vencimento DATE DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    forma_pagamento VARCHAR(20) DEFAULT NULL,
    comprovante VARCHAR(255) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create categorias_financeiras table
CREATE TABLE IF NOT EXISTS categorias_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
    descricao TEXT DEFAULT NULL,
    cor VARCHAR(7) DEFAULT '#007bff',
    icone VARCHAR(50) DEFAULT 'fas fa-tag',
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create contas_financeiras table
CREATE TABLE IF NOT EXISTS contas_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('caixa', 'banco', 'cartao', 'pix', 'outros')),
    saldo_inicial DECIMAL(10,2) DEFAULT 0.00,
    saldo_atual DECIMAL(10,2) DEFAULT 0.00,
    banco VARCHAR(100),
    agencia VARCHAR(20),
    conta VARCHAR(20),
    cor VARCHAR(7) DEFAULT '#28a745',
    icone VARCHAR(50) DEFAULT 'fas fa-wallet',
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create lancamentos_financeiros table
CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
    id SERIAL PRIMARY KEY,
    tipo_lancamento VARCHAR(20) NOT NULL CHECK (tipo_lancamento IN ('receita', 'despesa', 'transferencia')),
    categoria_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    conta_destino_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento TIMESTAMP,
    descricao TEXT NOT NULL,
    observacoes TEXT,
    forma_pagamento VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado')),
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create configuracao table
CREATE TABLE IF NOT EXISTS configuracao (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    valor TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, chave)
);

-- Insert sample estoque data for existing products
INSERT INTO estoque (produto_id, estoque_atual, estoque_minimo, tenant_id, filial_id) 
SELECT id, 10.00, 5.00, tenant_id, filial_id FROM produtos WHERE tenant_id = 1 AND filial_id = 1
ON CONFLICT DO NOTHING;

-- Insert default financial categories for all tenants/filiais
INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Vendas Mesa', 'receita', 'Receitas de vendas em mesa', '#28a745', 'fas fa-table', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Vendas Mesa'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Vendas Delivery', 'receita', 'Receitas de vendas delivery', '#17a2b8', 'fas fa-motorcycle', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Vendas Delivery'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', '#ffc107', 'fas fa-credit-card', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Vendas Fiadas'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', '#dc3545', 'fas fa-tools', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Despesas Operacionais'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', '#6f42c1', 'fas fa-bullhorn', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Despesas de Marketing'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Salários', 'despesa', 'Pagamento de salários e encargos', '#fd7e14', 'fas fa-users', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Salários'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Aluguel', 'despesa', 'Aluguel do estabelecimento', '#20c997', 'fas fa-building', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Aluguel'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Energia Elétrica', 'despesa', 'Contas de energia elétrica', '#ffc107', 'fas fa-bolt', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Energia Elétrica'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Água', 'despesa', 'Contas de água', '#17a2b8', 'fas fa-tint', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Água'
);

INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Internet', 'despesa', 'Contas de internet e telefone', '#6c757d', 'fas fa-wifi', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM categorias_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Internet'
);

-- Insert default financial accounts for all tenants/filiais
INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id)
SELECT 
    'Caixa Principal', 'caixa', 0.00, 0.00, '#28a745', 'fas fa-cash-register', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM contas_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Caixa Principal'
);

INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id)
SELECT 
    'Conta Corrente', 'banco', 0.00, 0.00, '#007bff', 'fas fa-university', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM contas_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Conta Corrente'
);

INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id)
SELECT 
    'PIX', 'pix', 0.00, 0.00, '#17a2b8', 'fas fa-mobile-alt', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM contas_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'PIX'
);

INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id)
SELECT 
    'Cartão de Crédito', 'cartao', 0.00, 0.00, '#dc3545', 'fas fa-credit-card', t.id, f.id
FROM tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
AND NOT EXISTS (
    SELECT 1 FROM contas_financeiras cf 
    WHERE cf.tenant_id = t.id AND cf.filial_id = f.id AND cf.nome = 'Cartão de Crédito'
);

