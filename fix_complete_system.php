<?php
require_once 'system/Database.php';

echo "=== CORREÇÃO COMPLETA DO SISTEMA ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // 1. Limpar pedidos antigos (mais de 4 horas)
    echo "=== LIMPANDO PEDIDOS ANTIGOS ===\n";
    $resultado = $db->update(
        'pedido',
        ['status' => 'Finalizado'],
        'status IN (?, ?, ?, ?) AND created_at <= NOW() - INTERVAL ?',
        ['Pendente', 'Preparando', 'Pronto', 'Entregue', '4 hours']
    );
    echo "✅ " . $resultado . " pedido(s) antigo(s) finalizado(s).\n";
    
    // 2. Verificar pedidos ativos restantes
    echo "\n=== VERIFICANDO PEDIDOS ATIVOS RESTANTES ===\n";
    $pedidosAtivos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosAtivos)) {
        echo "✅ Nenhum pedido ativo restante. Todas as mesas devem estar livres agora.\n";
    } else {
        echo "⚠️ Ainda existem " . count($pedidosAtivos) . " pedido(s) ativo(s):\n";
        foreach($pedidosAtivos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    // 3. Verificar status das mesas
    echo "\n=== VERIFICANDO STATUS DAS MESAS ===\n";
    $mesas = $db->fetchAll("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY numero");
    foreach($mesas as $mesa) {
        // Verificar se há pedidos ativos para esta mesa
        $pedidosMesa = $db->fetchAll("
            SELECT p.idpedido, p.status, p.valor_total, p.hora_pedido
            FROM pedido p 
            WHERE p.idmesa = ? AND p.status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY p.created_at DESC
        ", [$mesa['id_mesa']]);
        
        $statusReal = empty($pedidosMesa) ? 'LIVRE' : 'OCUPADA';
        echo "Mesa " . $mesa['numero'] . " (ID_MESA: " . $mesa['id_mesa'] . ") - Status Real: " . $statusReal . " - Pedidos Ativos: " . count($pedidosMesa) . "\n";
        
        if (!empty($pedidosMesa)) {
            foreach($pedidosMesa as $pedido) {
                echo "    * Pedido #{$pedido['idpedido']} - Status: {$pedido['status']} - Valor: R$ {$pedido['valor_total']} - Hora: {$pedido['hora_pedido']}\n";
            }
        }
    }
    
    // 4. Verificar inconsistências
    echo "\n=== VERIFICANDO INCONSISTÊNCIAS ===\n";
    
    // Mesas com múltiplos pedidos ativos
    $inconsistencias = $db->fetchAll("
        SELECT p.idmesa, COUNT(*) as total_pedidos
        FROM pedido p 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        GROUP BY p.idmesa
        HAVING COUNT(*) > 1
    ");
    
    if (!empty($inconsistencias)) {
        echo "⚠️ MESAS COM MÚLTIPLOS PEDIDOS ATIVOS:\n";
        foreach($inconsistencias as $inc) {
            echo "  - Mesa {$inc['idmesa']}: {$inc['total_pedidos']} pedidos ativos\n";
        }
    } else {
        echo "✅ Nenhuma mesa com múltiplos pedidos ativos\n";
    }
    
    echo "\n✅ SISTEMA CORRIGIDO!\n";
    echo "Agora teste o dashboard para ver se as mesas estão mostrando o status correto.\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
