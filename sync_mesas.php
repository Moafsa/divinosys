<?php
require_once 'system/Database.php';

$db = \System\Database::getInstance();
$mesas = $db->fetchAll('SELECT id, id_mesa, numero, status FROM mesas ORDER BY numero::integer');

echo "Mesas encontradas: " . count($mesas) . "\n";
$corrigidas = 0;

foreach($mesas as $mesa) {
    $pedidos = $db->fetchAll('SELECT COUNT(*) as total FROM pedido WHERE idmesa::varchar = ? AND status IN (?, ?, ?, ?)', [$mesa['id_mesa'], 'Pendente', 'Preparando', 'Pronto', 'Entregue']);
    $novoStatus = $pedidos[0]['total'] > 0 ? 'ocupada' : 'livre';
    
    if($mesa['status'] !== $novoStatus) {
        $db->update('mesas', ['status' => $novoStatus], 'id = ?', [$mesa['id']]);
        echo "Mesa " . $mesa['numero'] . ": " . $mesa['status'] . " -> " . $novoStatus . "\n";
        $corrigidas++;
    }
}

echo "Mesas corrigidas: " . $corrigidas . "\n";
?>
