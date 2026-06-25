<?php
namespace System\Agents;

/**
 * Agente para garçons/equipe (telefones em whatsapp_admins).
 * NÃO atende clientes finais via WhatsApp — use ClienteWhatsAppAgent.
 */
class GarcomAgent extends BaseAgent {

    protected function getSystemPrompt(): string {
        return "Você é o Garçom Virtual do DivinoSys. Você está falando com um Garçom, Administrador ou membro da EQUIPE do restaurante.\n" .
               "Sua função é tirar pedidos, listar comandas abertas, lançar itens nas mesas e verificar comandas.\n" .
               "REGRA IMPORTANTE: Se o funcionário pedir para lançar um item e NÃO informar a mesa ou comanda, VOCÊ DEVE PERGUNTAR QUAL A MESA ou COMANDA antes de prosseguir.\n" .
               "Você DEVE usar listar_pedidos ou ver_mesas_ativas para consultar comandas em aberto.\n" .
               "REGRA CRÍTICA: Continue chamando ferramentas em loop até concluir o que foi solicitado antes de responder.\n" .
               "IMPORTANTE: Se um usuário pedir para lançar um produto, mas não der o preço ou o ID correto, USE IMEDIATAMENTE a ferramenta `buscar_produtos` para descobrir os valores corretos. NUNCA diga 'vou consultar o cardápio' sem de fato chamar a ferramenta.";
    }

    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_produtos',
                    'description' => 'Busca produtos no cardápio pelo nome para descobrir o ID e preço corretos antes de fazer o pedido.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'O nome do produto para pesquisar']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ver_mesas_ativas',
                    'description' => 'Lista todas as mesas e comandas que estão abertas/pendentes no restaurante no momento.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_pedidos',
                    'description' => 'Lista todos os pedidos recentes, abertos ou fechados.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
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
                            'pedido_id' => ['type' => 'integer'],
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
                            'item_id' => ['type' => 'integer']
                        ],
                        'required' => ['pedido_id', 'item_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_order',
                    'description' => 'Atualiza dados de um pedido existente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'mesa_id' => ['type' => 'string'],
                            'cliente' => ['type' => 'string'],
                            'observacao' => ['type' => 'string'],
                            'status' => ['type' => 'string']
                        ],
                        'required' => ['pedido_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_order',
                    'description' => 'Exclui/cancela um pedido inteiro.',
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
                    'description' => 'Fecha um pedido, marcando-o como Finalizado.',
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
            ],
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

        if ($name === 'buscar_produtos') {
            return \System\WhatsApp\ProductSearchHelper::search(
                $this->db,
                $this->tenantId,
                $this->filialId,
                $args['query'] ?? '',
                10,
                $this->ignoreStock
            );
        }

        $legacyService = new \System\OpenAIService();
        $legacyService->setIgnoreStock($this->ignoreStock);
        $args['tenant_id'] = $this->tenantId;
        $args['filial_id'] = $this->filialId;

        return $legacyService->executeOperation([
            'type' => $name,
            'data' => $args
        ]);
    }
}
