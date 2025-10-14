<?php
/**
 * Apply Partial Payment Migration
 * Executes the database migration to add partial payment support
 */

require_once __DIR__ . '/system/Database.php';

echo "==============================================\n";
echo "PARTIAL PAYMENT MIGRATION\n";
echo "==============================================\n\n";

try {
    $db = \System\Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Reading migration file...\n";
    $migrationFile = __DIR__ . '/database/migrations/add_partial_payment_support.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    echo "Executing migration...\n\n";
    
    // Execute the SQL
    $result = pg_query($conn, $sql);
    
    if ($result === false) {
        throw new Exception("Migration failed: " . pg_last_error($conn));
    }
    
    echo "✓ Migration executed successfully!\n\n";
    
    // Verify the changes
    echo "Verifying changes...\n";
    
    // Check if columns exist
    $checkColumns = "
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        AND column_name IN ('valor_pago', 'saldo_devedor', 'status_pagamento')
        ORDER BY column_name
    ";
    
    $result = pg_query($conn, $checkColumns);
    $columns = pg_fetch_all($result);
    
    if ($columns && count($columns) === 3) {
        echo "✓ Columns added to 'pedido' table:\n";
        foreach ($columns as $col) {
            echo "  - {$col['column_name']} ({$col['data_type']})\n";
        }
    } else {
        echo "⚠ Warning: Expected 3 columns, found " . count($columns) . "\n";
    }
    
    // Check if pagamentos_pedido table exists
    $checkTable = "
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'pagamentos_pedido'
        ) as exists
    ";
    
    $result = pg_query($conn, $checkTable);
    $tableExists = pg_fetch_assoc($result);
    
    if ($tableExists['exists'] === 't') {
        echo "\n✓ Table 'pagamentos_pedido' created successfully\n";
        
        // Count columns in the table
        $countColumns = "
            SELECT COUNT(*) as count 
            FROM information_schema.columns 
            WHERE table_name = 'pagamentos_pedido'
        ";
        
        $result = pg_query($conn, $countColumns);
        $columnCount = pg_fetch_assoc($result);
        echo "  - Table has {$columnCount['count']} columns\n";
    } else {
        echo "\n⚠ Warning: Table 'pagamentos_pedido' was not created\n";
    }
    
    // Update existing pedidos
    echo "\nUpdating existing orders...\n";
    
    $updateExisting = "
        UPDATE pedido 
        SET 
            valor_pago = CASE WHEN status = 'Finalizado' THEN valor_total ELSE 0.00 END,
            saldo_devedor = CASE WHEN status = 'Finalizado' THEN 0.00 ELSE valor_total END,
            status_pagamento = CASE WHEN status = 'Finalizado' THEN 'quitado' ELSE 'pendente' END
        WHERE status_pagamento IS NULL OR saldo_devedor IS NULL
    ";
    
    $result = pg_query($conn, $updateExisting);
    $affected = pg_affected_rows($result);
    
    echo "✓ Updated {$affected} orders\n";
    
    echo "\n==============================================\n";
    echo "MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "==============================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Include the JavaScript file in your pages:\n";
    echo "   <script src=\"assets/js/pagamentos-parciais.js\"></script>\n\n";
    echo "2. Replace your close order buttons with:\n";
    echo "   <button onclick=\"abrirModalPagamento(pedidoId)\">Fechar Pedido</button>\n\n";
    echo "3. Test the partial payment functionality\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

