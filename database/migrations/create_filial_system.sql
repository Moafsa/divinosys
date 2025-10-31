-- =====================================================
-- SISTEMA DE FILIAIS - MIGRAÇÃO
-- =====================================================
-- Data: 2025-01-15
-- Descrição: Sistema completo de filiais para estabelecimentos

-- 1. TABELA DE FILIAIS (se não existir)
-- =====================================================
CREATE TABLE IF NOT EXISTS filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    cnpj VARCHAR(18),
    logo_url VARCHAR(500),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'suspenso')),
    configuracao JSONB, -- Configurações específicas da filial
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABELA DE USUÁRIOS POR FILIAL (se não existir)
-- =====================================================
CREATE TABLE IF NOT EXISTS usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id) ON DELETE CASCADE,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    tipo_usuario VARCHAR(50) NOT NULL CHECK (tipo_usuario IN ('admin_estabelecimento', 'admin_filial', 'operador', 'cozinha', 'garcom', 'caixa', 'entregador', 'cliente')),
    cargo VARCHAR(100),
    permissoes JSONB, -- Permissões específicas
    ativo BOOLEAN DEFAULT true,
    data_vinculacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(usuario_global_id, tenant_id, filial_id)
);

-- 3. TABELA DE CONFIGURAÇÕES DE FILIAL
-- =====================================================
CREATE TABLE IF NOT EXISTS filial_configuracoes (
    id SERIAL PRIMARY KEY,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    chave VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo VARCHAR(20) DEFAULT 'string' CHECK (tipo IN ('string', 'number', 'boolean', 'json')),
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(filial_id, chave)
);

-- 4. TABELA DE MESAS POR FILIAL
-- =====================================================
CREATE TABLE IF NOT EXISTS mesas (
    id_mesa SERIAL PRIMARY KEY,
    numero VARCHAR(10) NOT NULL,
    capacidade INTEGER DEFAULT 4,
    status VARCHAR(20) DEFAULT 'livre' CHECK (status IN ('livre', 'ocupada', 'reservada', 'manutencao')),
    posicao_x INTEGER DEFAULT 0,
    posicao_y INTEGER DEFAULT 0,
    observacoes TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, filial_id, numero)
);

-- 5. TABELA DE PRODUTOS POR FILIAL
-- =====================================================
CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    categoria_id INTEGER REFERENCES categorias(id) ON DELETE SET NULL,
    preco_normal DECIMAL(10,2) NOT NULL,
    preco_mini DECIMAL(10,2),
    preco_custo DECIMAL(10,2),
    imagem VARCHAR(500),
    estoque_atual INTEGER DEFAULT 0,
    estoque_minimo INTEGER DEFAULT 0,
    ativo BOOLEAN DEFAULT true,
    ingredientes JSONB, -- Lista de ingredientes
    observacoes TEXT,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. TABELA DE CATEGORIAS POR FILIAL
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    imagem VARCHAR(500),
    cor VARCHAR(7) DEFAULT '#007bff',
    icone VARCHAR(50) DEFAULT 'fas fa-tag',
    ordem INTEGER DEFAULT 0,
    ativo BOOLEAN DEFAULT true,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. TABELA DE PEDIDOS POR FILIAL
-- =====================================================
-- NOTE: pedido table is now created in 00_init_database.sql
-- Adding missing columns if they don't exist
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL;
ALTER TABLE pedido ADD COLUMN IF NOT EXISTS tamanho VARCHAR(20);

-- 8. TABELA DE ITENS DO PEDIDO
-- =====================================================
-- NOTE: pedido_itens table is now created in 00_init_database.sql
-- Adding missing columns if they don't exist
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS valor_unitario DECIMAL(10,2);
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS observacao TEXT;
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS tamanho VARCHAR(20) DEFAULT 'normal' CHECK (tamanho IN ('normal', 'mini', 'grande'));

