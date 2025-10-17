<?php
// Incluir configuração do sistema
require_once __DIR__ . '/../config/database.php';

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
    // Conectar ao banco
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
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
    $pdo->beginTransaction();
    
    try {
        // Criar pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedido (tenant_id, filial_id, idmesa, status, total, created_at, user_id) 
            VALUES (1, 1, ?, 'aberto', 0, NOW(), 1)
            RETURNING idpedido
        ");
        $stmt->execute([$mesa === 'delivery' ? '999' : $mesa]);
        $pedidoId = $stmt->fetchColumn();
        
        $totalPedido = 0;
        
        // Adicionar itens
        foreach ($itens as $item) {
            $subtotal = $item['preco'] * $item['quantidade'];
            $totalPedido += $subtotal;
            
            $stmt = $pdo->prepare("
                INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pedidoId,
                $item['produtoId'],
                $item['quantidade'],
                $item['preco'],
                $subtotal
            ]);
        }
        
        // Atualizar total do pedido
        $stmt = $pdo->prepare("UPDATE pedido SET total = ? WHERE idpedido = ?");
        $stmt->execute([$totalPedido, $pedidoId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'pedido_id' => $pedidoId,
            'total' => $totalPedido
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
