-- Script para corrigir duplicatas nas categorias e contas financeiras
-- Sistema Divino Lanches

-- 1. Remover duplicatas das categorias financeiras
-- Mantém apenas o registro com menor ID para cada combinação única
DELETE FROM categorias_financeiras 
WHERE id NOT IN (
    SELECT MIN(id) 
    FROM categorias_financeiras 
    GROUP BY nome, tipo, tenant_id, filial_id
);

-- 2. Remover duplicatas das contas financeiras
-- Mantém apenas o registro com menor ID para cada combinação única
DELETE FROM contas_financeiras 
WHERE id NOT IN (
    SELECT MIN(id) 
    FROM contas_financeiras 
    GROUP BY nome, tipo, tenant_id, filial_id
);

-- 3. Verificar se as tabelas existem e criar se necessário
CREATE TABLE IF NOT EXISTS categorias_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'investimento')),
    descricao TEXT,
    cor VARCHAR(7) DEFAULT '#007bff',
    icone VARCHAR(50) DEFAULT 'fas fa-tag',
    ativo BOOLEAN DEFAULT true,
    pai_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
    id SERIAL PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'transferencia')),
    categoria_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL,
    conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    conta_destino_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    pedido_id INTEGER,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento TIMESTAMP,
    descricao TEXT NOT NULL,
    observacoes TEXT,
    forma_pagamento VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado')),
    recorrencia VARCHAR(20) CHECK (recorrencia IN ('nenhuma', 'diaria', 'semanal', 'mensal', 'anual')),
    data_fim_recorrencia DATE,
    usuario_id INTEGER,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS anexos_financeiros (
    id SERIAL PRIMARY KEY,
    lancamento_id INTEGER REFERENCES lancamentos_financeiros(id) ON DELETE CASCADE,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_arquivo VARCHAR(50),
    tamanho_arquivo INTEGER,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS relatorios_financeiros (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    filtros JSONB,
    dados JSONB NOT NULL,
    status VARCHAR(20) DEFAULT 'gerado' CHECK (status IN ('gerando', 'gerado', 'erro')),
    usuario_id INTEGER,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Inserir dados padrão apenas se não existirem
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

INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id) VALUES
('Caixa Principal', 'caixa', 0.00, 0.00, '#28a745', 'fas fa-cash-register', 1, 1),
('Conta Corrente', 'banco', 0.00, 0.00, '#007bff', 'fas fa-university', 1, 1),
('PIX', 'pix', 0.00, 0.00, '#17a2b8', 'fas fa-mobile-alt', 1, 1),
('Cartão de Crédito', 'cartao', 0.00, 0.00, '#dc3545', 'fas fa-credit-card', 1, 1)
ON CONFLICT DO NOTHING;

-- 5. Criar índices para performance
CREATE INDEX IF NOT EXISTS idx_categorias_financeiras_tenant_filial ON categorias_financeiras(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_categorias_financeiras_tipo ON categorias_financeiras(tipo);
CREATE INDEX IF NOT EXISTS idx_contas_financeiras_tenant_filial ON contas_financeiras(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_tenant_filial ON lancamentos_financeiros(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_tipo ON lancamentos_financeiros(tipo);
CREATE INDEX IF NOT EXISTS idx_lancamentos_financeiros_data ON lancamentos_financeiros(created_at);

-- 6. Verificar resultados
SELECT 'Categorias após limpeza:' as info, COUNT(*) as total FROM categorias_financeiras WHERE tenant_id = 1 AND filial_id = 1;
SELECT 'Contas após limpeza:' as info, COUNT(*) as total FROM contas_financeiras WHERE tenant_id = 1 AND filial_id = 1;
