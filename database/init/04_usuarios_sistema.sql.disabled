-- Sistema de Usuários e Clientes Globais
-- Tabela para dados globais de usuários (compartilhados entre estabelecimentos)

CREATE TABLE IF NOT EXISTS usuarios_globais (
    id SERIAL PRIMARY KEY,
    telefone VARCHAR(20) UNIQUE NOT NULL,
    nome VARCHAR(255),
    email VARCHAR(255),
    cpf VARCHAR(14),
    cnpj VARCHAR(18),
    endereco_completo TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    ponto_referencia VARCHAR(255),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para vínculos de usuários com estabelecimentos
CREATE TABLE IF NOT EXISTS usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    tipo_usuario VARCHAR(50) NOT NULL, -- admin, cozinha, garcom, entregador, caixa, cliente
    cargo VARCHAR(100),
    permissoes JSONB,
    ativo BOOLEAN DEFAULT true,
    data_vinculacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- Tabela para tokens de autenticação (links mágicos)
CREATE TABLE IF NOT EXISTS tokens_autenticacao (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    tipo VARCHAR(50) DEFAULT 'login', -- login, reset_password
    expira_em TIMESTAMP NOT NULL,
    usado BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para sessões ativas
CREATE TABLE IF NOT EXISTS sessoes_ativas (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    token_sessao VARCHAR(255) UNIQUE NOT NULL,
    expira_em TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_telefone ON usuarios_globais(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tenant ON usuarios_estabelecimento(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tipo ON usuarios_estabelecimento(tipo_usuario);
CREATE INDEX IF NOT EXISTS idx_tokens_autenticacao_token ON tokens_autenticacao(token);
CREATE INDEX IF NOT EXISTS idx_tokens_autenticacao_expira ON tokens_autenticacao(expira_em);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_token ON sessoes_ativas(token_sessao);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_expira ON sessoes_ativas(expira_em);

-- Inserir usuário admin padrão
INSERT INTO usuarios_globais (telefone, nome, email, ativo) 
VALUES ('11999999999', 'Administrador', 'admin@divinolanches.com', true)
ON CONFLICT (telefone) DO NOTHING;

-- Vincular admin ao tenant 1
INSERT INTO usuarios_estabelecimento (usuario_global_id, tenant_id, filial_id, tipo_usuario, cargo, ativo)
SELECT ug.id, 1, 1, 'admin', 'Administrador', true
FROM usuarios_globais ug 
WHERE ug.telefone = '11999999999'
ON CONFLICT (usuario_global_id, tenant_id, filial_id) DO NOTHING;

-- Comentários para documentação
COMMENT ON TABLE usuarios_globais IS 'Dados globais de usuários compartilhados entre estabelecimentos';
COMMENT ON TABLE usuarios_estabelecimento IS 'Vínculos de usuários com estabelecimentos específicos';
COMMENT ON TABLE tokens_autenticacao IS 'Tokens para autenticação via link mágico';
COMMENT ON TABLE sessoes_ativas IS 'Sessões ativas de usuários logados';

COMMENT ON COLUMN usuarios_globais.telefone IS 'Telefone como identificador único global';
COMMENT ON COLUMN usuarios_estabelecimento.tipo_usuario IS 'admin, cozinha, garcom, entregador, caixa, cliente';
COMMENT ON COLUMN usuarios_estabelecimento.permissoes IS 'JSON com permissões específicas do usuário';
COMMENT ON COLUMN tokens_autenticacao.tipo IS 'Tipo do token: login, reset_password, etc';
COMMENT ON COLUMN sessoes_ativas.token_sessao IS 'Token da sessão ativa do usuário';
