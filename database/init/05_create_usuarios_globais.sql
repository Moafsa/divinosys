-- Criar tabela usuarios_globais (usuários compartilhados entre tenants)
CREATE TABLE IF NOT EXISTS usuarios_globais (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    tipo_usuario VARCHAR(50) DEFAULT 'cliente',
    cpf VARCHAR(14),
    cnpj VARCHAR(18),
    endereco_completo TEXT,
    ativo BOOLEAN DEFAULT true,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela usuarios_telefones (múltiplos telefones por usuário)
CREATE TABLE IF NOT EXISTS usuarios_telefones (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    telefone VARCHAR(20) NOT NULL,
    tipo VARCHAR(50) DEFAULT 'principal',
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela usuarios_enderecos (múltiplos endereços por usuário)
CREATE TABLE IF NOT EXISTS usuarios_enderecos (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    endereco_completo TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'principal',
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela usuarios_estabelecimento (relacionamento usuário-tenant-filial)
CREATE TABLE IF NOT EXISTS usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    cargo VARCHAR(100),
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- Criar tabela usuarios_consentimentos_lgpd (LGPD compliance)
CREATE TABLE IF NOT EXISTS usuarios_consentimentos_lgpd (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tipo_consentimento VARCHAR(100) NOT NULL,
    consentido BOOLEAN NOT NULL,
    dados_consentidos TEXT,
    ip_consentimento INET,
    user_agent TEXT,
    data_consentimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_revogacao TIMESTAMP,
    ativo BOOLEAN DEFAULT true
);

-- Criar tabela usuarios_logs_acesso (logs de acesso LGPD)
CREATE TABLE IF NOT EXISTS usuarios_logs_acesso (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    acao VARCHAR(100) NOT NULL,
    dados_alterados TEXT,
    ip_acesso INET,
    user_agent TEXT,
    data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela evolution_instancias (instâncias Evolution API)
CREATE TABLE IF NOT EXISTS evolution_instancias (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    nome_instancia VARCHAR(100) NOT NULL,
    numero_telefone VARCHAR(20),
    status VARCHAR(50) DEFAULT 'criada',
    qr_code TEXT,
    webhook_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(nome_instancia)
);

-- Criar índices para performance
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_telefone ON usuarios_globais(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_email ON usuarios_globais(email);
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_tipo ON usuarios_globais(tipo_usuario);
CREATE INDEX IF NOT EXISTS idx_usuarios_globais_ativo ON usuarios_globais(ativo);

CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_telefone ON usuarios_telefones(telefone);
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_usuario ON usuarios_telefones(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_telefones_ativo ON usuarios_telefones(ativo);

CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_usuario ON usuarios_estabelecimento(usuario_global_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tenant ON usuarios_estabelecimento(tenant_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_filial ON usuarios_estabelecimento(filial_id);

CREATE INDEX IF NOT EXISTS idx_evolution_instancias_tenant ON evolution_instancias(tenant_id);
CREATE INDEX IF NOT EXISTS idx_evolution_instancias_filial ON evolution_instancias(filial_id);
CREATE INDEX IF NOT EXISTS idx_evolution_instancias_nome ON evolution_instancias(nome_instancia);

-- Inserir usuário admin padrão na tabela usuarios_globais se não existir
INSERT INTO usuarios_globais (nome, email, telefone, tipo_usuario, ativo, data_cadastro, created_at, updated_at)
SELECT 'admin', 'admin@divinolanches.com', '', 'admin', true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM usuarios_globais WHERE tipo_usuario = 'admin');

-- Relacionar admin com tenant padrão
INSERT INTO usuarios_estabelecimento (usuario_global_id, tenant_id, filial_id, cargo, ativo, created_at, updated_at)
SELECT ug.id, 1, 1, 'Administrador', true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM usuarios_globais ug
WHERE ug.tipo_usuario = 'admin'
AND NOT EXISTS (
    SELECT 1 FROM usuarios_estabelecimento ue 
    WHERE ue.usuario_global_id = ug.id AND ue.tenant_id = 1
);