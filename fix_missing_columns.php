<?php
/**
 * Script para adicionar colunas faltantes nas tabelas financeiras
 */

// Configurar para mostrar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Conectar ao banco
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'postgres';
    
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔧 Adicionando Colunas Faltantes</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Adicionar colunas cor e icone na tabela categorias_financeiras
    echo "<h2>1. Corrigindo categorias_financeiras...</h2>";
    
    $alter_categorias = "
    ALTER TABLE categorias_financeiras 
    ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#007bff',
    ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fas fa-tag';
    ";
    
    try {
        $pdo->exec($alter_categorias);
        echo "<p>✅ Colunas adicionadas em categorias_financeiras</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 2. Adicionar colunas cor e icone na tabela contas_financeiras
    echo "<h2>2. Corrigindo contas_financeiras...</h2>";
    
    $alter_contas = "
    ALTER TABLE contas_financeiras 
    ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#28a745',
    ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fas fa-wallet';
    ";
    
    try {
        $pdo->exec($alter_contas);
        echo "<p>✅ Colunas adicionadas em contas_financeiras</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 3. Inserir dados iniciais
    echo "<h2>3. Inserindo dados iniciais...</h2>";
    
    // Categorias padrão
    $categorias = [
        ['Vendas', 'receita', '#28a745', 'fas fa-shopping-cart'],
        ['Taxa de entrega', 'receita', '#17a2b8', 'fas fa-truck'],
        ['Ingredientes', 'despesa', '#dc3545', 'fas fa-apple-alt'],
        ['Salários', 'despesa', '#fd7e14', 'fas fa-users'],
        ['Aluguel', 'despesa', '#6f42c1', 'fas fa-building'],
        ['Energia elétrica', 'despesa', '#ffc107', 'fas fa-bolt'],
        ['Água', 'despesa', '#20c997', 'fas fa-tint'],
        ['Internet', 'despesa', '#6c757d', 'fas fa-wifi'],
        ['Marketing', 'despesa', '#e83e8c', 'fas fa-bullhorn'],
        ['Manutenção', 'despesa', '#fd7e14', 'fas fa-tools']
    ];
    
    $stmt_cat = $pdo->prepare("INSERT INTO categorias_financeiras (nome, tipo, cor, icone, tenant_id, filial_id) VALUES (?, ?, ?, ?, 1, 1) ON CONFLICT DO NOTHING");
    
    foreach ($categorias as $cat) {
        try {
            $stmt_cat->execute($cat);
            echo "<p>✅ Categoria '{$cat[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // Contas padrão
    $contas = [
        ['Caixa Principal', 'caixa', '#28a745', 'fas fa-cash-register'],
        ['Banco do Brasil', 'banco', '#007bff', 'fas fa-university'],
        ['Cartão de Crédito', 'cartao', '#dc3545', 'fas fa-credit-card']
    ];
    
    $stmt_conta = $pdo->prepare("INSERT INTO contas_financeiras (nome, tipo, cor, icone, tenant_id, filial_id) VALUES (?, ?, ?, ?, 1, 1) ON CONFLICT DO NOTHING");
    
    foreach ($contas as $conta) {
        try {
            $stmt_conta->execute($conta);
            echo "<p>✅ Conta '{$conta[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. Verificar resultado
    echo "<h2>4. Verificando resultado...</h2>";
    
    $tabelas = ['categorias_financeiras', 'contas_financeiras', 'lancamentos_financeiros', 'relatorios_financeiros'];
    
    foreach ($tabelas as $tabela) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $tabela")->fetchColumn();
            echo "<p>📊 Tabela $tabela: $count registros</p>";
        } catch (Exception $e) {
            echo "<p>❌ Erro na tabela $tabela: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>✅ Correção concluída com sucesso!</h2>";
    echo "<p><a href='index.php'>Voltar ao sistema</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro na conexão:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
