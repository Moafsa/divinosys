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
            
        case 'salvar_produto':
            $produtoId = $_POST['produto_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoNormal = $_POST['preco_normal'] ?? 0;
            $precoMini = $_POST['preco_mini'] ?? 0;
            $categoriaId = $_POST['categoria_id'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome) || empty($precoNormal)) {
                throw new \Exception('Nome e preço são obrigatórios');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            if (empty($produtoId)) {
                // Criar novo produto
                $stmt = $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $tenantId, $filialId]);
                $message = 'Produto criado com sucesso!';
            } else {
                // Atualizar produto existente
                $stmt = $db->query("
                    UPDATE produtos 
                    SET nome = ?, descricao = ?, preco_normal = ?, preco_mini = ?, categoria_id = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = ? AND filial_id = ?
                ");
                $stmt->execute([$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $produtoId, $tenantId, $filialId]);
                $message = 'Produto atualizado com sucesso!';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;
            
            
        case 'salvar_categoria':
            $categoriaId = $_POST['categoria_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $parentId = $_POST['parent_id'] ?? null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome da categoria é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            if (empty($categoriaId)) {
                // Criar nova categoria
                $stmt = $db->query("
                    INSERT INTO categorias (nome, descricao, parent_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $parentId, $ativo, $tenantId, $filialId]);
                $message = 'Categoria criada com sucesso!';
            } else {
                // Atualizar categoria existente
                $stmt = $db->query("
                    UPDATE categorias 
                    SET nome = ?, descricao = ?, parent_id = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = ? AND filial_id = ?
                ");
                $stmt->execute([$nome, $descricao, $parentId, $ativo, $categoriaId, $tenantId, $filialId]);
                $message = 'Categoria atualizada com sucesso!';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
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
            
            $stmt = $db->query("SELECT * FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?");
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
            $stmt = $db->query("SELECT COUNT(*) FROM produtos WHERE categoria_id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$categoriaId, $tenantId, $filialId]);
            $produtosCount = $stmt->fetchColumn();
            
            if ($produtosCount > 0) {
                throw new \Exception('Não é possível excluir categoria que possui produtos associados');
            }
            
            // Excluir categoria
            $stmt = $db->query("DELETE FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$categoriaId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria excluída com sucesso'
            ]);
            break;
            
        case 'salvar_ingrediente':
            $ingredienteId = $_POST['ingrediente_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoAdicional = $_POST['preco_adicional'] ?? 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome do ingrediente é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            if (empty($ingredienteId)) {
                // Criar novo ingrediente
                $stmt = $db->query("
                    INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $precoAdicional, $ativo, $tenantId, $filialId]);
                $message = 'Ingrediente criado com sucesso!';
            } else {
                // Atualizar ingrediente existente
                $stmt = $db->query("
                    UPDATE ingredientes 
                    SET nome = ?, descricao = ?, preco_adicional = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = ? AND filial_id = ?
                ");
                $stmt->execute([$nome, $descricao, $precoAdicional, $ativo, $ingredienteId, $tenantId, $filialId]);
                $message = 'Ingrediente atualizado com sucesso!';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
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
            
            $stmt = $db->query("SELECT * FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?");
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
            $stmt = $db->query("SELECT COUNT(*) FROM produto_ingredientes WHERE ingrediente_id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$ingredienteId, $tenantId, $filialId]);
            $produtosCount = $stmt->fetchColumn();
            
            if ($produtosCount > 0) {
                throw new \Exception('Não é possível excluir ingrediente que está sendo usado em produtos');
            }
            
            // Excluir ingrediente
            $stmt = $db->query("DELETE FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?");
            $stmt->execute([$ingredienteId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ingrediente excluído com sucesso'
            ]);
            break;
            
        case 'excluir_produto':
            $produtoId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($produtoId)) {
                throw new \Exception('ID do produto é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Verificar se produto existe
            $produto = $db->fetch(
                "SELECT * FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$produtoId, $tenantId, $filialId]
            );
            
            if (!$produto) {
                throw new \Exception('Produto não encontrado');
            }
            
            // Excluir produto
            $stmt = $db->query("DELETE FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?", [$produtoId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Produto excluído com sucesso!'
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
