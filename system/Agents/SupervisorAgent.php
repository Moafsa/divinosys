<?php
namespace System\Agents;

class SupervisorAgent extends BaseAgent {
    
    protected function getSystemPrompt(): string {
        if ($this->whatsappCustomerMode) {
            return "Você é o Assistente Virtual de Atendimento ao Cliente via WhatsApp do restaurante.\n" .
                   "Sua missão é atender o cliente de forma direta, tirar dúvidas do cardápio e anotar pedidos.\n" .
                   "Para fazer isso, você usará ferramentas internas (delegar_para_estoque, delegar_para_pedidos, etc).\n" .
                   "REGRA CRÍTICA 1: NUNCA diga ao cliente 'Vou consultar o estoque', 'Vou confirmar com a equipe', 'Vou passar para o garçom'. O cliente NÃO PODE SABER que você está usando ferramentas ou departamentos. Aja como se a resposta fosse instantânea e você fizesse tudo sozinho.\n" .
                   "REGRA CRÍTICA 2: Ao usar uma ferramenta de delegação, NÃO envie nenhuma mensagem de texto junto. Apenas chame a ferramenta silenciosamente.\n" .
                   "REGRA CRÍTICA 3: Responda seguindo a PERSONALIZAÇÃO DO ATENDIMENTO WHATSAPP (nome e tom).";
        }

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
        if ($this->whatsappCustomerMode) {
            return [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'buscar_produtos',
                        'description' => 'Busca produtos no cardápio pelo nome para descobrir preços ou verificar disponibilidade.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string']
                            ],
                            'required' => ['query']
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'listar_pedidos',
                        'description' => 'Lista todos os pedidos recentes do cliente.',
                        'parameters' => ['type' => 'object', 'properties' => (object)[]]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'create_order',
                        'description' => 'Cria um novo pedido em uma mesa ou comanda. Para clientes de WhatsApp, use mesa_id = 999 para delivery ou 998 para retirada no balcão.',
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
                        'description' => 'Adiciona itens a um pedido existente.',
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
                ]
            ];
        }

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
                    'description' => 'Aciona o sistema de pedidos para lançar pedidos, adicionar itens ou checar comandas ativas.',
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
        if ($this->whatsappCustomerMode && in_array($name, ['buscar_produtos', 'listar_pedidos', 'create_order', 'add_item_to_order'])) {
            if ($name === 'buscar_produtos') {
                $query = $args['query'] ?? '';
                if (empty($query)) return ['success' => false, 'message' => 'Termo de busca vazio'];
                
                $sql = "SELECT id, nome, preco_normal, preco_promocional, em_promocao, estoque_atual, ativo 
                        FROM produtos 
                        WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? AND COALESCE(ativo, true) = true
                        LIMIT 10";
                
                $produtos = $this->db->fetchAll($sql, [$this->tenantId, $this->filialId, '%' . $query . '%']);
                
                if (empty($produtos)) {
                    return ['success' => true, 'message' => 'Nenhum produto encontrado com esse nome.'];
                }
                return ['success' => true, 'produtos' => $produtos];
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
                return ['success' => false, 'message' => 'Departamento ou função desconhecida: ' . $name];
        }
        
        $agent->setContext($this->tenantId, $this->filialId);
        $agent->setPersonaPrompt($this->personaPrompt);
        $agent->setWhatsAppCustomerMode($this->whatsappCustomerMode);
        $agent->setIgnoreStock($this->ignoreStock);
        
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
