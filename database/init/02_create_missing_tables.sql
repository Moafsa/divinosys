-- Create missing tables that are needed by the application

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

-- Create whatsapp_instances table
CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id SERIAL PRIMARY KEY,
    instance_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    qr_code TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'disconnected',
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create log_pedidos table
CREATE TABLE IF NOT EXISTS log_pedidos (
    id SERIAL PRIMARY KEY,
    idpedido INTEGER NOT NULL REFERENCES pedido(idpedido),
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
    pedido_id INTEGER DEFAULT NULL REFERENCES pedido(idpedido),
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
    tipo VARCHAR(20) NOT NULL,
    descricao TEXT DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create contas_financeiras table
CREATE TABLE IF NOT EXISTS contas_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    saldo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    saldo_atual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    banco VARCHAR(100) DEFAULT NULL,
    agencia VARCHAR(20) DEFAULT NULL,
    conta VARCHAR(20) DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create configuracao table
CREATE TABLE IF NOT EXISTS configuracao (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create caixas_entrada table
CREATE TABLE IF NOT EXISTS caixas_entrada (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    servidor VARCHAR(255) NOT NULL,
    porta INTEGER NOT NULL DEFAULT 993,
    ssl BOOLEAN DEFAULT true,
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create relatorios table
CREATE TABLE IF NOT EXISTS relatorios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    parametros JSON DEFAULT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing columns to existing tables

-- Add missing columns to pedido table
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS usuario_id INTEGER REFERENCES usuarios(id);
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS delivery BOOLEAN DEFAULT false;

-- Add missing columns to categorias table
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT DEFAULT NULL;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES categorias(id);

-- Fix data types
ALTER TABLE mesas ALTER COLUMN id_mesa TYPE VARCHAR(10);
ALTER TABLE pedido ALTER COLUMN idmesa TYPE VARCHAR(10);
ALTER TABLE pedidos ALTER COLUMN idmesa TYPE VARCHAR(10);

-- Insert sample data for categorias to fix undefined array key errors
UPDATE categorias SET descricao = 'Categoria de ' || nome WHERE descricao IS NULL;
UPDATE categorias SET parent_id = NULL WHERE parent_id IS NULL;

-- Insert sample data for pedido
UPDATE pedido SET usuario_id = 1 WHERE usuario_id IS NULL;
UPDATE pedido SET delivery = false WHERE delivery IS NULL;

-- Insert sample estoque data for existing products
INSERT INTO estoque (produto_id, estoque_atual, estoque_minimo, tenant_id, filial_id) 
SELECT id, 10.00, 5.00, tenant_id, filial_id FROM produtos WHERE tenant_id = 1 AND filial_id = 1;
