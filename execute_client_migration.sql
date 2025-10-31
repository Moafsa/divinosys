-- Execute client system migration
-- This script creates all necessary tables for the client management system

-- Create usuarios_globais table (global users/customers)
CREATE TABLE IF NOT EXISTS usuarios_globais (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) UNIQUE,
    email VARCHAR(255) UNIQUE,
    cpf VARCHAR(14),
    data_nascimento DATE,
    telefone_secundario VARCHAR(20),
    observacoes TEXT,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

-- Create cliente_historico table (client interaction history)
CREATE TABLE IF NOT EXISTS cliente_historico (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    tipo_interacao VARCHAR(50) NOT NULL, -- 'pedido', 'pagamento', 'cadastro', 'atualizacao'
    descricao TEXT,
    dados_anteriores JSONB,
    dados_novos JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create cliente_estabelecimentos table (establishments visited by client)
CREATE TABLE IF NOT EXISTS cliente_estabelecimentos (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    primeira_visita TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_visita TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_pedidos INTEGER DEFAULT 0,
    total_gasto DECIMAL(10,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- Add customer reference to pedido table if not exists
ALTER TABLE pedido 
ADD COLUMN IF NOT EXISTS usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL;

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
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
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

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_telefone ON usuarios_globais(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_email ON usuarios_globais(email);
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_ativo ON usuarios_globais(ativo);

CREATE INDEX IF NOT EXISTS idx_enderecos_usuario ON enderecos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_enderecos_tenant ON enderecos(tenant_id);
CREATE INDEX IF NOT EXISTS idx_enderecos_principal ON enderecos(principal);

CREATE INDEX IF NOT EXISTS idx_preferencias_cliente_usuario ON preferencias_cliente(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_preferencias_cliente_tenant ON preferencias_cliente(tenant_id);

CREATE INDEX IF NOT EXISTS idx_cliente_historico_usuario ON cliente_historico(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_cliente_historico_tenant ON cliente_historico(tenant_id);
CREATE INDEX IF NOT EXISTS idx_cliente_historico_tipo ON cliente_historico(tipo_interacao);

CREATE INDEX IF NOT EXISTS idx_cliente_estabelecimentos_usuario ON cliente_estabelecimentos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_cliente_estabelecimentos_tenant ON cliente_estabelecimentos(tenant_id);

CREATE INDEX IF NOT EXISTS idx_pedido_usuario ON pedido(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido ON pagamentos(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_usuario ON pagamentos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_tenant ON pagamentos(tenant_id);

-- Create triggers for automatic timestamp updates
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply triggers to all tables with updated_at column
CREATE TRIGGER update_usuarios_globais_updated_at BEFORE UPDATE ON usuarios_globais FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_enderecos_updated_at BEFORE UPDATE ON enderecos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_preferencias_cliente_updated_at BEFORE UPDATE ON preferencias_cliente FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_cliente_estabelecimentos_updated_at BEFORE UPDATE ON cliente_estabelecimentos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_pagamentos_updated_at BEFORE UPDATE ON pagamentos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert sample data for testing
INSERT INTO usuarios_globais (nome, telefone, email, cpf, ativo) VALUES 
('João Silva', '(11) 99999-9999', 'joao@email.com', '123.456.789-00', true),
('Maria Santos', '(11) 88888-8888', 'maria@email.com', '987.654.321-00', true),
('Pedro Oliveira', '(11) 77777-7777', 'pedro@email.com', '456.789.123-00', true)
ON CONFLICT (telefone) DO NOTHING;

-- Create view for client summary
CREATE OR REPLACE VIEW vw_clientes_resumo AS
SELECT 
    ug.id,
    ug.nome,
    ug.telefone,
    ug.email,
    ug.cpf,
    ug.ativo,
    COUNT(DISTINCT p.idpedido) as total_pedidos,
    COALESCE(SUM(p.valor_total), 0) as total_gasto,
    MAX(p.created_at) as ultimo_pedido,
    COUNT(DISTINCT ce.tenant_id) as estabelecimentos_visitados,
    ug.created_at as data_cadastro
FROM usuarios_globais ug
LEFT JOIN pedido p ON ug.id = p.usuario_global_id
LEFT JOIN cliente_estabelecimentos ce ON ug.id = ce.usuario_global_id
WHERE ug.ativo = true
GROUP BY ug.id, ug.nome, ug.telefone, ug.email, ug.cpf, ug.ativo, ug.created_at;
















