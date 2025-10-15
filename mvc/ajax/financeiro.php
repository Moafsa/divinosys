<?php
session_start();
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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new \Exception('No action specified');
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    $userId = $session->get('user_id') ?? 1;
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    switch ($action) {
        
        case 'buscar_pedidos_fiado':
            // Buscar pedidos que tenham pagamentos FIADO (incluindo quitados)
            $pedidos = $db->fetchAll(
                "SELECT DISTINCT p.idpedido, p.data, p.hora_pedido, p.cliente, p.telefone_cliente, 
                        p.idmesa, p.valor_total, p.status_pagamento, p.status, p.observacao,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_nao_fiado,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_fiado,
                        (SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago,
                        (p.valor_total - COALESCE((SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id), 0)) as saldo_devedor_real
                 FROM pedido p
                 WHERE EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?
                 ORDER BY p.data DESC, p.hora_pedido DESC",
                [$tenantId, $filialId, $tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);
            break;
            
        case 'buscar_total_recebiveis_fiado':
            // Buscar total de TODOS os valores FIADO (incluindo quitados)
            $resultado = $db->fetch(
                "SELECT COALESCE(SUM(
                    CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END
                ), 0) as total_recebiveis
                 FROM pagamentos_pedido pp
                 INNER JOIN pedido p ON pp.pedido_id = p.idpedido
                 WHERE pp.forma_pagamento = 'FIADO'
                 AND pp.tenant_id = ? 
                 AND pp.filial_id = ?
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?",
                [$tenantId, $filialId, $tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'total_recebiveis' => $resultado['total_recebiveis'] ?? 0
            ]);
            break;
            
        case 'quitar_pedido_fiado':
            $pedidoId = $_POST['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('Pedido ID é obrigatório');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Verificar se o pedido existe e tem pagamentos FIADO
                $pedido = $db->fetch(
                    "SELECT * FROM pedido 
                     WHERE idpedido = ? 
                     AND EXISTS (
                         SELECT 1 FROM pagamentos_pedido pp 
                         WHERE pp.pedido_id = pedido.idpedido 
                         AND pp.forma_pagamento = 'FIADO'
                         AND pp.tenant_id = ? 
                         AND pp.filial_id = ?
                     )
                     AND tenant_id = ? 
                     AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido fiado não encontrado');
                }
                
                // Calcular valores reais (excluindo fiado)
                $totalPagoReal = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $valorPagoReal = $totalPagoReal['total'] ?? 0;
                $saldoDevedorReal = $pedido['valor_total'] - $valorPagoReal;
                
                // Atualizar status do pagamento - marcar como quitado
                $db->update(
                    'pedido',
                    [
                        'status_pagamento' => 'quitado', // Marcar como quitado
                        'valor_pago' => $pedido['valor_total'], // Valor total incluindo fiado
                        'saldo_devedor' => 0 // Saldo zerado (fiado foi quitado)
                    ],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Registrar a quitação do fiado como um pagamento adicional
                $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $saldoDevedorReal, // Apenas o valor que estava fiado
                    'forma_pagamento' => 'Quitação Fiado',
                    'nome_cliente' => $pedido['cliente'],
                    'telefone_cliente' => $pedido['telefone_cliente'],
                    'descricao' => 'Pedido fiado quitado',
                    'usuario_id' => $userId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido fiado quitado com sucesso!',
                    'pedido_id' => $pedidoId
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        default:
            throw new \Exception('Action not implemented: ' . $action);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>