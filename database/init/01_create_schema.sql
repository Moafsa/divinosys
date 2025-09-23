-- Create database if not exists
-- This will be handled by Docker environment variables

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create tenants table
CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE,
    domain VARCHAR(255),
    cnpj VARCHAR(18),
    telefone VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    logo_url VARCHAR(500),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'suspenso')),
    plano_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create plans table
CREATE TABLE IF NOT EXISTS planos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    max_mesas INTEGER DEFAULT 10,
    max_usuarios INTEGER DEFAULT 3,
    max_produtos INTEGER DEFAULT 100,
    max_pedidos_mes INTEGER DEFAULT 1000,
    recursos JSONB,
    preco_mensal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create filiais table
CREATE TABLE IF NOT EXISTS filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nome VARCHAR(255) NOT NULL,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    cnpj VARCHAR(18),
    logo_url VARCHAR(500),
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create usuarios table (multi-tenant)
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nivel INTEGER NOT NULL DEFAULT 1,
    pergunta VARCHAR(255) NOT NULL,
    resposta VARCHAR(255) NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(login, tenant_id)
);

-- Create categorias table (multi-tenant)
CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagem VARCHAR(255),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create ingredientes table (multi-tenant)
CREATE TABLE IF NOT EXISTS ingredientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('pao', 'proteina', 'queijo', 'salada', 'molho', 'complemento')),
    preco_adicional DECIMAL(10,2) DEFAULT 0.00,
    disponivel BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    UNIQUE(nome, tenant_id)
);

-- Create produtos table (multi-tenant)
CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(255),
    categoria_id INTEGER NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco_normal DECIMAL(10,2) NOT NULL,
    preco_mini DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    imagem VARCHAR(255),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    UNIQUE(codigo, tenant_id)
);

-- Create produto_ingredientes table
CREATE TABLE IF NOT EXISTS produto_ingredientes (
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    ingrediente_id INTEGER NOT NULL REFERENCES ingredientes(id) ON DELETE CASCADE,
    padrao BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (produto_id, ingrediente_id)
);

-- Create mesas table (multi-tenant)
CREATE TABLE IF NOT EXISTS mesas (
    id SERIAL PRIMARY KEY,
    id_mesa VARCHAR(255) NOT NULL,
    nome VARCHAR(255),
    status VARCHAR(255) NOT NULL DEFAULT '1',
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    UNIQUE(id_mesa, tenant_id, filial_id)
);

-- Create clientes table (multi-tenant)
CREATE TABLE IF NOT EXISTS clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(50),
    complemento VARCHAR(100),
    cep VARCHAR(20),
    ponto_referencia VARCHAR(100),
    tel1 VARCHAR(20),
    tel2 VARCHAR(20),
    email VARCHAR(100),
    cpf_cnpj VARCHAR(30),
    rg VARCHAR(30),
    condominio VARCHAR(100),
    bloco VARCHAR(50),
    apartamento VARCHAR(50),
    local_entrega VARCHAR(100),
    observacoes VARCHAR(255),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create entregadores table (multi-tenant)
