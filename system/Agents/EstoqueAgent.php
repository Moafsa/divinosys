<?php
namespace System\Agents;

class EstoqueAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente de Estoque e Cardápio do DivinoSys. Sua função é responder a dúvidas sobre os produtos, seus preços, estoque atual, ingredientes e GERENCIAR o cardápio.\n" .
               "Você pode buscar produtos usando a ferramenta `buscar_produtos`.\n" .
               "SEMPRE use a ferramenta de busca antes de dizer que um produto não existe ou falar o preço dele.\n" .
               "Você também tem permissão para Adicionar, Editar e Excluir produtos, categorias e ingredientes, além de atualizar o estoque.";
    }
    
    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_produtos',
                    'description' => 'Busca produtos no cardápio pelo nome. Retorna os detalhes, preços e estoque.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'O nome ou parte do nome do produto']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_produtos',
                    'description' => 'Lista todos os produtos ativos do cardápio.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_product',
                    'description' => 'Cria um novo produto no cardápio.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string'],
                            'descricao' => ['type' => 'string'],
                            'preco' => ['type' => 'number'],
                            'categoria_id' => ['type' => 'integer'],
                            'em_promocao' => ['type' => 'boolean', 'description' => 'Colocar em promoção?'],
                            'preco_promocional' => ['type' => 'number', 'description' => 'Preço de promoção'],
                            'ativo' => ['type' => 'boolean', 'description' => 'true para mostrar no cardápio online, false para ocultar']
                        ],
                        'required' => ['nome', 'preco']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_product',
                    'description' => 'Edita um produto existente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nome' => ['type' => 'string'],
                            'descricao' => ['type' => 'string'],
                            'preco' => ['type' => 'number'],
                            'categoria_id' => ['type' => 'integer'],
                            'em_promocao' => ['type' => 'boolean'],
                            'preco_promocional' => ['type' => 'number'],
                            'ativo' => ['type' => 'boolean']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_product',
                    'description' => 'Exclui ou inativa um produto.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_categorias',
                    'description' => 'Lista as categorias do cardápio.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_category',
                    'description' => 'Cria uma nova categoria.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string']
                        ],
                        'required' => ['nome']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_category',
                    'description' => 'Atualiza uma categoria.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nome' => ['type' => 'string']
                        ],
                        'required' => ['id', 'nome']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_category',
                    'description' => 'Exclui uma categoria.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_ingredientes',
                    'description' => 'Lista todos os ingredientes e insumos.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_ingredient',
                    'description' => 'Cria um novo ingrediente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string'],
                            'custo' => ['type' => 'number']
                        ],
                        'required' => ['nome']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_ingredient',
                    'description' => 'Atualiza um ingrediente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nome' => ['type' => 'string'],
                            'custo' => ['type' => 'number']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_ingredient',
                    'description' => 'Exclui um ingrediente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer']
                        ],
                        'required' => ['id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ver_estoque',
                    'description' => 'Retorna os produtos que estão com estoque baixo ou a quantidade de todos os produtos que controlam estoque.',
                    'parameters' => ['type' => 'object', 'properties' => []]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'atualizar_estoque',
                    'description' => 'Atualiza (soma ou subtrai) a quantidade de estoque de um produto específico.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'produto_id' => ['type' => 'integer'],
                            'quantidade' => ['type' => 'number', 'description' => 'Quantidade a ser adicionada ou removida (use negativo para remover)'],
                            'motivo' => ['type' => 'string', 'description' => 'Motivo da alteração']
                        ],
                        'required' => ['produto_id', 'quantidade']
                    ]
                ]
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        if ($name === 'buscar_produtos') {
            $query = $args['query'] ?? '';
            if (empty($query)) return ['success' => false, 'message' => 'Termo de busca vazio'];
            
            $sql = "SELECT id, nome, preco_normal, estoque_atual, ativo 
                    FROM produtos 
                    WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? AND ativo = true
                    LIMIT 10";
            
            $produtos = $this->db->fetchAll($sql, [
                $this->tenantId, 
                $this->filialId, 
                '%' . $query . '%'
            ]);
            
            if (empty($produtos)) {
                return ['success' => true, 'message' => 'Nenhum produto encontrado com esse nome.'];
            }
            
            return ['success' => true, 'produtos' => $produtos];
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
