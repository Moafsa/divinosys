<?php
header('Content-Type: application/json');

// Autoloader simples
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        // ===== PRODUTOS =====
        case 'listar_produtos':
            $stmt = $db->query("
                SELECT p.*, c.nome as categoria_nome 
                FROM produtos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.tenant_id = 1 AND p.filial_id = 1 
                ORDER BY p.nome
            ");
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $produtos]);
            break;
            
        case 'buscar_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("SELECT * FROM produtos WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($produto) {
                echo json_encode(['success' => true, 'data' => $produto]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            }
            break;
            
        case 'salvar_produto':
            $id = $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $preco_normal = $_POST['preco_normal'] ?? 0;
            $preco_mini = $_POST['preco_mini'] ?? 0;
            $categoria_id = $_POST['categoria_id'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome) || empty($preco_normal)) {
                echo json_encode(['success' => false, 'message' => 'Nome e preço são obrigatórios']);
                break;
            }
            
            if (empty($id)) {
                // Criar
                $stmt = $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $preco_normal, $preco_mini, $categoria_id, $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar
                $stmt = $db->query("
                    UPDATE produtos 
                    SET nome = '$nome', descricao = '$descricao', preco_normal = $preco_normal, 
                        preco_mini = $preco_mini, categoria_id = $categoria_id, ativo = $ativo 
                    WHERE id = $id AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("DELETE FROM produtos WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        // ===== CATEGORIAS =====
        case 'listar_categorias':
            $stmt = $db->query("SELECT * FROM categorias WHERE tenant_id = 1 AND filial_id = 1 ORDER BY nome");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categorias]);
            break;
            
        case 'buscar_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("SELECT * FROM categorias WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($categoria) {
                echo json_encode(['success' => true, 'data' => $categoria]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
            }
            break;
            
        case 'salvar_categoria':
            $id = $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
                break;
            }
            
            if (empty($id)) {
                // Criar
                $stmt = $db->query("
                    INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar
                $stmt = $db->query("
                    UPDATE categorias 
                    SET nome = '$nome', descricao = '$descricao', ativo = $ativo 
                    WHERE id = $id AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!']);
            }
            break;
            
        case 'excluir_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("DELETE FROM categorias WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        // ===== INGREDIENTES =====
        case 'listar_ingredientes':
            $stmt = $db->query("SELECT * FROM ingredientes WHERE tenant_id = 1 AND filial_id = 1 ORDER BY nome");
            $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $ingredientes]);
            break;
            
        case 'buscar_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("SELECT * FROM ingredientes WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            $ingrediente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ingrediente) {
                echo json_encode(['success' => true, 'data' => $ingrediente]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ingrediente não encontrado']);
            }
            break;
            
        case 'salvar_ingrediente':
            $id = $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $preco_adicional = $_POST['preco_adicional'] ?? 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
                break;
            }
            
            if (empty($id)) {
                // Criar
                $stmt = $db->query("
                    INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) 
                    VALUES ('$nome', '$descricao', $preco_adicional, $ativo, 1, 1)
                ");
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar
                $stmt = $db->query("
                    UPDATE ingredientes 
                    SET nome = '$nome', descricao = '$descricao', preco_adicional = $preco_adicional, ativo = $ativo 
                    WHERE id = $id AND tenant_id = 1 AND filial_id = 1
                ");
                echo json_encode(['success' => true, 'message' => 'Ingrediente atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("DELETE FROM ingredientes WHERE id = $id AND tenant_id = 1 AND filial_id = 1");
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada: ' . $action]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
