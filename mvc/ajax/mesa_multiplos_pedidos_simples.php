<?php
// Configurar headers para JSON e evitar erros
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

// Função para capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno do servidor: ' . $error['message']
        ]);
        exit;
    }
});

try {
    // Primeiro verificar se há action específica no POST
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Se a ação principal é mesa_multiplos_pedidos mas há uma sub-ação no POST, usar a sub-ação
    if ($action === 'mesa_multiplos_pedidos' && !empty($_POST['action'])) {
        $action = $_POST['action'];
    }
    
    // Log para debug
    error_log("Action recebida: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    error_log("GET data: " . print_r($_GET, true));
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    error_log("Action antes do switch: " . $action);
    
    switch ($action) {
        case 'mesa_multiplos_pedidos':
        case 'ver_mesa_multiplos_pedidos':
            $mesaId = (int) ($_GET['mesa_id'] ?? 0);
            
            if ($mesaId <= 0) {
                throw new \Exception('ID da mesa inválido');
            }
            
            // Buscar dados da mesa
            $mesa = $db->fetch(
                'SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, $tenantId, $filialId]
            );
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada');
            }
            
            // Buscar pedidos ativos da mesa (excluindo quitados)
            $pedidos = $db->fetchAll(
                'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND status_pagamento != ? AND tenant_id = ? AND filial_id = ? ORDER BY created_at ASC',
                [$mesaId, 'Finalizado', 'Cancelado', 'quitado', $tenantId, $filialId]
            );
            
            $valorTotal = array_sum(array_column($pedidos, 'valor_total'));
            
            // Determinar status da mesa baseado nos pedidos ativos
            $statusMesa = !empty($pedidos) ? 'Ocupada' : 'Livre';
            $badgeClass = !empty($pedidos) ? 'bg-danger' : 'bg-success';
            
            // Gerar HTML da mesa
            $html = '<div class="mesa-details">';
            $html .= '<div class="status-card">';
            $html .= '<h6>Mesa ' . $mesa['id_mesa'] . '</h6>';
            $html .= '<p><strong>Total de Pedidos:</strong> ' . count($pedidos) . '</p>';
            $html .= '<p><strong>Status:</strong> <span class="badge ' . $badgeClass . '">' . $statusMesa . '</span></p>';
            $html .= '</div>';
            
            if (!empty($pedidos)) {
                $html .= '<div class="total-card">';
                $html .= '<h5>Total: R$ ' . number_format($valorTotal, 2, ',', '.') . '</h5>';
                $html .= '</div>';
            } else {
                $html .= '<div class="total-card">';
                $html .= '<h5>Total: R$ 0,00</h5>';
                $html .= '</div>';
            }
            
            if (!empty($pedidos)) {
                $html .= '<div class="itens-section">';
                $html .= '<h6>Pedidos Ativos</h6>';
                
                foreach ($pedidos as $pedido) {
                    $html .= '<div class="pedido-item">';
                    $html .= '<h6>Pedido #' . $pedido['idpedido'] . ' <span class="badge bg-primary">' . $pedido['status'] . '</span></h6>';
                    $html .= '<p><strong>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</strong> - ' . $pedido['hora_pedido'] . '</p>';
                    
                    // Buscar itens do pedido
                    $itens = $db->fetchAll(
                        'SELECT pi.*, p.nome as nome_produto FROM pedido_itens pi LEFT JOIN produtos p ON pi.produto_id = p.id WHERE pi.pedido_id = ?',
                        [$pedido['idpedido']]
                    );
                    
                    if (!empty($itens)) {
                        $html .= '<table class="table table-sm">';
                        $html .= '<thead><tr><th>Item</th><th>Qtd</th><th>Valor</th><th>Total</th></tr></thead>';
                        $html .= '<tbody>';
                        
                        foreach ($itens as $item) {
                            $html .= '<tr>';
                            $html .= '<td>';
                            $html .= '<strong>' . htmlspecialchars($item['nome_produto']) . '</strong>';
                            
                            // Mostrar observação se existir
                            if (!empty($item['observacao'])) {
                                $html .= '<br><small class="text-muted">Obs: ' . htmlspecialchars($item['observacao']) . '</small>';
                            }
                            
                            // Processar ingredientes
                            $ingredientesCom = [];
                            $ingredientesSem = [];
                            
                            if (!empty($item['ingredientes_com'])) {
                                if (is_string($item['ingredientes_com'])) {
                                    $ingredientesCom = array_filter(explode(', ', $item['ingredientes_com']));
                                } else {
                                    $ingredientesCom = $item['ingredientes_com'];
                                }
                            }
                            
                            if (!empty($item['ingredientes_sem'])) {
                                if (is_string($item['ingredientes_sem'])) {
                                    $ingredientesSem = array_filter(explode(', ', $item['ingredientes_sem']));
                                } else {
                                    $ingredientesSem = $item['ingredientes_sem'];
                                }
                            }
                            
                            // Mostrar ingredientes adicionados
                            if (!empty($ingredientesCom) && count($ingredientesCom) > 0) {
                                $html .= '<br><small class="text-success"><i class="fas fa-plus"></i> ' . implode(', ', $ingredientesCom) . '</small>';
                            }
                            
                            // Mostrar ingredientes removidos
                            if (!empty($ingredientesSem) && count($ingredientesSem) > 0) {
                                $html .= '<br><small class="text-danger"><i class="fas fa-minus"></i> ' . implode(', ', $ingredientesSem) . '</small>';
                            }
                            
                            $html .= '</td>';
                            $html .= '<td>' . $item['quantidade'] . '</td>';
                            $html .= '<td>R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>';
                            $html .= '<td>R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</tbody></table>';
                    }
                    
                    $html .= '<div class="pedido-actions">';
                    $html .= '<button class="btn btn-sm btn-warning" onclick="editarPedido(' . $pedido['idpedido'] . ')">Editar</button>';
                    $html .= '<button class="btn btn-sm btn-success" onclick="fecharPedidoIndividual(' . $pedido['idpedido'] . ')">Fechar Pedido</button>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            } else {
                $html .= '<div class="itens-section">';
                $html .= '<div class="alert alert-info">';
                $html .= '<i class="fas fa-info-circle"></i> Nenhum pedido ativo nesta mesa.';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '<div class="mesa-actions mt-3">';
            if (empty($pedidos)) {
                $html .= '<button class="btn btn-success" onclick="fazerPedido(' . $mesaId . ')">Fazer Pedido</button>';
            }
            $html .= '</div>';
            $html .= '</div>';
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'mesa' => $mesa,
                'pedidos' => $pedidos,
                'valor_total' => $valorTotal
            ]);
            break;
            
        case 'fechar_pedido_individual':
            error_log("=== INICIANDO fechar_pedido_individual ===");
            
            $pedidoId = (int)($_POST['id_pedido'] ?? 0);
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $valorPago = (float)($_POST['valor_pago'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $observacoes = $_POST['observacoes'] ?? '';
            
            error_log("Dados recebidos: pedidoId=$pedidoId, formaPagamento=$formaPagamento, valorPago=$valorPago");
            
            if ($pedidoId <= 0 || empty($formaPagamento)) {
                throw new \Exception('Dados incompletos para fechar o pedido');
            }
            
            error_log("Buscando pedido: $pedidoId");
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            error_log("Pedido encontrado: " . json_encode($pedido));
            
            // Atualizar status do pedido para 'Finalizado'
            error_log("Atualizando pedido...");
            $db->query(
                'UPDATE pedido SET status = ?, forma_pagamento = ?, cliente = ?, telefone_cliente = ?, observacao = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                ['Finalizado', $formaPagamento, html_entity_decode($nomeCliente, ENT_QUOTES, 'UTF-8'), $telefoneCliente, html_entity_decode($observacoes, ENT_QUOTES, 'UTF-8'), $pedidoId, $tenantId, $filialId]
            );
            error_log("Pedido atualizado com sucesso");
            
            // Verificar se a mesa pode ser liberada
            error_log("Verificando pedidos ativos na mesa: " . $pedido['idmesa']);
            $pedidosAtivosNaMesa = $db->fetch(
                'SELECT COUNT(*) as total FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                [$pedido['idmesa'], 'Finalizado', 'Cancelado', $tenantId, $filialId]
            );
            error_log("Pedidos ativos na mesa: " . $pedidosAtivosNaMesa['total']);
            
            if ($pedidosAtivosNaMesa['total'] == 0) {
                // Liberar a mesa se não houver mais pedidos ativos
                error_log("Liberando mesa: " . $pedido['idmesa']);
                $db->query(
                    'UPDATE mesas SET status = ? WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['Livre', (string)$pedido['idmesa'], $tenantId, $filialId]
                );
                error_log("Mesa liberada com sucesso");
            }
            
            error_log("=== FINALIZANDO fechar_pedido_individual ===");
            echo json_encode([
                'success' => true,
                'message' => "Pedido finalizado com sucesso! R$ " . number_format($valorPago, 2, ',', '.') . " - {$formaPagamento}",
                'pedido_id' => $pedidoId,
                'mesa_liberada' => $pedidosAtivosNaMesa['total'] == 0
            ]);
            break;
            
        case 'fechar_mesa_completa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $valorPago = (float)($_POST['valor_pago'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (empty($mesaId) || empty($formaPagamento) || $valorPago <= 0) {
                throw new \Exception('Dados incompletos para fechar a mesa');
            }
            
            // Buscar todos os pedidos ativos da mesa (excluindo finalizados, cancelados e quitados)
            $pedidos = $db->fetchAll(
                'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND status_pagamento != ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, 'Finalizado', 'Cancelado', 'quitado', $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                throw new \Exception('Nenhum pedido ativo encontrado na mesa');
            }
            
            // Usar saldo_devedor em vez de valor_total
            $valorTotal = 0;
            foreach ($pedidos as $pedido) {
                $valorTotal += (float)($pedido['saldo_devedor'] ?? $pedido['valor_total']);
            }
            
            // Fechar todos os pedidos da mesa
            foreach ($pedidos as $pedido) {
                $db->update(
                    'pedido',
                    [
                        'status' => 'Finalizado',
                        'forma_pagamento' => $formaPagamento,
                        'cliente' => html_entity_decode($nomeCliente, ENT_QUOTES, 'UTF-8'),
                        'telefone_cliente' => $telefoneCliente,
                        'observacao' => html_entity_decode($observacoes, ENT_QUOTES, 'UTF-8')
                    ],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
            }
            
            // Liberar a mesa
            $db->update(
                'mesas',
                ['status' => 'Livre'],
                'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [(string)$mesaId, $tenantId, $filialId]
            );
            
            $mensagem = "Mesa fechada com sucesso! Total: R$ " . number_format($valorTotal, 2, ',', '.') . " - {$formaPagamento}";
            if ($formaPagamento === 'Dinheiro' && $valorPago > $valorTotal) {
                $troco = $valorPago - $valorTotal;
                $mensagem .= " - Troco: R$ " . number_format($troco, 2, ',', '.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => $mensagem,
                'valor_total' => $valorTotal,
                'forma_pagamento' => $formaPagamento
            ]);
            break;
            
        default:
            error_log("Action não reconhecida: " . $action);
            throw new \Exception('Ação não reconhecida');
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