CREATE TABLE IF NOT EXISTS entregadores (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Ativo' CHECK (status IN ('Ativo', 'Inativo', 'Em Entrega')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create pedido table (multi-tenant)
CREATE TABLE IF NOT EXISTS pedido (
    idpedido SERIAL PRIMARY KEY,
    idmesa INTEGER,
    cliente VARCHAR(255),
    delivery BOOLEAN DEFAULT false,
    endereco_entrega TEXT,
    ponto_referencia VARCHAR(255),
    telefone_cliente VARCHAR(20),
    data DATE NOT NULL,
    hora_pedido TIME NOT NULL,
    hora_saida_entrega TIME,
    hora_entrega TIME,
    status VARCHAR(50) NOT NULL DEFAULT 'Pendente',
    valor_total DECIMAL(10,2) DEFAULT 0.00,
    taxa_entrega DECIMAL(10,2) DEFAULT 0.00,
    forma_pagamento VARCHAR(50),
    troco_para DECIMAL(10,2),
    entregador_id INTEGER REFERENCES entregadores(id) ON DELETE SET NULL,
    observacao TEXT,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tipo VARCHAR(20) DEFAULT 'mesa',
    cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create pedido_itens table (multi-tenant)
CREATE TABLE IF NOT EXISTS pedido_itens (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade INTEGER NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    observacao TEXT,
    ingredientes_sem TEXT,
    ingredientes_com TEXT,
    tamanho VARCHAR(10) NOT NULL DEFAULT 'normal',
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create pedido_item_ingredientes table
CREATE TABLE IF NOT EXISTS pedido_item_ingredientes (
    id SERIAL PRIMARY KEY,
    pedido_item_id INTEGER NOT NULL REFERENCES pedido_itens(id) ON DELETE CASCADE,
    ingrediente_id INTEGER NOT NULL REFERENCES ingredientes(id) ON DELETE CASCADE,
    incluido BOOLEAN NOT NULL DEFAULT true
);

-- Create estoque table (multi-tenant)
CREATE TABLE IF NOT EXISTS estoque (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    estoque_atual DECIMAL(10,2) DEFAULT 0.00,
    estoque_minimo DECIMAL(10,2) DEFAULT 0.00,
    preco_custo DECIMAL(10,2),
    marca VARCHAR(100),
    fornecedor VARCHAR(100),
    data_compra DATE,
    data_validade DATE,
    unidade VARCHAR(10),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create log_pedidos table (multi-tenant)
CREATE TABLE IF NOT EXISTS log_pedidos (
    id SERIAL PRIMARY KEY,
    idpedido INTEGER NOT NULL REFERENCES pedido(idpedido) ON DELETE CASCADE,
    status_anterior VARCHAR(50),
    novo_status VARCHAR(50),
    usuario VARCHAR(100),
    data_alteracao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create categorias_financeiras table (multi-tenant)
CREATE TABLE IF NOT EXISTS categorias_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create contas_financeiras table (multi-tenant)
CREATE TABLE IF NOT EXISTS contas_financeiras (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('conta_corrente', 'poupanca', 'carteira', 'outros')),
    saldo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    saldo_atual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    banco VARCHAR(100),
    agencia VARCHAR(20),
    conta VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create movimentacoes_financeiras table (multi-tenant)
CREATE TABLE IF NOT EXISTS movimentacoes_financeiras (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER REFERENCES pedido(idpedido) ON DELETE CASCADE,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
    categoria_id INTEGER NOT NULL REFERENCES categorias_financeiras(id) ON DELETE CASCADE,
    conta_id INTEGER NOT NULL REFERENCES contas_financeiras(id) ON DELETE CASCADE,
    valor DECIMAL(10,2) NOT NULL,
    data_movimentacao DATE NOT NULL,
    data_vencimento DATE,
    descricao TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'cancelado')),
    forma_pagamento VARCHAR(20),
    comprovante VARCHAR(255),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create parcelas_financeiras table
CREATE TABLE IF NOT EXISTS parcelas_financeiras (
    id SERIAL PRIMARY KEY,
    movimentacao_id INTEGER NOT NULL REFERENCES movimentacoes_financeiras(id) ON DELETE CASCADE,
    numero_parcela INTEGER NOT NULL,
    total_parcelas INTEGER NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'cancelado')),
    data_pagamento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create imagens_movimentacoes table
CREATE TABLE IF NOT EXISTS imagens_movimentacoes (
    id SERIAL PRIMARY KEY,
    movimentacao_id INTEGER NOT NULL REFERENCES movimentacoes_financeiras(id) ON DELETE CASCADE,
    caminho VARCHAR(255) NOT NULL
);

-- Create perfil_estabelecimento table (multi-tenant)
CREATE TABLE IF NOT EXISTS perfil_estabelecimento (
    id SERIAL PRIMARY KEY,
    nome_estabelecimento VARCHAR(120) NOT NULL,
    cnpj VARCHAR(24),
    endereco VARCHAR(255),
    telefone VARCHAR(32),
    site VARCHAR(120),
    mensagem_header VARCHAR(255),
    logo VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create atividade table (multi-tenant)
CREATE TABLE IF NOT EXISTS atividade (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    atividade VARCHAR(255) NOT NULL,
    ordem INTEGER NOT NULL,
    condicao INTEGER NOT NULL,
    start TIMESTAMP,
    color VARCHAR(10),
    "end" TIMESTAMP,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create cor table (multi-tenant)
CREATE TABLE IF NOT EXISTS cor (
    id INTEGER NOT NULL,
    cor VARCHAR(255) NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create despesas table (multi-tenant) - Legacy table
CREATE TABLE IF NOT EXISTS despesas (
    id SERIAL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL,
    despesa VARCHAR(255) NOT NULL,
    data VARCHAR(100) NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create vendas table (multi-tenant) - Legacy table
CREATE TABLE IF NOT EXISTS vendas (
    id SERIAL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL,
    cliente VARCHAR(255) NOT NULL,
    rendimento VARCHAR(255) NOT NULL,
    data VARCHAR(255) NOT NULL,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_usuarios_tenant ON usuarios(tenant_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_filial ON usuarios(filial_id);
CREATE INDEX IF NOT EXISTS idx_produtos_tenant ON produtos(tenant_id);
CREATE INDEX IF NOT EXISTS idx_produtos_filial ON produtos(filial_id);
CREATE INDEX IF NOT EXISTS idx_pedido_tenant ON pedido(tenant_id);
CREATE INDEX IF NOT EXISTS idx_pedido_filial ON pedido(filial_id);
CREATE INDEX IF NOT EXISTS idx_pedido_status ON pedido(status);
CREATE INDEX IF NOT EXISTS idx_pedido_data ON pedido(data);
CREATE INDEX IF NOT EXISTS idx_mesas_tenant ON mesas(tenant_id);
CREATE INDEX IF NOT EXISTS idx_mesas_filial ON mesas(filial_id);
CREATE INDEX IF NOT EXISTS idx_estoque_produto ON estoque(produto_id);
CREATE INDEX IF NOT EXISTS idx_estoque_atual ON estoque(estoque_atual);
CREATE INDEX IF NOT EXISTS idx_estoque_validade ON estoque(data_validade);

-- Create updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_usuarios_updated_at BEFORE UPDATE ON usuarios FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_entregadores_updated_at BEFORE UPDATE ON entregadores FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_pedido_updated_at BEFORE UPDATE ON pedido FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_estoque_updated_at BEFORE UPDATE ON estoque FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_categorias_financeiras_updated_at BEFORE UPDATE ON categorias_financeiras FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_contas_financeiras_updated_at BEFORE UPDATE ON contas_financeiras FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_movimentacoes_financeiras_updated_at BEFORE UPDATE ON movimentacoes_financeiras FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_parcelas_financeiras_updated_at BEFORE UPDATE ON parcelas_financeiras FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_tenants_updated_at BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
