-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create tenants table
CREATE TABLE tenants (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100),
    domain VARCHAR(255),
    cnpj VARCHAR(18),
    telefone VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    logo_url VARCHAR(500),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'suspenso')),
    plano_id INTEGER,
    -- Asaas Integration
    asaas_api_key VARCHAR(500),
    asaas_api_url VARCHAR(255) DEFAULT 'https://sandbox.asaas.com/api/v3',
    asaas_customer_id VARCHAR(100),
    asaas_webhook_token VARCHAR(255),
    asaas_environment VARCHAR(20) DEFAULT 'sandbox' CHECK (asaas_environment IN ('sandbox', 'production')),
    asaas_enabled BOOLEAN DEFAULT false,
    asaas_fiscal_info JSONB,
    asaas_municipal_service_id VARCHAR(100),
    asaas_municipal_service_code VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create plans table
CREATE TABLE planos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    max_mesas INTEGER DEFAULT 10,
    max_usuarios INTEGER DEFAULT 3,
    max_produtos INTEGER DEFAULT 100,
    max_pedidos_mes INTEGER DEFAULT 1000,
    max_filiais INTEGER DEFAULT 1,
    trial_days INTEGER DEFAULT 14,
    recursos JSONB,
    preco_mensal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create filiais table
CREATE TABLE filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    email VARCHAR(255),
    cnpj VARCHAR(18),
    logo_url VARCHAR(500),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'suspenso')),
    configuracao JSONB,
    -- Asaas Integration
    asaas_api_key VARCHAR(500),
    asaas_customer_id VARCHAR(100),
    asaas_enabled BOOLEAN DEFAULT false,
    asaas_fiscal_info JSONB,
    asaas_municipal_service_id VARCHAR(100),
    asaas_municipal_service_code VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create usuarios table
CREATE TABLE usuarios (
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

-- Create categorias table
CREATE TABLE categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    cor VARCHAR(7) DEFAULT '#007bff',
    icone VARCHAR(50) DEFAULT 'fas fa-utensils',
    parent_id INTEGER REFERENCES categorias(id) ON DELETE SET NULL,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagem VARCHAR(255),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, nome)
);

-- Create ingredientes table
CREATE TABLE ingredientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(20) DEFAULT 'complemento' CHECK (tipo IN ('pao', 'proteina', 'queijo', 'salada', 'molho', 'complemento')),
    preco_adicional DECIMAL(10,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT true,
    disponivel BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, nome)
);

-- Create produtos table
CREATE TABLE produtos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(255),
    categoria_id INTEGER NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco_normal DECIMAL(10,2) NOT NULL,
    preco_mini DECIMAL(10,2),
    preco_custo DECIMAL(10,2),
    ingredientes JSONB,
    estoque_atual DECIMAL(10,2) DEFAULT 0,
    estoque_minimo DECIMAL(10,2) DEFAULT 0,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagem VARCHAR(255),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, codigo)
);

-- Create produto_ingredientes table
CREATE TABLE produto_ingredientes (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    ingrediente_id INTEGER NOT NULL REFERENCES ingredientes(id) ON DELETE CASCADE,
    obrigatorio BOOLEAN DEFAULT false,
    preco_adicional DECIMAL(10,2) DEFAULT 0.00,
    padrao BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create mesas table
CREATE TABLE mesas (
    id SERIAL PRIMARY KEY,
    id_mesa VARCHAR(10) NOT NULL,
    numero INTEGER,
    nome VARCHAR(255),
    capacidade INTEGER DEFAULT 4,
    status VARCHAR(20) DEFAULT '1' CHECK (status IN ('1', '2', '3', 'livre', 'ocupada', 'reservada')),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, id_mesa)
);

-- Create pedido table (singular - matches system expectations)
CREATE TABLE pedido (
    idpedido SERIAL PRIMARY KEY,
    idmesa VARCHAR(10) DEFAULT NULL,
    cliente VARCHAR(100) DEFAULT NULL,
    delivery BOOLEAN DEFAULT false,
    status VARCHAR(50) DEFAULT 'Pendente' CHECK (status IN ('Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega', 'Entregue', 'Finalizado', 'Cancelado')),
    status_pagamento VARCHAR(50) DEFAULT 'pendente' CHECK (status_pagamento IN ('pendente', 'parcial', 'quitado')),
    valor_total DECIMAL(10,2) DEFAULT 0.00,
    valor_pago DECIMAL(10,2) DEFAULT 0.00,
    saldo_devedor DECIMAL(10,2) DEFAULT 0.00,
    data DATE DEFAULT CURRENT_DATE,
    hora_pedido TIME DEFAULT CURRENT_TIME,
    observacao TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create pedido_itens table
CREATE TABLE pedido_itens (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade INTEGER NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    tamanho VARCHAR(10) DEFAULT 'normal',
    observacao TEXT,
    ingredientes_com TEXT,
    ingredientes_sem TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);