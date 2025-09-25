<?php

require_once '../system/Database.php';
require_once '../system/EvolutionAPI.php';

use System\Database;
use System\EvolutionAPI;

// Inicializar sistema
Database::init();
EvolutionAPI::init();

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Obter dados do webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Processar webhook
    $result = EvolutionAPI::processWebhook($data);
    
    if ($result) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Webhook processado com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Erro ao processar webhook']);
    }
    
} catch (Exception $e) {
    error_log("Erro no webhook Evolution: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
