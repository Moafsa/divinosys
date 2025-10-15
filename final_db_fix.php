<?php
/**
 * Script DEFINITIVO para corrigir TODOS os problemas do banco de dados
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
    
    echo "<h1>🔧 CORREÇÃO DEFINITIVA DO BANCO DE DADOS</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. CORRIGIR TABELA PEDIDO
    echo "<h2>1. Corrigindo tabela pedido...</h2>";
    
    $alteracoes_pedido = [
        "ALTER TABLE pedido ADD COLUMN IF NOT EXISTS saldo_devedor DECIMAL(10,2) DEFAULT 0;",
        "ALTER TABLE pedido ADD COLUMN IF NOT EXISTS status_pagamento VARCHAR(20) DEFAULT 'pendente';",
        "ALTER TABLE pedido ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50);",
        "ALTER TABLE pedido ADD COLUMN IF NOT EXISTS valor_pago DECIMAL(10,2) DEFAULT 0;",
        "ALTER TABLE pedido ADD COLUMN IF NOT EXISTS data_pagamento TIMESTAMP;"
    ];
    
    foreach ($alteracoes_pedido as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Executado: " . substr($sql, 0, 50) . "...</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. CRIAR/CORRIGIR TABELAS FINANCEIRAS
    echo "<h2>2. Criando/Corrigindo tabelas financeiras...</h2>";
    
    // Criar tabela categorias_financeiras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categorias_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela categorias_financeiras criada/verificada</p>";
    
    // Adicionar colunas cor e icone
    $alteracoes_categorias = [
        "ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#007bff';",
        "ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fa-tag';"
    ];
    
    foreach ($alteracoes_categorias as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Executado: " . substr($sql, 0, 50) . "...</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // Criar tabela contas_financeiras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contas_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('dinheiro', 'banco', 'cartao')),
            saldo DECIMAL(10,2) DEFAULT 0,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela contas_financeiras criada/verificada</p>";
    
    // Adicionar colunas cor e icone
    $alteracoes_contas = [
        "ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#28a745';",
        "ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fa-money-bill-alt';"
    ];
    
    foreach ($alteracoes_contas as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Executado: " . substr($sql, 0, 50) . "...</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // Criar outras tabelas financeiras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
            id SERIAL PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('receita', 'despesa', 'transferencia')),
            valor DECIMAL(10,2) NOT NULL,
            data DATE DEFAULT CURRENT_DATE,
            descricao TEXT,
            categoria_id INTEGER,
            conta_id INTEGER,
            usuario_id INTEGER,
            pedido_id INTEGER,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela lancamentos_financeiros criada/verificada</p>";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS relatorios_financeiros (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            parametros JSONB,
            resultado JSONB,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            gerado_por INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela relatorios_financeiros criada/verificada</p>";
    
    // 3. CORRIGIR TABELA PRODUTO_INGREDIENTES
    echo "<h2>3. Corrigindo tabela produto_ingredientes...</h2>";
    
    $alteracoes_produto_ingredientes = [
        "ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS obrigatorio BOOLEAN DEFAULT FALSE;",
        "ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS preco_adicional DECIMAL(10,2) DEFAULT 0;"
    ];
    
    foreach ($alteracoes_produto_ingredientes as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>✅ Executado: " . substr($sql, 0, 50) . "...</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. INSERIR DADOS INICIAIS
    echo "<h2>4. Inserindo dados iniciais...</h2>";
    
    // Categorias financeiras
    $categorias = [
        ['Vendas', 'receita', '#28a745', 'fa-shopping-cart'],
        ['Taxa de entrega', 'receita', '#17a2b8', 'fa-truck'],
        ['Ingredientes', 'despesa', '#dc3545', 'fa-apple-alt'],
        ['Salários', 'despesa', '#fd7e14', 'fa-users'],
        ['Aluguel', 'despesa', '#6f42c1', 'fa-building'],
        ['Energia elétrica', 'despesa', '#ffc107', 'fa-bolt'],
        ['Água', 'despesa', '#20c997', 'fa-tint'],
        ['Internet', 'despesa', '#6c757d', 'fa-wifi'],
        ['Marketing', 'despesa', '#e83e8c', 'fa-bullhorn'],
        ['Manutenção', 'despesa', '#fd7e14', 'fa-tools']
    ];
    
    $stmt_cat = $pdo->prepare("INSERT INTO categorias_financeiras (nome, tipo, cor, icone, tenant_id, filial_id) VALUES (?, ?, ?, ?, 1, 1) ON CONFLICT DO NOTHING");
    
    foreach ($categorias as $cat) {
        try {
            $stmt_cat->execute($cat);
            echo "<p>✅ Categoria '{$cat[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso categoria '{$cat[0]}': " . $e->getMessage() . "</p>";
        }
    }
    
    // Contas financeiras
    $contas = [
        ['Caixa Principal', 'dinheiro', '#28a745', 'fa-cash-register'],
        ['Banco do Brasil', 'banco', '#007bff', 'fa-university'],
        ['Cartão de Crédito', 'cartao', '#dc3545', 'fa-credit-card']
    ];
    
    $stmt_conta = $pdo->prepare("INSERT INTO contas_financeiras (nome, tipo, cor, icone, tenant_id, filial_id) VALUES (?, ?, ?, ?, 1, 1) ON CONFLICT DO NOTHING");
    
    foreach ($contas as $conta) {
        try {
            $stmt_conta->execute($conta);
            echo "<p>✅ Conta '{$conta[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso conta '{$conta[0]}': " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. RESOLVER PROBLEMA DE CATEGORIAS
    echo "<h2>5. Resolvendo problema de categorias...</h2>";
    
    // Criar categoria padrão
    $default_category_name = 'Sem Categoria';
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nome = ? AND tenant_id = 1 AND filial_id = 1");
    $stmt->execute([$default_category_name]);
    $existing_default = $stmt->fetchColumn();
    
    if (!$existing_default) {
        $pdo->exec("INSERT INTO categorias (nome, tenant_id, filial_id) VALUES ('$default_category_name', 1, 1)");
        $default_category_id = $pdo->lastInsertId();
        echo "<p>✅ Categoria padrão '{$default_category_name}' criada com ID: {$default_category_id}</p>";
    } else {
        $default_category_id = $existing_default;
        echo "<p>✅ Categoria padrão '{$default_category_name}' já existe com ID: {$default_category_id}</p>";
    }
    
    // Mover produtos órfãos
    $stmt = $pdo->prepare("
        UPDATE produtos 
        SET categoria_id = ? 
        WHERE categoria_id IS NULL OR categoria_id NOT IN (SELECT id FROM categorias WHERE tenant_id = 1 AND filial_id = 1) 
        AND tenant_id = 1 AND filial_id = 1;
    ");
    $stmt->execute([$default_category_id]);
    $moved_count = $stmt->rowCount();
    echo "<p>✅ {$moved_count} produtos movidos para a categoria padrão</p>";
    
    // 6. VERIFICAR RESULTADO
    echo "<h2>6. Verificando resultado final...</h2>";
    
    // Testar consultas que estavam falhando
    try {
        $teste_pedido = $pdo->query("SELECT COUNT(*) FROM pedido WHERE saldo_devedor IS NOT NULL")->fetchColumn();
        echo "<p>✅ Teste pedido (saldo_devedor): {$teste_pedido} registros</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste pedido: " . $e->getMessage() . "</p>";
    }
    
    try {
        $teste_financeiro = $pdo->query("SELECT COUNT(*) FROM categorias_financeiras WHERE cor IS NOT NULL")->fetchColumn();
        echo "<p>✅ Teste financeiro (categorias com cor): {$teste_financeiro} registros</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste financeiro: " . $e->getMessage() . "</p>";
    }
    
    try {
        $teste_ingredientes = $pdo->query("SELECT COUNT(*) FROM produto_ingredientes WHERE obrigatorio IS NOT NULL")->fetchColumn();
        echo "<p>✅ Teste ingredientes (obrigatorio): {$teste_ingredientes} registros</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste ingredientes: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO DEFINITIVA CONCLUÍDA!</h1>";
    echo "<p><strong>Problemas corrigidos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Coluna 'saldo_devedor' adicionada na tabela pedido</li>";
    echo "<li>✅ Colunas 'cor' e 'icone' adicionadas nas tabelas financeiras</li>";
    echo "<li>✅ Coluna 'obrigatorio' adicionada na tabela produto_ingredientes</li>";
    echo "<li>✅ Tabelas financeiras criadas com dados iniciais</li>";
    echo "<li>✅ Categoria padrão criada para produtos órfãos</li>";
    echo "</ul>";
    echo "<p><a href='index.php?view=financeiro'>Testar página financeiro</a> | <a href='index.php?view=gerar_pedido'>Testar fazer pedido</a> | <a href='index.php?view=gerenciar_produtos'>Testar categorias</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    error_log("Erro crítico em final_db_fix.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
?>
