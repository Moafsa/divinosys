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
    $buscarPedido = $_GET['buscar_pedido'] ?? $_POST['buscar_pedido'] ?? '';
    $atualizarStatus = $_GET['atualizar_status'] ?? $_POST['atualizar_status'] ?? '';
    $excluirPedido = $_GET['excluir_pedido'] ?? $_POST['excluir_pedido'] ?? '';
    $fecharMesa = $_GET['fechar_mesa'] ?? $_POST['fechar_mesa'] ?? '';
    $fecharPedido = $_GET['fechar_pedido'] ?? $_POST['fechar_pedido'] ?? '';
    $atualizarObservacao = $_GET['atualizar_observacao'] ?? $_POST['atualizar_observacao'] ?? '';
    $alterarQuantidade = $_GET['alterar_quantidade'] ?? $_POST['alterar_quantidade'] ?? '';
    $removerItem = $_GET['remover_item'] ?? $_POST['remover_item'] ?? '';
    $atualizarMesa = $_GET['atualizar_mesa'] ?? $_POST['atualizar_mesa'] ?? '';
    
    if ($buscarPedido == '1') {
        $action = 'buscar_pedido';
    } elseif ($atualizarStatus == '1') {
        $action = 'atualizar_status';
    } elseif ($excluirPedido == '1') {
        $action = 'excluir_pedido';
    } elseif ($fecharMesa == '1') {
        $action = 'fechar_mesa';
    } elseif ($fecharPedido == '1') {
        $action = 'fechar_pedido';
    } elseif ($atualizarObservacao == '1') {
        $action = 'atualizar_observacao';
    } elseif ($alterarQuantidade == '1') {
        $action = 'alterar_quantidade';
    } elseif ($removerItem == '1') {
        $action = 'remover_item';
    } elseif ($atualizarMesa == '1') {
        $action = 'atualizar_mesa';
    }
    
    switch ($action) {
        case 'criar_pedido':
            $mesaId = $_POST['mesa_id'] ?? '';
            $itens = json_decode($_POST['itens'] ?? '[]', true);
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($mesaId) || empty($itens)) {
                throw new \Exception('Mesa e itens são obrigatórios');
            }
            
            // Para delivery, usar um ID especial
            if ($mesaId === 'delivery') {
                $mesaId = '999'; // ID especial para delivery
            } elseif (strpos($mesaId, ',') !== false) {
                // Múltiplas mesas - usar a primeira como principal
                $mesas = explode(',', $mesaId);
                $mesaId = trim($mesas[0]); // Usar primeira mesa como ID principal
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            $usuarioId = $session->getUserId();
            
            // PostgreSQL gerencia sequências automaticamente - não precisamos interferir
            
            // Calcular valor total
            $valorTotal = 0;
            foreach ($itens as $item) {
                $valorTotal += $item['preco'] * $item['quantidade'];
            }
            
            // Criar pedido
            $pedidoId = $db->insert('pedido', [
                'idmesa' => $mesaId,
                'cliente' => 'Cliente Mesa',
                'data' => date('Y-m-d'),
                'hora_pedido' => date('H:i:s'),
                'valor_total' => $valorTotal,
                'valor_pago' => 0.00,
                'saldo_devedor' => $valorTotal,
                'status_pagamento' => 'pendente',
                'status' => 'Pendente',
                'observacao' => $observacao,
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'delivery' => ($mesaId === '999') ? 1 : 0
            ]);
            
            // Criar itens do pedido
            foreach ($itens as $item) {
                // Preparar ingredientes
                $ingredientesCom = [];
                $ingredientesSem = [];
                
                // Verificar ingredientes adicionados - tratar undefined/null/vazio
                if (isset($item['ingredientes_adicionados']) && 
                    $item['ingredientes_adicionados'] !== null && 
                    $item['ingredientes_adicionados'] !== 'undefined' &&
                    $item['ingredientes_adicionados'] !== '' &&
                    is_array($item['ingredientes_adicionados']) && 
                    !empty($item['ingredientes_adicionados'])) {
                    foreach ($item['ingredientes_adicionados'] as $ing) {
                        if (isset($ing['nome']) && !empty($ing['nome'])) {
                            $ingredientesCom[] = $ing['nome'];
                        }
                    }
                }
                
                // Verificar ingredientes removidos - tratar undefined/null/vazio
                if (isset($item['ingredientes_removidos']) && 
                    $item['ingredientes_removidos'] !== null && 
                    $item['ingredientes_removidos'] !== 'undefined' &&
                    $item['ingredientes_removidos'] !== '' &&
                    is_array($item['ingredientes_removidos']) && 
                    !empty($item['ingredientes_removidos'])) {
                    foreach ($item['ingredientes_removidos'] as $ing) {
                        if (isset($ing['nome']) && !empty($ing['nome'])) {
                            $ingredientesSem[] = $ing['nome'];
                        }
                    }
                }
                
                $db->insert('pedido_itens', [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['id'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['preco'],
                    'valor_total' => $item['preco'] * $item['quantidade'],
                    'tamanho' => $item['tamanho'] ?? 'normal',
                    'observacao' => $item['observacao'] ?? '',
                    'ingredientes_com' => implode(', ', $ingredientesCom),
                    'ingredientes_sem' => implode(', ', $ingredientesSem),
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            // Atualizar status da mesa para ocupada (apenas se não for delivery)
            if ($mesaId !== '999') {
                $db->update('mesas', ['status' => '2'], 'id_mesa = ? AND tenant_id = ? AND filial_id = ?', [$mesaId, $tenantId, $filialId]);
            }
            
            // Buscar dados do pedido criado
            $pedido = $db->fetch("SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?", [$pedidoId, $tenantId, $filialId]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Pedido criado com sucesso!',
                'pedido_id' => $pedidoId,
                'pedido' => $pedido
            ]);
            break;
            
        case 'buscar_pedido':
            try {
                $pedidoId = $_GET['pedido_id'] ?? $_POST['pedido_id'] ?? '';
                
                if (empty($pedidoId)) {
                    throw new \Exception('ID do pedido é obrigatório');
                }
                
                $db = \System\Database::getInstance();
                $session = \System\Session::getInstance();
                $tenantId = $session->getTenantId() ?? 1;
                $filialId = $session->getFilialId() ?? 1;
                
                error_log("Buscando pedido ID: $pedidoId, Tenant: $tenantId, Filial: $filialId");
            
                // Buscar dados do pedido
                $pedido = $db->fetch(
                    "SELECT p.*, 
                            CASE 
                                WHEN p.idmesa = '999' THEN 'Delivery'
                                WHEN m.nome IS NOT NULL THEN m.nome
                                ELSE 'Mesa ' || p.idmesa
                            END as mesa_nome,
                            u.login as usuario_nome 
                     FROM pedido p 
                     LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
                     LEFT JOIN usuarios u ON p.usuario_id = u.id AND u.tenant_id = p.tenant_id
                     WHERE p.idpedido = ? AND p.tenant_id = ? AND p.filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Debug log
                error_log("Pedido encontrado: " . json_encode($pedido));
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            // Buscar itens do pedido
            $itens = $db->fetchAll(
                "SELECT pi.*, 
                        pr.nome as nome_produto,
                        pr.preco_normal as preco_produto
                 FROM pedido_itens pi 
                 LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = ?
                 WHERE pi.pedido_id = ? AND pi.tenant_id = ?",
                [$filialId, $pedidoId, $tenantId]
            );
            
            // Processar ingredientes para cada item
            foreach ($itens as &$item) {
                error_log("Item antes do processamento: " . json_encode($item));
                
                if (!empty($item['ingredientes_com']) && trim($item['ingredientes_com']) !== '') {
                    $item['ingredientes_com'] = explode(', ', $item['ingredientes_com']);
                } else {
                    $item['ingredientes_com'] = [];
                }
                
                if (!empty($item['ingredientes_sem']) && trim($item['ingredientes_sem']) !== '') {
                    $item['ingredientes_sem'] = explode(', ', $item['ingredientes_sem']);
                } else {
                    $item['ingredientes_sem'] = [];
                }
                
                error_log("Item após processamento: " . json_encode($item));
            }
            
            $pedido['itens'] = $itens;
            
                echo json_encode([
                    'success' => true,
                    'pedido' => $pedido,
                    'itens' => $itens
                ]);
                
            } catch (\Exception $e) {
                error_log("Erro ao buscar pedido: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao carregar dados do pedido: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'atualizar_status':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $novoStatus = $_POST['status'] ?? '';
            
            if (empty($pedidoId) || empty($novoStatus)) {
                throw new \Exception('ID do pedido e status são obrigatórios');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Buscar dados do pedido para obter a mesa
            $pedido = $db->fetch(
                "SELECT idmesa FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            // Atualizar status do pedido
            $db->update(
                'pedido',
                ['status' => $novoStatus],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            // Sincronizar status da mesa após atualização do pedido
            if ($pedido['idmesa'] && $pedido['idmesa'] !== '999') {
                // Verificar se há outros pedidos ativos para esta mesa
                $pedidosAtivos = $db->fetchAll(
                    "SELECT COUNT(*) as total FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')",
                    [$pedido['idmesa'], $tenantId, $filialId]
                );
                
                $novoStatusMesa = $pedidosAtivos[0]['total'] > 0 ? 'ocupada' : 'livre';
                
                // Atualizar status da mesa
                $db->update(
                    'mesas',
                    ['status' => $novoStatusMesa],
                    'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedido['idmesa'], $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Status atualizado com sucesso!'
            ]);
            break;
            
        case 'buscar_pedido':
            $pedidoId = $_GET['pedido_id'] ?? $_POST['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Buscar pedido
            $pedido = $db->fetch(
                "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
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
            
        case 'atualizar_pedido':
            $pedidoId = $_POST['id'] ?? '';
            $cliente = $_POST['cliente'] ?? '';
            $telefoneCliente = $_POST['telefone_cliente'] ?? '';
            $valorTotal = $_POST['valor_total'] ?? '';
            $status = $_POST['status'] ?? '';
            $statusPagamento = $_POST['status_pagamento'] ?? '';
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($pedidoId) || empty($cliente) || empty($valorTotal)) {
                throw new \Exception('Campos obrigatórios não preenchidos');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Verificar se pedido existe
            $pedidoAtual = $db->fetch(
                "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedidoAtual) {
                throw new \Exception('Pedido não encontrado');
            }
            
            $valorTotal = (float) $valorTotal;
            if ($valorTotal <= 0) {
                throw new \Exception('Valor total deve ser maior que zero');
            }
            
            // Calcular saldo devedor baseado no novo valor total
            $valorPago = (float) ($pedidoAtual['valor_pago'] ?? 0);
            $novoSaldoDevedor = $valorTotal - $valorPago;
            
            // Atualizar pedido
            $atualizado = $db->update('pedido', [
                'cliente' => $cliente,
                'telefone_cliente' => $telefoneCliente,
                'valor_total' => $valorTotal,
                'saldo_devedor' => $novoSaldoDevedor,
                'status' => $status,
                'status_pagamento' => $statusPagamento,
                'observacao' => $observacao,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'idpedido = ? AND tenant_id = ? AND filial_id = ?', [$pedidoId, $tenantId, $filialId]);
            
            if (!$atualizado) {
                throw new \Exception('Erro ao atualizar pedido');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pedido atualizado com sucesso!'
            ]);
            break;
            
        case 'excluir_pedido':
            $pedidoId = $_POST['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Buscar dados do pedido para liberar a mesa
            $pedido = $db->fetch(
                "SELECT idmesa FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                [$pedidoId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido não encontrado');
            }
            
            // Excluir itens do pedido (cascade)
            $db->delete('pedido_itens', 'pedido_id = ? AND tenant_id = ?', [$pedidoId, $tenantId]);
            
            // Excluir pedido
            $db->delete('pedido', 'idpedido = ? AND tenant_id = ? AND filial_id = ?', [$pedidoId, $tenantId, $filialId]);
            
            // Liberar mesa se existir
            if ($pedido['idmesa']) {
                $db->update(
                    'mesas',
                    ['status' => '1'],
                    'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedido['idmesa'], $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pedido excluído com sucesso!'
            ]);
            break;
            
        case 'atualizar_observacao':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Atualizar observação do pedido
            $db->update(
                'pedido',
                ['observacao' => $observacao],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Observação atualizada com sucesso!'
            ]);
            break;
            
        case 'alterar_quantidade':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $itemId = $_POST['item_id'] ?? '';
            $quantidade = (int) ($_POST['quantidade'] ?? 0);
            
            if (empty($pedidoId) || empty($itemId) || $quantidade <= 0) {
                throw new \Exception('Dados inválidos para alterar quantidade');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // Buscar item do pedido
            $item = $db->fetch(
                "SELECT * FROM pedido_itens WHERE id = ? AND pedido_id = ? AND tenant_id = ?",
                [$itemId, $pedidoId, $tenantId]
            );
            
            if (!$item) {
                throw new \Exception('Item não encontrado');
            }
            
            $novoValorTotal = $item['valor_unitario'] * $quantidade;
            
            // Atualizar quantidade e valor total do item
            $db->update(
                'pedido_itens',
                [
                    'quantidade' => $quantidade,
                    'valor_total' => $novoValorTotal
                ],
                'id = ? AND pedido_id = ? AND tenant_id = ?',
                [$itemId, $pedidoId, $tenantId]
            );
            
            // Recalcular valor total do pedido
            $totalPedido = $db->fetch(
                "SELECT SUM(valor_total) as total FROM pedido_itens WHERE pedido_id = ? AND tenant_id = ?",
                [$pedidoId, $tenantId]
            );
            
            $db->update(
                'pedido',
                ['valor_total' => $totalPedido['total']],
                'idpedido = ? AND tenant_id = ?',
                [$pedidoId, $tenantId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Quantidade atualizada com sucesso!'
            ]);
            break;
            
        case 'remover_item':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $itemId = $_POST['item_id'] ?? '';
            
            if (empty($pedidoId) || empty($itemId)) {
                throw new \Exception('ID do pedido e item são obrigatórios');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // Excluir item do pedido
            $db->delete(
                'pedido_itens',
                'id = ? AND pedido_id = ? AND tenant_id = ?',
                [$itemId, $pedidoId, $tenantId]
            );
            
            // Recalcular valor total do pedido
            $totalPedido = $db->fetch(
                "SELECT SUM(valor_total) as total FROM pedido_itens WHERE pedido_id = ? AND tenant_id = ?",
                [$pedidoId, $tenantId]
            );
            
            $novoTotal = $totalPedido['total'] ?? 0;
            
            $db->update(
                'pedido',
                ['valor_total' => $novoTotal],
                'idpedido = ? AND tenant_id = ?',
                [$pedidoId, $tenantId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Item removido com sucesso!'
            ]);
            break;
            
        case 'atualizar_pedido':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $mesaId = $_POST['mesa_id'] ?? '';
            $itens = json_decode($_POST['itens'] ?? '[]', true);
            $observacao = $_POST['observacao'] ?? '';
            
            // Debug: Log dos dados recebidos
            error_log("=== ATUALIZAR PEDIDO DEBUG ===");
            error_log("Pedido ID: " . $pedidoId);
            error_log("Mesa ID: " . $mesaId);
            error_log("Itens recebidos: " . json_encode($itens));
            error_log("Observação: " . $observacao);
            
            if (empty($pedidoId) || empty($mesaId) || empty($itens)) {
                throw new \Exception('Dados obrigatórios não fornecidos');
            }
            
            // Para delivery, usar um ID especial
            if ($mesaId === 'delivery') {
                $mesaId = '999'; // ID especial para delivery
            } elseif (strpos($mesaId, ',') !== false) {
                // Múltiplas mesas - usar a primeira como principal
                $mesas = explode(',', $mesaId);
                $mesaId = trim($mesas[0]); // Usar primeira mesa como ID principal
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Calcular novo valor total
            $valorTotal = 0;
            foreach ($itens as $item) {
                $valorTotal += $item['preco'] * $item['quantidade'];
            }
            
            // Atualizar dados do pedido
            $db->update(
                'pedido',
                [
                    'idmesa' => $mesaId,
                    'valor_total' => $valorTotal,
                    'observacao' => $observacao
                ],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
            // Remover itens antigos
            $db->delete('pedido_itens', 'pedido_id = ? AND tenant_id = ?', [$pedidoId, $tenantId]);
            
            // Adicionar novos itens
            foreach ($itens as $index => $item) {
                // Debug: Log do item
                error_log("Processando item $index: " . json_encode($item));
                
                // Preparar ingredientes
                $ingredientesCom = [];
                $ingredientesSem = [];
                
                if (isset($item['ingredientes_adicionados']) && is_array($item['ingredientes_adicionados'])) {
                    foreach ($item['ingredientes_adicionados'] as $ing) {
                        if (is_string($ing)) {
                            $ingredientesCom[] = $ing;
                        } elseif (is_array($ing) && isset($ing['nome'])) {
                            $ingredientesCom[] = $ing['nome'];
                        }
                    }
                }
                
                if (isset($item['ingredientes_removidos']) && is_array($item['ingredientes_removidos'])) {
                    foreach ($item['ingredientes_removidos'] as $ing) {
                        if (is_string($ing)) {
                            $ingredientesSem[] = $ing;
                        } elseif (is_array($ing) && isset($ing['nome'])) {
                            $ingredientesSem[] = $ing['nome'];
                        }
                    }
                }
                
                error_log("Ingredientes COM: " . implode(', ', $ingredientesCom));
                error_log("Ingredientes SEM: " . implode(', ', $ingredientesSem));
                
                $db->insert('pedido_itens', [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['id'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['preco'],
                    'valor_total' => $item['preco'] * $item['quantidade'],
                    'tamanho' => $item['tamanho'] ?? 'normal',
                    'observacao' => $item['observacao'] ?? '',
                    'ingredientes_com' => implode(', ', $ingredientesCom),
                    'ingredientes_sem' => implode(', ', $ingredientesSem),
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pedido atualizado com sucesso!'
            ]);
            break;
            
        case 'atualizar_mesa':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $mesaId = $_POST['mesa_id'] ?? '';
            
            if (empty($pedidoId) || empty($mesaId)) {
                throw new \Exception('ID do pedido e mesa são obrigatórios');
            }
            
            // Para delivery, usar um ID especial
            if ($mesaId === '999') {
                // Já é delivery, manter
            } elseif (strpos($mesaId, ',') !== false) {
                // Múltiplas mesas - usar a primeira como principal
                $mesas = explode(',', $mesaId);
                $mesaId = trim($mesas[0]); // Usar primeira mesa como ID principal
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Atualizar mesa do pedido
            $db->update(
                'pedido',
                ['idmesa' => $mesaId],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenantId, $filialId]
            );
            
        echo json_encode([
            'success' => true,
            'message' => 'Mesa do pedido atualizada com sucesso!'
        ]);
        break;

    case 'fechar_mesa':
        $mesaId = $_POST['mesa_id'] ?? '';
        
        if (empty($mesaId)) {
            throw new \Exception('ID da mesa é obrigatório');
        }
        
        $db = \System\Database::getInstance();
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId() ?? 1;
        $filialId = $session->getFilialId() ?? 1;
        
        // Buscar pedidos ativos da mesa (excluindo quitados)
        $pedidos = $db->fetchAll(
            "SELECT * FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') AND status_pagamento != 'quitado'",
            [$mesaId, $tenantId, $filialId]
        );
        
        if (empty($pedidos)) {
            throw new \Exception('Nenhum pedido ativo encontrado para esta mesa');
        }
        
        // Atualizar status de todos os pedidos para 'Finalizado'
        foreach ($pedidos as $pedido) {
            $db->update(
                'pedido',
                ['status' => 'Finalizado'],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedido['idpedido'], $tenantId, $filialId]
            );
        }
        
        // Atualizar status da mesa para livre (se não for delivery)
        if ($mesaId !== '999') {
            $db->update(
                'mesas',
                ['status' => '1'],
                'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, $tenantId, $filialId]
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Mesa fechada com sucesso! ' . count($pedidos) . ' pedido(s) finalizado(s).'
        ]);
        break;

    case 'fechar_pedido':
        $pedidoId = $_POST['pedido_id'] ?? '';
        $formaPagamento = $_POST['forma_pagamento'] ?? '';
        $trocoPara = $_POST['troco_para'] ?? '';
        $observacaoFechamento = $_POST['observacao_fechamento'] ?? '';
        
        if (empty($pedidoId) || empty($formaPagamento)) {
            throw new \Exception('ID do pedido e forma de pagamento são obrigatórios');
        }
        
        $db = \System\Database::getInstance();
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId() ?? 1;
        $filialId = $session->getFilialId() ?? 1;
        
        // Buscar pedido
        $pedido = $db->fetch(
            "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
            [$pedidoId, $tenantId, $filialId]
        );
        
        if (!$pedido) {
            throw new \Exception('Pedido não encontrado');
        }
        
        // Atualizar pedido
        $db->update(
            'pedido',
            [
                'status' => 'Finalizado',
                'forma_pagamento' => $formaPagamento,
                'troco_para' => $trocoPara ?: null,
                'observacao' => $observacaoFechamento ? ($pedido['observacao'] . "\n\nFechamento: " . $observacaoFechamento) : $pedido['observacao']
            ],
            'idpedido = ? AND tenant_id = ? AND filial_id = ?',
            [$pedidoId, $tenantId, $filialId]
        );
        
        // Se não for delivery, liberar a mesa
        if ($pedido['idmesa'] !== '999') {
            $db->update(
                'mesas',
                ['status' => '1'],
                'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [$pedido['idmesa'], $tenantId, $filialId]
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Pedido fechado com sucesso!'
        ]);
        break;

    case 'fechar_pedido_delivery':
        $pedidoId = $_POST['pedido_id'] ?? '';
        $formaPagamento = $_POST['forma_pagamento'] ?? '';
        $trocoPara = $_POST['troco_para'] ?? '';
        $observacaoFechamento = $_POST['observacao_fechamento'] ?? '';

        if (empty($pedidoId) || empty($formaPagamento)) {
            throw new \Exception('ID do pedido e forma de pagamento são obrigatórios');
        }

        $db = \System\Database::getInstance();
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId() ?? 1;
        $filialId = $session->getFilialId() ?? 1;

        // Buscar pedido de delivery
        $pedido = $db->fetch(
            "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ? AND delivery = true",
            [$pedidoId, $tenantId, $filialId]
        );

        if (!$pedido) {
            throw new \Exception('Pedido de delivery não encontrado');
        }

        if ($pedido['status'] === 'Finalizado' || $pedido['status'] === 'Cancelado') {
            throw new \Exception('Pedido já foi finalizado ou cancelado');
        }

        // Atualizar pedido de delivery
        $db->update(
            'pedido',
            [
                'status' => 'Finalizado',
                'forma_pagamento' => $formaPagamento,
                'troco_para' => $trocoPara ?: null,
                'observacao' => $observacaoFechamento ? ($pedido['observacao'] . "\n\nFechamento: " . $observacaoFechamento) : $pedido['observacao']
            ],
            'idpedido = ? AND tenant_id = ? AND filial_id = ?',
            [$pedidoId, $tenantId, $filialId]
        );

        // Não liberar mesa para delivery (mesmo que tenha mesa_id, é delivery)

        echo json_encode([
            'success' => true,
            'message' => 'Pedido de delivery fechado com sucesso!'
        ]);
        break;

        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>