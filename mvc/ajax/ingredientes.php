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
        $action = 'buscar_ingrediente';
    } elseif ($excluir == '1') {
        $action = 'excluir_ingrediente';
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
        case 'criar_ingrediente':
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $preco_adicional = (float)($_POST['preco_adicional'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome do ingrediente é obrigatório');
            }
            
            $ingredienteId = $db->insert('ingredientes', [
                'nome' => $nome,
                'descricao' => $descricao,
                'preco_adicional' => $preco_adicional,
                'ativo' => $ativo,
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Ingrediente criado com sucesso!',
                'ingrediente_id' => $ingredienteId
            ]);
            break;
            
        case 'atualizar_ingrediente':
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $preco_adicional = (float)($_POST['preco_adicional'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            if (empty($nome)) {
                throw new \Exception('Nome do ingrediente é obrigatório');
            }
            
            if ($id <= 0) {
                throw new \Exception('ID do ingrediente inválido');
            }
            
            // Check if ingredient exists
            $ingrediente = $db->fetch(
                'SELECT * FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$ingrediente) {
                throw new \Exception('Ingrediente não encontrado');
            }
            
            $db->update(
                'ingredientes',
                [
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'preco_adicional' => $preco_adicional,
                    'ativo' => $ativo
                ],
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Ingrediente atualizado com sucesso!'
            ]);
            break;
            
        case 'buscar_ingrediente':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new \Exception('ID do ingrediente inválido');
            }
            
            $ingrediente = $db->fetch(
                'SELECT * FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$ingrediente) {
                throw new \Exception('Ingrediente não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'ingrediente' => $ingrediente
            ]);
            break;
            
        case 'excluir_ingrediente':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new \Exception('ID do ingrediente inválido');
            }
            
            // Check if ingredient exists
            $ingrediente = $db->fetch(
                'SELECT * FROM ingredientes WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            if (!$ingrediente) {
                throw new \Exception('Ingrediente não encontrado');
            }
            
            // Check if ingredient is used in products
            $produtos = $db->fetch(
                'SELECT COUNT(*) as count FROM produto_ingredientes WHERE ingrediente_id = ?',
                [$id]
            );
            
            if ($produtos['count'] > 0) {
                throw new \Exception('Não é possível excluir ingrediente que está sendo usado em produtos');
            }
            
            $db->delete(
                'ingredientes',
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$id, $tenant['id'], $filial['id']]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Ingrediente excluído com sucesso!'
            ]);
            break;
            
        case 'listar_ingredientes':
            $ingredientes = $db->fetchAll(
                'SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome ASC',
                [$tenant['id'], $filial['id']]
            );
            
            echo json_encode([
                'success' => true,
                'ingredientes' => $ingredientes
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
