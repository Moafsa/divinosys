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
require_once __DIR__ . '/../model/AsaasAPIClient.php';

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
            
        case 'getConfigForTest':
            getAsaasConfigForTest();
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
    
    // Check if API key is provided and if it's a new key or masked
    $apiKeyValue = trim($data['asaas_api_key'] ?? '');
    
    // Detect if key is masked (contains bullet points) - this means user didn't enter a new key
    $isMaskedKey = !empty($apiKeyValue) && (
        strpos($apiKeyValue, '•') !== false || 
        strpos($apiKeyValue, 'chave salva') !== false ||
        preg_match('/^[a-zA-Z0-9]{4}[•\s]+[a-zA-Z0-9]{4}$/', $apiKeyValue)
    );
    
    // If key is empty or masked, get existing key from database
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
    // Otherwise, use the new key provided by the user (it's a real new key)
    
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
        // Always update API URL based on environment
        $apiUrl = ($data['asaas_environment'] === 'production') 
            ? 'https://www.asaas.com/api/v3' 
            : 'https://sandbox.asaas.com/api/v3';
        
        // Convert asaas_enabled to boolean explicitly for PostgreSQL
        // Convert to integer (0 or 1) which PostgreSQL accepts as boolean
        $asaasEnabledRaw = $data['asaas_enabled'] ?? false;
        if ($asaasEnabledRaw === true || $asaasEnabledRaw === 'true' || $asaasEnabledRaw === 1 || $asaasEnabledRaw === '1') {
            $asaasEnabled = 1;
        } else {
            $asaasEnabled = 0;
        }
        
        if ($filial_id) {
            // Update filial configuration
            // Also update tenant's environment and API URL (always, not just if provided)
            $tenantUpdateQuery = "UPDATE tenants SET 
                                 asaas_environment = ?,
                                 asaas_api_url = ?,
                                 updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?";
            $tenantStmt = $conn->prepare($tenantUpdateQuery);
            $tenantStmt->execute([
                $data['asaas_environment'],
                $apiUrl,
                $tenant_id
            ]);
            
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
                $asaasEnabled,
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
                      asaas_api_url = ?,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $data['asaas_api_key'],
                $data['asaas_customer_id'] ?? null,
                $asaasEnabled,
                $data['asaas_environment'],
                $apiUrl,
                $tenant_id
            ]);
        }
        
        if ($result) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved successfully',
                'environment' => $data['asaas_environment'] ?? null
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
    ob_clean(); // Clear any previous output
    
    try {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        
        error_log("testAsaasConnection START - tenant_id: $tenant_id, filial_id: $filial_id");
        
        if (!$tenant_id) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'tenant_id is required']);
            exit;
        }
        
        // Check if AsaasAPIClient class exists
        if (!class_exists('AsaasAPIClient')) {
            error_log("testAsaasConnection ERROR: AsaasAPIClient class not found");
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Classe AsaasAPIClient não encontrada. Verifique se o arquivo foi incluído corretamente.'
            ]);
            exit;
        }
        
        $asaasInvoice = new AsaasInvoice();
        $config = $asaasInvoice->getAsaasConfig($tenant_id, $filial_id);
        
        error_log("testAsaasConnection - Config loaded - enabled: " . ($config['asaas_enabled'] ?? 'not set') . ", has_key: " . (!empty($config['asaas_api_key']) ? 'yes' : 'no'));
        
        if (!$config || empty($config['asaas_api_key'])) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Integração Asaas não configurada ou chave API ausente'
            ]);
            exit;
        }
        
        // Check if enabled
        $isEnabled = ($config['asaas_enabled'] == 1 || $config['asaas_enabled'] === true || $config['asaas_enabled'] === '1');
        if (!$isEnabled) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Integração Asaas está desabilitada. Ative primeiro.'
            ]);
            exit;
        }
        
        // Get API URL and key
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        // Use centralized AsaasAPIClient to test connection
        // Uses proper timeouts to prevent system hangs
        try {
            error_log("testAsaasConnection - Creating AsaasAPIClient with URL: $api_url, Key length: " . strlen($api_key));
            $apiClient = new AsaasAPIClient($api_key, $api_url, 10, 5);
            
            error_log("testAsaasConnection - Calling testConnection()");
            $result = $apiClient->testConnection();
            
            error_log("testAsaasConnection - Result received: " . json_encode($result));
            
            ob_clean(); // Clear buffer before outputting JSON
            
            if ($result['success']) {
                $totalCount = $result['data']['totalCount'] ?? 0;
                
                error_log("testAsaasConnection - Connection successful. HTTP {$result['http_code']}. Total customers: $totalCount");
                
                $response = [
                    'success' => true,
                    'message' => 'Conexão com Asaas bem-sucedida! A API está respondendo corretamente.',
                    'details' => "Encontrados $totalCount cliente(s) cadastrado(s).",
                    'statusCode' => $result['http_code'],
                    'config' => [
                        'api_url' => $api_url,
                        'key_length' => strlen($api_key),
                        'key_prefix' => substr($api_key, 0, 10) . '...'
                    ]
                ];
                
                error_log("testAsaasConnection - Sending success response");
                echo json_encode($response);
                exit;
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                $httpCode = $result['http_code'] ?? 0;
                error_log("testAsaasConnection - API Error: HTTP $httpCode - $errorMsg");
                
                $response = [
                    'success' => false,
                    'error' => $errorMsg . ($httpCode > 0 ? " (HTTP $httpCode)" : '')
                ];
                
                error_log("testAsaasConnection - Sending error response");
                echo json_encode($response);
                exit;
            }
        } catch (\Exception $e) {
            error_log("testAsaasConnection - Exception: " . $e->getMessage());
            error_log("testAsaasConnection - Stack trace: " . $e->getTraceAsString());
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao testar conexão: ' . $e->getMessage()
            ]);
            exit;
        }
    } catch (\Exception $e) {
        error_log('testAsaasConnection Exception: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Test failed: ' . $e->getMessage()
        ]);
        exit;
    } catch (\Error $e) {
        error_log('testAsaasConnection Fatal Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage()
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
 * Get Asaas config for connection test (returns API key unmasked for browser to test)
 */
function getAsaasConfigForTest() {
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
        
        if (!$config) {
            echo json_encode([
                'success' => false,
                'error' => 'Configuração Asaas não encontrada'
            ]);
            exit;
        }
        
        // Return config with API key (unmasked) for browser to test
        echo json_encode([
            'success' => true,
            'data' => [
                'api_url' => $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3',
                'api_key' => $config['asaas_api_key'] ?? '',
                'is_enabled' => ($config['asaas_enabled'] == 1 || $config['asaas_enabled'] === true || $config['asaas_enabled'] === '1')
            ]
        ]);
        exit;
        
    } catch (\Exception $e) {
        error_log('Error in getAsaasConfigForTest: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao obter configuração: ' . $e->getMessage()
        ]);
        exit;
    }
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
    
    // Use centralized AsaasAPIClient
    try {
        $apiClient = new AsaasAPIClient($api_key, $api_url, 15, 5);
    } catch (\Exception $e) {
        error_log('Error creating AsaasAPIClient in createOrFindCustomer: ' . $e->getMessage());
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao inicializar cliente API: ' . $e->getMessage()]);
        exit;
    }
    
    // Primeiro, tentar buscar cliente existente pelo CNPJ
    $customerId = null;
    
    if (!empty($entityCnpj)) {
        // Buscar por CNPJ
        $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
        $searchResult = $apiClient->getCustomers(['cpfCnpj' => $cnpjClean]);
        
        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
            $customerId = $searchResult['data']['data'][0]['id'];
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
        
        $createResult = $apiClient->createCustomer($customerData);
        
        if ($createResult['success'] && isset($createResult['data']['id'])) {
            $customerId = $createResult['data']['id'];
        } else {
            $errorMsg = $createResult['error'] ?? 'Erro ao criar cliente no Asaas';
            
            // Se o erro for que o cliente já existe (duplicado), tentar buscar novamente
            if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false) {
                // Buscar novamente
                if (!empty($entityCnpj)) {
                    $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
                    $searchResult = $apiClient->getCustomers(['cpfCnpj' => $cnpjClean]);
                    
                    if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                        $customerId = $searchResult['data']['data'][0]['id'];
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
