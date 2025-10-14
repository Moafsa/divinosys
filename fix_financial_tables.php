<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;

    echo "Iniciando correção das tabelas financeiras...\n";

    // Adicionar colunas que faltam na tabela categorias_financeiras
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#007bff'");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fas fa-tag'");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS pai_id INTEGER REFERENCES categorias_financeiras(id) ON DELETE SET NULL");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $db->query("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    echo "Colunas adicionadas à tabela categorias_financeiras\n";

    // Adicionar colunas que faltam na tabela contas_financeiras
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS limite DECIMAL(10,2) DEFAULT 0.00");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#28a745'");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fas fa-wallet'");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $db->query("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    echo "Colunas adicionadas à tabela contas_financeiras\n";

    // Inserir categorias financeiras padrão
    $categorias = [
        ['Vendas Mesa', 'receita', 'Receitas de vendas em mesa', '#28a745', 'fas fa-table'],
        ['Vendas Delivery', 'receita', 'Receitas de vendas delivery', '#17a2b8', 'fas fa-motorcycle'],
        ['Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', '#ffc107', 'fas fa-credit-card'],
        ['Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', '#dc3545', 'fas fa-tools'],
        ['Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', '#6f42c1', 'fas fa-bullhorn'],
        ['Salários', 'despesa', 'Pagamento de salários e encargos', '#fd7e14', 'fas fa-users'],
        ['Aluguel', 'despesa', 'Aluguel do estabelecimento', '#20c997', 'fas fa-building'],
        ['Energia Elétrica', 'despesa', 'Contas de energia elétrica', '#ffc107', 'fas fa-bolt'],
        ['Água', 'despesa', 'Contas de água', '#17a2b8', 'fas fa-tint'],
        ['Internet', 'despesa', 'Contas de internet e telefone', '#6c757d', 'fas fa-wifi']
    ];

    foreach ($categorias as $categoria) {
        $db->query(
            "INSERT INTO categorias_financeiras (nome, tipo, descricao, cor, icone, tenant_id, filial_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?) 
             ON CONFLICT DO NOTHING",
            [$categoria[0], $categoria[1], $categoria[2], $categoria[3], $categoria[4], $tenantId, $filialId]
        );
    }
    
    echo "Categorias financeiras inseridas\n";

    // Inserir contas financeiras padrão
    $contas = [
        ['Caixa Principal', 'carteira', 0.00, 0.00, '#28a745', 'fas fa-cash-register'],
        ['Conta Corrente', 'conta_corrente', 0.00, 0.00, '#007bff', 'fas fa-university'],
        ['PIX', 'outros', 0.00, 0.00, '#17a2b8', 'fas fa-mobile-alt'],
        ['Cartão de Crédito', 'outros', 0.00, 0.00, '#dc3545', 'fas fa-credit-card']
    ];

    foreach ($contas as $conta) {
        $db->query(
            "INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, cor, icone, tenant_id, filial_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
             ON CONFLICT DO NOTHING",
            [$conta[0], $conta[1], $conta[2], $conta[3], $conta[4], $conta[5], $tenantId, $filialId]
        );
    }
    
    echo "Contas financeiras inseridas\n";

    echo "Correção das tabelas financeiras concluída com sucesso!\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
