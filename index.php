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
    // Initialize system
    $config = \System\Config::getInstance();
    $router = \System\Router::getInstance();
    
    // Handle AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        
        // Try to load AJAX handler
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
    // Mapear ações para arquivos AJAX
    $ajaxMap = [
        'login' => 'login.php',
        'criar_usuario' => 'auth.php',
        'listar_usuarios' => 'auth.php',
        'editar_usuario' => 'auth.php',
        'deletar_usuario' => 'auth.php',
        'buscar_usuario' => 'auth.php',
        'buscar_cliente' => 'auth.php',
        'criar_instancia' => 'evolution.php',
        'listar_instancias' => 'evolution.php',
        'deletar_instancia' => 'evolution.php',
        'conectar_instancia' => 'evolution.php',
        'desconectar_instancia' => 'evolution.php',
        'enviar_mensagem_lgpd' => 'evolution.php'
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
