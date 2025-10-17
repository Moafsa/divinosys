<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    $user = $session->getUser();
    
    if (!$tenant || !$filial || !$user) {
        throw new Exception('Sessão inválida');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['mesa']) || !isset($input['itens'])) {
        throw new Exception('Dados inválidos');
    }
    
    $mesa = $input['mesa'];
    $itens = $input['itens'];
    
    if (empty($itens)) {
        throw new Exception('Carrinho vazio');
    }
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        // Criar pedido
        $pedidoId = $db->insert('pedido', [
            'tenant_id' => $tenant['id'],
            'filial_id' => $filial['id'],
            'idmesa' => $mesa === 'delivery' ? '999' : $mesa,
            'status' => 'aberto',
            'total' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $user['id']
        ]);
        
        $totalPedido = 0;
        
        // Adicionar itens
        foreach ($itens as $item) {
            $subtotal = $item['preco'] * $item['quantidade'];
            $totalPedido += $subtotal;
            
            $db->insert('pedido_itens', [
                'pedido_id' => $pedidoId,
                'produto_id' => $item['produtoId'],
                'quantidade' => $item['quantidade'],
                'preco_unitario' => $item['preco'],
                'subtotal' => $subtotal
            ]);
        }
        
        // Atualizar total do pedido
        $db->update('pedido', ['total' => $totalPedido], ['idpedido' => $pedidoId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'pedido_id' => $pedidoId,
            'total' => $totalPedido
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
