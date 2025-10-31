<?php
/**
 * AJAX Handler for Fiscal Information
 * Processes fiscal information-related requests
 */

session_start();
require_once __DIR__ . '/../controller/FiscalInfoController.php';

try {
    $action = $_GET['action'] ?? '';
    $controller = new FiscalInfoController();
    
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
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Fiscal Info AJAX Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
