<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

$db = \System\Database::getInstance();

echo "=== TESTANDO BUSCAR PEDIDO ===\n";

// Testar buscar pedido 26 (que está na imagem)
$pedidoId = 26;

try {
    echo "Buscando pedido ID: $pedidoId\n";
    
    $pedido = $db->fetch(
        "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
        [$pedidoId, 1, 1]
    );
    
    if ($pedido) {
        echo "Pedido encontrado:\n";
        echo "- ID: " . $pedido['idpedido'] . "\n";
        echo "- Mesa: " . $pedido['idmesa'] . "\n";
        echo "- Cliente: " . $pedido['cliente'] . "\n";
        echo "- Status: " . $pedido['status'] . "\n";
        echo "- Valor: " . $pedido['valor_total'] . "\n";
        
        // Buscar itens
        $itens = $db->fetchAll(
            "SELECT pi.*, pr.nome as produto_nome 
             FROM pedido_itens pi 
             LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = ?
             WHERE pi.pedido_id = ? AND pi.tenant_id = ?",
            [1, $pedidoId, 1]
        );
        
        echo "\nItens encontrados: " . count($itens) . "\n";
        
        foreach ($itens as $item) {
            echo "- Item ID: " . $item['id'] . ", Produto: " . $item['produto_nome'] . "\n";
            echo "  Ingredientes COM: '" . ($item['ingredientes_com'] ?? 'NULL') . "'\n";
            echo "  Ingredientes SEM: '" . ($item['ingredientes_sem'] ?? 'NULL') . "'\n";
        }
        
    } else {
        echo "Pedido não encontrado!\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== TESTANDO BUSCAR PEDIDO 25 ===\n";

try {
    $pedidoId = 25;
    echo "Buscando pedido ID: $pedidoId\n";
    
    $pedido = $db->fetch(
        "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
        [$pedidoId, 1, 1]
    );
    
    if ($pedido) {
        echo "Pedido encontrado: " . $pedido['cliente'] . "\n";
    } else {
        echo "Pedido não encontrado!\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
