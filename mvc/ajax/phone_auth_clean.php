<?php

// Disable all error reporting and output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Start output buffering
ob_start();

// Suppress all output during includes
ob_start();
require_once __DIR__ . '/../../system/Config.php';
ob_end_clean();

ob_start();
require_once __DIR__ . '/../../system/Database.php';
ob_end_clean();

ob_start();
require_once __DIR__ . '/../../system/Auth.php';
ob_end_clean();

ob_start();
require_once __DIR__ . '/../../system/Session.php';
ob_end_clean();

use System\Auth;
use System\Database;
use System\Session;

// Clear any output
ob_clean();

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_clean();
    echo json_encode(['status' => 'ok']);
    exit;
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
    
    // Buscar estabelecimento do usuário pelo telefone
    $db = Database::getInstance();
    $usuario = $db->fetch(
        "SELECT ue.tenant_id, ue.filial_id 
         FROM usuarios_globais ug
         JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
         WHERE ug.telefone = ? AND ue.ativo = true
         ORDER BY ue.filial_id ASC LIMIT 1",
        [$telefone]
    );
    
    $tenantId = $usuario['tenant_id'] ?? 1;
    $filialId = $usuario['filial_id'] ?? 1;
    
    error_log("phone_auth_clean.php - Telefone: $telefone, Tenant: $tenantId, Filial: $filialId");
    
    // Generate and send access code
    $result = Auth::generateAndSendAccessCode($telefone, $tenantId, $filialId);
    
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
    
    // Buscar tenant/filial do usuário pelo telefone
    $db = Database::getInstance();
    $usuario = $db->fetch(
        "SELECT ue.tenant_id, ue.filial_id 
         FROM usuarios_globais ug
         JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
         WHERE ug.telefone = ? AND ue.ativo = true
         ORDER BY ue.filial_id ASC LIMIT 1",
        [$telefone]
    );
    
    $tenantId = $usuario['tenant_id'] ?? 1;
    $filialId = $usuario['filial_id'] ?? null;
    
    error_log("phone_auth_clean.php - Validando código - Telefone: $telefone, Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
    
    // Validate access code
    $result = Auth::validateAccessCode($telefone, $codigo, $tenantId, $filialId);
    
    if ($result['success']) {
        // Get tenant and filial data from database
        $db = Database::getInstance();
        $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $filial = null;
        if ($filialId) {
            $filial = $db->fetch("SELECT * FROM filiais WHERE id = ?", [$filialId]);
        }
        
        error_log("========== PHONE AUTH - VALIDATE CODE ==========");
        error_log("Tenant ID: $tenantId, Filial ID: " . ($filialId ?? 'NULL'));
        error_log("Result establishment: " . json_encode($result['establishment'] ?? []));
        error_log("Result user: " . json_encode($result['user'] ?? []));
        
        // Set session data in the format expected by the main system
        $_SESSION['auth_token'] = $result['session_token'];
        $_SESSION['usuario_global_id'] = $result['user']['usuario_global_id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['filial_id'] = $filialId ?? null;
        $userType = $result['establishment']['tipo_usuario'] ?? 'cliente';
        $_SESSION['user_type'] = $userType;
        $_SESSION['permissions'] = $result['permissions'] ?? [];
        $_SESSION['user_name'] = $result['user']['nome'] ?? 'Usuário';
        
        error_log("User type set to: $userType");
        error_log("Permissions: " . json_encode($_SESSION['permissions'] ?? []));
        
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
        
        // Log session data for debug
        error_log("phone_auth_clean.php - Session data set: user_id=" . ($_SESSION['user_id'] ?? 'null') . ", tipo=" . ($_SESSION['user_type'] ?? 'null') . ", tenant_set=" . (isset($_SESSION['tenant']) ? 'yes' : 'no'));
        
        // Force session write
        session_write_close();
        session_start();
        
        // Verify session was saved
        error_log("phone_auth_clean.php - After session restart: user_id=" . ($_SESSION['user_id'] ?? 'null'));
    }
    
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
        $db = Database::getInstance();
        $userEstablishment = $db->fetch(
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
