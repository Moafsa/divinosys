<?php
/**
 * Inserir dados padrão de contas e categorias financeiras
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Buscar primeiro tenant e filial
    $tenant = $db->fetch("SELECT id FROM tenants ORDER BY id LIMIT 1");
    $filial = $db->fetch("SELECT id FROM filiais ORDER BY id LIMIT 1");
    
    if (!$tenant || !$filial) {
        throw new Exception("Nenhum tenant ou filial encontrado");
    }
    
    $tenantId = $tenant['id'];
    $filialId = $filial['id'];
    
    // Verificar se já existem categorias
    $categoriasExistentes = $db->fetch("SELECT COUNT(*) as count FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    
    if ($categoriasExistentes['count'] == 0) {
        // Inserir categorias padrão
        $categorias = [
            ['nome' => 'Vendas Mesa', 'tipo' => 'receita', 'descricao' => 'Receitas de vendas em mesa', 'cor' => '#28a745', 'icone' => 'fas fa-table'],
            ['nome' => 'Vendas Delivery', 'tipo' => 'receita', 'descricao' => 'Receitas de vendas delivery', 'cor' => '#17a2b8', 'icone' => 'fas fa-motorcycle'],
            ['nome' => 'Vendas Fiadas', 'tipo' => 'receita', 'descricao' => 'Receitas de vendas fiadas', 'cor' => '#ffc107', 'icone' => 'fas fa-credit-card'],
            ['nome' => 'Despesas Operacionais', 'tipo' => 'despesa', 'descricao' => 'Despesas operacionais do estabelecimento', 'cor' => '#dc3545', 'icone' => 'fas fa-tools'],
            ['nome' => 'Despesas de Marketing', 'tipo' => 'despesa', 'descricao' => 'Despesas de marketing e publicidade', 'cor' => '#6f42c1', 'icone' => 'fas fa-bullhorn'],
            ['nome' => 'Salários', 'tipo' => 'despesa', 'descricao' => 'Pagamento de salários e encargos', 'cor' => '#fd7e14', 'icone' => 'fas fa-users'],
            ['nome' => 'Aluguel', 'tipo' => 'despesa', 'descricao' => 'Aluguel do estabelecimento', 'cor' => '#20c997', 'icone' => 'fas fa-building'],
            ['nome' => 'Energia Elétrica', 'tipo' => 'despesa', 'descricao' => 'Contas de energia elétrica', 'cor' => '#ffc107', 'icone' => 'fas fa-bolt'],
            ['nome' => 'Água', 'tipo' => 'despesa', 'descricao' => 'Contas de água', 'cor' => '#17a2b8', 'icone' => 'fas fa-tint'],
            ['nome' => 'Internet', 'tipo' => 'despesa', 'descricao' => 'Contas de internet e telefone', 'cor' => '#6c757d', 'icone' => 'fas fa-wifi']
        ];
        
        foreach ($categorias as $cat) {
            try {
                $db->insert('categorias_financeiras', array_merge($cat, [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'ativo' => true
                ]));
                $results[] = "✅ Categoria criada: {$cat['nome']}";
            } catch (Exception $e) {
                $results[] = "⚠️ Erro ao criar categoria {$cat['nome']}: " . substr($e->getMessage(), 0, 50);
            }
        }
    } else {
        $results[] = "⏭️ Categorias já existem ({$categoriasExistentes['count']} encontradas)";
    }
    
    // Verificar se já existem contas
    $contasExistentes = $db->fetch("SELECT COUNT(*) as count FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    
    if ($contasExistentes['count'] == 0) {
        // Inserir contas padrão
        $contas = [
            ['nome' => 'Caixa Principal', 'tipo' => 'caixa', 'cor' => '#28a745', 'icone' => 'fas fa-cash-register'],
            ['nome' => 'Conta Corrente', 'tipo' => 'banco', 'cor' => '#007bff', 'icone' => 'fas fa-university'],
            ['nome' => 'PIX', 'tipo' => 'pix', 'cor' => '#17a2b8', 'icone' => 'fas fa-mobile-alt'],
            ['nome' => 'Cartão de Crédito', 'tipo' => 'cartao', 'cor' => '#dc3545', 'icone' => 'fas fa-credit-card']
        ];
        
        foreach ($contas as $conta) {
            try {
                $db->insert('contas_financeiras', array_merge($conta, [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'saldo_inicial' => 0.00,
                    'saldo_atual' => 0.00,
                    'ativo' => true
                ]));
                $results[] = "✅ Conta criada: {$conta['nome']}";
            } catch (Exception $e) {
                $results[] = "⚠️ Erro ao criar conta {$conta['nome']}: " . substr($e->getMessage(), 0, 50);
            }
        }
    } else {
        $results[] = "⏭️ Contas já existem ({$contasExistentes['count']} encontradas)";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados padrão inseridos com sucesso!',
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

