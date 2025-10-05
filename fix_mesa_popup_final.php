<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

echo "=== FIXING MESA POPUP FINAL ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Check mesas table structure
    echo "=== MESAS TABLE STRUCTURE ===\n";
    $stmt = $db->getConnection()->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'mesas' ORDER BY ordinal_position");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- " . $col['column_name'] . ": " . $col['data_type'] . "\n";
    }
    echo "\n";

    // Check all mesas
    echo "=== ALL MESAS ===\n";
    $allMesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, tenant_id, filial_id FROM mesas ORDER BY id");
    foreach ($allMesas as $mesa) {
        echo "ID: " . $mesa['id'] . ", ID_MESA: " . $mesa['id_mesa'] . ", NUMERO: " . $mesa['numero'] . ", NOME: " . $mesa['nome'] . ", TENANT: " . $mesa['tenant_id'] . ", FILIAL: " . $mesa['filial_id'] . "\n";
    }
    echo "\n";

    // Test the exact query from mesa_multiplos_pedidos.php
    echo "=== TESTING MESA QUERY ===\n";
    $mesaId = 256; // This is the ID being passed from the frontend
    $tenantId = 1;
    $filialId = 1;

    echo "Looking for mesa with ID: $mesaId, tenant: $tenantId, filial: $filialId\n";

    // First try with tenant/filial
    $mesa = $db->fetch(
        "SELECT * FROM mesas WHERE id = ? AND tenant_id = ? AND filial_id = ?",
        [$mesaId, $tenantId, $filialId]
    );

    if ($mesa) {
        echo "✅ Mesa found with tenant/filial filter: " . $mesa['nome'] . "\n";
    } else {
        echo "❌ Mesa not found with tenant/filial filter\n";
        
        // Try without tenant/filial filter
        $mesa = $db->fetch(
            "SELECT * FROM mesas WHERE id = ?",
            [$mesaId]
        );
        
        if ($mesa) {
            echo "✅ Mesa found without tenant/filial filter: " . $mesa['nome'] . "\n";
            echo "  - tenant_id: " . $mesa['tenant_id'] . "\n";
            echo "  - filial_id: " . $mesa['filial_id'] . "\n";
        } else {
            echo "❌ Mesa not found even without tenant/filial filter\n";
            
            // Check if mesa with numero = 3 exists
            $mesaNumero3 = $db->fetch("SELECT * FROM mesas WHERE numero = 3 OR id_mesa = '3'");
            if ($mesaNumero3) {
                echo "✅ Found mesa with numero/id_mesa = 3: ID=" . $mesaNumero3['id'] . ", nome=" . $mesaNumero3['nome'] . "\n";
            } else {
                echo "❌ No mesa found with numero/id_mesa = 3\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
