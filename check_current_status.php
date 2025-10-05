<?php
require_once 'system/Database.php';

echo "=== VERIFICANDO STATUS ATUAL DAS MESAS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // 1. Verificar pedidos ativos
    echo "=== PEDIDOS ATIVOS ===\n";
    $pedidos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidos)) {
        echo "✅ Nenhum pedido ativo encontrado.\n";
    } else {
        echo "❌ Encontrados " . count($pedidos) . " pedido(s) ativo(s):\n";
        foreach($pedidos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . " - Criado: " . $pedido['created_at'] . "\n";
        }
    }
    
    // 2. Verificar status das mesas
    echo "\n=== STATUS DAS MESAS ===\n";
    $mesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY numero");
    foreach($mesas as $mesa) {
        // Verificar se há pedidos ativos para esta mesa
        $pedidosMesa = $db->fetchAll("
            SELECT p.idpedido, p.status, p.valor_total, p.hora_pedido, p.created_at
            FROM pedido p 
            WHERE p.idmesa = ? AND p.status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY p.created_at DESC
        ", [$mesa['id_mesa']]);
        
        $statusReal = empty($pedidosMesa) ? 'LIVRE' : 'OCUPADA';
        echo "Mesa " . $mesa['numero'] . " (ID_MESA: " . $mesa['id_mesa'] . ") - Status: " . $statusReal . " - Pedidos Ativos: " . count($pedidosMesa) . "\n";
        
        if (!empty($pedidosMesa)) {
            foreach($pedidosMesa as $pedido) {
                echo "    * Pedido #{$pedido['idpedido']} - Status: {$pedido['status']} - Valor: R$ {$pedido['valor_total']} - Hora: {$pedido['hora_pedido']} - Criado: {$pedido['created_at']}\n";
            }
        }
    }
    
    // 3. Se há pedidos ativos, oferecer opção de finalizar
    if (!empty($pedidos)) {
        echo "\n=== OPÇÕES ===\n";
        echo "1. Finalizar todos os pedidos ativos\n";
        echo "2. Finalizar apenas pedidos antigos (mais de 2 horas)\n";
        echo "3. Manter como está\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
