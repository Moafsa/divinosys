<?php
namespace System\Agents;

class FinanceiroAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente Financeiro do DivinoSys. Sua função é gerenciar o fiado, contas de clientes, pagamentos e faturas.\n" .
               "Você pode buscar dívidas, registrar pagamentos e gerar faturas.\n" .
               "Se um usuário não informar o ID do cliente, use `listar_pendencias_fiado` para buscar o cliente pelo nome antes de registrar pagamentos.";
    }
    
    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_pendencias_fiado',
                    'description' => 'Busca os clientes que possuem dívidas no fiado.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome_cliente' => [
                                'type' => 'string',
                                'description' => 'Nome do cliente para buscar (opcional)'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'baixar_pagamento_fiado',
                    'description' => 'Registra um pagamento de fiado para um cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'cliente_id' => [
                                'type' => 'integer',
                                'description' => 'ID do cliente'
                            ],
                            'pagamentos' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'valor' => ['type' => 'number'],
                                        'forma_pagamento' => ['type' => 'string', 'enum' => ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito']]
                                    ]
                                ]
                            ],
                            'desconto_valor' => ['type' => 'number'],
                            'destino' => ['type' => 'string', 'enum' => ['fiado', 'pedido', 'ambos']]
                        ],
                        'required' => ['cliente_id', 'pagamentos']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'gerar_fatura_fiado',
                    'description' => 'Gera o extrato/fatura de um cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'cliente_id' => [
                                'type' => 'integer',
                                'description' => 'ID do cliente'
                            ]
                        ],
                        'required' => ['cliente_id']
                    ]
                ]
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        // Usa o serviço legado temporariamente para não duplicar 1000 linhas de código
        $legacyService = new \System\OpenAIService();
        
        // Garante que o tenant_id e filial_id estão no data
        $args['tenant_id'] = $this->tenantId;
        $args['filial_id'] = $this->filialId;
        
        $operation = [
            'type' => $name,
            'data' => $args
        ];
        
        return $legacyService->executeOperation($operation);
    }
}
