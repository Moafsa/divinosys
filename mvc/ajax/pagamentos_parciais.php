<?php
/**
 * Partial Payment Handler
 * Handles partial payment operations for orders
 */

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../model/AsaasAPIClient.php';

header('Content-Type: application/json');

/**
 * Try to reuse a customer created with the same externalReference.
 */
function buscarClienteAsaasPorExternalReference(callable $makeAsaasRequest, ?string $externalReference, string $context = 'PAGAMENTOS_PARCIAIS')
{
    if (empty($externalReference)) {
        return null;
    }

    $searchResult = $makeAsaasRequest('GET', '/customers?externalReference=' . urlencode($externalReference));

    if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
        error_log("{$context}: Customer found by externalReference {$externalReference}");
        return $searchResult['data']['data'][0]['id'];
    }

    if (!empty($searchResult['error'])) {
        $errorDebug = is_string($searchResult['error']) ? $searchResult['error'] : json_encode($searchResult['error']);
        error_log("{$context}: ExternalReference search returned error: {$errorDebug}");
    }

    return null;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Parse dados_cliente if provided
    $dadosCliente = [];
    if (isset($_POST['dados_cliente'])) {
        $dadosCliente = json_decode($_POST['dados_cliente'], true) ?: [];
    }
    
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
            $dadosCliente = json_decode($_POST['dados_cliente'] ?? '{}', true);
            
            // Invoice generation parameters
            $gerarNotaFiscal = isset($_POST['gerar_nota_fiscal']) && $_POST['gerar_nota_fiscal'] === '1';
            $valorNotaFiscal = (float) ($_POST['valor_nota_fiscal'] ?? 0);
            $enviarWhatsApp = isset($_POST['enviar_whatsapp']) && $_POST['enviar_whatsapp'] === '1';
            $clienteCpf = $_POST['cliente_cpf'] ?? '';
            $clienteCnpj = $_POST['cliente_cnpj'] ?? '';
            
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
            
            // Validate invoice generation requirements
        if ($gerarNotaFiscal) {
            if ($valorNotaFiscal <= 0) {
                throw new \Exception('Valor da nota fiscal deve ser maior que zero');
            }
            // CPF/CNPJ é opcional para notas fiscais (depende do município)
            // Removida validação obrigatória
            if (!empty($clienteCpf) && !empty($clienteCnpj)) {
                throw new \Exception('Informe apenas CPF ou CNPJ, não ambos');
            }
            if ($enviarWhatsApp && empty($telefoneCliente)) {
                throw new \Exception('Telefone é obrigatório para envio da nota fiscal por WhatsApp');
            }
        }
            
            // Process customer data if provided (EXACT same logic as order creation)
            $clienteId = null;
            
            // DEBUG: Log all received data
            error_log("=== DEBUG PAGAMENTO ===");
            error_log("dadosCliente recebido: " . json_encode($dadosCliente));
            error_log("nome_cliente: " . ($nomeCliente ?? 'vazio'));
            error_log("telefone_cliente: " . ($telefoneCliente ?? 'vazio'));
            error_log("dados_cliente não vazio: " . (!empty($dadosCliente) ? 'true' : 'false'));
            if (!empty($dadosCliente)) {
                error_log("dadosCliente['nome']: " . ($dadosCliente['nome'] ?? 'vazio'));
                error_log("dadosCliente['telefone']: " . ($dadosCliente['telefone'] ?? 'vazio'));
                error_log("condição nome ou telefone: " . ((!empty($dadosCliente['nome']) || !empty($dadosCliente['telefone'])) ? 'true' : 'false'));
            }
            
            if (!empty($dadosCliente) && (!empty($dadosCliente['nome']) || !empty($dadosCliente['telefone']))) {
                error_log("✅ Entrando no processamento de cliente");
                try {
                    // Load ClienteController
                    require_once __DIR__ . '/../../mvc/controller/ClienteController.php';
                    $clienteController = new \MVC\Controller\ClienteController();
                    
                    // Create or find client
                    error_log("Chamando criarOuBuscarCliente com: " . json_encode($dadosCliente));
                    $result = $clienteController->criarOuBuscarCliente($dadosCliente);
                    error_log("Resultado criarOuBuscarCliente: " . json_encode($result));
                    
                    if ($result['success'] && $result['cliente']) {
                        $clienteId = $result['cliente']['id'];
                        error_log("✅ Cliente processado no pagamento: ID {$clienteId}, Nome: {$result['cliente']['nome']}");
                    } else {
                        error_log("❌ Erro ao processar cliente no pagamento: " . ($result['message'] ?? 'Erro desconhecido'));
                    }
                } catch (Exception $e) {
                    error_log("❌ Exception ao processar cliente no pagamento: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            } else {
                error_log("❌ Condição não atendida para processar cliente");
            }
            error_log("=== FIM DEBUG PAGAMENTO ===");
            
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
                
                // Buscar total de descontos aplicados
                $descontosResult = $db->fetch(
                    "SELECT COALESCE(SUM(valor_desconto), 0) as total_descontos 
                     FROM descontos_aplicados 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $totalDescontos = (float) ($descontosResult['total_descontos'] ?? 0);
                
                // Valor total líquido (considerando descontos)
                $valorTotalLiquido = $valorTotal - $totalDescontos;
                
                // Buscar total pago (excluindo descontos dos pagamentos)
                $totalPagoResult = $db->fetch(
                    "SELECT COALESCE(SUM(CASE WHEN forma_pagamento != 'DESCONTO' THEN valor_pago ELSE 0 END), 0) as total_pago 
                     FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $valorPagoAnterior = (float) ($totalPagoResult['total_pago'] ?? 0);
                $valorPagoNovo = $valorPagoAnterior + $valorPago;
                
                // Saldo devedor = valor total líquido - valor pago
                $saldoDevedor = $valorTotalLiquido - $valorPagoNovo;
                
                // Check if payment exceeds remaining balance
                if ($valorPagoNovo > $valorTotalLiquido) {
                    throw new \Exception(sprintf(
                        'Payment amount (R$ %.2f) exceeds remaining balance (R$ %.2f)',
                        $valorPago,
                        $valorTotalLiquido - $valorPagoAnterior
                    ));
                }
                
                // Calculate change if paying with cash
                $trocoDevolver = 0;
                if ($trocoPara > 0 && $formaPagamento === 'Dinheiro') {
                    $saldoAtual = $valorTotalLiquido - $valorPagoAnterior;
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
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
                
                // Garantir que o saldo_devedor está correto (sempre recalcular)
                // Saldo devedor = valor_total - descontos - pagamentos
                $saldoDevedorFinal = max(0, $saldoDevedor);
                
                // Log para debug
                error_log("Pagamento parcial - Pedido #$pedidoId: valorTotal=$valorTotal, descontos=$totalDescontos, valorTotalLiquido=$valorTotalLiquido, valorPagoAnterior=$valorPagoAnterior, valorPagoNovo=$valorPagoNovo, saldoDevedor=$saldoDevedorFinal");
                
                // Update order payment status
                $updateData = [
                    'valor_pago' => $valorPagoNovo,
                    'saldo_devedor' => $saldoDevedorFinal,
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
                
                // Generate invoice if requested
                $notaFiscalData = null;
                if ($gerarNotaFiscal) {
                    try {
                        $notaFiscalData = gerarNotaFiscalPedido($pedidoId, $valorNotaFiscal, $enviarWhatsApp, $telefoneCliente, $tenantId, $filialId, $clienteCpf, $clienteCnpj, $nomeCliente);
                    } catch (Exception $e) {
                        error_log("Erro ao gerar nota fiscal: " . $e->getMessage());
                        // Don't fail the payment if invoice generation fails
                        $notaFiscalData = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                $response = [
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
                ];
                
                // Add invoice data to response
                if ($notaFiscalData) {
                    $response['nota_fiscal'] = $notaFiscalData;
                }
                
                echo json_encode($response);
                
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

            // Verificar se há pedidos na mesa (excluindo quitados)
            $pedidosMesa = $db->fetchAll(
                "SELECT idpedido, valor_total, saldo_devedor, status, status_pagamento FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );

            // Não corrigir automaticamente - deixar os dados como estão
            
            error_log('PAGAMENTOS_PARCIAIS: Pedidos encontrados na mesa: ' . json_encode($pedidosMesa));

            // Calcular o saldo devedor total da mesa - FIADO conta como pagamento normal
            // Excluir pedidos quitados e finalizados/cancelados
            $saldoDevedorMesa = 0;
            $valorTotalMesa = 0;
            
            foreach ($pedidosMesa as $pedido) {
                // Excluir pedidos finalizados, cancelados ou quitados
                if (!in_array($pedido['status'], ['Finalizado', 'Cancelado']) && $pedido['status_pagamento'] != 'quitado') {
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
            $dadosCliente = json_decode($_POST['dados_cliente'] ?? '{}', true);

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
            
            // Process customer data if provided (EXACT same logic as order creation)
            $clienteId = null;
            if (!empty($dadosCliente) && !empty($dadosCliente['nome'])) {
                try {
                    // Load ClienteController
                    require_once __DIR__ . '/../../mvc/controller/ClienteController.php';
                    $clienteController = new \MVC\Controller\ClienteController();
                    
                    // Create or find client
                    $result = $clienteController->criarOuBuscarCliente($dadosCliente);
                    if ($result['success'] && $result['cliente']) {
                        $clienteId = $result['cliente']['id'];
                    }
                } catch (Exception $e) {
                    error_log("Erro ao processar cliente no pagamento da mesa: " . $e->getMessage());
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
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);

                // Calcular o novo valor pago total para este pedido (excluindo descontos)
                $totalPagoResult = $db->fetch(
                    "SELECT COALESCE(SUM(CASE WHEN forma_pagamento != 'DESCONTO' THEN valor_pago ELSE 0 END), 0) as total 
                     FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $totalPagoPedido = $totalPagoResult['total'] ?? 0;

                // Buscar o valor total do pedido
                $pedidoResult = $db->fetch(
                    "SELECT valor_total FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $valorTotalPedido = $pedidoResult['valor_total'] ?? 0;
                
                // Buscar total de descontos aplicados
                $descontosResult = $db->fetch(
                    "SELECT COALESCE(SUM(valor_desconto), 0) as total_descontos 
                     FROM descontos_aplicados 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                $totalDescontos = (float) ($descontosResult['total_descontos'] ?? 0);
                
                // Valor total líquido (considerando descontos)
                $valorTotalLiquido = $valorTotalPedido - $totalDescontos;

                // Calcular novo saldo devedor = valor total líquido - valor pago
                $novoSaldoDevedor = max(0, $valorTotalLiquido - $totalPagoPedido);
                
                // Log para debug
                error_log("Pagamento mesa - Pedido #$pedidoId: valorTotal=$valorTotalPedido, descontos=$totalDescontos, valorTotalLiquido=$valorTotalLiquido, totalPagoPedido=$totalPagoPedido, novoSaldoDevedor=$novoSaldoDevedor");

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

        case 'gerar_fatura_pix':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $valor = (float) ($_POST['valor'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido é obrigatório');
            }
            
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            try {
                // Get pedido
                $pedido = $db->fetch(
                    "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }
                
                // Get Asaas config
                require_once __DIR__ . '/../model/AsaasInvoice.php';
                $asaasInvoice = new AsaasInvoice();
                $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
                
                if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                    throw new \Exception('Integração Asaas não configurada para este estabelecimento');
                }
                
                // Create or find customer in Asaas
                // Use same logic as checkout (pedidos_online.php) - create directly in Asaas
                $clienteAsaasId = null;
                
                // Initialize Asaas API request function
                $apiUrl = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
                $apiKey = $asaasConfig['asaas_api_key'];
                
                $makeAsaasRequest = function($method, $endpoint, $data = null) use ($apiUrl, $apiKey) {
                    $url = $apiUrl . $endpoint;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'access_token: ' . $apiKey,
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    if ($method === 'POST') {
                        curl_setopt($ch, CURLOPT_POST, true);
                        if ($data) {
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        }
                    }
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $decoded = json_decode($response, true);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        return ['success' => true, 'data' => $decoded];
                    } else {
                        return ['success' => false, 'error' => $decoded['errors'] ?? 'Erro na API'];
                    }
                };
                
                // Clean phone and name
                $telefoneClienteLimpo = !empty($telefoneCliente) ? preg_replace('/[^0-9]/', '', $telefoneCliente) : '';
                $nomeClienteLimpo = trim($nomeCliente ?? '');
                
                // SIMPLE LOGIC: If we have phone, check BD for existing Asaas customer ID
                if (!empty($telefoneClienteLimpo)) {
                    try {
                        // Check if client exists in BD with Asaas customer ID (check if column exists first)
                        $clienteLocal = null;
                        try {
                            $clienteLocal = $db->fetch(
                                "SELECT id, nome, email, cpf, asaas_customer_id 
                                 FROM usuarios_globais 
                                 WHERE telefone = ? AND ativo = true 
                                 LIMIT 1",
                                [$telefoneClienteLimpo]
                            );
                        } catch (\Exception $e) {
                            // Column might not exist, try without it
                            error_log("PAGAMENTOS_PARCIAIS: Tentando buscar cliente sem asaas_customer_id: " . $e->getMessage());
                            try {
                                $clienteLocal = $db->fetch(
                                    "SELECT id, nome, email, cpf 
                                     FROM usuarios_globais 
                                     WHERE telefone = ? AND ativo = true 
                                     LIMIT 1",
                                    [$telefoneClienteLimpo]
                                );
                            } catch (\Exception $e2) {
                                error_log("PAGAMENTOS_PARCIAIS: Erro ao buscar cliente no BD: " . $e2->getMessage());
                            }
                        }
                        
                        if ($clienteLocal && !empty($clienteLocal['asaas_customer_id'])) {
                            // Client exists in BD and has Asaas customer ID - use it
                            $clienteAsaasId = $clienteLocal['asaas_customer_id'];
                            error_log("PAGAMENTOS_PARCIAIS: Cliente encontrado no BD com Asaas ID: $clienteAsaasId");
                        } else {
                            // Client doesn't exist in BD or doesn't have Asaas ID - create new in Asaas
                            $nomeParaAsaas = $nomeClienteLimpo ?: ($clienteLocal['nome'] ?? 'Cliente');
                            $telefoneParaAsaas = $telefoneClienteLimpo;
                            
                            $customerData = [
                                'name' => $nomeParaAsaas,
                                'phone' => $telefoneParaAsaas,
                                'externalReference' => $clienteLocal ? 'cliente_' . $clienteLocal['id'] : 'cliente_pedido_' . ($pedidoId ?? 'temp_' . time())
                            ];
                            
                            // Add email and CPF only if clienteLocal exists and has these fields
                            if ($clienteLocal && !empty($clienteLocal['email'])) {
                                $customerData['email'] = $clienteLocal['email'];
                            }
                            
                            if ($clienteLocal && !empty($clienteLocal['cpf'])) {
                                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $clienteLocal['cpf']);
                            }
                            
                            error_log("PAGAMENTOS_PARCIAIS: Criando novo cliente no Asaas: " . json_encode($customerData));
                            $customerResult = $makeAsaasRequest('POST', '/customers', $customerData);
                            
                            if ($customerResult['success'] && isset($customerResult['data']['id'])) {
                                $clienteAsaasId = $customerResult['data']['id'];
                                error_log("PAGAMENTOS_PARCIAIS: Cliente criado no Asaas: $clienteAsaasId");
                                
                                // Save Asaas customer ID to BD if client exists and column exists
                                if ($clienteLocal) {
                                    try {
                                        $db->update(
                                            'usuarios_globais',
                                            ['asaas_customer_id' => $clienteAsaasId],
                                            'id = ?',
                                            [$clienteLocal['id']]
                                        );
                                        error_log("PAGAMENTOS_PARCIAIS: Asaas customer ID salvo no BD para cliente ID: " . $clienteLocal['id']);
                                    } catch (\Exception $e) {
                                        error_log("PAGAMENTOS_PARCIAIS: Erro ao salvar Asaas customer ID no BD (coluna pode não existir): " . $e->getMessage());
                                    }
                                }
                            } else {
                                // If customer already exists, try to find by email or CPF
                                $errorMsg = is_array($customerResult['error']) ? json_encode($customerResult['error']) : ($customerResult['error'] ?? '');
                                error_log("PAGAMENTOS_PARCIAIS: Erro ao criar cliente no Asaas: $errorMsg");
                                
                                if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false || strpos($errorMsg, 'duplicate') !== false) {
                                    // Try to find by email first (only if clienteLocal exists and has email)
                                    if ($clienteLocal && !empty($clienteLocal['email'])) {
                                        $searchResult = $makeAsaasRequest('GET', '/customers?email=' . urlencode($clienteLocal['email']));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS: Cliente encontrado no Asaas por email: $clienteAsaasId");
                                            
                                            // Save Asaas customer ID to BD
                                            if ($clienteLocal) {
                                                try {
                                                    $db->update(
                                                        'usuarios_globais',
                                                        ['asaas_customer_id' => $clienteAsaasId],
                                                        'id = ?',
                                                        [$clienteLocal['id']]
                                                    );
                                                    error_log("PAGAMENTOS_PARCIAIS: Asaas customer ID salvo no BD após busca por email");
                                                } catch (\Exception $e) {
                                                    error_log("PAGAMENTOS_PARCIAIS: Erro ao salvar Asaas customer ID no BD: " . $e->getMessage());
                                                }
                                            }
                                        }
                                    }
                                    
                                    // If still not found, try by CPF (only if clienteLocal exists and has CPF)
                                    if (!$clienteAsaasId && $clienteLocal && !empty($clienteLocal['cpf'])) {
                                        $cpfLimpo = preg_replace('/[^0-9]/', '', $clienteLocal['cpf']);
                                        $searchResult = $makeAsaasRequest('GET', '/customers?cpfCnpj=' . urlencode($cpfLimpo));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS: Cliente encontrado no Asaas por CPF: $clienteAsaasId");
                                            
                                            // Save Asaas customer ID to BD
                                            if ($clienteLocal) {
                                                try {
                                                    $db->update(
                                                        'usuarios_globais',
                                                        ['asaas_customer_id' => $clienteAsaasId],
                                                        'id = ?',
                                                        [$clienteLocal['id']]
                                                    );
                                                    error_log("PAGAMENTOS_PARCIAIS: Asaas customer ID salvo no BD após busca por CPF");
                                                } catch (\Exception $e) {
                                                    error_log("PAGAMENTOS_PARCIAIS: Erro ao salvar Asaas customer ID no BD: " . $e->getMessage());
                                                }
                                            }
                                        }
                                    }
                                    
                                    // If still not found and clienteLocal doesn't exist, try to find by phone
                                    if (!$clienteAsaasId && !$clienteLocal) {
                                        $searchResult = $makeAsaasRequest('GET', '/customers?phone=' . urlencode($telefoneParaAsaas));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS: Cliente encontrado no Asaas por telefone: $clienteAsaasId");
                                        }
                                    }
                                }
                                
                                // If still not found, throw error (EXACTLY like checkout)
                                if (!$clienteAsaasId) {
                                    throw new \Exception('Não foi possível criar ou encontrar cliente no Asaas');
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("PAGAMENTOS_PARCIAIS: Erro ao processar cliente com telefone: " . $e->getMessage());
                        throw $e; // Throw error (EXACTLY like checkout)
                    }
                } else {
                    error_log("PAGAMENTOS_PARCIAIS: Nenhum telefone informado, usará cliente genérico");
                }
                
                // Final fallback: use tenant/filial customer ID (only if no client data provided)
                if (!$clienteAsaasId) {
                    error_log("PAGAMENTOS_PARCIAIS: Cliente específico não criado, tentando usar cliente genérico do tenant/filial");
                    $clienteAsaasId = $asaasConfig['asaas_customer_id'] ?? null;
                    if ($clienteAsaasId) {
                        error_log("PAGAMENTOS_PARCIAIS: Usando cliente genérico existente: $clienteAsaasId");
                    }
                }
                
                // If still no customer ID, create a generic customer using tenant/filial data
                if (!$clienteAsaasId) {
                    error_log("PAGAMENTOS_PARCIAIS: Nenhum cliente disponível, tentando criar cliente genérico no Asaas. Tenant: $tenantId, Filial: $filialId");
                    try {
                        // Get tenant/filial data
                        if ($filialId) {
                            $entity = $db->fetch(
                                "SELECT f.*, t.nome as tenant_nome, t.email as tenant_email, t.cnpj as tenant_cnpj, t.telefone as tenant_telefone
                                 FROM filiais f
                                 JOIN tenants t ON f.tenant_id = t.id
                                 WHERE f.id = ? AND f.tenant_id = ?",
                                [$filialId, $tenantId]
                            );
                            $entityName = $entity['nome'] ?? $entity['tenant_nome'] ?? 'Cliente';
                            $entityEmail = $entity['email'] ?? $entity['tenant_email'] ?? '';
                            $entityCnpj = $entity['cnpj'] ?? $entity['tenant_cnpj'] ?? '';
                            $entityPhone = $entity['telefone'] ?? $entity['tenant_telefone'] ?? '';
                        } else {
                            $entity = $db->fetch(
                                "SELECT * FROM tenants WHERE id = ?",
                                [$tenantId]
                            );
                            $entityName = $entity['nome'] ?? 'Cliente';
                            $entityEmail = $entity['email'] ?? '';
                            $entityCnpj = $entity['cnpj'] ?? '';
                            $entityPhone = $entity['telefone'] ?? '';
                        }
                        
                        error_log("PAGAMENTOS_PARCIAIS: Dados da entidade - Nome: $entityName, Email: $entityEmail, CNPJ: " . (!empty($entityCnpj) ? 'sim' : 'não'));
                        
                        // Try to find existing customer by CNPJ
                        if (!empty($entityCnpj)) {
                            $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
                            error_log("PAGAMENTOS_PARCIAIS: Buscando cliente por CNPJ: $cnpjClean");
                            $searchResult = $makeAsaasRequest('GET', '/customers?cpfCnpj=' . urlencode($cnpjClean));
                            if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                error_log("PAGAMENTOS_PARCIAIS: Cliente encontrado no Asaas: $clienteAsaasId");
                            } else {
                                error_log("PAGAMENTOS_PARCIAIS: Cliente não encontrado por CNPJ. Erro: " . json_encode($searchResult['error'] ?? 'N/A'));
                            }
                        }
                        
                $genericExternalReference = ($filialId ? 'filial_' : 'tenant_') . ($filialId ?? $tenantId);

                // Try to reuse a generic customer already registered for this tenant/filial
                if (!$clienteAsaasId) {
                    $clienteAsaasId = buscarClienteAsaasPorExternalReference($makeAsaasRequest, $genericExternalReference, 'PAGAMENTOS_PARCIAIS');
                }

                // Create generic customer if not found
                if (!$clienteAsaasId) {
                            $customerData = [
                                'name' => $entityName ?: 'Cliente',
                        'email' => $entityEmail ?: 'cliente@exemplo.com',
                        'phone' => preg_replace('/[^0-9]/', '', $entityPhone) ?: '11999999999',
                        'externalReference' => $genericExternalReference
                            ];
                            
                            if (!empty($entityCnpj)) {
                                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $entityCnpj);
                            }
                            
                            error_log("PAGAMENTOS_PARCIAIS: Criando cliente genérico no Asaas: " . json_encode($customerData));
                            $createResult = $makeAsaasRequest('POST', '/customers', $customerData);
                            
                            if ($createResult['success'] && isset($createResult['data']['id'])) {
                                $clienteAsaasId = $createResult['data']['id'];
                                error_log("PAGAMENTOS_PARCIAIS: Cliente genérico criado com sucesso: $clienteAsaasId");
                                
                                // Save to database for future use
                                if ($filialId) {
                                    $db->update(
                                        'filiais',
                                        ['asaas_customer_id' => $clienteAsaasId],
                                        'id = ? AND tenant_id = ?',
                                        [$filialId, $tenantId]
                                    );
                                    error_log("PAGAMENTOS_PARCIAIS: Customer ID salvo na filial $filialId");
                                } else {
                                    $db->update(
                                        'tenants',
                                        ['asaas_customer_id' => $clienteAsaasId],
                                        'id = ?',
                                        [$tenantId]
                                    );
                                    error_log("PAGAMENTOS_PARCIAIS: Customer ID salvo no tenant $tenantId");
                                }
                            } else {
                                $errorMsg = is_array($createResult['error']) ? json_encode($createResult['error']) : ($createResult['error'] ?? 'Erro desconhecido');
                                error_log("PAGAMENTOS_PARCIAIS: Erro ao criar cliente genérico: $errorMsg");
                            }
                    
                    if (!$clienteAsaasId) {
                        $clienteAsaasId = buscarClienteAsaasPorExternalReference($makeAsaasRequest, $genericExternalReference, 'PAGAMENTOS_PARCIAIS');
                    }
                        }
                    } catch (\Exception $e) {
                        error_log("PAGAMENTOS_PARCIAIS: Exception ao criar cliente genérico no Asaas: " . $e->getMessage());
                        error_log("PAGAMENTOS_PARCIAIS: Stack trace: " . $e->getTraceAsString());
                    }
                }
                
                if (!$clienteAsaasId) {
                    error_log("PAGAMENTOS_PARCIAIS: ERRO FINAL - Nenhum customer ID disponível. Tenant: $tenantId, Filial: $filialId");
                    error_log("PAGAMENTOS_PARCIAIS: Telefone informado: " . ($telefoneClienteLimpo ?: 'não informado'));
                    error_log("PAGAMENTOS_PARCIAIS: Nome informado: " . ($nomeClienteLimpo ?: 'não informado'));
                    error_log("PAGAMENTOS_PARCIAIS: Asaas Config: " . json_encode($asaasConfig ?? []));
                    
                    // Provide more specific error message
                    $errorMsg = 'Não foi possível criar ou encontrar cliente no Asaas. ';
                    if (empty($asaasConfig) || !$asaasConfig['asaas_enabled']) {
                        $errorMsg .= 'Integração Asaas não está habilitada para este estabelecimento.';
                    } elseif (empty($asaasConfig['asaas_api_key'])) {
                        $errorMsg .= 'Chave de API do Asaas não configurada.';
                    } else {
                        $errorMsg .= 'Verifique a configuração do Asaas no painel de configurações.';
                    }
                    
                    throw new \Exception($errorMsg);
                }
                
                error_log("PAGAMENTOS_PARCIAIS: Usando customer ID: $clienteAsaasId");
                
                // Get webhook URL
                $webhookUrl = null;
                if (!empty($asaasConfig['asaas_webhook_url'])) {
                    $webhookUrl = $asaasConfig['asaas_webhook_url'];
                } else {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
                    $webhookUrl = $protocol . '://' . $host . '/webhook/asaas.php';
                }
                
                // Calculate due date (1 day from now)
                try {
                    $dt = \System\TimeHelper::createDateTime('+1 day', $filialId);
                    $dueDate = $dt->format('Y-m-d');
                } catch (\Exception $e) {
                    $dueDate = date('Y-m-d', strtotime('+1 day'));
                }
                
                // Get filial name
                $filial = $db->fetch(
                    "SELECT nome FROM filiais WHERE id = ? AND tenant_id = ?",
                    [$filialId, $tenantId]
                );
                $filialNome = $filial['nome'] ?? 'Estabelecimento';
                
                // Create payment
                $paymentData = [
                    'customer' => $clienteAsaasId,
                    'billingType' => 'PIX',
                    'value' => number_format($valor, 2, '.', ''),
                    'dueDate' => $dueDate,
                    'description' => $descricao ?: "Pedido #{$pedidoId} - {$filialNome}",
                    'externalReference' => "PEDIDO_{$pedidoId}",
                    'webhook' => $webhookUrl
                ];
                
                $paymentResult = $makeAsaasRequest('POST', '/payments', $paymentData);
                
                if (!$paymentResult['success'] || !isset($paymentResult['data']['id'])) {
                    $errorMsg = 'Erro desconhecido';
                    if (is_array($paymentResult['error'])) {
                        if (isset($paymentResult['error'][0]['description'])) {
                            $errorMsg = $paymentResult['error'][0]['description'];
                        } else {
                            $errorMsg = json_encode($paymentResult['error']);
                        }
                    } elseif (!empty($paymentResult['error'])) {
                        $errorMsg = $paymentResult['error'];
                    }
                    throw new \Exception('Erro ao criar pagamento PIX no Asaas: ' . $errorMsg);
                }
                
                $paymentDataResult = $paymentResult['data'];
                $paymentId = $paymentDataResult['id'];
                
                // Get PIX QR code (may need to fetch payment details)
                $pixQrCode = $paymentDataResult['pixQrCode'] ?? null;
                $pixCopyPaste = $paymentDataResult['pixCopyPaste'] ?? $paymentDataResult['pixCopiaECola'] ?? null;
                
                // If QR code not in initial response, fetch payment details
                if (!$pixQrCode && !$pixCopyPaste) {
                    sleep(2); // Wait for Asaas to process
                    $paymentDetails = $makeAsaasRequest('GET', '/payments/' . $paymentId);
                    
                    if ($paymentDetails['success'] && isset($paymentDetails['data'])) {
                        $pixQrCode = $paymentDetails['data']['pixQrCode'] ?? null;
                        $pixCopyPaste = $paymentDetails['data']['pixCopyPaste'] ?? $paymentDetails['data']['pixCopiaECola'] ?? null;
                    }
                }
                
                // Save payment reference to pedido
                $db->update(
                    'pedido',
                    ['asaas_payment_id' => $paymentId],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Build payment URL
                $paymentUrl = $paymentDataResult['invoiceUrl'] ?? $paymentDataResult['invoiceUrl'] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Fatura PIX gerada com sucesso',
                    'payment_id' => $paymentId,
                    'valor' => $valor,
                    'pix_qr_code' => $pixQrCode,
                    'pix_copy_paste' => $pixCopyPaste,
                    'payment_url' => $paymentUrl
                ]);
                
            } catch (\Exception $e) {
                error_log("Erro ao gerar fatura PIX: " . $e->getMessage());
                throw $e;
            }
            break;

        case 'gerar_fatura_pix_mesa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $valor = (float) ($_POST['valor'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            
            if (empty($mesaId)) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            try {
                // Get pedidos da mesa
                $pedidosMesa = $db->fetchAll(
                    "SELECT idpedido FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado')",
                    [$mesaId, $tenantId, $filialId]
                );
                
                if (empty($pedidosMesa)) {
                    throw new \Exception('Nenhum pedido encontrado para esta mesa');
                }
                
                // Use first pedido for reference
                $pedidoId = $pedidosMesa[0]['idpedido'];
                
                // Get Asaas config
                require_once __DIR__ . '/../model/AsaasInvoice.php';
                $asaasInvoice = new AsaasInvoice();
                $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
                
                if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                    throw new \Exception('Integração Asaas não configurada para este estabelecimento');
                }
                
                // Create or find customer in Asaas
                // Use same logic as checkout (pedidos_online.php) - create directly in Asaas
                $clienteAsaasId = null;
                
                // Initialize Asaas API request function
                $apiUrl = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
                $apiKey = $asaasConfig['asaas_api_key'];
                
                $makeAsaasRequest = function($method, $endpoint, $data = null) use ($apiUrl, $apiKey) {
                    $url = $apiUrl . $endpoint;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'access_token: ' . $apiKey,
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    if ($method === 'POST') {
                        curl_setopt($ch, CURLOPT_POST, true);
                        if ($data) {
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        }
                    }
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $decoded = json_decode($response, true);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        return ['success' => true, 'data' => $decoded];
                    } else {
                        return ['success' => false, 'error' => $decoded['errors'] ?? 'Erro na API'];
                    }
                };
                
                // Clean phone and name
                $telefoneClienteLimpo = !empty($telefoneCliente) ? preg_replace('/[^0-9]/', '', $telefoneCliente) : '';
                $nomeClienteLimpo = trim($nomeCliente ?? '');
                
                // SIMPLE LOGIC: If we have phone, check BD for existing Asaas customer ID
                if (!empty($telefoneClienteLimpo)) {
                    try {
                        // Check if client exists in BD with Asaas customer ID (check if column exists first)
                        $clienteLocal = null;
                        try {
                            $clienteLocal = $db->fetch(
                                "SELECT id, nome, email, cpf, asaas_customer_id 
                                 FROM usuarios_globais 
                                 WHERE telefone = ? AND ativo = true 
                                 LIMIT 1",
                                [$telefoneClienteLimpo]
                            );
                        } catch (\Exception $e) {
                            // Column might not exist, try without it
                            error_log("PAGAMENTOS_PARCIAIS_MESA: Tentando buscar cliente sem asaas_customer_id: " . $e->getMessage());
                            try {
                                $clienteLocal = $db->fetch(
                                    "SELECT id, nome, email, cpf 
                                     FROM usuarios_globais 
                                     WHERE telefone = ? AND ativo = true 
                                     LIMIT 1",
                                    [$telefoneClienteLimpo]
                                );
                            } catch (\Exception $e2) {
                                error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao buscar cliente no BD: " . $e2->getMessage());
                            }
                        }
                        
                        if ($clienteLocal && !empty($clienteLocal['asaas_customer_id'])) {
                            // Client exists in BD and has Asaas customer ID - use it
                            $clienteAsaasId = $clienteLocal['asaas_customer_id'];
                            error_log("PAGAMENTOS_PARCIAIS_MESA: Cliente encontrado no BD com Asaas ID: $clienteAsaasId");
                        } else {
                            // Client doesn't exist in BD or doesn't have Asaas ID - create new in Asaas
                            $nomeParaAsaas = $nomeClienteLimpo ?: ($clienteLocal['nome'] ?? 'Cliente');
                            $telefoneParaAsaas = $telefoneClienteLimpo;
                            
                            $customerData = [
                                'name' => $nomeParaAsaas,
                                'phone' => $telefoneParaAsaas,
                                'externalReference' => $clienteLocal ? 'cliente_' . $clienteLocal['id'] : 'cliente_pedido_' . ($pedidoId ?? 'temp_' . time())
                            ];
                            
                            // Add email and CPF only if clienteLocal exists and has these fields
                            if ($clienteLocal && !empty($clienteLocal['email'])) {
                                $customerData['email'] = $clienteLocal['email'];
                            }
                            
                            if ($clienteLocal && !empty($clienteLocal['cpf'])) {
                                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $clienteLocal['cpf']);
                            }
                            
                            error_log("PAGAMENTOS_PARCIAIS_MESA: Criando novo cliente no Asaas: " . json_encode($customerData));
                            $customerResult = $makeAsaasRequest('POST', '/customers', $customerData);
                            
                            if ($customerResult['success'] && isset($customerResult['data']['id'])) {
                                $clienteAsaasId = $customerResult['data']['id'];
                                error_log("PAGAMENTOS_PARCIAIS_MESA: Cliente criado no Asaas: $clienteAsaasId");
                                
                                // Save Asaas customer ID to BD if client exists and column exists
                                if ($clienteLocal) {
                                    try {
                                        $db->update(
                                            'usuarios_globais',
                                            ['asaas_customer_id' => $clienteAsaasId],
                                            'id = ?',
                                            [$clienteLocal['id']]
                                        );
                                        error_log("PAGAMENTOS_PARCIAIS_MESA: Asaas customer ID salvo no BD para cliente ID: " . $clienteLocal['id']);
                                    } catch (\Exception $e) {
                                        error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao salvar Asaas customer ID no BD (coluna pode não existir): " . $e->getMessage());
                                    }
                                }
                            } else {
                                // If customer already exists, try to find by email or CPF
                                $errorMsg = is_array($customerResult['error']) ? json_encode($customerResult['error']) : ($customerResult['error'] ?? '');
                                error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao criar cliente no Asaas: $errorMsg");
                                
                                if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false || strpos($errorMsg, 'duplicate') !== false) {
                                    // Try to find by email first (only if clienteLocal exists and has email)
                                    if ($clienteLocal && !empty($clienteLocal['email'])) {
                                        $searchResult = $makeAsaasRequest('GET', '/customers?email=' . urlencode($clienteLocal['email']));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS_MESA: Cliente encontrado no Asaas por email: $clienteAsaasId");
                                            
                                            // Save Asaas customer ID to BD
                                            if ($clienteLocal) {
                                                try {
                                                    $db->update(
                                                        'usuarios_globais',
                                                        ['asaas_customer_id' => $clienteAsaasId],
                                                        'id = ?',
                                                        [$clienteLocal['id']]
                                                    );
                                                    error_log("PAGAMENTOS_PARCIAIS_MESA: Asaas customer ID salvo no BD após busca por email");
                                                } catch (\Exception $e) {
                                                    error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao salvar Asaas customer ID no BD: " . $e->getMessage());
                                                }
                                            }
                                        }
                                    }
                                    
                                    // If still not found, try by CPF (only if clienteLocal exists and has CPF)
                                    if (!$clienteAsaasId && $clienteLocal && !empty($clienteLocal['cpf'])) {
                                        $cpfLimpo = preg_replace('/[^0-9]/', '', $clienteLocal['cpf']);
                                        $searchResult = $makeAsaasRequest('GET', '/customers?cpfCnpj=' . urlencode($cpfLimpo));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS_MESA: Cliente encontrado no Asaas por CPF: $clienteAsaasId");
                                            
                                            // Save Asaas customer ID to BD
                                            if ($clienteLocal) {
                                                try {
                                                    $db->update(
                                                        'usuarios_globais',
                                                        ['asaas_customer_id' => $clienteAsaasId],
                                                        'id = ?',
                                                        [$clienteLocal['id']]
                                                    );
                                                    error_log("PAGAMENTOS_PARCIAIS_MESA: Asaas customer ID salvo no BD após busca por CPF");
                                                } catch (\Exception $e) {
                                                    error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao salvar Asaas customer ID no BD: " . $e->getMessage());
                                                }
                                            }
                                        }
                                    }
                                    
                                    // If still not found and clienteLocal doesn't exist, try to find by phone
                                    if (!$clienteAsaasId && !$clienteLocal) {
                                        $searchResult = $makeAsaasRequest('GET', '/customers?phone=' . urlencode($telefoneParaAsaas));
                                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                            error_log("PAGAMENTOS_PARCIAIS_MESA: Cliente encontrado no Asaas por telefone: $clienteAsaasId");
                                        }
                                    }
                                }
                                
                                // If still not found, throw error (EXACTLY like checkout)
                                if (!$clienteAsaasId) {
                                    throw new \Exception('Não foi possível criar ou encontrar cliente no Asaas');
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("PAGAMENTOS_PARCIAIS_MESA: Erro ao processar cliente com telefone: " . $e->getMessage());
                        throw $e;
                    }
                }
                // If no phone provided, don't search - will use generic customer below
                
                // Final fallback: use tenant/filial customer ID (only if no client data provided)
                if (!$clienteAsaasId) {
                    $clienteAsaasId = $asaasConfig['asaas_customer_id'] ?? null;
                }
                
                // If still no customer ID, create a generic customer using tenant/filial data
                if (!$clienteAsaasId) {
                    error_log("PAGAMENTOS_PARCIAIS (MESA): Tentando criar cliente genérico no Asaas. Tenant: $tenantId, Filial: $filialId");
                    try {
                        // Get tenant/filial data
                        if ($filialId) {
                            $entity = $db->fetch(
                                "SELECT f.*, t.nome as tenant_nome, t.email as tenant_email, t.cnpj as tenant_cnpj, t.telefone as tenant_telefone
                                 FROM filiais f
                                 JOIN tenants t ON f.tenant_id = t.id
                                 WHERE f.id = ? AND f.tenant_id = ?",
                                [$filialId, $tenantId]
                            );
                            $entityName = $entity['nome'] ?? $entity['tenant_nome'] ?? 'Cliente';
                            $entityEmail = $entity['email'] ?? $entity['tenant_email'] ?? '';
                            $entityCnpj = $entity['cnpj'] ?? $entity['tenant_cnpj'] ?? '';
                            $entityPhone = $entity['telefone'] ?? $entity['tenant_telefone'] ?? '';
                        } else {
                            $entity = $db->fetch(
                                "SELECT * FROM tenants WHERE id = ?",
                                [$tenantId]
                            );
                            $entityName = $entity['nome'] ?? 'Cliente';
                            $entityEmail = $entity['email'] ?? '';
                            $entityCnpj = $entity['cnpj'] ?? '';
                            $entityPhone = $entity['telefone'] ?? '';
                        }
                        
                        error_log("PAGAMENTOS_PARCIAIS (MESA): Dados da entidade - Nome: $entityName, Email: $entityEmail, CNPJ: " . (!empty($entityCnpj) ? 'sim' : 'não'));

                        $genericExternalReference = ($filialId ? 'filial_' : 'tenant_') . ($filialId ?? $tenantId);
                        if (!$clienteAsaasId) {
                            $clienteAsaasId = buscarClienteAsaasPorExternalReference($makeAsaasRequest, $genericExternalReference, 'PAGAMENTOS_PARCIAIS (MESA)');
                        }
                        
                        // Try to find existing customer by CNPJ
                        if (!empty($entityCnpj)) {
                            $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
                            error_log("PAGAMENTOS_PARCIAIS (MESA): Buscando cliente por CNPJ: $cnpjClean");
                            $searchResult = $makeAsaasRequest('GET', '/customers?cpfCnpj=' . urlencode($cnpjClean));
                            if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                                error_log("PAGAMENTOS_PARCIAIS (MESA): Cliente encontrado no Asaas: $clienteAsaasId");
                            } else {
                                error_log("PAGAMENTOS_PARCIAIS (MESA): Cliente não encontrado por CNPJ. Erro: " . json_encode($searchResult['error'] ?? 'N/A'));
                            }
                        }
                        
                        // Create generic customer if not found
                        if (!$clienteAsaasId) {
                            $customerData = [
                                'name' => $entityName ?: 'Cliente',
                                'email' => $entityEmail ?: 'cliente@exemplo.com',
                                'phone' => preg_replace('/[^0-9]/', '', $entityPhone) ?: '11999999999',
                                'externalReference' => $genericExternalReference
                            ];
                            
                            if (!empty($entityCnpj)) {
                                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $entityCnpj);
                            }
                            
                            error_log("PAGAMENTOS_PARCIAIS (MESA): Criando cliente genérico no Asaas: " . json_encode($customerData));
                            $createResult = $makeAsaasRequest('POST', '/customers', $customerData);
                            
                            if ($createResult['success'] && isset($createResult['data']['id'])) {
                                $clienteAsaasId = $createResult['data']['id'];
                                error_log("PAGAMENTOS_PARCIAIS (MESA): Cliente genérico criado com sucesso: $clienteAsaasId");
                                
                                // Save to database for future use
                                if ($filialId) {
                                    $db->update(
                                        'filiais',
                                        ['asaas_customer_id' => $clienteAsaasId],
                                        'id = ? AND tenant_id = ?',
                                        [$filialId, $tenantId]
                                    );
                                    error_log("PAGAMENTOS_PARCIAIS (MESA): Customer ID salvo na filial $filialId");
                                } else {
                                    $db->update(
                                        'tenants',
                                        ['asaas_customer_id' => $clienteAsaasId],
                                        'id = ?',
                                        [$tenantId]
                                    );
                                    error_log("PAGAMENTOS_PARCIAIS (MESA): Customer ID salvo no tenant $tenantId");
                                }
                            } else {
                                $errorMsg = is_array($createResult['error']) ? json_encode($createResult['error']) : ($createResult['error'] ?? 'Erro desconhecido');
                                error_log("PAGAMENTOS_PARCIAIS (MESA): Erro ao criar cliente genérico: $errorMsg");
                            }
                            
                            if (!$clienteAsaasId) {
                                $clienteAsaasId = buscarClienteAsaasPorExternalReference($makeAsaasRequest, $genericExternalReference, 'PAGAMENTOS_PARCIAIS (MESA)');
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("PAGAMENTOS_PARCIAIS (MESA): Exception ao criar cliente genérico no Asaas: " . $e->getMessage());
                        error_log("PAGAMENTOS_PARCIAIS (MESA): Stack trace: " . $e->getTraceAsString());
                    }
                }
                
                if (!$clienteAsaasId) {
                    error_log("PAGAMENTOS_PARCIAIS (MESA): ERRO FINAL - Nenhum customer ID disponível. Tenant: $tenantId, Filial: $filialId");
                    error_log("PAGAMENTOS_PARCIAIS (MESA): Telefone informado: " . ($telefoneClienteLimpo ?? 'não informado'));
                    error_log("PAGAMENTOS_PARCIAIS (MESA): Nome informado: " . ($nomeClienteLimpo ?? 'não informado'));
                    error_log("PAGAMENTOS_PARCIAIS (MESA): Asaas Config: " . json_encode($asaasConfig ?? []));
                    
                    // Provide more specific error message
                    $errorMsg = 'Não foi possível criar ou encontrar cliente no Asaas. ';
                    if (empty($asaasConfig) || !$asaasConfig['asaas_enabled']) {
                        $errorMsg .= 'Integração Asaas não está habilitada para este estabelecimento.';
                    } elseif (empty($asaasConfig['asaas_api_key'])) {
                        $errorMsg .= 'Chave de API do Asaas não configurada.';
                    } else {
                        $errorMsg .= 'Verifique a configuração do Asaas no painel de configurações.';
                    }
                    
                    throw new \Exception($errorMsg);
                }
                
                error_log("PAGAMENTOS_PARCIAIS (MESA): Usando customer ID: $clienteAsaasId");
                
                // Get webhook URL
                $webhookUrl = null;
                if (!empty($asaasConfig['asaas_webhook_url'])) {
                    $webhookUrl = $asaasConfig['asaas_webhook_url'];
                } else {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
                    $webhookUrl = $protocol . '://' . $host . '/webhook/asaas.php';
                }
                
                // Calculate due date (1 day from now)
                try {
                    $dt = \System\TimeHelper::createDateTime('+1 day', $filialId);
                    $dueDate = $dt->format('Y-m-d');
                } catch (\Exception $e) {
                    $dueDate = date('Y-m-d', strtotime('+1 day'));
                }
                
                // Get filial name
                $filial = $db->fetch(
                    "SELECT nome FROM filiais WHERE id = ? AND tenant_id = ?",
                    [$filialId, $tenantId]
                );
                $filialNome = $filial['nome'] ?? 'Estabelecimento';
                
                // Create payment
                $paymentData = [
                    'customer' => $clienteAsaasId,
                    'billingType' => 'PIX',
                    'value' => number_format($valor, 2, '.', ''),
                    'dueDate' => $dueDate,
                    'description' => $descricao ?: "Mesa #{$mesaId} - {$filialNome}",
                    'externalReference' => "MESA_{$mesaId}",
                    'webhook' => $webhookUrl
                ];
                
                $paymentResult = $makeAsaasRequest('POST', '/payments', $paymentData);
                
                if (!$paymentResult['success'] || !isset($paymentResult['data']['id'])) {
                    $errorMsg = 'Erro desconhecido';
                    if (is_array($paymentResult['error'])) {
                        if (isset($paymentResult['error'][0]['description'])) {
                            $errorMsg = $paymentResult['error'][0]['description'];
                        } else {
                            $errorMsg = json_encode($paymentResult['error']);
                        }
                    } elseif (!empty($paymentResult['error'])) {
                        $errorMsg = $paymentResult['error'];
                    }
                    throw new \Exception('Erro ao criar pagamento PIX no Asaas: ' . $errorMsg);
                }
                
                $paymentDataResult = $paymentResult['data'];
                $paymentId = $paymentDataResult['id'];
                
                // Get PIX QR code (may need to fetch payment details)
                $pixQrCode = $paymentDataResult['pixQrCode'] ?? null;
                $pixCopyPaste = $paymentDataResult['pixCopyPaste'] ?? $paymentDataResult['pixCopiaECola'] ?? null;
                
                // If QR code not in initial response, fetch payment details
                if (!$pixQrCode && !$pixCopyPaste) {
                    sleep(2); // Wait for Asaas to process
                    $paymentDetails = $makeAsaasRequest('GET', '/payments/' . $paymentId);
                    
                    if ($paymentDetails['success'] && isset($paymentDetails['data'])) {
                        $pixQrCode = $paymentDetails['data']['pixQrCode'] ?? null;
                        $pixCopyPaste = $paymentDetails['data']['pixCopyPaste'] ?? $paymentDetails['data']['pixCopiaECola'] ?? null;
                    }
                }
                
                // Save payment reference to all pedidos of the mesa (for tracking and reconciliation)
                // This ensures consistency with gerar_fatura_pix action
                foreach ($pedidosMesa as $pedidoMesa) {
                    $db->update(
                        'pedido',
                        ['asaas_payment_id' => $paymentId],
                        'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        [$pedidoMesa['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Build payment URL
                $paymentUrl = $paymentDataResult['invoiceUrl'] ?? $paymentDataResult['invoiceUrl'] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Fatura PIX gerada com sucesso',
                    'payment_id' => $paymentId,
                    'valor' => $valor,
                    'pix_qr_code' => $pixQrCode,
                    'pix_copy_paste' => $pixCopyPaste,
                    'payment_url' => $paymentUrl
                ]);
                
            } catch (\Exception $e) {
                error_log("Erro ao gerar fatura PIX da mesa: " . $e->getMessage());
                throw $e;
            }
            break;

        case 'ensureAsaasCustomer':
            try {
                // Ensure customer exists in Asaas (create if not exists, return ID if exists)
                $nomeCliente = $_POST['nome_cliente'] ?? '';
                $telefoneCliente = $_POST['telefone_cliente'] ?? '';
                $emailCliente = $_POST['email_cliente'] ?? '';
                $cpfCnpjCliente = $_POST['cpf_cnpj'] ?? '';
                $externalReference = $_POST['external_reference'] ?? '';
                
                if (empty($nomeCliente)) {
                    echo json_encode(['success' => false, 'error' => 'Nome do cliente é obrigatório']);
                    exit;
                }
                
                // Get Asaas config
                require_once __DIR__ . '/../model/AsaasInvoice.php';
                require_once __DIR__ . '/../model/AsaasAPIClient.php';
                $asaasInvoice = new AsaasInvoice();
                $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
                
                if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                    echo json_encode(['success' => false, 'error' => 'Integração Asaas não configurada']);
                    exit;
                }
                
                $apiUrl = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
                $apiKey = $asaasConfig['asaas_api_key'];
                
                // Use centralized AsaasAPIClient
                $apiClient = new AsaasAPIClient($apiKey, $apiUrl, 15, 5);
            
            $customerId = null;
            
            // Try to find customer by CPF/CNPJ first
            if (!empty($cpfCnpjCliente)) {
                $cpfCnpjLimpo = preg_replace('/[^0-9]/', '', $cpfCnpjCliente);
                $searchResult = $apiClient->getCustomers(['cpfCnpj' => $cpfCnpjLimpo]);
                
                if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                    $customerId = $searchResult['data']['data'][0]['id'];
                }
            }
            
            // Try to find by email if not found
            if (!$customerId && !empty($emailCliente)) {
                $searchResult = $apiClient->getCustomers(['email' => $emailCliente]);
                
                if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                    $customerId = $searchResult['data']['data'][0]['id'];
                }
            }
            
            // Try to find by externalReference if not found
            if (!$customerId && !empty($externalReference)) {
                $searchResult = $apiClient->getCustomers(['externalReference' => $externalReference]);
                
                if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                    $customerId = $searchResult['data']['data'][0]['id'];
                }
            }
            
            // Create customer if not found
            if (!$customerId) {
                $customerData = [
                    'name' => $nomeCliente,
                    'phone' => !empty($telefoneCliente) ? preg_replace('/[^0-9]/', '', $telefoneCliente) : '',
                    'externalReference' => $externalReference ?: 'pedido_' . time()
                ];
                
                if (!empty($emailCliente)) {
                    $customerData['email'] = $emailCliente;
                }
                
                if (!empty($cpfCnpjCliente)) {
                    $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $cpfCnpjCliente);
                }
                
                $createResult = $apiClient->createCustomer($customerData);
                
                if ($createResult['success'] && isset($createResult['data']['id'])) {
                    $customerId = $createResult['data']['id'];
                } else {
                    // If error is "already exists", try to find again
                    $errorMsg = is_string($createResult['error']) ? $createResult['error'] : json_encode($createResult['error']);
                    if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false) {
                        // Try to find by CPF/CNPJ again
                        if (!empty($cpfCnpjCliente)) {
                            $cpfCnpjLimpo = preg_replace('/[^0-9]/', '', $cpfCnpjCliente);
                            $searchResult = $apiClient->getCustomers(['cpfCnpj' => $cpfCnpjLimpo]);
                            if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                                $customerId = $searchResult['data']['data'][0]['id'];
                            }
                        }
                    }
                    
                    if (!$customerId) {
                        echo json_encode(['success' => false, 'error' => 'Não foi possível criar ou encontrar cliente no Asaas: ' . $errorMsg]);
                        exit;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'customer_id' => $customerId
            ]);
            exit;
            } catch (\Exception $e) {
                error_log('ensureAsaasCustomer Exception: ' . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            break;
            
        case 'createAsaasPayment':
            try {
                // Create payment in Asaas and get PIX QR Code
                $customerId = $_POST['customer_id'] ?? '';
                $valor = (float) ($_POST['valor'] ?? 0);
                $dueDate = $_POST['due_date'] ?? date('Y-m-d');
                $description = $_POST['description'] ?? '';
                $externalReference = $_POST['external_reference'] ?? '';
                
                if (empty($customerId)) {
                    echo json_encode(['success' => false, 'error' => 'ID do cliente no Asaas é obrigatório']);
                    exit;
                }
                
                if ($valor <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Valor deve ser maior que zero']);
                    exit;
                }
                
                // Get Asaas config
                require_once __DIR__ . '/../model/AsaasInvoice.php';
                require_once __DIR__ . '/../model/AsaasAPIClient.php';
                $asaasInvoice = new AsaasInvoice();
                $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
                
                if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                    echo json_encode(['success' => false, 'error' => 'Integração Asaas não configurada']);
                    exit;
                }
                
                $apiUrl = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
                $apiKey = $asaasConfig['asaas_api_key'];
                
                // Use centralized AsaasAPIClient
                $apiClient = new AsaasAPIClient($apiKey, $apiUrl, 15, 5);
                
                // Create payment
                $paymentData = [
                    'customer' => $customerId,
                    'billingType' => 'PIX',
                    'value' => number_format($valor, 2, '.', ''),
                    'dueDate' => $dueDate,
                    'description' => $description ?: 'Pagamento via PIX',
                    'externalReference' => $externalReference ?: 'pagamento_' . time()
                ];
                
                $paymentResult = $apiClient->createPayment($paymentData);
                
                if (!$paymentResult['success']) {
                    $errorMsg = is_string($paymentResult['error']) ? $paymentResult['error'] : json_encode($paymentResult['error']);
                    echo json_encode(['success' => false, 'error' => 'Erro ao criar pagamento no Asaas: ' . $errorMsg]);
                    exit;
                }
                
                $payment = $paymentResult['data'];
                
                // Get PIX QR Code
                $pixQrCodeResult = $apiClient->request('GET', '/payments/' . $payment['id'] . '/pixQrCode');
                
                if ($pixQrCodeResult['success']) {
                    $payment['pixQrCode'] = $pixQrCodeResult['data'];
                } else {
                    // QR Code is optional, payment can still be valid
                    error_log('Warning: Could not get PIX QR Code for payment ' . $payment['id']);
                }
                
                echo json_encode([
                    'success' => true,
                    'payment' => $payment
                ]);
                exit;
            } catch (\Exception $e) {
                error_log('createAsaasPayment Exception: ' . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            break;
            
        case 'testAsaasConnection':
            // Test Asaas API connection
            require_once __DIR__ . '/../model/AsaasInvoice.php';
            $asaasInvoice = new AsaasInvoice();
            $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
            
            if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                throw new \Exception('Integração Asaas não configurada');
            }
            
            $apiUrl = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            $apiKey = $asaasConfig['asaas_api_key'];
            
            // Use centralized AsaasAPIClient
            $apiClient = new AsaasAPIClient($apiKey, $apiUrl, 10, 5);
            $result = $apiClient->testConnection();
            
            if ($result['success']) {
                $totalCount = $result['data']['totalCount'] ?? 0;
                echo json_encode([
                    'success' => true,
                    'message' => 'Conexão com Asaas bem-sucedida!',
                    'details' => "Encontrados $totalCount cliente(s) cadastrado(s).",
                    'statusCode' => $result['http_code']
                ]);
            } else {
                $errorMsg = is_string($result['error']) ? $result['error'] : json_encode($result['error']);
                throw new \Exception('Erro ao conectar com Asaas: ' . $errorMsg);
            }
            break;
            
        case 'gerar_fatura_pix_prepare':
        case 'gerar_fatura_pix_mesa_prepare':
            // DEPRECATED: This approach is being replaced by ensureAsaasCustomer + createAsaasPayment
            // Keep for backward compatibility but will be removed in future version
            $pedidoId = $_POST['pedido_id'] ?? '';
            $mesaId = $_POST['mesa_id'] ?? '';
            $valor = (float) ($_POST['valor'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            
            if (($action === 'gerar_fatura_pix_prepare' && empty($pedidoId)) ||
                ($action === 'gerar_fatura_pix_mesa_prepare' && empty($mesaId))) {
                throw new \Exception('ID do pedido/mesa é obrigatório');
            }
            
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            // Get Asaas config
            require_once __DIR__ . '/../model/AsaasInvoice.php';
            $asaasInvoice = new AsaasInvoice();
            $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
            
            if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || !$asaasConfig['asaas_api_key']) {
                throw new \Exception('Integração Asaas não configurada para este estabelecimento');
            }
            
            // Prepare customer data
            $customerData = [
                'name' => $nomeCliente ?: 'Cliente',
                'email' => '',
                'mobilePhone' => '',
                'cpfCnpj' => ''
            ];
            
            // Try to get more customer data if available
            if (!empty($telefoneCliente)) {
                $telefoneFormatado = preg_replace('/[^0-9]/', '', $telefoneCliente);
                $customerData['mobilePhone'] = $telefoneFormatado;
                
                // Try to find existing customer by phone in usuarios_globais
                $cliente = $db->fetch(
                    "SELECT * FROM usuarios_globais WHERE telefone = ? LIMIT 1",
                    [$telefoneFormatado]
                );
                
                if ($cliente) {
                    $customerData['name'] = $cliente['nome'] ?? $customerData['name'];
                    $customerData['email'] = $cliente['email'] ?? '';
                    $customerData['cpfCnpj'] = $cliente['cpf'] ?? '';
                }
            }
            
            // Prepare payment data
            $dueDate = date('Y-m-d');
            $externalReference = $action === 'gerar_fatura_pix_prepare' ? "PED-{$pedidoId}" : "MESA-{$mesaId}";
            
            $paymentData = [
                'value' => number_format($valor, 2, '.', ''),
                'dueDate' => $dueDate,
                'description' => $descricao ?: ($action === 'gerar_fatura_pix_prepare' ? "Pedido #{$pedidoId}" : "Mesa #{$mesaId}"),
                'externalReference' => $externalReference
            ];
            
            // Return prepared data (API key included for frontend to use)
            echo json_encode([
                'success' => true,
                'data' => [
                    'asaas_config' => [
                        'api_url' => $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3',
                        'api_key' => $asaasConfig['asaas_api_key'] // Frontend needs this to call Asaas
                    ],
                    'customer_data' => $customerData,
                    'payment_data' => $paymentData
                ]
            ]);
            break;
            
        case 'salvar_fatura_pix':
            // Save Asaas response to database (called after frontend successfully creates payment)
            $pedidoId = $_POST['pedido_id'] ?? '';
            $mesaId = $_POST['mesa_id'] ?? '';
            $valor = (float) ($_POST['valor'] ?? 0);
            $asaasResponse = json_decode($_POST['asaas_response'] ?? '{}', true);
            
            if (empty($asaasResponse) || !isset($asaasResponse['id'])) {
                throw new \Exception('Resposta do Asaas inválida');
            }
            
            // Get pedido ID (if mesa, get first pedido of mesa)
            if ($mesaId && !$pedidoId) {
                $pedidoData = $db->fetch(
                    "SELECT idpedido FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') LIMIT 1",
                    [$mesaId, $tenantId, $filialId]
                );
                $pedidoId = $pedidoData['idpedido'] ?? null;
            }
            
            // Save to pagamentos table with Asaas data
            $pagamentoId = $db->insert('pagamentos', [
                'pedido_id' => $pedidoId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'valor' => $valor,
                'forma_pagamento' => 'pix',
                'status' => 'pendente',
                'transacao_id' => $asaasResponse['id'],
                'observacoes' => json_encode([
                    'pix_copia_e_cola' => $asaasResponse['pixQrCode']['payload'] ?? '',
                    'pix_qr_code_base64' => $asaasResponse['pixQrCode']['encodedImage'] ?? '',
                    'asaas_invoice_url' => $asaasResponse['invoiceUrl'] ?? '',
                    'vencimento' => $asaasResponse['dueDate'] ?? date('Y-m-d'),
                    'asaas_customer' => $asaasResponse['customer'] ?? ''
                ]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Fatura salva com sucesso',
                'pagamento_id' => $pagamentoId
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

/**
 * Generate invoice for order via Asaas
 */
function gerarNotaFiscalPedido($pedidoId, $valorNotaFiscal, $enviarWhatsApp, $telefoneCliente, $tenantId, $filialId, $clienteCpf = '', $clienteCnpj = '', $nomeCliente = '') {
    try {
        // Load required classes
        require_once __DIR__ . '/../../mvc/model/AsaasInvoice.php';
        require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
        
        $db = \System\Database::getInstance();
        
        // Get order details
        $pedido = $db->fetch(
            "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
            [$pedidoId, $tenantId, $filialId]
        );
        
        if (!$pedido) {
            throw new Exception('Pedido não encontrado');
        }
        
        // Update client fiscal data if provided
        if (($clienteCpf || $clienteCnpj) && $nomeCliente) {
            try {
                // Find client by name and phone
                $cliente = $db->fetch(
                    "SELECT ug.* FROM usuarios_globais ug 
                     JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id 
                     WHERE ug.nome = ? AND ug.telefone = ? AND ue.tenant_id = ?",
                    [$nomeCliente, $telefoneCliente, $tenantId]
                );
                
                if ($cliente) {
                    // Update existing client with fiscal data
                    $updateData = [];
                    if ($clienteCpf) {
                        $updateData['cpf'] = $clienteCpf;
                    }
                    if ($clienteCnpj) {
                        $updateData['cnpj'] = $clienteCnpj;
                    }
                    
                    if (!empty($updateData)) {
                        $db->update(
                            'usuarios_globais',
                            $updateData,
                            'id = ?',
                            [$cliente['id']]
                        );
                        error_log("Dados fiscais atualizados para cliente: {$nomeCliente}");
                    }
                } else {
                    // Create new client with fiscal data
                    $clienteId = $db->insert('usuarios_globais', [
                        'nome' => $nomeCliente,
                        'telefone' => $telefoneCliente,
                        'cpf' => $clienteCpf ?: null,
                        'cnpj' => $clienteCnpj ?: null,
                        'tipo_usuario' => 'cliente',
                        'ativo' => true,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Link client to tenant
                    $db->insert('usuarios_estabelecimento', [
                        'usuario_global_id' => $clienteId,
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId,
                        'tipo_usuario' => 'cliente',
                        'ativo' => true,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    error_log("Novo cliente criado com dados fiscais: {$nomeCliente}");
                }
            } catch (Exception $e) {
                error_log("Erro ao atualizar dados fiscais do cliente: " . $e->getMessage());
                // Don't fail invoice generation if client update fails
            }
        }
        
        // Get tenant Asaas configuration
        $tenant = $db->fetch(
            "SELECT asaas_api_key, asaas_api_url, asaas_environment, asaas_enabled 
             FROM tenants WHERE id = ?",
            [$tenantId]
        );
        
        if (!$tenant || !$tenant['asaas_enabled'] || !$tenant['asaas_api_key']) {
            throw new Exception('Integração Asaas não configurada para este estabelecimento');
        }
        
        // Get filial Asaas configuration (if exists)
        $filial = null;
        if ($filialId) {
            $filial = $db->fetch(
                "SELECT asaas_api_key, asaas_customer_id, asaas_enabled 
                 FROM filiais WHERE id = ? AND tenant_id = ?",
                [$filialId, $tenantId]
            );
        }
        
        // Use filial API key if available, otherwise use tenant API key
        $apiKey = $filial['asaas_api_key'] ?? $tenant['asaas_api_key'];
        $apiUrl = $tenant['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $environment = $tenant['asaas_environment'] ?? 'sandbox';
        
        // Create AsaasInvoice instance
        $asaasInvoice = new \MVC\Model\AsaasInvoice($apiKey, $apiUrl, $environment);
        
        // Prepare invoice data
        $clienteNome = $pedido['cliente'] ?? 'Cliente';
        $invoiceData = [
            'customer' => $filial['asaas_customer_id'] ?? $tenant['asaas_customer_id'] ?? null,
            'value' => $valorNotaFiscal,
            'description' => "Pedido #{$pedidoId} - {$clienteNome}",
            'externalReference' => "PEDIDO_{$pedidoId}",
            'dueDate' => date('Y-m-d'),
            'billingType' => 'UNDEFINED' // For invoice generation
        ];
        
        // Add fiscal data if available
        if ($clienteCpf) {
            $invoiceData['cpf'] = $clienteCpf;
        }
        if ($clienteCnpj) {
            $invoiceData['cnpj'] = $clienteCnpj;
        }
        
        // Generate invoice via Asaas
        $invoiceResult = $asaasInvoice->createInvoice($invoiceData);
        
        if (!$invoiceResult['success']) {
            throw new Exception('Erro ao gerar nota fiscal no Asaas: ' . ($invoiceResult['error'] ?? 'Erro desconhecido'));
        }
        
        $asaasInvoiceId = $invoiceResult['data']['id'] ?? null;
        $numeroNota = $invoiceResult['data']['invoiceNumber'] ?? null;
        
        // Save invoice to database
        $notaFiscalId = $db->insert('notas_fiscais', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'pedido_id' => $pedidoId,
            'asaas_invoice_id' => $asaasInvoiceId,
            'asaas_payment_id' => null, // Will be filled when payment is processed
            'numero_nota' => $numeroNota,
            'status' => 'issued',
            'valor_total' => $valorNotaFiscal,
            'data_emissao' => date('Y-m-d H:i:s'),
            'asaas_response' => json_encode($invoiceResult['data']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $result = [
            'success' => true,
            'nota_fiscal_id' => $notaFiscalId,
            'asaas_invoice_id' => $asaasInvoiceId,
            'numero_nota' => $numeroNota,
            'valor_total' => $valorNotaFiscal,
            'enviada_whatsapp' => false
        ];
        
        // Send via WhatsApp if requested
        if ($enviarWhatsApp && $telefoneCliente) {
            try {
                $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
                
                // Get active WhatsApp instance
                $instancia = $db->fetch(
                    "SELECT id FROM whatsapp_instances 
                     WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) AND status IN ('open', 'connected') 
                     ORDER BY created_at DESC LIMIT 1",
                    [$tenantId, $filialId]
                );
                
                if ($instancia) {
                    // Format phone number
                    $telefoneFormatado = preg_replace('/[^0-9]/', '', $telefoneCliente);
                    if (strlen($telefoneFormatado) == 11 && substr($telefoneFormatado, 0, 2) == '11') {
                        $telefoneFormatado = '55' . $telefoneFormatado;
                    }
                    
                    // Get PDF URL from Asaas
                    $pdfResult = $asaasInvoice->getInvoicePdfUrl($tenantId, $filialId, $asaasInvoiceId);
                    
                    if ($pdfResult['success'] && !empty($pdfResult['pdf_url'])) {
                        // Create message with PDF
                        $mensagem = "🧾 *Nota Fiscal Gerada*\n\n";
                        $mensagem .= "📄 Número: {$numeroNota}\n";
                        $mensagem .= "💰 Valor: R$ " . number_format($valorNotaFiscal, 2, ',', '.') . "\n";
                        $mensagem .= "📅 Data: " . date('d/m/Y H:i') . "\n";
                        $mensagem .= "🛒 Pedido: #{$pedidoId}\n\n";
                        $mensagem .= "📎 Anexo: PDF da Nota Fiscal\n\n";
                        $mensagem .= "Obrigado pela preferência! 🍽️";
                        
                        // Send PDF file
                        $fileName = "NotaFiscal_{$numeroNota}_{$pedidoId}.pdf";
                        $whatsappResult = $wuzapiManager->sendFile(
                            $instancia['id'], 
                            $telefoneFormatado, 
                            $pdfResult['pdf_url'], 
                            $fileName, 
                            $mensagem
                        );
                        
                        if ($whatsappResult['success']) {
                            $result['enviada_whatsapp'] = true;
                            $result['whatsapp_message_id'] = $whatsappResult['message_id'] ?? null;
                            $result['pdf_url'] = $pdfResult['pdf_url'];
                            
                            // Update database with PDF URL
                            $db->update(
                                'notas_fiscais',
                                ['pdf_url' => $pdfResult['pdf_url']],
                                'id = ?',
                                [$notaFiscalId]
                            );
                        } else {
                            error_log("Erro ao enviar PDF por WhatsApp: " . ($whatsappResult['message'] ?? 'Erro desconhecido'));
                            
                            // Fallback: send text message only
                            $mensagemTexto = "🧾 *Nota Fiscal Gerada*\n\n";
                            $mensagemTexto .= "📄 Número: {$numeroNota}\n";
                            $mensagemTexto .= "💰 Valor: R$ " . number_format($valorNotaFiscal, 2, ',', '.') . "\n";
                            $mensagemTexto .= "📅 Data: " . date('d/m/Y H:i') . "\n";
                            $mensagemTexto .= "🛒 Pedido: #{$pedidoId}\n\n";
                            $mensagemTexto .= "🔗 PDF: {$pdfResult['pdf_url']}\n\n";
                            $mensagemTexto .= "Obrigado pela preferência! 🍽️";
                            
                            $whatsappResult = $wuzapiManager->sendMessage($instancia['id'], $telefoneFormatado, $mensagemTexto);
                            
                            if ($whatsappResult['success']) {
                                $result['enviada_whatsapp'] = true;
                                $result['whatsapp_message_id'] = $whatsappResult['message_id'] ?? null;
                                $result['pdf_url'] = $pdfResult['pdf_url'];
                            }
                        }
                    } else {
                        error_log("Erro ao obter PDF da nota fiscal: " . ($pdfResult['error'] ?? 'Erro desconhecido'));
                        
                        // Send text message without PDF
                        $mensagem = "🧾 *Nota Fiscal Gerada*\n\n";
                        $mensagem .= "📄 Número: {$numeroNota}\n";
                        $mensagem .= "💰 Valor: R$ " . number_format($valorNotaFiscal, 2, ',', '.') . "\n";
                        $mensagem .= "📅 Data: " . date('d/m/Y H:i') . "\n";
                        $mensagem .= "🛒 Pedido: #{$pedidoId}\n\n";
                        $mensagem .= "Obrigado pela preferência! 🍽️";
                        
                        $whatsappResult = $wuzapiManager->sendMessage($instancia['id'], $telefoneFormatado, $mensagem);
                        
                        if ($whatsappResult['success']) {
                            $result['enviada_whatsapp'] = true;
                            $result['whatsapp_message_id'] = $whatsappResult['message_id'] ?? null;
                        }
                    }
                } else {
                    error_log("Nenhuma instância WhatsApp ativa encontrada");
                }
            } catch (Exception $e) {
                error_log("Erro ao enviar WhatsApp: " . $e->getMessage());
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erro na geração de nota fiscal: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

