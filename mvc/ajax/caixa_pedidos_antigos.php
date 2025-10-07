<?php
session_start();
require_once '../../system/Database.php';
require_once '../../system/Utils.php';

// Configurar headers para JSON
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
$acao = $_POST['acao'] ?? '';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    switch ($acao) {
        case 'listar_pedidos_antigos':
            listarPedidosAntigos($pdo, $tenantId, $filialId);
            break;
            
        case 'obter_pedido':
            obterPedido($pdo, $tenantId, $filialId);
            break;
            
        case 'finalizar_pedido_antigo':
            finalizarPedidoAntigo($pdo, $tenantId, $filialId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em caixa_pedidos_antigos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function listarPedidosAntigos($pdo, $tenantId, $filialId) {
    $sql = "
        SELECT 
            p.idpedido,
            p.idmesa,
            p.status,
            p.valor_total,
            p.cliente_nome,
            p.created_at,
            m.numero as mesa_numero,
            EXTRACT(EPOCH FROM (NOW() - p.created_at))/3600 as idade_horas
        FROM pedido p
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.tenant_id = ? AND p.filial_id = ?
        AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND p.created_at <= NOW() - INTERVAL '2 hours'
        ORDER BY p.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular resumo
    $totalPedidos = count($pedidos);
    $valorTotal = array_sum(array_column($pedidos, 'valor_total'));
    $mesasAfetadas = count(array_unique(array_column($pedidos, 'mesa_numero')));
    $maisAntigo = $totalPedidos > 0 ? max(array_column($pedidos, 'idade_horas')) : 0;
    
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'resumo' => [
            'total_pedidos' => $totalPedidos,
            'valor_total' => $valorTotal,
            'mesas_afetadas' => $mesasAfetadas,
            'mais_antigo' => round($maisAntigo)
        ]
    ]);
}

function obterPedido($pdo, $tenantId, $filialId) {
    $pedidoId = $_POST['pedido_id'] ?? '';
    
    if (empty($pedidoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do pedido não fornecido']);
        return;
    }
    
    $sql = "
        SELECT 
            p.*,
            m.numero as mesa_numero
        FROM pedido p
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.idpedido = ? AND p.tenant_id = ? AND p.filial_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pedidoId, $tenantId, $filialId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        return;
    }
    
    echo json_encode(['success' => true, 'pedido' => $pedido]);
}

function finalizarPedidoAntigo($pdo, $tenantId, $filialId) {
    $pedidoId = $_POST['pedido_id'] ?? '';
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    $valorPago = floatval($_POST['valor_pago'] ?? 0);
    $troco = floatval($_POST['troco'] ?? 0);
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Validar dados
    if (empty($pedidoId) || empty($formaPagamento) || $valorPago <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // 1. Obter dados do pedido
        $pedido = $pdo->query("
            SELECT * FROM pedido 
            WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?
        ")->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            throw new Exception('Pedido não encontrado');
        }
        
        // 2. Registrar movimentação financeira
        $stmt = $pdo->prepare("
            INSERT INTO movimentacoes_financeiras (
                tenant_id, filial_id, tipo, categoria_id, descricao,
                valor, data_movimentacao, referencia_id, created_at
            ) VALUES (?, ?, 'entrada', 1, ?, ?, NOW(), ?, NOW())
        ");
        $stmt->execute([
            $tenantId, $filialId,
            "Pedido #{$pedidoId} finalizado pelo caixa - {$formaPagamento}",
            $pedido['valor_total'], $pedidoId
        ]);
        
        // 3. Finalizar pedido
        $stmt = $pdo->prepare("
            UPDATE pedido 
            SET 
                status = 'Finalizado',
                observacoes = ?,
                updated_at = NOW()
            WHERE idpedido = ?
        ");
        $observacoesCompletas = "Finalizado pelo caixa - {$formaPagamento} - Valor pago: R$ " . 
                               number_format($valorPago, 2, ',', '.') . 
                               ($troco > 0 ? " - Troco: R$ " . number_format($troco, 2, ',', '.') : '') . 
                               ($observacoes ? " - " . $observacoes : '');
        
        $stmt->execute([$observacoesCompletas, $pedidoId]);
        
        // 4. Registrar pagamento (se existir tabela de pagamentos)
        $stmt = $pdo->prepare("
            INSERT INTO venda_pagamentos (
                venda_id, forma_pagamento, valor, observacoes, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$pedidoId, $formaPagamento, $pedido['valor_total'], $observacoesCompletas]);
        
        // 5. Atualizar status da mesa
        atualizarStatusMesa($pdo, $pedido['idmesa'], $tenantId, $filialId);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pedido finalizado com sucesso! Receita registrada no sistema financeiro.'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function atualizarStatusMesa($pdo, $mesaId, $tenantId, $filialId) {
    // Verificar se há outros pedidos ativos para esta mesa
    $pedidosAtivos = $pdo->query("
        SELECT COUNT(*) as total
        FROM pedido 
        WHERE idmesa::varchar = ? 
        AND tenant_id = ? AND filial_id = ?
        AND status NOT IN ('Finalizado', 'Cancelado')
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    $novoStatus = $pedidosAtivos > 0 ? 'ocupada' : 'livre';
    
    // Atualizar status da mesa
    $stmt = $pdo->prepare("
        UPDATE mesas 
        SET status = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ? AND filial_id = ?
    ");
    $stmt->execute([$novoStatus, $mesaId, $tenantId, $filialId]);
}
?>
