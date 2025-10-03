<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== FORCE CREATING MISSING TABLES ===\n";

$db = \System\Database::getInstance();

try {
    // 1. Create estoque table
    echo "Creating estoque table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS estoque (
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
    
    // 2. Create whatsapp_instances table
    echo "Creating whatsapp_instances table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS whatsapp_instances (
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
    
    // 3. Add missing columns to pedido table
    echo "Adding missing columns to pedido table...\n";
    $db->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS usuario_id INTEGER REFERENCES usuarios(id)");
    $db->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS delivery BOOLEAN DEFAULT false");
    echo "✅ Missing columns added to pedido table\n";
    
    // 4. Add missing columns to categorias table
    echo "Adding missing columns to categorias table...\n";
    $db->query("ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT DEFAULT NULL");
    $db->query("ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES categorias(id)");
    echo "✅ Missing columns added to categorias table\n";
    
    // 5. Fix data types
    echo "Fixing data types...\n";
    $db->query("ALTER TABLE mesas ALTER COLUMN id_mesa TYPE VARCHAR(10)");
    $db->query("ALTER TABLE pedido ALTER COLUMN idmesa TYPE VARCHAR(10)");
    $db->query("ALTER TABLE pedidos ALTER COLUMN idmesa TYPE VARCHAR(10)");
    echo "✅ Data types fixed\n";
    
    // 6. Update data
    echo "Updating data...\n";
    $db->query("UPDATE categorias SET descricao = 'Categoria de ' || nome WHERE descricao IS NULL");
    $db->query("UPDATE categorias SET parent_id = NULL WHERE parent_id IS NULL");
    $db->query("UPDATE pedido SET usuario_id = 1 WHERE usuario_id IS NULL");
    $db->query("UPDATE pedido SET delivery = false WHERE delivery IS NULL");
    echo "✅ Data updated\n";
    
    // 7. Reset admin password
    echo "Resetting admin password...\n";
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->query("UPDATE usuarios SET senha = ? WHERE login = 'admin'", [$hashed_password]);
    echo "✅ Admin password reset to: admin123\n";
    
    echo "\n✅ ALL TABLES AND DATA UPDATED SUCCESSFULLY!\n";
    echo "Admin credentials: admin / admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test
echo "\n=== TESTING ===\n";
try {
    // Test tables exist
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    echo "✅ Tables in database: " . count($tables) . "\n";
    
    // Test admin user
    $admin = $db->fetch("SELECT login FROM usuarios WHERE login = 'admin'");
    echo "✅ Admin user: " . ($admin ? $admin['login'] : 'NOT FOUND') . "\n";
    
    // Test estoque table
    $estoque = $db->fetchAll("SELECT COUNT(*) as count FROM estoque");
    echo "✅ Estoque records: " . $estoque[0]['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>
