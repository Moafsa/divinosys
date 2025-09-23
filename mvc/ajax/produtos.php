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
            
        case 'buscar_categoria':
            $categoriaId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($categoriaId)) {
                throw new \Exception('ID da categoria é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            $stmt = $db->prepare("SELECT * FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$categoriaId, $tenantId, $filialId]);
            $categoria = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'categoria' => $categoria
            ]);
            break;
            
        case 'excluir_categoria':
            $categoriaId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($categoriaId)) {
                throw new \Exception('ID da categoria é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Verificar se há produtos usando esta categoria
            $stmt = $db->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$categoriaId, $tenantId, $filialId]);
            $produtosCount = $stmt->fetchColumn();
            
            if ($produtosCount > 0) {
                throw new \Exception('Não é possível excluir categoria que possui produtos associados');
            }
            
            // Excluir categoria
            $stmt = $db->prepare("DELETE FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$categoriaId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria excluída com sucesso'
            ]);
            break;
            
        case 'buscar_ingrediente':
            $ingredienteId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($ingredienteId)) {
                throw new \Exception('ID do ingrediente é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            $stmt = $db->prepare("SELECT * FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$ingredienteId, $tenantId, $filialId]);
            $ingrediente = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$ingrediente) {
                throw new \Exception('Ingrediente não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'ingrediente' => $ingrediente
            ]);
            break;
            
        case 'excluir_ingrediente':
            $ingredienteId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($ingredienteId)) {
                throw new \Exception('ID do ingrediente é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Verificar se há produtos usando este ingrediente
            $stmt = $db->prepare("SELECT COUNT(*) FROM produto_ingredientes WHERE ingrediente_id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$ingredienteId, $tenantId, $filialId]);
            $produtosCount = $stmt->fetchColumn();
            
            if ($produtosCount > 0) {
                throw new \Exception('Não é possível excluir ingrediente que está sendo usado em produtos');
            }
            
            // Excluir ingrediente
            $stmt = $db->prepare("DELETE FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$ingredienteId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ingrediente excluído com sucesso'
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
