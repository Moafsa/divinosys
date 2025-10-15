<?php
/**
 * Script ULTRA SIMPLES para corrigir problemas críticos
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
    
    echo "<h1>🔧 CORREÇÃO ULTRA SIMPLES</h1>";
    
    // 1. CRIAR TABELA pagamentos_pedido
    echo "<h2>1. Criando tabela pagamentos_pedido...</h2>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagamentos_pedido (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL,
            valor_pago DECIMAL(10,2) NOT NULL,
            forma_pagamento VARCHAR(50) NOT NULL,
            nome_cliente VARCHAR(255),
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela pagamentos_pedido criada</p>";
    
    // 2. ADICIONAR COLUNAS na produto_ingredientes
    echo "<h2>2. Adicionando colunas em produto_ingredientes...</h2>";
    $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS obrigatorio BOOLEAN DEFAULT FALSE;");
    echo "<p>✅ Coluna obrigatorio adicionada</p>";
    
    $pdo->exec("ALTER TABLE produto_ingredientes ADD COLUMN IF NOT EXISTS preco_adicional DECIMAL(10,2) DEFAULT 0;");
    echo "<p>✅ Coluna preco_adicional adicionada</p>";
    
    // 3. ADICIONAR COLUNAS na categorias_financeiras
    echo "<h2>3. Adicionando colunas em categorias_financeiras...</h2>";
    $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#007bff';");
    echo "<p>✅ Coluna cor adicionada</p>";
    
    $pdo->exec("ALTER TABLE categorias_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fa-tag';");
    echo "<p>✅ Coluna icone adicionada</p>";
    
    // 4. ADICIONAR COLUNAS na contas_financeiras
    echo "<h2>4. Adicionando colunas em contas_financeiras...</h2>";
    $pdo->exec("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS cor VARCHAR(7) DEFAULT '#28a745';");
    echo "<p>✅ Coluna cor adicionada</p>";
    
    $pdo->exec("ALTER TABLE contas_financeiras ADD COLUMN IF NOT EXISTS icone VARCHAR(50) DEFAULT 'fa-money-bill-alt';");
    echo "<p>✅ Coluna icone adicionada</p>";
    
    // 5. VERIFICAR RESULTADO
    echo "<h2>5. Verificando resultado...</h2>";
    
    // Verificar se pagamentos_pedido existe
    $result = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'pagamentos_pedido'");
    $count = $result->fetchColumn();
    echo "<p>📊 Tabela pagamentos_pedido existe: " . ($count > 0 ? "SIM" : "NÃO") . "</p>";
    
    // Verificar se coluna obrigatorio existe
    $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'produto_ingredientes' AND column_name = 'obrigatorio'");
    $count = $result->fetchColumn();
    echo "<p>📊 Coluna obrigatorio existe: " . ($count > 0 ? "SIM" : "NÃO") . "</p>";
    
    // Verificar se coluna cor existe em categorias_financeiras
    $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'categorias_financeiras' AND column_name = 'cor'");
    $count = $result->fetchColumn();
    echo "<p>📊 Coluna cor em categorias_financeiras existe: " . ($count > 0 ? "SIM" : "NÃO") . "</p>";
    
    echo "<h1>🎉 CORREÇÃO CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=financeiro'>Testar página financeiro</a></p>";
    echo "<p><a href='index.php?view=fechar_pedido&pedido_id=15'>Testar registrar pagamento</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
