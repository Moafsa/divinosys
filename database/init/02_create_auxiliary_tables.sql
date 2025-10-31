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

