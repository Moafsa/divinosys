<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

$db = \System\Database::getInstance();

echo "=== VERIFICANDO PEDIDOS NO BANCO ===\n";

// Verificar todos os pedidos
$pedidos = $db->fetchAll('SELECT * FROM pedido ORDER BY created_at DESC LIMIT 10');
echo "Total de pedidos encontrados: " . count($pedidos) . "\n\n";

foreach ($pedidos as $pedido) {
    echo "Pedido ID: " . $pedido['idpedido'] . "\n";
    echo "  Mesa: " . $pedido['idmesa'] . "\n";
    echo "  Status: " . $pedido['status'] . "\n";
    echo "  Data: " . $pedido['data'] . "\n";
    echo "  Hora: " . $pedido['hora_pedido'] . "\n";
    echo "  Valor: R$ " . $pedido['valor_total'] . "\n";
    echo "  Tenant ID: " . $pedido['tenant_id'] . "\n";
    echo "  Filial ID: " . $pedido['filial_id'] . "\n";
    echo "  Created: " . $pedido['created_at'] . "\n";
    echo "  ---\n";
}

echo "\n=== VERIFICANDO SESSÃO ===\n";
$session = \System\Session::getInstance();
$tenant = $session->getTenant();
$filial = $session->getFilial();

echo "Tenant ID da sessão: " . ($tenant['id'] ?? 'NULL') . "\n";
echo "Filial ID da sessão: " . ($filial['id'] ?? 'NULL') . "\n";

echo "\n=== TESTANDO CONSULTA DA PÁGINA DE PEDIDOS ===\n";
if ($tenant && $filial) {
    $pedidos_filtrados = $db->fetchAll(
        "SELECT p.*, m.id_mesa, m.nome as mesa_nome, u.login as usuario_nome
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.data = CURRENT_DATE
         ORDER BY p.hora_pedido DESC",
        [$tenant['id'], $filial['id']]
    );
    
    echo "Pedidos filtrados por tenant/filial e data atual: " . count($pedidos_filtrados) . "\n";
    
    foreach ($pedidos_filtrados as $pedido) {
        echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " - Status: " . $pedido['status'] . "\n";
    }
} else {
    echo "Erro: Tenant ou Filial não encontrados na sessão\n";
}
?>