-- 9. TABELA DE RELATÓRIOS CONSOLIDADOS
-- =====================================================
CREATE TABLE IF NOT EXISTS relatorios_consolidados (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    tipo_relatorio VARCHAR(50) NOT NULL, -- 'vendas', 'financeiro', 'produtos', 'clientes'
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    dados JSONB NOT NULL,
    filiais_incluidas INTEGER[] DEFAULT '{}', -- IDs das filiais incluídas
    status VARCHAR(20) DEFAULT 'gerado' CHECK (status IN ('gerando', 'gerado', 'erro')),
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. TABELA DE LOGS DE FILIAL
-- =====================================================
CREATE TABLE IF NOT EXISTS filial_logs (
    id SERIAL PRIMARY KEY,
    filial_id INTEGER NOT NULL REFERENCES filiais(id) ON DELETE CASCADE,
    usuario_global_id INTEGER REFERENCES usuarios_globais(id) ON DELETE SET NULL,
    acao VARCHAR(100) NOT NULL,
    entidade VARCHAR(50),
    entidade_id INTEGER,
    dados_anteriores JSONB,
    dados_novos JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ÍNDICES PARA PERFORMANCE
-- =====================================================

-- Filiais
CREATE INDEX IF NOT EXISTS idx_filiais_tenant ON filiais(tenant_id);
CREATE INDEX IF NOT EXISTS idx_filiais_status ON filiais(status);

-- Usuários Estabelecimento
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tenant ON usuarios_estabelecimento(tenant_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_filial ON usuarios_estabelecimento(filial_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_tipo ON usuarios_estabelecimento(tipo_usuario);
CREATE INDEX IF NOT EXISTS idx_usuarios_estabelecimento_ativo ON usuarios_estabelecimento(ativo);

-- Mesas
CREATE INDEX IF NOT EXISTS idx_mesas_tenant_filial ON mesas(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_mesas_status ON mesas(status);

-- Produtos
CREATE INDEX IF NOT EXISTS idx_produtos_tenant_filial ON produtos(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_produtos_categoria ON produtos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_produtos_ativo ON produtos(ativo);

-- Categorias
CREATE INDEX IF NOT EXISTS idx_categorias_tenant_filial ON categorias(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_categorias_ativo ON categorias(ativo);

-- Pedidos
CREATE INDEX IF NOT EXISTS idx_pedido_tenant_filial ON pedido(tenant_id, filial_id);
CREATE INDEX IF NOT EXISTS idx_pedido_data ON pedido(data);
CREATE INDEX IF NOT EXISTS idx_pedido_status ON pedido(status);
CREATE INDEX IF NOT EXISTS idx_pedido_mesa ON pedido(idmesa);

-- Pedido Itens
CREATE INDEX IF NOT EXISTS idx_pedido_itens_pedido ON pedido_itens(pedido_id);
CREATE INDEX IF NOT EXISTS idx_pedido_itens_produto ON pedido_itens(produto_id);
CREATE INDEX IF NOT EXISTS idx_pedido_itens_tenant_filial ON pedido_itens(tenant_id, filial_id);

-- Relatórios Consolidados
CREATE INDEX IF NOT EXISTS idx_relatorios_consolidados_tenant ON relatorios_consolidados(tenant_id);
CREATE INDEX IF NOT EXISTS idx_relatorios_consolidados_tipo ON relatorios_consolidados(tipo_relatorio);
CREATE INDEX IF NOT EXISTS idx_relatorios_consolidados_periodo ON relatorios_consolidados(periodo_inicio, periodo_fim);

-- Logs de Filial
CREATE INDEX IF NOT EXISTS idx_filial_logs_filial ON filial_logs(filial_id);
CREATE INDEX IF NOT EXISTS idx_filial_logs_acao ON filial_logs(acao);
CREATE INDEX IF NOT EXISTS idx_filial_logs_data ON filial_logs(created_at);

-- TRIGGERS PARA ATUALIZAÇÃO AUTOMÁTICA
-- =====================================================

-- Função para atualizar updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Aplicar triggers
CREATE TRIGGER update_filiais_updated_at BEFORE UPDATE ON filiais FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_usuarios_estabelecimento_updated_at BEFORE UPDATE ON usuarios_estabelecimento FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_mesas_updated_at BEFORE UPDATE ON mesas FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_produtos_updated_at BEFORE UPDATE ON produtos FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_categorias_updated_at BEFORE UPDATE ON categorias FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_pedido_updated_at BEFORE UPDATE ON pedido FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_filial_configuracoes_updated_at BEFORE UPDATE ON filial_configuracoes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- VIEWS PARA RELATÓRIOS
-- =====================================================

-- View para resumo de filiais
CREATE OR REPLACE VIEW vw_filiais_resumo AS
SELECT 
    f.id,
    f.nome,
    f.endereco,
    f.telefone,
    f.status,
    t.nome as estabelecimento,
    COUNT(DISTINCT ue.id) as total_usuarios,
    COUNT(DISTINCT m.id_mesa) as total_mesas,
    COUNT(DISTINCT p.id) as total_produtos,
    COALESCE(SUM(ped.valor_total), 0) as receita_total,
    COUNT(DISTINCT ped.idpedido) as total_pedidos,
    f.created_at
FROM filiais f
JOIN tenants t ON f.tenant_id = t.id
LEFT JOIN usuarios_estabelecimento ue ON f.id = ue.filial_id AND ue.ativo = true
LEFT JOIN mesas m ON f.id = m.filial_id
LEFT JOIN produtos p ON f.id = p.filial_id AND p.ativo = true
LEFT JOIN pedido ped ON f.id = ped.filial_id
WHERE f.status = 'ativo'
GROUP BY f.id, f.nome, f.endereco, f.telefone, f.status, t.nome, f.created_at;

-- View para relatórios consolidados
CREATE OR REPLACE VIEW vw_relatorios_consolidados AS
SELECT 
    t.id as tenant_id,
    t.nome as estabelecimento,
    COUNT(DISTINCT f.id) as total_filiais,
    COUNT(DISTINCT ue.id) as total_usuarios,
    COUNT(DISTINCT m.id_mesa) as total_mesas,
    COUNT(DISTINCT p.id) as total_produtos,
    COALESCE(SUM(ped.valor_total), 0) as receita_total,
    COUNT(DISTINCT ped.idpedido) as total_pedidos,
    AVG(ped.valor_total) as ticket_medio,
    MAX(ped.created_at) as ultimo_pedido
FROM tenants t
LEFT JOIN filiais f ON t.id = f.tenant_id AND f.status = 'ativo'
LEFT JOIN usuarios_estabelecimento ue ON f.id = ue.filial_id AND ue.ativo = true
LEFT JOIN mesas m ON f.id = m.filial_id
LEFT JOIN produtos p ON f.id = p.filial_id AND p.ativo = true
LEFT JOIN pedido ped ON f.id = ped.filial_id
GROUP BY t.id, t.nome;

-- DADOS INICIAIS
-- =====================================================

-- Inserir filial padrão se não existir
INSERT INTO filiais (tenant_id, nome, endereco, telefone, email, status)
SELECT 
    t.id,
    'Filial Principal',
    'Endereço Principal',
    '(11) 99999-9999',
    'contato@divinolanches.com',
    'ativo'
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM filiais f WHERE f.tenant_id = t.id
)
LIMIT 1;

-- Configurações padrão para filiais
INSERT INTO filial_configuracoes (filial_id, chave, valor, tipo, descricao)
SELECT 
    f.id,
    'numero_mesas',
    '15',
    'number',
    'Número de mesas da filial'
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM filial_configuracoes fc WHERE fc.filial_id = f.id AND fc.chave = 'numero_mesas'
);

INSERT INTO filial_configuracoes (filial_id, chave, valor, tipo, descricao)
SELECT 
    f.id,
    'capacidade_mesa',
    '4',
    'number',
    'Capacidade padrão das mesas'
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM filial_configuracoes fc WHERE fc.filial_id = f.id AND fc.chave = 'capacidade_mesa'
);

INSERT INTO filial_configuracoes (filial_id, chave, valor, tipo, descricao)
SELECT 
    f.id,
    'cor_primaria',
    '#007bff',
    'string',
    'Cor primária da filial'
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM filial_configuracoes fc WHERE fc.filial_id = f.id AND fc.chave = 'cor_primaria'
);

-- Criar mesas padrão para filiais existentes
INSERT INTO mesas (numero, capacidade, tenant_id, filial_id)
SELECT 
    generate_series(1, 15)::text,
    4,
    f.tenant_id,
    f.id
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM mesas m WHERE m.filial_id = f.id
);

-- Criar categorias padrão para filiais existentes
INSERT INTO categorias (nome, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Lanches',
    'Hambúrgueres e lanches',
    '#ff6b6b',
    'fas fa-hamburger',
    f.tenant_id,
    f.id
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM categorias c WHERE c.filial_id = f.id AND c.nome = 'Lanches'
);

INSERT INTO categorias (nome, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Bebidas',
    'Refrigerantes e sucos',
    '#4ecdc4',
    'fas fa-glass-martini',
    f.tenant_id,
    f.id
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM categorias c WHERE c.filial_id = f.id AND c.nome = 'Bebidas'
);

INSERT INTO categorias (nome, descricao, cor, icone, tenant_id, filial_id)
SELECT 
    'Sobremesas',
    'Doces e sobremesas',
    '#ffe66d',
    'fas fa-ice-cream',
    f.tenant_id,
    f.id
FROM filiais f
WHERE NOT EXISTS (
    SELECT 1 FROM categorias c WHERE c.filial_id = f.id AND c.nome = 'Sobremesas'
);

-- COMENTÁRIOS DAS TABELAS
-- =====================================================

COMMENT ON TABLE filiais IS 'Filiais de cada estabelecimento';
COMMENT ON TABLE usuarios_estabelecimento IS 'Vinculação de usuários a estabelecimentos e filiais';
COMMENT ON TABLE filial_configuracoes IS 'Configurações específicas de cada filial';
COMMENT ON TABLE mesas IS 'Mesas de cada filial';
COMMENT ON TABLE produtos IS 'Produtos de cada filial';
COMMENT ON TABLE categorias IS 'Categorias de produtos de cada filial';
COMMENT ON TABLE pedido IS 'Pedidos de cada filial';
COMMENT ON TABLE pedido_itens IS 'Itens dos pedidos';
COMMENT ON TABLE relatorios_consolidados IS 'Relatórios consolidados do estabelecimento';
COMMENT ON TABLE filial_logs IS 'Logs de atividades das filiais';

-- COMENTÁRIOS DAS COLUNAS
-- =====================================================

COMMENT ON COLUMN filiais.tenant_id IS 'ID do estabelecimento proprietário';
COMMENT ON COLUMN filiais.status IS 'Status da filial: ativo, inativo, suspenso';
COMMENT ON COLUMN filiais.configuracao IS 'Configurações específicas da filial em JSON';

COMMENT ON COLUMN usuarios_estabelecimento.tipo_usuario IS 'Tipo de usuário: admin_estabelecimento, admin_filial, operador, etc.';
COMMENT ON COLUMN usuarios_estabelecimento.permissoes IS 'Permissões específicas do usuário em JSON';

COMMENT ON COLUMN mesas.status IS 'Status da mesa: livre, ocupada, reservada, manutencao';
COMMENT ON COLUMN mesas.posicao_x IS 'Posição X da mesa no layout';
COMMENT ON COLUMN mesas.posicao_y IS 'Posição Y da mesa no layout';

COMMENT ON COLUMN produtos.ingredientes IS 'Lista de ingredientes do produto em JSON';
COMMENT ON COLUMN produtos.estoque_atual IS 'Quantidade atual em estoque';
COMMENT ON COLUMN produtos.estoque_minimo IS 'Quantidade mínima para alerta';

COMMENT ON COLUMN pedido.delivery IS 'Se é pedido de delivery';
COMMENT ON COLUMN pedido.status IS 'Status do pedido';
COMMENT ON COLUMN pedido.valor_total IS 'Valor total do pedido';

COMMENT ON COLUMN pedido_itens.tamanho IS 'Tamanho do item: normal, mini, grande';
COMMENT ON COLUMN pedido_itens.observacao IS 'Observação específica do item';

COMMENT ON COLUMN relatorios_consolidados.filiais_incluidas IS 'Array com IDs das filiais incluídas no relatório';
COMMENT ON COLUMN relatorios_consolidados.dados IS 'Dados do relatório em JSON';

COMMENT ON COLUMN filial_logs.acao IS 'Ação realizada: criar, atualizar, deletar, etc.';
COMMENT ON COLUMN filial_logs.entidade IS 'Entidade afetada: produto, mesa, pedido, etc.';
COMMENT ON COLUMN filial_logs.entidade_id IS 'ID da entidade afetada';
COMMENT ON COLUMN filial_logs.dados_anteriores IS 'Dados antes da alteração em JSON';
COMMENT ON COLUMN filial_logs.dados_novos IS 'Dados após a alteração em JSON';



