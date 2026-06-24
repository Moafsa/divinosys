<?php
namespace System\Agents;

class SupervisorAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        return "Você é o Gerente Geral (Supervisor) do DivinoSys. Sua função é receber a mensagem do usuário e decidir qual departamento deve cuidar da solicitação.\n" .
               "Você tem uma equipe de agentes especializados:\n" .
               "- Estoque/Cardápio: Responde sobre preços, produtos, ingredientes e categorias.\n" .
               "- Garçom: Cuida de tirar pedidos novos, adicionar itens em comandas e verificar mesas.\n" .
               "- Financeiro: Cuida do fiado, faturas, dívidas e pagamentos.\n" .
               "- Atendente: Lida com informações de cadastro de clientes e histórico geral.\n\n" .
               "REGRAS OBRIGATÓRIAS:\n" .
               "1. Se a mensagem for sobre um assunto específico, DELEGUE imediatamente para o agente correspondente usando as ferramentas disponíveis.\n" .
               "2. Se for um simples 'Oi' ou pergunta geral sobre quem você é, responda DIRETAMENTE como o Gerente Inteligente.\n" .
               "3. Se o usuário perguntar algo como 'quantos X existem no sistema', ele está se referindo ao banco de dados do restaurante (ex: clientes cadastrados, usuários, produtos). Delegue para o agente correto (Atendente para pessoas/clientes, Estoque para produtos, etc).";
    }
    
    protected function getTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegar_para_estoque',
                    'description' => 'Aciona o Agente de Estoque para buscar produtos, ver preços, criar, editar ou excluir produtos/categorias/ingredientes, e gerenciar o estoque.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'resumo_solicitacao' => ['type' => 'string', 'description' => 'Resumo do que o usuário quer do estoque/cardápio.']
                        ],
                        'required' => ['resumo_solicitacao']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegar_para_garcom',
                    'description' => 'Aciona o Garçom para lançar pedidos, criar comandas, adicionar itens a pedidos existentes ou checar mesas ativas.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'resumo_solicitacao' => ['type' => 'string']
                        ],
                        'required' => ['resumo_solicitacao']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegar_para_financeiro',
                    'description' => 'Aciona o Agente Financeiro para lidar com fiado, dívidas, pagamentos, baixar dívida, fatura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'resumo_solicitacao' => ['type' => 'string']
                        ],
                        'required' => ['resumo_solicitacao']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegar_para_atendente',
                    'description' => 'Aciona o Atendente para lidar com cadastros de clientes e busca de histórico/extrato de consumo.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'resumo_solicitacao' => ['type' => 'string']
                        ],
                        'required' => ['resumo_solicitacao']
                    ]
                ]
            ]
        ];
    }
    
    private $messagesHistory = [];
    
    // We override process to store messages so executeTool can pass them
    public function process(array $messages, $maxIterations = 4) {
        $this->messagesHistory = $messages;
        return parent::process($messages, $maxIterations);
    }
    
    protected function executeTool(string $name, array $args): array {
        $agent = null;
        
        switch ($name) {
            case 'delegar_para_estoque':
                $agent = new EstoqueAgent();
                break;
            case 'delegar_para_garcom':
                $agent = new GarcomAgent();
                break;
            case 'delegar_para_financeiro':
                $agent = new FinanceiroAgent();
                break;
            case 'delegar_para_atendente':
                $agent = new AtendenteAgent();
                break;
            default:
                return ['success' => false, 'message' => 'Departamento desconhecido'];
        }
        
        $agent->setContext($this->tenantId, $this->filialId);
        
        // Pass the entire conversation history to the specialized agent so it understands the full context
        $result = $agent->process($this->messagesHistory, 4);
        
        // The result from the specialized agent is directly returned to the user,
        // so we format it as a special action to tell the Supervisor loop to stop and return this exactly.
        if (isset($result['response'])) {
            return ['_final_handoff_response' => $result['response']];
        }
        
        return $result;
    }
}
