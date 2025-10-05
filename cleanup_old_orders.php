<?php
require_once 'system/Database.php';

echo "=== LIMPEZA DE PEDIDOS ANTIGOS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // Verificar pedidos antigos
    echo "=== VERIFICANDO PEDIDOS ANTIGOS ===\n";
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
        echo "⚠️ Encontrados " . count($pedidosAntigos) . " pedido(s) antigo(s):\n";
        foreach($pedidosAntigos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . " - Criado: " . $pedido['created_at'] . "\n";
        }
        
        echo "\n=== FINALIZANDO PEDIDOS ANTIGOS ===\n";
        $resultado = $db->update(
            'pedido',
            ['status' => 'Finalizado'],
            'status IN (?, ?, ?, ?) AND created_at <= NOW() - INTERVAL ?',
            ['Pendente', 'Preparando', 'Pronto', 'Entregue', '2 hours']
        );
        
        echo "✅ " . $resultado . " pedido(s) antigo(s) finalizado(s) automaticamente.\n";
    }
    
    echo "\n=== VERIFICANDO PEDIDOS ATIVOS APÓS LIMPEZA ===\n";
    $pedidosAtivos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosAtivos)) {
        echo "✅ Nenhum pedido ativo restante.\n";
    } else {
        echo "⚠️ Ainda existem " . count($pedidosAtivos) . " pedido(s) ativo(s):\n";
        foreach($pedidosAtivos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
