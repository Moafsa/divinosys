<?php
require_once 'system/Database.php';

$db = \System\Database::getInstance();
echo "=== TODAS AS MESAS ===\n";
$mesas = $db->fetchAll('SELECT id, id_mesa, numero, nome FROM mesas ORDER BY id');
foreach($mesas as $mesa) {
    echo "ID: " . $mesa['id'] . ", ID_MESA: '" . $mesa['id_mesa'] . "', NUMERO: " . $mesa['numero'] . ", NOME: " . $mesa['nome'] . "\n";
}
?>
