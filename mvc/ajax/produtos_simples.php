<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Autoloader simples
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Definir tenant e filial globalmente
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId();
    
    // Verificar se existe tabela filiais
    $filiais_exists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'filiais') as exists");
    
    if ($filiais_exists['exists']) {
        // Sistema com tabela filiais - SEMPRE definir uma filial
        if ($filialId === null) {
            $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
            $filialId = $filial_padrao ? $filial_padrao['id'] : null;
        }
        
        // Se ainda não há filial, criar uma padrão
        if ($filialId === null) {
            $filial_id = $db->insert('filiais', [
                'tenant_id' => $tenantId,
                'nome' => 'Filial Principal',
                'status' => 'ativo',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $filialId = $filial_id;
        }
    } else {
        // Sistema sem tabela filiais - filiais são tenants independentes
        // Neste caso, filial_id deve ser null para usar apenas tenant_id
        $filialId = null;
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'buscar_produto':
            $produtoId = $_POST['produto_id'] ?? $_GET['produto_id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($produtoId)) {
                echo json_encode(['success' => false, 'message' => 'ID do produto é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM produtos WHERE id = $produtoId AND tenant_id = $tenantId AND filial_id = $filialId");
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
                // Verificar se já existe produto com o mesmo nome para este tenant
                $produto_existente = $db->fetch("
                    SELECT id FROM produtos 
                    WHERE nome = ? AND tenant_id = ?
                ", [$nome, $tenantId]);
                
                if ($produto_existente) {
                    echo json_encode(['success' => false, 'message' => 'Já existe um produto com este nome!']);
                    break;
                }
                
                // Criar novo produto
                $stmt = $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $tenantId, $filialId]);
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar produto existente
                $stmt = $db->query("
                    UPDATE produtos 
                    SET nome = '$nome', descricao = '$descricao', preco_normal = $precoNormal, 
                        preco_mini = $precoMini, categoria_id = $categoriaId, ativo = $ativo 
                    WHERE id = $produtoId AND tenant_id = $tenantId AND filial_id = $filialId
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
            $stmt = $db->query("DELETE FROM produtos WHERE id = $produtoId AND tenant_id = $tenantId AND filial_id = $filialId");
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        case 'buscar_categoria':
            $categoriaId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($categoriaId)) {
                echo json_encode(['success' => false, 'message' => 'ID da categoria é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM categorias WHERE id = $categoriaId AND tenant_id = $tenantId AND filial_id = $filialId");
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
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $ativo, $tenantId, $filialId]);
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar categoria existente
                $stmt = $db->query("
                    UPDATE categorias 
                    SET nome = '$nome', descricao = '$descricao', ativo = $ativo 
                    WHERE id = $categoriaId AND tenant_id = $tenantId AND filial_id = $filialId
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
            $stmt = $db->query("DELETE FROM categorias WHERE id = $categoriaId AND tenant_id = $tenantId AND filial_id = $filialId");
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        case 'buscar_ingrediente':
            $ingredienteId = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($ingredienteId)) {
                echo json_encode(['success' => false, 'message' => 'ID do ingrediente é obrigatório']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM ingredientes WHERE id = $ingredienteId AND tenant_id = $tenantId AND filial_id = $filialId");
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
                // Verificar se já existe ingrediente com o mesmo nome para este tenant
                $ingrediente_existente = $db->fetch("
                    SELECT id FROM ingredientes 
                    WHERE nome = ? AND tenant_id = ?
                ", [$nome, $tenantId]);
                
                if ($ingrediente_existente) {
                    echo json_encode(['success' => false, 'message' => 'Já existe um ingrediente com este nome!']);
                    break;
                }
                
                // Criar novo ingrediente
                // Normalizar filial: se ausente ou inválida, usar filial padrão do tenant
                $filialIdToUse = $filialId;
                if ($filialIdToUse === null) {
                    $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                    $filialIdToUse = $filial_padrao['id'] ?? null;
                } else {
                    $filial_valida = $db->fetch("SELECT id FROM filiais WHERE id = ? AND tenant_id = ?", [$filialIdToUse, $tenantId]);
                    if (!$filial_valida) {
                        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                        $filialIdToUse = $filial_padrao['id'] ?? null;
                    }
                }
                if ($filialIdToUse === null) {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma filial encontrada para este estabelecimento. Crie uma filial antes de cadastrar ingredientes.']);
                    break;
                }
                $db->query("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?, ?)", [$nome, $descricao, $precoAdicional, $ativo, $tenantId, $filialIdToUse]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar ingrediente existente
                $db->query("
                    UPDATE ingredientes 
                    SET nome = ?, descricao = ?, preco_adicional = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = ? AND filial_id = ?
                ", [$nome, $descricao, $precoAdicional, $ativo, $ingredienteId, $tenantId, $filialId]);
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
            $stmt = $db->query("DELETE FROM ingredientes WHERE id = $ingredienteId AND tenant_id = $tenantId AND filial_id = $filialId");
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada: ' . $action]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
