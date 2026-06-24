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
                    'parameters' => ['type' => 'object', 'properties' => []]
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
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        if ($name === 'buscar_cliente') {
            $sql = "SELECT id, nome, telefone, email FROM clientes WHERE tenant_id = ? AND nome ILIKE ? LIMIT 10";
            $nome = $args['nome'] ?? '';
            $clientes = $this->db->fetchAll($sql, [$this->tenantId, '%' . $nome . '%']);
            return ['success' => true, 'clientes' => $clientes];
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
