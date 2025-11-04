-- =====================================================
-- SISTEMA DE CAIXA AVANÇADO - ESTRUTURA DE BANCO
-- =====================================================
-- Este arquivo cria todas as tabelas necessárias para:
-- - Sistema de vendas fiadas
-- - Sistema de descontos e cortesias  
-- - Integração com gateways de pagamento
-- - Relatórios financeiros avançados
-- =====================================================

-- 1. TABELA DE CLIENTES PARA FIADO
-- =====================================================
CREATE TABLE IF NOT EXISTS clientes_fiado (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf_cnpj VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    limite_credito DECIMAL(10,2) DEFAULT 0.00,
    saldo_devedor DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'bloqueado', 'suspenso')),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABELA DE VENDAS FIADAS
-- =====================================================
CREATE TABLE IF NOT EXISTS vendas_fiadas (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL REFERENCES clientes_fiado(id) ON DELETE CASCADE,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado')),
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. TABELA DE PAGAMENTOS DE FIADO
-- =====================================================
CREATE TABLE IF NOT EXISTS pagamentos_fiado (
    id SERIAL PRIMARY KEY,
    venda_fiada_id INTEGER NOT NULL REFERENCES vendas_fiadas(id) ON DELETE CASCADE,
    valor_pago DECIMAL(10,2) NOT NULL,
    data_pagamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    forma_pagamento VARCHAR(50) NOT NULL,
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. TABELA DE TIPOS DE DESCONTO
-- =====================================================
CREATE TABLE IF NOT EXISTS tipos_desconto (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('percentual', 'valor_fixo', 'cortesia')),
    valor DECIMAL(10,2),
    percentual DECIMAL(5,2),
    requer_autorizacao BOOLEAN DEFAULT false,
    nivel_autorizacao INTEGER DEFAULT 1 CHECK (nivel_autorizacao IN (1, 2, 3)), -- 1=caixa, 2=supervisor, 3=gerente
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. TABELA DE DESCONTOS APLICADOS
-- =====================================================
CREATE TABLE IF NOT EXISTS descontos_aplicados (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    tipo_desconto_id INTEGER REFERENCES tipos_desconto(id) ON DELETE SET NULL,
    valor_desconto DECIMAL(10,2) NOT NULL,
    motivo TEXT,
    autorizado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    data_aplicacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- 6. TABELA DE CONFIGURAÇÕES DE PAGAMENTO
-- =====================================================
CREATE TABLE IF NOT EXISTS configuracao_pagamento (
    id SERIAL PRIMARY KEY,
    gateway VARCHAR(50) NOT NULL CHECK (gateway IN ('stone', 'pagseguro', 'cielo', 'mercadopago', 'pix')),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('maquina', 'online', 'pix')),
    configuracao JSONB NOT NULL,
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. TABELA DE TRANSAÇÕES DE PAGAMENTO
-- =====================================================
CREATE TABLE IF NOT EXISTS transacoes_pagamento (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL,
    gateway VARCHAR(50) NOT NULL,
    id_transacao_gateway VARCHAR(100),
    valor DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('pendente', 'aprovada', 'negada', 'cancelada', 'estornada')),
    dados_resposta JSONB,
    data_processamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- 8. TABELA DE CATEGORIAS FINANCEIRAS
-- =====================================================
-- NOTE: categorias_financeiras table is created in 02_create_missing_tables.sql
-- Adding missing columns if they don't exist (none needed for now)

-- 9. TABELA DE CONTAS FINANCEIRAS
-- =====================================================
-- NOTE: contas_financeiras table is created in 02_create_missing_tables.sql
-- Adding missing columns if they don't exist (none needed for now)

-- 10. TABELA DE MOVIMENTAÇÕES FINANCEIRAS DETALHADAS
-- =====================================================
CREATE TABLE IF NOT EXISTS movimentacoes_financeiras_detalhadas (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL,
    mesa_id VARCHAR(10),
    tipo_movimentacao VARCHAR(20) NOT NULL CHECK (tipo_movimentacao IN ('receita', 'despesa')),
    categoria_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descricao TEXT,
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'confirmado' CHECK (status IN ('pendente', 'confirmado', 'cancelado')),
    comprovante_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. TABELA DE RELATÓRIOS FINANCEIROS
-- =====================================================
CREATE TABLE IF NOT EXISTS relatorios_financeiros (
    id SERIAL PRIMARY KEY,
    tipo_relatorio VARCHAR(50) NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    dados_relatorio JSONB,
    usuario_gerador INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- ÍNDICES PARA PERFORMANCE
-- =====================================================

-- Índices para clientes_fiado
CREATE INDEX IF NOT EXISTS idx_clientes_fiado_tenant_filial ON clientes_fiado(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_clientes_fiado_status ON clientes_fiado(status);
CREATE INDEX IF NOT EXISTS idx_clientes_fiado_cpf ON clientes_fiado(cpf_cnpj);

-- Índices para vendas_fiadas
CREATE INDEX IF NOT EXISTS idx_vendas_fiadas_cliente ON vendas_fiadas(cliente_id);
CREATE INDEX IF NOT EXISTS idx_vendas_fiadas_pedido ON vendas_fiadas(pedido_id);
CREATE INDEX IF NOT EXISTS idx_vendas_fiadas_status ON vendas_fiadas(status);
CREATE INDEX IF NOT EXISTS idx_vendas_fiadas_vencimento ON vendas_fiadas(data_vencimento);
CREATE INDEX IF NOT EXISTS idx_vendas_fiadas_tenant_filial ON vendas_fiadas(tenant_id, filial_id);

-- Índices para pagamentos_fiado
CREATE INDEX IF NOT EXISTS idx_pagamentos_fiado_venda ON pagamentos_fiado(venda_fiada_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_fiado_data ON pagamentos_fiado(data_pagamento);
CREATE INDEX IF NOT EXISTS idx_pagamentos_fiado_tenant_filial ON pagamentos_fiado(tenant_id, filial_id);

-- Índices para descontos_aplicados
CREATE INDEX IF NOT EXISTS idx_descontos_pedido ON descontos_aplicados(pedido_id);
CREATE INDEX IF NOT EXISTS idx_descontos_tipo ON descontos_aplicados(tipo_desconto_id);
CREATE INDEX IF NOT EXISTS idx_descontos_data ON descontos_aplicados(data_aplicacao);
CREATE INDEX IF NOT EXISTS idx_descontos_tenant_filial ON descontos_aplicados(tenant_id, filial_id);

-- Índices para transacoes_pagamento
CREATE INDEX IF NOT EXISTS idx_transacoes_pedido ON transacoes_pagamento(pedido_id);
CREATE INDEX IF NOT EXISTS idx_transacoes_gateway ON transacoes_pagamento(gateway);
CREATE INDEX IF NOT EXISTS idx_transacoes_status ON transacoes_pagamento(status);
CREATE INDEX IF NOT EXISTS idx_transacoes_data ON transacoes_pagamento(data_processamento);
CREATE INDEX IF NOT EXISTS idx_transacoes_tenant_filial ON transacoes_pagamento(tenant_id, filial_id);

-- Índices para movimentacoes_financeiras_detalhadas
CREATE INDEX IF NOT EXISTS idx_movimentacoes_pedido ON movimentacoes_financeiras_detalhadas(pedido_id);
CREATE INDEX IF NOT EXISTS idx_movimentacoes_tipo ON movimentacoes_financeiras_detalhadas(tipo_movimentacao);
CREATE INDEX IF NOT EXISTS idx_movimentacoes_data ON movimentacoes_financeiras_detalhadas(data_movimentacao);
CREATE INDEX IF NOT EXISTS idx_movimentacoes_tenant_filial ON movimentacoes_financeiras_detalhadas(tenant_id, filial_id);

-- =====================================================
-- DADOS INICIAIS (SEEDS)
-- =====================================================
-- NOTE: Financial categories and accounts are now created automatically
-- during tenant onboarding via OnboardingController::createDefaultFinancialData()
-- This ensures each tenant gets their own set of financial data.

-- Inserir tipos de desconto padrão
INSERT INTO tipos_desconto (nome, tipo, percentual, requer_autorizacao, nivel_autorizacao, tenant_id, filial_id) VALUES
('Desconto Cliente Frequente', 'percentual', 5.00, false, 1, 1, 1),
('Desconto por Problema', 'percentual', 10.00, true, 2, 1, 1),
('Cortesia Gerência', 'cortesia', 100.00, true, 3, 1, 1),
('Desconto Funcionário', 'percentual', 20.00, true, 2, 1, 1),
('Desconto Especial', 'valor_fixo', 0.00, true, 3, 1, 1)
ON CONFLICT DO NOTHING;

-- =====================================================
-- TRIGGERS PARA ATUALIZAÇÃO AUTOMÁTICA
-- =====================================================

-- Trigger para atualizar saldo devedor do cliente
CREATE OR REPLACE FUNCTION atualizar_saldo_cliente()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE clientes_fiado 
        SET saldo_devedor = saldo_devedor + NEW.valor_total
        WHERE id = NEW.cliente_id;
    ELSIF TG_OP = 'UPDATE' THEN
        UPDATE clientes_fiado 
        SET saldo_devedor = saldo_devedor - OLD.valor_total + NEW.valor_total
        WHERE id = NEW.cliente_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE clientes_fiado 
        SET saldo_devedor = saldo_devedor - OLD.valor_total
        WHERE id = OLD.cliente_id;
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_atualizar_saldo_cliente
    AFTER INSERT OR UPDATE OR DELETE ON vendas_fiadas
    FOR EACH ROW EXECUTE FUNCTION atualizar_saldo_cliente();

-- Trigger para atualizar saldo da conta financeira
CREATE OR REPLACE FUNCTION atualizar_saldo_conta()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.tipo_movimentacao = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + NEW.valor
            WHERE id = NEW.conta_id;
        ELSE
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - NEW.valor
            WHERE id = NEW.conta_id;
        END IF;
    ELSIF TG_OP = 'UPDATE' THEN
        -- Reverter valor antigo
        IF OLD.tipo_movimentacao = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - OLD.valor
            WHERE id = OLD.conta_id;
        ELSE
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + OLD.valor
            WHERE id = OLD.conta_id;
        END IF;
        
        -- Aplicar novo valor
        IF NEW.tipo_movimentacao = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + NEW.valor
            WHERE id = NEW.conta_id;
        ELSE
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - NEW.valor
            WHERE id = NEW.conta_id;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF OLD.tipo_movimentacao = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - OLD.valor
            WHERE id = OLD.conta_id;
        ELSE
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + OLD.valor
            WHERE id = OLD.conta_id;
        END IF;
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_atualizar_saldo_conta
    AFTER INSERT OR UPDATE OR DELETE ON movimentacoes_financeiras_detalhadas
    FOR EACH ROW EXECUTE FUNCTION atualizar_saldo_conta();

-- =====================================================
-- COMENTÁRIOS DAS TABELAS
-- =====================================================

COMMENT ON TABLE clientes_fiado IS 'Clientes autorizados para vendas fiadas';
COMMENT ON TABLE vendas_fiadas IS 'Registro de vendas a prazo';
COMMENT ON TABLE pagamentos_fiado IS 'Pagamentos recebidos de vendas fiadas';
COMMENT ON TABLE tipos_desconto IS 'Tipos de desconto disponíveis no sistema';
COMMENT ON TABLE descontos_aplicados IS 'Descontos aplicados em pedidos';
COMMENT ON TABLE configuracao_pagamento IS 'Configurações de gateways de pagamento';
COMMENT ON TABLE transacoes_pagamento IS 'Transações processadas pelos gateways';
COMMENT ON TABLE categorias_financeiras IS 'Categorias para classificação financeira';
COMMENT ON TABLE contas_financeiras IS 'Contas para controle de saldos';
COMMENT ON TABLE movimentacoes_financeiras_detalhadas IS 'Movimentações financeiras detalhadas';
COMMENT ON TABLE relatorios_financeiros IS 'Relatórios financeiros gerados';

-- =====================================================
-- FIM DA ESTRUTURA
-- =====================================================
