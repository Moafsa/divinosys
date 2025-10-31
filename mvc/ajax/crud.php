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
        // ===== PRODUTOS =====
        case 'listar_produtos':
            if ($filialId !== null) {
                // Sistema com filiais - usar filtro por filial_id
                $produtos = $db->fetchAll("
                    SELECT p.*, c.nome as categoria_nome 
                    FROM produtos p 
                    LEFT JOIN categorias c ON p.categoria_id = c.id 
                    WHERE p.tenant_id = ? AND p.filial_id = ? 
                    ORDER BY p.nome
                ", [$tenantId, $filialId]);
            } else {
                // Sistema sem filiais - usar apenas tenant_id
                $produtos = $db->fetchAll("
                    SELECT p.*, c.nome as categoria_nome 
                    FROM produtos p 
                    LEFT JOIN categorias c ON p.categoria_id = c.id 
                    WHERE p.tenant_id = ? 
                    ORDER BY p.nome
                ", [$tenantId]);
            }
            echo json_encode(['success' => true, 'data' => $produtos]);
            break;
            
        case 'buscar_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            if ($filialId !== null) {
                $produto = $db->fetch("SELECT * FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?", [$id, $tenantId, $filialId]);
            } else {
                $produto = $db->fetch("SELECT * FROM produtos WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            }
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
                // Verificar se já existe produto com o mesmo nome para este tenant
                $produto_existente = $db->fetch("
                    SELECT id FROM produtos 
                    WHERE nome = ? AND tenant_id = ?
                ", [$nome, $tenantId]);
                
                if ($produto_existente) {
                    echo json_encode(['success' => false, 'message' => 'Já existe um produto com este nome!']);
                    break;
                }
                
                // Criar
                $stmt = $db->query("
                    INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $preco_normal, $preco_mini, $categoria_id, $ativo, $tenantId, $filialId]);
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar
                $stmt = $db->query("
                    UPDATE produtos 
                    SET nome = '$nome', descricao = '$descricao', preco_normal = $preco_normal, 
                        preco_mini = $preco_mini, categoria_id = $categoria_id, ativo = $ativo 
                    WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId
                ");
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("DELETE FROM produtos WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId");
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        // ===== CATEGORIAS =====
        case 'listar_categorias':
            $stmt = $db->query("SELECT * FROM categorias WHERE tenant_id = $tenantId AND filial_id = $filialId ORDER BY nome");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $categorias]);
            break;
            
        case 'buscar_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("SELECT * FROM categorias WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId");
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
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $ativo, $tenantId, $filialId]);
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar
                $stmt = $db->query("
                    UPDATE categorias 
                    SET nome = '$nome', descricao = '$descricao', ativo = $ativo 
                    WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId
                ");
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!']);
            }
            break;
            
        case 'excluir_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $stmt = $db->query("DELETE FROM categorias WHERE id = $id AND tenant_id = $tenantId AND filial_id = $filialId");
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        // ===== INGREDIENTES =====
        case 'listar_ingredientes':
            // Sistema com filiais - mostrar ingredientes da filial atual
            $ingredientes = $db->fetchAll("
                SELECT * FROM ingredientes 
                WHERE tenant_id = ? AND filial_id = ?
                ORDER BY nome
            ", [$tenantId, $filialId]);
            echo json_encode(['success' => true, 'data' => $ingredientes]);
            break;
            
        case 'buscar_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            // Sistema com filiais - buscar ingrediente da filial atual
            $ingrediente = $db->fetch("
                SELECT * FROM ingredientes 
                WHERE id = ? AND tenant_id = ? AND filial_id = ?
            ", [$id, $tenantId, $filialId]);
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
                // Verificar se já existe ingrediente com o mesmo nome para este tenant
                $ingrediente_existente = $db->fetch("
                    SELECT id FROM ingredientes 
                    WHERE nome = ? AND tenant_id = ?
                ", [$nome, $tenantId]);
                
                if ($ingrediente_existente) {
                    echo json_encode(['success' => false, 'message' => 'Já existe um ingrediente com este nome!']);
                    break;
                }
                
                // Criar
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
                $db->query("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?, ?)", [$nome, $descricao, $preco_adicional, $ativo, $tenantId, $filialIdToUse]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar
                $db->query("
                    UPDATE ingredientes 
                    SET nome = ?, descricao = ?, preco_adicional = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = ? AND filial_id = ?
                ", [$nome, $descricao, $preco_adicional, $ativo, $id, $tenantId, $filialId]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            $db->query("DELETE FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?", [$id, $tenantId, $filialId]);
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada: ' . $action]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
