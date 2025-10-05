<?php
$host = 'postgres';
$port = '5432';
$dbname = 'divino_lanches';
$user = 'postgres';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== LIMPEZA DE PEDIDOS ANTIGOS ===\n";
    
    // Finalizar pedidos antigos
    $stmt = $pdo->prepare("UPDATE pedido SET status = 'Finalizado' WHERE status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue') AND created_at <= NOW() - INTERVAL '2 hours'");
    $stmt->execute();
    $resultado = $stmt->rowCount();
    echo "✅ " . $resultado . " pedido(s) antigo(s) finalizado(s).\n";
    
    // Verificar pedidos ativos restantes
    echo "\n=== VERIFICANDO PEDIDOS ATIVOS RESTANTES ===\n";
    $stmt = $pdo->query("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    $pedidosAtivos = $stmt->fetchAll();
    
    if (empty($pedidosAtivos)) {
        echo "✅ Nenhum pedido ativo restante. Todas as mesas devem estar livres agora.\n";
    } else {
        echo "⚠️ Ainda existem " . count($pedidosAtivos) . " pedido(s) ativo(s):\n";
        foreach($pedidosAtivos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    echo "\n✅ LIMPEZA CONCLUÍDA!\n";
    echo "As mesas devem estar livres agora. Verifique o dashboard.\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
