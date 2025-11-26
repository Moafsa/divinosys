<?php
/**
 * AJAX Handler for Fiscal Information
 * Processes fiscal information-related requests
 */

// Start output buffering FIRST
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Shutdown function para capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ERRO FATAL FISCAL_INFO: " . json_encode($error));
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro fatal: ' . $error['message'] . ' em ' . basename($error['file']) . ':' . $error['line']
            ]);
        }
    }
});

// Session e headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Carregar arquivos (mesmo padrão do configuracoes.php)
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../model/AsaasFiscalInfo.php';
require_once __DIR__ . '/../controller/FiscalInfoController.php';

// Limpar buffer
ob_clean();

try {
    $action = $_GET['action'] ?? '';
    
    if (empty($action)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action parameter is required']);
        exit;
    }
    
    try {
        $controller = new FiscalInfoController();
    } catch (\Exception $e) {
        error_log('Error creating FiscalInfoController: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error initializing Fiscal Info Controller: ' . $e->getMessage()
        ]);
        exit;
    } catch (\Error $e) {
        error_log('Fatal error creating FiscalInfoController: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error initializing Fiscal Info Controller: ' . $e->getMessage()
        ]);
        exit;
    }
    
    switch ($action) {
        case 'createOrUpdateFiscalInfo':
            $controller->createOrUpdateFiscalInfo();
            break;
            
        case 'getFiscalInfo':
            $controller->getFiscalInfo();
            break;
            
        case 'listMunicipalOptions':
            $controller->listMunicipalOptions();
            break;
            
        case 'listMunicipalServices':
            $controller->listMunicipalServices();
            break;
            
        case 'listNBSCodes':
            $controller->listNBSCodes();
            break;
            
        case 'configureIssuerPortal':
            $controller->configureIssuerPortal();
            break;
            
        case 'validateCNPJ':
            $controller->validateCNPJ();
            break;
            
        case 'getFiscalStats':
            $controller->getFiscalStats();
            break;
            
        case 'deactivateFiscalInfo':
            $controller->deactivateFiscalInfo();
            break;
            
        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
            exit;
    }
    
} catch (\Exception $e) {
    error_log('Fiscal Info AJAX Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit;
} catch (\Error $e) {
    error_log('Fiscal Info AJAX Fatal Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
    exit;
}
