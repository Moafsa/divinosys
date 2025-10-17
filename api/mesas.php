<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$tenant || !$filial) {
        throw new Exception('Sessão inválida');
    }
    
    // Buscar mesas
    $mesas = $db->fetchAll("
        SELECT 
            id_mesa,
            nome,
            CASE 
                WHEN status = 'livre' THEN 'livre'
                ELSE 'ocupada'
            END as status
        FROM mesas 
        WHERE tenant_id = ? AND filial_id = ? 
        ORDER BY id_mesa
    ", [$tenant['id'], $filial['id']]);
    
    // Adicionar opção de delivery
    $mesas[] = [
        'id_mesa' => 'delivery',
        'nome' => 'Delivery',
        'status' => 'livre'
    ];
    
    echo json_encode([
        'success' => true,
        'mesas' => $mesas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
