<?php
namespace System\Agents;

class GarcomAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Garçom Virtual do DivinoSys. Sua função é tirar pedidos, listar comandas abertas, lançar itens nas mesas e verificar comandas.\n" .
               "Você DEVE usar listar_pedidos ou ver_mesas_ativas para consultar comandas em aberto.\n" .
               "Se um usuário pedir para lançar um produto, mas não der o preço ou o ID correto, você DEVE consultar o agente de estoque ou pedir os detalhes ao cliente.";
    }
    
    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ver_mesas_ativas',
                    'description' => 'Lista todas as mesas e comandas que estão abertas/pendentes no restaurante no momento.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_pedidos',
                    'description' => 'Lista todos os pedidos recentes, abertos ou fechados.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_order',
                    'description' => 'Cria um novo pedido em uma mesa ou comanda.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mesa_id' => ['type' => 'string', 'description' => 'Número da mesa ou identificação da comanda'],
                            'cliente' => ['type' => 'string'],
                            'itens' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'quantidade' => ['type' => 'integer'],
                                        'preco' => ['type' => 'number'],
                                        'observacao' => ['type' => 'string'],
                                        'tamanho' => ['type' => 'string']
                                    ],
                                    'required' => ['id', 'quantidade', 'preco']
                                ]
                            ]
                        ],
                        'required' => ['mesa_id', 'itens']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_item_to_order',
                    'description' => 'Adiciona itens a um pedido/mesa existente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer', 'description' => 'ID numérico interno do pedido (NÃO é o número da mesa)'],
                            'itens' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'quantidade' => ['type' => 'integer'],
                                        'preco' => ['type' => 'number'],
                                        'observacao' => ['type' => 'string'],
                                        'tamanho' => ['type' => 'string']
                                    ]
                                ]
                            ]
                        ],
                        'required' => ['pedido_id', 'itens']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_item_from_order',
                    'description' => 'Remove um item de um pedido existente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'item_id' => ['type' => 'integer', 'description' => 'ID do item do pedido a ser removido']
                        ],
                        'required' => ['pedido_id', 'item_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_order',
                    'description' => 'Atualiza dados de um pedido existente, como mudar a mesa, cliente ou status.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'mesa_id' => ['type' => 'string'],
                            'cliente' => ['type' => 'string'],
                            'observacao' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega', 'Entregue']]
                        ],
                        'required' => ['pedido_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_order',
                    'description' => 'Exclui/cancela um pedido inteiro e todos os seus itens.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer']
                        ],
                        'required' => ['pedido_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fechar_pedido',
                    'description' => 'Fecha um pedido, marcando-o como Finalizado e o pagamento como quitado.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'forma_pagamento' => ['type' => 'string', 'enum' => ['dinheiro', 'pix', 'cartao', 'fiado']],
                            'observacao_fechamento' => ['type' => 'string']
                        ],
                        'required' => ['pedido_id', 'forma_pagamento']
                    ]
                ]
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        if ($name === 'ver_mesas_ativas') {
            $sql = "SELECT idpedido as id, idmesa as mesa, cliente, valor_total, hora_pedido 
                    FROM pedido 
                    WHERE tenant_id = ? AND filial_id = ? AND status = 'Pendente' AND status_pagamento != 'quitado'";
            $mesas = $this->db->fetchAll($sql, [$this->tenantId, $this->filialId]);
            return ['success' => true, 'mesas_ativas' => $mesas];
        }
        
        $legacyService = new \System\OpenAIService();
        $args['tenant_id'] = $this->tenantId;
        $args['filial_id'] = $this->filialId;
        
        return $legacyService->executeOperation([
            'type' => $name,
            'data' => $args
        ]);
    }
}
