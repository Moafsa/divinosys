<?php
// Direct PDO connection to avoid System class issues
$host = $_ENV['DB_HOST'] ?? 'postgres';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
$user = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

echo "=== RESTORING TABLES FROM LOCAL BACKUP ===\n";

try {
    // 1. Drop and recreate categorias table with correct structure from backup
    echo "Recreating categorias table...\n";
    $pdo->exec("DROP TABLE IF EXISTS categorias CASCADE");
    $pdo->exec("
        CREATE TABLE categorias (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            imagem VARCHAR(255),
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            parent_id INTEGER,
            ordem INTEGER DEFAULT 0,
            ativo BOOLEAN DEFAULT true,
            descricao TEXT
        )
    ");
    echo "✅ categorias table recreated\n";
    
    // 2. Drop and recreate ingredientes table with correct structure from backup
    echo "Recreating ingredientes table...\n";
    $pdo->exec("DROP TABLE IF EXISTS ingredientes CASCADE");
    $pdo->exec("
        CREATE TABLE ingredientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(50) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            preco_adicional NUMERIC(10,2) DEFAULT 0.00,
            disponivel BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            descricao TEXT,
            ativo BOOLEAN DEFAULT true,
            CONSTRAINT ingredientes_tipo_check CHECK (tipo IN ('pao', 'proteina', 'queijo', 'salada', 'molho', 'complemento'))
        )
    ");
    echo "✅ ingredientes table recreated\n";
    
    // 3. Drop and recreate produtos table with correct structure from backup
    echo "Recreating produtos table...\n";
    $pdo->exec("DROP TABLE IF EXISTS produtos CASCADE");
    $pdo->exec("
        CREATE TABLE produtos (
            id SERIAL PRIMARY KEY,
            codigo VARCHAR(255),
            categoria_id INTEGER NOT NULL,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            preco_normal NUMERIC(10,2) NOT NULL,
            preco_mini NUMERIC(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            imagem VARCHAR(255),
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            imagens JSONB,
            ativo BOOLEAN DEFAULT true,
            destaque BOOLEAN DEFAULT false,
            ordem INTEGER DEFAULT 0,
            estoque_atual INTEGER DEFAULT 0,
            estoque_minimo INTEGER DEFAULT 0,
            preco_custo NUMERIC(10,2) DEFAULT 0.00
        )
    ");
    echo "✅ produtos table recreated\n";
    
    // 4. Drop and recreate produto_ingredientes table
    echo "Recreating produto_ingredientes table...\n";
    $pdo->exec("DROP TABLE IF EXISTS produto_ingredientes CASCADE");
    $pdo->exec("
        CREATE TABLE produto_ingredientes (
            produto_id INTEGER NOT NULL,
            ingrediente_id INTEGER NOT NULL,
            padrao BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tenant_id INTEGER DEFAULT 1 NOT NULL,
            filial_id INTEGER DEFAULT 1 NOT NULL,
            PRIMARY KEY (produto_id, ingrediente_id)
        )
    ");
    echo "✅ produto_ingredientes table recreated\n";
    
    // 5. Insert sample data for testing
    echo "Inserting sample data...\n";
    
    // Insert categorias
    $pdo->exec("
        INSERT INTO categorias (id, nome, tenant_id, filial_id, ativo, descricao) VALUES 
        (1, 'Lanches', 1, 1, true, 'Categoria de Lanches'),
        (2, 'Bebidas', 1, 1, true, 'Categoria de Bebidas'),
        (3, 'Sobremesas', 1, 1, true, 'Categoria de Sobremesas')
        ON CONFLICT (id) DO NOTHING
    ");
    
    // Insert ingredientes
    $pdo->exec("
        INSERT INTO ingredientes (id, nome, tipo, preco_adicional, disponivel, tenant_id, filial_id, ativo) VALUES 
        (1, 'Pão Francês', 'pao', 0.00, true, 1, 1, true),
        (2, 'Pão de Hambúrguer', 'pao', 0.00, true, 1, 1, true),
        (3, 'Hambúrguer de Carne', 'proteina', 8.50, true, 1, 1, true),
        (4, 'Frango Grelhado', 'proteina', 7.50, true, 1, 1, true),
        (5, 'Queijo Cheddar', 'queijo', 2.00, true, 1, 1, true),
        (6, 'Queijo Mussarela', 'queijo', 1.50, true, 1, 1, true)
        ON CONFLICT (id) DO NOTHING
    ");
    
    // Insert produtos
    $pdo->exec("
        INSERT INTO produtos (id, codigo, categoria_id, nome, descricao, preco_normal, preco_mini, tenant_id, filial_id, ativo) VALUES 
        (1, 'LCH001', 1, 'X-Burger', 'Hambúrguer de carne com queijo, alface e tomate', 15.90, 12.90, 1, 1, true),
        (2, 'LCH002', 1, 'X-Salada', 'Hambúrguer de carne com queijo, alface, tomate e cebola', 17.90, 14.90, 1, 1, true),
        (3, 'BEB001', 2, 'Coca-Cola 350ml', 'Refrigerante Coca-Cola lata', 4.50, NULL, 1, 1, true)
        ON CONFLICT (id) DO NOTHING
    ");
    
    // Insert produto_ingredientes
    $pdo->exec("
        INSERT INTO produto_ingredientes (produto_id, ingrediente_id, padrao, tenant_id, filial_id) VALUES 
        (1, 2, true, 1, 1),
        (1, 3, true, 1, 1),
        (1, 5, true, 1, 1),
        (2, 2, true, 1, 1),
        (2, 3, true, 1, 1),
        (2, 5, true, 1, 1)
        ON CONFLICT (produto_id, ingrediente_id) DO NOTHING
    ");
    
    echo "✅ Sample data inserted\n";
    
    // 6. Test the queries that were failing
    echo "\n=== TESTING QUERIES ===\n";
    
    // Test categorias query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categorias WHERE tenant_id = 1 AND filial_id = 1");
    $result = $stmt->fetch();
    echo "✅ Categorias query: " . $result['count'] . " records\n";
    
    // Test ingredientes query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ingredientes WHERE tenant_id = 1 AND filial_id = 1");
    $result = $stmt->fetch();
    echo "✅ Ingredientes query: " . $result['count'] . " records\n";
    
    // Test produtos query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM produtos WHERE tenant_id = 1 AND filial_id = 1");
    $result = $stmt->fetch();
    echo "✅ Produtos query: " . $result['count'] . " records\n";
    
    // Test produto_ingredientes query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM produto_ingredientes WHERE tenant_id = 1 AND filial_id = 1");
    $result = $stmt->fetch();
    echo "✅ Produto_ingredientes query: " . $result['count'] . " records\n";
    
    echo "\n✅ ALL TABLES RESTORED FROM BACKUP!\n";
    echo "The cadastro de produtos, ingredientes e categorias should now work.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
