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
    public function processMessage($message, $attachments = [])
    {
        try {
            // Process attachments if any
            $attachmentData = [];
            if (!empty($attachments)) {
                $attachmentData = $this->processAttachments($attachments);
            }
            
            // Prepare messages for OpenAI
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt()
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
        return "Assistente IA para restaurante. Operações: criar_produto, listar_produtos, criar_ingrediente, listar_ingredientes, criar_categoria, listar_categorias, listar_pedidos, responder_pergunta. Responda em português. Para confirmação: {\"type\":\"confirmation\",\"message\":\"...\",\"action\":\"acao\",\"confirm\":true}. Para respostas: {\"type\":\"response\",\"message\":\"...\"}.";
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
             ORDER BY id_mesa::integer",
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
        $tenantId = $this->session->getTenantId() ?? 1;
        $filialId = $this->session->getFilialId() ?? 1;
        $usuarioId = $this->session->getUserId();
        
        $orderId = $this->db->insert('pedido', [
            'idmesa' => $data['mesa_id'] ?? '999',
            'cliente' => $data['cliente'] ?? 'Cliente IA',
            'delivery' => $data['delivery'] ?? false,
            'data' => date('Y-m-d'),
            'hora_pedido' => date('H:i:s'),
            'valor_total' => $data['valor_total'] ?? 0,
            'status' => 'Pendente',
            'observacao' => $data['observacao'] ?? 'Pedido criado via IA',
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        return [
            'success' => true,
            'message' => 'Pedido criado com sucesso!',
            'order_id' => $orderId
        ];
    }
}
