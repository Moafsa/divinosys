<?php
// Disable error display in output (to prevent JSON corruption)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log errors, but don't display

// Start output buffering to capture any unexpected output
ob_start();

session_start();
header('Content-Type: application/json');

// Autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => __DIR__ . '/../../system/',
        'App\\' => __DIR__ . '/../../app/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/OpenAIService.php';
require_once __DIR__ . '/../../system/N8nAIService.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    error_log("ai_chat.php - Action: $action");
    
    // Determine which AI service to use based on environment
    $config = \System\Config::getInstance();
    $useN8n = $config->getEnv('USE_N8N_AI') === 'true';
    
    error_log("ai_chat.php - USE_N8N_AI: " . ($useN8n ? 'true' : 'false'));
    
    switch ($action) {
        case 'send_message':
            $message = $_POST['message'] ?? '';
            $attachments = $_POST['attachments'] ?? [];
            $historyJson = $_POST['history'] ?? '[]';
            
            // Parse chat history
            $chatHistory = json_decode($historyJson, true);
            if (!is_array($chatHistory)) {
                $chatHistory = [];
            }
            
            error_log("ai_chat.php - Message: $message");
            error_log("ai_chat.php - History count: " . count($chatHistory));
            
            if (empty($message)) {
                throw new Exception('Mensagem é obrigatória');
            }
            
            // Get session
            $session = \System\Session::getInstance();
            $db = \System\Database::getInstance();
            
            // Get or set tenant/filial context
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            // If no tenant in session, try to get from user
            if (!$tenantId && $session->isLoggedIn()) {
                $user = $session->getUser();
                if ($user && isset($user['tenant_id'])) {
                    $tenantId = $user['tenant_id'];
                    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
                    if ($tenant) {
                        $session->setTenant($tenant);
                    }
                }
            }
            
            // If still no tenant, use first active tenant as fallback
            if (!$tenantId) {
                $tenant = $db->fetch("SELECT * FROM tenants WHERE status = 'ativo' LIMIT 1");
                if ($tenant) {
                    $tenantId = $tenant['id'];
                    $session->setTenant($tenant);
                    error_log("ai_chat.php - Using fallback tenant: {$tenant['nome']} (ID: $tenantId)");
                }
            }
            
            // Get or set filial
            if (!$filialId && $tenantId) {
                $filial = $db->fetch("SELECT * FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
                if ($filial) {
                    $filialId = $filial['id'];
                    $session->setFilial($filial);
                }
            }
            
            error_log("ai_chat.php - Context: Tenant=$tenantId, Filial=$filialId");
            
            // Use n8n service if configured, otherwise fallback to OpenAI
            try {
                if ($useN8n) {
                    error_log("ai_chat.php - Using N8nAIService");
                    $aiService = new \System\N8nAIService();
                } else {
                    error_log("ai_chat.php - Using OpenAIService");
                    $aiService = new \System\OpenAIService();
                }
                
                error_log("ai_chat.php - Calling processMessage...");
                
                // Pass chat history as additional context
                $additionalContext = [
                    'chat_history' => $chatHistory,
                    'source' => 'web'
                ];
                
                $response = $aiService->processMessage($message, [], $tenantId, $filialId, $additionalContext);
                error_log("ai_chat.php - Response received: " . json_encode($response));
                
            } catch (Exception $serviceError) {
                error_log("ai_chat.php - Service Error: " . $serviceError->getMessage());
                error_log("ai_chat.php - Stack trace: " . $serviceError->getTraceAsString());
                throw new Exception('Erro ao processar mensagem: ' . $serviceError->getMessage());
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'response' => $response
            ]);
            exit;
            
        case 'execute_operation':
            $operation = json_decode($_POST['operation'] ?? '{}', true);
            
            if (empty($operation)) {
                throw new Exception('Operação é obrigatória');
            }
            
            $aiService = new \System\OpenAIService();
            $result = $aiService->executeOperation($operation);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            exit;
            
        case 'upload_file':
            $uploadedFile = $_FILES['file'] ?? null;
            
            if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do arquivo');
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            $fileType = mime_content_type($uploadedFile['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Tipo de arquivo não suportado');
            }
            
            // Validate file size (max 10MB)
            if ($uploadedFile['size'] > 10 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande (máximo 10MB)');
            }
            
            // Create uploads directory if not exists
            $uploadDir = __DIR__ . '/../../uploads/ai_chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                throw new Exception('Erro ao salvar arquivo');
            }
            
            // Determine file type for processing
            $type = 'unknown';
            if (strpos($fileType, 'image/') === 0) {
                $type = 'image';
            } elseif ($fileType === 'application/pdf') {
                $type = 'pdf';
            } elseif (in_array($fileType, ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
                $type = 'spreadsheet';
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'file' => [
                    'name' => $uploadedFile['name'],
                    'path' => $filePath,
                    'type' => $type,
                    'size' => $uploadedFile['size']
                ]
            ]);
            exit;
            
        case 'get_context':
            $aiService = new \System\OpenAIService();
            
            // Use reflection to access private method (not ideal, but works for demo)
            $reflection = new ReflectionClass($aiService);
            $method = $reflection->getMethod('getSystemContext');
            $method->setAccessible(true);
            $context = $method->invoke($aiService);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'context' => json_decode($context, true)
            ]);
            exit;
            
        case 'search_products':
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                throw new Exception('Query de busca é obrigatória');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            $products = $db->fetchAll(
                "SELECT p.id, p.nome, p.preco_normal, p.descricao, c.nome as categoria 
                 FROM produtos p 
                 LEFT JOIN categorias c ON p.categoria_id = c.id 
                 WHERE p.tenant_id = ? AND p.filial_id = ? 
                 AND (LOWER(p.nome) LIKE LOWER(?) OR LOWER(p.descricao) LIKE LOWER(?)) 
                 ORDER BY p.nome 
                 LIMIT 20",
                [$tenantId, $filialId, "%{$query}%", "%{$query}%"]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            exit;
            
        case 'search_ingredients':
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                throw new Exception('Query de busca é obrigatória');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            $ingredients = $db->fetchAll(
                "SELECT id, nome, tipo, preco_adicional 
                 FROM ingredientes 
                 WHERE tenant_id = ? AND filial_id = ? 
                 AND LOWER(nome) LIKE LOWER(?) 
                 ORDER BY nome 
                 LIMIT 20",
                [$tenantId, $filialId, "%{$query}%"]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'ingredients' => $ingredients
            ]);
            exit;
            
        case 'search_categories':
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                throw new Exception('Query de busca é obrigatória');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            $categories = $db->fetchAll(
                "SELECT id, nome 
                 FROM categorias 
                 WHERE tenant_id = ? AND filial_id = ? 
                 AND LOWER(nome) LIKE LOWER(?) 
                 ORDER BY nome 
                 LIMIT 20",
                [$tenantId, $filialId, "%{$query}%"]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            exit;
            
        default:
            throw new Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    error_log('AI Chat Error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
