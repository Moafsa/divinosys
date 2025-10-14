<?php

// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('America/Sao_Paulo');

// Define application paths
define('APP_PATH', __DIR__);
define('SYSTEM_PATH', __DIR__ . '/system');
define('MVC_PATH', __DIR__ . '/mvc');
define('UPLOADS_PATH', __DIR__ . '/uploads');
define('LOGS_PATH', __DIR__ . '/logs');

// Create necessary directories
if (!is_dir(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}
if (!is_dir(LOGS_PATH)) {
    mkdir(LOGS_PATH, 0755, true);
}

// Auto-fix sequences on application start (only once per session)
if (!isset($_SESSION['sequences_fixed'])) {
    require_once __DIR__ . '/auto_fix_sequences.php';
    autoFixSequences();
    $_SESSION['sequences_fixed'] = true;
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => SYSTEM_PATH . '/',
        'App\\' => APP_PATH . '/app/',
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

// Load Composer autoloader if exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Load helper functions
require_once __DIR__ . '/system/helpers.php';

try {
    error_log('INDEX: Iniciando aplicação');
    
    // Initialize system
    $config = \System\Config::getInstance();
    $router = \System\Router::getInstance();
    
    error_log('INDEX: Sistema inicializado');
    
    // Handle AJAX requests
    if (!empty($_GET['action']) || !empty($_POST['action'])) {
        
        error_log('INDEX: Entrou no bloco AJAX');
        
        // Try to load AJAX handler
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        error_log('INDEX: Action detectada: ' . $action);
        
        
    // Mapear ações para arquivos AJAX
    $ajaxMap = [
        'login' => 'login.php',
        'criar_usuario' => 'auth.php',
        'listar_usuarios' => 'auth.php',
        'editar_usuario' => 'auth.php',
        'deletar_usuario' => 'auth.php',
        'buscar_usuario' => 'auth.php',
        'buscar_cliente' => 'auth.php',
        // Mesa e pedidos actions
        'mesa_multiplos_pedidos' => 'mesa_multiplos_pedidos_simples.php',
        'ver_mesa_multiplos_pedidos' => 'mesa_multiplos_pedidos_simples.php',
        'fechar_pedido_individual' => 'mesa_multiplos_pedidos_simples.php',
        'fechar_mesa_completa' => 'mesa_multiplos_pedidos_simples.php',
        'pagamentos_parciais' => 'pagamentos_parciais.php',
        // WhatsApp/Baileys actions
        'criar_instancia' => 'whatsapp.php',
        'listar_instancias' => 'whatsapp.php',
        'deletar_instancia' => 'whatsapp.php',
        'conectar_instancia' => 'whatsapp.php',
        'desconectar_instancia' => 'whatsapp.php',
        'enviar_mensagem' => 'whatsapp.php',
        'status_instancia' => 'whatsapp.php',
        'send_message' => 'whatsapp_n8n.php',
        'get_instance_status' => 'whatsapp_n8n.php',
        'webhook_received' => 'whatsapp_n8n.php',
        // AI Chat actions
        'ai_chat' => 'ai_chat.php',
        'send_message' => 'ai_chat.php',
        'execute_operation' => 'ai_chat.php',
        'upload_file' => 'ai_chat.php',
        'get_context' => 'ai_chat.php',
        'search_products' => 'ai_chat.php',
        'search_ingredients' => 'ai_chat.php',
        'search_categories' => 'ai_chat.php',
    ];
        
        $ajaxFile = $ajaxMap[$action] ?? $action . '.php';
        $fullPath = MVC_PATH . '/ajax/' . $ajaxFile;
        
        if (file_exists($fullPath)) {
            include $fullPath;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada: ' . $action]);
        }
        exit;
    }
    
    // Handle regular requests
    $router->resolve();
    
} catch (\Exception $e) {
    // Log error
    error_log('Application error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Show error page
    if ($config->isDebug()) {
        echo '<h1>Erro da Aplicação</h1>';
        echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Linha:</strong> ' . $e->getLine() . '</p>';
        echo '<h2>Stack Trace:</h2>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>Erro Interno do Servidor</h1>';
        echo '<p>Ocorreu um erro inesperado. Tente novamente mais tarde.</p>';
    }
}

// End output buffering
ob_end_flush();