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
    
    // Buscar produtos
    $produtos = $db->fetchAll("
        SELECT 
            id,
            nome,
            preco,
            categoria,
            ativo
        FROM produtos 
        WHERE tenant_id = ? AND filial_id = ? AND ativo = 1
        ORDER BY categoria, nome
    ", [$tenant['id'], $filial['id']]);
    
    echo json_encode([
        'success' => true,
        'produtos' => $produtos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
