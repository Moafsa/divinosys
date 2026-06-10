<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $telefone = $_GET['telefone'] ?? '';
    $telefoneLimpo = preg_replace('/[^0-9]/', '', $telefone);
    
    if (empty($telefoneLimpo)) {
        throw new \Exception('Telefone inválido');
    }
    
    $db = \System\Database::getInstance();
    
    // Busca na tabela usuarios_globais (que é usada no Divinosys para clientes)
    $cliente = $db->fetch(
        "SELECT id, nome, telefone FROM usuarios_globais WHERE REGEXP_REPLACE(telefone, '[^0-9]', '', 'g') LIKE ?", 
        ['%' . $telefoneLimpo . '%']
    );
    
    if ($cliente) {
        echo json_encode([
            'success' => true,
            'cliente' => [
                'id' => $cliente['id'],
                'nome' => $cliente['nome'],
                'telefone' => $cliente['telefone']
            ]
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
