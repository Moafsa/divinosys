<?php

namespace MVC\Model;

use System\Database;

class Cliente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new client
     */
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Insert client data - convert empty strings to NULL for date fields
            $clienteId = $this->db->insert('usuarios_globais', [
                'nome' => $data['nome'],
                'telefone' => $data['telefone'] ?? null,
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'data_nascimento' => !empty($data['data_nascimento']) ? $data['data_nascimento'] : null,
                'telefone_secundario' => $data['telefone_secundario'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'ativo' => true
            ]);

            // Create client history record (without tenant/filial since they don't exist in usuarios_globais)
            $this->registrarHistorico($clienteId, null, null, 'cadastro', 'Cliente cadastrado no sistema', null, $data);

            $this->db->commit();
            return ['success' => true, 'id' => $clienteId, 'message' => 'Cliente cadastrado com sucesso'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage()];
        }
    }

    /**
     * Find client by phone number
     */
    public function findByTelefone($telefone)
    {
        try {
            error_log("Cliente::findByTelefone - Buscando cliente com telefone: " . $telefone);
            $cliente = $this->db->fetch(
                "SELECT * FROM usuarios_globais WHERE telefone = ? AND ativo = true",
                [$telefone]
            );
            error_log("Cliente::findByTelefone - Resultado: " . ($cliente ? json_encode($cliente) : 'null'));
            return $cliente;
        } catch (Exception $e) {
            error_log("Cliente::findByTelefone - Erro: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find client by email
     */
    public function findByEmail($email)
    {
        try {
            $cliente = $this->db->fetch(
                "SELECT * FROM usuarios_globais WHERE email = ? AND ativo = true",
                [$email]
            );
            return $cliente;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get client by ID
     */
    public function getById($id)
    {
        try {
            $cliente = $this->db->fetch(
                "SELECT * FROM usuarios_globais WHERE id = ? AND ativo = true",
                [$id]
            );
            return $cliente;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update client data
     */
    public function update($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Get current data for history
            $clienteAtual = $this->getById($id);
            if (!$clienteAtual) {
                throw new Exception('Cliente não encontrado');
            }

            // Update client data (remove tenant_id and filial_id as they don't exist in usuarios_globais)
            // Convert empty strings to NULL for date fields
            $updateData = $data;
            unset($updateData['tenant_id'], $updateData['filial_id']);
            
            // Convert empty date fields to NULL
            if (isset($updateData['data_nascimento']) && empty($updateData['data_nascimento'])) {
                $updateData['data_nascimento'] = null;
            }
            
            $this->db->update('usuarios_globais', $updateData, 'id = ?', [$id]);

            // Register history
            $this->registrarHistorico($id, null, null, 'atualizacao', 'Dados do cliente atualizados', $clienteAtual, $data);

            $this->db->commit();
            return ['success' => true, 'message' => 'Cliente atualizado com sucesso'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Erro ao atualizar cliente: ' . $e->getMessage()];
        }
    }

    /**
     * Get all clients with pagination
     */
    public function getAll($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $where = ['ug.ativo = true', "ug.tipo_usuario = 'cliente'"];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = "(ug.nome ILIKE ? OR ug.telefone ILIKE ? OR ug.email ILIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Removed tenant_id filter - clients are global across all tenants
            // if (!empty($filters['tenant_id'])) {
            //     $where[] = "EXISTS (SELECT 1 FROM cliente_estabelecimentos ce WHERE ce.usuario_global_id = ug.id AND ce.tenant_id = ?)";
            //     $params[] = $filters['tenant_id'];
            // }

            $whereClause = implode(' AND ', $where);

            $clientes = $this->db->fetchAll(
                "SELECT ug.*, 
                        COUNT(DISTINCT p.idpedido) as total_pedidos,
                        COALESCE(SUM(p.valor_total), 0) as total_gasto,
                        MAX(p.created_at) as ultimo_pedido,
                        COUNT(DISTINCT pag.pedido_id) as pedidos_pagos,
                        COALESCE(SUM(pag.valor_pago), 0) as total_pago
                 FROM usuarios_globais ug
                 LEFT JOIN pedido p ON ug.id = p.usuario_global_id
                 LEFT JOIN pagamentos_pedido pag ON ug.id = pag.usuario_global_id
                 WHERE $whereClause
                 GROUP BY ug.id
                 ORDER BY ug.nome
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            );

            return $clientes;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get client order history
     */
    public function getHistoricoPedidos($clienteId, $tenantId = null, $limit = 20)
    {
        try {
            // Get orders where client is the original order owner
            $where = ['p.usuario_global_id = ?'];
            $params = [$clienteId];

            if ($tenantId) {
                $where[] = 'p.tenant_id = ?';
                $params[] = $tenantId;
            }

            $whereClause = implode(' AND ', $where);

            $pedidosOriginais = $this->db->fetchAll(
                "SELECT p.*, 
                        t.nome as tenant_nome,
                        f.nome as filial_nome,
                        COUNT(pi.id) as total_itens,
                        'original' as tipo_relacao
                 FROM pedido p
                 LEFT JOIN tenants t ON p.tenant_id = t.id
                 LEFT JOIN filiais f ON p.filial_id = f.id
                 LEFT JOIN pedido_itens pi ON p.idpedido = pi.pedido_id
                 WHERE $whereClause
                 GROUP BY p.idpedido, t.nome, f.nome",
                $params
            );

            // Get orders where client made payments (even if not the original owner)
            $wherePagamentos = ['pag.usuario_global_id = ?'];
            $paramsPagamentos = [$clienteId];

            if ($tenantId) {
                $wherePagamentos[] = 'pag.tenant_id = ?';
                $paramsPagamentos[] = $tenantId;
            }

            $wherePagamentosClause = implode(' AND ', $wherePagamentos);

            $pedidosPagos = $this->db->fetchAll(
                "SELECT DISTINCT p.*, 
                        t.nome as tenant_nome,
                        f.nome as filial_nome,
                        COUNT(pi.id) as total_itens,
                        'pagamento' as tipo_relacao,
                        SUM(pag.valor_pago) as valor_pago_pelo_cliente
                 FROM pedido p
                 LEFT JOIN tenants t ON p.tenant_id = t.id
                 LEFT JOIN filiais f ON p.filial_id = f.id
                 LEFT JOIN pedido_itens pi ON p.idpedido = pi.pedido_id
                 INNER JOIN pagamentos_pedido pag ON p.idpedido = pag.pedido_id
                 WHERE $wherePagamentosClause
                 GROUP BY p.idpedido, t.nome, f.nome
                 ORDER BY p.created_at DESC",
                $paramsPagamentos
            );

            // Combine and deduplicate orders
            $todosPedidos = [];
            $pedidosIds = [];

            // Add original orders first
            foreach ($pedidosOriginais as $pedido) {
                $todosPedidos[] = $pedido;
                $pedidosIds[] = $pedido['idpedido'];
            }

            // Add paid orders that are not already in the list
            foreach ($pedidosPagos as $pedido) {
                if (!in_array($pedido['idpedido'], $pedidosIds)) {
                    $todosPedidos[] = $pedido;
                }
            }

            // Sort by creation date and limit
            usort($todosPedidos, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return array_slice($todosPedidos, 0, $limit);

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get client payment history
     */
    public function getHistoricoPagamentos($clienteId, $tenantId = null, $limit = 20)
    {
        try {
            $where = ['pag.usuario_global_id = ?'];
            $params = [$clienteId];

            if ($tenantId) {
                $where[] = 'pag.tenant_id = ?';
                $params[] = $tenantId;
            }

            $whereClause = implode(' AND ', $where);

            $pagamentos = $this->db->fetchAll(
                "SELECT pag.*, 
                        p.idpedido,
                        p.cliente as pedido_cliente,
                        t.nome as tenant_nome,
                        f.nome as filial_nome
                 FROM pagamentos_pedido pag
                 LEFT JOIN pedido p ON pag.pedido_id = p.idpedido
                 LEFT JOIN tenants t ON pag.tenant_id = t.id
                 LEFT JOIN filiais f ON pag.filial_id = f.id
                 WHERE $whereClause
                 ORDER BY pag.created_at DESC
                 LIMIT ?",
                array_merge($params, [$limit])
            );

            return $pagamentos;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get establishments visited by client with real data
     */
    public function getEstabelecimentosVisitados($clienteId)
    {
        try {
            // Simplified approach: get establishments from both orders and payments
            $estabelecimentos = [];
            
            // 1. Get establishments from original orders
            $pedidosOriginais = $this->db->fetchAll(
                "SELECT DISTINCT 
                        p.tenant_id,
                        p.filial_id,
                        t.nome as tenant_nome,
                        f.nome as filial_nome,
                        COUNT(p.idpedido) as pedidos_originais,
                        SUM(p.valor_total) as total_gasto_original,
                        MAX(p.created_at) as ultima_visita
                 FROM pedido p
                 LEFT JOIN tenants t ON p.tenant_id = t.id
                 LEFT JOIN filiais f ON p.filial_id = f.id
                 WHERE p.usuario_global_id = ?
                 GROUP BY p.tenant_id, p.filial_id, t.nome, f.nome",
                [$clienteId]
            );
            
            // 2. Get establishments from payments
            $pagamentosEstabelecimentos = $this->db->fetchAll(
                "SELECT DISTINCT 
                        pag.tenant_id,
                        pag.filial_id,
                        t.nome as tenant_nome,
                        f.nome as filial_nome,
                        COUNT(DISTINCT pag.pedido_id) as pedidos_pagos,
                        SUM(pag.valor_pago) as total_pago,
                        MAX(pag.created_at) as ultima_visita
                 FROM pagamentos_pedido pag
                 LEFT JOIN tenants t ON pag.tenant_id = t.id
                 LEFT JOIN filiais f ON pag.filial_id = f.id
                 WHERE pag.usuario_global_id = ?
                 GROUP BY pag.tenant_id, pag.filial_id, t.nome, f.nome",
                [$clienteId]
            );
            
            // 3. Combine data
            $estabelecimentosMap = [];
            
            // Add original orders
            foreach ($pedidosOriginais as $est) {
                $key = $est['tenant_id'] . '_' . $est['filial_id'];
                $estabelecimentosMap[$key] = [
                    'tenant_id' => $est['tenant_id'],
                    'filial_id' => $est['filial_id'],
                    'tenant_nome' => $est['tenant_nome'],
                    'filial_nome' => $est['filial_nome'],
                    'pedidos_originais' => (int)$est['pedidos_originais'],
                    'pedidos_pagos' => 0,
                    'total_gasto_original' => (float)$est['total_gasto_original'],
                    'total_pago' => 0.0,
                    'ultima_visita' => $est['ultima_visita']
                ];
            }
            
            // Add/update with payment data
            foreach ($pagamentosEstabelecimentos as $est) {
                $key = $est['tenant_id'] . '_' . $est['filial_id'];
                if (isset($estabelecimentosMap[$key])) {
                    // Update existing
                    $estabelecimentosMap[$key]['pedidos_pagos'] = (int)$est['pedidos_pagos'];
                    $estabelecimentosMap[$key]['total_pago'] = (float)$est['total_pago'];
                    // Keep the most recent date
                    if ($est['ultima_visita'] > $estabelecimentosMap[$key]['ultima_visita']) {
                        $estabelecimentosMap[$key]['ultima_visita'] = $est['ultima_visita'];
                    }
                } else {
                    // Add new
                    $estabelecimentosMap[$key] = [
                        'tenant_id' => $est['tenant_id'],
                        'filial_id' => $est['filial_id'],
                        'tenant_nome' => $est['tenant_nome'],
                        'filial_nome' => $est['filial_nome'],
                        'pedidos_originais' => 0,
                        'pedidos_pagos' => (int)$est['pedidos_pagos'],
                        'total_gasto_original' => 0.0,
                        'total_pago' => (float)$est['total_pago'],
                        'ultima_visita' => $est['ultima_visita']
                    ];
                }
            }
            
            // 4. Calculate totals and format
            $resultado = [];
            foreach ($estabelecimentosMap as $est) {
                $est['total_pedidos'] = $est['pedidos_originais'] + $est['pedidos_pagos'];
                $resultado[] = $est;
            }
            
            // Sort by last visit
            usort($resultado, function($a, $b) {
                return strtotime($b['ultima_visita']) - strtotime($a['ultima_visita']);
            });
            
            return $resultado;

        } catch (Exception $e) {
            error_log("Erro ao buscar estabelecimentos visitados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Register client interaction history
     */
    public function registrarHistorico($clienteId, $tenantId, $filialId, $tipoInteracao, $descricao, $dadosAnteriores = null, $dadosNovos = null)
    {
        try {
            // Skip history if tenant_id is not provided (tenant_id is required for foreign key)
            if ($tenantId === null) {
                error_log("Cliente::registrarHistorico - tenant_id is null, skipping history registration");
                return;
            }
            
            // Use default filial if not provided
            if ($filialId === null) {
                $filial_padrao = $this->db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
            $this->db->insert('cliente_historico', [
                'usuario_global_id' => $clienteId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'tipo_interacao' => $tipoInteracao,
                'descricao' => $descricao,
                'dados_anteriores' => $dadosAnteriores ? json_encode($dadosAnteriores) : null,
                'dados_novos' => $dadosNovos ? json_encode($dadosNovos) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar histórico do cliente: " . $e->getMessage());
        }
    }

    /**
     * Update client establishment visit
     */
    public function atualizarVisitaEstabelecimento($clienteId, $tenantId, $filialId, $valorPedido = 0)
    {
        try {
            // Skip if tenant_id is not provided (required for foreign key)
            if ($tenantId === null) {
                error_log("Cliente::atualizarVisitaEstabelecimento - tenant_id is null, skipping");
                return;
            }
            
            // Use default filial if not provided
            if ($filialId === null) {
                $filial_padrao = $this->db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
            // Check if client has visited this establishment before
            $existente = $this->db->fetch(
                "SELECT * FROM cliente_estabelecimentos 
                 WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ?",
                [$clienteId, $tenantId, $filialId]
            );

            if ($existente) {
                // Update existing record
                $this->db->update('cliente_estabelecimentos', [
                    'ultima_visita' => date('Y-m-d H:i:s'),
                    'total_pedidos' => $existente['total_pedidos'] + 1,
                    'total_gasto' => $existente['total_gasto'] + $valorPedido
                ], [
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            } else {
                // Create new record
                $this->db->insert('cliente_estabelecimentos', [
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'primeira_visita' => date('Y-m-d H:i:s'),
                    'ultima_visita' => date('Y-m-d H:i:s'),
                    'total_pedidos' => 1,
                    'total_gasto' => $valorPedido
                ]);
            }
        } catch (Exception $e) {
            error_log("Erro ao atualizar visita do estabelecimento: " . $e->getMessage());
        }
    }

    /**
     * Get client statistics
     */
    public function getEstatisticas($clienteId, $tenantId = null)
    {
        try {
            $where = ['usuario_global_id = ?'];
            $params = [$clienteId];

            if ($tenantId) {
                $where[] = 'tenant_id = ?';
                $params[] = $tenantId;
            }

            $whereClause = implode(' AND ', $where);

            // Get statistics from orders (original behavior)
            $stats = $this->db->fetch(
                "SELECT 
                    COUNT(DISTINCT p.idpedido) as total_pedidos,
                    COALESCE(SUM(p.valor_total), 0) as total_gasto,
                    COALESCE(AVG(p.valor_total), 0) as ticket_medio,
                    MAX(p.created_at) as ultimo_pedido,
                    MIN(p.created_at) as primeiro_pedido,
                    COUNT(DISTINCT p.tenant_id) as estabelecimentos_visitados
                 FROM pedido p
                 WHERE $whereClause",
                $params
            );

            // Get additional statistics from payments made by this client
            $paymentStats = $this->db->fetch(
                "SELECT 
                    COUNT(DISTINCT pag.pedido_id) as pedidos_pagos,
                    COALESCE(SUM(pag.valor_pago), 0) as total_pago,
                    MAX(pag.created_at) as ultimo_pagamento
                 FROM pagamentos_pedido pag
                 WHERE pag.usuario_global_id = ?" . 
                 ($tenantId ? " AND pag.tenant_id = ?" : ""),
                $tenantId ? [$clienteId, $tenantId] : [$clienteId]
            );

            // Combine statistics: include both orders and payments
            $combinedStats = [
                'total_pedidos' => max($stats['total_pedidos'] ?? 0, $paymentStats['pedidos_pagos'] ?? 0),
                'total_gasto' => max($stats['total_gasto'] ?? 0, $paymentStats['total_pago'] ?? 0),
                'ticket_medio' => $stats['ticket_medio'] ?? 0,
                'ultimo_pedido' => $stats['ultimo_pedido'] ?? null,
                'ultimo_pagamento' => $paymentStats['ultimo_pagamento'] ?? null,
                'primeiro_pedido' => $stats['primeiro_pedido'] ?? null,
                'estabelecimentos_visitados' => $stats['estabelecimentos_visitados'] ?? 0,
                'total_pago' => $paymentStats['total_pago'] ?? 0,
                'pedidos_pagos' => $paymentStats['pedidos_pagos'] ?? 0
            ];

            return $combinedStats;
        } catch (Exception $e) {
            return [
                'total_pedidos' => 0,
                'total_gasto' => 0,
                'ticket_medio' => 0,
                'ultimo_pedido' => null,
                'ultimo_pagamento' => null,
                'primeiro_pedido' => null,
                'estabelecimentos_visitados' => 0,
                'total_pago' => 0,
                'pedidos_pagos' => 0
            ];
        }
    }

    /**
     * Search clients by phone or name
     */
    public function search($term, $tenantId = null, $limit = 10)
    {
        try {
            $where = ['ug.ativo = true'];
            $params = [];

            if ($tenantId) {
                $where[] = "EXISTS (SELECT 1 FROM cliente_estabelecimentos ce WHERE ce.usuario_global_id = ug.id AND ce.tenant_id = ?)";
                $params[] = $tenantId;
            }

            $where[] = "(ug.nome ILIKE ? OR ug.telefone ILIKE ?)";
            $searchTerm = '%' . $term . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;

            $whereClause = implode(' AND ', $where);

            $clientes = $this->db->fetchAll(
                "SELECT ug.id, ug.nome, ug.telefone, ug.email
                 FROM usuarios_globais ug
                 WHERE $whereClause
                 ORDER BY ug.nome
                 LIMIT ?",
                array_merge($params, [$limit])
            );

            return $clientes;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Deactivate client (soft delete)
     */
    public function deactivate($id)
    {
        try {
            $this->db->update('usuarios_globais', ['ativo' => false], 'id = ?', [$id]);
            return ['success' => true, 'message' => 'Cliente desativado com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao desativar cliente: ' . $e->getMessage()];
        }
    }

    /**
     * Get client addresses
     */
    public function getEnderecos($clienteId)
    {
        try {
            $enderecos = $this->db->fetchAll(
                "SELECT * FROM enderecos 
                 WHERE usuario_global_id = ? AND ativo = true
                 ORDER BY principal DESC, created_at DESC",
                [$clienteId]
            );

            return $enderecos;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Add client address
     */
    public function adicionarEndereco($clienteId, $dados)
    {
        try {
            $dados['usuario_global_id'] = $clienteId;
            $dados['tenant_id'] = 1; // Default tenant
            $dados['created_at'] = date('Y-m-d H:i:s');
            $dados['updated_at'] = date('Y-m-d H:i:s');
            $dados['ativo'] = true;

            $enderecoId = $this->db->insert('enderecos', $dados);
            return ['success' => true, 'id' => $enderecoId, 'message' => 'Endereço adicionado com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao adicionar endereço: ' . $e->getMessage()];
        }
    }

    /**
     * Get client preferences
     */
    public function getPreferencias($clienteId, $tenantId, $filialId = null)
    {
        try {
            $where = ['usuario_global_id = ?', 'tenant_id = ?'];
            $params = [$clienteId, $tenantId];

            if ($filialId) {
                $where[] = 'filial_id = ?';
                $params[] = $filialId;
            }

            $whereClause = implode(' AND ', $where);

            $preferencias = $this->db->fetch(
                "SELECT * FROM preferencias_cliente WHERE $whereClause",
                $params
            );

            return $preferencias;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update client preferences
     */
    public function atualizarPreferencias($clienteId, $tenantId, $filialId, $dados)
    {
        try {
            $existente = $this->getPreferencias($clienteId, $tenantId, $filialId);

            if ($existente) {
                $this->db->update('preferencias_cliente', $dados, [
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            } else {
                $this->db->insert('preferencias_cliente', array_merge($dados, [
                    'usuario_global_id' => $clienteId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]));
            }

            return ['success' => true, 'message' => 'Preferências atualizadas com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar preferências: ' . $e->getMessage()];
        }
    }


    /**
     * Update address
     */
    public function atualizarEndereco($enderecoId, $dados)
    {
        try {
            $dados['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('enderecos', $dados, 'id = ?', [$enderecoId]);
            return ['success' => true, 'message' => 'Endereço atualizado com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar endereço: ' . $e->getMessage()];
        }
    }

    /**
     * Delete address
     */
    public function removerEndereco($enderecoId)
    {
        try {
            $this->db->delete('enderecos', 'id = ?', [$enderecoId]);
            return ['success' => true, 'message' => 'Endereço removido com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao remover endereço: ' . $e->getMessage()];
        }
    }

    /**
     * Get order details with items
     */
    public function getDetalhesPedido($pedidoId, $tenantId = null, $filialId = null)
    {
        try {
            $where = ['p.idpedido = ?'];
            $params = [$pedidoId];

            if ($tenantId) {
                $where[] = 'p.tenant_id = ?';
                $params[] = $tenantId;
            }

            if ($filialId) {
                $where[] = 'p.filial_id = ?';
                $params[] = $filialId;
            }

            $whereClause = implode(' AND ', $where);

            // Get order details
            $pedido = $this->db->fetch(
                "SELECT p.*, 
                        t.nome as tenant_nome,
                        f.nome as filial_nome,
                        m.numero as mesa_numero
                 FROM pedido p
                 LEFT JOIN tenants t ON p.tenant_id = t.id
                 LEFT JOIN filiais f ON p.filial_id = f.id
                 LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
                 WHERE $whereClause",
                $params
            );

            if (!$pedido) {
                return null;
            }

            // Get order items
            $itens = $this->db->fetchAll(
                "SELECT pi.*, pr.nome as nome_produto
                 FROM pedido_itens pi
                 LEFT JOIN produtos pr ON pi.produto_id = pr.id
                 WHERE pi.pedido_id = ?
                 ORDER BY pi.id",
                [$pedidoId]
            );

            $pedido['itens'] = $itens;

            return $pedido;
        } catch (Exception $e) {
            return null;
        }
    }

}
