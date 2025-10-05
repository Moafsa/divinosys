<?php
require_once 'system/Database.php';

echo "=== VERIFICANDO PEDIDOS ATIVOS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";

    // Verificar pedidos ativos
    echo "=== PEDIDOS ATIVOS (não finalizados/cancelados) ===\n";
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
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    echo "\n=== PEDIDOS RECENTES (últimas 2 horas) ===\n";
    $pedidosRecentes = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND p.created_at > NOW() - INTERVAL '2 hours'
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosRecentes)) {
        echo "✅ Nenhum pedido recente encontrado.\n";
    } else {
        echo "❌ Encontrados " . count($pedidosRecentes) . " pedido(s) recente(s):\n";
        foreach($pedidosRecentes as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    echo "\n=== LIMPEZA DE PEDIDOS ANTIGOS ===\n";
    // Verificar pedidos antigos que podem estar causando problema
    $pedidosAntigos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND p.created_at <= NOW() - INTERVAL '2 hours'
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosAntigos)) {
        echo "✅ Nenhum pedido antigo encontrado.\n";
    } else {
        echo "⚠️ Encontrados " . count($pedidosAntigos) . " pedido(s) antigo(s) que podem estar causando problema:\n";
        foreach($pedidosAntigos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . " - Criado: " . $pedido['created_at'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
