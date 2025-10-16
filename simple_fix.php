<?php
/**
 * Script SIMPLES para corrigir problemas do banco
 */

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
    
    echo "<h1>🔧 CORREÇÃO SIMPLES DO BANCO</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. ADICIONAR COLUNA obrigatorio
    echo "<h2>1. Adicionando coluna obrigatorio...</h2>";
    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS obrigatorio BOOLEAN DEFAULT FALSE;");
        echo "<p>✅ Coluna obrigatorio adicionada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 2. ADICIONAR COLUNA preco_adicional
    echo "<h2>2. Adicionando coluna preco_adicional...</h2>";
    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS preco_adicional DECIMAL(10,2) DEFAULT 0;");
        echo "<p>✅ Coluna preco_adicional adicionada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 3. CRIAR TABELA categorias_financeiras se não existir
    echo "<h2>3. Criando tabela categorias_financeiras...</h2>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categorias_financeiras (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                tipo VARCHAR(50) NOT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER,
                cor VARCHAR(7) DEFAULT '#007bff',
                icone VARCHAR(50) DEFAULT 'fa-tag',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "<p>✅ Tabela categorias_financeiras criada/verificada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 4. ADICIONAR COLUNAS cor e icone se não existirem
    echo "<h2>4. Adicionando colunas cor e icone...</h2>";
    try {
        $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#007bff';");
        echo "<p>✅ Coluna cor adicionada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro cor: " . $e->getMessage() . "</p>";
    }
    
    try {
        $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fa-tag';");
        echo "<p>✅ Coluna icone adicionada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro icone: " . $e->getMessage() . "</p>";
    }
    
    // 5. CRIAR TABELA contas_financeiras se não existir
    echo "<h2>5. Criando tabela contas_financeiras...</h2>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contas_financeiras (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                tipo VARCHAR(50) NOT NULL,
                saldo DECIMAL(10,2) DEFAULT 0,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER,
                cor VARCHAR(7) DEFAULT '#28a745',
                icone VARCHAR(50) DEFAULT 'fa-money-bill-alt',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "<p>✅ Tabela contas_financeiras criada/verificada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 6. CRIAR TABELA pagamentos_pedido
    echo "<h2>6. Criando tabela pagamentos_pedido...</h2>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pagamentos_pedido (
                id SERIAL PRIMARY KEY,
                pedido_id INTEGER NOT NULL,
                valor_pago DECIMAL(10,2) NOT NULL,
                forma_pagamento VARCHAR(50) NOT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "<p>✅ Tabela pagamentos_pedido criada</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 7. CRIAR TABELA lancamentos_financeiros se não existir
    echo "<h2>7. Criando tabela lancamentos_financeiros...</h2>";
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
                id SERIAL PRIMARY KEY,
                tipo VARCHAR(50) NOT NULL,
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
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    // 8. VERIFICAR RESULTADO
    echo "<h2>8. Verificando resultado...</h2>";
    
    // Testar se as colunas existem
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'produto_ingredientes' AND column_name = 'obrigatorio'");
        $count = $result->fetchColumn();
        if ($count > 0) {
            echo "<p>✅ Coluna obrigatorio existe na tabela produto_ingredientes</p>";
        } else {
            echo "<p>❌ Coluna obrigatorio NÃO existe na tabela produto_ingredientes</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar obrigatorio: " . $e->getMessage() . "</p>";
    }
    
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'categorias_financeiras' AND column_name = 'cor'");
        $count = $result->fetchColumn();
        if ($count > 0) {
            echo "<p>✅ Coluna cor existe na tabela categorias_financeiras</p>";
        } else {
            echo "<p>❌ Coluna cor NÃO existe na tabela categorias_financeiras</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar cor: " . $e->getMessage() . "</p>";
    }
    
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'pagamentos_pedido'");
        $count = $result->fetchColumn();
        if ($count > 0) {
            echo "<p>✅ Tabela pagamentos_pedido existe</p>";
        } else {
            echo "<p>❌ Tabela pagamentos_pedido NÃO existe</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar pagamentos_pedido: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO SIMPLES CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=financeiro'>Testar página financeiro</a> | <a href='index.php?view=gerenciar_produtos'>Testar produtos</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>




