<?php
require_once 'system/Database.php';

echo "=== LIMPEZA SIMPLES DE PEDIDOS ANTIGOS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // Finalizar pedidos antigos
    echo "=== FINALIZANDO PEDIDOS ANTIGOS ===\n";
    $resultado = $db->update(
        'pedido',
        ['status' => 'Finalizado'],
        'status IN (?, ?, ?, ?) AND created_at <= NOW() - INTERVAL ?',
        ['Pendente', 'Preparando', 'Pronto', 'Entregue', '2 hours']
    );
    
    echo "✅ " . $resultado . " pedido(s) antigo(s) finalizado(s) automaticamente.\n";
    
    // Verificar pedidos ativos restantes
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
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
