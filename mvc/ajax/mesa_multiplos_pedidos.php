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
    
    // Auto-corrigir sequência da tabela pedido_itens
    $maxId = $db->fetch("SELECT MAX(id) as max_id FROM pedido_itens");
    $currentSeq = $db->fetch("SELECT last_value FROM pedido_itens_id_seq");
    
    if ($currentSeq['last_value'] <= $maxId['max_id']) {
        $newValue = $maxId['max_id'] + 1;
        $db->query("SELECT setval('pedido_itens_id_seq', ?)", [$newValue]);
        error_log("Sequência pedido_itens_id_seq corrigida automaticamente para: " . $newValue);
    }
    
    switch ($action) {
        case 'ver_mesa_multiplos_pedidos':
            $mesaId = (int) ($_GET['mesa_id'] ?? 0);
            
            if (!$mesaId) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            // Get mesa info - mesaId is the 'id_mesa' field from the mesas table (sent by Dashboard1.php)
            $mesa = $db->fetch(
                "SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            // If not found, try without tenant/filial filter
            if (!$mesa) {
                $mesa = $db->fetch(
                    "SELECT * FROM mesas WHERE id_mesa = ?",
                    [$mesaId]
                );
            }
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada para ID: ' . $mesaId . ' (tenant: ' . $tenantId . ', filial: ' . $filialId . ')');
            }
            
            // Get all pedidos for this mesa
            // First check if pedido table exists
            $tableExists = $db->fetch("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pedido')");
            
            if (!$tableExists['exists']) {
                throw new \Exception('Tabela pedido não existe');
            }
            
            $pedidos = $db->fetchAll(
                "SELECT * FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue') ORDER BY created_at ASC",
                [$mesa['id_mesa'], $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                // Mesa livre
                $html = '
                    <div class="text-center py-5">
                        <i class="fas fa-table fa-3x text-success mb-3"></i>
                        <h4 class="text-success">Mesa Livre</h4>
                        <p class="text-muted">Esta mesa está disponível para novos pedidos.</p>
                        <a href="index.php?view=gerar_pedido&mesa=' . $mesa['id_mesa'] . '" class="btn btn-success">
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
                                <h6><i class="fas fa-table"></i> Mesa ' . $mesa['id_mesa'] . '</h6>
                                <p class="mb-1"><strong>Total de Pedidos:</strong> ' . count($pedidos) . '</p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning">Ocupada</span></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h5 class="text-primary">Total: R$ ' . number_format($valorTotalMesa, 2, ',', '.') . '</h5>
                            </div>
                        </div>
                        
                        <div class="pedidos-list">';
                
                foreach ($pedidos as $index => $pedido) {
                    // Get itens do pedido - consulta simplificada
                    $itens = $db->fetchAll(
                        "SELECT pi.*, pr.nome as produto_nome
                         FROM pedido_itens pi 
                         LEFT JOIN produtos pr ON pi.produto_id = pr.id
                         WHERE pi.pedido_id = ? AND pi.tenant_id = ? AND pi.filial_id = ?
                         ORDER BY pi.id",
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
                            </div>';
                    
                    // Mostrar observação do pedido se existir
                    if (!empty($pedido['observacao'])) {
                        $html .= '
                            <div class="card-body border-bottom">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-comment"></i> <strong>Observação do Pedido:</strong> ' . htmlspecialchars($pedido['observacao']) . '
                                </div>
                            </div>';
                    }
                    
                    $html .= '
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
                        // Processar ingredientes exatamente como na popup de pedidos
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
                        
                        
                        $html .= '
                            <tr>
                                <td>
                                    <strong>' . htmlspecialchars($item['produto_nome']) . '</strong>';
                        
                        // Mostrar observação se existir
                        if (!empty($item['observacao'])) {
                            $html .= '<br><small class="text-muted">' . htmlspecialchars($item['observacao']) . '</small>';
                        }
                        
                        // Mostrar ingredientes adicionados
                        if (!empty($ingredientesCom) && count($ingredientesCom) > 0) {
                            $html .= '<br><small class="text-success"><i class="fas fa-plus"></i> ' . implode(', ', $ingredientesCom) . '</small>';
                        }
                        
                        // Mostrar ingredientes removidos
                        if (!empty($ingredientesSem) && count($ingredientesSem) > 0) {
                            $html .= '<br><small class="text-danger"><i class="fas fa-minus"></i> ' . implode(', ', $ingredientesSem) . '</small>';
                        }
                        
                        $html .= '
                                </td>
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
                                    </button>';
                    
                    // Adicionar botão de avançar status baseado no status atual
                    $statusAtual = $pedido['status'];
                    if ($statusAtual == 'Pendente') {
                        $html .= '<button class="btn btn-warning btn-sm" onclick="avancarStatusPedido(' . $pedido['idpedido'] . ', \'Em Preparo\')">
                                    <i class="fas fa-clock"></i> Em Preparo
                                  </button>';
                    } elseif ($statusAtual == 'Em Preparo') {
                        $html .= '<button class="btn btn-info btn-sm" onclick="avancarStatusPedido(' . $pedido['idpedido'] . ', \'Pronto\')">
                                    <i class="fas fa-check-circle"></i> Pronto
                                  </button>';
                    } elseif ($statusAtual == 'Pronto') {
                        $html .= '<button class="btn btn-success btn-sm" onclick="avancarStatusPedido(' . $pedido['idpedido'] . ', \'Entregue\')">
                                    <i class="fas fa-truck"></i> Entregue
                                  </button>';
                    }
                    
                    $html .= '<button class="btn btn-success btn-sm" onclick="fecharPedidoIndividual(' . $pedido['idpedido'] . ')">
                                        <i class="fas fa-check"></i> Fechar Pedido
                                    </button>
                                </div>
                            </div>
                        </div>';
                }
                
                $html .= '
                        </div>
                        
                        <div class="actions-mesa mt-3">
                            <button class="btn btn-success btn-lg" onclick="fecharMesaCompleta(' . $mesa['id_mesa'] . ')">
                                <i class="fas fa-check-circle"></i> Fechar Mesa Completa
                            </button>
                        </div>
                    </div>
                ';
            }
            
            echo json_encode(['success' => true, 'html' => $html]);
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
            
        case 'fechar_pedido_individual':
            $pedidoId = (int)($_POST['id_pedido'] ?? 0);
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $valorPago = (float)($_POST['valor_pago'] ?? 0);
            $nomeCliente = $_POST['nome_cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if ($pedidoId <= 0 || empty($formaPagamento) || $valorPago <= 0) {
                throw new \Exception('Dados incompletos para fechar o pedido');
            }
            
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            // Atualizar status do pedido para 'Finalizado'
            $db->execute(
                'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, nome_cliente = ?, telefone_cliente = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                ['Finalizado', $formaPagamento, $valorPago, $nomeCliente, $telefoneCliente, $observacoes, $pedidoId, $tenantId, $filialId]
            );
            
            // Verificar se a mesa pode ser liberada
            $pedidosAtivosNaMesa = $db->fetch(
                'SELECT COUNT(*) as total FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                [$pedido['idmesa'], 'Finalizado', 'Cancelado', $tenantId, $filialId]
            );
            
            if ($pedidosAtivosNaMesa['total'] == 0) {
                // Liberar a mesa se não houver mais pedidos ativos
                $db->execute(
                    'UPDATE mesas SET status = ? WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['Livre', $pedido['idmesa'], $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Pedido finalizado com sucesso! R$ " . number_format($valorPago, 2, ',', '.') . " - {$formaPagamento}",
                'pedido_id' => $pedidoId,
                'mesa_liberada' => $pedidosAtivosNaMesa['total'] == 0
            ]);
            break;
            
        case 'fechar_mesa_completa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $dadosFechamento = json_decode($_POST['dados_fechamento'] ?? '{}', true);
            
            if (empty($mesaId) || empty($dadosFechamento)) {
                throw new \Exception('Dados incompletos para fechar a mesa');
            }
            
            // Buscar todos os pedidos ativos da mesa
            $pedidos = $db->fetchAll(
                'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                [$mesaId, 'Finalizado', 'Cancelado', $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                throw new \Exception('Nenhum pedido ativo encontrado na mesa');
            }
            
            $valorTotal = array_sum(array_column($pedidos, 'valor_total'));
            $observacao = $dadosFechamento['observacao'] ?? '';
            
            // Processar fechamento baseado no tipo
            if ($dadosFechamento['tipo'] === 'simples') {
                // Fechamento simples - uma forma de pagamento
                $formaPagamento = $dadosFechamento['formaPagamento'];
                $valorPago = $dadosFechamento['valorPago'] ?? $valorTotal;
                
                foreach ($pedidos as $pedido) {
                    $db->execute(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamento, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->execute(
                    'UPDATE mesas SET status = ? WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['Livre', $mesaId, $tenantId, $filialId]
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
                
            } elseif ($dadosFechamento['tipo'] === 'dividido') {
                // Fechamento dividido por pessoas
                $numeroPessoas = (int)$dadosFechamento['numeroPessoas'];
                $formaPagamento = $dadosFechamento['formaPagamento'];
                $valorPorPessoa = $valorTotal / $numeroPessoas;
                
                foreach ($pedidos as $pedido) {
                    $db->execute(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamento, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->execute(
                    'UPDATE mesas SET status = ? WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['Livre', $mesaId, $tenantId, $filialId]
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => "Mesa fechada com sucesso! Dividido em {$numeroPessoas} pessoa(s) - R$ " . number_format($valorPorPessoa, 2, ',', '.') . " por pessoa - {$formaPagamento}",
                    'valor_total' => $valorTotal,
                    'numero_pessoas' => $numeroPessoas,
                    'valor_por_pessoa' => $valorPorPessoa,
                    'forma_pagamento' => $formaPagamento
                ]);
                
            } elseif ($dadosFechamento['tipo'] === 'misto') {
                // Fechamento com múltiplas formas de pagamento
                $pagamentos = $dadosFechamento['pagamentos'];
                $totalInformado = $pagamentos['dinheiro'] + $pagamentos['debito'] + $pagamentos['credito'] + $pagamentos['pix'];
                
                // Verificar se o total confere
                if (abs($totalInformado - $valorTotal) > 0.01) {
                    throw new \Exception('Total informado não confere com o valor da mesa');
                }
                
                // Criar string com formas de pagamento
                $formasPagamento = [];
                if ($pagamentos['dinheiro'] > 0) $formasPagamento[] = "Dinheiro: R$ " . number_format($pagamentos['dinheiro'], 2, ',', '.');
                if ($pagamentos['debito'] > 0) $formasPagamento[] = "Cartão Débito: R$ " . number_format($pagamentos['debito'], 2, ',', '.');
                if ($pagamentos['credito'] > 0) $formasPagamento[] = "Cartão Crédito: R$ " . number_format($pagamentos['credito'], 2, ',', '.');
                if ($pagamentos['pix'] > 0) $formasPagamento[] = "PIX: R$ " . number_format($pagamentos['pix'], 2, ',', '.');
                
                $formaPagamentoCompleta = implode(' + ', $formasPagamento);
                
                foreach ($pedidos as $pedido) {
                    $db->execute(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamentoCompleta, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->execute(
                    'UPDATE mesas SET status = ? WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['Livre', $mesaId, $tenantId, $filialId]
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => "Mesa fechada com sucesso! Total: R$ " . number_format($valorTotal, 2, ',', '.') . " - {$formaPagamentoCompleta}",
                    'valor_total' => $valorTotal,
                    'pagamentos' => $pagamentos
                ]);
            } else {
                throw new \Exception('Tipo de fechamento não reconhecido');
            }
            break;
            
        case 'ver_pedido':
            $pedidoId = (int)($_GET['ver_pedido'] ?? 0);
            
            // Debug: log the received parameters
            error_log("ver_pedido case - pedidoId: " . $pedidoId);
            error_log("GET parameters: " . print_r($_GET, true));
            
            if ($pedidoId <= 0) {
                throw new \Exception('ID do pedido inválido: ' . $pedidoId);
            }
            
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'pedido' => $pedido
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

<script>
function avancarStatusPedido(pedidoId, novoStatus) {
    console.log('Avançando status do pedido:', pedidoId, 'para:', novoStatus);
    
    // Fazer chamada AJAX para atualizar status
    const formData = new URLSearchParams();
    formData.append('action', 'atualizar_status');
    formData.append('pedido_id', pedidoId);
    formData.append('status', novoStatus);
    
    fetch('index.php?action=pedidos', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Sucesso!',
                text: 'Status atualizado com sucesso!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Recarregar a popup da mesa
                location.reload();
            });
        } else {
            Swal.fire('Erro', data.message || 'Erro ao atualizar status', 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        Swal.fire('Erro', 'Erro ao atualizar status do pedido', 'error');
    });
}
</script>
