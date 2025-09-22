<?php
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

// Simples e direto - usar require_once
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $buscarMesas = $_GET['buscar_mesas'] ?? $_POST['buscar_mesas'] ?? '';
    
    if ($buscarMesas == '1') {
        $action = 'buscar_mesas';
    }
    
    switch ($action) {
        case 'buscar_mesas':
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Buscar todas as mesas
            $mesas = $db->fetchAll(
                "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id_mesa::integer",
                [$tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'mesas' => $mesas
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    error_log('Erro no AJAX de Mesas: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
