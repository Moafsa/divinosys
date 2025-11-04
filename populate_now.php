<?php
/**
 * POPULATE FINANCIAL DATA FOR ALL EXISTING TENANTS
 * Access via: http://localhost:8080/populate_now.php
 */

require_once 'system/Database.php';

set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = \System\Database::getInstance();

echo "<!DOCTYPE html><html><head><title>Populate Financial Data</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#dcdcaa;}</style>";
echo "</head><body>";

echo "<h1 style='color:#569cd6;'>🚀 Populating Financial Data for All Tenants</h1>";
echo "<hr>";

try {
    // Get all active tenants
    $tenants = $db->fetchAll("SELECT id, nome FROM tenants WHERE status = 'ativo' ORDER BY id");
    
    if (empty($tenants)) {
        echo "<p class='error'>❌ No active tenants found!</p>";
        exit;
    }
    
    echo "<p class='info'>Found " . count($tenants) . " active tenant(s)</p><br>";
    
    foreach ($tenants as $tenant) {
        echo "<div style='margin-bottom:30px;border-left:3px solid #569cd6;padding-left:15px;'>";
        echo "<h2 style='color:#4ec9b0;'>Tenant: {$tenant['nome']} (ID: {$tenant['id']})</h2>";
        
        // Get first filial
        $filial = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenant['id']]);
        
        if (!$filial) {
            echo "<p class='error'>  ⚠️ No filial found for tenant {$tenant['id']}, skipping...</p>";
            echo "</div>";
            continue;
        }
        
        $filial_id = $filial['id'];
        echo "<p class='info'>  Using Filial ID: {$filial_id}</p>";
        
        // Check existing categories
        $cat_count = $db->fetch("SELECT COUNT(*) as total FROM categorias_financeiras WHERE tenant_id = ?", [$tenant['id']]);
        
        if ($cat_count['total'] > 0) {
            echo "<p class='info'>  ℹ️  Already has {$cat_count['total']} financial categories, skipping category creation</p>";
        } else {
            echo "<p class='info'>  Creating financial categories...</p>";
            
            $categorias = [
                ['Vendas Mesa', 'receita', 'Receitas de vendas em mesa', '#28a745', 'fas fa-table'],
                ['Vendas Delivery', 'receita', 'Receitas de vendas delivery', '#17a2b8', 'fas fa-motorcycle'],
                ['Vendas Balcão', 'receita', 'Receitas de vendas no balcão', '#20c997', 'fas fa-store'],
                ['Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', '#ffc107', 'fas fa-credit-card'],
                ['Outras Receitas', 'receita', 'Outras receitas diversas', '#6f42c1', 'fas fa-plus-circle'],
                ['Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', '#dc3545', 'fas fa-tools'],
                ['Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', '#fd7e14', 'fas fa-bullhorn'],
                ['Salários', 'despesa', 'Pagamento de salários e encargos', '#6610f2', 'fas fa-users'],
                ['Aluguel', 'despesa', 'Aluguel do estabelecimento', '#e83e8c', 'fas fa-building'],
                ['Contas (Água, Luz, Internet)', 'despesa', 'Contas de consumo', '#6c757d', 'fas fa-file-invoice-dollar']
            ];
            
            $inserted = 0;
            foreach ($categorias as $cat) {
                try {
                    $db->insert('categorias_financeiras', [
                        'nome' => $cat[0],
                        'tipo' => $cat[1],
                        'descricao' => $cat[2],
                        'cor' => $cat[3],
                        'icone' => $cat[4],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial_id,
                        'ativo' => true
                    ]);
                    $inserted++;
                } catch (Exception $e) {
                    echo "<p class='error'>    ⚠️ Error inserting category '{$cat[0]}': {$e->getMessage()}</p>";
                }
            }
            
            echo "<p class='success'>  ✅ Created {$inserted} financial categories</p>";
        }
        
        // Check existing accounts
        $acc_count = $db->fetch("SELECT COUNT(*) as total FROM contas_financeiras WHERE tenant_id = ?", [$tenant['id']]);
        
        if ($acc_count['total'] > 0) {
            echo "<p class='info'>  ℹ️  Already has {$acc_count['total']} financial accounts, skipping account creation</p>";
        } else {
            echo "<p class='info'>  Creating financial accounts...</p>";
            
            $contas = [
                ['Caixa Principal', 'caixa', '#28a745', 'fas fa-cash-register'],
                ['Conta Corrente', 'banco', '#007bff', 'fas fa-university'],
                ['PIX', 'pix', '#17a2b8', 'fas fa-mobile-alt'],
                ['Cartão de Crédito', 'cartao', '#dc3545', 'fas fa-credit-card']
            ];
            
            $inserted = 0;
            foreach ($contas as $conta) {
                try {
                    $db->insert('contas_financeiras', [
                        'nome' => $conta[0],
                        'tipo' => $conta[1],
                        'saldo_inicial' => 0.00,
                        'saldo_atual' => 0.00,
                        'cor' => $conta[2],
                        'icone' => $conta[3],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial_id,
                        'ativo' => true
                    ]);
                    $inserted++;
                } catch (Exception $e) {
                    echo "<p class='error'>    ⚠️ Error inserting account '{$conta[0]}': {$e->getMessage()}</p>";
                }
            }
            
            echo "<p class='success'>  ✅ Created {$inserted} financial accounts</p>";
        }
        
        echo "<p class='success'>  ✅ Tenant processing complete</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2 style='color:#4ec9b0;'>📊 Final Summary</h2>";
    
    // Verification query
    $summary = $db->fetchAll("
        SELECT 
            t.id,
            t.nome,
            COUNT(DISTINCT cf.id) AS categories,
            COUNT(DISTINCT cof.id) AS accounts
        FROM tenants t
        LEFT JOIN categorias_financeiras cf ON cf.tenant_id = t.id
        LEFT JOIN contas_financeiras cof ON cof.tenant_id = t.id
        WHERE t.status = 'ativo'
        GROUP BY t.id, t.nome
        ORDER BY t.id
    ");
    
    echo "<table style='border-collapse:collapse;width:100%;margin-top:20px;'>";
    echo "<tr style='background:#264f78;'>";
    echo "<th style='padding:10px;border:1px solid #3c3c3c;'>Tenant ID</th>";
    echo "<th style='padding:10px;border:1px solid #3c3c3c;'>Tenant Name</th>";
    echo "<th style='padding:10px;border:1px solid #3c3c3c;'>Categories</th>";
    echo "<th style='padding:10px;border:1px solid #3c3c3c;'>Accounts</th>";
    echo "<th style='padding:10px;border:1px solid #3c3c3c;'>Status</th>";
    echo "</tr>";
    
    foreach ($summary as $row) {
        $status = ($row['categories'] > 0 && $row['accounts'] > 0) ? '✅ OK' : '❌ Missing';
        $color = ($row['categories'] > 0 && $row['accounts'] > 0) ? '#4ec9b0' : '#f48771';
        
        echo "<tr>";
        echo "<td style='padding:10px;border:1px solid #3c3c3c;'>{$row['id']}</td>";
        echo "<td style='padding:10px;border:1px solid #3c3c3c;'>{$row['nome']}</td>";
        echo "<td style='padding:10px;border:1px solid #3c3c3c;text-align:center;'>{$row['categories']}</td>";
        echo "<td style='padding:10px;border:1px solid #3c3c3c;text-align:center;'>{$row['accounts']}</td>";
        echo "<td style='padding:10px;border:1px solid #3c3c3c;color:{$color};font-weight:bold;'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 style='color:#4ec9b0;'>✅ DONE!</h2>";
    echo "<p class='success'>All tenants have been processed. You can now:</p>";
    echo "<ul>";
    echo "<li><a href='index.php?view=novo_lancamento' style='color:#569cd6;'>Go to Novo Lançamento</a> and check if categories are populated</li>";
    echo "<li><a href='index.php' style='color:#569cd6;'>Go to Dashboard</a></li>";
    echo "</ul>";
    echo "<p style='color:#ce9178;'>⚠️ You can safely delete this file (populate_now.php) after use.</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ FATAL ERROR: {$e->getMessage()}</p>";
    echo "<pre style='color:#f48771;'>{$e->getTraceAsString()}</pre>";
}

echo "</body></html>";
?>

