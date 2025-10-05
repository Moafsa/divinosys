<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

echo "=== DEBUGGING MESA COMPARISON ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Check mesas data
    echo "=== MESAS DATA ===\n";
    $mesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, tenant_id, filial_id FROM mesas ORDER BY id");
    foreach ($mesas as $mesa) {
        echo "ID: " . $mesa['id'] . ", ID_MESA: '" . $mesa['id_mesa'] . "', NUMERO: " . $mesa['numero'] . ", NOME: " . $mesa['nome'] . ", TENANT: " . $mesa['tenant_id'] . ", FILIAL: " . $mesa['filial_id'] . "\n";
    }
    echo "\n";

    // Check pedidos data
    echo "=== PEDIDOS DATA ===\n";
    $pedidos = $db->fetchAll("SELECT idpedido, idmesa, valor_total, status, tenant_id, filial_id FROM pedido WHERE status NOT IN ('Finalizado', 'Cancelado') ORDER BY idpedido");
    foreach ($pedidos as $pedido) {
        echo "PEDIDO ID: " . $pedido['idpedido'] . ", IDMESA: '" . $pedido['idmesa'] . "', VALOR: " . $pedido['valor_total'] . ", STATUS: " . $pedido['status'] . ", TENANT: " . $pedido['tenant_id'] . ", FILIAL: " . $pedido['filial_id'] . "\n";
    }
    echo "\n";

    // Test the query from mesas.php
    echo "=== TESTING MESAS.PHP QUERY ===\n";
    $tenantId = 1;
    $filialId = 1;
    
    $mesasQuery = "SELECT m.*, 
                    CASE WHEN p.idpedido IS NOT NULL THEN 1 ELSE 0 END as tem_pedido,
                    p.idpedido, p.valor_total, p.hora_pedido, p.status as pedido_status
             FROM mesas m 
             LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar AND p.status NOT IN ('Finalizado', 'Cancelado')
             WHERE m.tenant_id = ? AND m.filial_id = ? 
             ORDER BY m.numero::integer";
    
    $mesasResult = $db->fetchAll($mesasQuery, [$tenantId, $filialId]);
    foreach ($mesasResult as $mesa) {
        echo "MESA ID: " . $mesa['id'] . ", ID_MESA: '" . $mesa['id_mesa'] . "', NUMERO: " . $mesa['numero'] . ", TEM_PEDIDO: " . $mesa['tem_pedido'] . ", PEDIDO_ID: " . ($mesa['idpedido'] ?? 'NULL') . "\n";
    }
    echo "\n";

    // Test the query from Dashboard1.php
    echo "=== TESTING DASHBOARD1.PHP QUERY ===\n";
    $dashboardQuery = "SELECT m.*, 
                        CASE WHEN p.idpedido IS NOT NULL THEN 1 ELSE 0 END as tem_pedido,
                        p.idpedido, p.valor_total, p.hora_pedido, p.status as pedido_status
                 FROM mesas m 
                 LEFT JOIN pedido p ON m.numero = p.idmesa::varchar AND p.status NOT IN ('Finalizado', 'Cancelado')
                 WHERE m.tenant_id = ? AND m.filial_id = ? 
                 ORDER BY m.numero::integer";
    
    $dashboardResult = $db->fetchAll($dashboardQuery, [$tenantId, $filialId]);
    foreach ($dashboardResult as $mesa) {
        echo "MESA ID: " . $mesa['id'] . ", ID_MESA: '" . $mesa['id_mesa'] . "', NUMERO: " . $mesa['numero'] . ", TEM_PEDIDO: " . $mesa['tem_pedido'] . ", PEDIDO_ID: " . ($mesa['idpedido'] ?? 'NULL') . "\n";
    }
    echo "\n";

    // Test mesa_multiplos_pedidos.php query
    echo "=== TESTING MESA_MULTIPLOS_PEDIDOS.PHP QUERY ===\n";
    $mesaId = '3'; // This is what mesas.php sends (id_mesa)
    $mesa = $db->fetch("SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?", [$mesaId, $tenantId, $filialId]);
    
    if ($mesa) {
        echo "✅ Mesa found with id_mesa = '$mesaId': " . $mesa['nome'] . "\n";
        
        // Test pedidos query
        $pedidos = $db->fetchAll("SELECT * FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') ORDER BY created_at ASC", [$mesa['id_mesa'], $tenantId, $filialId]);
        echo "Found " . count($pedidos) . " pedidos for this mesa\n";
    } else {
        echo "❌ Mesa not found with id_mesa = '$mesaId'\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
