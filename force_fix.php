<?php
/**
 * Script FORÇADO para corrigir problemas críticos
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
    
    echo "<h1>🔧 CORREÇÃO FORÇADA</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. DROPAR E RECRIAR TABELA pagamentos_pedido
    echo "<h2>1. Recriando tabela pagamentos_pedido...</h2>";
    
    try {
        $pdo->exec("DROP TABLE IF EXISTS pagamentos_pedido CASCADE;");
        echo "<p>✅ Tabela pagamentos_pedido removida</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso ao remover: " . $e->getMessage() . "</p>";
    }
    
    $pdo->exec("
        CREATE TABLE pagamentos_pedido (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL,
            valor_pago DECIMAL(10,2) NOT NULL,
            forma_pagamento VARCHAR(50) NOT NULL,
            nome_cliente VARCHAR(255),
            telefone_cliente VARCHAR(20),
            descricao TEXT,
            troco_para DECIMAL(10,2) DEFAULT 0,
            troco_devolver DECIMAL(10,2) DEFAULT 0,
            usuario_id INTEGER,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela pagamentos_pedido criada com todas as colunas</p>";
    
    // 2. FORÇAR ADIÇÃO DE COLUNAS em categorias_financeiras
    echo "<h2>2. Forçando colunas em categorias_financeiras...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN cor VARCHAR(7) DEFAULT '#007bff';");
        echo "<p>✅ Coluna cor adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna cor já existe</p>";
        } else {
            echo "<p>⚠️ Aviso cor: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN icone VARCHAR(50) DEFAULT 'fa-tag';");
        echo "<p>✅ Coluna icone adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna icone já existe</p>";
        } else {
            echo "<p>⚠️ Aviso icone: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN ativo BOOLEAN DEFAULT TRUE;");
        echo "<p>✅ Coluna ativo adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna ativo já existe</p>";
        } else {
            echo "<p>⚠️ Aviso ativo: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. FORÇAR ADIÇÃO DE COLUNAS em contas_financeiras
    echo "<h2>3. Forçando colunas em contas_financeiras...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE contas_financeiras ADD COLUMN cor VARCHAR(7) DEFAULT '#28a745';");
        echo "<p>✅ Coluna cor adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna cor já existe</p>";
        } else {
            echo "<p>⚠️ Aviso cor: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE contas_financeiras ADD COLUMN icone VARCHAR(50) DEFAULT 'fa-wallet';");
        echo "<p>✅ Coluna icone adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna icone já existe</p>";
        } else {
            echo "<p>⚠️ Aviso icone: " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. FORÇAR ADIÇÃO DE COLUNAS em produto_ingredientes
    echo "<h2>4. Forçando colunas em produto_ingredientes...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN obrigatorio BOOLEAN DEFAULT FALSE;");
        echo "<p>✅ Coluna obrigatorio adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna obrigatorio já existe</p>";
        } else {
            echo "<p>⚠️ Aviso obrigatorio: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN preco_adicional DECIMAL(10,2) DEFAULT 0;");
        echo "<p>✅ Coluna preco_adicional adicionada</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna preco_adicional já existe</p>";
        } else {
            echo "<p>⚠️ Aviso preco_adicional: " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. VERIFICAR RESULTADO
    echo "<h2>5. Verificando resultado...</h2>";
    
    // Verificar estrutura da tabela pagamentos_pedido
    try {
        $result = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'pagamentos_pedido' ORDER BY ordinal_position");
        $columns = $result->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>📊 Colunas em pagamentos_pedido: " . implode(', ', $columns) . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar pagamentos_pedido: " . $e->getMessage() . "</p>";
    }
    
    // Verificar se coluna ativo existe
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'categorias_financeiras' AND column_name = 'ativo'");
        $count = $result->fetchColumn();
        echo "<p>📊 Coluna ativo em categorias_financeiras: " . ($count > 0 ? "EXISTE" : "NÃO EXISTE") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar ativo: " . $e->getMessage() . "</p>";
    }
    
    // Testar consulta que estava falhando
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM categorias_financeiras WHERE ativo = true");
        $count = $result->fetchColumn();
        echo "<p>✅ Teste consulta ativo: $count registros</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste ativo: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO FORÇADA CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=financeiro'>Testar página financeiro</a></p>";
    echo "<p><a href='index.php?view=fechar_pedido&pedido_id=15'>Testar registrar pagamento</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
