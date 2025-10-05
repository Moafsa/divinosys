<?php
require_once 'system/Database.php';

echo "=== CORRIGINDO STATUS DAS MESAS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // 1. Finalizar pedidos antigos (mais de 2 horas)
    echo "=== FINALIZANDO PEDIDOS ANTIGOS ===\n";
    $resultado = $db->update(
        'pedido',
        ['status' => 'Finalizado'],
        'status IN (?, ?, ?, ?) AND created_at <= NOW() - INTERVAL ?',
        ['Pendente', 'Preparando', 'Pronto', 'Entregue', '2 hours']
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
        echo "Mesa " . $mesa['numero'] . " (ID_MESA: " . $mesa['id_mesa'] . ") - Status: " . $mesa['status'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
