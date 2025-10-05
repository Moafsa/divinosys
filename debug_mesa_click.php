<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

echo "=== DEBUG MESA CLICK ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Check all mesas and their IDs
    echo "=== ALL MESAS ===\n";
    $allMesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY id");
    foreach ($allMesas as $mesa) {
        echo "ID: " . $mesa['id'] . ", ID_MESA: " . $mesa['id_mesa'] . ", NUMERO: " . $mesa['numero'] . ", NOME: " . $mesa['nome'] . ", STATUS: " . $mesa['status'] . "\n";
    }
    echo "\n";

    // Test the specific query from mesa_multiplos_pedidos.php
    echo "=== TESTING MESA QUERIES ===\n";
    
    // Test with mesa ID 3 (which should be the mesa that's occupied)
    $testMesaId = 3;
    echo "Testing with mesa ID: $testMesaId\n";
    
    // This is what the current code does
    $mesa = $db->fetch(
        "SELECT * FROM mesas WHERE id = ? AND tenant_id = ? AND filial_id = ?",
        [$testMesaId, 1, 1]
    );
    
    if ($mesa) {
        echo "✅ Mesa found with ID query:\n";
        echo "  - id: " . $mesa['id'] . "\n";
        echo "  - id_mesa: " . $mesa['id_mesa'] . "\n";
        echo "  - numero: " . $mesa['numero'] . "\n";
        echo "  - nome: " . $mesa['nome'] . "\n";
        echo "  - status: " . $mesa['status'] . "\n\n";
        
        // Now test the pedidos query
        echo "=== TESTING PEDIDOS QUERY ===\n";
        $pedidos = $db->fetchAll(
            "SELECT * FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') ORDER BY created_at ASC",
            [$mesa['id_mesa'], 1, 1]
        );
        
        echo "Found " . count($pedidos) . " pedidos for mesa id_mesa: " . $mesa['id_mesa'] . "\n";
        foreach ($pedidos as $pedido) {
            echo "  - Pedido ID: " . $pedido['idpedido'] . ", Valor: " . $pedido['valor_total'] . ", Status: " . $pedido['status'] . "\n";
        }
        
    } else {
        echo "❌ Mesa NOT found with ID query\n";
        
        // Try with id_mesa instead
        echo "Trying with id_mesa query...\n";
        $mesa = $db->fetch(
            "SELECT * FROM mesas WHERE id_mesa::varchar = ? AND tenant_id = ? AND filial_id = ?",
            [$testMesaId, 1, 1]
        );
        
        if ($mesa) {
            echo "✅ Mesa found with id_mesa query:\n";
            echo "  - id: " . $mesa['id'] . "\n";
            echo "  - id_mesa: " . $mesa['id_mesa'] . "\n";
            echo "  - numero: " . $mesa['numero'] . "\n";
            echo "  - nome: " . $mesa['nome'] . "\n";
            echo "  - status: " . $mesa['status'] . "\n\n";
        } else {
            echo "❌ Mesa NOT found with id_mesa query either\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
