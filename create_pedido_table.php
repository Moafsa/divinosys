<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== CREATING MISSING PEDIDO TABLE ===\n";

$db = \System\Database::getInstance();

try {
    // Check if pedido table exists
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $table_names = array_column($tables, 'table_name');
    
    if (!in_array('pedido', $table_names)) {
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
                usuario_id INTEGER DEFAULT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ pedido table created\n";
        
        // Insert sample data
        echo "Inserting sample data...\n";
        $db->query("INSERT INTO pedido (idmesa, cliente, status, valor_total, data, hora_pedido, usuario_id, tenant_id, filial_id) VALUES
            ('1', 'Cliente Teste', 'Pendente', 25.00, CURRENT_DATE, CURRENT_TIME, 1, 1, 1)");
        echo "✅ Sample data inserted\n";
        
    } else {
        echo "✅ pedido table already exists\n";
        
        // Check if it has the right columns
        $columns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = 'pedido'");
        $column_names = array_column($columns, 'column_name');
        
        if (!in_array('usuario_id', $column_names)) {
            echo "Adding usuario_id column...\n";
            $db->query("ALTER TABLE pedido ADD COLUMN usuario_id INTEGER DEFAULT NULL");
            echo "✅ usuario_id column added\n";
        }
        
        if (!in_array('delivery', $column_names)) {
            echo "Adding delivery column...\n";
            $db->query("ALTER TABLE pedido ADD COLUMN delivery BOOLEAN DEFAULT false");
            echo "✅ delivery column added\n";
        }
    }
    
    // Update data
    echo "Updating data...\n";
    $db->query("UPDATE pedido SET usuario_id = 1 WHERE usuario_id IS NULL");
    $db->query("UPDATE pedido SET delivery = false WHERE delivery IS NULL");
    echo "✅ Data updated\n";
    
    // Test the table
    echo "Testing pedido table...\n";
    $pedidos = $db->fetchAll("SELECT COUNT(*) as count FROM pedido");
    echo "✅ Pedido records: " . $pedidos[0]['count'] . "\n";
    
    // Test a query that the dashboard might use
    echo "Testing dashboard query...\n";
    $dashboard_test = $db->fetchAll("SELECT p.*, u.login as usuario_nome FROM pedido p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.tenant_id = ? AND p.filial_id = ? AND p.delivery = true AND p.data >= CURRENT_DATE - INTERVAL '7 days' ORDER BY p.hora_pedido DESC", [1, 1]);
    echo "✅ Dashboard query test passed - Found " . count($dashboard_test) . " delivery orders\n";
    
    echo "\n✅ PEDIDO TABLE CREATED AND TESTED!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
