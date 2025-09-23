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

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $buscar = $_GET['buscar'] ?? $_POST['buscar'] ?? '';
    $excluir = $_GET['excluir'] ?? $_POST['excluir'] ?? '';
    
    if ($buscar == '1') {
        $action = 'buscar_categoria';
    } elseif ($excluir == '1') {
        $action = 'excluir_categoria';
    }
    
    $session = \System\Session::getInstance();
    $db = \System\Database::getInstance();
    
    $user = $session->getUser();
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$user || !$tenant || !$filial) {
        throw new \Exception('Sessão inválida');
    }
    
    switch ($action) {
        case 'criar_categoria':
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $parent_id = $_POST['parent_id'] ?? null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome da categoria é obrigatório');
            }
            
            // Handle image upload
            $imagem = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/categorias/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                $filename = 'cat_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadPath)) {
                    $imagem = 'uploads/categorias/' . $filename;
                }
            }
            
            $categoriaId = $db->insert('categorias', [
                'nome' => $nome,
                'descricao' => $descricao,
                'parent_id' => $parent_id ?: null,
                'ordem' => $ordem,
                'ativo' => $ativo,
                'imagem' => $imagem,
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria criada com sucesso!',
                'categoria_id' => $categoriaId
            ]);
            break;
            
        case 'atualizar_categoria':
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $parent_id = $_POST['parent_id'] ?? null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome da categoria é obrigatório');
            }
            
            if ($id <= 0) {
                throw new \Exception('ID da categoria inválido');
            }
            
            // Check if category exists
            $categoria = $db->fetch(
                'SELECT * FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada');
            }
            
            $updateData = [
                'nome' => $nome,
                'descricao' => $descricao,
                'parent_id' => $parent_id ?: null,
                'ordem' => $ordem,
                'ativo' => $ativo
            ];
            
            // Handle image upload
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/categorias/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                $filename = 'cat_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadPath)) {
                    // Remove old image if exists
                    if ($categoria['imagem'] && file_exists(__DIR__ . '/../../' . $categoria['imagem'])) {
                        unlink(__DIR__ . '/../../' . $categoria['imagem']);
                    }
                    $updateData['imagem'] = 'uploads/categorias/' . $filename;
                }
            }
            
            $db->update(
                'categorias',
                $updateData,
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso!'
            ]);
            break;
            
        case 'buscar_categoria':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new \Exception('ID da categoria inválido');
            }
            
            $categoria = $db->fetch(
                'SELECT * FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'categoria' => $categoria
            ]);
            break;
            
        case 'excluir_categoria':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new \Exception('ID da categoria inválido');
            }
            
            // Check if category exists
            $categoria = $db->fetch(
                'SELECT * FROM categorias WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada');
            }
            
            // Check if category has products
            $produtos = $db->fetch(
                'SELECT COUNT(*) as count FROM produtos WHERE categoria_id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if ($produtos['count'] > 0) {
                throw new \Exception('Não é possível excluir categoria que possui produtos');
            }
            
            // Check if category has subcategories
            $subcategorias = $db->fetch(
                'SELECT COUNT(*) as count FROM categorias WHERE parent_id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if ($subcategorias['count'] > 0) {
                throw new \Exception('Não é possível excluir categoria que possui subcategorias');
            }
            
            // Remove image file if exists
            if ($categoria['imagem'] && file_exists(__DIR__ . '/../../' . $categoria['imagem'])) {
                unlink(__DIR__ . '/../../' . $categoria['imagem']);
            }
            
            $db->delete(
                'categorias',
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria excluída com sucesso!'
            ]);
            break;
            
        case 'listar_categorias':
            $categorias = $db->fetchAll("
                SELECT c.*, 
                       parent.nome as parent_nome,
                       COUNT(p.id) as total_produtos
                FROM categorias c
                LEFT JOIN categorias parent ON c.parent_id = parent.id
                LEFT JOIN produtos p ON c.id = p.categoria_id AND p.tenant_id = c.tenant_id AND p.filial_id = c.filial_id
                WHERE c.tenant_id = ? AND c.filial_id = ?
                GROUP BY c.id, parent.nome
                ORDER BY c.parent_id IS NULL DESC, c.ordem ASC, c.nome ASC
            ", [$tenant['id'], $filial['id']]);
            
            echo json_encode([
                'success' => true,
                'categorias' => $categorias
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
