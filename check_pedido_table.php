<?php
require_once __DIR__ . '/system/Config.php';
require_once __System\Database::getInstance();

echo "=== CHECKING PEDIDO TABLE ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Check if pedido table exists
    echo "=== CHECKING TABLE EXISTENCE ===\n";
    $stmt = $db->getConnection()->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pedido')");
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        echo "✅ Table 'pedido' EXISTS\n";
        
        // Check table structure
        echo "\n=== TABLE STRUCTURE ===\n";
        $stmt = $db->getConnection()->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'pedido' ORDER BY ordinal_position");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- " . $col['column_name'] . ": " . $col['data_type'] . "\n";
        }
        
        // Check data
        echo "\n=== TABLE DATA ===\n";
        $count = $db->fetch("SELECT COUNT(*) as count FROM pedido");
        echo "Total records: " . $count['count'] . "\n";
        
        if ($count['count'] > 0) {
            $sample = $db->fetchAll("SELECT * FROM pedido LIMIT 3");
            echo "\nSample data:\n";
            foreach ($sample as $row) {
                echo "ID: " . $row['idpedido'] . ", Mesa: " . $row['idmesa'] . ", Status: " . $row['status'] . ", Valor: " . $row['valor_total'] . "\n";
            }
        }
        
    } else {
        echo "❌ Table 'pedido' does NOT exist\n";
        
        // Check what pedido-related tables exist
        echo "\n=== CHECKING RELATED TABLES ===\n";
        $stmt = $db->getConnection()->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%pedido%' ORDER BY tablename");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "- " . $table . "\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
