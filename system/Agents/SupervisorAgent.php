<?php
namespace System\Agents;

/**
 * Supervisor para equipe/garçons (whatsapp_admins). Clientes usam ClienteWhatsAppAgent diretamente.
 */
class SupervisorAgent extends BaseAgent {

    protected function getSystemPrompt(): string {
        return "Você é o Gerente Geral (Supervisor) do DivinoSys. Sua função é receber a mensagem do usuário da EQUIPE e decidir qual departamento deve cuidar da solicitação.\n" .
               "Você tem uma equipe de agentes especializados:\n" .
               "- Estoque/Cardápio: Responde sobre preços, produtos, ingredientes e categorias.\n" .
               "- Garçom: Cuida de tirar pedidos, adicionar itens em comandas e verificar mesas (SOMENTE para funcionários/garçons).\n" .
               "- Financeiro: Cuida do fiado, faturas, dívidas e pagamentos.\n" .
               "- Atendente: Lida com informações de cadastro de clientes e histórico geral.\n\n" .
               "REGRAS OBRIGATÓRIAS:\n" .
               "1. Se a mensagem for sobre um assunto específico, DELEGUE imediatamente para o agente correspondente usando as ferramentas disponíveis.\n" .
               "2. Ao delegar, NÃO envie texto junto — chame a ferramenta silenciosamente.\n" .
               "3. Se for um simples 'Oi' ou pergunta geral, responda DIRETAMENTE como o Gerente Inteligente.\n" .
               "4. O agente delegado continuará em loop com ferramentas até concluir a solicitação.";
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
                            'resumo_solicitacao' => ['type' => 'string']
                        ],
                        'required' => ['resumo_solicitacao']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegar_para_garcom',
                    'description' => 'Aciona o Garçom Virtual para pedidos de mesa/comanda (equipe interna).',
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
                    'description' => 'Aciona o Agente Financeiro para fiado, dívidas, pagamentos e faturas.',
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
                    'description' => 'Aciona o Atendente para cadastros de clientes e histórico.',
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
                return ['success' => false, 'message' => 'Departamento desconhecido: ' . $name];
        }

        $agent->setContext($this->tenantId, $this->filialId);
        $agent->setPersonaPrompt($this->personaPrompt);
        $agent->setIgnoreStock($this->ignoreStock);

        $result = $agent->process($this->messagesHistory, 10);

        if (isset($result['response'])) {
            return ['_final_handoff_response' => $result['response']];
        }

        return $result;
    }
}
