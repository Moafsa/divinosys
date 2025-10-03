<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== REPLICATING LOCAL DATABASE EXACTLY ===\n";

$db = \System\Database::getInstance();

try {
    // Drop ALL existing tables to start fresh
    echo "Dropping all existing tables...\n";
    $tables_to_drop = [
        'atividade', 'clientes', 'pedido_itens', 'produto_ingredientes', 
        'produtos', 'ingredientes', 'categorias', 'mesas', 'pedido', 
        'pedidos', 'pedidoss', 'usuarios', 'filiais', 'tenants', 'planos',
        'estoque', 'whatsapp_instances', 'log_pedidos', 'entregadores',
        'movimentacoes_financeiras', 'categorias_financeiras', 'contas_financeiras',
        'configuracao', 'caixas_entrada', 'relatorios'
    ];
    
    foreach ($tables_to_drop as $table) {
        try {
            $db->query("DROP TABLE IF EXISTS {$table} CASCADE");
        } catch (Exception $e) {
            // Ignore errors if table doesn't exist
        }
    }
    echo "✅ All tables dropped\n";
    
    // Create planos table
    echo "Creating planos table...\n";
    $db->query("
        CREATE TABLE planos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            max_mesas INTEGER DEFAULT 5,
            max_usuarios INTEGER DEFAULT 2,
            max_produtos INTEGER DEFAULT 50,
            max_pedidos_mes INTEGER DEFAULT 500,
            recursos JSON DEFAULT '{}',
            preco_mensal DECIMAL(10,2) DEFAULT 29.90,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ planos table created\n";
    
    // Create tenants table
    echo "Creating tenants table...\n";
    $db->query("
        CREATE TABLE tenants (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            subdomain VARCHAR(50) UNIQUE,
            domain VARCHAR(100),
            cnpj VARCHAR(18),
            telefone VARCHAR(20),
            email VARCHAR(255),
            endereco TEXT,
            logo_url VARCHAR(255),
            cor_primaria VARCHAR(7) DEFAULT '#007bff',
            status VARCHAR(20) DEFAULT 'ativo',
            plano_id INTEGER REFERENCES planos(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ tenants table created\n";
    
    // Create filiais table
    echo "Creating filiais table...\n";
    $db->query("
        CREATE TABLE filiais (
            id SERIAL PRIMARY KEY,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            nome VARCHAR(100) NOT NULL,
            endereco TEXT,
            telefone VARCHAR(20),
            email VARCHAR(255),
            cnpj VARCHAR(18),
            logo_url VARCHAR(255),
            status VARCHAR(20) DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ filiais table created\n";
    
    // Create usuarios table
    echo "Creating usuarios table...\n";
    $db->query("
        CREATE TABLE usuarios (
            id SERIAL PRIMARY KEY,
            login VARCHAR(50) NOT NULL,
            senha VARCHAR(255) NOT NULL,
            nivel INTEGER DEFAULT 1,
            pergunta VARCHAR(255),
            resposta VARCHAR(255),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ usuarios table created\n";
    
    // Create categorias table
    echo "Creating categorias table...\n";
    $db->query("
        CREATE TABLE categorias (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT DEFAULT NULL,
            parent_id INTEGER REFERENCES categorias(id),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ categorias table created\n";
    
    // Create ingredientes table
    echo "Creating ingredientes table...\n";
    $db->query("
        CREATE TABLE ingredientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            preco DECIMAL(10,2) DEFAULT 0.00,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ ingredientes table created\n";
    
    // Create produtos table
    echo "Creating produtos table...\n";
    $db->query("
        CREATE TABLE produtos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            preco DECIMAL(10,2) NOT NULL,
            categoria_id INTEGER REFERENCES categorias(id),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ produtos table created\n";
    
    // Create produto_ingredientes table
    echo "Creating produto_ingredientes table...\n";
    $db->query("
        CREATE TABLE produto_ingredientes (
            id SERIAL PRIMARY KEY,
            produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
            ingrediente_id INTEGER NOT NULL REFERENCES ingredientes(id) ON DELETE CASCADE,
            quantidade DECIMAL(10,2) DEFAULT 1.00,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL
        )
    ");
    echo "✅ produto_ingredientes table created\n";
    
    // Create mesas table
    echo "Creating mesas table...\n";
    $db->query("
        CREATE TABLE mesas (
            id SERIAL PRIMARY KEY,
            id_mesa VARCHAR(10) NOT NULL,
            numero INTEGER,
            nome VARCHAR(255),
            status VARCHAR(20) DEFAULT 'livre',
            capacidade INTEGER DEFAULT 4,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ mesas table created\n";
    
    // Create pedido table (singular)
    echo "Creating pedido table...\n";
    $db->query("
        CREATE TABLE pedido (
            idpedido SERIAL PRIMARY KEY,
            idmesa VARCHAR(10) DEFAULT NULL,
            cliente VARCHAR(100) DEFAULT NULL,
            delivery BOOLEAN DEFAULT false,
            status VARCHAR(50) DEFAULT 'Pendente',
            valor_total DECIMAL(10,2) DEFAULT 0.00,
            data DATE DEFAULT CURRENT_DATE,
            hora_pedido TIME DEFAULT CURRENT_TIME,
            usuario_id INTEGER DEFAULT NULL REFERENCES usuarios(id),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ pedido table created\n";
    
    // Create pedidos table (plural)
    echo "Creating pedidos table...\n";
    $db->query("
        CREATE TABLE pedidos (
            idpedido SERIAL PRIMARY KEY,
            idmesa VARCHAR(10) DEFAULT NULL,
            cliente VARCHAR(100) DEFAULT NULL,
            delivery BOOLEAN DEFAULT false,
            status VARCHAR(50) DEFAULT 'Pendente',
            valor_total DECIMAL(10,2) DEFAULT 0.00,
            data DATE DEFAULT CURRENT_DATE,
            hora_pedido TIME DEFAULT CURRENT_TIME,
            usuario_id INTEGER DEFAULT NULL REFERENCES usuarios(id),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ pedidos table created\n";
    
    // Create pedidoss table (double s - as expected by code)
    echo "Creating pedidoss table...\n";
    $db->query("
        CREATE TABLE pedidoss (
            idpedido SERIAL PRIMARY KEY,
            idmesa VARCHAR(10) DEFAULT NULL,
            cliente VARCHAR(100) DEFAULT NULL,
            delivery BOOLEAN DEFAULT false,
            status VARCHAR(50) DEFAULT 'Pendente',
            valor_total DECIMAL(10,2) DEFAULT 0.00,
            data DATE DEFAULT CURRENT_DATE,
            hora_pedido TIME DEFAULT CURRENT_TIME,
            usuario_id INTEGER DEFAULT NULL REFERENCES usuarios(id),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ pedidoss table created\n";
    
    // Create pedido_itens table
    echo "Creating pedido_itens table...\n";
    $db->query("
        CREATE TABLE pedido_itens (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL,
            produto_id INTEGER NOT NULL REFERENCES produtos(id),
            quantidade INTEGER NOT NULL DEFAULT 1,
            preco_unitario DECIMAL(10,2) NOT NULL,
            preco_total DECIMAL(10,2) NOT NULL,
            observacoes TEXT,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ pedido_itens table created\n";
    
    // Create clientes table
    echo "Creating clientes table...\n";
    $db->query("
        CREATE TABLE clientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            telefone VARCHAR(20),
            email VARCHAR(255),
            endereco TEXT,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ clientes table created\n";
    
    // Create atividade table
    echo "Creating atividade table...\n";
    $db->query("
        CREATE TABLE atividade (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER REFERENCES usuarios(id),
            acao VARCHAR(100) NOT NULL,
            descricao TEXT,
            \"end\" VARCHAR(50),
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ atividade table created\n";
    
    // Create estoque table
    echo "Creating estoque table...\n";
    $db->query("
        CREATE TABLE estoque (
            id SERIAL PRIMARY KEY,
            produto_id INTEGER NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
            estoque_atual DECIMAL(10,2) DEFAULT 0.00,
            estoque_minimo DECIMAL(10,2) DEFAULT 0.00,
            preco_custo DECIMAL(10,2) DEFAULT NULL,
            marca VARCHAR(100) DEFAULT NULL,
            fornecedor VARCHAR(100) DEFAULT NULL,
            data_compra DATE DEFAULT NULL,
            data_validade DATE DEFAULT NULL,
            unidade VARCHAR(10) DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ estoque table created\n";
    
    // Create whatsapp_instances table
    echo "Creating whatsapp_instances table...\n";
    $db->query("
        CREATE TABLE whatsapp_instances (
            id SERIAL PRIMARY KEY,
            instance_name VARCHAR(100) NOT NULL,
            phone_number VARCHAR(20) DEFAULT NULL,
            qr_code TEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'disconnected',
            ativo BOOLEAN DEFAULT true,
            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ whatsapp_instances table created\n";
    
    // Insert essential data
    echo "Inserting essential data...\n";
    
    // Insert planos
    $db->query("INSERT INTO planos (nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, preco_mensal) VALUES
        ('Starter', 5, 2, 50, 500, 29.90),
        ('Professional', 15, 5, 200, 2000, 79.90),
        ('Enterprise', -1, -1, -1, -1, 199.90)");
    
    // Insert tenant
    $db->query("INSERT INTO tenants (nome, status, plano_id) VALUES
        ('Divino Lanches', 'ativo', 2)");
    
    // Insert filial
    $db->query("INSERT INTO filiais (tenant_id, nome, status) VALUES
        (1, 'Filial Principal', 'ativo')");
    
    // Insert admin user with correct password
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->query("INSERT INTO usuarios (login, senha, nivel, pergunta, resposta, tenant_id, filial_id) VALUES
        ('admin', ?, 1, 'admin', 'admin', 1, 1)", [$hashed_password]);
    
    // Insert categorias
    $db->query("INSERT INTO categorias (nome, descricao, tenant_id, filial_id) VALUES
        ('Lanches', 'Categoria de Lanches', 1, 1),
        ('Bebidas', 'Categoria de Bebidas', 1, 1),
        ('Sobremesas', 'Categoria de Sobremesas', 1, 1)");
    
    // Insert mesas
    $db->query("INSERT INTO mesas (id_mesa, numero, nome, status, tenant_id, filial_id) VALUES
        ('1', 1, '', 'livre', 1, 1),
        ('2', 2, '', 'livre', 1, 1),
        ('3', 3, '', 'livre', 1, 1),
        ('4', 4, '', 'livre', 1, 1),
        ('5', 5, '', 'livre', 1, 1)");
    
    // Insert sample data in all pedido tables
    $db->query("INSERT INTO pedido (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
        ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
    
    $db->query("INSERT INTO pedidos (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
        ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
    
    $db->query("INSERT INTO pedidoss (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
        ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
    
    echo "✅ Essential data inserted\n";
    
    echo "\n✅ LOCAL DATABASE REPLICATED EXACTLY!\n";
    echo "Admin credentials: admin / admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test
echo "\n=== FINAL TEST ===\n";
try {
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    echo "✅ Tables created: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "  - " . $table['table_name'] . "\n";
    }
    
    // Test admin login
    $admin = $db->fetch("SELECT login FROM usuarios WHERE login = 'admin'");
    echo "✅ Admin user: " . ($admin ? $admin['login'] : 'NOT FOUND') . "\n";
    
    // Test pedidoss query (the one that was failing)
    $pedidoss = $db->fetchAll("SELECT COUNT(*) as count FROM pedidoss");
    echo "✅ Pedidoss records: " . $pedidoss[0]['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>
