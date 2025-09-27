<?php
require_once 'system/Database.php';

$db = System\Database::getInstance();
$result = $db->query('DESCRIBE whatsapp_instances');

echo "Columns in whatsapp_instances table:\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
