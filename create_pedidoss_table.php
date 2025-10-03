<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== CREATING PEDIDOSS TABLE (AS EXPECTED BY CODE) ===\n";

$db = \System\Database::getInstance();

try {
    // Check if pedidoss table exists
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $table_names = array_column($tables, 'table_name');
    
    if (!in_array('pedidoss', $table_names)) {
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
                usuario_id INTEGER DEFAULT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ pedidoss table created\n";
        
        // Insert sample data
        echo "Inserting sample data...\n";
        $db->query("INSERT INTO pedidoss (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
            ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
        echo "✅ Sample data inserted\n";
        
    } else {
        echo "✅ pedidoss table already exists\n";
    }
    
    // Update data
    echo "Updating data...\n";
    $db->query("UPDATE pedidoss SET usuario_id = 1 WHERE usuario_id IS NULL");
    $db->query("UPDATE pedidoss SET delivery = false WHERE delivery IS NULL");
    echo "✅ Data updated\n";
    
    // Test the table
    echo "Testing pedidoss table...\n";
    $pedidoss = $db->fetchAll("SELECT COUNT(*) as count FROM pedidoss");
    echo "✅ Pedidoss records: " . $pedidoss[0]['count'] . "\n";
    
    // Test the exact query from Dashboard1.php
    echo "Testing dashboard query...\n";
    $dashboard_test = $db->fetchAll("
        SELECT p.*, m.numero as mesa_numero, m.id as mesa_id,
                COUNT(p.idpedido) OVER (PARTITION BY p.idmesa) as total_pedidos_mesa
         FROM pedidoss p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.numero::varchar AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.status NOT IN ('Finalizado', 'Cancelado')
         ORDER BY p.idmesa, p.created_at ASC", [1, 1]);
    echo "✅ Dashboard query test passed - Found " . count($dashboard_test) . " active orders\n";
    
    echo "\n✅ PEDIDOSS TABLE CREATED AND TESTED!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
