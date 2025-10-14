-- =====================================================
-- SISTEMA FINANCEIRO COMPLETO - MIGRAÇÃO
-- =====================================================
-- Data: 2025-10-14
-- Descrição: Sistema financeiro completo com relatórios, lançamentos e gestão de pedidos

-- 1. TABELA DE CATEGORIAS FINANCEIRAS EXPANDIDA
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'investimento')),
    descricao TEXT,
    cor VARCHAR(7) DEFAULT '#007bff',
    icone VARCHAR(50) DEFAULT 'fas fa-tag',
    ativo BOOLEAN DEFAULT true,
    pai_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABELA DE CONTAS FINANCEIRAS EXPANDIDA
-- =====================================================
CREATE TABLE IF NOT EXISTS contas_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('caixa', 'banco', 'cartao', 'pix', 'outros')),
    saldo_inicial DECIMAL(10,2) DEFAULT 0.00,
    saldo_atual DECIMAL(10,2) DEFAULT 0.00,
    banco VARCHAR(100),
    agencia VARCHAR(20),
    conta VARCHAR(20),
    limite DECIMAL(10,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT true,
    cor VARCHAR(7) DEFAULT '#28a745',
    icone VARCHAR(50) DEFAULT 'fas fa-wallet',
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. TABELA DE LANÇAMENTOS FINANCEIROS
-- =====================================================
CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'transferencia')),
    categoria_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    conta_destino_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE SET NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento TIMESTAMP,
    descricao TEXT NOT NULL,
    observacoes TEXT,
    forma_pagamento VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado')),
    recorrência VARCHAR(20) CHECK (recorrência IN ('nenhuma', 'diaria', 'semanal', 'mensal', 'anual')),
    data_fim_recorrência DATE,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. TABELA DE ANEXOS FINANCEIROS
-- =====================================================
CREATE TABLE IF NOT EXISTS anexos_financeiros (
    id SERIAL PRIMARY KEY,
    lancamento_id INTEGER REFERENCES lancamentos_financeiros(id) ON DELETE CASCADE,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_arquivo VARCHAR(50),
    tamanho_arquivo INTEGER,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. TABELA DE HISTÓRICO DE PEDIDOS FINANCEIROS
-- =====================================================
CREATE TABLE IF NOT EXISTS historico_pedidos_financeiros (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE CASCADE,
    acao VARCHAR(50) NOT NULL, -- 'criado', 'pago_parcial', 'pago_total', 'cancelado', 'reembolsado'
    valor_anterior DECIMAL(10,2) DEFAULT 0.00,
    valor_novo DECIMAL(10,2) DEFAULT 0.00,
    diferenca DECIMAL(10,2) DEFAULT 0.00,
    forma_pagamento VARCHAR(50),
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. TABELA DE RELATÓRIOS FINANCEIROS
-- =====================================================
CREATE TABLE IF NOT EXISTS relatorios_financeiros (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- 'vendas', 'despesas', 'fluxo_caixa', 'lucro_prejuizo'
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    filtros JSONB,
    dados JSONB NOT NULL,
    status VARCHAR(20) DEFAULT 'gerado' CHECK (status IN ('gerando', 'gerado', 'erro')),
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. TABELA DE METAS FINANCEIRAS
-- =====================================================
CREATE TABLE IF NOT EXISTS metas_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'lucro')),
    valor_meta DECIMAL(10,2) NOT NULL,
    valor_atual DECIMAL(10,2) DEFAULT 0.00,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'concluida', 'cancelada')),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. ÍNDICES PARA PERFORMANCE
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_tipo ON lancamentos_financeiros(tipo);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_data ON lancamentos_financeiros(data_vencimento);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_status ON lancamentos_financeiros(status);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_pedido ON lancamentos_financeiros(pedido_id);
CREATE INDEX IF NOT EXISTS idx_historico_pedidos_pedido ON historico_pedidos_financeiros(pedido_id);
CREATE INDEX IF NOT EXISTS idx_historico_pedidos_acao ON historico_pedidos_financeiros(acao);
CREATE INDEX IF NOT EXISTS idx_anexos_lancamento ON anexos_financeiros(lancamento_id);

-- 9. TRIGGERS PARA ATUALIZAÇÃO AUTOMÁTICA
-- =====================================================

