<?php
require_once 'system/Database.php';

try {
    $db = \System\Database::getInstance();
    echo "=== INVESTIGANDO STATUS DAS MESAS ===\n\n";
    
    // Verificar todas as mesas
    echo "=== TODAS AS MESAS ===\n";
    $mesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY numero");
    foreach($mesas as $mesa) {
        echo "Mesa " . $mesa['numero'] . " (ID: " . $mesa['id'] . ", ID_MESA: " . $mesa['id_mesa'] . ") - Status: " . $mesa['status'] . "\n";
    }
    
    echo "\n=== PEDIDOS ATIVOS ===\n";
    $pedidos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidos)) {
        echo "Nenhum pedido ativo encontrado.\n";
    } else {
        foreach($pedidos as $pedido) {
            echo "Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    echo "\n=== VERIFICANDO MESA 3 ESPECIFICAMENTE ===\n";
    $mesa3 = $db->fetch("SELECT * FROM mesas WHERE id_mesa = '3'");
    if ($mesa3) {
        echo "Mesa 3 - Status na tabela mesas: " . $mesa3['status'] . "\n";
        
        $pedidosMesa3 = $db->fetchAll("
            SELECT * FROM pedido 
            WHERE idmesa = '3' AND status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY created_at DESC
        ");
        
        if (empty($pedidosMesa3)) {
            echo "Mesa 3: Nenhum pedido ativo encontrado.\n";
        } else {
            echo "Mesa 3: " . count($pedidosMesa3) . " pedido(s) ativo(s):\n";
            foreach($pedidosMesa3 as $pedido) {
                echo "  - Pedido #" . $pedido['idpedido'] . " - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . "\n";
            }
        }
    }
    
    echo "\n=== VERIFICANDO MESA 8 ESPECIFICAMENTE ===\n";
    $mesa8 = $db->fetch("SELECT * FROM mesas WHERE id_mesa = '8'");
    if ($mesa8) {
        echo "Mesa 8 - Status na tabela mesas: " . $mesa8['status'] . "\n";
        
        $pedidosMesa8 = $db->fetchAll("
            SELECT * FROM pedido 
            WHERE idmesa = '8' AND status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY created_at DESC
        ");
        
        if (empty($pedidosMesa8)) {
            echo "Mesa 8: Nenhum pedido ativo encontrado.\n";
        } else {
            echo "Mesa 8: " . count($pedidosMesa8) . " pedido(s) ativo(s):\n";
            foreach($pedidosMesa8 as $pedido) {
                echo "  - Pedido #" . $pedido['idpedido'] . " - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
