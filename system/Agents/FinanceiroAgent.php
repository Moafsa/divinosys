<?php
namespace System\Agents;

class FinanceiroAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Agente Financeiro do DivinoSys. Sua função é gerenciar o fiado, contas de clientes, pagamentos, faturas e despesas operacionais do restaurante.\n" .
               "Você pode buscar dívidas, registrar pagamentos, gerar faturas e lançar contas a pagar (despesas).\n" .
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
                            'nome_cliente' => ['type' => 'string', 'description' => 'Nome do cliente para buscar (opcional)']
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
                            'cliente_id' => ['type' => 'integer'],
                            'valor_pago' => ['type' => 'number'],
                            'desconto_valor' => ['type' => 'number'],
                            'destino' => ['type' => 'string', 'enum' => ['fiado', 'pedido', 'ambos']]
                        ],
                        'required' => ['cliente_id', 'valor_pago']
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
                            'cliente_id' => ['type' => 'integer']
                        ],
                        'required' => ['cliente_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'configurar_cobranca_fiado',
                    'description' => 'Configura os lembretes de cobrança automática para um cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'cliente_id' => ['type' => 'integer'],
                            'frequencia' => ['type' => 'string', 'enum' => ['diaria', 'semanal', 'mensal']],
                            'ativo' => ['type' => 'boolean']
                        ],
                        'required' => ['cliente_id', 'frequencia', 'ativo']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_despesa',
                    'description' => 'Registra uma saída de caixa/despesa operacional (contas a pagar ou pagas).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'descricao' => ['type' => 'string'],
                            'valor' => ['type' => 'number'],
                            'categoria_id' => ['type' => 'integer', 'description' => 'ID da categoria financeira'],
                            'data_pagamento' => ['type' => 'string', 'description' => 'Data no formato YYYY-MM-DD (deixe vazio se for hoje)']
                        ],
                        'required' => ['descricao', 'valor']
                    ]
                ]
            ]
        ];
    }
    
    protected function executeTool(string $name, array $args): array {
        $legacyService = new \System\OpenAIService();
        $args['tenant_id'] = $this->tenantId;
        $args['filial_id'] = $this->filialId;
        
        $operation = [
            'type' => $name,
            'data' => $args
        ];
        
        return $legacyService->executeOperation($operation);
    }
}
