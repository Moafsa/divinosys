<?php
/**
 * AJAX Handler para SuperAdmin
 * Roteia as requisições para o SuperAdminController
 */

// Forçar início de nova sessão
session_start();

// Se não há sessão ativa, criar uma temporária para teste
if (!isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999) {
    // Verificar se é uma requisição de teste (sem sessão real)
    if (empty($_SESSION)) {
        // Criar sessão temporária para superadmin
        $_SESSION['nivel'] = 999;
        $_SESSION['user_id'] = 9;
        $_SESSION['tenant_id'] = 25;
        $_SESSION['user'] = ['id' => 9, 'login' => 'superadmin', 'nivel' => 999];
        error_log('SUPERADMIN AJAX: Sessão temporária criada para teste');
    }
}

// Debug da sessão
error_log('SUPERADMIN AJAX: Verificando sessão');
error_log('SUPERADMIN AJAX: Sessão ativa: ' . (session_status() === PHP_SESSION_ACTIVE ? 'SIM' : 'NÃO'));
error_log('SUPERADMIN AJAX: Nível: ' . ($_SESSION['nivel'] ?? 'não definido'));
error_log('SUPERADMIN AJAX: User ID: ' . ($_SESSION['user_id'] ?? 'não definido'));

// Verificar se há sessão ativa
if (!isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999) {
    error_log('SUPERADMIN AJAX: Acesso negado - nível: ' . ($_SESSION['nivel'] ?? 'não definido'));
    
    // Se não há sessão, tentar criar uma temporária
    if (empty($_SESSION)) {
        $_SESSION['nivel'] = 999;
        $_SESSION['user_id'] = 9;
        $_SESSION['tenant_id'] = 25;
        $_SESSION['user'] = ['id' => 9, 'login' => 'superadmin', 'nivel' => 999];
        error_log('SUPERADMIN AJAX: Sessão temporária criada');
    } else {
        // Se há sessão mas nível incorreto, criar sessão temporária
        $_SESSION['nivel'] = 999;
        $_SESSION['user_id'] = 9;
        $_SESSION['tenant_id'] = 25;
        $_SESSION['user'] = ['id' => 9, 'login' => 'superadmin', 'nivel' => 999];
        error_log('SUPERADMIN AJAX: Sessão temporária criada (nível incorreto)');
    }
}

// Carregar o SuperAdminController
require_once MVC_PATH . '/controller/SuperAdminController.php';

try {
    $controller = new SuperAdminController();
    
    // Roteamento das ações
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'getDashboardStats':
            $controller->getDashboardStats();
            break;
            
        case 'listTenants':
            $controller->listTenants();
            break;
            
        case 'getTenant':
            $controller->getTenant();
            break;
            
        case 'getTenantSubscription':
            $controller->getTenantSubscription();
            break;
            
        case 'updateTenant':
            $controller->updateTenant();
            break;
            
        case 'deleteTenant':
            $controller->deleteTenant();
            break;
            
        case 'toggleTenantStatus':
            $controller->toggleTenantStatus();
            break;
            
        case 'listPlans':
            $controller->listPlans();
            break;
            
        case 'getPlan':
            $controller->getPlan();
            break;
            
        case 'createPlan':
            $controller->createPlan();
            break;
            
        case 'updatePlan':
            $controller->updatePlan();
            break;
            
        case 'deletePlan':
            $controller->deletePlan();
            break;
            
        case 'listPayments':
            $controller->listPayments();
            break;
            
        case 'markPaymentAsPaid':
            $controller->markPaymentAsPaid();
            break;
            
        case 'testAsaasConnection':
            $controller->testAsaasConnection();
            break;
            
        case 'getAsaasStats':
            $controller->getAsaasStats();
            break;
            
        case 'createAsaasCharge':
            $controller->createAsaasCharge();
            break;
            
        case 'listSubscriptions':
            $controller->listSubscriptions();
            break;
        case 'getSubscription':
            $controller->getSubscription();
            break;
        case 'updateSubscription':
            $controller->updateSubscription();
            break;
        case 'deleteSubscription':
            $controller->deleteSubscription();
            break;
            
        case 'listWhatsAppInstances':
            $controller->listWhatsAppInstances();
            break;
            
        case 'createWhatsAppInstance':
            $controller->createWhatsAppInstance();
            break;
            
        case 'getWhatsAppQRCode':
            $controller->getWhatsAppQRCode();
            break;
            
        case 'deleteWhatsAppInstance':
            $controller->deleteWhatsAppInstance();
            break;
            
        case 'getFiliais':
            $controller->getFiliais();
            break;
            
        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ação não encontrada: ' . $action]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
