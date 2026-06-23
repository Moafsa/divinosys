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
                "Para ações de fiado, as ações são: \n" .
                "- listar_pendencias_fiado (data: {\"nome_cliente\": \"opcional, nome do cliente para buscar especifico\"})\n" .
                "- configurar_cobranca_fiado (data: {\"cliente_id\": ID, \"frequencia\": \"diaria\"|\"semanal\"|\"mensal\", \"ativo\": true|false})\n" .
                "- gerar_fatura_fiado (data: {\"cliente_id\": ID})\n" .
                "- baixar_pagamento_fiado (data: {\"cliente_id\": ID, \"valor_pago\": 50.00})\n" .
                "Para executar UMA DESSAS AÇÕES, responda EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"nome_da_acao\",\"data\":{...}}. " .
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
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ];
            
            // Add attachment data if available
            if (!empty($attachmentData)) {
                $messages[1]['content'] .= "\n\nDados dos anexos:\n" . json_encode($attachmentData, JSON_PRETTY_PRINT);
            }
            
            // Call OpenAI API
            $response = $this->callOpenAI($messages);
            
            // Parse response and determine action
            $action = $this->parseAIResponse($response, $attachmentData);
            
            // If it's an action, we should execute it right away to mimic WhatsApp behavior
            if (isset($action['type']) && $action['type'] === 'action') {
                if (in_array($action['action'], [
                    'listar_pendencias_fiado', 'configurar_cobranca_fiado', 
                    'gerar_fatura_fiado', 'baixar_pagamento_fiado', 
                    'create_order', 'add_item_to_order', 'remove_item_from_order',
                    'solicitar_fatura_fiado'
                ])) {
                    $opResult = $this->executeOperation($action);
                    return [
                        'type' => 'response',
                        'message' => $opResult['message'] ?? 'Ação executada com sucesso.'
                    ];
                } else {
                    $opToExecute = [
                        'type' => $action['action'] ?? $action['type'],
                        'data' => $action['data'] ?? []
                    ];
                    $execResult = $this->executeOperation($opToExecute);
                    return [
                        'type' => 'response',
                        'message' => $execResult['message'] ?? "Ação concluída com sucesso!"
                    ];
                }
            }
            
            return $action;
            
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
        return "Assistente IA para restaurante (IAm). Operações disponíveis: criar_produto, listar_produtos, criar_ingrediente, listar_ingredientes, criar_categoria, listar_categorias, listar_pedidos, listar_pendencias_fiado, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado, create_order, add_item_to_order, remove_item_from_order. Responda em português. Para confirmação: {\"type\":\"confirmation\",\"message\":\"...\",\"action\":\"acao\",\"confirm\":true}. Para respostas com ações: {\"type\":\"action\",\"action\":\"nome\",\"data\":{...}}.";
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
                    "Para ações de fiado, as ações são: \n" .
                    "- listar_pendencias_fiado (data: {\"nome_cliente\": \"opcional, nome do cliente\"})\n" .
                    "- configurar_cobranca_fiado (data: {\"cliente_id\": ID, \"frequencia\": \"diaria\"|\"semanal\"|\"mensal\", \"ativo\": true|false})\n" .
                    "- gerar_fatura_fiado (data: {\"cliente_id\": ID})\n" .
                    "- baixar_pagamento_fiado (data: {\"cliente_id\": ID, \"valor_pago\": 50.00})\n" .
                    "Para executar UMA DESSAS AÇÕES, responda EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"nome_da_acao\",\"data\":{...}}. " .
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
            $aiText = $result['choices'][0]['message']['content'] ?? 'Desculpe, não entendi.';
            
            // Parse response to check for actions
            $parsedAction = null;
            if (strpos(trim($aiText), '{') === 0) {
                $jsonData = json_decode($aiText, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $parsedAction = $jsonData;
                }
            }
            
            // Extra logic for actions missing in executeOperation natively
            if ($parsedAction && isset($parsedAction['type']) && $parsedAction['type'] === 'action') {
                $this->tenantId = $tenantId;
                $this->filialId = $filialId;
                
                if (in_array($parsedAction['action'], [
                    'listar_pendencias_fiado', 'configurar_cobranca_fiado', 
                    'gerar_fatura_fiado', 'baixar_pagamento_fiado', 
                    'create_order', 'add_item_to_order', 'remove_item_from_order',
                    'solicitar_fatura_fiado'
                ])) {
                    $opResult = $this->executeOperation($parsedAction);
                    return ['success' => true, 'response' => ['message' => $opResult['message'] ?? 'Ação executada com sucesso.']];
                } else {
                    // Try to execute general operation
                    $this->tenantId = $tenantId;
                    $this->filialId = $filialId;
                    
                    // executeOperation expects $operation['type'] to be the action name
                    $opToExecute = [
                        'type' => $parsedAction['action'] ?? $parsedAction['type'],
                        'data' => $parsedAction['data'] ?? []
                    ];
                    
                    $execResult = $this->executeOperation($opToExecute);
                    
                    if (isset($execResult['success']) && $execResult['success'] === false) {
                        return ['success' => true, 'response' => ['message' => "Houve um erro ao realizar a ação: " . $execResult['message']]];
                    }
                    return ['success' => true, 'response' => ['message' => $execResult['message'] ?? "Ação concluída com sucesso!"]];
                }
            } elseif ($parsedAction && isset($parsedAction['type']) && $parsedAction['type'] === 'response') {
                $aiText = $parsedAction['message'] ?? $aiText;
            }
            
            return ['success' => true, 'response' => ['message' => $aiText]];

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
                case 'configurar_cobranca_fiado':
                    return $this->configurarCobrancaFiado($operation['data']);
                case 'gerar_fatura_fiado':
                case 'solicitar_fatura_fiado':
                    return $this->gerarFaturaFiado($operation['data']);
                case 'baixar_pagamento_fiado':
                    return $this->baixarPagamentoFiado($operation['data']);
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
        $cId = $data['cliente_id'] ?? null;
        $valorPago = (float)($data['valor_pago'] ?? 0);
        
        if (!$cId || $valorPago <= 0) return ['success' => false, 'message' => "Informe o ID do cliente e o valor pago válido."];
        
        $cliente = $this->db->fetch("SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ?", [$cId, $tenantId]);
        if (!$cliente) return ['success' => false, 'message' => "Cliente não encontrado."];
        
        $pedidos = $this->db->fetchAll("SELECT * FROM vendas_fiadas WHERE cliente_id = ? AND status IN ('pendente', 'vencido') ORDER BY data_vencimento ASC", [$cId]);
        
        $valorRestante = $valorPago;
        $this->db->beginTransaction();
        try {
            foreach ($pedidos as $p) {
                if ($valorRestante <= 0) break;
                
                $valorPendentePedido = (float)$p['valor_total'] - (float)$p['valor_pago'];
                if ($valorRestante >= $valorPendentePedido) {
                    $this->db->update('vendas_fiadas', [
                        'valor_pago' => $p['valor_total'],
                        'status' => 'pago'
                    ], 'id = ?', [$p['id']]);
                    $valorRestante -= $valorPendentePedido;
                } else {
                    $this->db->update('vendas_fiadas', [
                        'valor_pago' => (float)$p['valor_pago'] + $valorRestante
                    ], 'id = ?', [$p['id']]);
                    $valorRestante = 0;
                }
            }
            
            $novoSaldo = max(0, (float)$cliente['saldo_devedor'] - $valorPago);
            $this->db->update('clientes_fiado', ['saldo_devedor' => $novoSaldo], 'id = ?', [$cId]);
            
            $vendaId = count($pedidos) > 0 ? $pedidos[0]['id'] : null;
            if ($vendaId) {
                $this->db->insert('pagamentos_fiado', [
                    'venda_fiada_id' => $vendaId,
                    'valor_pago' => $valorPago,
                    'forma_pagamento' => 'dinheiro/pix (whatsapp)',
                    'tenant_id' => $tenantId,
                    'filial_id' => $this->filialId ?? 1
                ]);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => "Pagamento de R$ " . number_format($valorPago, 2, ',', '.') . " registrado com sucesso! Novo saldo: R$ " . number_format($novoSaldo, 2, ',', '.')];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => "Erro ao registrar pagamento: " . $e->getMessage()];
        }
    }
}
