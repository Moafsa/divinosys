<?php
namespace System\Agents;

class ClienteWhatsAppAgent extends BaseAgent {

    protected function getSystemPrompt(): string {
        return "Você é o Assistente Virtual de Atendimento ao Cliente via WhatsApp do restaurante.\n" .
               "REGRA CRÍTICA: Use buscar_produtos → anotar_item para cada produto.\n" .
               "REGRA CRÍTICA: Delivery EXIGE endereço completo antes do resumo. Use consultar_taxas_entrega se precisar.\n" .
               "REGRA CRÍTICA: confirmar_pedido SOMENTE após SIM ao resumo. Pagamento SOMENTE após pedido registrado.\n" .
               "REGRA CRÍTICA: NUNCA diga que não conseguiu registrar — use ver_rascunho e tente novamente.\n\n" .
               \System\WhatsApp\CustomerOrderPrompt::flowInstructions();
    }

    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_produtos',
                    'description' => 'Busca produtos no cardápio pelo nome.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['query' => ['type' => 'string']],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'anotar_item',
                    'description' => 'Adiciona produto ao rascunho (não registra ainda).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'produto_id' => ['type' => 'integer'],
                            'nome' => ['type' => 'string'],
                            'quantidade' => ['type' => 'integer'],
                            'preco' => ['type' => 'number']
                        ],
                        'required' => ['produto_id', 'nome', 'preco']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_taxas_entrega',
                    'description' => 'Lista taxas de entrega configuradas por bairro.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'definir_entrega',
                    'description' => 'Define retirada ou delivery. Para delivery, endereco é OBRIGATÓRIO (rua, número, bairro).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'tipo' => ['type' => 'string', 'enum' => ['retirada', 'delivery']],
                            'endereco' => ['type' => 'string', 'description' => 'Endereço completo — obrigatório para delivery']
                        ],
                        'required' => ['tipo']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ver_rascunho',
                    'description' => 'Mostra rascunho com itens, taxa de entrega e total.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirmar_pedido',
                    'description' => 'Registra pedido no sistema. Use SOMENTE após cliente confirmar resumo com SIM.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['cliente' => ['type' => 'string']],
                        'required' => ['cliente']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_pagamento',
                    'description' => 'Registra forma de pagamento após pedido criado. PIX gera cobrança automaticamente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'forma' => ['type' => 'string', 'enum' => ['pix', 'dinheiro', 'cartao']],
                            'troco_para' => ['type' => 'number'],
                            'valor' => ['type' => 'number'],
                            'cpf' => ['type' => 'string', 'description' => 'CPF do cliente (11 dígitos) — obrigatório para PIX']
                        ],
                        'required' => ['pedido_id', 'forma']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancelar_pedido',
                    'description' => 'Cancela o pedido atual ou um pedido específico se o cliente fornecer o ID.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer', 'description' => 'ID opcional do pedido para cancelar']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_pedidos_cliente',
                    'description' => 'Lista os pedidos ativos (em andamento) deste cliente.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
        ];
    }

    protected function executeTool(string $name, array $args): array {
        if ($name === 'buscar_produtos') {
            return \System\WhatsApp\ProductSearchHelper::search(
                $this->db, $this->tenantId, $this->filialId,
                $args['query'] ?? '', 10, $this->ignoreStock
            );
        }

        if ($name === 'consultar_taxas_entrega') {
            return [
                'success' => true,
                'taxas' => \System\WhatsApp\DeliveryFeeHelper::bairrosTexto($this->db, $this->tenantId, $this->filialId),
            ];
        }

        if (!$this->orderSessionId) {
            return ['success' => false, 'message' => 'Sessão de pedido não encontrada.'];
        }

        $sessionSvc = new \System\WhatsApp\WhatsAppOrderSessionService($this->db);

        if ($name === 'anotar_item') {
            $draft = $sessionSvc->getDraft($this->orderSessionId);
            $draft = \System\WhatsApp\WhatsAppOrderDraft::addItem($draft, [
                'id' => (int) ($args['produto_id'] ?? 0),
                'nome' => (string) ($args['nome'] ?? ''),
                'quantidade' => (int) ($args['quantidade'] ?? 1),
                'preco' => (float) ($args['preco'] ?? 0),
            ]);
            $sessionSvc->saveDraft($this->orderSessionId, $draft);
            return ['success' => true, 'message' => 'Item anotado.', 'total' => \System\WhatsApp\WhatsAppOrderDraft::total($draft)];
        }

        if ($name === 'definir_entrega') {
            $draft = $sessionSvc->getDraft($this->orderSessionId);
            $tipo = strtolower((string) ($args['tipo'] ?? 'retirada'));

            if ($tipo === 'delivery') {
                $endereco = trim((string) ($args['endereco'] ?? ''));
                if ($endereco === '') {
                    return ['success' => false, 'message' => 'Para delivery, informe o endereço completo (rua, número e bairro).'];
                }
                $fee = \System\WhatsApp\DeliveryFeeHelper::calculateFee($this->db, $this->tenantId, $this->filialId, $endereco);
                if (empty($fee['success'])) {
                    return $fee;
                }
                $draft['mesa_id'] = '999';
                $draft['endereco'] = $endereco;
                $draft['taxa_entrega'] = (float) $fee['taxa'];
                $sessionSvc->saveDraft($this->orderSessionId, $draft);
                return [
                    'success' => true,
                    'message' => 'Delivery configurado.',
                    'taxa_entrega' => $draft['taxa_entrega'],
                    'bairro' => $fee['bairro'] ?? null,
                    'resumo' => \System\WhatsApp\WhatsAppOrderDraft::summaryText($draft),
                ];
            }

            $draft['mesa_id'] = '998';
            $draft['endereco'] = '';
            $draft['taxa_entrega'] = 0;
            $sessionSvc->saveDraft($this->orderSessionId, $draft);
            return ['success' => true, 'message' => 'Retirada no balcão configurada.'];
        }

        if ($name === 'ver_rascunho') {
            $draft = $sessionSvc->getDraft($this->orderSessionId);
            if (($draft['mesa_id'] ?? '') === '999' && empty($draft['endereco'])) {
                return ['success' => false, 'message' => 'Delivery selecionado mas endereço ainda não informado. Peça o endereço ao cliente.'];
            }
            $draft['aguardando_confirmacao'] = true;
            $sessionSvc->saveDraft($this->orderSessionId, $draft);
            return [
                'success' => true,
                'resumo' => \System\WhatsApp\WhatsAppOrderDraft::summaryText($draft),
                'total' => \System\WhatsApp\WhatsAppOrderDraft::total($draft),
                'taxa_entrega' => (float) ($draft['taxa_entrega'] ?? 0),
            ];
        }

        if ($name === 'confirmar_pedido') {
            $draft = $sessionSvc->getDraft($this->orderSessionId);
            if (!empty($draft['pedido_id'])) {
                $pedidoId = (int) $draft['pedido_id'];
                return [
                    'success' => true,
                    'pedido_id' => $pedidoId,
                    'already_confirmed' => true,
                    '_final_handoff_response' => 'Seu pedido #' . $pedidoId . ' já está registrado! Como prefere pagar: PIX, dinheiro ou cartão?',
                ];
            }
            if (empty($draft['itens'])) {
                return ['success' => false, 'message' => 'Rascunho vazio.'];
            }
            if (empty($draft['mesa_id'])) {
                return ['success' => false, 'message' => 'Defina retirada ou delivery primeiro.'];
            }
            if (($draft['mesa_id'] ?? '') === '999' && empty($draft['endereco'])) {
                return ['success' => false, 'message' => 'Delivery exige endereço antes de confirmar.'];
            }

            $legacyService = new \System\OpenAIService();
            $legacyService->setIgnoreStock($this->ignoreStock);
            $payload = \System\WhatsApp\WhatsAppOrderDraft::toCreateOrderPayload(
                $draft,
                (string) ($args['cliente'] ?? $this->customerName ?: 'Cliente WhatsApp')
            );
            $payload['tenant_id'] = $this->tenantId;
            $payload['filial_id'] = $this->filialId;
            $payload['cliente_telefone'] = $this->customerPhone;

            $result = $legacyService->executeOperation(['type' => 'create_order', 'data' => $payload]);
            if (!empty($result['success'])) {
                $orderId = (int) ($result['order_id'] ?? $result['data']['id'] ?? 0);
                $draft['pedido_id'] = $orderId;
                $draft['aguardando_confirmacao'] = false;
                $sessionSvc->saveDraft($this->orderSessionId, $draft);
                $result['pedido_id'] = $orderId;
                $result['message'] = 'Pedido #' . $orderId . ' registrado com sucesso! Total: R$ ' .
                    number_format(\System\WhatsApp\WhatsAppOrderDraft::total($draft), 2, ',', '.');
                $result['_final_handoff_response'] = 'Perfeito! Pedido #' . $orderId . ' registrado com sucesso. Total: R$ ' .
                    number_format(\System\WhatsApp\WhatsAppOrderDraft::total($draft), 2, ',', '.') .
                    "\n\nComo prefere pagar: PIX, dinheiro ou cartão?";
            }

            return $result;
        }

        if ($name === 'registrar_pagamento') {
            $pedidoId = (int) ($args['pedido_id'] ?? 0);
            $forma = strtolower((string) ($args['forma'] ?? ''));
            $valor = (float) ($args['valor'] ?? 0);
            $troco = isset($args['troco_para']) ? (float) $args['troco_para'] : null;

            if ($pedidoId <= 0 || $forma === '') {
                return ['success' => false, 'message' => 'Informe pedido_id e forma de pagamento.'];
            }

            if ($forma === 'dinheiro' && ($troco === null || $troco <= 0)) {
                return ['success' => false, 'message' => 'Para dinheiro, informe troco_para (valor para quanto precisa de troco).'];
            }

            $pedido = $this->db->fetch(
                'SELECT valor_total, observacao FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $this->tenantId, $this->filialId]
            );
            if (!$pedido) {
                return ['success' => false, 'message' => 'Pedido não encontrado.'];
            }

            if ($valor <= 0) {
                $valor = (float) $pedido['valor_total'];
            }

            $obs = trim((string) ($pedido['observacao'] ?? ''));
            $obs .= "\nPagamento: " . strtoupper($forma);
            if ($forma === 'dinheiro' && $troco > 0) {
                $obs .= ' — Troco para R$ ' . number_format($troco, 2, ',', '.');
            }

            if ($forma === 'pix') {
                $cpf = preg_replace('/[^0-9]/', '', (string) ($args['cpf'] ?? $sessionSvc->getDraft($this->orderSessionId)['cpf'] ?? ''));
                
                if (empty($cpf) && !empty($this->customerPhone)) {
                    $clienteDb = $this->db->fetch('SELECT cpf FROM clientes WHERE telefone = ? AND tenant_id = ? AND filial_id = ? AND cpf IS NOT NULL AND cpf != \'\'', [$this->customerPhone, $this->tenantId, $this->filialId]);
                    if ($clienteDb && !empty($clienteDb['cpf'])) {
                        $cpf = preg_replace('/[^0-9]/', '', $clienteDb['cpf']);
                    }
                }

                if (empty($cpf)) {
                    return ['success' => false, 'message' => 'Para gerar o PIX, preciso do seu CPF. Por favor, me informe seu CPF (apenas números).'];
                }

                $pix = \System\WhatsApp\PixInvoiceHelper::gerarParaPedido(
                    $this->db, $this->tenantId, $this->filialId,
                    $pedidoId, $valor,
                    (string) ($args['nome_cliente'] ?? $this->customerName),
                    (string) ($args['telefone_cliente'] ?? $this->customerPhone),
                    'Pedido #' . $pedidoId,
                    $cpf
                );
                if (!empty($pix['success'])) {
                    $draft = $sessionSvc->getDraft($this->orderSessionId);
                    $draft['forma_pagamento'] = 'pix';
                    $sessionSvc->saveDraft($this->orderSessionId, $draft);
                    $sessionSvc->closeSession($this->orderSessionId, 'completed');
                    $pix['_final_handoff_response'] = $pix['message'] ?? 'PIX gerado para o pedido #' . $pedidoId . '.';
                    
                    // Schedule a 10-minute reminder
                    require_once __DIR__ . '/../WhatsApp/PaymentNotificationService.php';
                    $paymentService = new \System\WhatsApp\PaymentNotificationService();
                    $sessionData = $this->db->fetch('SELECT instance_id FROM whatsapp_order_sessions WHERE id = ?', [$this->orderSessionId]);
                    $instanceId = $sessionData['instance_id'] ?? null;
                    
                    if ($instanceId) {
                        $paymentService->scheduleReminder(
                            $pedidoId,
                            $this->tenantId,
                            $this->filialId,
                            $pix['payment_id'],
                            $this->customerPhone,
                            $this->customerName,
                            $valor,
                            $pix['payment_url'],
                            $pix['pix_copy_paste'],
                            'PIX',
                            $instanceId
                        );
                    }
                }
                return $pix;
            }

            $this->db->update(
                'pedido',
                ['forma_pagamento' => $forma, 'observacao' => trim($obs)],
                'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                [$pedidoId, $this->tenantId, $this->filialId]
            );

            $draft = $sessionSvc->getDraft($this->orderSessionId);
            $draft['forma_pagamento'] = $forma;
            if ($forma === 'dinheiro' && $troco > 0) {
                $draft['troco_para'] = $troco;
            }
            $sessionSvc->saveDraft($this->orderSessionId, $draft);
            $sessionSvc->closeSession($this->orderSessionId, 'completed');
            return [
                'success' => true,
                'message' => 'Pagamento em ' . $forma . ' registrado para o pedido #' . $pedidoId . '.',
                '_final_handoff_response' => 'Pagamento em ' . $forma . ' registrado! Seu pedido #' . $pedidoId . ' está confirmado. Obrigado!',
            ];
        }

        if ($name === 'cancelar_pedido') {
            $pedidoId = (int) ($args['pedido_id'] ?? 0);
            $draft = $sessionSvc->getDraft($this->orderSessionId);
            
            if (!$pedidoId) {
                $pedidoId = $draft['pedido_id'] ?? null;
            }
            
            if (!$pedidoId) {
                // Tenta achar o último pedido ativo desse cliente
                $lastOrder = $this->db->fetch(
                    "SELECT idpedido FROM pedido WHERE tenant_id = ? AND filial_id = ? AND cliente_telefone = ? AND status IN ('Pendente', 'Em Preparo') ORDER BY idpedido DESC LIMIT 1",
                    [$this->tenantId, $this->filialId, $this->customerPhone]
                );
                $pedidoId = $lastOrder['idpedido'] ?? null;
            }

            if ($pedidoId) {
                // Verifica se o pedido pertence ao cliente (ou se está no rascunho atual)
                $orderData = $this->db->fetch(
                    "SELECT cliente_telefone FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $this->tenantId, $this->filialId]
                );
                
                $isOwner = ($orderData && $orderData['cliente_telefone'] === $this->customerPhone);
                $isInDraft = (($draft['pedido_id'] ?? null) == $pedidoId);

                if ($isOwner || $isInDraft) {
                    $this->db->update('pedido', ['status' => 'Cancelado'], 'idpedido = ?', [$pedidoId]);
                    $sessionSvc->closeSession($this->orderSessionId, 'cancelled');
                    return [
                        'success' => true, 
                        'message' => 'O pedido #' . $pedidoId . ' foi cancelado com sucesso. A sessão também foi fechada.',
                        '_final_handoff_response' => 'Seu pedido #' . $pedidoId . ' foi cancelado conforme solicitado. Se precisar de mais alguma coisa no futuro, é só chamar! Até logo!'
                    ];
                } else {
                    return ['success' => false, 'message' => 'Pedido #' . $pedidoId . ' não encontrado ou não pertence a este número.'];
                }
            } else {
                $sessionSvc->closeSession($this->orderSessionId, 'cancelled');
                return [
                    'success' => false, 
                    'message' => 'Não encontrei nenhum pedido em andamento para cancelar.',
                    '_final_handoff_response' => 'Não encontrei nenhum pedido ativo para cancelar no momento. Posso ajudar com mais alguma coisa?'
                ];
            }
        }

        if ($name === 'consultar_pedidos_cliente') {
            $pedidos = $this->db->fetchAll(
                "SELECT idpedido, data, hora_pedido, valor_total, status, idmesa FROM pedido WHERE tenant_id = ? AND filial_id = ? AND cliente_telefone = ? AND status NOT IN ('Cancelado', 'Entregue', 'Finalizado') ORDER BY idpedido DESC LIMIT 5",
                [$this->tenantId, $this->filialId, $this->customerPhone]
            );
            
            if (empty($pedidos)) {
                return ['success' => true, 'message' => 'Nenhum pedido ativo encontrado para este cliente.'];
            }
            return ['success' => true, 'pedidos' => $pedidos];
        }

        return ['success' => false, 'message' => "Ferramenta desconhecida: {$name}"];
    }

    public function runTool(string $name, array $args = []): array
    {
        return $this->executeTool($name, $args);
    }
}
