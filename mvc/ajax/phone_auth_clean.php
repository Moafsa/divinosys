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
    
    error_log("phone_auth_clean.php - Buscando usuário para telefone: $telefone");
    
    // Preparar variações do telefone para busca
    $telefoneVariacoes = [$telefone];
    
    // Se telefone começa com 55 (Brasil), adicionar variação sem o 55
    if (strlen($telefone) > 11 && substr($telefone, 0, 2) == '55') {
        $telefoneSemCodigo = substr($telefone, 2);
        $telefoneVariacoes[] = $telefoneSemCodigo;
        error_log("phone_auth_clean.php - Adicionando variação sem código: $telefoneSemCodigo");
    }
    
    // Se telefone não começa com 55, adicionar variação com 55
    if (strlen($telefone) <= 11 && !str_starts_with($telefone, '55')) {
        $telefoneComCodigo = '55' . $telefone;
        $telefoneVariacoes[] = $telefoneComCodigo;
        error_log("phone_auth_clean.php - Adicionando variação com código: $telefoneComCodigo");
    }
    
    // Buscar TODOS os estabelecimentos do usuário (pode ter múltiplos)
    $placeholders = implode(',', array_fill(0, count($telefoneVariacoes), '?'));
    $usuarioGlobal = $db->fetch(
        "SELECT ug.id, ug.nome, ug.telefone as telefone_original
         FROM usuarios_globais ug
         WHERE ug.telefone IN ($placeholders)
         LIMIT 1",
        $telefoneVariacoes
    );
    
    $estabelecimentosUsuario = [];
    if ($usuarioGlobal) {
        // Buscar TODOS os estabelecimentos ativos deste usuário
        $estabelecimentosUsuario = $db->fetchAll(
            "SELECT ue.id, ue.tenant_id, ue.filial_id, ue.tipo_usuario, 
                    t.nome as tenant_nome, f.nome as filial_nome
             FROM usuarios_estabelecimento ue
             LEFT JOIN tenants t ON ue.tenant_id = t.id
             LEFT JOIN filiais f ON ue.filial_id = f.id
             WHERE ue.usuario_global_id = ? AND ue.ativo = true
             ORDER BY ue.tenant_id, ue.filial_id",
            [$usuarioGlobal['id']]
        );
        error_log("phone_auth_clean.php - Usuário encontrado: {$usuarioGlobal['nome']} (ID: {$usuarioGlobal['id']})");
        error_log("phone_auth_clean.php - Estabelecimentos encontrados: " . count($estabelecimentosUsuario));
    } else {
        error_log("phone_auth_clean.php - Nenhum usuário encontrado para variações: " . implode(', ', $telefoneVariacoes));
    }
    
    // Se encontrou usuário com estabelecimentos, retornar lista para escolha
    if ($usuarioGlobal && count($estabelecimentosUsuario) > 0) {
        // Buscar instância ativa para enviar código (usar primeiro estabelecimento como padrão)
        $primeiroEstabelecimento = $estabelecimentosUsuario[0];
        $tenantId = $primeiroEstabelecimento['tenant_id'];
        $filialId = $primeiroEstabelecimento['filial_id'];
        
        error_log("phone_auth_clean.php - Usuário tem " . count($estabelecimentosUsuario) . " estabelecimento(s) - Usando primeiro para envio: Tenant=$tenantId, Filial=" . ($filialId ?? 'NULL'));
        
        // Gerar código usando primeiro estabelecimento (para envio)
        $result = Auth::generateAndSendAccessCode($telefone, $tenantId, $filialId);
        
        if ($result['success']) {
            // Adicionar lista de estabelecimentos na resposta
            $result['usuario'] = [
                'id' => $usuarioGlobal['id'],
                'nome' => $usuarioGlobal['nome'],
                'telefone' => $usuarioGlobal['telefone_original']
            ];
            $result['estabelecimentos'] = array_map(function($est) {
                return [
                    'id' => $est['id'],
                    'tenant_id' => $est['tenant_id'],
                    'filial_id' => $est['filial_id'],
                    'tipo_usuario' => $est['tipo_usuario'],
                    'tenant_nome' => $est['tenant_nome'] ?? 'Estabelecimento',
                    'filial_nome' => $est['filial_nome'] ?? null
                ];
            }, $estabelecimentosUsuario);
            $result['requires_selection'] = count($estabelecimentosUsuario) > 1; // Se tiver mais de um, precisa escolher
        }
        
        ob_clean();
        echo json_encode($result);
        exit;
    }
    
    // Se não encontrou usuário com estabelecimentos, tratar como cliente
    if (!$usuarioGlobal || count($estabelecimentosUsuario) == 0) {
        // Tratar como cliente - buscar qualquer instância ativa para usar como fallback
        error_log("phone_auth_clean.php - Usuário não encontrado ou sem estabelecimentos - Tratando como cliente");
        error_log("phone_auth_clean.php - Buscando instância ativa como fallback");
        
        // Buscar instância ativa (priorizar status ativo, mas aceitar qualquer se ativo=true)
        $instanciaAtiva = $db->fetch(
            "SELECT tenant_id, filial_id, id, instance_name, status 
             FROM whatsapp_instances 
             WHERE ativo = true
             ORDER BY 
                CASE WHEN status IN ('open', 'connected', 'ativo', 'active') THEN 1 ELSE 2 END,
                created_at DESC 
             LIMIT 1"
        );
        
        if ($instanciaAtiva) {
            $tenantId = $instanciaAtiva['tenant_id'];
            $filialId = $instanciaAtiva['filial_id'];
            error_log("phone_auth_clean.php - Usando instância ativa como fallback - ID: {$instanciaAtiva['id']}, Nome: {$instanciaAtiva['instance_name']}, Status: {$instanciaAtiva['status']}, Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
        } else {
            // Se não tem instância ativa, usar valores padrão
            $tenantId = 1;
            $filialId = null;
            error_log("phone_auth_clean.php - Nenhuma instância ativa encontrada, usando valores padrão - Tenant: $tenantId, Filial: NULL");
        }
        
        // Generate and send access code (como cliente)
        $result = Auth::generateAndSendAccessCode($telefone, $tenantId, $filialId);
        
        // Marcar que é acesso como cliente
        if ($result['success']) {
            $result['access_type'] = 'cliente';
            $result['requires_selection'] = false;
        }
        
        ob_clean();
        echo json_encode($result);
        exit;
    }
}

