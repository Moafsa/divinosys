<?php
/**
 * Script simples para corrigir o banco de dados online
 */

// Configurar para mostrar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Conectar diretamente ao banco
    $host = 'postgres';
    $dbname = 'divino_lanches';
    $user = 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'postgres';
    
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Correção do Banco Online</h1>";
    echo "<p>Conectado ao banco com sucesso!</p>";
    
    // Verificar se as tabelas existem
    $tables = ['usuarios_globais', 'tenants', 'filiais', 'pedido', 'produtos'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p>✅ Tabela $table: $count registros</p>";
        } catch (Exception $e) {
            echo "<p>❌ Erro na tabela $table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Criar tabelas financeiras se não existirem
    echo "<h2>Criando tabelas financeiras...</h2>";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS categorias_financeiras (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
        tenant_id INTEGER NOT NULL,
        filial_id INTEGER NOT NULL,
        ativo BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    try {
        $pdo->exec($sql);
        echo "<p>✅ Tabela categorias_financeiras criada</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    $sql = "
    CREATE TABLE IF NOT EXISTS contas_financeiras (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('banco', 'caixa', 'cartao')),
        tenant_id INTEGER NOT NULL,
        filial_id INTEGER NOT NULL,
        ativo BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    try {
        $pdo->exec($sql);
        echo "<p>✅ Tabela contas_financeiras criada</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    $sql = "
    CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
        id SERIAL PRIMARY KEY,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
        categoria_id INTEGER,
        conta_id INTEGER,
        tenant_id INTEGER NOT NULL,
        filial_id INTEGER NOT NULL,
        data_lancamento DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    try {
        $pdo->exec($sql);
        echo "<p>✅ Tabela lancamentos_financeiros criada</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // Inserir dados iniciais
    echo "<h2>Inserindo dados iniciais...</h2>";
    
    // Categorias padrão
    $categorias = [
        ['Vendas', 'receita'],
        ['Taxa de entrega', 'receita'],
        ['Ingredientes', 'despesa'],
        ['Salários', 'despesa'],
        ['Aluguel', 'despesa'],
        ['Energia elétrica', 'despesa'],
        ['Água', 'despesa'],
        ['Internet', 'despesa'],
        ['Marketing', 'despesa'],
        ['Manutenção', 'despesa']
    ];
    
    foreach ($categorias as $cat) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categorias_financeiras (nome, tipo, tenant_id, filial_id) VALUES (?, ?, 1, 1) ON CONFLICT DO NOTHING");
            $stmt->execute($cat);
            echo "<p>✅ Categoria '{$cat[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    // Contas padrão
    $contas = [
        ['Caixa Principal', 'caixa'],
        ['Banco do Brasil', 'banco'],
        ['Cartão de Crédito', 'cartao']
    ];
    
    foreach ($contas as $conta) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contas_financeiras (nome, tipo, tenant_id, filial_id) VALUES (?, ?, 1, 1) ON CONFLICT DO NOTHING");
            $stmt->execute($conta);
            echo "<p>✅ Conta '{$conta[0]}' inserida</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>✅ Correção concluída!</h2>";
    echo "<p><a href='index.php'>Voltar ao sistema</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
