<?php
/**
 * AJAX Handler for Asaas Configuration
 * Processes Asaas configuration requests
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
        error_log("ERRO FATAL ASAAS_CONFIG: " . json_encode($error));
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
require_once __DIR__ . '/../model/AsaasInvoice.php';
require_once __DIR__ . '/../model/AsaasFiscalInfo.php';

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
            
        case 'createOrFindCustomer':
            createOrFindCustomer();
            break;
            
        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
            exit;
    }
    
} catch (\Exception $e) {
    error_log('Asaas Config AJAX Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit;
} catch (\Error $e) {
    error_log('Asaas Config AJAX Fatal Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
    exit;
}

function saveAsaasConfig() {
    ob_clean();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    if (!isset($data['tenant_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tenant_id is required']);
        exit;
    }
    
    $tenant_id = $data['tenant_id'];
    $filial_id = $data['filial_id'] ?? null;
    
    // Validate environment
    if (!isset($data['asaas_environment']) || empty($data['asaas_environment'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field 'asaas_environment' is required"]);
        exit;
    }
    
    // If API key is empty or contains only masked characters (•), get existing key from database
    $apiKeyValue = trim($data['asaas_api_key'] ?? '');
    $isMaskedKey = !empty($apiKeyValue) && preg_match('/^[a-zA-Z0-9]{4}•+[a-zA-Z0-9]{4}$/', $apiKeyValue);
    
    if (empty($apiKeyValue) || $isMaskedKey) {
        // User didn't enter a new key, keep existing one
        try {
            $asaasInvoice = new AsaasInvoice();
            $existingConfig = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
            if (!empty($existingConfig['asaas_api_key'])) {
                $data['asaas_api_key'] = $existingConfig['asaas_api_key'];
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Chave API é obrigatória"]);
                exit;
            }
        } catch (\Exception $e) {
            error_log('Error getting existing config: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Chave API é obrigatória"]);
            exit;
        }
    }
    // Otherwise, use the new key provided by the user
    
    try {
        $db = \System\Database::getInstance();
        $conn = $db->getConnection();
    } catch (\Exception $e) {
        error_log('Error getting database connection in saveAsaasConfig: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
    
    try {
        if ($filial_id) {
            // Update filial configuration
            $query = "UPDATE filiais SET 
                      asaas_api_key = ?,
                      asaas_customer_id = ?,
                      asaas_enabled = ?,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = ? AND tenant_id = ?";
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $data['asaas_api_key'],
                $data['asaas_customer_id'] ?? null,
                $data['asaas_enabled'] ?? false,
                $filial_id,
                $tenant_id
            ]);
        } else {
            // Update tenant configuration
            $query = "UPDATE tenants SET 
                      asaas_api_key = ?,
                      asaas_customer_id = ?,
                      asaas_enabled = ?,
                      asaas_environment = ?,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $data['asaas_api_key'],
                $data['asaas_customer_id'] ?? null,
                $data['asaas_enabled'] ?? false,
                $data['asaas_environment'],
                $tenant_id
            ]);
        }
        
        if ($result) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved successfully'
            ]);
            exit;
        } else {
            error_log('Database update failed');
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save configuration: ' . $error
            ]);
            exit;
        }
        
    } catch (\Exception $e) {
        error_log('Save Asaas Config Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

function testAsaasConnection() {
    ob_clean();
    
    $tenant_id = $_GET['tenant_id'] ?? null;
    $filial_id = $_GET['filial_id'] ?? null;
    
    if (!$tenant_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tenant_id is required']);
        exit;
    }
    
    try {
        $asaasInvoice = new AsaasInvoice();
        $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
    } catch (\Exception $e) {
        error_log('Error creating AsaasInvoice in testAsaasConnection: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error initializing Asaas: ' . $e->getMessage()]);
        exit;
    }
    
    if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Asaas integration not configured'
        ]);
        exit;
    }
    
    // Test connection by making a simple API call to Asaas
    // Use the same pattern as AsaasPayment::testConnection()
    $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
    $api_key = $config['asaas_api_key'];
    
    // Use customers endpoint with limit=1 (same as AsaasPayment)
    $testUrl = $api_url . '/customers?limit=1';
    
    $headers = [
        'access_token: ' . $api_key,
        'Content-Type: application/json',
        'User-Agent: DivinoSYS/2.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Erro de conexão: ' . $error
        ]);
        exit;
    }
    
    $decoded_response = json_decode($response, true);
    
    if ($http_code >= 200 && $http_code < 300) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Conexão bem-sucedida! A chave API está válida.',
            'data' => $decoded_response
        ]);
        exit;
    } else {
        $errorMsg = 'Erro desconhecido';
        
        // Try to extract error message from Asaas response
        if (isset($decoded_response['errors']) && is_array($decoded_response['errors']) && count($decoded_response['errors']) > 0) {
            $errorMsg = $decoded_response['errors'][0]['description'] ?? $decoded_response['errors'][0]['code'] ?? 'Erro desconhecido';
        } elseif (isset($decoded_response['error'])) {
            $errorMsg = $decoded_response['error'];
        } elseif (isset($decoded_response['message'])) {
            $errorMsg = $decoded_response['message'];
        }
        
        // Log full response for debugging
        error_log('Asaas API Error Response (HTTP ' . $http_code . '): ' . json_encode($decoded_response));
        
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => $errorMsg . ' (HTTP ' . $http_code . ')'
        ]);
        exit;
    }
}

function getAsaasConfig() {
    ob_clean();
    
    $tenant_id = $_GET['tenant_id'] ?? null;
    $filial_id = $_GET['filial_id'] ?? null;
    
    if (!$tenant_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tenant_id is required']);
        exit;
    }
    
    // Verificar se Database está disponível antes de instanciar
    if (!class_exists('System\\Database')) {
        error_log('System\\Database não encontrado em getAsaasConfig');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database class not available']);
        exit;
    }
    
    // Verificar se AsaasInvoice está disponível
    if (!class_exists('AsaasInvoice')) {
        error_log('AsaasInvoice não encontrado em getAsaasConfig');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'AsaasInvoice class not available']);
        exit;
    }
    
    try {
        $asaasInvoice = new AsaasInvoice();
    } catch (\Exception $e) {
        error_log('Error creating AsaasInvoice in getAsaasConfig: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error creating AsaasInvoice: ' . $e->getMessage()]);
        exit;
    } catch (\Error $e) {
        error_log('Fatal error creating AsaasInvoice in getAsaasConfig: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal error creating AsaasInvoice: ' . $e->getMessage()]);
        exit;
    }
    
    try {
        $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
    } catch (\Exception $e) {
        error_log('Error calling getAsaasConfig: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error getting config: ' . $e->getMessage()]);
        exit;
    }
    
    // Mask API key for display (show first 4 and last 4 chars)
    $hasApiKey = !empty($config['asaas_api_key']);
    if (isset($config['asaas_api_key']) && $hasApiKey) {
        $apiKey = $config['asaas_api_key'];
        $keyLength = strlen($apiKey);
        if ($keyLength > 8) {
            // Show first 4 and last 4 characters, mask the rest
            $maskedKey = substr($apiKey, 0, 4) . str_repeat('•', $keyLength - 8) . substr($apiKey, -4);
        } else {
            // If key is too short, just mask it all
            $maskedKey = str_repeat('•', $keyLength);
        }
        $config['asaas_api_key_masked'] = $maskedKey;
        unset($config['asaas_api_key']); // Remove actual key
    }
    $config['has_api_key'] = $hasApiKey;
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $config
    ]);
    exit;
}

/**
 * Criar ou buscar cliente no Asaas automaticamente
 * Similar ao que é feito no onboarding de planos
 */
