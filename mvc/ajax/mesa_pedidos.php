<?php
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

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $buscarMesa = $_GET['buscar_mesa'] ?? $_POST['buscar_mesa'] ?? '';
    $fecharPedido = $_GET['fechar_pedido'] ?? $_POST['fechar_pedido'] ?? '';
    $fecharMesa = $_GET['fechar_mesa'] ?? $_POST['fechar_mesa'] ?? '';
    $dividirPagamento = $_GET['dividir_pagamento'] ?? $_POST['dividir_pagamento'] ?? '';
    
    if ($buscarMesa == '1') {
        $action = 'buscar_mesa_pedidos';
    } elseif ($fecharPedido == '1') {
        $action = 'fechar_pedido_individual';
    } elseif ($fecharMesa == '1') {
        $action = 'fechar_mesa_completa';
    } elseif ($dividirPagamento == '1') {
        $action = 'dividir_pagamento';
    }
    
    $session = \System\Session::getInstance();
    $db = \System\Database::getInstance();
    
    $user = $session->getUser();
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    
    if (!$user || !$tenant || !$filial) {
        throw new \Exception('Sessão inválida');
    }
    
    switch ($action) {
        case 'buscar_mesa_pedidos':
            $mesaId = $_GET['mesa_id'] ?? $_POST['mesa_id'] ?? '';
            
            if (empty($mesaId)) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            // Get mesa info
            $mesa = $db->fetch(
                'SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, $tenant['id'], $filial['id']]
            );
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada');
            }
            
            // Get mesa_pedidos info
            $mesaPedidos = $db->fetch(
                'SELECT * FROM mesa_pedidos WHERE mesa_id = ? AND status = ? AND tenant_id = ? AND filial_id = ?',
                [$mesaId, 'aberta', $tenant['id'], $filial['id']]
            );
            
            // Get all pedidos for this mesa
            $pedidos = $db->fetchAll("
                SELECT p.*, 
                       COUNT(pi.id) as total_itens,
                       SUM(pi.valor_total) as valor_calculado
                FROM pedido p
                LEFT JOIN pedido_itens pi ON p.idpedido = pi.pedido_id
                WHERE p.idmesa = ? AND p.tenant_id = ? AND p.filial_id = ? 
                AND p.status NOT IN ('Finalizado', 'Cancelado')
                GROUP BY p.idpedido
                ORDER BY p.created_at ASC
            ", [$mesaId, $tenant['id'], $filial['id']]);
            
            // Get itens for each pedido
            foreach ($pedidos as &$pedido) {
                $itens = $db->fetchAll("
                    SELECT pi.*, pr.nome as produto_nome, pr.imagem as produto_imagem
                    FROM pedido_itens pi
                    LEFT JOIN produtos pr ON pi.produto_id = pr.id
                    WHERE pi.pedido_id = ? AND pi.tenant_id = ? AND pi.filial_id = ?
                    ORDER BY pi.id
                ", [$pedido['idpedido'], $tenant['id'], $filial['id']]);
                
                $pedido['itens'] = $itens;
            }
            
            echo json_encode([
                'success' => true,
                'mesa' => $mesa,
                'mesa_pedidos' => $mesaPedidos,
                'pedidos' => $pedidos
            ]);
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
            
            $db->beginTransaction();
            
            try {
                // Get pedido info
                $pedido = $db->fetch(
                    'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenant['id'], $filial['id']]
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
                    [$pedidoId, $tenant['id'], $filial['id']]
                );
                
                // Create payment record
                $db->insert('pagamentos', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $pedido['valor_total'],
                    'forma_pagamento' => $formaPagamento,
                    'numero_pessoas' => $numeroPessoas,
                    'valor_por_pessoa' => $valorPorPessoa,
                    'observacao' => $observacao,
                    'usuario_id' => $user['id'],
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id']
                ]);
                
                // Check if all pedidos for this mesa are closed
                $pedidosAbertos = $db->fetch(
                    'SELECT COUNT(*) as count FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                    [$pedido['idmesa'], 'Finalizado', 'Cancelado', $tenant['id'], $filial['id']]
                );
                
                if ($pedidosAbertos['count'] == 0) {
                    // All pedidos closed, update mesa_pedidos status
                    $db->update(
                        'mesa_pedidos',
                        ['status' => 'fechada', 'updated_at' => date('Y-m-d H:i:s')],
                        'mesa_id = ? AND status = ? AND tenant_id = ? AND filial_id = ?',
                        [$pedido['idmesa'], 'aberta', $tenant['id'], $filial['id']]
                    );
                    
                    // Free the mesa
                    $db->update(
                        'mesas',
                        ['status' => '1'],
                        'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                        [$pedido['idmesa'], $tenant['id'], $filial['id']]
                    );
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido fechado com sucesso!',
                    'valor_por_pessoa' => $valorPorPessoa
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'fechar_mesa_completa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $numeroPessoas = (int)($_POST['numero_pessoas'] ?? 1);
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($mesaId) || empty($formaPagamento)) {
                throw new \Exception('Mesa e forma de pagamento são obrigatórios');
            }
            
            $db->beginTransaction();
            
            try {
                // Get all open pedidos for this mesa
                $pedidos = $db->fetchAll(
                    'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                    [$mesaId, 'Finalizado', 'Cancelado', $tenant['id'], $filial['id']]
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
                    
                    // Create payment record for each pedido
                    $db->insert('pagamentos', [
                        'pedido_id' => $pedido['idpedido'],
                        'valor_pago' => $pedido['valor_total'],
                        'forma_pagamento' => $formaPagamento,
                        'numero_pessoas' => $numeroPessoas,
                        'observacao' => $observacao,
                        'usuario_id' => $user['id'],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial['id']
                    ]);
                }
                
                $valorPorPessoa = $valorTotal / $numeroPessoas;
                
                // Update mesa_pedidos status
                $db->update(
                    'mesa_pedidos',
                    ['status' => 'fechada', 'updated_at' => date('Y-m-d H:i:s')],
                    'mesa_id = ? AND status = ? AND tenant_id = ? AND filial_id = ?',
                    [$mesaId, 'aberta', $tenant['id'], $filial['id']]
                );
                
                // Free the mesa
                $db->update(
                    'mesas',
                    ['status' => '1'],
                    'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                    [$mesaId, $tenant['id'], $filial['id']]
                );
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mesa fechada com sucesso!',
                    'valor_total' => $valorTotal,
                    'valor_por_pessoa' => $valorPorPessoa,
                    'pedidos_fechados' => count($pedidos)
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'dividir_pagamento':
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
            $numeroPessoas = (int)($_POST['numero_pessoas'] ?? 1);
            
            if ($pedidoId <= 0 || $numeroPessoas <= 0) {
                throw new \Exception('Dados inválidos');
            }
            
            $pedido = $db->fetch(
                'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $tenant['id'], $filial['id']]
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
