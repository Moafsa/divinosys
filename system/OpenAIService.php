<?php

namespace System;

use Exception;
use PDO;

class OpenAIService
{
    private $apiKey;
    private $config;
    private $db;
    private $session;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->db = Database::getInstance();
        $this->session = Session::getInstance();
        
        // Get OpenAI API key from environment
        $this->apiKey = $this->config->getEnv('OPENAI_API_KEY');
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }
    }

    /**
     * Process user message and determine action
     */
    public function processMessage($message, $attachments = [], $tenantId = null, $filialId = null, $context = [])
    {
        try {
            // Process attachments if any
            $attachmentData = [];
            if (!empty($attachments)) {
                $attachmentData = $this->processAttachments($attachments);
            }
            
            $this->tenantId = $tenantId ?? $this->session->getTenantId() ?? 1;
            $this->filialId = $filialId ?? $this->session->getFilialId() ?? 1;
            
            // Build the same prompt as admin in WhatsApp
            $systemPrompt = "Você é a IAm, a Inteligência Artificial e o cérebro integrado diretamente ao sistema de gestão deste restaurante. ";
            $systemPrompt .= "O usuário com quem você está falando é o Dono/Administrador do sistema (sua base de dados). ";
            $systemPrompt .= "ATENÇÃO: VOCÊ TEM ACESSO TOTAL E DIRETO AO BANCO DE DADOS deste restaurante (Tenant), incluindo clientes, finanças, fiado e comandas. Fale com confiança, não diga que é uma IA com restrições. Aja como o gerente geral do sistema. " .
                "Você tem permissão total para realizar ações gerenciais e atuar como *Garçom Online*. Você pode: criar_produto, listar_produtos, listar_pedidos, listar_pendencias_fiado, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado. " .
                "Como *Garçom Online*, você pode lançar pedidos nas mesas ou comandas. Ações disponíveis:\n" .
                "- create_order (data: {\"mesa_id\": \"5\", \"cliente\": \"Nome\", \"itens\": [{\"id\": 1, \"quantidade\": 2, \"preco\": 10.0, \"observacao\": \"\", \"tamanho\": \"normal\"}]})\n" .
                "- add_item_to_order (data: {\"pedido_id\": 10, \"itens\": [{\"id\": 2, \"quantidade\": 1, \"preco\": 15.0}]})\n" .
                "- remove_item_from_order (data: {\"pedido_item_id\": 25})\n\n" .
                "Para consultar e alterar estoque de produtos:\n" .
                "- ver_estoque (data: {\"produto_nome\": \"nome do produto\"})\n" .
                "- atualizar_estoque (data: {\"produto_nome\": \"nome do produto\", \"quantidade\": 10, \"operacao\": \"adicionar\"|\"remover\"|\"definir\"})\n\n" .
                "Para ações de fiado, as ações são: \n" .
                "- listar_pendencias_fiado (data: {\"nome_cliente\": \"opcional, nome do cliente para buscar saldo devedor\"})\n" .
                "- listar_compras_cliente (data: {\"nome_cliente\": \"nome do cliente para ver a lista de pedidos, consumos, pagamentos e descontos do fiado\"})\n" .
                "- configurar_cobranca_fiado (data: {\"cliente_id\": ID, \"frequencia\": \"diaria\"|\"semanal\"|\"mensal\", \"ativo\": true|false})\n" .
                "- gerar_fatura_fiado (data: {\"cliente_id\": ID})\n" .
                "- baixar_pagamento_fiado (data: {\"cliente_id\": ID, \"pagamentos\": [{\"valor\": 10.0, \"forma_pagamento\": \"dinheiro\"}], \"desconto_valor\": 2.50, \"destino\": \"ambos\"}). O destino pode ser 'fiado', 'pedido' ou 'ambos'. Se o usuário não informar as formas de pagamento para baixar um saldo, VOCÊ DEVE OBRIGATORIAMENTE PERGUNTAR antes de executar a ação. Se ele pedir um desconto % ou R$, calcule o valor final absoluto e mande em desconto_valor. \n" .
                "Sempre que o usuário relatar um pagamento (ex: 'o cliente pagou X'), você deve agir para BAIXAR O PAGAMENTO usando a ação baixar_pagamento_fiado. Se precisar do ID do cliente, chame listar_pendencias_fiado primeiro e, COM O RESULTADO EM MÃOS, decida o próximo passo. \n" .
                "Para executar UMA DESSAS AÇÕES, responda EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"nome_da_acao\",\"data\":{...}}. " .
                "IMPORTANTE: Se o usuário pedir o EXTRATO ou HISTÓRICO do fiado (ex: 'o que o Moacir consumiu?', 'quais são os pedidos pendentes dele?'), use a ação `listar_compras_cliente`. NÃO use essa ação se a intenção do usuário for pagar/baixar uma dívida. " .
                "Atenção: Os IDs que você acessa no fiado são 'IDs do Fiado', que podem ser diferentes dos IDs globais do cliente. Se for citar o ID, chame de 'ID Fiado'. " .
                "MUITO IMPORTANTE: Sempre que você falar sobre qualquer pedido (seja atual ou compras passadas), informe o número do pedido na sua resposta. " .
                "Se for criar um pedido e o usuário não der os preços, busque no CONTEXTO e inclua os IDs e precos corretos. ";
            
            // Generate context safely
            try {
                $systemContext = $this->getSystemContext();
                $systemPrompt .= "\n\nCONTEXTO ATUAL DO RESTAURANTE:\n" . $systemContext;
            } catch (\Exception $ctxErr) {
                error_log("Failed to load context: " . $ctxErr->getMessage());
            }

            // Prepare messages for OpenAI
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ]
            ];
            
            // Add chat history if available
            if (isset($context['chat_history']) && is_array($context['chat_history'])) {
                foreach ($context['chat_history'] as $msg) {
                    $msgText = $msg['content'] ?? $msg['text'] ?? '';
                    if (trim($msgText) !== '') {
                        $messages[] = [
                            'role' => (isset($msg['sender']) && $msg['sender'] === 'user') ? 'user' : 'assistant',
                            'content' => $msgText
                        ];
                    }
                }
            }
            
            // Add current message
            $messages[] = [
                'role' => 'user',
                'content' => $message
            ];
            
            // Add attachment data if available
            if (!empty($attachmentData)) {
                $lastIndex = count($messages) - 1;
                $messages[$lastIndex]['content'] .= "\n\nDados dos anexos:\n" . json_encode($attachmentData, JSON_PRETTY_PRINT);
            }
            
            // Use loop para MCP (Agentic Loop)
            $maxIterations = 4;
            $iteration = 0;
            $finalAction = ['type' => 'response', 'message' => 'Desculpe, não entendi.'];
            
            while ($iteration < $maxIterations) {
                $iteration++;
                
                $response = $this->callOpenAI($messages);
                $action = $this->parseAIResponse($response, $attachmentData);
                
                if (isset($action['type']) && $action['type'] === 'action') {
                    $messages[] = ['role' => 'assistant', 'content' => json_encode($action)];
                    
                    $opToExecute = [
                        'type' => $action['action'] ?? $action['type'],
                        'data' => $action['data'] ?? []
                    ];
                    
                    $execResult = $this->executeOperation($opToExecute);
                    
                    $resultStr = json_encode($execResult);
                    $messages[] = [
                        'role' => 'user',
                        'content' => "O sistema executou a ação e retornou:\n{$resultStr}\n\nAnalise o resultado. Se a tarefa foi totalmente concluída, retorne {'type':'response', 'message':'mensagem natural aqui'}. Se precisar perguntar algo ao usuário (como a forma de pagamento), retorne {'type':'response', 'message':'sua pergunta'}. Se precisar de outra ação do sistema, retorne outro {'type':'action', ...}."
                    ];
                    continue;
                } else {
                    $finalAction = $action;
                    break;
                }
            }
            
            return $finalAction;
            
        } catch (Exception $e) {
            error_log('OpenAI Service Error: ' . $e->getMessage());
            return [
                'type' => 'error',
                'message' => 'Erro ao processar sua solicitação: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get system context with current data
     */
    private function getSystemContext()
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $context = [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'current_time' => date('Y-m-d H:i:s'),
            'products' => $this->getProductsSummary($tenantId, $filialId),
            'categories' => $this->getCategoriesSummary($tenantId, $filialId),
            'ingredients' => $this->getIngredientsSummary($tenantId, $filialId),
            'active_orders' => $this->getActiveOrdersSummary($tenantId, $filialId),
            'tables' => $this->getTablesSummary($tenantId, $filialId)
        ];
        
        return json_encode($context, JSON_PRETTY_PRINT);
    }

    /**
     * Get system prompt for AI
     */
    private function getSystemPrompt()
    {
        return "Assistente IA para restaurante (IAm). Operações disponíveis: criar_produto, listar_produtos, criar_ingrediente, listar_ingredientes, criar_categoria, listar_categorias, listar_pedidos, listar_pendencias_fiado, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado, create_order, add_item_to_order, remove_item_from_order, ver_estoque, atualizar_estoque. Responda em português. Para confirmação: {\"type\":\"confirmation\",\"message\":\"...\",\"action\":\"acao\",\"confirm\":true}. Para respostas com ações: {\"type\":\"action\",\"action\":\"nome\",\"data\":{...}}.";
    }

    /**
     * Process WhatsApp messages
     */
    public function processWhatsAppMessage($message, $tenantId, $filialId, $context)
    {
        try {
            $isAdmin = $context['is_admin'] ?? false;
            $customerName = $context['customer_name'] ?? 'Cliente';
            
            // Temporarily set session IDs so getSystemContext works
            $originalTenant = $this->tenantId;
            $originalFilial = $this->filialId;
            $this->tenantId = $tenantId;
            $this->filialId = $filialId;
            
            $systemContext = $this->getSystemContext();
            
            // Restore originals
            $this->tenantId = $originalTenant;
            $this->filialId = $originalFilial;
            
            $systemPrompt = "Você é a IAm, a Inteligência Artificial integrada ao sistema do restaurante. O nome da pessoa falando no WhatsApp é $customerName. ";
            if ($isAdmin) {
                $systemPrompt .= "ATENÇÃO DE CONTEXTO: O usuário conversando agora é um MEMBRO DA EQUIPE INTERNA (Administrador ou Garçom). Como IA do sistema, VOCÊ TEM ACESSO TOTAL AO BANCO DE DADOS (Tenant), incluindo comandas, clientes e fiado. Aja como a gerente inteligente que apoia a equipe. Fale com confiança e autoridade de sistema, sem bloqueios de IA padrão. " .
                    "Você tem permissão para realizar ações gerenciais e receber pedidos da equipe. " .
                    "Você pode: criar_produto, listar_produtos, listar_pedidos, listar_pendencias_fiado, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado. " .
                    "Como *Garçom Online*, você pode lançar pedidos nas mesas ou comandas. Ações disponíveis:\n" .
                    "- create_order (data: {\"mesa_id\": \"5\", \"cliente\": \"Nome\", \"itens\": [{\"id\": 1, \"quantidade\": 2, \"preco\": 10.0, \"observacao\": \"\", \"tamanho\": \"normal\"}]})\n" .
                    "- add_item_to_order (data: {\"pedido_id\": 10, \"itens\": [{\"id\": 2, \"quantidade\": 1, \"preco\": 15.0}]})\n" .
                    "- remove_item_from_order (data: {\"pedido_item_id\": 25})\n\n" .
                    "Para consultar e alterar estoque de produtos:\n" .
                    "- ver_estoque (data: {\"produto_nome\": \"nome do produto\"})\n" .
                    "- atualizar_estoque (data: {\"produto_nome\": \"nome do produto\", \"quantidade\": 10, \"operacao\": \"adicionar\"|\"remover\"|\"definir\"})\n\n" .
                    "Para ações de fiado, as ações são: \n" .
                    "- listar_pendencias_fiado (data: {\"nome_cliente\": \"nome do cliente\"}). IMPORTANTE: Sempre que for buscar a dívida ou o ID de um cliente específico (ex: 'o moacir pagou'), VOCÊ DEVE OBRIGATORIAMENTE passar o 'nome_cliente' para não listar todos os devedores. Só envie sem 'nome_cliente' se o usuário explicitamente pedir a lista de todos.\n" .
                    "- listar_compras_cliente (data: {\"nome_cliente\": \"nome do cliente para ver a lista de pedidos, consumos, pagamentos e descontos do fiado\"})\n" .
                    "- configurar_cobranca_fiado (data: {\"cliente_id\": ID, \"frequencia\": \"diaria\"|\"semanal\"|\"mensal\", \"ativo\": true|false})\n" .
                    "- gerar_fatura_fiado (data: {\"cliente_id\": ID})\n" .
                    "- baixar_pagamento_fiado (data: {\"cliente_id\": ID, \"pagamentos\": [{\"valor\": 10.0, \"forma_pagamento\": \"dinheiro\"}], \"desconto_valor\": 2.50, \"destino\": \"ambos\"}). O destino pode ser 'fiado', 'pedido' ou 'ambos'. Se o usuário não informar as formas de pagamento para baixar um saldo, VOCÊ DEVE OBRIGATORIAMENTE PERGUNTAR antes de executar a ação. Se ele pedir um desconto % ou R$, calcule o valor final absoluto e mande em desconto_valor. \n" .
                    "Sempre que o usuário relatar um pagamento (ex: 'o cliente pagou X'), você deve agir para BAIXAR O PAGAMENTO usando a ação baixar_pagamento_fiado. Se precisar do ID do cliente, chame listar_pendencias_fiado primeiro e, COM O RESULTADO EM MÃOS, decida o próximo passo. \n" .
                    "Para executar UMA DESSAS AÇÕES, responda EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"nome_da_acao\",\"data\":{...}}. " .
                    "IMPORTANTE: Se o usuário pedir o EXTRATO ou HISTÓRICO do fiado (ex: 'o que o Moacir consumiu?', 'quais são os pedidos pendentes dele?'), use a ação `listar_compras_cliente`. NÃO use essa ação se a intenção do usuário for pagar/baixar uma dívida. " .
                    "Atenção: Os IDs que você acessa no fiado são 'IDs do Fiado', que podem ser diferentes dos IDs globais do cliente. Se for citar o ID, chame de 'ID Fiado'. " .
                    "MUITO IMPORTANTE: Sempre que você falar sobre qualquer pedido (seja atual ou compras passadas), informe o número do pedido na sua resposta. " .
                    "Se for criar um pedido e o usuário não der os preços, busque no CONTEXTO e inclua os IDs e precos corretos. ";
            } else {
                $customerPhone = $context['customer_phone'] ?? '';
                $fiadoContext = "";
                if (!empty($customerPhone)) {
                    $clienteFiado = $this->db->fetch("SELECT id, saldo_devedor FROM clientes_fiado WHERE telefone = ? AND tenant_id = ?", [$customerPhone, $tenantId]);
                    if ($clienteFiado && $clienteFiado['saldo_devedor'] > 0) {
                        $fiadoContext = "ATENÇÃO: Este cliente possui uma dívida no Fiado de R$ " . number_format($clienteFiado['saldo_devedor'], 2, ',', '.') . ". " .
                            "Se ele perguntar sobre dívidas ou quiser pagar, informe esse valor educadamente. " .
                            "Se ele pedir a fatura, você pode gerar usando a ação: {\"type\":\"action\",\"action\":\"solicitar_fatura_fiado\",\"data\":{\"cliente_id\": " . $clienteFiado['id'] . "}}. Ele é o ID: " . $clienteFiado['id'] . "\n\n";
                    }
                }
                $systemPrompt .= "ATENÇÃO DE CONTEXTO: O usuário conversando agora é um CLIENTE do restaurante. Seja muito educado(a), prestativo(a) e simpático(a). " . 
                                 "Seu objetivo é tirar dúvidas do cardápio, informar status de pedidos e ajudar no que for preciso.\n\n" . $fiadoContext .
                    "Você pode receber pedidos do cliente. Para lançar um pedido, responda EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"create_order\",\"data\":{...}}. " .
                    "Caso contrário, o formato da sua resposta deve ser apenas texto limpo para o WhatsApp.";
            }
            
            $systemPrompt .= "\n\nCONTEXTO ATUAL DO RESTAURANTE:\n" . $systemContext;

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message]
            ];

            $maxIterations = 4;
            $iteration = 0;
            $finalResponseText = 'Desculpe, não entendi.';

            while ($iteration < $maxIterations) {
                $iteration++;

                // Use gpt-4o-mini for better JSON/action extraction
                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                $data = [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.7
                ];
                
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ]);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $result = json_decode($response, true);
                $aiText = $result['choices'][0]['message']['content'] ?? '';
                if (empty($aiText)) break;
                
                // Parse response to check for actions
                $parsedAction = null;
                $jsonData = json_decode($aiText, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $parsedAction = $jsonData;
                } else {
                    $jsonStart = strpos($aiText, '{');
                    $jsonEnd = strrpos($aiText, '}');
                    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                        $jsonString = substr($aiText, $jsonStart, $jsonEnd - $jsonStart + 1);
                        $extractedJson = json_decode($jsonString, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($extractedJson)) {
                            $parsedAction = $extractedJson;
                        }
                    }
                    
                    if (!$parsedAction && preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $aiText, $matches)) {
                        $extractedJson = json_decode($matches[1], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($extractedJson)) {
                            $parsedAction = $extractedJson;
                        }
                    }
                }
                
                if ($parsedAction && isset($parsedAction['type']) && $parsedAction['type'] === 'action') {
                    $this->tenantId = $tenantId;
                    $this->filialId = $filialId;
                    
                    // SEGURANÇA: Validar se cliente está tentando executar ação de administrador
                    $adminActions = [
                        'create_product', 'update_product', 'delete_product', 
                        'create_category', 'create_ingredient', 'listar_pendencias_fiado', 
                        'configurar_cobranca_fiado', 'baixar_pagamento_fiado',
                        'ver_estoque', 'atualizar_estoque'
                    ];
                    
                    $requestedAction = $parsedAction['action'] ?? $parsedAction['type'];
                    
                    if (!$isAdmin && in_array($requestedAction, $adminActions)) {
                        $messages[] = [
                            'role' => 'user', 
                            'content' => "Erro de Segurança: Você tentou executar uma ação restrita ({$requestedAction}) em uma conversa com um CLIENTE. Clientes não podem gerenciar o sistema. Responda educadamente dizendo que você não pode fazer isso."
                        ];
                        continue;
                    }

                    // Registra a ação tomada pela IA
                    $messages[] = ['role' => 'assistant', 'content' => $aiText];
                    
                    // Formata a ação para o executeOperation
                    $opToExecute = [
                        'type' => $parsedAction['action'] ?? $parsedAction['type'],
                        'data' => $parsedAction['data'] ?? []
                    ];
                    
                    $execResult = $this->executeOperation($opToExecute);
                    
                    // Retorna o resultado para a IA poder pensar no próximo passo
                    $resultStr = json_encode($execResult);
                    $messages[] = [
                        'role' => 'user', 
                        'content' => "O sistema executou a ação e retornou:\n{$resultStr}\n\nAnalise o resultado. Se a tarefa foi totalmente concluída e o objetivo alcançado, responda ao usuário final apenas com TEXTO natural comunicando o sucesso/conclusão. Se você ainda precisar de dados do usuário (ex: forma de pagamento), responda perguntando em TEXTO. Se precisar de outra ação do sistema, gere outro JSON de ação."
                    ];
                    
                    continue; // Loop again para nova decisão
                } else {
                    // Não é uma ação, é uma resposta final para o usuário
                    $finalResponseText = $aiText;
                    if ($parsedAction && isset($parsedAction['type']) && $parsedAction['type'] === 'response') {
                        $finalResponseText = $parsedAction['message'] ?? $aiText;
                    }
                    break;
                }
            }
            
            return ['success' => true, 'response' => ['message' => $finalResponseText]];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process file attachments (images, PDFs, spreadsheets)
     */
    private function processAttachments($attachments)
    {
        $processedData = [];
        
        foreach ($attachments as $attachment) {
            $filePath = $attachment['path'] ?? '';
            $fileType = $attachment['type'] ?? '';
            $fileName = $attachment['name'] ?? '';
            
            if (!file_exists($filePath)) {
                continue;
            }
            
            try {
                switch ($fileType) {
                    case 'image':
                        $processedData[] = $this->processImage($filePath, $fileName);
                        break;
                    case 'pdf':
                        $processedData[] = $this->processPDF($filePath, $fileName);
                        break;
                    case 'spreadsheet':
                        $processedData[] = $this->processSpreadsheet($filePath, $fileName);
                        break;
                    default:
                        $processedData[] = [
                            'type' => 'unknown',
                            'name' => $fileName,
                            'message' => 'Tipo de arquivo não suportado para processamento automático'
                        ];
                }
            } catch (Exception $e) {
                $processedData[] = [
                    'type' => 'error',
                    'name' => $fileName,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $processedData;
    }

    /**
     * Process image using OpenAI Vision API
     */
    private function processImage($filePath, $fileName)
    {
        $base64 = base64_encode(file_get_contents($filePath));
        $mimeType = mime_content_type($filePath);
        
        $response = $this->callOpenAIVision([
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Analyze this restaurant product image. Extract: product name, description, ingredients (if visible), estimated price range, category (sandwich, drink, dessert, etc). Respond in Portuguese.'
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$base64}"
                        ]
                    ]
                ]
            ]
        ]);
        
        return [
            'type' => 'image',
            'name' => $fileName,
            'analysis' => $response['choices'][0]['message']['content'] ?? 'Análise não disponível'
        ];
    }

    /**
     * Transcribe audio using OpenAI Whisper API
     */
    public function transcribeAudio($filePath)
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Arquivo de áudio não encontrado.'];
        }
        
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        $cFile = new \CURLFile($filePath);
        
        $postData = [
            'file' => $cFile,
            'model' => 'whisper-1',
            'language' => 'pt'
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['text'])) {
            return ['success' => true, 'text' => $result['text']];
        }
        
        return [
            'success' => false,
            'message' => 'Erro na transcrição: ' . ($result['error']['message'] ?? 'Desconhecido')
        ];
    }

    /**
     * Process PDF document
     */
    private function processPDF($filePath, $fileName)
    {
        // For now, return basic info - in production, use a PDF parsing library
        return [
            'type' => 'pdf',
            'name' => $fileName,
            'message' => 'Arquivo PDF detectado. Para processamento completo, seria necessário uma biblioteca de parsing de PDF.'
        ];
    }

    /**
     * Process spreadsheet (CSV, Excel)
     */
    private function processSpreadsheet($filePath, $fileName)
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $this->processCSV($filePath, $fileName);
        } else {
            return [
                'type' => 'spreadsheet',
                'name' => $fileName,
                'message' => 'Arquivo de planilha detectado. Para processamento completo, seria necessário uma biblioteca de parsing de Excel.'
            ];
        }
    }

    /**
     * Process CSV file
     */
    private function processCSV($filePath, $fileName)
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle !== false) {
            $headers = fgetcsv($handle);
            $rowCount = 0;
            
            while (($row = fgetcsv($handle)) !== false && $rowCount < 100) { // Limit to 100 rows
                $data[] = array_combine($headers, $row);
                $rowCount++;
            }
            
            fclose($handle);
        }
        
        return [
            'type' => 'csv',
            'name' => $fileName,
            'data' => $data,
            'row_count' => count($data)
        ];
    }

    /**
     * Call OpenAI Chat API
     */
    private function callOpenAI($messages)
    {
        $data = [
            'model' => 'gpt-4',
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 500
        ];
        
        return $this->makeOpenAIRequest('https://api.openai.com/v1/chat/completions', $data);
    }

    /**
     * Call OpenAI Vision API
     */
    private function callOpenAIVision($messages)
    {
        $data = [
            'model' => 'gpt-4-vision-preview',
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 1000
        ];
        
        return $this->makeOpenAIRequest('https://api.openai.com/v1/chat/completions', $data);
    }

    /**
     * Make HTTP request to OpenAI API
     */
    private function makeOpenAIRequest($url, $data)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('OpenAI API Error (HTTP ' . $httpCode . '): ' . $response);
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI API');
        }
        
        return $decoded;
    }

    /**
     * Parse AI response and determine action
     */
    private function parseAIResponse($response, $attachmentData = [])
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Try to parse as JSON first
        $jsonData = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            return $jsonData;
        }
        
        // Try to extract JSON from text (e.g. if wrapped in markdown or conversational text)
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
            $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $jsonData = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                if (isset($jsonData['type']) && $jsonData['type'] === 'action') {
                    return $jsonData;
                }
            }
        }
        
        // Se ainda não achou JSON válido com type=action, procura blocos markdown ```json
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                return $jsonData;
            }
        }
        
        // If not JSON, try to extract action from text
        return $this->extractActionFromText($content, $attachmentData);
    }

    /**
     * Extract action from text response
     */
    private function extractActionFromText($text, $attachmentData = [])
    {
        $text = strtolower($text);
        
        // Simple keyword detection
        if (strpos($text, 'criar') !== false || strpos($text, 'adicionar') !== false || strpos($text, 'novo') !== false) {
            return [
                'type' => 'confirmation',
                'message' => 'Detectei que você quer criar algo. Por favor, seja mais específico sobre o que deseja criar.',
                'confirm' => false
            ];
        }
        
        if (strpos($text, 'editar') !== false || strpos($text, 'alterar') !== false || strpos($text, 'modificar') !== false) {
            return [
                'type' => 'confirmation',
                'message' => 'Detectei que você quer editar algo. Por favor, especifique o que e como deseja modificar.',
                'confirm' => false
            ];
        }
        
        if (strpos($text, 'excluir') !== false || strpos($text, 'remover') !== false || strpos($text, 'deletar') !== false) {
            return [
                'type' => 'confirmation',
                'message' => 'Detectei que você quer excluir algo. Por favor, confirme o que deseja remover.',
                'confirm' => true
            ];
        }
        
        return [
            'type' => 'info',
            'message' => $text
        ];
    }

    /**
     * Get products summary for context
     */
    private function getProductsSummary($tenantId, $filialId)
    {
        $products = $this->db->fetchAll(
            "SELECT p.id, p.nome, p.preco_normal, c.nome as categoria 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             ORDER BY p.nome 
             LIMIT 20",
            [$tenantId, $filialId]
        );
        
        return $products;
    }

    /**
     * Get categories summary for context
     */
    private function getCategoriesSummary($tenantId, $filialId)
    {
        return $this->db->fetchAll(
            "SELECT id, nome FROM categorias 
             WHERE tenant_id = ? AND filial_id = ? 
             ORDER BY nome",
            [$tenantId, $filialId]
        );
    }

    /**
     * Get ingredients summary for context
     */
    private function getIngredientsSummary($tenantId, $filialId)
    {
        return $this->db->fetchAll(
            "SELECT id, nome, tipo, preco_adicional 
             FROM ingredientes 
             WHERE tenant_id = ? AND filial_id = ? 
             ORDER BY tipo, nome",
            [$tenantId, $filialId]
        );
    }

    /**
     * Get active orders summary for context
     */
    private function getActiveOrdersSummary($tenantId, $filialId)
    {
        return $this->db->fetchAll(
            "SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.data, p.hora_pedido 
             FROM pedido p 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.status NOT IN ('Finalizado', 'Cancelado') 
             ORDER BY p.data DESC, p.hora_pedido DESC 
             LIMIT 10",
            [$tenantId, $filialId]
        );
    }

    /**
     * Get tables summary for context
     */
    private function getTablesSummary($tenantId, $filialId)
    {
        return $this->db->fetchAll(
            "SELECT id_mesa, status FROM mesas 
             WHERE tenant_id = ? AND filial_id = ? 
             ORDER BY id_mesa",
            [$tenantId, $filialId]
        );
    }

    /**
     * Execute database operation based on AI response
     */
    public function executeOperation($operation)
    {
        try {
            switch ($operation['type']) {
                case 'create_product':
                    return $this->createProduct($operation['data']);
                case 'update_product':
                    return $this->updateProduct($operation['data']);
                case 'delete_product':
                    return $this->deleteProduct($operation['data']);
                case 'create_category':
                    return $this->createCategory($operation['data']);
                case 'create_ingredient':
                    return $this->createIngredient($operation['data']);
                case 'create_order':
                    return $this->createOrder($operation['data']);
                case 'add_item_to_order':
                    return $this->addItemToOrder($operation['data']);
                case 'remove_item_from_order':
                    return $this->removeItemFromOrder($operation['data']);
                case 'listar_pendencias_fiado':
                    return $this->listarPendenciasFiado($operation['data'] ?? []);
                case 'listar_compras_cliente':
                    return $this->listarComprasCliente($operation['data'] ?? []);
                case 'configurar_cobranca_fiado':
                    return $this->configurarCobrancaFiado($operation['data']);
                case 'gerar_fatura_fiado':
                case 'solicitar_fatura_fiado':
                    return $this->gerarFaturaFiado($operation['data']);
                case 'baixar_pagamento_fiado':
                    return $this->baixarPagamentoFiado($operation['data']);
                case 'ver_estoque':
                    return $this->verEstoque($operation['data']);
                case 'atualizar_estoque':
                    return $this->atualizarEstoque($operation['data']);
                default:
                    throw new Exception('Operação não suportada: ' . $operation['type']);
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
     * Create new product
     */
    private function createProduct($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $productId = $this->db->insert('produtos', [
            'nome' => $data['nome'],
            'categoria_id' => $data['categoria_id'],
            'preco_normal' => $data['preco_normal'],
            'preco_mini' => $data['preco_mini'] ?? null,
            'descricao' => $data['descricao'] ?? '',
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        return [
            'success' => true,
            'message' => 'Produto criado com sucesso!',
            'product_id' => $productId
        ];
    }

    /**
     * Update existing product
     */
    private function updateProduct($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = $data['nome'];
        if (isset($data['preco_normal'])) $updateData['preco_normal'] = $data['preco_normal'];
        if (isset($data['preco_mini'])) $updateData['preco_mini'] = $data['preco_mini'];
        if (isset($data['descricao'])) $updateData['descricao'] = $data['descricao'];
        
        $this->db->update(
            'produtos',
            $updateData,
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return [
            'success' => true,
            'message' => 'Produto atualizado com sucesso!'
        ];
    }

    /**
     * Delete product
     */
    private function deleteProduct($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $this->db->delete(
            'produtos',
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return [
            'success' => true,
            'message' => 'Produto excluído com sucesso!'
        ];
    }

    /**
     * Create new category
     */
    private function createCategory($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $categoryId = $this->db->insert('categorias', [
            'nome' => $data['nome'],
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        return [
            'success' => true,
            'message' => 'Categoria criada com sucesso!',
            'category_id' => $categoryId
        ];
    }

    /**
     * Create new ingredient
     */
    private function createIngredient($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $ingredientId = $this->db->insert('ingredientes', [
            'nome' => $data['nome'],
            'tipo' => $data['tipo'] ?? 'complemento',
            'preco_adicional' => $data['preco_adicional'] ?? 0,
            'disponivel' => $data['disponivel'] ?? true,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        return [
            'success' => true,
            'message' => 'Ingrediente criado com sucesso!',
            'ingredient_id' => $ingredientId
        ];
    }

    /**
     * Create new order
     */
    private function createOrder($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $usuarioId = $this->session->getUserId() ?? 1;
        
        $valorTotal = 0;
        if (!empty($data['itens'])) {
            foreach ($data['itens'] as $item) {
                $valorTotal += (float)($item['preco'] ?? 0) * (int)($item['quantidade'] ?? 1);
            }
        } else {
            $valorTotal = $data['valor_total'] ?? 0;
        }
        
        $orderId = $this->db->insert('pedido', [
            'idmesa' => $data['mesa_id'] ?? '999',
            'cliente' => $data['cliente'] ?? 'Cliente IA',
            'delivery' => $data['delivery'] ?? false,
            'data' => date('Y-m-d'),
            'hora_pedido' => date('H:i:s'),
            'valor_total' => $valorTotal,
            'status' => 'Pendente',
            'observacao' => $data['observacao'] ?? 'Pedido criado via IA',
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        if (!empty($data['itens']) && $orderId) {
            foreach ($data['itens'] as $item) {
                $this->db->insert('pedido_itens', [
                    'pedido_id' => $orderId,
                    'produto_id' => $item['id'] ?? 1,
                    'quantidade' => $item['quantidade'] ?? 1,
                    'valor_unitario' => $item['preco'] ?? 0,
                    'valor_total' => ((float)($item['preco'] ?? 0)) * ((int)($item['quantidade'] ?? 1)),
                    'tamanho' => $item['tamanho'] ?? 'normal',
                    'observacao' => $item['observacao'] ?? '',
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
        }
        
        // Atualizar status da mesa
        if (($data['mesa_id'] ?? '999') !== '999') {
            $this->db->update('mesas', ['status' => '2'], 'id_mesa = ? AND tenant_id = ?', [$data['mesa_id'], $tenantId]);
        }
        
        return [
            'success' => true,
            'message' => 'Pedido criado com sucesso! ID: ' . $orderId,
            'order_id' => $orderId
        ];
    }
    
    private function addItemToOrder($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $pedidoId = $data['pedido_id'] ?? null;
        if (!$pedidoId) return ['success' => false, 'message' => 'ID do pedido não fornecido.'];
        
        foreach ($data['itens'] as $item) {
            $this->db->insert('pedido_itens', [
                'pedido_id' => $pedidoId,
                'produto_id' => $item['id'] ?? 1,
                'quantidade' => $item['quantidade'] ?? 1,
                'valor_unitario' => $item['preco'] ?? 0,
                'valor_total' => ((float)($item['preco'] ?? 0)) * ((int)($item['quantidade'] ?? 1)),
                'tamanho' => $item['tamanho'] ?? 'normal',
                'observacao' => $item['observacao'] ?? '',
                'tenant_id' => $tenantId,
                'filial_id' => $this->filialId ?? 1
            ]);
        }
        
        // Recalcular total do pedido
        $this->db->query("UPDATE pedido SET valor_total = (SELECT SUM(valor_total) FROM pedido_itens WHERE pedido_id = ?) WHERE idpedido = ?", [$pedidoId, $pedidoId]);
        
        return ['success' => true, 'message' => 'Item adicionado ao pedido ' . $pedidoId];
    }
    
    private function removeItemFromOrder($data)
    {
        $itemId = $data['pedido_item_id'] ?? null;
        if (!$itemId) return ['success' => false, 'message' => 'ID do item não fornecido.'];
        
        // Obter pedido_id
        $item = $this->db->fetch("SELECT pedido_id FROM pedido_itens WHERE id = ?", [$itemId]);
        
        if ($item) {
            $this->db->delete('pedido_itens', 'id = ?', [$itemId]);
            $this->db->query("UPDATE pedido SET valor_total = (SELECT COALESCE(SUM(valor_total), 0) FROM pedido_itens WHERE pedido_id = ?) WHERE idpedido = ?", [$item['pedido_id'], $item['pedido_id']]);
        }
        
        return ['success' => true, 'message' => 'Item removido.'];
    }
    
    private function listarPendenciasFiado($data = [])
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $nomeCliente = $data['nome_cliente'] ?? null;
        
        if ($nomeCliente) {
            $devedores = $this->db->fetchAll(
                "SELECT id, nome, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? AND saldo_devedor > 0 AND nome ILIKE ? 
                 ORDER BY nome ASC LIMIT 50", 
                [$tenantId, "%{$nomeCliente}%"]
            );
        } else {
            $devedores = $this->db->fetchAll(
                "SELECT id, nome, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? AND saldo_devedor > 0 
                 ORDER BY nome ASC LIMIT 50", 
                [$tenantId]
            );
        }
        
        if (empty($devedores)) {
            if ($nomeCliente) {
                return ['success' => true, 'message' => "Nenhuma pendência encontrada para o cliente '{$nomeCliente}'."];
            }
            return ['success' => true, 'message' => "Nenhum cliente está devendo no momento."];
        }
        
        $msg = "*Clientes com pendências (Fiado):*\n\n";
        foreach ($devedores as $d) {
            $cob = $d['cobranca_automatica'] ? "Ativa ({$d['cobranca_frequencia']})" : "Inativa";
            $msg .= "👤 *{$d['nome']}* (ID: {$d['id']})\n";
            $msg .= "💰 Dívida: R$ " . number_format($d['saldo_devedor'], 2, ',', '.') . "\n";
            $msg .= "🤖 Cobrança IA: {$cob}\n\n";
        }
        
        if (count($devedores) === 50) {
            $msg .= "\n⚠️ Exibindo apenas os 50 primeiros resultados. Peça para pesquisar por um nome específico se não encontrar na lista.";
        }
        
        return ['success' => true, 'message' => $msg];
    }
    
    private function listarComprasCliente($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $nomeCliente = $data['nome_cliente'] ?? null;
        
        if (!$nomeCliente) {
            return ['success' => false, 'message' => "Informe o nome do cliente."];
        }
        
        $cliente = $this->db->fetch("SELECT id, nome FROM clientes_fiado WHERE nome ILIKE ? AND tenant_id = ?", ["%{$nomeCliente}%", $tenantId]);
        if (!$cliente) {
            return ['success' => true, 'message' => "Nenhum cliente fiado encontrado com esse nome."];
        }
        
        $cId = $cliente['id'];
        
        $itens = $this->db->fetchAll("
            SELECT p.idpedido, p.data, pr.nome as produto_nome, pi.quantidade, pi.valor_total
            FROM vendas_fiadas v
            JOIN pedido p ON v.pedido_id = p.idpedido
            JOIN pedido_itens pi ON p.idpedido = pi.pedido_id
            JOIN produtos pr ON pi.produto_id = pr.id
            WHERE v.cliente_id = ? AND v.status IN ('pendente', 'vencido')
            ORDER BY p.data DESC
        ", [$cId]);
        
        if (empty($itens)) {
            return ['success' => true, 'message' => "O cliente {$cliente['nome']} não possui consumações detalhadas atreladas a pedidos não pagos."];
        }
        
        $itensPorPedido = [];
        foreach ($itens as $i) {
            $itensPorPedido[$i['idpedido']]['data'] = $i['data'];
            $itensPorPedido[$i['idpedido']]['itens'][] = $i;
        }

        $msg = "Detalhes do consumo pendente de {$cliente['nome']}:\n";
        foreach ($itensPorPedido as $idPedido => $pedido) {
            $dataCompra = date('d/m/Y', strtotime($pedido['data']));
            $msg .= "- Pedido #{$idPedido} em {$dataCompra}:\n";
            foreach ($pedido['itens'] as $item) {
                $msg .= "  * {$item['quantidade']}x {$item['produto_nome']} (R$ " . number_format($item['valor_total'], 2, ',', '.') . ")\n";
            }
            
            // Buscar pagamentos e descontos atrelados ao pedido
            $pagamentos = $this->db->fetchAll("SELECT forma_pagamento, valor_pago FROM pagamentos_pedido WHERE pedido_id = ?", [$idPedido]);
            if (!empty($pagamentos)) {
                $msg .= "  * Divisão de pagamentos do pedido no caixa:\n";
                foreach ($pagamentos as $pag) {
                    $msg .= "    - " . $pag['forma_pagamento'] . ": R$ " . number_format($pag['valor_pago'], 2, ',', '.') . "\n";
                }
            }
        }
        
        $msg .= "\n[INSTRUÇÃO PARA A IA]: Acima está a lista de itens consumidos e como o pedido foi fechado no caixa (incluindo dinheiro, descontos e o valor que efetivamente foi para o FIADO). Use essas informações para explicar exatamente o que o cliente consumiu e como a conta foi dividida (ex: pagou X em dinheiro, teve Y de desconto, e sobrou Z para o fiado).";
        
        return ['success' => true, 'message' => $msg];
    }
    
    private function configurarCobrancaFiado($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $cId = $data['cliente_id'] ?? null;
        $freq = $data['frequencia'] ?? 'semanal';
        $ativo = isset($data['ativo']) ? (bool)$data['ativo'] : true;
        
        if (!$cId) return ['success' => false, 'message' => "Por favor, informe o ID do cliente."];
        
        $this->db->update('clientes_fiado', [
            'cobranca_automatica' => $ativo ? 'true' : 'false',
            'cobranca_frequencia' => $freq
        ], 'id = ? AND tenant_id = ?', [$cId, $tenantId]);
        
        $status = $ativo ? "ativada" : "desativada";
        return ['success' => true, 'message' => "Cobrança automática {$status} para o cliente ID {$cId} com frequência {$freq}."];
    }
    
    private function gerarFaturaFiado($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $cId = $data['cliente_id'] ?? null;
        if (!$cId) return ['success' => false, 'message' => "Informe o ID do cliente."];
        
        $cliente = $this->db->fetch("SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ?", [$cId, $tenantId]);
        if (!$cliente) return ['success' => false, 'message' => "Cliente não encontrado."];
        
        $pedidos = $this->db->fetchAll("SELECT * FROM vendas_fiadas WHERE cliente_id = ? AND status IN ('pendente', 'vencido') ORDER BY data_vencimento ASC", [$cId]);
        
        $msg = "📄 *Fatura de {$cliente['nome']}*\n";
        $msg .= "Total em aberto: R$ " . number_format($cliente['saldo_devedor'], 2, ',', '.') . "\n\n";
        $msg .= "*Detalhamento:*\n";
        
        foreach ($pedidos as $p) {
            $pendente = $p['valor_total'] - $p['valor_pago'];
            $msg .= "- Pedido #{$p['pedido_id']} (" . date('d/m/Y', strtotime($p['created_at'])) . "): R$ " . number_format($pendente, 2, ',', '.') . "\n";
        }
        
        return ['success' => true, 'message' => $msg];
    }
    
    private function baixarPagamentoFiado($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $cId = $data['cliente_id'] ?? null;
        
        $pagamentos = $data['pagamentos'] ?? [];
        if (empty($pagamentos) && isset($data['valor_pago'])) {
            $pagamentos = [['valor' => (float)$data['valor_pago'], 'forma_pagamento' => 'dinheiro/pix (whatsapp)']];
        }
        
        $descontoValor = (float)($data['desconto_valor'] ?? 0);
        $destino = $data['destino'] ?? 'ambos'; // 'fiado', 'pedido' ou 'ambos'
        
        $valorTotalPago = 0;
        foreach ($pagamentos as $pag) {
            $valorTotalPago += (float)($pag['valor'] ?? 0);
        }
        
        if (!$cId || ($valorTotalPago <= 0 && $descontoValor <= 0)) {
            return ['success' => false, 'message' => "Informe o ID do cliente e os valores pagos ou desconto."];
        }
        
        $cliente = $this->db->fetch("SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ?", [$cId, $tenantId]);
        if (!$cliente) return ['success' => false, 'message' => "Cliente não encontrado."];
        
        $usuarioGlobalId = $cliente['usuario_global_id'] ?? $cId;
        
        // Constrói a query com base no destino escolhido
        $queryFiado = "SELECT id, valor_total, valor_pago, (valor_total - valor_pago) as saldo_devedor, data_vencimento as data_ordenacao, 'fiado' as origem FROM vendas_fiadas WHERE cliente_id = ? AND tenant_id = ? AND status IN ('pendente', 'vencido')";
        $queryPedido = "SELECT idpedido as id, valor_total, valor_pago, saldo_devedor, created_at as data_ordenacao, 'pedido' as origem FROM pedido WHERE usuario_global_id = ? AND tenant_id = ? AND status_pagamento IN ('pendente', 'parcial') AND status NOT IN ('Cancelado')";
        
        $sqlPendentes = "";
        $queryParams = [];
        
        if ($destino === 'fiado') {
            $sqlPendentes = $queryFiado . " ORDER BY data_ordenacao ASC";
            $queryParams = [$cId, $tenantId];
        } elseif ($destino === 'pedido') {
            $sqlPendentes = $queryPedido . " ORDER BY data_ordenacao ASC";
            $queryParams = [$usuarioGlobalId, $tenantId];
        } else {
            $sqlPendentes = "($queryFiado) UNION ALL ($queryPedido) ORDER BY data_ordenacao ASC";
            $queryParams = [$cId, $tenantId, $usuarioGlobalId, $tenantId];
        }
        
        $pedidos = $this->db->fetchAll($sqlPendentes, $queryParams);
        
        // Adiciona o desconto como um pagamento virtual
        $listaAmortizacao = [];
        if ($descontoValor > 0) {
            $listaAmortizacao[] = ['valor' => $descontoValor, 'forma_pagamento' => 'desconto', 'is_desconto' => true];
        }
        foreach ($pagamentos as $pag) {
            if ((float)$pag['valor'] > 0) {
                $listaAmortizacao[] = ['valor' => (float)$pag['valor'], 'forma_pagamento' => $pag['forma_pagamento'], 'is_desconto' => false];
            }
        }
        
        $this->db->beginTransaction();
        try {
            foreach ($listaAmortizacao as $pgto) {
                $valorRestante = $pgto['valor'];
                
                foreach ($pedidos as &$p) { // Passa por referência para atualizar o saldo_devedor em tempo real na memória
                    if ($valorRestante <= 0) break;
                    if ((float)$p['saldo_devedor'] <= 0) continue;
                    
                    $saldoAtual = (float)$p['saldo_devedor'];
                    $valorPagarNestaVenda = min($valorRestante, $saldoAtual);
                    
                    if ($p['origem'] === 'pedido') {
                        $this->db->insert('pagamentos_pedido', [
                            'pedido_id' => $p['id'],
                            'valor_pago' => $valorPagarNestaVenda,
                            'forma_pagamento' => $pgto['forma_pagamento'],
                            'descricao' => $pgto['is_desconto'] ? 'Desconto (IA)' : 'Pagamento Fiado/Comanda (IA)',
                            'usuario_id' => $this->session->getUserId() ?? 1,
                            'usuario_global_id' => $usuarioGlobalId,
                            'tenant_id' => $tenantId,
                            'filial_id' => $filialId
                        ]);
                        
                        $novoValorPago = (float)$p['valor_pago'] + $valorPagarNestaVenda;
                        $novoSaldoDevedor = (float)$p['saldo_devedor'] - $valorPagarNestaVenda;
                        $novoStatus = $novoSaldoDevedor <= 0.01 ? 'concluido' : 'parcial';
                        
                        $this->db->update('pedido', [
                            'valor_pago' => $novoValorPago,
                            'saldo_devedor' => $novoSaldoDevedor,
                            'status_pagamento' => $novoStatus
                        ], 'idpedido = ?', [$p['id']]);
                        
                        $p['valor_pago'] = $novoValorPago;
                        $p['saldo_devedor'] = $novoSaldoDevedor;
                    } else {
                        $this->db->insert('pagamentos_fiado', [
                            'venda_fiada_id' => $p['id'],
                            'valor_pago' => $valorPagarNestaVenda,
                            'forma_pagamento' => $pgto['forma_pagamento'],
                            'observacoes' => $pgto['is_desconto'] ? 'Desconto (IA)' : 'Pagamento Fiado (IA)',
                            'tenant_id' => $tenantId,
                            'filial_id' => $filialId
                        ]);
                        
                        $novoValorPago = (float)$p['valor_pago'] + $valorPagarNestaVenda;
                        $novoSaldoDevedor = (float)$p['valor_total'] - $novoValorPago;
                        $novoStatus = $novoSaldoDevedor <= 0.01 ? 'pago' : 'pendente';
                        
                        $this->db->update('vendas_fiadas', [
                            'valor_pago' => $novoValorPago,
                            'status' => $novoStatus
                        ], 'id = ?', [$p['id']]);
                        
                        $p['valor_pago'] = $novoValorPago;
                        $p['saldo_devedor'] = $novoSaldoDevedor;
                    }
                    
                    if (!$pgto['is_desconto']) {
                        $this->db->insert('movimentacoes_financeiras', [
                            'tenant_id' => $tenantId,
                            'filial_id' => $filialId,
                            'tipo' => 'entrada',
                            'categoria_id' => 2,
                            'descricao' => "Pagamento fiado lote (IA) - Venda ID: {$p['id']} - {$pgto['forma_pagamento']}",
                            'valor' => $valorPagarNestaVenda,
                            'referencia_id' => $p['id']
                        ]);
                    }
                    
                    $valorRestante -= $valorPagarNestaVenda;
                }
            }
            
            // Só subtraímos do clientes_fiado o que foi pago ou descontado DENTRO das vendas_fiadas?
            // O saldo_devedor na tabela clientes_fiado deve refletir o total atualizado das vendas_fiadas
            // O jeito mais seguro é recalcular o saldo_devedor.
            $saldoFiadoAtualizado = $this->db->fetch("SELECT SUM(valor_total - valor_pago) as total FROM vendas_fiadas WHERE cliente_id = ? AND tenant_id = ? AND status IN ('pendente', 'vencido')", [$cId, $tenantId]);
            $novoSaldo = max(0, (float)($saldoFiadoAtualizado['total'] ?? 0));
            $this->db->update('clientes_fiado', ['saldo_devedor' => $novoSaldo], 'id = ?', [$cId]);
            
            $this->db->commit();
            return ['success' => true, 'message' => "Transação de R$ " . number_format($valorTotalPago, 2, ',', '.') . " registrada com sucesso. Desconto aplicado: R$ " . number_format($descontoValor, 2, ',', '.') . ". Novo saldo do fiado (não inclui comandas soltas): R$ " . number_format($novoSaldo, 2, ',', '.')];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => "Erro ao registrar pagamento: " . $e->getMessage()];
        }
    }

    private function verEstoque($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $nome = $data['produto_nome'] ?? '';
        
        if (empty($nome)) return ['success' => false, 'message' => 'Informe o nome do produto.'];
        
        $produtos = $this->db->fetchAll("SELECT id, nome, estoque_atual FROM produtos WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? LIMIT 10", [$tenantId, $filialId, "%{$nome}%"]);
        
        if (empty($produtos)) return ['success' => true, 'message' => "Nenhum produto encontrado com o nome '{$nome}'."];
        
        $msg = "*Estoque atual:*\n";
        foreach ($produtos as $p) {
            $estoque = $p['estoque_atual'] ?? 0;
            $msg .= "- {$p['nome']}: {$estoque} unidades\n";
        }
        
        return ['success' => true, 'message' => $msg];
    }

    private function atualizarEstoque($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $nome = $data['produto_nome'] ?? '';
        $quantidade = $data['quantidade'] ?? null;
        $operacao = $data['operacao'] ?? 'adicionar';
        
        if (empty($nome) || $quantidade === null) return ['success' => false, 'message' => 'Informe o nome do produto e a quantidade.'];
        
        $produtos = $this->db->fetchAll("SELECT id, nome, estoque_atual FROM produtos WHERE tenant_id = ? AND filial_id = ? AND nome ILIKE ? ORDER BY length(nome) ASC LIMIT 1", [$tenantId, $filialId, "%{$nome}%"]);
        
        if (empty($produtos)) return ['success' => false, 'message' => "Produto '{$nome}' não encontrado no sistema."];
        
        $p = $produtos[0];
        $estoqueAtual = floatval($p['estoque_atual'] ?? 0);
        $quantidade = floatval($quantidade);
        
        if ($operacao === 'adicionar') {
            $novoEstoque = $estoqueAtual + $quantidade;
        } elseif ($operacao === 'remover') {
            $novoEstoque = $estoqueAtual - $quantidade;
        } else {
            $novoEstoque = $quantidade;
        }
        
        $this->db->update('produtos', ['estoque_atual' => $novoEstoque], 'id = ? AND tenant_id = ? AND filial_id = ?', [$p['id'], $tenantId, $filialId]);
        
        return ['success' => true, 'message' => "O estoque de *{$p['nome']}* foi atualizado com sucesso! Novo saldo em estoque: {$novoEstoque} unidades."];
    }
}
