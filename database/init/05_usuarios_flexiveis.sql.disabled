-- Sistema de Usuários Flexível e LGPD Compliant
-- Tabela para dados globais de usuários (compartilhados entre estabelecimentos)

-- Atualizar tabela usuarios_globais para ser mais flexível
ALTER TABLE usuarios_globais DROP COLUMN IF EXISTS telefone;
ALTER TABLE usuarios_globais DROP COLUMN IF EXISTS endereco_completo;
ALTER TABLE usuarios_globais DROP COLUMN IF EXISTS latitude;
ALTER TABLE usuarios_globais DROP COLUMN IF EXISTS longitude;
ALTER TABLE usuarios_globais DROP COLUMN IF EXISTS ponto_referencia;

-- Adicionar campos para LGPD
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS cpf_cnpj VARCHAR(18);
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS data_nascimento DATE;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS genero VARCHAR(20);
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS lgpd_consentimento BOOLEAN DEFAULT false;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS lgpd_data_consentimento TIMESTAMP;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS lgpd_finalidade TEXT;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS lgpd_compartilhamento BOOLEAN DEFAULT false;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS lgpd_marketing BOOLEAN DEFAULT false;
ALTER TABLE usuarios_globais ADD COLUMN IF NOT EXISTS observacoes TEXT;

-- Tabela para múltiplos telefones por usuário
CREATE TABLE IF NOT EXISTS usuarios_telefones (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    telefone VARCHAR(20) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'principal', -- principal, secundario, whatsapp, comercial
    ativo BOOLEAN DEFAULT true,
    verificado BOOLEAN DEFAULT false,
    data_verificacao TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, telefone)
);

-- Tabela para múltiplos endereços por usuário
CREATE TABLE IF NOT EXISTS usuarios_enderecos (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    endereco_completo TEXT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    ponto_referencia VARCHAR(255),
    tipo VARCHAR(20) DEFAULT 'residencial', -- residencial, comercial, entrega, cobranca
    ativo BOOLEAN DEFAULT true,
    padrao BOOLEAN DEFAULT false, -- endereço padrão para entrega
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para histórico de telefones (para LGPD)
CREATE TABLE IF NOT EXISTS usuarios_telefones_historico (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    telefone_anterior VARCHAR(20),
    telefone_novo VARCHAR(20),
    motivo VARCHAR(100), -- mudanca, perda, atualizacao
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT
);

-- Tabela para histórico de endereços (para LGPD)
CREATE TABLE IF NOT EXISTS usuarios_enderecos_historico (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    endereco_anterior TEXT,
    endereco_novo TEXT,
    motivo VARCHAR(100), -- mudanca, viagem, trabalho
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT
);

-- Tabela para consentimentos LGPD por estabelecimento
CREATE TABLE IF NOT EXISTS usuarios_consentimentos_lgpd (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    finalidade VARCHAR(100) NOT NULL, -- pedidos, marketing, compartilhamento
    consentimento BOOLEAN NOT NULL,
    data_consentimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_consentimento INET,
    user_agent TEXT,
    observacoes TEXT,
    UNIQUE(usuario_global_id, tenant_id, filial_id, finalidade)
);

-- Tabela para logs de acesso (para LGPD)
CREATE TABLE IF NOT EXISTS usuarios_logs_acesso (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER,
    filial_id INTEGER,
    acao VARCHAR(100) NOT NULL, -- login, logout, consulta, alteracao
    dados_alterados JSONB,
    ip_acesso INET,
    user_agent TEXT,
    data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_telefone ON usuarios_telefones(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_usuario ON usuarios_telefones(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_enderecos_usuario ON usuarios_enderecos(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_enderecos_padrao ON usuarios_enderecos(usuario_global_id, padrao);
CREATE INDEX IF NOT EXISTS idx_usuarios_consentimentos_lgpd ON usuarios_consentimentos_lgpd(usuario_global_id, tenant_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_logs_acesso_usuario ON usuarios_logs_acesso(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_logs_acesso_data ON usuarios_logs_acesso(data_acesso);

-- Atualizar usuário admin padrão
UPDATE usuarios_globais SET 
    lgpd_consentimento = true,
    lgpd_data_consentimento = CURRENT_TIMESTAMP,
    lgpd_finalidade = 'Administração do sistema',
    lgpd_compartilhamento = true
WHERE telefone = '11999999999';

-- Inserir telefone do admin
INSERT INTO usuarios_telefones (usuario_global_id, telefone, tipo, ativo, verificado)
SELECT id, '11999999999', 'principal', true, true
FROM usuarios_globais 
WHERE nome = 'Administrador'
ON CONFLICT (usuario_global_id, telefone) DO NOTHING;

-- Comentários para documentação
COMMENT ON TABLE usuarios_telefones IS 'Múltiplos telefones por usuário';
COMMENT ON TABLE usuarios_enderecos IS 'Múltiplos endereços por usuário';
COMMENT ON TABLE usuarios_telefones_historico IS 'Histórico de alterações de telefone para LGPD';
COMMENT ON TABLE usuarios_enderecos_historico IS 'Histórico de alterações de endereço para LGPD';
COMMENT ON TABLE usuarios_consentimentos_lgpd IS 'Consentimentos LGPD por estabelecimento';
COMMENT ON TABLE usuarios_logs_acesso IS 'Logs de acesso para auditoria LGPD';

COMMENT ON COLUMN usuarios_telefones.tipo IS 'principal, secundario, whatsapp, comercial';
COMMENT ON COLUMN usuarios_enderecos.tipo IS 'residencial, comercial, entrega, cobranca';
COMMENT ON COLUMN usuarios_consentimentos_lgpd.finalidade IS 'pedidos, marketing, compartilhamento';
COMMENT ON COLUMN usuarios_logs_acesso.acao IS 'login, logout, consulta, alteracao';
