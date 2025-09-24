<?php
// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Autoloader
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    // Conectar ao banco
    $db = \System\Database::getInstance();
    
    // Obter ação
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Ação não especificada');
    }
    
    // Log da ação
    error_log("AJAX Action: " . $action);
    
    switch ($action) {
        case 'buscar_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? $_POST['produto_id'] ?? $_GET['produto_id'] ?? '';

            if (empty($id)) {
                throw new Exception('ID do produto é obrigatório');
            }

            $produto = $db->fetch("SELECT * FROM produtos WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);

            if (!$produto) {
                throw new Exception('Produto não encontrado');
            }

            // Buscar ingredientes do produto
            $ingredientes = $db->fetchAll("
                SELECT i.*, pi.produto_id 
                FROM ingredientes i 
                INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id 
                WHERE pi.produto_id = ? AND i.tenant_id = 1 AND i.filial_id = 1
            ", [$id]);

            // Buscar todos os ingredientes disponíveis
            $todosIngredientes = $db->fetchAll("
                SELECT * FROM ingredientes 
                WHERE tenant_id = 1 AND filial_id = 1 
                ORDER BY nome
            ");

            echo json_encode([
                'success' => true, 
                'produto' => $produto, 
                'ingredientes' => $ingredientes,
                'todos_ingredientes' => $todosIngredientes
            ]);
            break;
            
        case 'buscar_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID da categoria é obrigatório');
            }
            
            $categoria = $db->fetch("SELECT * FROM categorias WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);
            
            if (!$categoria) {
                throw new Exception('Categoria não encontrada');
            }
            
            echo json_encode(['success' => true, 'categoria' => $categoria]);
            break;
            
        case 'buscar_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do ingrediente é obrigatório');
            }
            
            $ingrediente = $db->fetch("SELECT * FROM ingredientes WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);
            
            if (!$ingrediente) {
                throw new Exception('Ingrediente não encontrado');
            }
            
            echo json_encode(['success' => true, 'ingrediente' => $ingrediente]);
            break;
            
        case 'salvar_produto':
            $produtoId = $_POST['produto_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoNormal = $_POST['preco_normal'] ?? 0;
            $precoMini = $_POST['preco_mini'] ?? 0;
            $categoriaId = $_POST['categoria_id'] ?? null;
            $ativo = $_POST['ativo'] ?? 0;
            $estoqueAtual = $_POST['estoque_atual'] ?? 0;
            $estoqueMinimo = $_POST['estoque_minimo'] ?? 0;
            $precoCusto = $_POST['preco_custo'] ?? 0;
            $ingredientes = $_POST['ingredientes'] ?? '[]';
            
            // Tratar valores vazios
            if ($precoMini === '' || $precoMini === null) $precoMini = 0;
            if ($categoriaId === '' || $categoriaId === null) $categoriaId = null;
            if ($estoqueAtual === '' || $estoqueAtual === null) $estoqueAtual = 0;
            if ($estoqueMinimo === '' || $estoqueMinimo === null) $estoqueMinimo = 0;
            if ($precoCusto === '' || $precoCusto === null) $precoCusto = 0;
            
            if (empty($nome) || empty($precoNormal)) {
                throw new Exception('Nome e preço normal são obrigatórios');
            }
            
            // Processar imagem se enviada
            $imagemPath = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/produtos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid() . '.' . $extension;
                $imagemPath = 'uploads/produtos/' . $fileName;
                
                if (!move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../../' . $imagemPath)) {
                    throw new Exception('Erro ao fazer upload da imagem');
                }
            }
            
            if (empty($produtoId)) {
                // Criar novo produto
                $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, estoque_atual, estoque_minimo, preco_custo, imagem, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ", [$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $estoqueAtual, $estoqueMinimo, $precoCusto, $imagemPath]);
                
                $novoProdutoId = $db->lastInsertId();
                
                // Salvar ingredientes
                $ingredientesArray = json_decode($ingredientes, true);
                if (is_array($ingredientesArray)) {
                    foreach ($ingredientesArray as $ingredienteId) {
                        $db->query("
                            INSERT INTO produto_ingredientes (produto_id, ingrediente_id, tenant_id, filial_id) 
                            VALUES (?, ?, 1, 1)
                        ", [$novoProdutoId, $ingredienteId]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar produto existente
                if ($imagemPath) {
                    $db->query("
                        UPDATE produtos 
                        SET nome = ?, descricao = ?, preco_normal = ?, 
                            preco_mini = ?, categoria_id = ?, ativo = ?, estoque_atual = ?, estoque_minimo = ?, preco_custo = ?, imagem = ? 
                        WHERE id = ? AND tenant_id = 1 AND filial_id = 1
                    ", [$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $estoqueAtual, $estoqueMinimo, $precoCusto, $imagemPath, $produtoId]);
                } else {
                    $db->query("
                        UPDATE produtos 
                        SET nome = ?, descricao = ?, preco_normal = ?, 
                            preco_mini = ?, categoria_id = ?, ativo = ?, estoque_atual = ?, estoque_minimo = ?, preco_custo = ? 
                        WHERE id = ? AND tenant_id = 1 AND filial_id = 1
                    ", [$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $estoqueAtual, $estoqueMinimo, $precoCusto, $produtoId]);
                }
                
                // Atualizar ingredientes
                $db->query("DELETE FROM produto_ingredientes WHERE produto_id = ? AND tenant_id = 1 AND filial_id = 1", [$produtoId]);
                
                $ingredientesArray = json_decode($ingredientes, true);
                if (is_array($ingredientesArray)) {
                    foreach ($ingredientesArray as $ingredienteId) {
                        $db->query("
                            INSERT INTO produto_ingredientes (produto_id, ingrediente_id, tenant_id, filial_id) 
                            VALUES (?, ?, 1, 1)
                        ", [$produtoId, $ingredienteId]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do produto é obrigatório');
            }
            
            $db->query("DELETE FROM produtos WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        case 'salvar_categoria':
            $categoriaId = $_POST['categoria_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $parentId = $_POST['parent_id'] ?? '';
            $ativo = $_POST['ativo'] ?? 0;
            
            // Tratar valores vazios
            if ($parentId === '' || $parentId === null) $parentId = null;
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            if (empty($categoriaId)) {
                // Criar nova categoria
                $db->query("
                    INSERT INTO categorias (nome, descricao, parent_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, 1, 1)
                ", [$nome, $descricao, $parentId, $ativo]);
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar categoria existente
                $db->query("
                    UPDATE categorias 
                    SET nome = ?, descricao = ?, parent_id = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = 1 AND filial_id = 1
                ", [$nome, $descricao, $parentId, $ativo, $categoriaId]);
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!']);
            }
            break;
            
        case 'excluir_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID da categoria é obrigatório');
            }
            
            $db->query("DELETE FROM categorias WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        case 'salvar_ingrediente':
            $ingredienteId = $_POST['ingrediente_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoAdicional = $_POST['preco_adicional'] ?? 0;
            $ativo = $_POST['ativo'] ?? 0;
            
            if (empty($nome)) {
                throw new Exception('Nome do ingrediente é obrigatório');
            }
            
            if (empty($ingredienteId)) {
                // Criar novo ingrediente
                $db->query("
                    INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, 1, 1)
                ", [$nome, $descricao, $precoAdicional, $ativo]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar ingrediente existente
                $db->query("
                    UPDATE ingredientes 
                    SET nome = ?, descricao = ?, preco_adicional = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = 1 AND filial_id = 1
                ", [$nome, $descricao, $precoAdicional, $ativo, $ingredienteId]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do ingrediente é obrigatório');
            }
            
            $db->query("DELETE FROM ingredientes WHERE id = ? AND tenant_id = 1 AND filial_id = 1", [$id]);
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            throw new Exception('Ação não encontrada: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    error_log("AJAX Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro fatal: ' . $e->getMessage()]);
}
?>
