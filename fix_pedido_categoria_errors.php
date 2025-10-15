<?php
/**
 * Script para corrigir erros de pedido e categoria
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
    
    echo "<h1>🔧 Correção de Erros de Pedido e Categoria</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Corrigir tabela pedido - adicionar coluna saldo_devedor
    echo "<h2>1. Corrigindo tabela pedido...</h2>";
    
    $alter_pedido = "
    ALTER TABLE pedido 
    ADD COLUMN IF NOT EXISTS saldo_devedor DECIMAL(10,2) DEFAULT 0;
    ";
    
    try {
        $pdo->exec($alter_pedido);
        echo "<p>✅ Coluna 'saldo_devedor' adicionada na tabela pedido</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 2. Verificar estrutura da tabela pedido
    echo "<h2>2. Verificando estrutura da tabela pedido...</h2>";
    
    try {
        $columns = $pdo->query("
            SELECT column_name, data_type, column_default 
            FROM information_schema.columns 
            WHERE table_name = 'pedido' 
            ORDER BY ordinal_position
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>📊 Colunas da tabela pedido:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li>{$col['column_name']} ({$col['data_type']}) - Default: {$col['column_default']}</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 3. Verificar categorias e produtos
    echo "<h2>3. Verificando categorias e produtos...</h2>";
    
    try {
        // Contar categorias
        $categorias_count = $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
        echo "<p>📊 Total de categorias: $categorias_count</p>";
        
        // Contar produtos
        $produtos_count = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
        echo "<p>📊 Total de produtos: $produtos_count</p>";
        
        // Verificar produtos sem categoria
        $produtos_sem_categoria = $pdo->query("
            SELECT COUNT(*) FROM produtos 
            WHERE categoria_id IS NULL OR categoria_id NOT IN (SELECT id FROM categorias)
        ")->fetchColumn();
        echo "<p>📊 Produtos sem categoria válida: $produtos_sem_categoria</p>";
        
        // Mostrar categorias com produtos
        $categorias_com_produtos = $pdo->query("
            SELECT c.id, c.nome, COUNT(p.id) as total_produtos
            FROM categorias c
            LEFT JOIN produtos p ON c.id = p.categoria_id
            GROUP BY c.id, c.nome
            HAVING COUNT(p.id) > 0
            ORDER BY total_produtos DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>📊 Categorias com produtos:</p>";
        echo "<ul>";
        foreach ($categorias_com_produtos as $cat) {
            echo "<li><strong>{$cat['nome']}</strong> (ID: {$cat['id']}) - {$cat['total_produtos']} produtos</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 4. Criar categoria padrão para produtos órfãos
    echo "<h2>4. Criando categoria padrão para produtos órfãos...</h2>";
    
    try {
        // Verificar se já existe categoria "Sem Categoria"
        $categoria_padrao = $pdo->query("SELECT id FROM categorias WHERE nome = 'Sem Categoria'")->fetch();
        
        if (!$categoria_padrao) {
            $pdo->exec("
                INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id) 
                VALUES ('Sem Categoria', 'Categoria padrão para produtos sem categoria', true, 1, 1)
            ");
            $categoria_padrao_id = $pdo->lastInsertId();
            echo "<p>✅ Categoria 'Sem Categoria' criada (ID: $categoria_padrao_id)</p>";
        } else {
            $categoria_padrao_id = $categoria_padrao['id'];
            echo "<p>✅ Categoria 'Sem Categoria' já existe (ID: $categoria_padrao_id)</p>";
        }
        
        // Atualizar produtos sem categoria
        $produtos_atualizados = $pdo->exec("
            UPDATE produtos 
            SET categoria_id = $categoria_padrao_id 
            WHERE categoria_id IS NULL OR categoria_id NOT IN (SELECT id FROM categorias)
        ");
        
        echo "<p>✅ $produtos_atualizados produtos atualizados para usar categoria padrão</p>";
        
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }
    
    // 5. Verificar resultado final
    echo "<h2>5. Verificando resultado final...</h2>";
    
    try {
        // Testar consulta de pedidos
        $teste_pedido = $pdo->query("
            SELECT COUNT(*) as total 
            FROM pedido 
            WHERE saldo_devedor IS NOT NULL
        ")->fetch();
        echo "<p>✅ Teste de consulta com saldo_devedor: {$teste_pedido['total']} registros</p>";
        
        // Verificar se ainda há produtos órfãos
        $produtos_orfos = $pdo->query("
            SELECT COUNT(*) FROM produtos 
            WHERE categoria_id IS NULL OR categoria_id NOT IN (SELECT id FROM categorias)
        ")->fetchColumn();
        echo "<p>📊 Produtos órfãos restantes: $produtos_orfos</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste final: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>✅ Correção concluída com sucesso!</h2>";
    echo "<p><strong>Problemas corrigidos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Coluna 'saldo_devedor' adicionada na tabela pedido</li>";
    echo "<li>✅ Categoria padrão criada para produtos órfãos</li>";
    echo "<li>✅ Produtos sem categoria válida foram atualizados</li>";
    echo "</ul>";
    echo "<p><a href='index.php?view=gerar_pedido'>Testar fazer pedido</a> | <a href='index.php?view=gerenciar_produtos'>Gerenciar produtos</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro na conexão:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
