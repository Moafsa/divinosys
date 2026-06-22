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
    
    $cpf = $_GET['cpf'] ?? '';
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
    
    if (empty($telefoneLimpo) && empty($cpfLimpo)) {
        throw new \Exception('Telefone ou CPF inválido');
    }
    
    $db = \System\Database::getInstance();
    
    // Busca na tabela usuarios_globais (que é usada no Divinosys para clientes)
    $cliente = null;
    if (!empty($cpfLimpo)) {
        $cliente = $db->fetch(
            "SELECT id, nome, telefone, cpf FROM usuarios_globais WHERE REGEXP_REPLACE(cpf, '[^0-9]', '', 'g') LIKE ?", 
            ['%' . $cpfLimpo . '%']
        );
    } else if (!empty($telefoneLimpo)) {
        $cliente = $db->fetch(
            "SELECT id, nome, telefone, cpf FROM usuarios_globais WHERE REGEXP_REPLACE(telefone, '[^0-9]', '', 'g') LIKE ?", 
            ['%' . $telefoneLimpo . '%']
        );
    }
    
    if ($cliente) {
        echo json_encode([
            'success' => true,
            'cliente' => [
                'id' => $cliente['id'],
                'nome' => $cliente['nome'],
                'telefone' => $cliente['telefone'],
                'cpf' => $cliente['cpf']
            ]
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
