-- ============================================
-- SISTEMA DE ASSINATURA SAAS
-- ============================================

-- Tabela de assinaturas
CREATE TABLE IF NOT EXISTS assinaturas (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    plano_id INTEGER NOT NULL REFERENCES planos(id) ON DELETE RESTRICT,
    status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'suspensa', 'cancelada', 'trial', 'inadimplente')),
    data_inicio DATE NOT NULL,
    data_fim DATE,
    data_proxima_cobranca DATE,
    valor DECIMAL(10,2) NOT NULL,
    periodicidade VARCHAR(20) DEFAULT 'mensal' CHECK (periodicidade IN ('mensal', 'trimestral', 'semestral', 'anual')),
    trial_ate DATE,
    asaas_subscription_id VARCHAR(100),
    cancelada_em TIMESTAMP,
    motivo_cancelamento TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de pagamentos de assinaturas (renamed to avoid conflict with pagamentos table in 04_update_mesa_pedidos.sql)
CREATE TABLE IF NOT EXISTS pagamentos_assinaturas (
    id SERIAL PRIMARY KEY,
    assinatura_id INTEGER NOT NULL REFERENCES assinaturas(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    valor DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'falhou', 'cancelado', 'estornado')),
    metodo_pagamento VARCHAR(50),
    gateway_payment_id VARCHAR(255),
    gateway_response TEXT,
    data_pagamento TIMESTAMP,
    data_vencimento DATE NOT NULL,
    tentativas INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de histórico de uso (para controle de limites)
CREATE TABLE IF NOT EXISTS uso_recursos (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    mes_referencia VARCHAR(7) NOT NULL, -- formato: YYYY-MM
    mesas_usadas INTEGER DEFAULT 0,
    usuarios_usados INTEGER DEFAULT 0,
    produtos_usados INTEGER DEFAULT 0,
    pedidos_mes INTEGER DEFAULT 0,
    storage_mb DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, mes_referencia)
);

-- Tabela de logs de auditoria
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE SET NULL,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    acao VARCHAR(100) NOT NULL,
    entidade VARCHAR(50),
    entidade_id INTEGER,
    dados_anteriores JSONB,
    dados_novos JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT false,
    data_leitura TIMESTAMP,
    link VARCHAR(500),
    prioridade VARCHAR(20) DEFAULT 'normal' CHECK (prioridade IN ('baixa', 'normal', 'alta', 'urgente')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de configurações de tenant
CREATE TABLE IF NOT EXISTS tenant_config (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    chave VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo VARCHAR(20) DEFAULT 'string' CHECK (tipo IN ('string', 'integer', 'boolean', 'json')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, chave)
);

-- Índices para performance
CREATE INDEX idx_assinaturas_tenant ON assinaturas(tenant_id);
CREATE INDEX idx_assinaturas_status ON assinaturas(status);
CREATE INDEX idx_pagamentos_assinaturas_assinatura ON pagamentos_assinaturas(assinatura_id);
CREATE INDEX idx_pagamentos_assinaturas_tenant ON pagamentos_assinaturas(tenant_id);
CREATE INDEX idx_pagamentos_assinaturas_status ON pagamentos_assinaturas(status);
CREATE INDEX idx_uso_recursos_tenant ON uso_recursos(tenant_id);
CREATE INDEX idx_uso_recursos_mes ON uso_recursos(mes_referencia);
CREATE INDEX idx_audit_logs_tenant ON audit_logs(tenant_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_notificacoes_tenant ON notificacoes(tenant_id);
CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX idx_notificacoes_lida ON notificacoes(lida);

-- Inserir planos padrão (com max_filiais)
INSERT INTO planos (nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, max_filiais, recursos, preco_mensal) VALUES
('Starter', 5, 2, 50, 500, 1, '{"relatorios_basicos": true, "suporte_email": true, "backup_diario": false}', 49.90),
('Professional', 15, 5, 200, 2000, 3, '{"relatorios_basicos": true, "relatorios_avancados": true, "suporte_email": true, "suporte_whatsapp": true, "backup_diario": true, "api_acesso": false}', 149.90),
('Business', 30, 10, 500, 5000, 10, '{"relatorios_basicos": true, "relatorios_avancados": true, "relatorios_customizados": true, "suporte_email": true, "suporte_whatsapp": true, "suporte_telefone": true, "backup_diario": true, "api_acesso": true, "white_label": false}', 299.90),
('Enterprise', -1, -1, -1, -1, -1, '{"relatorios_basicos": true, "relatorios_avancados": true, "relatorios_customizados": true, "suporte_email": true, "suporte_whatsapp": true, "suporte_telefone": true, "suporte_dedicado": true, "backup_diario": true, "backup_tempo_real": true, "api_acesso": true, "white_label": true, "integracoes_customizadas": true}', 999.90)
ON CONFLICT DO NOTHING;

-- Criar tenant para superadmin (sistema)
INSERT INTO tenants (nome, subdomain, status) VALUES
('SuperAdmin', 'admin', 'ativo')
ON CONFLICT DO NOTHING;

-- Criar usuário superadmin
-- NOTE: Only create if not exists to avoid duplicate key errors
INSERT INTO usuarios (login, senha, nivel, pergunta, resposta, tenant_id, filial_id)
SELECT 
    'superadmin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password
    999, -- nível superadmin
    'Sistema',
    'Sistema',
    t.id,
    NULL
FROM tenants t 
WHERE t.subdomain = 'admin'
AND NOT EXISTS (
    SELECT 1 FROM usuarios u 
    WHERE u.login = 'superadmin' AND u.tenant_id = t.id
);

-- Function para atualizar updated_at automaticamente
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers para updated_at
CREATE TRIGGER update_assinaturas_updated_at BEFORE UPDATE ON assinaturas
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_pagamentos_assinaturas_updated_at BEFORE UPDATE ON pagamentos_assinaturas
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_uso_recursos_updated_at BEFORE UPDATE ON uso_recursos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tenant_config_updated_at BEFORE UPDATE ON tenant_config
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

