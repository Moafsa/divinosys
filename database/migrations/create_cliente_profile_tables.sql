-- Create client profile tables
-- This migration creates tables for client profile data, addresses, and order history

-- Add additional fields to usuarios_globais if they don't exist
ALTER TABLE usuarios_globais 
ADD COLUMN IF NOT EXISTS cpf VARCHAR(14),
ADD COLUMN IF NOT EXISTS data_nascimento DATE,
ADD COLUMN IF NOT EXISTS telefone_secundario VARCHAR(20),
ADD COLUMN IF NOT EXISTS observacoes TEXT;

-- Create enderecos table (client addresses)
CREATE TABLE IF NOT EXISTS enderecos (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    tipo VARCHAR(20) DEFAULT 'entrega' CHECK (tipo IN ('entrega', 'cobranca', 'residencial', 'comercial')),
    cep VARCHAR(10),
    logradouro VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    pais VARCHAR(50) DEFAULT 'Brasil',
    referencia TEXT,
    principal BOOLEAN DEFAULT false,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_enderecos_usuario ON enderecos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_enderecos_tenant ON enderecos(tenant_id);

-- Create preferencias_cliente table (client preferences)
CREATE TABLE IF NOT EXISTS preferencias_cliente (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    receber_promocoes BOOLEAN DEFAULT true,
    receber_notificacoes BOOLEAN DEFAULT true,
    forma_pagamento_preferida VARCHAR(50),
    observacoes_pedido TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- Add payment tracking to pedido table if not exists
ALTER TABLE pedido 
ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50),
ADD COLUMN IF NOT EXISTS status_pagamento VARCHAR(20) DEFAULT 'pendente' 
    CHECK (status_pagamento IN ('pendente', 'pago', 'parcial', 'cancelado', 'estornado')),
ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS data_pagamento TIMESTAMP;

-- Create pagamentos table (payment history)
CREATE TABLE IF NOT EXISTS pagamentos (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(id) ON DELETE CASCADE,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    valor DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmado' CHECK (status IN ('pendente', 'confirmado', 'cancelado', 'estornado')),
    transacao_id VARCHAR(100),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido ON pagamentos(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_usuario ON pagamentos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_tenant ON pagamentos(tenant_id);

-- Add customer reference to pedido if not exists
ALTER TABLE pedido 
ADD COLUMN IF NOT EXISTS usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_pedido_usuario ON pedido(usuario_global_id);







