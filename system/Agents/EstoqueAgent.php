<?php
namespace System\Agents;

class EstoqueAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente de Estoque e Cardápio do DivinoSys. Sua função é responder a dúvidas sobre os produtos, seus preços, estoque atual, ingredientes e GERENCIAR o cardápio.\n" .
               "Você pode buscar produtos usando a ferramenta `buscar_produtos`.\n" .
               "Para perguntas sobre promoções (ex: 'o que está em promoção hoje?'), use OBRIGATORIAMENTE a ferramenta `listar_promocoes` antes de responder.\n" .
               "SEMPRE use as ferramentas de busca/listagem antes de dizer que um produto não existe ou falar o preço dele.\n" .
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
                    'name' => 'listar_promocoes',
                    'description' => 'Lista todos os produtos que estão em promoção no cardápio hoje.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_produtos',
                    'description' => 'Lista todos os produtos ativos do cardápio.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
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
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
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
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
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
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
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
            
            $sql = "SELECT id, nome, preco_normal, preco_promocional, em_promocao, estoque_atual, ativo 
                    FROM produtos 
                    WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? AND COALESCE(ativo, true) = true
                    LIMIT 10";
            
            $produtos = $this->db->fetchAll($sql, [
                $this->tenantId, 
                $this->filialId, 
                '%' . $query . '%'
            ]);
            
            if (empty($produtos)) {
                return ['success' => true, 'message' => 'Nenhum produto encontrado com esse nome.'];
            }

            if ($this->ignoreStock) {
                foreach ($produtos as &$produto) {
                    unset($produto['estoque_atual']);
                }
                unset($produto);
            } else {
                foreach ($produtos as &$produto) {
                    $estoque = (float) ($produto['estoque_atual'] ?? 0);
                    $produto['disponivel'] = $estoque > 0;
                    $produto['status_estoque'] = $estoque > 0 ? 'disponível' : 'sem estoque';
                }
                unset($produto);
            }
            
            return ['success' => true, 'produtos' => $produtos];
        }

        if ($name === 'listar_promocoes') {
            $legacyService = new \System\OpenAIService();
            $legacyService->setIgnoreStock($this->ignoreStock);
            return $legacyService->executeOperation([
                'type' => 'listar_promocoes',
                'data' => [
                    'tenant_id' => $this->tenantId,
                    'filial_id' => $this->filialId,
                ],
            ]);
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
