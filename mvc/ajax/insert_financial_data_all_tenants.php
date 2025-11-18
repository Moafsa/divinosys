<?php
/**
 * Inserir dados padrão de contas e categorias financeiras para TODOS os tenants/filiais
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    
    // Buscar todos os tenants e suas filiais
    $tenants = $db->fetchAll("SELECT id FROM tenants ORDER BY id");
    
    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        
        // Buscar filiais do tenant
        $filiais = $db->fetchAll("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id", [$tenantId]);
        
        if (empty($filiais)) {
            $results[] = "⚠️ Tenant $tenantId não tem filiais";
            continue;
        }
        
        foreach ($filiais as $filial) {
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
                    } catch (Exception $e) {
                        // Ignorar duplicatas
                    }
                }
                $results[] = "✅ Tenant $tenantId/Filial $filialId: {$categoriasExistentes['count']} categorias criadas";
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
                    } catch (Exception $e) {
                        // Ignorar duplicatas
                    }
                }
                $results[] = "✅ Tenant $tenantId/Filial $filialId: 4 contas criadas";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Dados padrão inseridos para todos os tenants/filiais!',
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

