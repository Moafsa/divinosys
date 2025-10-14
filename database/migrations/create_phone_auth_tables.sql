-- Create phone authentication system tables
-- This migration creates tables for phone-based authentication with dynamic codes

-- Create usuarios_globais table (cross-tenant users)
CREATE TABLE IF NOT EXISTS usuarios_globais (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    avatar_url VARCHAR(500),
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create usuarios_telefones table (phone numbers for global users)
CREATE TABLE IF NOT EXISTS usuarios_telefones (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    telefone VARCHAR(20) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'principal' CHECK (tipo IN ('principal', 'secundario')),
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(telefone)
);

-- Create usuarios_estabelecimento table (user roles per establishment)
CREATE TABLE IF NOT EXISTS usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    tipo_usuario VARCHAR(50) NOT NULL CHECK (tipo_usuario IN (
        'admin', 'cozinha', 'caixa', 'garcom', 'entregador', 'cliente'
    )),
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- Create codigos_acesso table (dynamic access codes)
CREATE TABLE IF NOT EXISTS codigos_acesso (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    telefone VARCHAR(20) NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    usado BOOLEAN DEFAULT false,
    expira_em TIMESTAMP NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(codigo, telefone, tenant_id)
);

-- Create tokens_autenticacao table (session tokens)
CREATE TABLE IF NOT EXISTS tokens_autenticacao (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    tipo VARCHAR(20) DEFAULT 'login' CHECK (tipo IN ('login', 'sessao', 'recuperacao')),
    expira_em TIMESTAMP NOT NULL,
    usado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create sessoes_ativas table (active sessions)
CREATE TABLE IF NOT EXISTS sessoes_ativas (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    token_sessao VARCHAR(255) NOT NULL UNIQUE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expira_em TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create consentimentos_lgpd table (LGPD consents)
CREATE TABLE IF NOT EXISTS consentimentos_lgpd (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    tipo_consentimento VARCHAR(50) NOT NULL CHECK (tipo_consentimento IN (
        'pedidos', 'marketing', 'compartilhamento_dados'
    )),
    consentimento BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id, tipo_consentimento)
);

-- Create whatsapp_instances table (WuzAPI instances)
CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    instance_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    wuzapi_token VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'connecting' CHECK (status IN (
        'connecting', 'open', 'closed', 'error'
    )),
    qr_code TEXT,
    last_seen TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(instance_name)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_telefone ON usuarios_telefones(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_usuario ON usuarios_telefones(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_usuario ON usuarios_estabelecimento(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tenant ON usuarios_estabelecimento(tenant_id);
CREATE INDEX IF NOT EXISTS idx_codigos_acesso_telefone ON codigos_acesso(telefone);
CREATE INDEX IF NOT EXISTS idx_codigos_acesso_codigo ON codigos_acesso(codigo);
CREATE INDEX IF NOT EXISTS idx_codigos_acesso_expira ON codigos_acesso(expira_em);
CREATE INDEX IF NOT EXISTS idx_tokens_autenticacao_token ON tokens_autenticacao(token);
CREATE INDEX IF NOT EXISTS idx_tokens_autenticacao_expira ON tokens_autenticacao(expira_em);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_token ON sessoes_ativas(token_sessao);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_usuario ON sessoes_ativas(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_tenant ON whatsapp_instances(tenant_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_instances_status ON whatsapp_instances(status);
