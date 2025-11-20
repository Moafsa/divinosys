<?php
/**
 * API endpoint for online menu product search and customization
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    // Get action from URL or POST - handle both buscar_produto_cardapio and buscar_produto
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    // If action is buscar_produto_cardapio, map to buscar_produto
    if ($action === 'buscar_produto_cardapio') {
        $action = 'buscar_produto';
    }
    
    $tenantId = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? null;
    $filialId = $_GET['filial_id'] ?? $_POST['filial_id'] ?? null;
    
    if (!$tenantId || !$filialId) {
        throw new Exception('Tenant ID e Filial ID são obrigatórios');
    }
    
    switch ($action) {
        case 'buscar_produto':
            $produtoId = $_GET['produto_id'] ?? $_POST['produto_id'] ?? null;
            
            if (!$produtoId) {
                throw new Exception('ID do produto é obrigatório');
            }
            
            // Get product
            $produto = $db->fetch(
                "SELECT p.*, c.nome as categoria_nome 
                 FROM produtos p 
                 LEFT JOIN categorias c ON p.categoria_id = c.id 
                 WHERE p.id = ? AND p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true",
                [$produtoId, $tenantId, $filialId]
            );
            
            if (!$produto) {
                throw new Exception('Produto não encontrado');
            }
            
            // Get product ingredients
            $ingredientes = $db->fetchAll(
                "SELECT i.id, i.nome, i.tipo, i.preco_adicional, COALESCE(pi.padrao, true) as padrao
                 FROM ingredientes i
                 INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id
                 WHERE pi.produto_id = ? AND COALESCE(i.disponivel, true) = true
                 ORDER BY i.tipo, i.nome",
                [$produtoId]
            );
            
            // Get all available ingredients for customization
            $todosIngredientes = $db->fetchAll(
                "SELECT * FROM ingredientes 
                 WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) AND COALESCE(disponivel, true) = true
                 ORDER BY nome",
                [$tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'produto' => $produto,
                'ingredientes' => $ingredientes,
                'todos_ingredientes' => $todosIngredientes
            ]);
            break;
            
        case 'buscar_produtos':
            $searchTerm = $_GET['search'] ?? $_POST['search'] ?? '';
            
            $query = "SELECT p.*, c.nome as categoria_nome 
                      FROM produtos p 
                      LEFT JOIN categorias c ON p.categoria_id = c.id 
                      WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true";
            $params = [$tenantId, $filialId];
            
            if (!empty($searchTerm)) {
                $query .= " AND (p.nome ILIKE ? OR p.descricao ILIKE ? OR c.nome ILIKE ?)";
                $searchPattern = '%' . $searchTerm . '%';
                $params[] = $searchPattern;
                $params[] = $searchPattern;
                $params[] = $searchPattern;
            }
            
            $query .= " ORDER BY c.nome, p.nome LIMIT 50";
            
            $produtos = $db->fetchAll($query, $params);
            
            echo json_encode([
                'success' => true,
                'produtos' => $produtos
            ]);
            break;
            
        default:
            throw new Exception('Ação não encontrada');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

