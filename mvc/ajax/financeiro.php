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
    
    // Debug log
    error_log("Financeiro AJAX - Action: " . $action);
    
    if (empty($action)) {
        throw new \Exception('No action specified');
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    $userId = $session->get('user_id') ?? 1;
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    switch ($action) {
        
        case 'buscar_dados_pedido':
            $pedidoId = $_GET['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('Pedido ID é obrigatório');
            }
            
            // Buscar dados completos do pedido
            $pedido = $db->fetch(
                "SELECT DISTINCT p.idpedido, p.data, p.hora_pedido, p.cliente, p.telefone_cliente, 
                        p.idmesa, p.valor_total, p.status_pagamento, p.status, p.observacao,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_nao_fiado,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_fiado,
                        (SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago,
                        (p.valor_total - COALESCE((SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id), 0)) as saldo_devedor_real
                 FROM pedido p
                 WHERE p.idpedido = ? 
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?
                 AND EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )",
                [$pedidoId, $tenantId, $filialId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido fiado não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'pedido' => $pedido
            ]);
            break;
        
        case 'buscar_pedidos_fiado':
            // Buscar pedidos que tenham valores FIADO pendentes (não quitados)
            $pedidos = $db->fetchAll(
                "SELECT DISTINCT p.idpedido, p.data, p.hora_pedido, p.cliente, p.telefone_cliente, 
                        p.idmesa, p.valor_total, p.status_pagamento, p.status, p.observacao,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_nao_fiado,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_fiado,
                        (SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as saldo_fiado_pendente
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
            
            // Filtrar apenas pedidos com saldo fiado real pendente
            $pedidosFiltrados = [];
            foreach ($pedidos as $pedido) {
                // Calcular saldo fiado real (fiado original - fiado quitado)
                $fiadoOriginal = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $fiadoQuitado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?
                     AND descricao LIKE '%fiado%'",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $saldoFiadoReal = ($fiadoOriginal['total'] ?? 0) - ($fiadoQuitado['total'] ?? 0);
                
                if ($saldoFiadoReal > 0.01) {
                    $pedido['saldo_fiado_pendente'] = $saldoFiadoReal;
                    $pedidosFiltrados[] = $pedido;
                }
            }
            
            $pedidos = $pedidosFiltrados;
            
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);
            break;
            
        case 'buscar_total_recebiveis_fiado':
            // Calcular saldo fiado real (fiado original - fiado quitado)
            $totalRecebiveis = 0;
            $pedidos = $db->fetchAll(
                "SELECT DISTINCT p.idpedido FROM pedido p
                 WHERE EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?",
                [$tenantId, $filialId, $tenantId, $filialId]
            );
            
            foreach ($pedidos as $pedido) {
                // Calcular saldo fiado real (fiado original - fiado quitado)
                $fiadoOriginal = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $fiadoQuitado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?
                     AND descricao LIKE '%fiado%'",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $saldoFiadoReal = ($fiadoOriginal['total'] ?? 0) - ($fiadoQuitado['total'] ?? 0);
                
                if ($saldoFiadoReal > 0.01) {
                    $totalRecebiveis += $saldoFiadoReal;
                }
            }
            
            $resultado = ['total_recebiveis' => $totalRecebiveis];
            
            echo json_encode([
                'success' => true,
                'total_recebiveis' => $resultado['total_recebiveis'] ?? 0
            ]);
            break;
            
        case 'excluir_pedido_fiado':
            $pedidoId = $_POST['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido não informado');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Verificar se o pedido existe e pertence ao tenant/filial
                $pedido = $db->fetch(
                    "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }
                
                // Verificar se o pedido tem pagamentos fiado
                $temFiado = $db->fetch(
                    "SELECT COUNT(*) as count FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if ($temFiado['count'] == 0) {
                    throw new \Exception('Este pedido não possui pagamentos fiado');
                }
                
                // Excluir todos os pagamentos do pedido
                $db->delete(
                    'pagamentos_pedido',
                    'pedido_id = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Excluir itens do pedido
                $db->delete(
                    'pedido_itens',
                    'pedido_id = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Excluir o pedido
                $db->delete(
                    'pedido',
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Commit transaction
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido excluído com sucesso!'
                ]);
                
            } catch (\Exception $e) {
                // Rollback transaction
                $db->rollback();
                throw $e;
            }
            break;
            

        case 'registrar_pagamento_fiado':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $valorPago = $_POST['valor_pago'] ?? 0;
            $descricao = $_POST['descricao'] ?? '';
            
            if (empty($pedidoId) || empty($formaPagamento) || $valorPago <= 0) {
                throw new \Exception('Dados incompletos para registrar pagamento');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Buscar dados do pedido
                $pedido = $db->fetch(
                    "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }
                
                // Calcular valor fiado pendente
                $totalFiado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $valorFiadoPendente = $totalFiado['total'] ?? 0;
                
                // Verificar se o valor não excede o saldo fiado
                if ($valorPago > $valorFiadoPendente + 0.01) {
                    throw new \Exception('Valor não pode ser maior que o saldo fiado pendente');
                }
                
                // Registrar o pagamento
                $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorPago,
                    'forma_pagamento' => $formaPagamento,
                    'nome_cliente' => $pedido['cliente'],
                    'telefone_cliente' => $pedido['telefone_cliente'],
                    'descricao' => $descricao ?: 'Pagamento de pedido fiado',
                    'usuario_id' => $userId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
                
                // Recalcular totais após o pagamento (apenas pagamentos não-fiado)
                $novoTotalPagoNaoFiado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $novoValorPagoNaoFiado = $novoTotalPagoNaoFiado['total'] ?? 0;
                $novoSaldoDevedor = $pedido['valor_total'] - $novoValorPagoNaoFiado;
                
                // Determinar novo status (baseado no saldo total)
                $novoStatusPagamento = 'pendente';
                if ($novoSaldoDevedor <= 0.01) {
                    $novoStatusPagamento = 'quitado';
                } elseif ($novoValorPagoNaoFiado > 0) {
                    $novoStatusPagamento = 'parcial';
                }
                
                // Atualizar pedido
                $db->update(
                    'pedido',
                    [
                        'valor_pago' => $novoValorPagoNaoFiado,
                        'saldo_devedor' => $novoSaldoDevedor,
                        'status_pagamento' => $novoStatusPagamento,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Se totalmente quitado, liberar mesa se necessário
                $mesaLiberada = false;
                if ($novoStatusPagamento === 'quitado') {
                    // Verificar se todos os pedidos da mesa estão quitados
                    $pedidosPendentes = $db->fetch(
                        "SELECT COUNT(*) as total FROM pedido 
                         WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado') 
                         AND status_pagamento != 'quitado' AND tenant_id = ? AND filial_id = ?",
                        [$pedido['idmesa'], $tenantId, $filialId]
                    );
                    
                    if (($pedidosPendentes['total'] ?? 0) === 0) {
                        $db->update(
                            'mesas',
                            ['status' => 'livre'],
                            'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                            [$pedido['idmesa'], $tenantId, $filialId]
                        );
                        $mesaLiberada = true;
                    }
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pagamento registrado com sucesso!',
                    'pedido_id' => $pedidoId,
                    'pedido_quitado' => $novoStatusPagamento === 'quitado',
                    'mesa_liberada' => $mesaLiberada,
                    'saldo_restante' => $novoSaldoDevedor
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