/**
 * Handle code validation
 */
function handleValidateCode() {
    $telefone = $_POST['telefone'] ?? '';
    $codigo = $_POST['codigo'] ?? '';
    $accessType = $_POST['access_type'] ?? 'usuario'; // 'usuario' ou 'cliente'
    $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
    $filialId = isset($_POST['filial_id']) ? (int)$_POST['filial_id'] : null;
    $tipoUsuario = $_POST['tipo_usuario'] ?? null;
    
    error_log("phone_auth_clean.php - handleValidateCode - AccessType: $accessType, Tenant: " . ($tenantId ?? 'NULL') . ", Filial: " . ($filialId ?? 'NULL') . ", TipoUsuario: " . ($tipoUsuario ?? 'NULL'));
    
    if (empty($telefone) || empty($codigo)) {
        throw new Exception('Telefone e código são obrigatórios');
    }
    
    // Clean phone number
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    $db = Database::getInstance();
    
    // Se acesso como cliente, buscar qualquer instância ativa
    if ($accessType === 'cliente') {
        error_log("phone_auth_clean.php - Validando código como CLIENTE - Telefone: $telefone");
        
        // Buscar instância ativa para cliente
        $instanciaAtiva = $db->fetch(
            "SELECT tenant_id, filial_id 
             FROM whatsapp_instances 
             WHERE ativo = true
             ORDER BY 
                CASE WHEN status IN ('open', 'connected', 'ativo', 'active') THEN 1 ELSE 2 END,
                created_at DESC 
             LIMIT 1"
        );
        
        if ($instanciaAtiva) {
            $tenantId = $instanciaAtiva['tenant_id'];
            $filialId = $instanciaAtiva['filial_id'];
        } else {
            $tenantId = 1;
            $filialId = null;
        }
        
        error_log("phone_auth_clean.php - Cliente usando Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
    } else {
        // Acesso como usuário - usar tenant/filial escolhidos
        if (!$tenantId) {
            error_log("phone_auth_clean.php - Tenant não fornecido, buscando do usuário");
            // Se não foi escolhido, buscar do usuário
            $usuario = $db->fetch(
                "SELECT ue.tenant_id, ue.filial_id 
                 FROM usuarios_globais ug
                 JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
                 WHERE ug.telefone = ? AND ue.ativo = true
                 ORDER BY ue.filial_id ASC LIMIT 1",
                [$telefone]
            );
            
            if ($usuario) {
                $tenantId = $usuario['tenant_id'];
                $filialId = $usuario['filial_id'];
            } else {
                // Fallback: buscar qualquer instância ativa
                $instanciaAtiva = $db->fetch(
                    "SELECT tenant_id, filial_id 
                     FROM whatsapp_instances 
                     WHERE ativo = true
                     ORDER BY created_at DESC LIMIT 1"
                );
                $tenantId = $instanciaAtiva['tenant_id'] ?? 1;
                $filialId = $instanciaAtiva['filial_id'] ?? null;
            }
        }
        
        error_log("phone_auth_clean.php - Validando código como USUÁRIO - Telefone: $telefone, Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL') . ", Tipo: " . ($tipoUsuario ?? 'N/A'));
        
        // Verificar se o usuário realmente tem acesso a este estabelecimento
        if ($tenantId) {
            $usuarioGlobal = $db->fetch(
                "SELECT id FROM usuarios_globais WHERE telefone = ? LIMIT 1",
                [$telefone]
            );
            
            if ($usuarioGlobal) {
                // Construir query e parâmetros corretamente
                if ($filialId) {
                    $verificacao = $db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true
                         LIMIT 1",
                        [$usuarioGlobal['id'], $tenantId, $filialId]
                    );
                } else {
                    $verificacao = $db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND ativo = true
                         LIMIT 1",
                        [$usuarioGlobal['id'], $tenantId]
                    );
                }
                
                if (!$verificacao) {
                    error_log("phone_auth_clean.php - AVISO: Usuário não tem vínculo com Tenant=$tenantId, Filial=" . ($filialId ?? 'NULL'));
                    // Listar todos os vínculos do usuário
                    $todosVinculos = $db->fetchAll(
                        "SELECT * FROM usuarios_estabelecimento WHERE usuario_global_id = ? AND ativo = true",
                        [$usuarioGlobal['id']]
                    );
                    error_log("phone_auth_clean.php - Vínculos disponíveis: " . json_encode($todosVinculos));
                } else {
                    error_log("phone_auth_clean.php - Usuário tem vínculo confirmado: Tipo=" . $verificacao['tipo_usuario']);
                }
            }
        }
    }
    
    // Validate access code
    $result = Auth::validateAccessCode($telefone, $codigo, $tenantId, $filialId, $accessType, $tipoUsuario);
    
    if ($result['success']) {
        // Garantir que tipo_usuario está na resposta
        if (!isset($result['tipo_usuario']) && isset($result['establishment']['tipo_usuario'])) {
            $result['tipo_usuario'] = $result['establishment']['tipo_usuario'];
        }
        
        error_log("phone_auth_clean.php - Validação bem-sucedida - Tipo: " . ($result['tipo_usuario'] ?? 'N/A'));
        
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
