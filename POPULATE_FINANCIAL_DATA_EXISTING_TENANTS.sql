-- =====================================================
-- POPULATE FINANCIAL DATA FOR EXISTING TENANTS
-- =====================================================
-- Run this SQL on existing databases to create financial
-- categories and accounts for tenants that don't have them yet.
-- 
-- This script is IDEMPOTENT (safe to run multiple times)
--
-- Date: 2025-11-04
-- =====================================================

DO $$
DECLARE
    tenant_record RECORD;
    filial_record RECORD;
    categoria_count INTEGER;
    conta_count INTEGER;
BEGIN
    RAISE NOTICE 'Starting financial data population for existing tenants...';
    
    -- Loop through all active tenants
    FOR tenant_record IN 
        SELECT id, nome FROM tenants WHERE status = 'ativo' ORDER BY id
    LOOP
        RAISE NOTICE '----------------------------------------';
        RAISE NOTICE 'Processing Tenant: % (ID: %)', tenant_record.nome, tenant_record.id;
        
        -- Get first filial for this tenant
        SELECT id INTO filial_record 
        FROM filiais 
        WHERE tenant_id = tenant_record.id 
        ORDER BY id LIMIT 1;
        
        IF filial_record.id IS NULL THEN
            RAISE NOTICE '  WARNING: No filial found for tenant %, skipping...', tenant_record.id;
            CONTINUE;
        END IF;
        
        RAISE NOTICE '  Using Filial ID: %', filial_record.id;
        
        -- Check if tenant already has financial categories
        SELECT COUNT(*) INTO categoria_count
        FROM categorias_financeiras
        WHERE tenant_id = tenant_record.id;
        
        IF categoria_count > 0 THEN
            RAISE NOTICE '  INFO: Tenant already has % financial categories, skipping category creation', categoria_count;
        ELSE
            RAISE NOTICE '  Creating financial categories...';
            
            -- Insert financial categories
            INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id, ativo) VALUES
            ('Vendas Mesa', 'receita', 'Receitas de vendas em mesa', '#28a745', 'fas fa-table', tenant_record.id, filial_record.id, true),
            ('Vendas Delivery', 'receita', 'Receitas de vendas delivery', '#17a2b8', 'fas fa-motorcycle', tenant_record.id, filial_record.id, true),
            ('Vendas Balcão', 'receita', 'Receitas de vendas no balcão', '#20c997', 'fas fa-store', tenant_record.id, filial_record.id, true),
            ('Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', '#ffc107', 'fas fa-credit-card', tenant_record.id, filial_record.id, true),
            ('Outras Receitas', 'receita', 'Outras receitas diversas', '#6f42c1', 'fas fa-plus-circle', tenant_record.id, filial_record.id, true),
            ('Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', '#dc3545', 'fas fa-tools', tenant_record.id, filial_record.id, true),
            ('Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', '#fd7e14', 'fas fa-bullhorn', tenant_record.id, filial_record.id, true),
            ('Salários', 'despesa', 'Pagamento de salários e encargos', '#6610f2', 'fas fa-users', tenant_record.id, filial_record.id, true),
            ('Aluguel', 'despesa', 'Aluguel do estabelecimento', '#e83e8c', 'fas fa-building', tenant_record.id, filial_record.id, true),
            ('Contas (Água, Luz, Internet)', 'despesa', 'Contas de consumo', '#6c757d', 'fas fa-file-invoice-dollar', tenant_record.id, filial_record.id, true);
            
            RAISE NOTICE '  ✅ Created 10 financial categories';
        END IF;
        
        -- Check if tenant already has financial accounts
        SELECT COUNT(*) INTO conta_count
        FROM contas_financeiras
        WHERE tenant_id = tenant_record.id;
        
        IF conta_count > 0 THEN
            RAISE NOTICE '  INFO: Tenant already has % financial accounts, skipping account creation', conta_count;
        ELSE
            RAISE NOTICE '  Creating financial accounts...';
            
            -- Insert financial accounts
            INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id, ativo) VALUES
            ('Caixa Principal', 'caixa', 0.00, 0.00, '#28a745', 'fas fa-cash-register', tenant_record.id, filial_record.id, true),
            ('Conta Corrente', 'banco', 0.00, 0.00, '#007bff', 'fas fa-university', tenant_record.id, filial_record.id, true),
            ('PIX', 'pix', 0.00, 0.00, '#17a2b8', 'fas fa-mobile-alt', tenant_record.id, filial_record.id, true),
            ('Cartão de Crédito', 'cartao', 0.00, 0.00, '#dc3545', 'fas fa-credit-card', tenant_record.id, filial_record.id, true);
            
            RAISE NOTICE '  ✅ Created 4 financial accounts';
        END IF;
        
        RAISE NOTICE '  ✅ Tenant % processing complete', tenant_record.nome;
    END LOOP;
    
    RAISE NOTICE '========================================';
    RAISE NOTICE '✅ Financial data population completed!';
    RAISE NOTICE '========================================';
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '❌ ERROR: %', SQLERRM;
        RAISE EXCEPTION 'Failed to populate financial data: %', SQLERRM;
END $$;

-- Verify results
SELECT 
    t.id AS tenant_id,
    t.nome AS tenant_name,
    COUNT(DISTINCT cf.id) AS financial_categories,
    COUNT(DISTINCT cof.id) AS financial_accounts
FROM tenants t
LEFT JOIN categorias_financeiras cf ON cf.tenant_id = t.id
LEFT JOIN contas_financeiras cof ON cof.tenant_id = t.id
WHERE t.status = 'ativo'
GROUP BY t.id, t.nome
ORDER BY t.id;

