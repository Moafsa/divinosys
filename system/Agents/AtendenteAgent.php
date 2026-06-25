<?php
namespace System\Agents;

class AtendenteAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente de Atendimento do restaurante. Sua função é lidar com o histórico de clientes, perfil e tira-dúvidas gerais.\n" .
               "Você DEVE usar `buscar_cliente` ou `listar_clientes_geral` se não souber o ID de um cliente.\n" .
               "Se um cliente quiser saber o que já comeu no passado, use `listar_compras_cliente`.";
    }
    
    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_cliente',
                    'description' => 'Busca clientes no banco de dados geral (não apenas fiado) por nome ou parte do nome.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_clientes_geral',
                    'description' => 'Lista todos os clientes cadastrados.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_compras_cliente',
                    'description' => 'Busca todo o histórico de compras, pagamentos e consumo de um cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome_cliente' => ['type' => 'string']
                        ],
                        'required' => ['nome_cliente']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_order',
                    'description' => 'Cria um novo pedido.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'mesa_id' => ['type' => 'string', 'description' => 'Número da mesa'],
                            'cliente' => ['type' => 'string'],
                            'itens' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'quantidade' => ['type' => 'integer'],
                                        'preco' => ['type' => 'number']
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
                    'name' => 'update_order',
                    'description' => 'Atualiza dados de um pedido existente, como mudar a mesa, cliente ou status.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pedido_id' => ['type' => 'integer'],
                            'mesa_id' => ['type' => 'string'],
                            'cliente' => ['type' => 'string'],
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
                    'description' => 'Fecha um pedido, marcando-o como Finalizado e quitado.',
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
        if ($name === 'buscar_cliente') {
            $sql = "SELECT ug.id, ug.nome, ug.telefone, ug.email 
                    FROM usuarios_globais ug 
                    JOIN cliente_estabelecimentos ce ON ug.id = ce.usuario_global_id 
                    WHERE ce.tenant_id = ? AND ug.nome ILIKE ? LIMIT 10";
            $nome = $args['nome'] ?? '';
            $clientes = $this->db->fetchAll($sql, [$this->tenantId, '%' . $nome . '%']);
            
            // Tentar também buscar no clientes_fiado como fallback, caso seja um cliente apenas de fiado antigo
            if (empty($clientes)) {
                $sqlFiado = "SELECT id, nome, telefone, '' as email FROM clientes_fiado WHERE tenant_id = ? AND nome ILIKE ? LIMIT 10";
                $clientes = $this->db->fetchAll($sqlFiado, [$this->tenantId, '%' . $nome . '%']);
            }
            
            return ['success' => true, 'clientes' => $clientes];
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
