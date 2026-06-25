-- Migration: populate_categorias_contas_financeiras
-- Purpose: Populate categorias_financeiras and contas_financeiras tables with default data for all existing tenants and filiais

-- Insert default categories for all tenant/filial combinations
INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, ativo, tenant_id, filial_id, created_at, updated_at)
SELECT 
    cat.nome,
    cat.tipo,
    cat.descricao,
    cat.cor,
    true as ativo,
    t.id as tenant_id,
    f.id as filial_id,
    CURRENT_TIMESTAMP as created_at,
    CURRENT_TIMESTAMP as updated_at
FROM (
    VALUES 
        ('Vendas', 'receita', 'Receitas de vendas de produtos', '#28a745'),
        ('Serviços', 'receita', 'Receitas de prestação de serviços', '#17a2b8'),
        ('Outras Receitas', 'receita', 'Outras fontes de receita', '#6f42c1'),
        ('Fornecedores', 'despesa', 'Pagamentos a fornecedores', '#dc3545'),
        ('Salários', 'despesa', 'Pagamento de salários e encargos', '#fd7e14'),
        ('Aluguel', 'despesa', 'Pagamento de aluguel', '#e83e8c'),
        ('Utilidades', 'despesa', 'Contas de água, luz, telefone, internet', '#ffc107'),
        ('Outras Despesas', 'despesa', 'Outras despesas operacionais', '#6c757d')
) AS cat(nome, tipo, descricao, cor)
CROSS JOIN tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
  AND NOT EXISTS (
      SELECT 1 FROM categorias_financeiras cf 
      WHERE cf.tenant_id = t.id 
        AND cf.filial_id = f.id 
        AND cf.nome = cat.nome
        AND cf.tipo = cat.tipo
  );

-- Insert default accounts for all tenant/filial combinations
INSERT INTO contas_financeiras (nome, tipo, saldo_atual, ativo, tenant_id, filial_id, created_at, updated_at)
SELECT 
    acc.nome,
    acc.tipo,
    0.00 as saldo_atual,
    true as ativo,
    t.id as tenant_id,
    f.id as filial_id,
    CURRENT_TIMESTAMP as created_at,
    CURRENT_TIMESTAMP as updated_at
FROM (
    VALUES 
        ('Caixa', 'caixa'),
        ('Conta Corrente', 'banco'),
        ('Poupança', 'banco'),
        ('Cartão de Crédito', 'cartao'),
        ('Cartão de Débito', 'cartao')
) AS acc(nome, tipo)
CROSS JOIN tenants t
CROSS JOIN filiais f
WHERE f.tenant_id = t.id
  AND NOT EXISTS (
      SELECT 1 FROM contas_financeiras cf 
      WHERE cf.tenant_id = t.id 
        AND cf.filial_id = f.id 
        AND cf.nome = acc.nome
        AND cf.tipo = acc.tipo
  );

-- Verify the migration
SELECT 
    'Categorias inseridas' as tipo,
    COUNT(*) as total
FROM categorias_financeiras
UNION ALL
SELECT 
    'Contas inseridas' as tipo,
    COUNT(*) as total
FROM contas_financeiras;
