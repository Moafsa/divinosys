<?php
namespace System\Agents;

class EstoqueAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente de Estoque e Cardápio do DivinoSys. Sua função é responder a dúvidas sobre os produtos, seus preços, estoque atual e ingredientes.\n" .
               "Você pode buscar produtos usando a ferramenta `buscar_produtos`.\n" .
               "SEMPRE use a ferramenta de busca antes de dizer que um produto não existe ou falar o preço dele.";
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
                            'query' => [
                                'type' => 'string',
                                'description' => 'O nome ou parte do nome do produto para buscar (ex: "coca", "pizza", "x-bacon")'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_categorias',
                    'description' => 'Lista as categorias do cardápio.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        switch ($name) {
            case 'buscar_produtos':
                $query = $args['query'] ?? '';
                if (empty($query)) return ['success' => false, 'message' => 'Termo de busca vazio'];
                
                $sql = "SELECT id, nome, preco, preco_promocional, controla_estoque, estoque_atual, status 
                        FROM produtos 
                        WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? AND status = 'ativo'
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
                
            case 'listar_categorias':
                $sql = "SELECT id, nome FROM categorias WHERE tenant_id = ? AND status = 'ativo'";
                $categorias = $this->db->fetchAll($sql, [$this->tenantId]);
                return ['success' => true, 'categorias' => $categorias];
                
            default:
                throw new \Exception("Ferramenta não encontrada no Agente de Estoque: $name");
        }
    }
}
