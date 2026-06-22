<?php
// Configurar headers para JSON e evitar erros
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

function resolverTipoDescontoManual($db, $tenantId, $filialId)
{
    $row = $db->fetch(
        "SELECT id FROM tipos_desconto
         WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) AND ativo = true
         ORDER BY CASE WHEN nome ILIKE '%especial%' THEN 0 ELSE 1 END, id
         LIMIT 1",
        [$tenantId, $filialId]
    );

    return $row['id'] ?? null;
}

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
    
    // DEBUG: Log original action
    error_log('MESA_MODAL DEBUG: Action original = ' . $action . ', verMesa = ' . $verMesa);
    
    // Só sobrescrever action se não for uma action específica do sistema de pagamentos parciais
    if ($verMesa == '1' && !in_array($action, ['buscar_pedidos_mesa', 'liberar_mesa', 'fechar_mesa'])) {
        $action = 'ver_mesa_multiplos_pedidos';
        error_log('MESA_MODAL DEBUG: Action sobrescrita para ver_mesa_multiplos_pedidos');
    } elseif ($fecharPedido == '1' && !in_array($action, ['buscar_pedidos_mesa', 'liberar_mesa', 'fechar_mesa'])) {
        $action = 'fechar_pedido_individual';
    } elseif ($fecharMesa == '1' && !in_array($action, ['buscar_pedidos_mesa', 'liberar_mesa', 'fechar_mesa'])) {
        $action = 'fechar_mesa_completa';
    } elseif ($dividirPagamento == '1' && !in_array($action, ['buscar_pedidos_mesa', 'liberar_mesa', 'fechar_mesa'])) {
        $action = 'dividir_pagamento';
    }
    
    error_log('MESA_MODAL DEBUG: Action final = ' . $action);
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    // PostgreSQL gerencia sequências automaticamente - não precisamos interferir
    
    switch ($action) {
        case 'buscar_pedidos_mesa':
            $mesaId = $_POST['mesa_id'] ?? '';
            
            if (empty($mesaId)) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            // Buscar pedidos ativos da mesa (excluindo entregues e quitados)
            $pedidos = $db->fetchAll(
                "SELECT * FROM pedido 
                 WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado')
                 AND tenant_id = ? AND filial_id = ?
                 AND NOT (status = 'Entregue' AND status_pagamento = 'quitado')
                 ORDER BY created_at ASC",
                [$mesaId, $tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);
            break;
            
        case 'desvincular_comanda':
            $mesaId = $_POST['mesa_id'] ?? '';
            if (empty($mesaId)) {
                throw new \Exception('ID da comanda é obrigatório');
            }
            
            // Unlink comanda
            $db->query(
                "UPDATE mesas SET status = 'livre', cliente_nome = NULL, cliente_telefone = NULL WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Comanda desvinculada com sucesso!']);
            break;
            
        case 'ver_mesa_multiplos_pedidos':
            $mesaId = $_GET['mesa_id'] ?? '';
            
            if (empty($mesaId)) {
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
            
        // Buscar todos os pedidos da mesa com dados do estabelecimento (excluindo quitados e entregues quitados)
        $pedidos = $db->fetchAll(
            "SELECT p.*, t.nome as tenant_nome, f.nome as filial_nome, u.login as usuario_nome
             FROM pedido p 
             LEFT JOIN tenants t ON p.tenant_id = t.id 
             LEFT JOIN filiais f ON p.filial_id = f.id 
             LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
             WHERE p.idmesa::varchar = ? AND p.tenant_id = ? AND p.filial_id = ? 
             AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue') 
             AND p.status_pagamento != 'quitado' 
             AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado') 
             ORDER BY p.created_at ASC",
            [$mesa['id_mesa'], $tenantId, $filialId]
        );
            
            // Calcular saldo devedor total da mesa
            $saldoDevedorTotal = 0;
            foreach ($pedidos as $pedido) {
                if ($pedido['status_pagamento'] !== 'quitado') {
                    $saldoDevedorTotal += (float)$pedido['saldo_devedor'];
                }
            }
            
            // Só mostrar pedidos se há saldo devedor
            if ($saldoDevedorTotal <= 0) {
                // Mesa livre - sem saldo devedor
                $html = '
                    <div class="text-center py-5">
                        <i class="fas fa-table fa-3x text-success mb-3"></i>
                        <h4 class="text-success">Mesa Livre</h4>
                        <p class="text-muted">Esta mesa está disponível para novos pedidos. Todos os pedidos foram quitados.</p>
                        <a href="index.php?view=gerar_pedido&mesa=' . $mesa['id_mesa'] . '" class="btn btn-success">
                            <i class="fas fa-plus"></i> Criar Pedido
                        </a>
                ';
                
                if (($mesa['tipo_atendimento'] ?? '') === 'comanda' && !empty($mesa['cliente_nome'])) {
                    $html .= '
                        <button onclick="desvincularComanda(\'' . $mesa['id_mesa'] . '\')" class="btn btn-outline-danger mt-2 ms-2">
                            <i class="fas fa-unlink"></i> Desvincular Comanda
                        </button>
                    ';
                }
                
                $html .= '
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
                                    ' . (!empty($pedido['usuario_nome']) ? '<br><small class="text-info"><i class="fas fa-user"></i> ' . htmlspecialchars($pedido['usuario_nome']) . '</small>' : '') . '
                                    ' . (!empty($pedido['tenant_nome']) ? '<br><small class="text-secondary"><i class="fas fa-building"></i> ' . htmlspecialchars($pedido['tenant_nome']) . '</small>' : '') . '
                                </div>
                            </div>
';
                    
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
                            </div>
                            
                            <div class="card-footer">
                                <div class="actions d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary btn-sm" onclick="editarPedido(' . $pedido['idpedido'] . ')">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="imprimirPedidoMesa(' . $pedido['idpedido'] . ')" title="Imprimir Pedido">
                                        <i class="fas fa-print"></i> Imprimir
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
                        
                        <div class="text-center mt-3 mb-3">
                            <a href="index.php?view=gerar_pedido&mesa=' . $mesa['id_mesa'] . '" class="btn btn-primary btn-lg shadow-sm">
                                <i class="fas fa-plus-circle me-2"></i> Adicionar Pedido
                            </a>
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
            $valorDesconto = (float)($_POST['valor_desconto'] ?? 0);
            $tipoDesconto = $_POST['tipo_desconto'] ?? 'valor_fixo'; // 'valor_fixo' ou 'percentual'
            
            if (empty($mesaId) || empty($formaPagamento)) {
                throw new \Exception('Mesa e forma de pagamento são obrigatórios');
            }
            
            // Get all open pedidos for this mesa (excluindo finalizados, cancelados e quitados)
            $pedidos = $db->fetchAll(
                'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND status_pagamento != ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, 'Finalizado', 'Cancelado', 'quitado', $tenantId, $filialId]
            );
            
            if (empty($pedidos)) {
                throw new \Exception('Nenhum pedido aberto encontrado para esta mesa');
            }
            
            $valorTotal = 0;
            $valorPorPessoa = 0;
            $totalDescontoAplicado = 0;

            // Calcular valor total primeiro
            foreach ($pedidos as $pedido) {
                $valorTotal += (float)($pedido['saldo_devedor'] ?? $pedido['valor_total']);
            }

            // Calcular desconto sobre o valor total da mesa
            if ($valorDesconto > 0) {
                if ($tipoDesconto === 'percentual') {
                    $totalDescontoAplicado = $valorTotal * ($valorDesconto / 100);
                } else {
                    $totalDescontoAplicado = $valorDesconto;
                }
            }

            $valorTotalComDesconto = $valorTotal - $totalDescontoAplicado;

            // Close all pedidos - usar saldo_devedor em vez de valor_total
            foreach ($pedidos as $pedido) {
                $valorPedido = (float)($pedido['saldo_devedor'] ?? $pedido['valor_total']);

                // Calcular desconto proporcional para este pedido
                $descontoPedido = 0;
                if ($totalDescontoAplicado > 0 && $valorTotal > 0) {
                    $descontoPedido = ($valorPedido / $valorTotal) * $totalDescontoAplicado;
                }

                $valorPagoPedido = $valorPedido - $descontoPedido;

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

                // Registrar desconto se houver
                if ($descontoPedido > 0) {
                    $db->insert('descontos_aplicados', [
                        'pedido_id' => $pedido['idpedido'],
                        'tipo_desconto_id' => resolverTipoDescontoManual($db, $tenant['id'], $filial['id']),
                        'valor_desconto' => $descontoPedido,
                        'motivo' => 'Desconto aplicado no fechamento da mesa',
                        'autorizado_por' => $user['id'],
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId
                    ]);

                    // Registrar no audit_logs
                    $db->insert('audit_logs', [
                        'tenant_id' => $tenantId,
                        'usuario_id' => $user['id'],
                        'acao' => 'aplicar_desconto_mesa_multiplos',
                        'entidade' => 'pedido',
                        'entidade_id' => $pedido['idpedido'],
                        'dados_anteriores' => json_encode(['valor_pedido' => $valorPedido]),
                        'dados_novos' => json_encode([
                            'desconto_proporcional' => $descontoPedido,
                            'valor_final' => $valorPagoPedido,
                            'mesa_id' => $mesaId
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                }

                // Create payment record for each pedido
                $db->insert('pagamentos', [
                    'pedido_id' => $pedido['idpedido'],
                    'valor_pago' => $valorPagoPedido,
                    'desconto_aplicado' => $descontoPedido,
                    'forma_pagamento' => $formaPagamento,
                    'numero_pessoas' => $numeroPessoas,
                    'observacao' => $observacao,
                    'usuario_id' => $user['id'],
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            $valorPorPessoa = $valorTotalComDesconto / $numeroPessoas;
            
            // Free the mesa
            $db->update(
                'mesas',
                ['status' => 'livre', 'cliente_nome' => null, 'cliente_telefone' => null],
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
            
            // Tentar criar ou buscar cliente global se telefone for informado
            $usuarioGlobalId = $pedido['usuario_global_id'] ?? null;
            if (!empty($telefoneCliente)) {
                require_once __DIR__ . '/../../mvc/controller/ClienteController.php';
                $clienteController = new \MVC\Controller\ClienteController();
                $resultCliente = $clienteController->criarOuBuscarCliente([
                    'nome' => $nomeCliente,
                    'telefone' => $telefoneCliente
                ]);
                if ($resultCliente['success'] && !empty($resultCliente['cliente'])) {
                    $usuarioGlobalId = $resultCliente['cliente']['id'];
                }
            }
            
            // Atualizar status do pedido para 'Finalizado'
            $db->query(
                'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, nome_cliente = ?, telefone_cliente = ?, observacoes = ?, usuario_global_id = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                ['Finalizado', $formaPagamento, $valorPago, $nomeCliente, $telefoneCliente, $observacoes, $usuarioGlobalId, $pedidoId, $tenantId, $filialId]
            );
            
            // Verificar se a mesa pode ser liberada (excluindo entregues e quitados)
            $pedidosAtivosNaMesa = $db->fetch(
                'SELECT COUNT(*) as total FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ? AND NOT (status = ? AND status_pagamento = ?)',
                [$pedido['idmesa'], 'Finalizado', 'Cancelado', $tenantId, $filialId, 'Entregue', 'quitado']
            );
            
            if ($pedidosAtivosNaMesa['total'] == 0) {
                // Liberar a mesa se não houver mais pedidos ativos
                $db->query(
                    'UPDATE mesas SET status = ?, cliente_nome = NULL, cliente_telefone = NULL WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['livre', $pedido['idmesa'], $tenantId, $filialId]
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
            $observacao = $dadosFechamento['observacao'] ?? '';
            
            // Processar fechamento baseado no tipo
            if ($dadosFechamento['tipo'] === 'simples') {
                // Fechamento simples - uma forma de pagamento
                $formaPagamento = $dadosFechamento['formaPagamento'];
                $valorPago = $dadosFechamento['valorPago'] ?? $valorTotal;
                
                foreach ($pedidos as $pedido) {
                    $db->query(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamento, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->query(
                    'UPDATE mesas SET status = ?, cliente_nome = NULL, cliente_telefone = NULL WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['livre', $mesaId, $tenantId, $filialId]
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
                $valorPorPessoa = $valorTotalComDesconto / $numeroPessoas;
                
                foreach ($pedidos as $pedido) {
                    $db->query(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamento, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->query(
                    'UPDATE mesas SET status = ?, cliente_nome = NULL, cliente_telefone = NULL WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
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
                    $db->query(
                        'UPDATE pedido SET status = ?, forma_pagamento = ?, valor_pago = ?, observacoes = ? WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                        ['Finalizado', $formaPagamentoCompleta, $pedido['valor_total'], $observacao, $pedido['idpedido'], $tenantId, $filialId]
                    );
                }
                
                // Liberar a mesa
                $db->query(
                    'UPDATE mesas SET status = ?, cliente_nome = NULL, cliente_telefone = NULL WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    ['livre', $mesaId, $tenantId, $filialId]
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

