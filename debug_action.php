<?php
// Debug action parameter
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

echo json_encode([
    'method' => $method,
    'action' => $action,
    'action_length' => strlen($action),
    'action_bytes' => bin2hex($action),
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
]);
?>
