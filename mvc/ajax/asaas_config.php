<?php
/**
 * AJAX Handler for Asaas Configuration
 * Processes Asaas configuration requests
 */

session_start();
require_once __DIR__ . '/../model/AsaasInvoice.php';
require_once __DIR__ . '/../model/AsaasFiscalInfo.php';

try {
    $action = $_GET['action'] ?? '';
    $asaasInvoice = new AsaasInvoice();
    $asaasFiscalInfo = new AsaasFiscalInfo();
    
    switch ($action) {
        case 'saveConfig':
            saveAsaasConfig();
            break;
            
        case 'testConnection':
            testAsaasConnection();
            break;
            
        case 'getConfig':
            getAsaasConfig();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Asaas Config AJAX Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function saveAsaasConfig() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['tenant_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'tenant_id is required']);
        return;
    }
    
    $tenant_id = $data['tenant_id'];
    $filial_id = $data['filial_id'] ?? null;
    
    // Validate required fields
    $required_fields = ['asaas_api_key', 'asaas_environment'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            return;
        }
    }
    
    $conn = Database::getInstance()->getConnection();
    
    try {
        if ($filial_id) {
            // Update filial configuration
            $query = "UPDATE filiais SET 
                      asaas_api_key = $1,
                      asaas_customer_id = $2,
                      asaas_enabled = $3,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $4 AND tenant_id = $5";
            
            $result = pg_query_params($conn, $query, [
                $data['asaas_api_key'],
                $data['asaas_customer_id'] ?? null,
                $data['asaas_enabled'] ?? false,
                $filial_id,
                $tenant_id
            ]);
        } else {
            // Update tenant configuration
            $query = "UPDATE tenants SET 
                      asaas_api_key = $1,
                      asaas_customer_id = $2,
                      asaas_enabled = $3,
                      asaas_environment = $4,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = $5";
            
            $result = pg_query_params($conn, $query, [
                $data['asaas_api_key'],
                $data['asaas_customer_id'] ?? null,
                $data['asaas_enabled'] ?? false,
                $data['asaas_environment'],
                $tenant_id
            ]);
        }
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save configuration'
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Save Asaas Config Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function testAsaasConnection() {
    $tenant_id = $_GET['tenant_id'] ?? null;
    $filial_id = $_GET['filial_id'] ?? null;
    
    if (!$tenant_id) {
        http_response_code(400);
        echo json_encode(['error' => 'tenant_id is required']);
        return;
    }
    
    $asaasInvoice = new AsaasInvoice();
    $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
    
    if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Asaas integration not configured'
        ]);
        return;
    }
    
    // Test connection by trying to get fiscal info
    $asaasFiscalInfo = new AsaasFiscalInfo();
    $result = $asaasFiscalInfo->getFiscalInfo($tenant_id, $filial_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Connection successful',
            'data' => $result['data']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Connection failed: ' . $result['error']
        ]);
    }
}

function getAsaasConfig() {
    $tenant_id = $_GET['tenant_id'] ?? null;
    $filial_id = $_GET['filial_id'] ?? null;
    
    if (!$tenant_id) {
        http_response_code(400);
        echo json_encode(['error' => 'tenant_id is required']);
        return;
    }
    
    $asaasInvoice = new AsaasInvoice();
    $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
    
    if ($config) {
        // Remove sensitive data
        unset($config['asaas_api_key']);
        
        echo json_encode([
            'success' => true,
            'data' => $config
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Configuration not found'
        ]);
    }
}
