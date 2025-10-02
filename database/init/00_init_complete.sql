-- Complete database initialization script
-- This script creates all tables and inserts essential data

-- Create extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create tables in correct order (respecting foreign keys)
CREATE TABLE IF NOT EXISTS planos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    max_mesas INTEGER DEFAULT 10,
    max_usuarios INTEGER DEFAULT 3,
    max_produtos INTEGER DEFAULT 100,
    max_pedidos_mes INTEGER DEFAULT 1000,
    recursos JSONB,
    preco_mensal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE,
    domain VARCHAR(255),
    cnpj VARCHAR(18),
    telefone VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    logo_url VARCHAR(500),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'suspenso')),
    plano_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    cnpj VARCHAR(18),
    logo_url VARCHAR(500),
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nivel INTEGER NOT NULL DEFAULT 1,
    pergunta VARCHAR(255) NOT NULL,
    resposta VARCHAR(255) NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(login, tenant_id)
);

CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagem VARCHAR(255),
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    categoria_id INTEGER,
    imagem VARCHAR(500),
    disponivel BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS ingredientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS mesas (
    id SERIAL PRIMARY KEY,
    numero INTEGER NOT NULL,
    capacidade INTEGER DEFAULT 4,
    status VARCHAR(20) DEFAULT 'livre' CHECK (status IN ('livre', 'ocupada', 'reservada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(50),
    complemento VARCHAR(100),
    cep VARCHAR(20),
    ponto_referencia VARCHAR(100),
    tel1 VARCHAR(20),
    tel2 VARCHAR(20),
    email VARCHAR(100),
    cpf_cnpj VARCHAR(30),
    rg VARCHAR(30),
    condominio VARCHAR(100),
    bloco VARCHAR(50),
    apartamento VARCHAR(50),
    local_entrega VARCHAR(100),
    observacoes VARCHAR(255),
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS pedidos (
    id SERIAL PRIMARY KEY,
    mesa_id INTEGER,
    cliente_id INTEGER,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'preparando', 'pronto', 'entregue', 'cancelado')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS pedido_itens (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL,
    produto_id INTEGER NOT NULL,
    quantidade INTEGER NOT NULL DEFAULT 1,
    preco DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

CREATE TABLE IF NOT EXISTS atividade (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    atividade VARCHAR(255) NOT NULL,
    ordem INTEGER NOT NULL,
    condicao INTEGER NOT NULL,
    start TIMESTAMP,
    color VARCHAR(10),
    "end" TIMESTAMP,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER
);

-- Insert essential data
INSERT INTO planos (id, nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, recursos, preco_mensal) 
VALUES 
    (1, 'Starter', 5, 2, 50, 500, '{"relatorios_basicos": true}', 29.90),
    (2, 'Professional', 15, 5, 200, 2000, '{"relatorios_avancados": true}', 79.90),
    (3, 'Enterprise', -1, -1, -1, -1, '{"relatorios_customizados": true}', 199.90)
ON CONFLICT (id) DO NOTHING;

INSERT INTO tenants (id, nome, subdomain, domain, cnpj, telefone, email, endereco, cor_primaria, status, plano_id) 
VALUES (1, 'Divino Lanches', 'divino', 'divinolanches.com', '12345678000199', '(11) 99999-9999', 'contato@divinolanches.com', 'Rua das Flores, 123', '#28a745', 'ativo', 2)
ON CONFLICT (id) DO NOTHING;

INSERT INTO filiais (id, tenant_id, nome, endereco, telefone, email, cnpj, status) 
VALUES (1, 1, 'Matriz', 'Rua das Flores, 123', '(11) 99999-9999', 'contato@divinolanches.com', '12345678000199', 'ativo')
ON CONFLICT (id) DO NOTHING;

-- Create admin user with correct password hash for admin123
INSERT INTO usuarios (id, login, senha, nivel, pergunta, resposta, tenant_id, filial_id) 
VALUES (1, 'admin', '$2y$10$uR0mp9s8gbii7CkB11YUFeiblzYr0r9EyLFungmMtIot3WkkmuKY6', 1, 'admin', 'admin', 1, 1)
ON CONFLICT (id) DO UPDATE SET senha = EXCLUDED.senha;

-- Create WuzAPI user and database
DO $$
BEGIN
    -- Create wuzapi role if it doesn't exist
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'wuzapi') THEN
        CREATE ROLE wuzapi WITH LOGIN CREATEDB PASSWORD 'wuzapi';
    END IF;
    
    -- Create wuzapi database if it doesn't exist
    IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wuzapi') THEN
        CREATE DATABASE wuzapi OWNER wuzapi;
        GRANT ALL PRIVILEGES ON DATABASE wuzapi TO wuzapi;
    END IF;
END
$$;
