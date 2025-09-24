<?php
header('Content-Type: application/json');

// Autoloader simples
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'buscar_produto':
            $produtoId = $_POST['produto_id'] ?? $_GET['produto_id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($produtoId)) {
                echo json_encode(['success' => false, 'message' => 'ID do produto é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM produtos WHERE id = $produtoId AND tenant_id = 1 AND filial_id = 1");
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                break;
            }
            
            echo json_encode(['success' => true, 'produto' => $produto]);
            break;
            
        case 'salvar_produto':
            $produtoId = $_POST['produto_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoNormal = $_POST['preco_normal'] ?? 0;
            $precoMini = $_POST['preco_mini'] ?? 0;
            $categoriaId = $_POST['categoria_id'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome) || empty($precoNormal)) {
                echo json_encode(['success' => false, 'message' => 'Nome e preço são obrigatórios']);
                break;
            }
            
            if (empty($produtoId)) {
                // Criar novo produto
                $stmt = $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $precoNormal, $precoMini, $categoriaId, $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar produto existente
                $stmt = $db->query("
                    UPDATE produtos 
                    SET nome = '$nome', descricao = '$descricao', preco_normal = $precoNormal, 
                        preco_mini = $precoMini, categoria_id = $categoriaId, ativo = $ativo 
                    WHERE id = $produtoId AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_produto':
            $produtoId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($produtoId)) {
                echo json_encode(['success' => false, 'message' => 'ID do produto é obrigatório']);
                break;
            }
            
            // Excluir produto
            $stmt = $db->query("DELETE FROM produtos WHERE id = $produtoId AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        case 'buscar_categoria':
            $categoriaId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($categoriaId)) {
                echo json_encode(['success' => false, 'message' => 'ID da categoria é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM categorias WHERE id = $categoriaId AND tenant_id = 1 AND filial_id = 1");
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$categoria) {
                echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
                break;
            }
            
            echo json_encode(['success' => true, 'categoria' => $categoria]);
            break;
            
        case 'salvar_categoria':
            $categoriaId = $_POST['categoria_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome da categoria é obrigatório']);
                break;
            }
            
            if (empty($categoriaId)) {
                // Criar nova categoria
                $stmt = $db->query("
                    INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar categoria existente
                $stmt = $db->query("
                    UPDATE categorias 
                    SET nome = '$nome', descricao = '$descricao', ativo = $ativo 
                    WHERE id = $categoriaId AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!']);
            }
            break;
            
        case 'excluir_categoria':
            $categoriaId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($categoriaId)) {
                echo json_encode(['success' => false, 'message' => 'ID da categoria é obrigatório']);
                break;
            }
            
            // Excluir categoria
            $stmt = $db->query("DELETE FROM categorias WHERE id = $categoriaId AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        case 'buscar_ingrediente':
            $ingredienteId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($ingredienteId)) {
                echo json_encode(['success' => false, 'message' => 'ID do ingrediente é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM ingredientes WHERE id = $ingredienteId AND tenant_id = 1 AND filial_id = 1");
            $ingrediente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ingrediente) {
                echo json_encode(['success' => false, 'message' => 'Ingrediente não encontrado']);
                break;
            }
            
            echo json_encode(['success' => true, 'ingrediente' => $ingrediente]);
            break;
            
        case 'salvar_ingrediente':
            $ingredienteId = $_POST['ingrediente_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoAdicional = $_POST['preco_adicional'] ?? 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome do ingrediente é obrigatório']);
                break;
            }
            
            if (empty($ingredienteId)) {
                // Criar novo ingrediente
                $stmt = $db->query("
                    INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $precoAdicional, $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar ingrediente existente
                $stmt = $db->query("
                    UPDATE ingredientes 
                    SET nome = '$nome', descricao = '$descricao', preco_adicional = $precoAdicional, ativo = $ativo 
                    WHERE id = $ingredienteId AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Ingrediente atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_ingrediente':
            $ingredienteId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($ingredienteId)) {
                echo json_encode(['success' => false, 'message' => 'ID do ingrediente é obrigatório']);
                break;
            }
            
            // Excluir ingrediente
            $stmt = $db->query("DELETE FROM ingredientes WHERE id = $ingredienteId AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada: ' . $action]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
