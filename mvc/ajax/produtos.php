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
    $buscarTodos = $_GET['buscar_todos'] ?? $_POST['buscar_todos'] ?? '';
    $buscarProdutos = $_GET['buscar_produtos'] ?? $_POST['buscar_produtos'] ?? '';
    
    // Normalizar ações baseadas em parâmetros GET/POST
    if ($buscarProduto == '1') {
        $action = 'buscar_produto';
    } elseif ($buscarTodos == '1') {
        $action = 'buscar_todos';
    } elseif ($buscarProdutos == '1') {
        $action = 'buscar_produtos';
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
                $stmt = $db->query("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $descricao, $precoAdicional, $ativo, $tenantId, $filialIdToUse]);
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
            
        case 'buscar_todos':
        case 'listar_produtos':
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId();
            
            // Normalizar filial se necessário
            if ($filialId === null) {
                $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
            // Buscar produtos com categorias - garantir unicidade absoluta
            // Primeiro buscar IDs únicos, depois buscar dados completos
            if ($filialId !== null) {
                $produtosIds = $db->fetchAll(
                    "SELECT DISTINCT id FROM produtos 
                     WHERE tenant_id = ? AND filial_id = ? AND COALESCE(ativo, true) = true
                     ORDER BY id",
                    [$tenantId, $filialId]
                );
            } else {
                $produtosIds = $db->fetchAll(
                    "SELECT DISTINCT id FROM produtos 
                     WHERE tenant_id = ? AND COALESCE(ativo, true) = true
                     ORDER BY id",
                    [$tenantId]
                );
            }
            
            // Se não encontrou produtos, retornar array vazio
            if (empty($produtosIds)) {
                $produtos = [];
            } else {
                // Buscar dados completos dos produtos únicos
                $ids = array_column($produtosIds, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $produtos = $db->fetchAll(
                    "SELECT p.*, c.nome as categoria_nome 
                     FROM produtos p 
                     LEFT JOIN categorias c ON p.categoria_id = c.id 
                     WHERE p.id IN ($placeholders)
                     ORDER BY c.nome, p.nome",
                    $ids
                );
            }
            
            // Remover produtos duplicados por ID usando array associativo (mais eficiente)
            $produtosTemp = [];
            foreach ($produtos as $produto) {
                $produtoId = (int)$produto['id'];
                // Se já existe, manter o primeiro (pular duplicado)
                if (!isset($produtosTemp[$produtoId])) {
                    $produtosTemp[$produtoId] = $produto;
                }
            }
            // Converter de volta para array indexado numericamente
            $produtos = array_values($produtosTemp);
            
            // Buscar ingredientes para cada produto
            // IMPORTANTE: Não usar referência (&) para evitar problemas de duplicação
            foreach ($produtos as $key => $produto) {
                try {
                    $ingredientes = $db->fetchAll(
                        "SELECT i.id, i.nome, i.tipo, i.preco_adicional, COALESCE(pi.padrao, true) as padrao
                         FROM ingredientes i
                         INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id
                         WHERE pi.produto_id = ? AND COALESCE(i.disponivel, true) = true
                         ORDER BY i.tipo, i.nome",
                        [$produto['id']]
                    );
                    $produtos[$key]['ingredientes'] = $ingredientes;
                } catch (\Exception $e) {
                    $produtos[$key]['ingredientes'] = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'produtos' => $produtos
            ]);
            break;
            
        case 'buscar_produtos':
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId();
            $query = $_GET['q'] ?? $_POST['q'] ?? '';
            $categoriaId = $_GET['categoria_id'] ?? $_POST['categoria_id'] ?? '';
            
            // Normalizar filial se necessário
            if ($filialId === null) {
                $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
            // Construir query para buscar IDs únicos primeiro
            $sqlIds = "SELECT DISTINCT id FROM produtos WHERE tenant_id = ?";
            $paramsIds = [$tenantId];
            
            if ($filialId !== null) {
                $sqlIds .= " AND filial_id = ?";
                $paramsIds[] = $filialId;
            }
            
            $sqlIds .= " AND COALESCE(ativo, true) = true";
            
            // Adicionar filtro de busca
            if (!empty($query)) {
                $sqlIds .= " AND (LOWER(nome) LIKE LOWER(?) OR LOWER(descricao) LIKE LOWER(?))";
                $searchTerm = "%{$query}%";
                $paramsIds[] = $searchTerm;
                $paramsIds[] = $searchTerm;
            }
            
            // Adicionar filtro de categoria
            if (!empty($categoriaId)) {
                $sqlIds .= " AND categoria_id = ?";
                $paramsIds[] = $categoriaId;
            }
            
            $sqlIds .= " ORDER BY id";
            
            // Buscar IDs únicos
            $produtosIds = $db->fetchAll($sqlIds, $paramsIds);
            
            // Se não encontrou produtos, retornar array vazio
            if (empty($produtosIds)) {
                $produtos = [];
            } else {
                // Buscar dados completos dos produtos únicos
                $ids = array_column($produtosIds, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $produtos = $db->fetchAll(
                    "SELECT p.*, c.nome as categoria_nome 
                     FROM produtos p 
                     LEFT JOIN categorias c ON p.categoria_id = c.id 
                     WHERE p.id IN ($placeholders)
                     ORDER BY c.nome, p.nome",
                    $ids
                );
            }
            
            // Remover produtos duplicados por ID (caso haja duplicação na query)
            // Usar array associativo indexado por ID para garantir unicidade
            $produtosUnicos = [];
            foreach ($produtos as $produto) {
                $produtoId = (int)$produto['id']; // Garantir que ID seja sempre inteiro
                if (!isset($produtosUnicos[$produtoId])) {
                    $produtosUnicos[$produtoId] = $produto;
                }
            }
            // Converter de volta para array indexado numericamente
            $produtos = array_values($produtosUnicos);
            
            // Buscar ingredientes para cada produto
            // IMPORTANTE: Não usar referência (&) para evitar problemas de duplicação
            foreach ($produtos as $key => $produto) {
                try {
                    $ingredientes = $db->fetchAll(
                        "SELECT i.id, i.nome, i.tipo, i.preco_adicional, COALESCE(pi.padrao, true) as padrao
                         FROM ingredientes i
                         INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id
                         WHERE pi.produto_id = ? AND COALESCE(i.disponivel, true) = true
                         ORDER BY i.tipo, i.nome",
                        [$produto['id']]
                    );
                    $produtos[$key]['ingredientes'] = $ingredientes;
                } catch (\Exception $e) {
                    $produtos[$key]['ingredientes'] = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'produtos' => $produtos
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
