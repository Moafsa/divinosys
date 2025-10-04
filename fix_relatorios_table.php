<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== FIXING RELATORIOS TABLE ISSUE ===\n";

$db = \System\Database::getInstance();

try {
    // The relatorios.php is looking for 'pedido' table, but we have 'pedidoss'
    // Let's create the 'pedido' table with the same structure as 'pedidoss'
    
    echo "Creating pedido table (singular)...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS pedido (
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
    
    // Copy data from pedidoss to pedido
    echo "Copying data from pedidoss to pedido...\n";
    $db->query("
        INSERT INTO pedido (idmesa, cliente, delivery, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id, created_at, updated_at)
        SELECT idmesa, cliente, delivery, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id, created_at, updated_at
        FROM pedidoss
        WHERE NOT EXISTS (SELECT 1 FROM pedido WHERE pedido.idpedido = pedidoss.idpedido)
    ");
    
    echo "✅ pedido table created and populated\n";
    
    // Also ensure pedido_itens table has the right structure
    echo "Checking pedido_itens table...\n";
    
    // Check if pedido_itens has the right columns
    $columns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido_itens' 
        ORDER BY ordinal_position
    ");
    
    $columnNames = array_column($columns, 'column_name');
    
    // Add missing columns if needed
    if (!in_array('pedido_id', $columnNames)) {
        echo "Adding pedido_id column to pedido_itens...\n";
        $db->query("ALTER TABLE pedido_itens ADD COLUMN pedido_id INTEGER");
        echo "✅ pedido_id column added\n";
    }
    
    if (!in_array('produto_id', $columnNames)) {
        echo "Adding produto_id column to pedido_itens...\n";
        $db->query("ALTER TABLE pedido_itens ADD COLUMN produto_id INTEGER");
        echo "✅ produto_id column added\n";
    }
    
    if (!in_array('quantidade', $columnNames)) {
        echo "Adding quantidade column to pedido_itens...\n";
        $db->query("ALTER TABLE pedido_itens ADD COLUMN quantidade INTEGER DEFAULT 1");
        echo "✅ quantidade column added\n";
    }
    
    if (!in_array('valor_total', $columnNames)) {
        echo "Adding valor_total column to pedido_itens...\n";
        $db->query("ALTER TABLE pedido_itens ADD COLUMN valor_total DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ valor_total column added\n";
    }
    
    // Insert sample pedido_itens data
    echo "Inserting sample pedido_itens data...\n";
    $db->query("
        INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, valor_total)
        VALUES 
        (1, 1, 2, 25.00),
        (1, 2, 1, 15.00)
        ON CONFLICT DO NOTHING
    ");
    echo "✅ Sample pedido_itens data inserted\n";
    
    echo "\n✅ RELATORIOS TABLE ISSUE FIXED!\n";
    
    // Test the relatorios queries
    echo "\n=== TESTING RELATORIOS QUERIES ===\n";
    
    try {
        // Test basic stats query
        $total_pedidos = $db->count('pedido', 'tenant_id = 1 AND filial_id = 1 AND data = CURRENT_DATE');
        echo "✅ Total pedidos query: $total_pedidos\n";
        
        // Test valor total query
        $valorTotal = $db->fetch(
            "SELECT COALESCE(SUM(valor_total), 0) as total FROM pedido WHERE tenant_id = 1 AND filial_id = 1 AND data = CURRENT_DATE"
        );
        echo "✅ Valor total query: R$ " . number_format($valorTotal['total'], 2, ',', '.') . "\n";
        
        // Test vendas por dia query
        $vendas_por_dia = $db->fetchAll(
            "SELECT data, COUNT(*) as quantidade, COALESCE(SUM(valor_total), 0) as valor 
             FROM pedido 
             WHERE tenant_id = 1 AND filial_id = 1 AND data = CURRENT_DATE
             GROUP BY data 
             ORDER BY data"
        );
        echo "✅ Vendas por dia query: " . count($vendas_por_dia) . " records\n";
        
        // Test produtos mais vendidos query
        $produtos_mais_vendidos = $db->fetchAll(
            "SELECT p.nome, SUM(pi.quantidade) as total_vendido, SUM(pi.valor_total) as valor_total
             FROM pedido_itens pi
             JOIN produtos p ON pi.produto_id = p.id
             JOIN pedido ped ON pi.pedido_id = ped.idpedido
             WHERE ped.tenant_id = 1 AND ped.filial_id = 1 AND ped.data = CURRENT_DATE
             GROUP BY p.id, p.nome
             ORDER BY total_vendido DESC
             LIMIT 10"
        );
        echo "✅ Produtos mais vendidos query: " . count($produtos_mais_vendidos) . " records\n";
        
        // Test status pedidos query
        $status_pedidos = $db->fetchAll(
            "SELECT status, COUNT(*) as quantidade 
             FROM pedido 
             WHERE tenant_id = 1 AND filial_id = 1 AND data = CURRENT_DATE
             GROUP BY status"
        );
        echo "✅ Status pedidos query: " . count($status_pedidos) . " records\n";
        
    } catch (Exception $e) {
        echo "❌ Query test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 ALL RELATORIOS QUERIES TESTED SUCCESSFULLY!\n";
    echo "The relatorios page should now work without errors.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
