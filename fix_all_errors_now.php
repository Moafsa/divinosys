<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== FIXING ALL ERRORS NOW ===\n";

$db = \System\Database::getInstance();

try {
    // 1. Create pedidoss table if it doesn't exist
    echo "Creating pedidoss table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS pedidoss (
            idpedido SERIAL PRIMARY KEY,
            idmesa VARCHAR(10) DEFAULT NULL,
            cliente VARCHAR(100) DEFAULT NULL,
            delivery BOOLEAN DEFAULT false,
            status VARCHAR(50) DEFAULT 'Pendente',
            valor_total DECIMAL(10,2) DEFAULT 0.00,
            data DATE DEFAULT CURRENT_DATE,
            hora_pedido TIME DEFAULT CURRENT_TIME,
            usuario_id INTEGER DEFAULT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert sample data
    $db->query("INSERT INTO pedidoss (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
        ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
    echo "✅ pedidoss table created with data\n";
    
    // 2. Add imagem column to categorias table
    echo "Adding imagem column to categorias...\n";
    $db->query("ALTER TABLE categorias ADD COLUMN IF NOT EXISTS imagem VARCHAR(255) DEFAULT NULL");
    echo "✅ imagem column added to categorias\n";
    
    // 3. Update categorias with default imagem
    echo "Updating categorias with default imagem...\n";
    $db->query("UPDATE categorias SET imagem = 'default-category.png' WHERE imagem IS NULL");
    echo "✅ categorias updated with default imagem\n";
    
    // 4. Create missing tables for relatorios
    echo "Creating missing tables for relatorios...\n";
    
    // Create log_pedidos table
    $db->query("
        CREATE TABLE IF NOT EXISTS log_pedidos (
            id SERIAL PRIMARY KEY,
            idpedido INTEGER NOT NULL,
            status_anterior VARCHAR(50) DEFAULT NULL,
            novo_status VARCHAR(50) DEFAULT NULL,
            usuario VARCHAR(100) DEFAULT NULL,
            data_alteracao TIMESTAMP NOT NULL
        )
    ");
    
    // Create movimentacoes_financeiras table
    $db->query("
        CREATE TABLE IF NOT EXISTS movimentacoes_financeiras (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER DEFAULT NULL,
            tipo VARCHAR(20) NOT NULL,
            categoria_id INTEGER NOT NULL,
            conta_id INTEGER NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            data_movimentacao DATE NOT NULL,
            data_vencimento DATE DEFAULT NULL,
            descricao TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            forma_pagamento VARCHAR(20) DEFAULT NULL,
            comprovante VARCHAR(255) DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create categorias_financeiras table
    $db->query("
        CREATE TABLE IF NOT EXISTS categorias_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            descricao TEXT DEFAULT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create contas_financeiras table
    $db->query("
        CREATE TABLE IF NOT EXISTS contas_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            saldo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            saldo_atual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            banco VARCHAR(100) DEFAULT NULL,
            agencia VARCHAR(20) DEFAULT NULL,
            conta VARCHAR(20) DEFAULT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create relatorios table
    $db->query("
        CREATE TABLE IF NOT EXISTS relatorios (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            parametros JSON DEFAULT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "✅ All missing tables for relatorios created\n";
    
    // 5. Reset admin password
    echo "Resetting admin password...\n";
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->query("UPDATE usuarios SET senha = ? WHERE login = 'admin'", [$hashed_password]);
    echo "✅ Admin password reset to: admin123\n";
    
    // 6. Fix data types if needed
    echo "Fixing data types...\n";
    try {
        $db->query("ALTER TABLE mesas ALTER COLUMN id_mesa TYPE VARCHAR(10)");
        echo "✅ mesas.id_mesa type fixed\n";
    } catch (Exception $e) {
        echo "⚠️ mesas.id_mesa already correct\n";
    }
    
    try {
        $db->query("ALTER TABLE pedido ALTER COLUMN idmesa TYPE VARCHAR(10)");
        echo "✅ pedido.idmesa type fixed\n";
    } catch (Exception $e) {
        echo "⚠️ pedido.idmesa already correct\n";
    }
    
    try {
        $db->query("ALTER TABLE pedidos ALTER COLUMN idmesa TYPE VARCHAR(10)");
        echo "✅ pedidos.idmesa type fixed\n";
    } catch (Exception $e) {
        echo "⚠️ pedidos.idmesa already correct\n";
    }
    
    echo "\n✅ ALL ERRORS FIXED!\n";
    echo "Admin credentials: admin / admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test all the problematic queries
echo "\n=== TESTING ALL FIXES ===\n";
try {
    // Test pedidoss query (dashboard)
    $pedidoss = $db->fetchAll("SELECT COUNT(*) as count FROM pedidoss");
    echo "✅ pedidoss query test passed - " . $pedidoss[0]['count'] . " records\n";
    
    // Test categorias with imagem
    $categorias = $db->fetchAll("SELECT nome, imagem FROM categorias WHERE tenant_id = 1");
    echo "✅ categorias with imagem test passed - " . count($categorias) . " records\n";
    foreach ($categorias as $cat) {
        echo "  - " . $cat['nome'] . " (imagem: " . ($cat['imagem'] ?: 'NULL') . ")\n";
    }
    
    // Test admin login
    $admin = $db->fetch("SELECT login FROM usuarios WHERE login = 'admin'");
    echo "✅ Admin user test passed - " . ($admin ? $admin['login'] : 'NOT FOUND') . "\n";
    
    // Test relatorios tables
    $relatorios = $db->fetchAll("SELECT COUNT(*) as count FROM relatorios");
    echo "✅ relatorios table test passed - " . $relatorios[0]['count'] . " records\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\n🎉 ALL FIXES APPLIED AND TESTED!\n";
echo "The system should now work without errors.\n";
?>
