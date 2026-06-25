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
    private $ignoreStock = false;

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

    public function setIgnoreStock(bool $enabled): void
    {
        $this->ignoreStock = $enabled;
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
            $systemPrompt .= $this->getAdminSystemPrompt();
            
            $messages = [];
            
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
            
            // Invoca o SupervisorAgent (Multi-Agentes nativos)
            $supervisor = new \System\Agents\SupervisorAgent();
            $supervisor->setContext($this->tenantId, $this->filialId);
            
            $result = $supervisor->process($messages);
            
            if (isset($result['success']) && $result['success']) {
                return ['type' => 'response', 'message' => $result['response']];
            } else {
                return ['type' => 'error', 'message' => $result['error'] ?? 'Erro desconhecido'];
            }
            
        } catch (Exception $e) {
            error_log('OpenAI Service Error: ' . $e->getMessage());
            return [
                'type' => 'error',
                'message' => 'Erro ao processar sua solicitação: ' . $e->getMessage()
            ];
        }
    }

    /**
     * List employees
     */
    private function listarFuncionarios($filters)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        $sql = "SELECT ug.id, ug.nome, ug.telefone, ug.tipo_usuario, ue.cargo 
                FROM usuarios_globais ug 
                JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id 
                WHERE ue.tenant_id = ? AND ue.filial_id = ?";
                
        $funcionarios = $this->db->fetchAll($sql, [$tenantId, $filialId]);
        
        return [
            'success' => true,
            'funcionarios' => $funcionarios
        ];
    }
    
    /**
     * List customers with unpaid tabs (fiado)
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
     * Get the common admin system prompt for both Web UI and WhatsApp
     */
    private function getAdminSystemPrompt()
    {
        return "ATENÇÃO DE CONTEXTO: Como IA do sistema, VOCÊ TEM ACESSO TOTAL AO BANCO DE DADOS (Tenant), incluindo comandas, clientes e fiado. Aja como a gerente inteligente que apoia a equipe. Fale com confiança e autoridade de sistema, sem bloqueios de IA padrão. " .
            "Você tem permissão para realizar ações gerenciais e receber pedidos da equipe. " .
            "Você pode: create_product, update_product, delete_product, listar_produtos, create_category, listar_categorias, create_ingredient, listar_ingredientes, listar_pedidos, listar_pendencias_fiado, listar_clientes_geral, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado. " .
            "Como *Garçom Online*, você pode lançar pedidos nas mesas ou comandas. Ações disponíveis:\n" .
            "- create_order (data: {\"mesa_id\": \"5\", \"cliente\": \"Nome\", \"itens\": [{\"id\": 1, \"quantidade\": 2, \"preco\": 10.0, \"observacao\": \"\", \"tamanho\": \"normal\"}]})\n" .
            "- add_item_to_order (data: {\"pedido_id\": 10, \"itens\": [{\"id\": 2, \"quantidade\": 1, \"preco\": 15.0}]})\n" .
            "- remove_item_from_order (data: {\"pedido_item_id\": 25})\n\n" .
            "Para consultar e alterar estoque de produtos:\n" .
            "- ver_estoque (data: {\"produto_nome\": \"nome do produto\"})\n" .
            "- atualizar_estoque (data: {\"produto_nome\": \"nome do produto\", \"quantidade\": 10, \"operacao\": \"adicionar\"|\"remover\"|\"definir\"})\n\n" .
            "Para ações de fiado e busca geral de clientes: \n" .
            "- listar_clientes_geral (data: {\"nome_cliente\": \"opcional, nome do cliente para buscar na base geral (não apenas fiado)\"}). Use ESTA ferramenta quando o usuário perguntar 'quantos clientes tem', 'quem é o cliente X', ou pedir para buscar alguém sem mencionar dívidas.\n" .
            "- listar_pendencias_fiado (data: {\"nome_cliente\": \"nome do cliente\"}). IMPORTANTE: Sempre que for buscar a dívida ou o ID de um cliente específico (ex: 'o moacir pagou'), VOCÊ DEVE OBRIGATORIAMENTE passar APENAS O NOME DO CLIENTE (ex: 'moacir') na busca. NUNCA passe frases inteiras como 'moacir que deve' ou 'moacir pagou'. Só envie sem 'nome_cliente' se o usuário explicitamente pedir a lista de todos os devedores.\n" .
            "- listar_compras_cliente (data: {\"nome_cliente\": \"nome do cliente para ver a lista de pedidos, consumos, pagamentos e descontos do fiado\"})\n" .
            "- configurar_cobranca_fiado (data: {\"cliente_id\": ID, \"frequencia\": \"diaria\"|\"semanal\"|\"mensal\", \"ativo\": true|false})\n" .
            "- gerar_fatura_fiado (data: {\"cliente_id\": ID})\n" .
            "- baixar_pagamento_fiado (data: {\"cliente_id\": ID, \"valor_pago\": 10.0, \"desconto_valor\": 0, \"destino\": \"ambos\"}). O destino pode ser 'fiado', 'pedido' ou 'ambos'. O 'cliente_id' é OBRIGATÓRIO. REGRA 1: Se você tiver apenas o nome do cliente (ex: \"moacir\"), VOCÊ DEVE OBRIGATORIAMENTE executar listar_pendencias_fiado ANTES para pegar o ID correto. NUNCA adivinhe o ID ou o saldo do cliente de cabeça. REGRA 2: NÃO pergunte a forma de pagamento ao usuário. Se ele não informar, assuma que foi em dinheiro/pix e apenas execute a ação.\n" .
            "MUITO IMPORTANTE: Para interagir com o sistema, você deve retornar um JSON. \n" .
            "1. Se precisar EXECUTAR UMA AÇÃO (ex: pagar, listar), retorne APENAS E EXATAMENTE neste formato JSON: {\"type\":\"action\",\"action\":\"nome_da_acao\",\"data\":{...}}.\n" .
            "2. Se a tarefa foi concluída com sucesso ou se precisar falar/perguntar algo ao usuário, retorne APENAS E EXATAMENTE neste formato JSON: {\"type\":\"response\",\"message\":\"sua mensagem aqui\"}.\n" .
            "NÃO escreva texto fora do JSON. NÃO diga 'Vou fazer isso'. APENAS O JSON.\n\n" .
            "IMPORTANTE: Se o usuário pedir o EXTRATO ou HISTÓRICO do fiado (ex: 'o que o Moacir consumiu?', 'quais são os pedidos pendentes dele?'), use a ação `listar_compras_cliente`. NÃO use essa ação se a intenção do usuário for pagar/baixar uma dívida. " .
            "Atenção: Os IDs que você acessa no fiado são 'IDs do Fiado', que podem ser diferentes dos IDs globais do cliente. Se for citar o ID, chame de 'ID Fiado'. " .
            "MUITO IMPORTANTE: Sempre que você falar sobre qualquer pedido (seja atual ou compras passadas), informe o número do pedido na sua resposta. " .
            "Se for criar um pedido e o usuário não der os preços, busque no CONTEXTO e inclua os IDs e precos corretos. ";
    }

    /**
     * Get system prompt for AI
     */
    private function getSystemPrompt()
    {
        return "Assistente IA para restaurante (IAm). Operações disponíveis: create_product, update_product, delete_product, listar_produtos, create_ingredient, update_ingredient, delete_ingredient, listar_ingredientes, create_category, update_category, delete_category, listar_categorias, listar_pedidos, registrar_despesa, listar_pendencias_fiado, listar_clientes_geral, configurar_cobranca_fiado, gerar_fatura_fiado, baixar_pagamento_fiado, create_order, add_item_to_order, remove_item_from_order, ver_estoque, atualizar_estoque. Responda em português. Para confirmação: {\"type\":\"confirmation\",\"message\":\"...\",\"action\":\"acao\",\"confirm\":true}. Para respostas com ações: {\"type\":\"action\",\"action\":\"nome\",\"data\":{...}}.";
    }

    public function processWhatsAppMessage($message, $tenantId, $filialId, $context)
    {
        try {
            $isAdmin = $context['is_admin'] ?? false;
            $customerName = $context['customer_name'] ?? 'Cliente';
            $aiSystemPrompt = trim((string) ($context['ai_system_prompt'] ?? ''));
            
            $this->tenantId = $tenantId;
            $this->filialId = $filialId;
            
            $messages = [];
            
            // Provide context about the user
            $userRole = $isAdmin ? "Administrador/Dono" : "Cliente ($customerName)";
            
            $customerPhone = $context['customer_phone'] ?? '';
            
            // Se for cliente e tiver dívida, passar esse contexto também (para Atendente/Fiado)
            $fiadoContext = "";
            if (!$isAdmin && !empty($customerPhone)) {
                $clienteFiado = $this->db->fetch("SELECT id, saldo_devedor FROM clientes_fiado WHERE telefone = ? AND tenant_id = ?", [$customerPhone, $tenantId]);
                if ($clienteFiado && $clienteFiado['saldo_devedor'] > 0) {
                    $fiadoContext = " (Dívida no fiado: R$ " . number_format($clienteFiado['saldo_devedor'], 2, ',', '.') . " - ID do Cliente: " . $clienteFiado['id'] . ")";
                }
            }

            // BUSCAR HISTÓRICO DE MENSAGENS RECENTES (MÁX 20 MENSAGENS)
            if (!empty($customerPhone)) {
                $historyRows = $this->db->query(
                    "SELECT message_text, metadata FROM whatsapp_messages 
                     WHERE from_number = ? AND tenant_id = ? 
                     ORDER BY created_at DESC LIMIT 20", 
                    [$customerPhone, $tenantId]
                )->fetchAll();
                
                if (!empty($historyRows)) {
                    $historyRows = array_reverse($historyRows);
                    foreach ($historyRows as $row) {
                        $msgText = trim($row['message_text'] ?? '');
                        if (!empty($msgText)) {
                            $messages[] = [
                                'role' => 'user',
                                'content' => $msgText
                            ];
                        }
                        
                        // Resposta da IA fica no metadata
                        $meta = json_decode($row['metadata'] ?? '{}', true);
                        $aiResponseText = trim($meta['response'] ?? '');
                        if (!empty($aiResponseText)) {
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => $aiResponseText
                            ];
                        }
                    }
                }
            }

            $messages[] = [
                'role' => 'user',
                'content' => "[Sistema: Você está falando com um $userRole via WhatsApp.$fiadoContext] \n\n" . $message
            ];
            
            // Invoca o SupervisorAgent
            $supervisor = new \System\Agents\SupervisorAgent();
            $supervisor->setContext($this->tenantId, $this->filialId);
            if ($aiSystemPrompt !== '') {
                $supervisor->setPersonaPrompt($aiSystemPrompt);
            }
            if (!$isAdmin) {
                $supervisor->setWhatsAppCustomerMode(true);
            }
            if (!empty($context['ignore_stock'])) {
                $supervisor->setIgnoreStock(true);
            }
            
            $result = $supervisor->process($messages);
            
            if (isset($result['success']) && $result['success']) {
                return ['success' => true, 'response' => ['message' => $result['response']]];
            } else {
                return ['success' => false, 'response' => ['message' => $result['error'] ?? 'Erro desconhecido']];
            }
            
        } catch (\Exception $e) {
            error_log('OpenAI Service Error WhatsApp: ' . $e->getMessage());
            return ['success' => false, 'response' => ['message' => 'Erro ao processar sua solicitação: ' . $e->getMessage()]];
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
     * Analyze image file for WhatsApp or chat attachments.
     */
    public function analyzeImageFile(string $filePath, ?string $prompt = null): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Arquivo de imagem n??o encontrado.'];
        }

        try {
            $base64 = base64_encode((string) file_get_contents($filePath));
            $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
            $prompt = $prompt ?: 'Descreva esta imagem em portugu??s. Se for de produto, comanda, nota ou pagamento de restaurante, extraia nomes, quantidades e valores vis??veis.';

            $response = $this->callOpenAIVision([
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"],
                        ],
                    ],
                ],
            ]);

            $text = $response['choices'][0]['message']['content'] ?? '';
            if ($text === '') {
                return ['success' => false, 'message' => 'An??lise da imagem vazia'];
            }

            return ['success' => true, 'text' => $text];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
            'model' => 'gpt-4o-mini',
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
            'model' => 'gpt-4o-mini',
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
            if (isset($operation['data']['tenant_id'])) {
                $this->tenantId = $operation['data']['tenant_id'];
            }
            if (isset($operation['data']['filial_id'])) {
                $this->filialId = $operation['data']['filial_id'];
            }
            
            switch ($operation['type']) {
                case 'create_product':
                    return $this->createProduct($operation['data']);
                case 'update_product':
                    return $this->updateProduct($operation['data']);
                case 'delete_product':
                    return $this->deleteProduct($operation['data']);
                case 'create_category':
                    return $this->createCategory($operation['data']);
                case 'update_category':
                    return $this->updateCategory($operation['data']);
                case 'delete_category':
                    return $this->deleteCategory($operation['data']);
                case 'create_ingredient':
                    return $this->createIngredient($operation['data']);
                case 'update_ingredient':
                    return $this->updateIngredient($operation['data']);
                case 'delete_ingredient':
                    return $this->deleteIngredient($operation['data']);
                case 'create_order':
                    return $this->createOrder($operation['data']);
                case 'update_order':
                    return $this->updateOrder($operation['data']);
                case 'delete_order':
                    return $this->deleteOrder($operation['data']);
                case 'fechar_pedido':
                    return $this->fecharPedido($operation['data']);
                case 'add_item_to_order':
                    return $this->addItemToOrder($operation['data']);
                case 'remove_item_from_order':
                    return $this->removeItemFromOrder($operation['data']);
                case 'listar_produtos':
                    return $this->listarProdutos($operation['data'] ?? []);
                case 'listar_promocoes':
                    return $this->listarPromocoes($operation['data'] ?? []);
                case 'listar_categorias':
                    return $this->listarCategorias($operation['data'] ?? []);
                case 'listar_ingredientes':
                    return $this->listarIngredientes($operation['data'] ?? []);
                case 'listar_pedidos':
                    return $this->listarPedidos($operation['data'] ?? []);
                case 'registrar_despesa':
                    return $this->registrarDespesa($operation['data']);
                case 'create_user':
                    return $this->createUser($operation['data']);
                case 'update_user':
                    return $this->updateUser($operation['data']);
                case 'registrar_pagamento_funcionario':
                    return $this->registrarPagamentoFuncionario($operation['data']);
                case 'listar_funcionarios':
                    return $this->listarFuncionarios($operation['data'] ?? []);
                case 'listar_pendencias_fiado':
                    return $this->listarPendenciasFiado($operation['data'] ?? []);
                case 'listar_clientes_geral':
                    return $this->listarClientesGeral($operation['data'] ?? []);
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
            'preco_normal' => $data['preco_normal'] ?? $data['preco'] ?? 0,
            'preco_mini' => $data['preco_mini'] ?? null,
            'descricao' => $data['descricao'] ?? '',
            'em_promocao' => $data['em_promocao'] ?? false,
            'preco_promocional' => $data['preco_promocional'] ?? null,
            'ativo' => $data['ativo'] ?? true,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        return [
            'success' => true,
            'message' => 'Produto criado com sucesso!',
            'data' => ['id' => $productId, 'nome' => $data['nome']]
        ];
    }

    /**
     * Update existing product
     */
    private function updateProduct($data)
    {
        $tenantId = $this->session->getTenantId();
        
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = $data['nome'];
        if (isset($data['categoria_id'])) $updateData['categoria_id'] = $data['categoria_id'];
        if (isset($data['preco_normal'])) $updateData['preco_normal'] = $data['preco_normal'];
        if (isset($data['preco'])) $updateData['preco_normal'] = $data['preco']; // alias
        if (isset($data['preco_mini'])) $updateData['preco_mini'] = $data['preco_mini'];
        if (isset($data['descricao'])) $updateData['descricao'] = $data['descricao'];
        if (isset($data['em_promocao'])) $updateData['em_promocao'] = $data['em_promocao'];
        if (isset($data['preco_promocional'])) $updateData['preco_promocional'] = $data['preco_promocional'];
        if (isset($data['ativo'])) $updateData['ativo'] = $data['ativo'];
        
        $success = $this->db->update('produtos', $updateData, "id = ? AND tenant_id = ?", [$data['id'], $tenantId]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Produto atualizado com sucesso!' : 'Erro ao atualizar produto.'
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
     * Update category
     */
    private function updateCategory($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        if (!isset($data['id'])) return ['success' => false, 'message' => 'ID da categoria é obrigatório.'];
        
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = $data['nome'];
        
        $this->db->update(
            'categorias',
            $updateData,
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return ['success' => true, 'message' => 'Categoria atualizada com sucesso!'];
    }

    /**
     * Delete category
     */
    private function deleteCategory($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        if (!isset($data['id'])) return ['success' => false, 'message' => 'ID da categoria é obrigatório.'];
        
        $this->db->delete(
            'categorias',
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return ['success' => true, 'message' => 'Categoria excluída com sucesso!'];
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
     * Update ingredient
     */
    private function updateIngredient($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        if (!isset($data['id'])) return ['success' => false, 'message' => 'ID do ingrediente é obrigatório.'];
        
        $updateData = [];
        if (isset($data['nome'])) $updateData['nome'] = $data['nome'];
        if (isset($data['tipo'])) $updateData['tipo'] = $data['tipo'];
        if (isset($data['preco_adicional'])) $updateData['preco_adicional'] = $data['preco_adicional'];
        if (isset($data['disponivel'])) $updateData['disponivel'] = $data['disponivel'];
        
        $this->db->update(
            'ingredientes',
            $updateData,
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return ['success' => true, 'message' => 'Ingrediente atualizado com sucesso!'];
    }

    /**
     * Delete ingredient
     */
    private function deleteIngredient($data)
    {
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        
        if (!isset($data['id'])) return ['success' => false, 'message' => 'ID do ingrediente é obrigatório.'];
        
        $this->db->delete(
            'ingredientes',
            'id = ? AND tenant_id = ? AND filial_id = ?',
            [$data['id'], $tenantId, $filialId]
        );
        
        return ['success' => true, 'message' => 'Ingrediente excluído com sucesso!'];
    }

    /**
     * Create new order
     */
    private function createOrder($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $usuarioId = $this->session->getUserId() ?? 1;

        if (!empty($data['itens'])) {
            $stockError = $this->validateItensEstoque($data['itens'], $tenantId, $filialId);
            if ($stockError !== null) {
                return $stockError;
            }
        }
        
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
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $pedidoId = $data['pedido_id'] ?? null;
        if (!$pedidoId) return ['success' => false, 'message' => 'ID do pedido não fornecido.'];

        if (!empty($data['itens'])) {
            $stockError = $this->validateItensEstoque($data['itens'], $tenantId, $filialId);
            if ($stockError !== null) {
                return $stockError;
            }
        }
        
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

    private function validateItensEstoque(array $itens, int $tenantId, int $filialId): ?array
    {
        if ($this->ignoreStock) {
            return null;
        }

        foreach ($itens as $item) {
            $produtoId = (int) ($item['id'] ?? 0);
            $quantidade = max(1, (int) ($item['quantidade'] ?? 1));
            if ($produtoId <= 0) {
                continue;
            }

            $produto = $this->db->fetch(
                "SELECT nome, estoque_atual, ativo FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$produtoId, $tenantId, $filialId]
            );

            if (!$produto) {
                return ['success' => false, 'message' => "Produto ID {$produtoId} n??o encontrado no card??pio."];
            }

            if (isset($produto['ativo']) && !$produto['ativo']) {
                return ['success' => false, 'message' => "O produto \"{$produto['nome']}\" est?? indispon??vel no momento."];
            }

            $estoque = (float) ($produto['estoque_atual'] ?? 0);
            if ($estoque < $quantidade) {
                $disp = rtrim(rtrim(number_format($estoque, 2, ',', '.'), '0'), ',');
                return [
                    'success' => false,
                    'message' => "Sem estoque suficiente para \"{$produto['nome']}\" (dispon??vel: {$disp}, pedido: {$quantidade}).",
                ];
            }
        }

        return null;
    }
    
    private function listarPendenciasFiado($data = [])
    {
        $tenantId = $data['tenant_id'] ?? $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $nomeCliente = $data['nome_cliente'] ?? null;
        
        if ($nomeCliente) {
            $devedores = $this->db->fetchAll(
                "SELECT id, nome, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? AND saldo_devedor >= 0.01 AND nome ILIKE ? 
                 ORDER BY nome ASC LIMIT 50", 
                [$tenantId, "%{$nomeCliente}%"]
            );
        } else {
            $devedores = $this->db->fetchAll(
                "SELECT id, nome, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? AND saldo_devedor >= 0.01 
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
    
    private function listarClientesGeral($data)
    {
        $tenantId = $data['tenant_id'] ?? $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $nomeCliente = $data['nome_cliente'] ?? null;
        
        if ($nomeCliente) {
            $clientes = $this->db->fetchAll(
                "SELECT id, nome, telefone, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? AND nome ILIKE ? 
                 ORDER BY nome ASC LIMIT 50", 
                [$tenantId, "%{$nomeCliente}%"]
            );
        } else {
            $clientes = $this->db->fetchAll(
                "SELECT id, nome, telefone, saldo_devedor, cobranca_automatica, cobranca_frequencia 
                 FROM clientes_fiado 
                 WHERE tenant_id = ? 
                 ORDER BY nome ASC LIMIT 50", 
                [$tenantId]
            );
        }
        
        if (empty($clientes)) {
            if ($nomeCliente) {
                return ['success' => true, 'message' => "Nenhum cliente encontrado com o nome '{$nomeCliente}' no sistema."];
            }
            return ['success' => true, 'message' => "Nenhum cliente cadastrado no sistema."];
        }
        
        $msg = "*Lista Geral de Clientes (Até 50):*\n\n";
        foreach ($clientes as $c) {
            $cob = $c['cobranca_automatica'] ? "Ativa ({$c['cobranca_frequencia']})" : "Inativa";
            $msg .= "👤 *{$c['nome']}* (ID Fiado: {$c['id']})\n";
            if (!empty($c['telefone'])) $msg .= "📱 Telefone: {$c['telefone']}\n";
            $msg .= "💰 Saldo Devedor Atual: R$ " . number_format($c['saldo_devedor'], 2, ',', '.') . "\n";
            $msg .= "🤖 Cobrança IA: {$cob}\n\n";
        }
        
        $msg .= "[IMPORTANTE PARA A IA]: Se o usuário perguntou sobre quantidade de clientes, ou procurou um nome, CITE TODOS OS CLIENTES DESTA LISTA, sem omitir nenhum, mesmo que o saldo devedor seja zero. Não confunda pessoas com nomes parecidos.";
        
        return ['success' => true, 'message' => $msg];
    }
    
    private function listarComprasCliente($data)
    {
        $tenantId = $data['tenant_id'] ?? $this->tenantId ?? $this->session->getTenantId() ?? 1;
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

    private function resolveContaCaixaId(int $tenantId, int $filialId): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND tipo = 'caixa' AND ativo = true ORDER BY id ASC LIMIT 1",
            [$tenantId, $filialId]
        );
        return (int)($row['id'] ?? 1);
    }

    private function resolveClienteIdParaPagamento(array $data, int $tenantId): ?int
    {
        if (!empty($data['cliente_id'])) {
            return (int)$data['cliente_id'];
        }

        $nome = trim((string)($data['nome_cliente'] ?? ''));
        if ($nome === '') {
            return null;
        }

        $comDivida = $this->db->fetchAll(
            "SELECT id, nome, saldo_devedor FROM clientes_fiado
             WHERE tenant_id = ? AND saldo_devedor >= 0.01 AND nome ILIKE ?
             ORDER BY saldo_devedor DESC, nome ASC",
            [$tenantId, "%{$nome}%"]
        );

        if (empty($comDivida)) {
            return null;
        }

        $nomeLower = mb_strtolower($nome);
        $exatos = array_values(array_filter($comDivida, fn($c) => mb_strtolower(trim($c['nome'])) === $nomeLower));
        if (count($exatos) === 1) {
            return (int)$exatos[0]['id'];
        }

        if (count($comDivida) === 1) {
            return (int)$comDivida[0]['id'];
        }

        return null;
    }
    
    private function baixarPagamentoFiado($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $cId = $this->resolveClienteIdParaPagamento($data, $tenantId);
        
        $pagamentos = $data['pagamentos'] ?? [];
        if (empty($pagamentos) && isset($data['valor_pago'])) {
            $pagamentos = [[
                'valor' => (float)$data['valor_pago'],
                'forma_pagamento' => $data['forma_pagamento'] ?? 'dinheiro'
            ]];
        }
        
        $descontoValor = (float)($data['desconto_valor'] ?? 0);
        $destino = $data['destino'] ?? 'ambos'; // 'fiado', 'pedido' ou 'ambos'
        
        $valorTotalPago = 0;
        foreach ($pagamentos as $pag) {
            $valorTotalPago += (float)($pag['valor'] ?? 0);
        }
        
        if (!$cId || ($valorTotalPago <= 0 && $descontoValor <= 0)) {
            $nomeHint = trim((string)($data['nome_cliente'] ?? ''));
            if ($nomeHint !== '') {
                return ['success' => false, 'message' => "Encontrei mais de um cliente com o nome '{$nomeHint}' ou nenhum com d??vida. Use listar_pendencias_fiado e informe o cliente_id correto."];
            }
            return ['success' => false, 'message' => "Informe o cliente_id (ou nome_cliente) e os valores pagos ou desconto."];
        }
        
        $cliente = $this->db->fetch("SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ?", [$cId, $tenantId]);
        if (!$cliente) return ['success' => false, 'message' => "Cliente não encontrado."];
        
        $usuarioGlobalId = $cliente['usuario_global_id'] ?? $cId;
        
        // Constrói as queries
        $queryFiado = "SELECT id, valor_total, valor_pago, (valor_total - valor_pago) as saldo_devedor, data_vencimento as data_ordenacao FROM vendas_fiadas WHERE cliente_id = ? AND tenant_id = ? AND status IN ('pendente', 'vencido') ORDER BY data_ordenacao ASC";
        $queryPedido = "SELECT idpedido as id, valor_total, valor_pago, saldo_devedor, created_at as data_ordenacao FROM pedido WHERE usuario_global_id = ? AND tenant_id = ? AND status_pagamento IN ('pendente', 'parcial') AND status NOT IN ('Cancelado') ORDER BY data_ordenacao ASC";
        
        $pedidosFiado = ($destino === 'fiado' || $destino === 'ambos') ? $this->db->fetchAll($queryFiado, [$cId, $tenantId]) : [];
        $pedidosComanda = ($destino === 'pedido' || $destino === 'ambos') ? $this->db->fetchAll($queryPedido, [$usuarioGlobalId, $tenantId]) : [];

        $temDividaFiado = !empty(array_filter($pedidosFiado, fn($p) => (float)$p['saldo_devedor'] > 0));
        $temDividaComanda = !empty(array_filter($pedidosComanda, fn($p) => (float)$p['saldo_devedor'] > 0));
        if ($valorTotalPago > 0 && !$temDividaFiado && !$temDividaComanda) {
            return ['success' => false, 'message' => "O cliente {$cliente['nome']} (ID {$cId}) n??o possui d??vidas pendentes para receber R$ " . number_format($valorTotalPago, 2, ',', '.') . "."];
        }
        
        $contaCaixaId = $this->resolveContaCaixaId($tenantId, $filialId);
        $totalAmortizado = 0.0;
        
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
                // Pagamos as tabelas paralelamente, já que representam a mesma dívida
                $valorRestanteFiado = $pgto['valor'];
                $valorRestantePedido = $pgto['valor'];
                
                // 1. Amortiza na tabela vendas_fiadas
                foreach ($pedidosFiado as &$p) {
                    if ($valorRestanteFiado <= 0) break;
                    if ((float)$p['saldo_devedor'] <= 0) continue;
                    
                    $saldoAtual = (float)$p['saldo_devedor'];
                    $valorPagarNestaVenda = min($valorRestanteFiado, $saldoAtual);
                    
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
                    
                    // Lança no caixa (movimentacoes_financeiras) a partir da baixa fiado
                    if (!$pgto['is_desconto'] && $valorPagarNestaVenda > 0) {
                        $vendaRow = $this->db->fetch("SELECT pedido_id FROM vendas_fiadas WHERE id = ?", [$p['id']]);
                        $pedidoIdVenda = (int)($vendaRow['pedido_id'] ?? 0);

                        $this->db->insert('movimentacoes_financeiras', [
                            'tenant_id' => $tenantId,
                            'filial_id' => $filialId,
                            'tipo' => 'entrada',
                            'categoria_id' => 2,
                            'conta_id' => $contaCaixaId,
                            'data_movimentacao' => date('Y-m-d'),
                            'status' => 'pago',
                            'forma_pagamento' => $pgto['forma_pagamento'],
                            'descricao' => "Pagamento fiado (IA) - {$cliente['nome']} - Venda ID: {$p['id']}",
                            'valor' => $valorPagarNestaVenda,
                            'pedido_id' => $pedidoIdVenda ?: null
                        ]);
                    }
                    
                    $valorRestanteFiado -= $valorPagarNestaVenda;
                    $totalAmortizado += $valorPagarNestaVenda;
                }
                
                // 2. Amortiza na tabela pedido (apenas baixa de sistema, não lança no caixa de novo)
                foreach ($pedidosComanda as &$p) {
                    if ($valorRestantePedido <= 0) break;
                    if ((float)$p['saldo_devedor'] <= 0) continue;
                    
                    $saldoAtual = (float)$p['saldo_devedor'];
                    $valorPagarNestaVenda = min($valorRestantePedido, $saldoAtual);
                    
                    $this->db->insert('pagamentos_pedido', [
                        'pedido_id' => $p['id'],
                        'valor_pago' => $valorPagarNestaVenda,
                        'forma_pagamento' => $pgto['forma_pagamento'],
                        'descricao' => $pgto['is_desconto'] ? 'Desconto (IA)' : 'Pagamento Fiado/Comanda (IA)',
                        'usuario_id' => $this->session->getUserId() ?? 1,
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId
                    ]);
                    
                    $novoValorPago = (float)$p['valor_pago'] + $valorPagarNestaVenda;
                    $novoSaldoDevedor = (float)$p['saldo_devedor'] - $valorPagarNestaVenda;
                    $novoStatus = $novoSaldoDevedor <= 0.01 ? 'quitado' : 'parcial';
                    
                    $this->db->update('pedido', [
                        'valor_pago' => $novoValorPago,
                        'saldo_devedor' => $novoSaldoDevedor,
                        'status_pagamento' => $novoStatus
                    ], 'idpedido = ?', [$p['id']]);
                    
                    $p['valor_pago'] = $novoValorPago;
                    $p['saldo_devedor'] = $novoSaldoDevedor;
                    
                    $valorRestantePedido -= $valorPagarNestaVenda;
                }
            }
            
            // Só subtraímos do clientes_fiado o que foi pago ou descontado DENTRO das vendas_fiadas?
            // O saldo_devedor na tabela clientes_fiado deve refletir o total atualizado das vendas_fiadas
            // O jeito mais seguro é recalcular o saldo_devedor.
            $saldoFiadoAtualizado = $this->db->fetch("SELECT SUM(valor_total - valor_pago) as total FROM vendas_fiadas WHERE cliente_id = ? AND tenant_id = ? AND status IN ('pendente', 'vencido')", [$cId, $tenantId]);
            $novoSaldo = max(0, (float)($saldoFiadoAtualizado['total'] ?? 0));
            $this->db->update('clientes_fiado', ['saldo_devedor' => $novoSaldo], 'id = ?', [$cId]);

            if ($valorTotalPago > 0 && $totalAmortizado <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Nenhum valor foi abatido da dívida de {$cliente['nome']}. Verifique o cliente e o saldo pendente."];
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => "Pagamento de R$ " . number_format($totalAmortizado > 0 ? $totalAmortizado : $valorTotalPago, 2, ',', '.') . " registrado para *{$cliente['nome']}* (ID {$cId}). Desconto: R$ " . number_format($descontoValor, 2, ',', '.') . ". Novo saldo fiado: R$ " . number_format($novoSaldo, 2, ',', '.')];
        } catch (\Exception $e) {
            try {
                if ($this->db->getConnection()->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (\Exception $ignored) {
            }
            return ['success' => false, 'message' => "Erro ao registrar pagamento: " . $e->getMessage()];
        }
    }

    private function verEstoque($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $nome = $data['produto_nome'] ?? '';
        
        if ($this->ignoreStock) {
            return ['success' => true, 'message' => 'O controle de estoque está desativado nas configurações. Todos os produtos são considerados disponíveis.'];
        }

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
        
        if ($this->ignoreStock) {
            return ['success' => false, 'message' => 'O controle de estoque está desativado nas configurações. Não é possível atualizar o estoque agora.'];
        }

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

    private function listarProdutos($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $produtos = $this->db->fetchAll("SELECT id, nome, preco_normal, preco_promocional, em_promocao, estoque_atual FROM produtos WHERE tenant_id = ? AND filial_id = ? ORDER BY nome ASC", [$tenantId, $filialId]);
        
        if (empty($produtos)) return ['success' => true, 'message' => "Nenhum produto cadastrado.", 'total' => 0];
        
        $msg = "*Lista de Produtos (total: " . count($produtos) . "):*\n";
        foreach ($produtos as $p) {
            $preco = (!empty($p['em_promocao']) && !empty($p['preco_promocional']))
                ? 'R$ ' . number_format((float) $p['preco_promocional'], 2, ',', '.') . ' (promoção, de R$ ' . number_format((float) $p['preco_normal'], 2, ',', '.') . ')'
                : 'R$ ' . number_format((float) $p['preco_normal'], 2, ',', '.');
            $linha = "- ID: {$p['id']} | {$p['nome']} | Preço: {$preco}";
            if (!$this->ignoreStock) {
                $estoque = (float) ($p['estoque_atual'] ?? 0);
                $linha .= ' | Estoque: ' . $estoque . ($estoque <= 0 ? ' (indisponível)' : '');
            }
            $msg .= $linha . "\n";
        }
        return ['success' => true, 'message' => $msg, 'total' => count($produtos)];
    }

    private function listarPromocoes($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;

        $produtos = $this->db->fetchAll(
            "SELECT id, nome, preco_normal, preco_promocional, em_promocao
             FROM produtos
             WHERE tenant_id = ? AND filial_id = ?
               AND em_promocao = true
               AND COALESCE(ativo, true) = true
             ORDER BY nome ASC",
            [$tenantId, $filialId]
        );

        if (empty($produtos)) {
            return ['success' => true, 'message' => 'Nenhum produto em promoção no momento.', 'total' => 0, 'produtos' => []];
        }

        $msg = '*Produtos em promoção hoje (' . count($produtos) . "):*\n";
        foreach ($produtos as $p) {
            $msg .= '- ID: ' . $p['id'] . ' | ' . $p['nome']
                . ' | De R$ ' . number_format((float) $p['preco_normal'], 2, ',', '.')
                . ' por R$ ' . number_format((float) $p['preco_promocional'], 2, ',', '.') . "\n";
        }

        return ['success' => true, 'message' => $msg, 'total' => count($produtos), 'produtos' => $produtos];
    }

    private function listarCategorias($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $categorias = $this->db->fetchAll("SELECT id, nome FROM categorias WHERE tenant_id = ? AND filial_id = ? ORDER BY nome ASC", [$tenantId, $filialId]);
        
        if (empty($categorias)) return ['success' => true, 'message' => "Nenhuma categoria cadastrada."];
        
        $msg = "*Lista de Categorias:*\n";
        foreach ($categorias as $c) {
            $msg .= "- ID: {$c['id']} | {$c['nome']}\n";
        }
        return ['success' => true, 'message' => $msg];
    }

    private function listarIngredientes($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $ingredientes = $this->db->fetchAll("SELECT id, nome, valor FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome ASC", [$tenantId, $filialId]);
        
        if (empty($ingredientes)) return ['success' => true, 'message' => "Nenhum ingrediente cadastrado."];
        
        $msg = "*Lista de Ingredientes:*\n";
        foreach ($ingredientes as $i) {
            $msg .= "- ID: {$i['id']} | {$i['nome']} | Valor: R$ " . number_format($i['valor'], 2, ',', '.') . "\n";
        }
        return ['success' => true, 'message' => $msg];
    }

    private function listarPedidos($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        $pedidos = $this->db->fetchAll("SELECT idpedido, mesa_id, valor_total, status, data FROM pedido WHERE tenant_id = ? AND filial_id = ? AND data::date = CURRENT_DATE ORDER BY data DESC LIMIT 20", [$tenantId, $filialId]);
        
        if (empty($pedidos)) return ['success' => true, 'message' => "Nenhum pedido registrado hoje."];
        
        $msg = "*Pedidos de Hoje (Até 20):*\n";
        foreach ($pedidos as $p) {
            $msg .= "- Pedido #{$p['idpedido']} | Mesa: {$p['mesa_id']} | Total: R$ " . number_format($p['valor_total'], 2, ',', '.') . " | Status: {$p['status']}\n";
        }
        return ['success' => true, 'message' => $msg];
    }

    private function registrarDespesa($data)
    {
        $tenantId = $this->tenantId ?? $this->session->getTenantId() ?? 1;
        $filialId = $this->filialId ?? $this->session->getFilialId() ?? 1;
        
        $valor = (float)($data['valor'] ?? 0);
        $descricao = $data['descricao'] ?? '';
        $categoriaId = $data['categoria_id'] ?? 1; // Padrão
        $formaPagamento = $data['forma_pagamento'] ?? 'dinheiro';
        
        if ($valor <= 0 || empty($descricao)) return ['success' => false, 'message' => 'Informe o valor e a descrição da despesa.'];
        
        try {
            $this->db->insert('movimentacoes_financeiras', [
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'tipo' => 'despesa',
                'categoria_id' => $categoriaId,
                'conta_id' => 1,
                'data_movimentacao' => date('Y-m-d'),
                'status' => 'pago',
                'forma_pagamento' => $formaPagamento,
                'descricao' => "Despesa/Pagamento lançada via IA: {$descricao}",
                'valor' => $valor,
                'observacoes' => "Lançamento manual por IA"
            ]);
            
            return ['success' => true, 'message' => "Despesa de R$ " . number_format($valor, 2, ',', '.') . " ('{$descricao}') registrada com sucesso no financeiro!"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => "Erro ao registrar despesa: " . $e->getMessage()];
        }
    }
}
