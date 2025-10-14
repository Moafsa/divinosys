<?php

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Auth.php';
require_once __DIR__ . '/../../system/Session.php';

use System\Auth;
use System\Database;
use System\Session;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Initialize database and session
    $config = \System\Config::getInstance();
    $db = Database::getInstance();
    Auth::init();
    $session = Session::getInstance();

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'solicitar_codigo':
            handleRequestCode();
            break;
            
        case 'validar_codigo':
            handleValidateCode();
            break;
            
        case 'verificar_sessao':
            handleCheckSession();
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        default:
            throw new Exception('Ação não encontrada');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle code request
 */
function handleRequestCode() {
    $telefone = $_POST['telefone'] ?? '';
    
    if (empty($telefone)) {
        throw new Exception('Telefone é obrigatório');
    }
    
    // Clean phone number (remove special characters)
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    // Get tenant and filial from session or default
    $tenantId = $_SESSION['tenant_id'] ?? 1;
    $filialId = $_SESSION['filial_id'] ?? 1;
    
    
    // Generate and send access code
    $result = Auth::generateAndSendAccessCode($telefone, $tenantId, $filialId);
    
    echo json_encode($result);
}

/**
 * Handle code validation
 */
function handleValidateCode() {
    $telefone = $_POST['telefone'] ?? '';
    $codigo = $_POST['codigo'] ?? '';
    
    if (empty($telefone) || empty($codigo)) {
        throw new Exception('Telefone e código são obrigatórios');
    }
    
    // Clean phone number
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    // Get tenant and filial from session or default
    $tenantId = $_SESSION['tenant_id'] ?? 1;
    $filialId = $_SESSION['filial_id'] ?? null;
    
    // Validate access code
    $result = Auth::validateAccessCode($telefone, $codigo, $tenantId, $filialId);
    
    if ($result['success']) {
        // Set session data in the format expected by the main system
        $_SESSION['auth_token'] = $result['session_token'];
        $_SESSION['usuario_global_id'] = $result['user']['usuario_global_id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['filial_id'] = $filialId;
        $_SESSION['user_type'] = $result['establishment']['tipo_usuario'];
        $_SESSION['permissions'] = $result['permissions'];
        $_SESSION['user_name'] = $result['user']['nome'];
        
        // Set session data in the format expected by Session class
        $_SESSION['user'] = [
            'id' => $result['user']['usuario_global_id'],
            'login' => $result['user']['nome'],
            'nivel' => $result['establishment']['tipo_usuario'] === 'admin' ? 1 : 2,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ];
        $_SESSION['user_id'] = $result['user']['usuario_global_id'];
        $_SESSION['user_login'] = $result['user']['nome'];
        $_SESSION['user_nivel'] = $result['establishment']['tipo_usuario'] === 'admin' ? 1 : 2;
    }
    
    echo json_encode($result);
}

/**
 * Handle session check
 */
function handleCheckSession() {
    $sessionData = Auth::validateSession();
    
    if ($sessionData) {
        // Get user permissions
        $userEstablishment = Database::getInstance()->fetch(
            "SELECT * FROM usuarios_estabelecimento 
             WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
            [$sessionData['usuario_global_id'], $sessionData['tenant_id'], $sessionData['filial_id']]
        );
        
        if ($userEstablishment) {
            $permissions = Auth::getUserPermissions($userEstablishment['tipo_usuario']);
            
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => $sessionData,
                'user_type' => $userEstablishment['tipo_usuario'],
                'permissions' => $permissions
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'message' => 'Sessão inválida'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Sessão expirada'
        ]);
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    Auth::logout();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logout realizado com sucesso'
    ]);
}
