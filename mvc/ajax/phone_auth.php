<?php

// Disable error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Auth.php';
require_once __DIR__ . '/../../system/Session.php';

use System\Auth;
use System\Database;
use System\Session;

// Clear any output that might have been generated
ob_clean();

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
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
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
    
    // Ensure clean output
    ob_clean();
    echo json_encode($result);
    exit;
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
    error_log("phone_auth.php - Validating code for phone: $telefone, code: $codigo, tenant: $tenantId, filial: $filialId");
    $result = Auth::validateAccessCode($telefone, $codigo, $tenantId, $filialId);
    error_log("phone_auth.php - Validation result: " . json_encode($result));
    
    if ($result['success']) {
        // Get tenant and filial data from database
        $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $filial = null;
        if ($filialId) {
            $filial = $db->fetch("SELECT * FROM filiais WHERE id = ?", [$filialId]);
        }
        
        // Set session data in the format expected by the main system
        $_SESSION['auth_token'] = $result['session_token'];
        $_SESSION['usuario_global_id'] = $result['user']['usuario_global_id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['filial_id'] = $filialId ?? null;
        $_SESSION['user_type'] = $result['establishment']['tipo_usuario'] ?? 'cliente';
        $_SESSION['permissions'] = $result['permissions'] ?? [];
        $_SESSION['user_name'] = $result['user']['nome'] ?? 'Usuário';
        
        // Set tenant and filial objects in session (required by views)
        if ($tenant) {
            $_SESSION['tenant'] = $tenant;
        }
        if ($filial) {
            $_SESSION['filial'] = $filial;
        }
        
        // Determine user level based on tipo_usuario
        $userLevel = 2; // Default user level
        if (isset($result['establishment']['tipo_usuario'])) {
            switch (strtolower($result['establishment']['tipo_usuario'])) {
                case 'admin':
                case 'administrador':
                    $userLevel = 1;
                    break;
                case 'cliente':
                    $userLevel = 3;
                    break;
                default:
                    $userLevel = 2;
            }
        }
        
        // Set session data in the format expected by Session class
        $_SESSION['user'] = [
            'id' => $result['user']['usuario_global_id'],
            'login' => $result['user']['nome'] ?? 'Usuário',
            'nivel' => $userLevel,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId ?? null
        ];
        $_SESSION['user_id'] = $result['user']['usuario_global_id'];
        $_SESSION['user_login'] = $result['user']['nome'] ?? 'Usuário';
        $_SESSION['user_nivel'] = $userLevel;
        
        // PHP automatically saves session data when script ends
        // No need to explicitly save - just ensure all data is set correctly
        
        // Add debug info to response (helpful for troubleshooting)
        $result['debug'] = [
            'user_id_set' => isset($_SESSION['user_id']),
            'user_id_value' => $_SESSION['user_id'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tenant_set' => isset($_SESSION['tenant']),
            'filial_set' => isset($_SESSION['filial']),
            'establishment_tipo' => $result['establishment']['tipo_usuario'] ?? 'not_set'
        ];
        
        error_log("phone_auth.php - Session data set: user_id=" . ($_SESSION['user_id'] ?? 'null') . ", tipo=" . ($_SESSION['user_type'] ?? 'null') . ", tenant_set=" . (isset($_SESSION['tenant']) ? 'yes' : 'no'));
    }
    
    // Ensure clean output
    ob_clean();
    echo json_encode($result);
    exit;
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
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => $sessionData,
                'user_type' => $userEstablishment['tipo_usuario'],
                'permissions' => $permissions
            ]);
            exit;
        } else {
            ob_clean();
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'message' => 'Sessão inválida'
            ]);
            exit;
        }
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Sessão expirada'
        ]);
        exit;
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    Auth::logout();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Logout realizado com sucesso'
    ]);
    exit;
}