function createOrFindCustomer() {
    ob_clean();
    
    $tenant_id = $_GET['tenant_id'] ?? null;
    $filial_id = $_GET['filial_id'] ?? null;
    
    if (!$tenant_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tenant_id is required']);
        exit;
    }
    
    try {
        $db = \System\Database::getInstance();
        $conn = $db->getConnection();
    } catch (\Exception $e) {
        error_log('Error getting database connection in createOrFindCustomer: ' . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
    
    // Buscar dados do tenant/filial
    if ($filial_id) {
        $query = "SELECT f.*, t.nome as tenant_nome, t.email as tenant_email, t.cnpj as tenant_cnpj, t.telefone as tenant_telefone
                  FROM filiais f
                  JOIN tenants t ON f.tenant_id = t.id
                  WHERE f.id = ? AND f.tenant_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$filial_id, $tenant_id]);
        $entity = $stmt->fetch(\PDO::FETCH_ASSOC);
        $entityName = $entity['nome'] ?? $entity['tenant_nome'];
        $entityEmail = $entity['email'] ?? $entity['tenant_email'];
        $entityCnpj = $entity['cnpj'] ?? $entity['tenant_cnpj'];
        $entityPhone = $entity['telefone'] ?? $entity['tenant_telefone'];
    } else {
        $query = "SELECT * FROM tenants WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$tenant_id]);
        $entity = $stmt->fetch(\PDO::FETCH_ASSOC);
        $entityName = $entity['nome'] ?? '';
        $entityEmail = $entity['email'] ?? '';
        $entityCnpj = $entity['cnpj'] ?? '';
        $entityPhone = $entity['telefone'] ?? '';
    }
    
    if (!$entity) {
        ob_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Estabelecimento não encontrado']);
        exit;
    }
    
    // Buscar configuração do Asaas
    try {
        $asaasInvoice = new AsaasInvoice();
        $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
    } catch (\Exception $e) {
        error_log('Error creating AsaasInvoice in createOrFindCustomer: ' . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error initializing Asaas: ' . $e->getMessage()]);
        exit;
    }
    
    if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Asaas não está configurado. Configure a API Key primeiro.']);
        exit;
    }
    
    $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
    $api_key = $config['asaas_api_key'];
    
    // Primeiro, tentar buscar cliente existente pelo CNPJ ou email
    $customerId = null;
    
    if (!empty($entityCnpj)) {
        // Buscar por CNPJ
        $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
        $searchUrl = $api_url . '/customers?cpfCnpj=' . urlencode($cnpjClean);
        
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'access_token: ' . $api_key,
            'Content-Type: application/json'
        ]);
        
        $searchResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $searchResult = json_decode($searchResponse, true);
            if (isset($searchResult['data']) && count($searchResult['data']) > 0) {
                $customerId = $searchResult['data'][0]['id'];
            }
        }
    }
    
    // Se não encontrou, criar novo cliente
    if (!$customerId) {
        $customerData = [
            'name' => $entityName,
            'email' => $entityEmail,
            'phone' => preg_replace('/[^0-9]/', '', $entityPhone),
            'externalReference' => ($filial_id ? 'filial_' : 'tenant_') . ($filial_id ?? $tenant_id)
        ];
        
        if (!empty($entityCnpj)) {
            $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $entityCnpj);
        }
        
        $ch = curl_init($api_url . '/customers');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'access_token: ' . $api_key,
            'Content-Type: application/json'
        ]);
        
        $createResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $createResult = json_decode($createResponse, true);
            if (isset($createResult['id'])) {
                $customerId = $createResult['id'];
            }
        } else {
            $errorResult = json_decode($createResponse, true);
            $errorMsg = $errorResult['errors'][0]['description'] ?? 'Erro ao criar cliente no Asaas';
            
            // Se o erro for que o cliente já existe (duplicado), tentar buscar novamente
            if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false) {
                // Buscar novamente
                if (!empty($entityCnpj)) {
                    $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
                    $searchUrl = $api_url . '/customers?cpfCnpj=' . urlencode($cnpjClean);
                    
                    $ch = curl_init($searchUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'access_token: ' . $api_key,
                        'Content-Type: application/json'
                    ]);
                    
                    $searchResponse = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $searchResult = json_decode($searchResponse, true);
                        if (isset($searchResult['data']) && count($searchResult['data']) > 0) {
                            $customerId = $searchResult['data'][0]['id'];
                        }
                    }
                }
            }
            
            if (!$customerId) {
                ob_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $errorMsg
                ]);
                exit;
            }
        }
    }
    
    // Salvar o customer_id no banco
    if ($filial_id) {
        $updateQuery = "UPDATE filiais SET asaas_customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateResult = $updateStmt->execute([$customerId, $filial_id, $tenant_id]);
    } else {
        $updateQuery = "UPDATE tenants SET asaas_customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateResult = $updateStmt->execute([$customerId, $tenant_id]);
    }
    
    if ($updateResult) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'customer_id' => $customerId,
            'message' => $customerId ? 'Cliente encontrado/criado com sucesso!' : 'Cliente criado com sucesso!'
        ]);
        exit;
    } else {
        $error = pg_last_error($conn);
        error_log('PostgreSQL Error ao salvar customer_id: ' . $error);
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao salvar ID do cliente no banco de dados: ' . $error
        ]);
        exit;
    }
}
