<?php

namespace System;

use Exception;

/**
 * n8n AI Service
 * 
 * Replaces direct OpenAI calls with n8n webhook integration
 * Uses MCP (Model Context Protocol) for efficient data retrieval
 */
class N8nAIService
{
    private $config;
    private $session;
    private $webhookUrl;
    private $timeout;
    
    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->session = Session::getInstance();
        
        // Get n8n webhook URL for AI/MCP from environment
        $this->webhookUrl = $this->config->getEnv('AI_N8N_WEBHOOK_URL');
        
        if (empty($this->webhookUrl)) {
            throw new Exception('AI_N8N_WEBHOOK_URL not configured in environment');
        }
        
        $this->timeout = (int) ($this->config->getEnv('AI_N8N_TIMEOUT') ?: 30);
    }
    
    /**
     * Process user message through n8n workflow
     * 
     * @param string $message User message/question
     * @param array $attachments Optional file attachments
     * @param int $tenantId Optional override tenant ID (for webhook context)
     * @param int $filialId Optional override filial ID (for webhook context)
     * @param array $additionalContext Optional additional context data
     * @return array Response from AI
     */
    public function processMessage($message, $attachments = [], $tenantId = null, $filialId = null, $additionalContext = [])
    {
        try {
            // Use provided IDs or get from session
            $tenantId = $tenantId ?? $this->session->getTenantId();
            $filialId = $filialId ?? $this->session->getFilialId();
            $userId = $this->session->getUserId();
            
            // Validate tenant and filial IDs
            if (!$tenantId || !$filialId) {
                throw new Exception('Multi-tenant system requires valid tenant_id and filial_id');
            }
            
            // Get rich context data with error handling
            $db = \System\Database::getInstance();
            
            // Get tenant info (with fallback)
            $tenant = ['id' => $tenantId, 'nome' => 'Estabelecimento', 'subdomain' => '', 'cnpj' => '', 'telefone' => '', 'email' => ''];
            try {
                $tenantData = $db->fetch("SELECT id, nome, subdomain, cnpj, telefone, email FROM tenants WHERE id = ?", [$tenantId]);
                if ($tenantData) {
                    $tenant = $tenantData;
                }
            } catch (Exception $e) {
                error_log("N8nAIService - Error fetching tenant: " . $e->getMessage());
            }
            
            // Get filial info (with fallback)
            $filial = ['id' => $filialId, 'nome' => 'Matriz', 'endereco' => '', 'telefone' => ''];
            try {
                $filialData = $db->fetch("SELECT id, nome, endereco, telefone FROM filiais WHERE id = ?", [$filialId]);
                if ($filialData) {
                    $filial = $filialData;
                }
            } catch (Exception $e) {
                error_log("N8nAIService - Error fetching filial: " . $e->getMessage());
            }
            
            // Get user info if available (with fallback)
            $user = null;
            if ($userId) {
                try {
                    $user = $db->fetch("SELECT id, login, nivel FROM usuarios WHERE id = ?", [$userId]);
                } catch (Exception $e) {
                    error_log("N8nAIService - Error fetching user: " . $e->getMessage());
                }
            }
            
            // Determine message source/type
            $source = $additionalContext['source'] ?? 'web';
            $messageType = $additionalContext['message_type'] ?? 'chat';
            
            // Get business hours and operational info
            $currentHour = (int) date('H');
            $isBusinessHours = ($currentHour >= 9 && $currentHour < 22);
            $dayOfWeek = date('w'); // 0 = Sunday, 6 = Saturday
            
            // Get some statistics for context (with error handling)
            $stats = ['pedidos_hoje' => 0, 'mesas_ocupadas' => 0, 'mesas_disponiveis' => 0, 'pedidos_ativos' => 0];
            
            try {
                $statsQuery = $db->fetch("
                    SELECT 
                        COUNT(DISTINCT CASE WHEN p.data = CURRENT_DATE THEN p.idpedido END) as pedidos_hoje,
                        COUNT(DISTINCT CASE WHEN m.status = '2' THEN m.id_mesa END) as mesas_ocupadas,
                        COUNT(DISTINCT CASE WHEN m.status = '1' THEN m.id_mesa END) as mesas_disponiveis,
                        COUNT(DISTINCT CASE WHEN p.status IN ('Pendente', 'Em Preparo') THEN p.idpedido END) as pedidos_ativos
                    FROM mesas m
                    LEFT JOIN pedido p ON p.tenant_id = m.tenant_id AND p.filial_id = m.filial_id
                    WHERE m.tenant_id = ? AND m.filial_id = ?
                ", [$tenantId, $filialId]);
                
                if ($statsQuery) {
                    $stats = $statsQuery;
                }
            } catch (Exception $statsError) {
                error_log("N8nAIService - Stats query failed: " . $statsError->getMessage());
                // Continue with default values
            }
            
            // Build enriched payload
            $payload = [
                // Core message
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                
                // Context IDs (for MCP queries)
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'user_id' => $userId,
                
                // Rich context
                'context' => [
                    // Business info
                    'tenant' => [
                        'id' => $tenant['id'] ?? $tenantId,
                        'nome' => $tenant['nome'] ?? 'Unknown',
                        'subdomain' => $tenant['subdomain'] ?? '',
                        'telefone' => $tenant['telefone'] ?? '',
                        'email' => $tenant['email'] ?? '',
                        'cnpj' => $tenant['cnpj'] ?? ''
                    ],
                    'filial' => [
                        'id' => $filial['id'] ?? $filialId,
                        'nome' => $filial['nome'] ?? 'Matriz',
                        'endereco' => $filial['endereco'] ?? '',
                        'telefone' => $filial['telefone'] ?? ''
                    ],
                    
                    // User/Agent info
                    'user' => $user ? [
                        'id' => $user['id'],
                        'login' => $user['login'],
                        'nivel' => $user['nivel'],
                        'is_admin' => $user['nivel'] == 1,
                        'role' => $user['nivel'] == 1 ? 'admin' : ($user['nivel'] == 2 ? 'manager' : 'operator')
                    ] : null,
                    
                    // Message metadata
                    'source' => $source, // 'web', 'whatsapp', 'api', 'n8n'
                    'message_type' => $messageType, // 'chat', 'command', 'order', 'query', 'billing'
                    'channel' => $source === 'whatsapp' ? 'whatsapp' : 'web',
                    
                    // Operational context
                    'operational' => [
                        'is_business_hours' => $isBusinessHours,
                        'current_hour' => $currentHour,
                        'day_of_week' => $dayOfWeek,
                        'is_weekend' => in_array($dayOfWeek, [0, 6]),
                        'pedidos_hoje' => (int) ($stats['pedidos_hoje'] ?? 0),
                        'mesas_ocupadas' => (int) ($stats['mesas_ocupadas'] ?? 0),
                        'mesas_disponiveis' => (int) ($stats['mesas_disponiveis'] ?? 0),
                        'pedidos_ativos' => (int) ($stats['pedidos_ativos'] ?? 0)
                    ],
                    
                    // Service type hints (helps AI decide prompt)
                    'service_type' => $this->detectServiceType($message, $source),
                ],
                
                // Suggested prompts by service type
                'prompts' => $this->getPromptsByServiceType(
                    $this->detectServiceType($message, $source),
                    $tenant,
                    $filial,
                    $source
                ),
                
                // Customer context (if from WhatsApp)
                'customer' => isset($additionalContext['customer_phone']) ? [
                    'phone' => $additionalContext['customer_phone'] ?? '',
                    'name' => $additionalContext['customer_name'] ?? '',
                    'whatsapp' => $additionalContext['customer_phone'] ?? '',
                    'is_new' => $additionalContext['is_new_customer'] ?? false
                ] : null,
                
                // Session metadata
                'session' => [
                    'conversation_id' => $additionalContext['conversation_id'] ?? uniqid('conv_'),
                    'platform' => $source,
                    'language' => 'pt-BR',
                    'timezone' => 'America/Sao_Paulo'
                ]
            ];
            
            // Merge any additional context
            if (!empty($additionalContext)) {
                foreach ($additionalContext as $key => $value) {
                    if (!isset($payload[$key]) && !in_array($key, ['source', 'message_type', 'customer_phone', 'customer_name', 'conversation_id'])) {
                        $payload[$key] = $value;
                    }
                }
            }
            
            // Add attachment info if present
            if (!empty($attachments)) {
                $payload['attachments'] = array_map(function($attachment) {
                    // Convert file to base64 for n8n processing
                    $fileContent = '';
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $fileContent = base64_encode(file_get_contents($attachment['path']));
                    }
                    
                    return [
                        'name' => $attachment['name'] ?? '',
                        'type' => $attachment['type'] ?? '',
                        'path' => $attachment['path'] ?? '',
                        'content' => $fileContent,
                        'size' => isset($attachment['path']) ? filesize($attachment['path']) : 0
                    ];
                }, $attachments);
            }
            
            // Call n8n webhook
            $response = $this->callN8nWebhook($payload);
            
            // Parse and return response
            return $this->parseN8nResponse($response);
            
        } catch (Exception $e) {
            error_log('N8n AI Service Error: ' . $e->getMessage());
            return [
                'type' => 'error',
                'message' => 'Erro ao processar sua solicitação. Por favor, tente novamente.'
            ];
        }
    }
    
    /**
     * Execute confirmed operation
     * 
     * @param array $operation Operation details
     * @return array Result of operation
     */
    public function executeOperation($operation)
    {
        try {
            // Some operations need to be executed locally
            // (database writes, file operations, etc.)
            
            if (!isset($operation['type'])) {
                throw new Exception('Operation type not specified');
            }
            
            switch ($operation['type']) {
                case 'create_product':
                case 'update_product':
                case 'delete_product':
                case 'create_category':
                case 'create_ingredient':
                case 'create_order':
                    // These need local execution for security
                    return $this->executeLocalOperation($operation);
                    
                default:
                    // Query operations can go through n8n
                    return $this->executeRemoteOperation($operation);
            }
            
        } catch (Exception $e) {
            error_log('Execute Operation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao executar operação: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Call n8n webhook
     * 
     * @param array $payload Request payload
     * @return array Response from n8n
     * @throws Exception On error
     */
    private function callN8nWebhook($payload)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->webhookUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Divino-Lanches/1.0'
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            error_log('n8n HTTP Error ' . $httpCode . ': ' . $response);
            throw new Exception('n8n webhook returned HTTP ' . $httpCode);
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from n8n webhook');
        }
        
        return $decoded;
    }
    
    /**
     * Parse n8n response
     * 
     * @param array $response Raw response from n8n
     * @return array Parsed response
     */
    private function parseN8nResponse($response)
    {
        if (!isset($response['success']) || !$response['success']) {
            return [
                'type' => 'error',
                'message' => $response['message'] ?? 'Erro desconhecido'
            ];
        }
        
        if (!isset($response['response'])) {
            return [
                'type' => 'error',
                'message' => 'Resposta inválida do servidor'
            ];
        }
        
        return $response['response'];
    }
    
    /**
     * Execute operation locally (database writes)
     * 
     * @param array $operation Operation details
     * @return array Result
     */
    private function executeLocalOperation($operation)
    {
        // Use existing OpenAI service for local operations
        // This maintains security for database writes
        $openAIService = new OpenAIService();
        return $openAIService->executeOperation($operation);
    }
    
    /**
     * Execute operation remotely through n8n
     * 
     * @param array $operation Operation details
     * @return array Result
     */
    private function executeRemoteOperation($operation)
    {
        $tenantId = $this->session->getTenantId();
        $filialId = $this->session->getFilialId();
        
        if (!$tenantId || !$filialId) {
            throw new Exception('Multi-tenant system requires valid tenant_id and filial_id from user session');
        }
        
        $payload = [
            'action' => 'execute_operation',
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ];
        
        $response = $this->callN8nWebhook($payload);
        
        return $response['result'] ?? [
            'success' => false,
            'message' => 'Erro ao executar operação'
        ];
    }
    
    /**
     * Get suggested prompts by service type
     * Returns ready-to-use system prompts for n8n AI Agent
     * 
     * @param string $serviceType Detected service type
     * @param array $tenant Tenant data
     * @param array $filial Filial data
     * @param string $source Message source
     * @return array Prompts (system, user, tools_instruction)
     */
    private function getPromptsByServiceType($serviceType, $tenant, $filial, $source)
    {
        $tenantName = $tenant['nome'] ?? 'Estabelecimento';
        $filialEndereco = $filial['endereco'] ?? '';
        $filialTelefone = $filial['telefone'] ?? '';
        
        $basePrompt = "Você é um assistente virtual inteligente do **{$tenantName}**.\n\n";
        
        switch ($serviceType) {
            case 'order':
                return [
                    'system' => $basePrompt . 
"**SUA MISSÃO:** Receber e processar pedidos de forma eficiente e amigável.

**INFORMAÇÕES DO ESTABELECIMENTO:**
- Nome: {$tenantName}
- Endereço: {$filialEndereco}
- Telefone: {$filialTelefone}

**FERRAMENTAS MCP DISPONÍVEIS:**
1. **search_products** - Buscar produtos no cardápio
   - Use quando cliente mencionar item específico
   - Exemplo: search_products(term='x-bacon', limit=5)

2. **get_categories** - Listar categorias do cardápio
   - Use quando cliente perguntar 'o que tem' ou 'cardápio'

3. **create_order** - Criar pedido completo
   - Use APENAS após confirmar todos os itens e valores
   - Estrutura: {cliente, telefone_cliente, tipo_entrega, itens, forma_pagamento}
   - Validar endereço para delivery
   - Validar mesa_id para pedidos presenciais

**FLUXO DE ATENDIMENTO:**
1. Saudação cordial (Bom dia/tarde/noite)
2. Buscar produtos mencionados (search_products)
3. Confirmar itens, quantidades e valores
4. Perguntar tipo de entrega (delivery/balcão/mesa)
5. Se delivery: Solicitar endereço completo
6. Se mesa: Perguntar número da mesa
7. Confirmar forma de pagamento
8. Criar pedido (create_order)
9. Confirmar número do pedido e tempo estimado

**REGRAS:**
- Sempre use emojis para comunicação mais amigável 😊🍔
- Confirme valores ANTES de criar pedido
- Para delivery, endereço completo é obrigatório
- Tempo estimado padrão: 30-45 minutos
- Seja cordial e profissional
- Se cliente não souber o que pedir, sugira categorias populares

**EXEMPLO DE CONVERSA:**
Cliente: \"Quero 2 X-Bacon sem cebola\"
Você: \"Oi! 😊 Encontrei no cardápio:
🍔 X-Bacon - R$ 15,90

Você quer 2 unidades? (Total: R$ 31,80)
Sem cebola, anotado! ✅

Será para delivery ou retirada no balcão?\"",

                    'tools_instruction' => 
"**COMO USAR AS FERRAMENTAS:**

**1. Buscar Produto:**
```json
{
  \"tool\": \"search_products\",
  \"parameters\": {\"term\": \"x-bacon\", \"limit\": 5}
}
```

**2. Criar Pedido:**
```json
{
  \"tool\": \"create_order\",
  \"parameters\": {
    \"cliente\": \"Nome do Cliente\",
    \"telefone_cliente\": \"11999999999\",
    \"tipo_entrega\": \"delivery\",
    \"endereco\": \"Rua X, 123\",
    \"itens\": [
      {\"produto_id\": 15, \"quantidade\": 2, \"observacao\": \"Sem cebola\"}
    ],
    \"forma_pagamento\": \"PIX\"
  }
}
```

**IMPORTANTE:** 
- SEMPRE busque produtos primeiro (search_products)
- SEMPRE confirme valores antes de criar pedido
- NUNCA crie pedido sem confirmar com cliente",

                    'type' => 'order'
                ];
                
            case 'query':
                return [
                    'system' => $basePrompt .
"**SUA MISSÃO:** Responder perguntas sobre produtos, preços, horários e informações gerais.

**ESTABELECIMENTO:**
- {$tenantName}
- {$filialEndereco}
- Telefone: {$filialTelefone}
- Horário: Segunda a Sexta 9h-22h, Sábado e Domingo 10h-23h

**FERRAMENTAS MCP:**
1. **get_products** - Listar produtos por categoria
2. **search_products** - Buscar produto específico
3. **get_categories** - Ver todas categorias
4. **get_tables** - Ver disponibilidade de mesas

**INSTRUÇÕES:**
- Seja objetivo e claro
- Sempre mencione preços quando disponível
- Use emojis para melhor visual
- Se não encontrar, sugira alternativas
- Para horários, confirme contexto operacional

**EXEMPLO:**
Cliente: \"Quanto custa o X-Tudo?\"
Você: \"O X-Tudo custa R$ 18,90! 🍔
Ele vem com hambúrguer, bacon, queijo, ovo, presunto, alface e tomate.
Deseja fazer um pedido? 😊\"",

                    'tools_instruction' => "Use search_products para buscar itens específicos. Sempre mostre preços.",
                    'type' => 'query'
                ];
                
            case 'billing':
                return [
                    'system' => $basePrompt .
"**SUA MISSÃO:** Auxiliar clientes com pagamentos e consultas de débitos.

**FERRAMENTAS MCP:**
1. **get_fiado_customers** - Buscar débitos do cliente
2. **get_orders** - Histórico de pedidos
3. **create_payment** - Registrar pagamento (quando confirmado)

**DADOS DE PAGAMENTO:**
- PIX: {$filialTelefone}
- Nome: {$tenantName}

**INSTRUÇÕES:**
- Consulte débitos usando get_fiado_customers
- Seja educado e compreensivo
- Ofereça opções de pagamento: PIX ou presencial
- Confirme pagamentos antes de registrar
- Agradeça pelo pagamento

**EXEMPLO:**
Cliente: \"Quanto eu devo?\"
Você: \"Oi! Vou consultar para você... 🔍

Você tem um saldo pendente de R$ 45,50:
📋 Pedido #123 (02/11): R$ 25,00
📋 Pedido #145 (03/11): R$ 20,50

Pode pagar via:
💳 PIX: {$filialTelefone} (MOACIR FERREIRA DOS SANTOS)
🏪 Ou presencial em: {$filialEndereco}

Assim que realizar o pagamento, me avise para confirmar! 😊\"",

                    'tools_instruction' => "Use get_fiado_customers(search=telefone_cliente) para buscar débitos",
                    'type' => 'billing'
                ];
                
            case 'management':
                return [
                    'system' => $basePrompt .
"**SUA MISSÃO:** Auxiliar na gestão administrativa do sistema.

**FERRAMENTAS MCP ADMINISTRATIVAS:**
1. **create_product** - Criar novo produto
2. **update_product** - Atualizar produto
3. **delete_product** - Excluir produto
4. **create_category** - Criar categoria
5. **create_ingredient** - Criar ingrediente
6. **create_customer** - Cadastrar cliente
7. **create_financial_entry** - Criar lançamento financeiro

**INSTRUÇÕES:**
- Confirme dados antes de executar operações
- Para criar produto: nome, categoria_id, preço obrigatórios
- Para criar categoria: nome e tipo (produto/ingrediente)
- Para lançamento: tipo, valor, descrição, categoria
- Sempre valide se usuário tem permissão
- Retorne confirmação clara após cada operação

**EXEMPLO:**
Usuário: \"Cadastrar novo produto: Batata Frita R$ 12,00\"
Você: \"Vou cadastrar:
🍟 Batata Frita - R$ 12,00

Qual categoria? (Lanches, Porções, Bebidas, etc)\"",

                    'tools_instruction' => "Valide permissões e confirme dados antes de executar. Use create_* para inserir.",
                    'type' => 'management'
                ];
                
            case 'support':
                $tenantEmail = $tenant['email'] ?? 'contato@estabelecimento.com';
                return [
                    'system' => $basePrompt .
"**SUA MISSÃO:** Oferecer suporte e resolver problemas.

**INFORMAÇÕES DE CONTATO:**
- Telefone: {$filialTelefone}
- Email: {$tenantEmail}

**INSTRUÇÕES:**
- Seja empático e prestativo
- Para problemas técnicos: Encaminhe para suporte
- Para dúvidas de uso: Explique passo a passo
- Para reclamações: Ouça, anote e ofereça solução

**FERRAMENTAS:**
- get_orders - Para consultar pedidos com problema
- get_customers - Para buscar histórico do cliente

Sempre finalize oferecendo mais ajuda.",

                    'tools_instruction' => "Use get_orders e get_customers para investigar problemas relatados",
                    'type' => 'support'
                ];
                
            default: // 'chat'
                return [
                    'system' => $basePrompt .
"**SUA MISSÃO:** Conversar de forma amigável e direcionar para o serviço adequado.

**ESTABELECIMENTO:**
- {$tenantName}
- {$filialEndereco}
- Telefone: {$filialTelefone}

**VOCÊ PODE AJUDAR COM:**
- 🍔 Fazer pedidos
- 💰 Consultar débitos
- ❓ Tirar dúvidas sobre cardápio
- 📞 Informações de contato

**INSTRUÇÕES:**
- Saudação cordial baseada no horário
- Pergunte como pode ajudar
- Direcione para o serviço adequado
- Use emojis para comunicação amigável

**FERRAMENTAS:**
- search_products - Para mostrar opções
- get_categories - Para listar categorias

Seja simpático e prestativo! 😊",

                    'tools_instruction' => "Identifique a intenção do cliente e use as ferramentas apropriadas",
                    'type' => 'chat'
                ];
        }
    }
    
    /**
     * Detect service type from message content
     * Helps AI choose the right prompt and behavior
     * 
     * @param string $message User message
     * @param string $source Message source
     * @return string Service type
     */
    private function detectServiceType($message, $source)
    {
        $messageLower = mb_strtolower($message);
        
        // Order keywords
        $orderKeywords = ['quero', 'pedir', 'fazer pedido', 'mandar', 'delivery', 'entregar', 'levar'];
        foreach ($orderKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'order';
            }
        }
        
        // Query/Info keywords
        $queryKeywords = ['quanto custa', 'preço', 'valor', 'cardápio', 'menu', 'tem ', 'quais', 'horário', 'aberto'];
        foreach ($queryKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'query';
            }
        }
        
        // Billing keywords
        $billingKeywords = ['pagar', 'dívida', 'débito', 'quanto devo', 'pendente', 'fiado'];
        foreach ($billingKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'billing';
            }
        }
        
        // Management keywords (admin only)
        $managementKeywords = ['cadastrar', 'adicionar produto', 'criar categoria', 'excluir', 'editar'];
        foreach ($managementKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'management';
            }
        }
        
        // Support keywords
        $supportKeywords = ['ajuda', 'suporte', 'problema', 'erro', 'não funciona'];
        foreach ($supportKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return 'support';
            }
        }
        
        // Default
        return 'chat';
    }
    
    /**
     * Get service health status
     * 
     * @return array Health status
     */
    public function getHealthStatus()
    {
        try {
            // Try to ping n8n webhook health endpoint
            $healthUrl = str_replace('/webhook/', '/webhook-test/', $this->webhookUrl);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $healthUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_NOBODY => true
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return [
                'status' => $httpCode === 200 ? 'online' : 'offline',
                'webhook_url' => $this->webhookUrl,
                'http_code' => $httpCode
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
