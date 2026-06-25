<?php
namespace System\Agents;

abstract class BaseAgent {
    protected $db;
    protected $session;
    protected $apiKey;
    protected $model = 'gpt-4o-mini';
    protected $tenantId;
    protected $filialId;
    protected $personaPrompt = '';
    protected $whatsappCustomerMode = false;
    protected $ignoreStock = false;
    protected $orderSessionId = null;
    protected $customerName = '';
    protected $customerPhone = '';
    
    public function setOrderSessionId(?int $sessionId): void
    {
        $this->orderSessionId = $sessionId;
    }

    public function setCustomerContext(string $name, string $phone): void
    {
        $this->customerName = trim($name);
        $this->customerPhone = trim($phone);
    }
    
    public function __construct() {
        $this->db = \System\Database::getInstance();
        $this->session = \System\Session::getInstance();
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        if (!$this->apiKey && file_exists(__DIR__ . '/../../.env')) {
            $env = parse_ini_file(__DIR__ . '/../../.env');
            $this->apiKey = $env['OPENAI_API_KEY'] ?? '';
        }
    }
    
    public function setContext($tenantId, $filialId) {
        $this->tenantId = $tenantId;
        $this->filialId = $filialId;
    }

    public function setPersonaPrompt(?string $prompt): void
    {
        $this->personaPrompt = trim((string) $prompt);
    }

    public function setWhatsAppCustomerMode(bool $enabled): void
    {
        $this->whatsappCustomerMode = $enabled;
    }

    public function setIgnoreStock(bool $enabled): void
    {
        $this->ignoreStock = $enabled;
    }
    
    /**
     * Define the tools available to this agent
     */
    abstract protected function getTools(): array;
    
    /**
     * Define the system prompt for this agent
     */
    abstract protected function getSystemPrompt(): string;
    
    /**
     * Execute a specific tool
     */
    abstract protected function executeTool(string $name, array $args): array;
    
    /**
     * Process a message loop with tool calling
     */
    public function process(array $messages, $maxIterations = 4) {
        // Ensure system prompt is present
        $systemPrompt = $this->getSystemPrompt();
        $systemContext = "\n\nData e Hora Atual: " . date('Y-m-d H:i:s');
        $systemContext .= "\n[IMPORTANTE] Você é o Agente: " . static::class;
        if ($this->personaPrompt !== '') {
            $systemContext .= "\n\n[PERSONALIZAÇÃO DO ATENDIMENTO WHATSAPP]\n" . $this->personaPrompt;
        }
        
        $hasSystem = false;
        foreach ($messages as &$msg) {
            if ($msg['role'] === 'system') {
                $msg['content'] = $systemPrompt . $systemContext;
                $hasSystem = true;
                break;
            }
        }
        
        if (!$hasSystem) {
            array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt . $systemContext]);
        }
        
        $iteration = 0;
        $tools = $this->getTools();
        
        while ($iteration < $maxIterations) {
            $iteration++;
            
            $data = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.1
            ];
            
            if (!empty($tools)) {
                $data['tools'] = $tools;
                $data['tool_choice'] = 'auto';
            }
            
            $response = $this->makeOpenAIRequest($data);
            $responseMessage = $response['choices'][0]['message'] ?? null;
            
            if (!$responseMessage) {
                return ['success' => false, 'error' => 'Resposta inválida da OpenAI'];
            }
            
            // Add assistant response to messages
            $messages[] = $responseMessage;
            
            // Handle tool calls
            if (!empty($responseMessage['tool_calls'])) {
                foreach ($responseMessage['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $functionArgs = json_decode($toolCall['function']['arguments'], true) ?? [];
                    
                    try {
                        $toolResult = $this->executeTool($functionName, $functionArgs);
                        
                        // If it's a handoff response from a sub-agent, we abort the loop and return it to the user directly
                        if (isset($toolResult['_final_handoff_response'])) {
                            return [
                                'success' => true,
                                'response' => $toolResult['_final_handoff_response'],
                                'messages' => $messages
                            ];
                        }
                        
                        $resultStr = json_encode($toolResult, JSON_UNESCAPED_UNICODE);
                    } catch (\Throwable $e) {
                        file_put_contents(__DIR__ . '/../../ai_debug.log', date('Y-m-d H:i:s') . " - Tool " . $functionName . " Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", FILE_APPEND);
                        $toolResult = ['success' => false, 'error' => $e->getMessage()];
                        $resultStr = json_encode($toolResult, JSON_UNESCAPED_UNICODE);
                    }
                    
                    // Add tool response to messages
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $functionName,
                        'content' => $resultStr
                    ];
                }
                // Continue loop to let AI process tool results
                continue;
            }
            
            // If no tool calls, this is the final response
            return [
                'success' => true,
                'response' => $responseMessage['content'],
                'messages' => $messages
            ];
        }

        // Ao atingir o limite, devolve a última resposta textual se existir
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (($msg['role'] ?? '') === 'assistant' && !empty($msg['content'])) {
                return [
                    'success' => true,
                    'response' => $msg['content'],
                    'messages' => $messages
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Limite de iterações atingido',
            'messages' => $messages
        ];
    }
    
    protected function makeOpenAIRequest($data) {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('CURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('OpenAI API Error (HTTP ' . $httpCode . '): ' . $response);
        }
        
        return json_decode($response, true);
    }
}
