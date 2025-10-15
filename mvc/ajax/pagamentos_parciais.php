<?php
/**
 * Partial Payment Handler
 * Handles partial payment operations for orders
 */

require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    error_log('PAGAMENTOS_PARCIAIS DEBUG: Action recebida: ' . $action);
    error_log('PAGAMENTOS_PARCIAIS DEBUG: POST data: ' . print_r($_POST, true));
    error_log('PAGAMENTOS_PARCIAIS DEBUG: GET data: ' . print_r($_GET, true));
    
    if (empty($action)) {
        throw new \Exception('No action specified');
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Check if user is authenticated
    if (!$session->get('user_id')) {
        throw new \Exception('User not authenticated');
    }
    
    $userId = $session->get('user_id');
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    switch ($action) {
        
        case 'consultar_saldo_pedido':
            $pedidoId = $_POST['pedido_id'] ?? $_GET['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('Order ID is required');
            }
            
            // Fetch order details
            $pedido = $db->fetch(
                "SELECT idpedido, valor_total, valor_pago, saldo_devedor, status_pagamento, 
                        status, cliente, telefone_cliente, idmesa
                 FROM pedido 
                 WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Order not found');
            }
            
            // Fetch payment history
            $pagamentos = $db->fetchAll(
                "SELECT id, valor_pago, forma_pagamento, nome_cliente, telefone_cliente, 
                        descricao, troco_para, troco_devolver, created_at
                 FROM pagamentos_pedido 
                 WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?
                 ORDER BY created_at DESC",
                [$pedidoId, $tenantId, $filialId]
            );
            
            // Calculate totals
            $valorTotal = (float) $pedido['valor_total'];
            $valorPago = (float) ($pedido['valor_pago'] ?? 0);
            $saldoDevedor = (float) ($pedido['saldo_devedor'] ?? 0);
            
            echo json_encode([
                'success' => true,
                'pedido' => [
                    'id' => $pedido['idpedido'],
                    'valor_total' => $valorTotal,
                    'valor_pago' => $valorPago,
                    'saldo_devedor' => $saldoDevedor,
                    'status_pagamento' => $pedido['status_pagamento'] ?? 'pendente',
                    'status' => $pedido['status'],
                    'cliente' => $pedido['cliente'],
                    'telefone_cliente' => $pedido['telefone_cliente'],
                    'mesa_id' => $pedido['idmesa']
                ],
                'pagamentos' => $pagamentos,
                'total_pagamentos' => count($pagamentos)
            ]);
            break;
            
        case 'registrar_pagamento_parcial':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $valorPago = (float) ($_POST['valor_pago'] ?? 0);
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $trocoPara = (float) ($_POST['troco_para'] ?? 0);
            
            // Validation
            if (empty($pedidoId)) {
                throw new \Exception('Order ID is required');
            }
            
            if ($valorPago <= 0) {
                throw new \Exception('Payment amount must be greater than zero');
            }
            
            if (empty($formaPagamento)) {
                throw new \Exception('Payment method is required');
            }
            
            // Validate FIADO payment requirements
            if ($formaPagamento === 'FIADO') {
                if (empty($nomeCliente)) {
                    throw new \Exception('Nome do cliente é obrigatório para pagamento fiado');
                }
                if (empty($telefoneCliente)) {
                    throw new \Exception('Telefone do cliente é obrigatório para pagamento fiado');
                }
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Fetch order
                $pedido = $db->fetch(
                    "SELECT idpedido, valor_total, valor_pago, saldo_devedor, status, idmesa
                     FROM pedido 
                     WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Order not found');
                }
                
                // Check if order is already finalized or cancelled
                if (in_array($pedido['status'], ['Finalizado', 'Cancelado'])) {
                    throw new \Exception('Cannot add payment to a finalized or cancelled order');
                }
                
                // Calculate values
                $valorTotal = (float) $pedido['valor_total'];
                $valorPagoAnterior = (float) ($pedido['valor_pago'] ?? 0);
                $valorPagoNovo = $valorPagoAnterior + $valorPago;
                $saldoDevedor = $valorTotal - $valorPagoNovo;
                
                // Check if payment exceeds remaining balance
                if ($valorPagoNovo > $valorTotal) {
                    throw new \Exception(sprintf(
                        'Payment amount (R$ %.2f) exceeds remaining balance (R$ %.2f)',
                        $valorPago,
                        $valorTotal - $valorPagoAnterior
                    ));
                }
                
                // Calculate change if paying with cash
                $trocoDevolver = 0;
                if ($trocoPara > 0 && $formaPagamento === 'Dinheiro') {
                    $saldoAtual = $valorTotal - $valorPagoAnterior;
                    $trocoDevolver = $trocoPara - $saldoAtual;
                    if ($trocoDevolver < 0) {
                        throw new \Exception('Change amount is less than the payment amount');
                    }
                }
                
                // Determine payment status
                $statusPagamento = 'parcial';
                if ($saldoDevedor <= 0) {
                    $statusPagamento = 'quitado';
                } else if ($valorPagoNovo == 0) {
                    $statusPagamento = 'pendente';
                }
                
                // FIADO is treated as a normal payment - it counts towards the total
                // No special handling needed - FIADO counts as payment
                
                // Insert payment record
                $pagamentoId = $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorPago,
                    'forma_pagamento' => $formaPagamento,
                    'nome_cliente' => $nomeCliente ?: null,
                    'telefone_cliente' => $telefoneCliente ?: null,
                    'descricao' => $descricao ?: null,
                    'troco_para' => $trocoPara > 0 ? $trocoPara : null,
                    'troco_devolver' => $trocoDevolver > 0 ? $trocoDevolver : null,
                    'usuario_id' => $userId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
                
                // Update order payment status
                $updateData = [
                    'valor_pago' => $valorPagoNovo,
                    'saldo_devedor' => max(0, $saldoDevedor),
                    'status_pagamento' => $statusPagamento
                ];
                
                // If fully paid, update order status and client info
                if ($statusPagamento === 'quitado') {
                    $updateData['status'] = 'Finalizado';
                    
                    // Update client info if provided
                    if ($nomeCliente) {
                        $updateData['cliente'] = $nomeCliente;
                    }
                    if ($telefoneCliente) {
                        $updateData['telefone_cliente'] = $telefoneCliente;
                    }
                    
                    // Free the table if not delivery AND saldo_devedor is zero
                    if ($pedido['idmesa'] && $pedido['idmesa'] != '999' && $saldoDevedor <= 0) {
                        // Check if there are other open orders for this table
                        $pedidosAbertos = $db->fetch(
                            "SELECT COUNT(*) as count 
                             FROM pedido 
                             WHERE idmesa = ? 
                             AND idpedido != ? 
                             AND status NOT IN ('Finalizado', 'Cancelado')
                             AND tenant_id = ? 
                             AND filial_id = ?",
                            [$pedido['idmesa'], $pedidoId, $tenantId, $filialId]
                        );
                        
                        // Only free table if no other open orders
                        if ($pedidosAbertos['count'] == 0) {
                            $db->update(
                                'mesas',
                                ['status' => '1'],
                                'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                                [$pedido['idmesa'], $tenantId, $filialId]
                            );
                        }
                    }
                }
                
                // For FIADO payments, still update client info but don't change table status
                if ($formaPagamento === 'FIADO') {
                    // Update client info (mandatory for FIADO)
                    if ($nomeCliente) {
                        $updateData['cliente'] = $nomeCliente;
                    }
                    if ($telefoneCliente) {
                        $updateData['telefone_cliente'] = $telefoneCliente;
                    }
                }
                
                // Update order
                $db->update(
                    'pedido',
                    $updateData,
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => $statusPagamento === 'quitado' 
                        ? 'Order fully paid and closed!' 
                        : sprintf('Partial payment registered. Remaining: R$ %.2f', max(0, $saldoDevedor)),
                    'pagamento_id' => $pagamentoId,
                    'valor_pago' => $valorPago,
                    'valor_total_pago' => $valorPagoNovo,
                    'saldo_devedor' => max(0, $saldoDevedor),
                    'status_pagamento' => $statusPagamento,
                    'troco_devolver' => $trocoDevolver,
                    'pedido_fechado' => $statusPagamento === 'quitado'
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'cancelar_pagamento':
            $pagamentoId = $_POST['pagamento_id'] ?? '';
            
            if (empty($pagamentoId)) {
                throw new \Exception('Payment ID is required');
            }
            
            $db->beginTransaction();
            
            try {
                // Fetch payment
                $pagamento = $db->fetch(
                    "SELECT * FROM pagamentos_pedido 
                     WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pagamentoId, $tenantId, $filialId]
                );
                
                if (!$pagamento) {
                    throw new \Exception('Payment not found');
                }
                
                // Fetch order
                $pedido = $db->fetch(
                    "SELECT idpedido, valor_total, valor_pago, status 
                     FROM pedido 
                     WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pagamento['pedido_id'], $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Order not found');
                }
                
                // Calculate new values
                $valorPagoNovo = (float) $pedido['valor_pago'] - (float) $pagamento['valor_pago'];
                $saldoDevedor = (float) $pedido['valor_total'] - $valorPagoNovo;
                
                // Determine new payment status
                $statusPagamento = 'parcial';
                if ($saldoDevedor >= $pedido['valor_total']) {
                    $statusPagamento = 'pendente';
                } else if ($saldoDevedor <= 0) {
                    $statusPagamento = 'quitado';
                }
                
                // Update order
                $db->update(
                    'pedido',
                    [
                        'valor_pago' => max(0, $valorPagoNovo),
                        'saldo_devedor' => max(0, $saldoDevedor),
                        'status_pagamento' => $statusPagamento,
                        'status' => $statusPagamento === 'quitado' ? 'Finalizado' : 'Pendente'
                    ],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pagamento['pedido_id'], $tenantId, $filialId]
                );
                
                // Delete payment record
                $db->delete(
                    'pagamentos_pedido',
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$pagamentoId, $tenantId, $filialId]
                );
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment cancelled successfully',
                    'valor_pago' => max(0, $valorPagoNovo),
                    'saldo_devedor' => max(0, $saldoDevedor),
                    'status_pagamento' => $statusPagamento
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'consultar_saldo_mesa':
            $mesaId = $_POST['mesa_id'] ?? $_GET['mesa_id'] ?? null;

            error_log('PAGAMENTOS_PARCIAIS: consultar_saldo_mesa - mesaId: ' . $mesaId . ', tenantId: ' . $tenantId . ', filialId: ' . $filialId);

            if (!$mesaId) {
                throw new \Exception('ID da mesa é obrigatório.');
            }

            // Verificar se há pedidos na mesa
            $pedidosMesa = $db->fetchAll(
                "SELECT idpedido, valor_total, saldo_devedor, status, status_pagamento FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );

            // Não corrigir automaticamente - deixar os dados como estão
            
            error_log('PAGAMENTOS_PARCIAIS: Pedidos encontrados na mesa: ' . json_encode($pedidosMesa));

            // Calcular o saldo devedor total da mesa - FIADO conta como pagamento normal
            $saldoDevedorMesa = 0;
            $valorTotalMesa = 0;
            
            foreach ($pedidosMesa as $pedido) {
                if (!in_array($pedido['status'], ['Finalizado', 'Cancelado'])) {
                    $valorTotalMesa += (float)($pedido['valor_total'] ?? 0);
                    
                    // Usar o saldo_devedor do banco que já considera todos os pagamentos (incluindo FIADO)
                    $saldoDevedorMesa += (float)($pedido['saldo_devedor'] ?? 0);
                }
            }

            error_log('PAGAMENTOS_PARCIAIS: consultar_saldo_mesa - saldoDevedorMesa: ' . $saldoDevedorMesa . ', valorTotalMesa: ' . $valorTotalMesa);

            echo json_encode(['success' => true, 'saldo_devedor_mesa' => (float)$saldoDevedorMesa, 'valor_total_mesa' => (float)$valorTotalMesa]);
            break;

        case 'registrar_pagamento_mesa':
            $mesaId = $_POST['mesa_id'] ?? null;
            $formaPagamento = $_POST['forma_pagamento'] ?? null;
            $valorPago = $_POST['valor_pago'] ?? null;
            $nomeCliente = $_POST['nome_cliente'] ?? null;
            $telefoneCliente = $_POST['telefone_cliente'] ?? null;
            $descricao = $_POST['descricao'] ?? null;

            if (!$mesaId || !$formaPagamento || $valorPago === null) {
                throw new \Exception('Dados incompletos para registrar pagamento da mesa.');
            }
            
            // Validate FIADO payment requirements for mesa
            if ($formaPagamento === 'FIADO') {
                if (empty($nomeCliente)) {
                    throw new \Exception('Nome do cliente é obrigatório para pagamento fiado');
                }
                if (empty($telefoneCliente)) {
                    throw new \Exception('Telefone do cliente é obrigatório para pagamento fiado');
                }
            }

            $db->beginTransaction();

            // Obter todos os pedidos ativos da mesa com saldo devedor
            $pedidosMesa = $db->fetchAll(
                "SELECT idpedido, saldo_devedor, valor_total, status_pagamento FROM pedido WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado') AND status_pagamento != 'quitado' AND tenant_id = ? AND filial_id = ? ORDER BY created_at ASC",
                [$mesaId, $tenantId, $filialId]
            );

            $valorRestanteAPagar = (float)$valorPago;
            $totalSaldoDevedorMesa = 0;
            foreach ($pedidosMesa as $p) {
                // Usar saldo_devedor do banco de dados (que já está correto)
                $totalSaldoDevedorMesa += (float)($p['saldo_devedor'] ?? 0);
            }

            if ($valorRestanteAPagar > $totalSaldoDevedorMesa + 0.01) { // Tolerância
                $db->rollBack();
                throw new \Exception('Valor pago não pode ser maior que o saldo devedor total da mesa.');
            }

            foreach ($pedidosMesa as $pedido) {
                $pedidoId = $pedido['idpedido'];
                
                // Usar saldo_devedor do banco de dados (que já está correto)
                $saldoDevedorPedido = (float)($pedido['saldo_devedor'] ?? 0);

                if ($valorRestanteAPagar <= 0) {
                    break; // Não há mais valor para pagar
                }

                $valorAPagarNestePedido = min($valorRestanteAPagar, $saldoDevedorPedido);

                // Registrar o pagamento na tabela pagamentos_pedido
                $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorAPagarNestePedido,
                    'forma_pagamento' => $formaPagamento,
                    'nome_cliente' => $nomeCliente,
                    'telefone_cliente' => $telefoneCliente,
                    'descricao' => $descricao,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);

                // Calcular o novo valor pago total para este pedido
                $totalPagoResult = $db->fetch(
                    "SELECT COALESCE(SUM(valor_pago), 0) as total FROM pagamentos_pedido WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $totalPagoPedido = $totalPagoResult['total'] ?? 0;

                // Buscar o valor total do pedido
                $pedidoResult = $db->fetch(
                    "SELECT valor_total FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $valorTotalPedido = $pedidoResult['valor_total'] ?? 0;

                // Calcular novo saldo devedor
                $novoSaldoDevedor = $valorTotalPedido - $totalPagoPedido;

                // Determinar novo status de pagamento
                $novoStatusPagamento = 'pendente';
                if ($novoSaldoDevedor <= 0.01) {
                    $novoStatusPagamento = 'quitado';
                } elseif ($totalPagoPedido > 0) {
                    $novoStatusPagamento = 'parcial';
                }

                // Atualizar o pedido com os novos valores
                $db->query(
                    "UPDATE pedido SET valor_pago = ?, saldo_devedor = ?, status_pagamento = ?, updated_at = NOW() WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$totalPagoPedido, $novoSaldoDevedor, $novoStatusPagamento, $pedidoId, $tenantId, $filialId]
                );

                $valorRestanteAPagar -= $valorAPagarNestePedido;
            }

            // Após processar todos os pagamentos, verificar se a mesa pode ser liberada
            $pedidosPendentesAposPagamento = 0;
            $pedidosAtualizados = $db->fetchAll(
                "SELECT idpedido, saldo_devedor, status, status_pagamento FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            foreach ($pedidosAtualizados as $pedido) {
                // Se o pedido não está finalizado/cancelado E o status de pagamento não é 'quitado', ele ainda está pendente
                if (!in_array($pedido['status'], ['Finalizado', 'Cancelado']) && $pedido['status_pagamento'] != 'quitado') {
                    $pedidosPendentesAposPagamento++;
                }
            }

            $mesaLiberada = false;
            if ($pedidosPendentesAposPagamento == 0) {
                $db->query(
                    "UPDATE mesas SET status = 'livre' WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
                    [$mesaId, $tenantId, $filialId]
                );
                $mesaLiberada = true;
            }

            // Calcular saldo restante - buscar dados atualizados
            $pedidosAtualizadosCompleto = $db->fetchAll(
                "SELECT idpedido, valor_total, saldo_devedor, status_pagamento, status FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            $saldoRestante = 0;
            foreach ($pedidosAtualizadosCompleto as $pedido) {
                if (!in_array($pedido['status'], ['Finalizado', 'Cancelado']) && $pedido['status_pagamento'] != 'quitado') {
                    // Usar saldo_devedor do banco de dados (que já está correto)
                    $saldoRestante += (float)($pedido['saldo_devedor'] ?? 0);
                }
            }

            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'mesa_liberada' => $mesaLiberada,
                'saldo_restante' => (float)$saldoRestante
            ]);
            break;

        default:
            throw new \Exception('Invalid action: ' . $action);
    }
    
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