-- Trigger para atualizar saldo da conta
CREATE OR REPLACE FUNCTION atualizar_saldo_conta()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.tipo = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + NEW.valor
            WHERE id = NEW.conta_id;
        ELSIF NEW.tipo = 'despesa' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - NEW.valor
            WHERE id = NEW.conta_id;
        END IF;
    ELSIF TG_OP = 'UPDATE' THEN
        -- Reverter saldo anterior
        IF OLD.tipo = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - OLD.valor
            WHERE id = OLD.conta_id;
        ELSIF OLD.tipo = 'despesa' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + OLD.valor
            WHERE id = OLD.conta_id;
        END IF;
        
        -- Aplicar novo saldo
        IF NEW.tipo = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + NEW.valor
            WHERE id = NEW.conta_id;
        ELSIF NEW.tipo = 'despesa' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - NEW.valor
            WHERE id = NEW.conta_id;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF OLD.tipo = 'receita' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual - OLD.valor
            WHERE id = OLD.conta_id;
        ELSIF OLD.tipo = 'despesa' THEN
            UPDATE contas_financeiras 
            SET saldo_atual = saldo_atual + OLD.valor
            WHERE id = OLD.conta_id;
        END IF;
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_atualizar_saldo_conta
    AFTER INSERT OR UPDATE OR DELETE ON lancamentos_financeiros
    FOR EACH ROW EXECUTE FUNCTION atualizar_saldo_conta();

-- 10. DADOS INICIAIS
-- =====================================================

-- Inserir categorias financeiras padrão
INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id) VALUES
('Vendas Mesa', 'receita', 'Receitas de vendas em mesa', '#28a745', 'fas fa-table', 1, 1),
('Vendas Delivery', 'receita', 'Receitas de vendas delivery', '#17a2b8', 'fas fa-motorcycle', 1, 1),
('Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', '#ffc107', 'fas fa-credit-card', 1, 1),
('Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', '#dc3545', 'fas fa-tools', 1, 1),
('Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', '#6f42c1', 'fas fa-bullhorn', 1, 1),
('Salários', 'despesa', 'Pagamento de salários e encargos', '#fd7e14', 'fas fa-users', 1, 1),
('Aluguel', 'despesa', 'Aluguel do estabelecimento', '#20c997', 'fas fa-building', 1, 1),
('Energia Elétrica', 'despesa', 'Contas de energia elétrica', '#ffc107', 'fas fa-bolt', 1, 1),
('Água', 'despesa', 'Contas de água', '#17a2b8', 'fas fa-tint', 1, 1),
('Internet', 'despesa', 'Contas de internet e telefone', '#6c757d', 'fas fa-wifi', 1, 1)
ON CONFLICT DO NOTHING;

-- Inserir contas financeiras padrão
INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id) VALUES
('Caixa Principal', 'caixa', 0.00, 0.00, '#28a745', 'fas fa-cash-register', 1, 1),
('Conta Corrente', 'banco', 0.00, 0.00, '#007bff', 'fas fa-university', 1, 1),
('PIX', 'pix', 0.00, 0.00, '#17a2b8', 'fas fa-mobile-alt', 1, 1),
('Cartão de Crédito', 'cartao', 0.00, 0.00, '#dc3545', 'fas fa-credit-card', 1, 1)
ON CONFLICT DO NOTHING;

-- 11. COMENTÁRIOS DAS TABELAS
-- =====================================================
COMMENT ON TABLE categorias_financeiras IS 'Categorias para classificação de receitas e despesas';
COMMENT ON TABLE contas_financeiras IS 'Contas financeiras do estabelecimento (caixa, banco, etc.)';
COMMENT ON TABLE lancamentos_financeiros IS 'Lançamentos financeiros (receitas, despesas, transferências)';
COMMENT ON TABLE anexos_financeiros IS 'Anexos (imagens, documentos) dos lançamentos financeiros';
COMMENT ON TABLE historico_pedidos_financeiros IS 'Histórico de alterações financeiras dos pedidos';
COMMENT ON TABLE relatorios_financeiros IS 'Relatórios financeiros gerados pelo sistema';
COMMENT ON TABLE metas_financeiras IS 'Metas financeiras do estabelecimento';
