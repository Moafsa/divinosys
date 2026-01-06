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

    // Migração temporária: adicionar coluna desconto_aplicado na tabela pagamentos
    try {
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'pagamentos' AND column_name = 'desconto_aplicado'");
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $pdo->exec("ALTER TABLE pagamentos ADD COLUMN desconto_aplicado DECIMAL(10,2) DEFAULT 0");
            $pdo->exec("COMMENT ON COLUMN pagamentos.desconto_aplicado IS 'Valor do desconto aplicado ao pagamento (seja fixo ou percentual)'");
            error_log("Migração executada: coluna desconto_aplicado adicionada à tabela pagamentos");
        }
    } catch (Exception $e) {
        error_log("Erro na migração desconto_aplicado: " . $e->getMessage());
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
            $valorDesconto = (float)($_POST['valor_desconto'] ?? 0);
            $tipoDesconto = $_POST['tipo_desconto'] ?? 'valor_fixo'; // 'valor_fixo' ou 'percentual'

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

                // Calcular desconto
                $valorDescontoAplicado = 0;
                if ($valorDesconto > 0) {
                    if ($tipoDesconto === 'percentual') {
                        $valorDescontoAplicado = $pedido['valor_total'] * ($valorDesconto / 100);
                    } else {
                        $valorDescontoAplicado = $valorDesconto;
                    }
                }

                $valorFinal = $pedido['valor_total'] - $valorDescontoAplicado;
                $valorPorPessoa = $valorFinal / $numeroPessoas;
                
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
                
                // Registrar desconto se houver
                $descontoId = null;
                if ($valorDescontoAplicado > 0) {
                    // Registrar desconto aplicado
                    $descontoId = $db->insert('descontos_aplicados', [
                        'pedido_id' => $pedidoId,
                        'tipo_desconto_id' => 1, // Tipo genérico para desconto manual (assumindo que existe)
                        'valor_desconto' => $valorDescontoAplicado,
                        'motivo' => 'Desconto aplicado no fechamento',
                        'autorizado_por' => $user['id'],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial['id']
                    ]);

                    // Registrar no audit_logs
                    $db->insert('audit_logs', [
                        'tenant_id' => $tenant['id'],
                        'usuario_id' => $user['id'],
                        'acao' => 'aplicar_desconto',
                        'entidade' => 'pedido',
                        'entidade_id' => $pedidoId,
                        'dados_anteriores' => json_encode(['valor_total' => $pedido['valor_total']]),
                        'dados_novos' => json_encode([
                            'valor_desconto' => $valorDescontoAplicado,
                            'tipo_desconto' => $tipoDesconto,
                            'valor_final' => $valorFinal
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                }

                // Create payment record
                $db->insert('pagamentos', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorFinal,
                    'desconto_aplicado' => $valorDescontoAplicado,
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
            $valorDesconto = (float)($_POST['valor_desconto'] ?? 0);
            $tipoDesconto = $_POST['tipo_desconto'] ?? 'valor_fixo'; // 'valor_fixo' ou 'percentual'

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
                $totalDescontoAplicado = 0;

                // Calcular valor total primeiro
                foreach ($pedidos as $pedido) {
                    $valorTotal += $pedido['valor_total'];
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

                // Close all pedidos
                foreach ($pedidos as $pedido) {
                    
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
                    
                    // Calcular proporcionalmente o desconto por pedido
                    $proporcao = $pedido['valor_total'] / $valorTotal;
                    $descontoPedido = $totalDescontoAplicado * $proporcao;
                    $valorFinalPedido = $pedido['valor_total'] - $descontoPedido;

                    // Registrar desconto se houver
                    if ($descontoPedido > 0) {
                        $db->insert('descontos_aplicados', [
                            'pedido_id' => $pedido['idpedido'],
                            'tipo_desconto_id' => 1, // Tipo genérico para desconto manual
                            'valor_desconto' => $descontoPedido,
                            'motivo' => 'Desconto aplicado no fechamento da mesa',
                            'autorizado_por' => $user['id'],
                            'tenant_id' => $tenant['id'],
                            'filial_id' => $filial['id']
                        ]);

                        // Registrar no audit_logs
                        $db->insert('audit_logs', [
                            'tenant_id' => $tenant['id'],
                            'usuario_id' => $user['id'],
                            'acao' => 'aplicar_desconto_mesa',
                            'entidade' => 'pedido',
                            'entidade_id' => $pedido['idpedido'],
                            'dados_anteriores' => json_encode(['valor_total' => $pedido['valor_total']]),
                            'dados_novos' => json_encode([
                                'desconto_proporcional' => $descontoPedido,
                                'valor_final' => $valorFinalPedido,
                                'mesa_id' => $mesaId
                            ]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);
                    }

                    // Create payment record for each pedido
                    $pagamentoId = $db->insert('pagamentos', [
                        'pedido_id' => $pedido['idpedido'],
                        'valor_pago' => $valorFinalPedido,
                        'desconto_aplicado' => $descontoPedido,
                        'forma_pagamento' => $formaPagamento,
                        'numero_pessoas' => $numeroPessoas,
                        'observacao' => $observacao,
                        'usuario_id' => $user['id'],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial['id']
                    ]);

                    // Registrar desconto no audit_logs
                    if ($descontoPedido > 0) {
                        $db->insert('audit_logs', [
                            'tenant_id' => $tenant['id'],
                            'usuario_id' => $user['id'],
                            'acao' => 'aplicar_desconto',
                            'entidade' => 'pagamento',
                            'entidade_id' => $pagamentoId,
                            'dados_novos' => json_encode([
                                'pedido_id' => $pedido['idpedido'],
                                'mesa_id' => $mesaId,
                                'valor_desconto_total' => $totalDescontoAplicado,
                                'valor_desconto_pedido' => $descontoPedido,
                                'tipo_desconto' => $tipoDesconto,
                                'valor_desconto_original' => $valorDesconto,
                                'valor_total_bruto' => $valorTotal,
                                'valor_final_pedido' => $valorFinalPedido
                            ]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);
                    }
                }
                
                $valorPorPessoa = $valorTotalComDesconto / $numeroPessoas;
                
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

        case 'aplicar_desconto_mesa':
            $mesaId = $_POST['mesa_id'] ?? '';
            $valorDesconto = (float)($_POST['valor_desconto'] ?? 0);
            $tipoDesconto = $_POST['tipo_desconto'] ?? 'valor_fixo';
            $descricao = $_POST['descricao'] ?? 'Desconto aplicado manualmente';

            if (empty($mesaId) || $valorDesconto <= 0) {
                throw new \Exception('Dados inválidos para aplicar desconto');
            }

            $db->beginTransaction();

            try {
                // Buscar todos os pedidos abertos da mesa
                $pedidos = $db->fetchAll(
                    'SELECT * FROM pedido WHERE idmesa = ? AND status NOT IN (?, ?) AND tenant_id = ? AND filial_id = ?',
                    [$mesaId, 'Finalizado', 'Cancelado', $tenant['id'], $filial['id']]
                );

                if (empty($pedidos)) {
                    throw new \Exception('Nenhum pedido aberto encontrado para esta mesa');
                }

                // Calcular valor total da mesa (recalculando saldo_devedor de cada pedido)
                $valorTotalMesa = 0;
                $saldosPedidos = [];
                
                foreach ($pedidos as $pedido) {
                    // Recalcular saldo_devedor atual considerando descontos e pagamentos já aplicados
                    $valorTotalPedido = (float)$pedido['valor_total'];
                    
                    // Buscar total de descontos já aplicados para este pedido
                    $descontosAnteriores = $db->fetch(
                        "SELECT COALESCE(SUM(valor_desconto), 0) as total 
                         FROM descontos_aplicados 
                         WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                        [$pedido['idpedido'], $tenant['id'], $filial['id']]
                    );
                    $totalDescontosAnteriores = (float)($descontosAnteriores['total'] ?? 0);
                    
                    // Buscar total pago (excluindo descontos)
                    $totalPagoResult = $db->fetch(
                        "SELECT COALESCE(SUM(CASE WHEN forma_pagamento != 'DESCONTO' THEN valor_pago ELSE 0 END), 0) as total 
                         FROM pagamentos_pedido 
                         WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                        [$pedido['idpedido'], $tenant['id'], $filial['id']]
                    );
                    $totalPago = (float)($totalPagoResult['total'] ?? 0);
                    
                    // Saldo devedor atual = valor_total - descontos_anteriores - pagamentos
                    $saldoDevedorAtual = max(0, $valorTotalPedido - $totalDescontosAnteriores - $totalPago);
                    $saldosPedidos[$pedido['idpedido']] = $saldoDevedorAtual;
                    $valorTotalMesa += $saldoDevedorAtual;
                }

                // Aplicar desconto proporcionalmente
                $totalDescontoAplicado = 0;
                foreach ($pedidos as $pedido) {
                    $valorPedido = $saldosPedidos[$pedido['idpedido']];
                    $proporcao = ($valorTotalMesa > 0) ? ($valorPedido / $valorTotalMesa) : (1 / count($pedidos));
                    $descontoPedido = $valorDesconto * $proporcao;
                    $novoSaldoPedido = $valorPedido - $descontoPedido;

                    // Registrar desconto aplicado
                    $db->insert('descontos_aplicados', [
                        'pedido_id' => $pedido['idpedido'],
                        'tipo_desconto_id' => 1,
                        'valor_desconto' => $descontoPedido,
                        'motivo' => $descricao,
                        'autorizado_por' => $user['id'],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial['id']
                    ]);

                    // Atualizar saldo_devedor do pedido
                    $db->update(
                        'pedido',
                        ['saldo_devedor' => $novoSaldoPedido > 0 ? $novoSaldoPedido : 0],
                        'idpedido = ?',
                        [$pedido['idpedido']]
                    );

                    // Registrar desconto no histórico de pagamentos
                    $db->insert('pagamentos_pedido', [
                        'pedido_id' => $pedido['idpedido'],
                        'valor_pago' => $descontoPedido,
                        'forma_pagamento' => 'DESCONTO',
                        'descricao' => $descricao . ' (Desconto aplicado)',
                        'usuario_id' => $user['id'],
                        'tenant_id' => $tenant['id'],
                        'filial_id' => $filial['id']
                    ]);

                    // Registrar no audit_logs
                    $db->insert('audit_logs', [
                        'tenant_id' => $tenant['id'],
                        'usuario_id' => $user['id'],
                        'acao' => 'aplicar_desconto_mesa',
                        'entidade' => 'pedido',
                        'entidade_id' => $pedido['idpedido'],
                        'dados_anteriores' => json_encode(['valor_pedido' => $valorPedido]),
                        'dados_novos' => json_encode([
                            'desconto_proporcional' => $descontoPedido,
                            'novo_saldo' => $novoSaldoPedido,
                            'mesa_id' => $mesaId,
                            'tipo_desconto' => $tipoDesconto
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    $totalDescontoAplicado += $descontoPedido;
                }

                // Verificar se saldo = 0 e fechar automaticamente
                $novoSaldoMesa = $valorTotalMesa - $totalDescontoAplicado;

                if ($novoSaldoMesa <= 0.01) {
                    // Fechar todos os pedidos e mesa
                    foreach ($pedidos as $pedido) {
                        $db->update(
                            'pedido',
                            ['status' => 'Finalizado', 'status_pagamento' => 'quitado'],
                            'idpedido = ?',
                            [$pedido['idpedido']]
                        );
                    }

                    $db->update(
                        'mesa_pedidos',
                        ['status' => 'fechada', 'updated_at' => date('Y-m-d H:i:s')],
                        'mesa_id = ? AND status = ? AND tenant_id = ? AND filial_id = ?',
                        [$mesaId, 'aberta', $tenant['id'], $filial['id']]
                    );

                    $db->update(
                        'mesas',
                        ['status' => '1'],
                        'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                        [$mesaId, $tenant['id'], $filial['id']]
                    );
                }

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Desconto aplicado com sucesso!',
                    'desconto_aplicado' => $totalDescontoAplicado,
                    'saldo_restante' => $novoSaldoMesa,
                    'mesa_fechada' => $novoSaldoMesa <= 0.01
                ]);

            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'aplicar_desconto_pedido':
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
            $valorDesconto = (float)($_POST['valor_desconto'] ?? 0);
            $tipoDesconto = $_POST['tipo_desconto'] ?? 'valor_fixo';
            $descricao = $_POST['descricao'] ?? 'Desconto aplicado manualmente';

            if ($pedidoId <= 0 || $valorDesconto <= 0) {
                throw new \Exception('Dados inválidos para aplicar desconto');
            }

            $db->beginTransaction();

            try {
                // Buscar pedido
                $pedido = $db->fetch(
                    'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenant['id'], $filial['id']]
                );

                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }

                // Recalcular saldo_devedor atual considerando descontos e pagamentos já aplicados
                $valorTotal = (float)$pedido['valor_total'];
                
                // Buscar total de descontos já aplicados
                $descontosAnteriores = $db->fetch(
                    "SELECT COALESCE(SUM(valor_desconto), 0) as total 
                     FROM descontos_aplicados 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenant['id'], $filial['id']]
                );
                $totalDescontosAnteriores = (float)($descontosAnteriores['total'] ?? 0);
                
                // Buscar total pago (excluindo descontos)
                $totalPagoResult = $db->fetch(
                    "SELECT COALESCE(SUM(CASE WHEN forma_pagamento != 'DESCONTO' THEN valor_pago ELSE 0 END), 0) as total 
                     FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenant['id'], $filial['id']]
                );
                $totalPago = (float)($totalPagoResult['total'] ?? 0);
                
                // Saldo devedor atual = valor_total - descontos_anteriores - pagamentos
                $saldoDevedorAtual = $valorTotal - $totalDescontosAnteriores - $totalPago;
                $valorPedido = max(0, $saldoDevedorAtual);

                // Registrar desconto aplicado
                $db->insert('descontos_aplicados', [
                    'pedido_id' => $pedidoId,
                    'tipo_desconto_id' => 1,
                    'valor_desconto' => $valorDesconto,
                    'motivo' => $descricao,
                    'autorizado_por' => $user['id'],
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id']
                ]);

                // Registrar desconto no histórico de pagamentos
                $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorDesconto,
                    'forma_pagamento' => 'DESCONTO',
                    'descricao' => $descricao . ' (Desconto aplicado)',
                    'usuario_id' => $user['id'],
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id']
                ]);

                // Registrar no audit_logs
                $db->insert('audit_logs', [
                    'tenant_id' => $tenant['id'],
                    'usuario_id' => $user['id'],
                    'acao' => 'aplicar_desconto_pedido',
                    'entidade' => 'pedido',
                    'entidade_id' => $pedidoId,
                    'dados_anteriores' => json_encode(['valor_pedido' => $valorPedido]),
                    'dados_novos' => json_encode([
                        'desconto' => $valorDesconto,
                        'tipo_desconto' => $tipoDesconto
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                // Verificar se saldo = 0 e fechar automaticamente
                $novoSaldo = $valorPedido - $valorDesconto;

                // Atualizar saldo_devedor do pedido
                $db->update(
                    'pedido',
                    ['saldo_devedor' => $novoSaldo > 0 ? $novoSaldo : 0],
                    'idpedido = ?',
                    [$pedidoId]
                );

                if ($novoSaldo <= 0.01) {
                    // Fechar pedido
                    $db->update(
                        'pedido',
                        ['status' => 'Finalizado', 'status_pagamento' => 'quitado'],
                        'idpedido = ?',
                        [$pedidoId]
                    );
                }

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Desconto aplicado com sucesso!',
                    'desconto_aplicado' => $valorDesconto,
                    'saldo_restante' => $novoSaldo,
                    'pedido_fechado' => $novoSaldo <= 0.01
                ]);

            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
