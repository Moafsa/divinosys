-- Tabela para pagamentos de funcionários (salários e adiantamentos)
CREATE TABLE IF NOT EXISTS pagamentos_funcionarios (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    tipo_pagamento VARCHAR(20) NOT NULL CHECK (tipo_pagamento IN ('salario', 'adiantamento', 'bonus', 'outros')),
    valor DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    data_referencia DATE, -- Para salários, data de referência do mês
    descricao TEXT,
    forma_pagamento VARCHAR(50), -- dinheiro, pix, transferencia, etc
    conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
    lancamento_financeiro_id INTEGER REFERENCES lancamentos_financeiros(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'cancelado')),
    observacoes TEXT,
    usuario_pagamento_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL, -- Quem fez o pagamento
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_pagamentos_funcionarios_usuario ON pagamentos_funcionarios(usuario_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_funcionarios_tenant ON pagamentos_funcionarios(tenant_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_funcionarios_filial ON pagamentos_funcionarios(filial_id);
CREATE INDEX IF NOT EXISTS idx_pagamentos_funcionarios_data ON pagamentos_funcionarios(data_pagamento);
CREATE INDEX IF NOT EXISTS idx_pagamentos_funcionarios_status ON pagamentos_funcionarios(status);

-- Comentários nas colunas
COMMENT ON TABLE pagamentos_funcionarios IS 'Registro de pagamentos de salários, adiantamentos e outros pagamentos para funcionários';
COMMENT ON COLUMN pagamentos_funcionarios.tipo_pagamento IS 'Tipo: salario, adiantamento, bonus, outros';
COMMENT ON COLUMN pagamentos_funcionarios.data_referencia IS 'Data de referência do mês para salários';
COMMENT ON COLUMN pagamentos_funcionarios.lancamento_financeiro_id IS 'ID do lançamento financeiro relacionado (se criado)';

