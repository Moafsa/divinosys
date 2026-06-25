<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../model/Cliente.php';
require_once __DIR__ . '/../../system/TelefoneHelper.php';
require_once __DIR__ . '/../controller/ClienteController.php';

ob_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    error_log("clientes.php - Starting request handling");
    
    $session = \System\Session::getInstance();
    
    error_log("clientes.php - Session initialized: " . ($session->isLoggedIn() ? 'logged in' : 'not logged in'));
    
    // Check if user is logged in
    if (!$session->isLoggedIn()) {
        error_log("clientes.php - User not logged in");
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }
    
    error_log("clientes.php - Creating ClienteController");
    $controller = new \MVC\Controller\ClienteController();
    
    error_log("clientes.php - Calling handleRequest");
    $controller->handleRequest();
    
} catch (Exception $e) {
    error_log("clientes.php - Exception: " . $e->getMessage());
    error_log("clientes.php - Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
