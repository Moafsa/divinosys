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
    $buscarProduto = $_GET['buscar_produto'] ?? $_POST['buscar_produto'] ?? '';
    
    if ($buscarProduto == '1') {
        $action = 'buscar_produto';
    }
    
    switch ($action) {
        case 'buscar_produto':
            $produtoId = $_GET['produto_id'] ?? $_POST['produto_id'] ?? '';
            
            if (empty($produtoId)) {
                throw new \Exception('ID do produto é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Buscar produto
            $produto = $db->fetch(
                "SELECT * FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$produtoId, $tenantId, $filialId]
            );
            
            if (!$produto) {
                throw new \Exception('Produto não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'produto' => $produto
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    error_log('Erro no AJAX de Produtos: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
