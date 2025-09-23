<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $verMesa = $_GET['ver_mesa'] ?? $_POST['ver_mesa'] ?? '';
    $fecharPedido = $_GET['fechar_pedido'] ?? $_POST['fechar_pedido'] ?? '';
    $fecharMesa = $_GET['fechar_mesa'] ?? $_POST['fechar_mesa'] ?? '';
    $dividirPagamento = $_GET['dividir_pagamento'] ?? $_POST['dividir_pagamento'] ?? '';
    
    if ($verMesa == '1') {
        $action = 'ver_mesa_multiplos_pedidos';
    } elseif ($fecharPedido == '1') {
        $action = 'fechar_pedido_individual';
    } elseif ($fecharMesa == '1') {
        $action = 'fechar_mesa_completa';
    } elseif ($dividirPagamento == '1') {
        $action = 'dividir_pagamento';
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = 1; // Usar valor padrão
    $filialId = 1; // Usar valor padrão
    
    switch ($action) {
        case 'ver_mesa_multiplos_pedidos':
            $mesaId = (int) ($_GET['mesa_id'] ?? 0);
            
            if (!$mesaId) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            // Get mesa info
            $mesa = $db->fetch(
                "SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada');
            }
            
            // Get all pedidos for this mesa
            $pedidos = $db->fetchAll(
                "SELECT * FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') ORDER BY created_at ASC",
                [$mesaId, $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                // Mesa livre
                $html = '
                    <div class="text-center py-5">
                        <i class="fas fa-table fa-3x text-success mb-3"></i>
                        <h4 class="text-success">Mesa Livre</h4>
                        <p class="text-muted">Esta mesa está disponível para novos pedidos.</p>
                        <a href="index.php?view=gerar_pedido&mesa=' . $mesaId . '" class="btn btn-success">
                            <i class="fas fa-plus"></i> Criar Pedido
                        </a>
                    </div>
                ';
            } else {
                $valorTotalMesa = 0;
                foreach ($pedidos as $pedido) {
                    $valorTotalMesa += $pedido['valor_total'];
                }
                
                $html = '
                    <div class="mesa-pedidos">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6><i class="fas fa-table"></i> Mesa ' . $mesaId . '</h6>
                                <p class="mb-1"><strong>Total de Pedidos:</strong> ' . count($pedidos) . '</p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning">Ocupada</span></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h5 class="text-primary">Total: R$ ' . number_format($valorTotalMesa, 2, ',', '.') . '</h5>
                            </div>
                        </div>
                        
                        <div class="pedidos-list">';
                
                foreach ($pedidos as $index => $pedido) {
                    // Get itens do pedido
                    $itens = $db->fetchAll(
                        'SELECT pi.*, pr.nome as produto_nome FROM pedido_itens pi 
                         LEFT JOIN produtos pr ON pi.produto_id = pr.id 
                         WHERE pi.pedido_id = ? AND pi.tenant_id = ? AND pi.filial_id = ?',
                        [$pedido['idpedido'], $tenantId, $filialId]
                    );
                    
                    $html .= '
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-receipt"></i> Pedido #' . $pedido['idpedido'] . '
                                    <span class="badge bg-secondary ms-2">' . $pedido['status'] . '</span>
                                </h6>
                                <div class="text-end">
                                    <strong>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong>
                                    <br><small class="text-muted">' . $pedido['hora_pedido'] . '</small>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="itens-pedido">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-3">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Qtd</th>
                                                    <th>Valor</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
                    
                    foreach ($itens as $item) {
                        $html .= '
                            <tr>
                                <td>' . htmlspecialchars($item['produto_nome']) . '</td>
                                <td>' . $item['quantidade'] . '</td>
                                <td>R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>
                                <td>R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>
                            </tr>';
                    }
                    
                    $html .= '
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editarPedido(' . $pedido['idpedido'] . ')">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-success btn-sm" onclick="fecharPedidoIndividual(' . $pedido['idpedido'] . ')">
                                        <i class="fas fa-check"></i> Fechar Pedido
                                    </button>
                                </div>
                            </div>
                        </div>';
                }
                
                $html .= '
                        </div>
                        
                        <div class="actions-mesa mt-3">
                            <button class="btn btn-success btn-lg" onclick="fecharMesaCompleta(' . $mesaId . ')">
                                <i class="fas fa-check-circle"></i> Fechar Mesa Completa
                            </button>
                        </div>
                    </div>
                ';
            }
            
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        case 'fechar_pedido_individual':
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $numeroPessoas = (int)($_POST['numero_pessoas'] ?? 1);
            $observacao = $_POST['observacao'] ?? '';
            
            if ($pedidoId <= 0) {
                throw new \Exception('ID do pedido inválido');
            }
            
            if (empty($formaPagamento)) {
                throw new \Exception('Forma de pagamento é obrigatória');
            }
            
            // Get pedido info
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            $valorPorPessoa = $pedido['valor_total'] / $numeroPessoas;
            
            // Update pedido
            $db->update(
                'pedido',
                [
                    'status' => 'Finalizado',
                    'forma_pagamento' => $formaPagamento,
                    'numero_pessoas' => $numeroPessoas,
                    'valor_por_pessoa' => $valorPorPessoa,
                    'observacao_pagamento' => $observacao
                ],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            // Check if all pedidos for this mesa are closed
            $pedidosAbertos = $db->fetch(
                'SELECT COUNT(*) as count FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                [$pedido['idmesa'], 'Finalizado', 'Cancelado', $tenantId, $filialId]
            );
            
            if ($pedidosAbertos['count'] == 0) {
                // All pedidos closed, free the mesa
                $db->update(
                    'mesas',
                    ['status' => '1'],
                    'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedido['idmesa'], $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pedido fechado com sucesso!',
                'valor_por_pessoa' => $valorPorPessoa
            ]);
            break;
            
        case 'fechar_mesa_completa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $numeroPessoas = (int)($_POST['numero_pessoas'] ?? 1);
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($mesaId) || empty($formaPagamento)) {
                throw new \Exception('Mesa e forma de pagamento são obrigatórios');
            }
            
            // Get all open pedidos for this mesa
            $pedidos = $db->fetchAll(
                'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                [$mesaId, 'Finalizado', 'Cancelado', $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                throw new \Exception('Nenhum pedido aberto encontrado para esta mesa');
            }
            
            $valorTotal = 0;
            $valorPorPessoa = 0;
            
            // Close all pedidos
            foreach ($pedidos as $pedido) {
                $valorTotal += $pedido['valor_total'];
                
                $db->update(
                    'pedido',
                    [
                        'status' => 'Finalizado',
                        'forma_pagamento' => $formaPagamento,
                        'numero_pessoas' => $numeroPessoas,
                        'observacao_pagamento' => $observacao
                    ],
                    'idpedido = ?',
                    [$pedido['idpedido']]
                );
            }
            
            $valorPorPessoa = $valorTotal / $numeroPessoas;
            
            // Free the mesa
            $db->update(
                'mesas',
                ['status' => '1'],
                'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, $tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Mesa fechada com sucesso!',
                'valor_total' => $valorTotal,
                'valor_por_pessoa' => $valorPorPessoa,
                'pedidos_fechados' => count($pedidos)
            ]);
            break;
            
        case 'dividir_pagamento':
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
            $numeroPessoas = (int)($_POST['numero_pessoas'] ?? 1);
            
            if ($pedidoId <= 0 || $numeroPessoas <= 0) {
                throw new \Exception('Dados inválidos');
            }
            
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            $valorPorPessoa = $pedido['valor_total'] / $numeroPessoas;
            
            echo json_encode([
                'success' => true,
                'valor_total' => $pedido['valor_total'],
                'numero_pessoas' => $numeroPessoas,
                'valor_por_pessoa' => $valorPorPessoa
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
