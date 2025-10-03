<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== SAFE TABLE FIXING SCRIPT ===\n";

$db = \System\Database::getInstance();

try {
    // 1. Check what tables exist
    echo "Checking existing tables...\n";
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $table_names = array_column($tables, 'table_name');
    echo "Existing tables: " . implode(', ', $table_names) . "\n";
    
    // 2. Create estoque table if it doesn't exist
    if (!in_array('estoque', $table_names)) {
        echo "Creating estoque table...\n";
        $db->query("
            CREATE TABLE estoque (
                id SERIAL PRIMARY KEY,
                produto_id INTEGER NOT NULL,
                estoque_atual DECIMAL(10,2) DEFAULT 0.00,
                estoque_minimo DECIMAL(10,2) DEFAULT 0.00,
                preco_custo DECIMAL(10,2) DEFAULT NULL,
                marca VARCHAR(100) DEFAULT NULL,
                fornecedor VARCHAR(100) DEFAULT NULL,
                data_compra DATE DEFAULT NULL,
                data_validade DATE DEFAULT NULL,
                unidade VARCHAR(10) DEFAULT NULL,
                observacoes TEXT DEFAULT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ estoque table created\n";
    } else {
        echo "✅ estoque table already exists\n";
    }
    
    // 3. Create whatsapp_instances table if it doesn't exist
    if (!in_array('whatsapp_instances', $table_names)) {
        echo "Creating whatsapp_instances table...\n";
        $db->query("
            CREATE TABLE whatsapp_instances (
                id SERIAL PRIMARY KEY,
                instance_name VARCHAR(100) NOT NULL,
                phone_number VARCHAR(20) DEFAULT NULL,
                qr_code TEXT DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'disconnected',
                ativo BOOLEAN DEFAULT true,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ whatsapp_instances table created\n";
    } else {
        echo "✅ whatsapp_instances table already exists\n";
    }
    
    // 4. Check if pedido table exists and add columns safely
    if (in_array('pedido', $table_names)) {
        echo "Checking pedido table columns...\n";
        
        // Check if usuario_id column exists
        $columns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = 'pedido'");
        $column_names = array_column($columns, 'column_name');
        
        if (!in_array('usuario_id', $column_names)) {
            echo "Adding usuario_id column to pedido table...\n";
            $db->query("ALTER TABLE pedido ADD COLUMN usuario_id INTEGER");
            echo "✅ usuario_id column added\n";
        } else {
            echo "✅ usuario_id column already exists\n";
        }
        
        if (!in_array('delivery', $column_names)) {
            echo "Adding delivery column to pedido table...\n";
            $db->query("ALTER TABLE pedido ADD COLUMN delivery BOOLEAN DEFAULT false");
            echo "✅ delivery column added\n";
        } else {
            echo "✅ delivery column already exists\n";
        }
    } else {
        echo "⚠️ pedido table not found, skipping column additions\n";
    }
    
    // 5. Check categorias table and add columns safely
    if (in_array('categorias', $table_names)) {
        echo "Checking categorias table columns...\n";
        
        $columns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = 'categorias'");
        $column_names = array_column($columns, 'column_name');
        
        if (!in_array('descricao', $column_names)) {
            echo "Adding descricao column to categorias table...\n";
            $db->query("ALTER TABLE categorias ADD COLUMN descricao TEXT DEFAULT NULL");
            echo "✅ descricao column added\n";
        } else {
            echo "✅ descricao column already exists\n";
        }
        
        if (!in_array('parent_id', $column_names)) {
            echo "Adding parent_id column to categorias table...\n";
            $db->query("ALTER TABLE categorias ADD COLUMN parent_id INTEGER");
            echo "✅ parent_id column added\n";
        } else {
            echo "✅ parent_id column already exists\n";
        }
    } else {
        echo "⚠️ categorias table not found, skipping column additions\n";
    }
    
    // 6. Fix data types safely
    echo "Fixing data types...\n";
    
    if (in_array('mesas', $table_names)) {
        try {
            $db->query("ALTER TABLE mesas ALTER COLUMN id_mesa TYPE VARCHAR(10)");
            echo "✅ mesas.id_mesa type fixed\n";
        } catch (Exception $e) {
            echo "⚠️ Could not fix mesas.id_mesa type: " . $e->getMessage() . "\n";
        }
    }
    
    if (in_array('pedido', $table_names)) {
        try {
            $db->query("ALTER TABLE pedido ALTER COLUMN idmesa TYPE VARCHAR(10)");
            echo "✅ pedido.idmesa type fixed\n";
        } catch (Exception $e) {
            echo "⚠️ Could not fix pedido.idmesa type: " . $e->getMessage() . "\n";
        }
    }
    
    if (in_array('pedidos', $table_names)) {
        try {
            $db->query("ALTER TABLE pedidos ALTER COLUMN idmesa TYPE VARCHAR(10)");
            echo "✅ pedidos.idmesa type fixed\n";
        } catch (Exception $e) {
            echo "⚠️ Could not fix pedidos.idmesa type: " . $e->getMessage() . "\n";
        }
    }
    
    // 7. Update data safely
    echo "Updating data...\n";
    
    if (in_array('categorias', $table_names)) {
        try {
            $db->query("UPDATE categorias SET descricao = 'Categoria de ' || nome WHERE descricao IS NULL");
            $db->query("UPDATE categorias SET parent_id = NULL WHERE parent_id IS NULL");
            echo "✅ categorias data updated\n";
        } catch (Exception $e) {
            echo "⚠️ Could not update categorias data: " . $e->getMessage() . "\n";
        }
    }
    
    if (in_array('pedido', $table_names)) {
        try {
            $db->query("UPDATE pedido SET usuario_id = 1 WHERE usuario_id IS NULL");
            $db->query("UPDATE pedido SET delivery = false WHERE delivery IS NULL");
            echo "✅ pedido data updated\n";
        } catch (Exception $e) {
            echo "⚠️ Could not update pedido data: " . $e->getMessage() . "\n";
        }
    }
    
    // 8. Reset admin password
    echo "Resetting admin password...\n";
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->query("UPDATE usuarios SET senha = ? WHERE login = 'admin'", [$hashed_password]);
    echo "✅ Admin password reset to: admin123\n";
    
    echo "\n✅ ALL OPERATIONS COMPLETED!\n";
    echo "Admin credentials: admin / admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test
echo "\n=== FINAL TEST ===\n";
try {
    // Test admin user
    $admin = $db->fetch("SELECT login FROM usuarios WHERE login = 'admin'");
    echo "✅ Admin user: " . ($admin ? $admin['login'] : 'NOT FOUND') . "\n";
    
    // Test estoque table
    $estoque = $db->fetchAll("SELECT COUNT(*) as count FROM estoque");
    echo "✅ Estoque records: " . $estoque[0]['count'] . "\n";
    
    // Test whatsapp_instances table
    $whatsapp = $db->fetchAll("SELECT COUNT(*) as count FROM whatsapp_instances");
    echo "✅ WhatsApp instances: " . $whatsapp[0]['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>
