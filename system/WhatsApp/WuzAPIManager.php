<?php

namespace System\WhatsApp;

use Exception;

/**
 * Gerenciador da WuzAPI - API WhatsApp moderna em Go
 * Endpoints: QR code, status, mensagens, webhooks
 */
class WuzAPIManager 
{
    private $wuzapiUrl;
    private $apiKey;
    private $webhookUrl;
    
    public function __construct() 
    {
        error_log("WuzAPIManager::__construct - Iniciando construtor");
        // URL interna do Docker: wuzapi:8080 (comunicação entre containers)
        // URL externa para testes: localhost:8081 (mapeamento externo)
        $this->wuzapiUrl = $_ENV['WUZAPI_URL'] ?? 'http://wuzapi:8080';
        $this->apiKey = $_ENV['WUZAPI_API_KEY'] ?? '1234ABCD'; // Token padrão da WuzAPI
        $this->webhookUrl = ''; // Webhook será definido apenas quando necessário
        error_log("WuzAPIManager::__construct - Construtor finalizado");
    }
    
    /**
     * Conectar sessão WhatsApp via WuzAPI
     */
    public function createInstance($instanceName, $phoneNumber, $webhookUrl = null) 
    {
        try {
            error_log("WuzAPIManager::createInstance - Iniciando criação de instância: $instanceName");
            
            // Criar usuário diretamente no banco da WuzAPI
            $token = $this->generateRandomToken();
            error_log("WuzAPIManager::createInstance - Token gerado: $token");
            
            // Conectar ao banco da WuzAPI usando as mesmas credenciais do sistema
            // Credentials must be provided via environment variables
            $dbHost = $_ENV['DB_HOST'] ?? null;
            $dbPort = $_ENV['DB_PORT'] ?? '5432';
            $dbName = $_ENV['DB_NAME'] ?? null;
            $dbUser = $_ENV['DB_USER'] ?? null;
            $dbPassword = $_ENV['DB_PASSWORD'] ?? null;
            
            if (!$dbHost || !$dbName || !$dbUser || !$dbPassword) {
                throw new Exception('Database credentials not configured. Please set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD environment variables.');
            }
            
            $pdo = new \PDO(
                "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
                $dbUser,
                $dbPassword,
                [
                    \PDO::ATTR_TIMEOUT => 120,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
            error_log("WuzAPIManager::createInstance - Conexão PDO estabelecida");
            
            // Testar conexão
            $testStmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $testStmt->fetchColumn();
            error_log("WuzAPIManager::createInstance - Usuários existentes na WuzAPI: $userCount");
            
            $stmt = $pdo->prepare("
                INSERT INTO users (name, token, webhook, events) 
                VALUES (?, ?, ?, ?) 
                RETURNING id, name, token
            ");
            
            $events = 'Message,Connected,Disconnected';
            error_log("WuzAPIManager::createInstance - Executando INSERT com: $instanceName, $token, " . ($webhookUrl ?? '') . ", $events");
            
            $stmt->execute([$instanceName, $token, $webhookUrl ?? '', $events]);
            $result = array();
            if ($row = $stmt->fetch()) {
                $result = $row;
            }
            
            error_log("WuzAPIManager::createInstance - Resultado: " . json_encode($result));
            
            if ($result) {
                // Tentar iniciar a sessão para ativar a instância
                try {
                    error_log("WuzAPIManager::createInstance - Tentando iniciar sessão para instância {$result['id']} com token {$result['token']}");
                    $sessionStarted = $this->startSession($result['token']);
                    if ($sessionStarted) {
                        error_log("WuzAPIManager::createInstance - Sessão iniciada com sucesso para instância {$result['id']}");
                    } else {
                        error_log("WuzAPIManager::createInstance - Falha ao iniciar sessão para instância {$result['id']}");
                    }
                } catch (Exception $e) {
                    error_log("WuzAPIManager::createInstance - Erro ao iniciar sessão: " . $e->getMessage());
                }
                
                return [
                    'success' => true,
                    'instance_id' => $result['id'],
                    'token' => $result['token'],
                    'message' => 'Instância criada na WuzAPI com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao criar instância na WuzAPI'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::createInstance - Error: " . $e->getMessage());
            error_log("WuzAPIManager::createInstance - Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Erro ao criar instância: ' . $e->getMessage()
            ];
        }
    }
    
    private function generateRandomToken() {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }
    
    private function getConnectPayload(): string
    {
        return json_encode([
            'Subscribe' => ['Message', 'ReadReceipt', 'ChatPresence'],
            'Immediate' => true,
        ]);
    }

    private function parseSessionData(?array $data): array
    {
        if (!$data) {
            return ['connected' => false, 'websocket' => false, 'logged_in' => false, 'qr_code' => null];
        }

        $loggedIn = (bool) ($data['LoggedIn'] ?? $data['loggedIn'] ?? false);
        $websocket = (bool) ($data['Connected'] ?? $data['connected'] ?? false);

        return [
            'connected' => $loggedIn || $websocket,
            'websocket' => $websocket,
            'logged_in' => $loggedIn,
            'qr_code' => $data['QRCode'] ?? $data['qrcode'] ?? $data['Qrcode'] ?? null,
        ];
    }

    private function isTransientSessionError(string $message): bool
    {
        $needles = ['already connected', 'not connected', 'no session'];
        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractQrFromResponses(?array $statusResponse, ?array $qrResponse = null): ?string
    {
        $fromStatus = $this->parseSessionData($statusResponse['data'] ?? null);
        if (!empty($fromStatus['qr_code'])) {
            return $fromStatus['qr_code'];
        }

        if ($qrResponse) {
            return $qrResponse['data']['QRCode'] ?? $qrResponse['data']['qrcode'] ?? null;
        }

        return null;
    }

    private function safeConnect(string $token): void
    {
        try {
            $this->makeApiCall('POST', '/session/connect', $this->getConnectPayload(), $token);
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'already connected') === false) {
                throw $e;
            }
            error_log('WuzAPIManager::safeConnect - Sessão já conectada, continuando para obter QR');
        }
    }

    /**
     * Reseta sessão zumbi (connected=1 no banco mas websocket desconectado)
     */
    private function resetZombieSession($wuzapiInstanceId, $token, $jid = null)
    {
        $db = \System\Database::getInstance();

        if ($jid) {
            $db->query("DELETE FROM whatsmeow_device WHERE jid = ?", [$jid]);
        }

        $db->query(
            "UPDATE users SET connected = 0, jid = '', qrcode = '' WHERE token = ?",
            [$token]
        );

        $db->query(
            "UPDATE whatsapp_instances SET status = 'disconnected' WHERE wuzapi_instance_id = ? OR wuzapi_token = ?",
            [$wuzapiInstanceId, $token]
        );

        error_log("WuzAPIManager::resetZombieSession - Sessão resetada para WuzAPI ID: $wuzapiInstanceId");
    }

    private function isZombieSession($connectResponse, $statusResponse): bool
    {
        $session = $this->parseSessionData($statusResponse['data'] ?? null);
        $details = $connectResponse['data']['details'] ?? '';
        $jid = $connectResponse['data']['jid'] ?? '';

        return !$session['logged_in'] && !$session['connected'] && (
            $details === 'Already Connected' ||
            (!empty($jid) && $details !== 'Connected!')
        );
    }

    /**
     * Gerar QR code para sessão
     */
    public function generateQRCode($instanceId) 
    {
        try {
            error_log("WuzAPIManager::generateQRCode - Gerando QR para instância: $instanceId");
            
            $db = \System\Database::getInstance();
            $instance = $db->query("SELECT wuzapi_instance_id, wuzapi_token FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance || !$instance['wuzapi_instance_id']) {
                return [
                    'success' => false,
                    'message' => 'Instância não encontrada na WuzAPI'
                ];
            }
            
            $wuzapiInstanceId = $instance['wuzapi_instance_id'];
            $token = $instance['wuzapi_token'];

            $statusResponse = $this->makeApiCall('GET', '/session/status', null, $token);
            $session = $this->parseSessionData($statusResponse['data'] ?? null);

            if ($session['logged_in']) {
                $db->query("UPDATE whatsapp_instances SET status = 'connected' WHERE id = ?", [$instanceId]);
                return [
                    'success' => true,
                    'qr_code' => null,
                    'status' => 'connected',
                    'message' => 'WhatsApp já está conectado'
                ];
            }

            if (!empty($session['qr_code'])) {
                $db->query("UPDATE whatsapp_instances SET status = 'connecting' WHERE id = ?", [$instanceId]);
                return [
                    'success' => true,
                    'qr_code' => $session['qr_code'],
                    'status' => 'connecting',
                    'message' => 'QR code gerado com sucesso. Escaneie com seu WhatsApp.'
                ];
            }

            $justConnected = false;
            if (!$session['websocket']) {
                try {
                    $connectResponse = $this->makeApiCall('POST', '/session/connect', $this->getConnectPayload(), $token);
                    $justConnected = true;
                    if ($this->isZombieSession($connectResponse, $statusResponse)) {
                        $jid = $connectResponse['data']['jid'] ?? null;
                        $this->resetZombieSession($wuzapiInstanceId, $token, $jid);
                        $this->makeApiCall('POST', '/session/connect', $this->getConnectPayload(), $token);
                    }
                } catch (Exception $e) {
                    if (!$this->isTransientSessionError($e->getMessage())) {
                        throw $e;
                    }
                }
            } else {
                $this->safeConnect($token);
            }

            if ($justConnected) {
                sleep(2);
            }

            for ($attempt = 1; $attempt <= 15; $attempt++) {
                $statusResponse = $this->makeApiCall('GET', '/session/status', null, $token);
                $session = $this->parseSessionData($statusResponse['data'] ?? null);

                if ($session['logged_in']) {
                    $db->query("UPDATE whatsapp_instances SET status = 'connected' WHERE id = ?", [$instanceId]);
                    return [
                        'success' => true,
                        'qr_code' => null,
                        'status' => 'connected',
                        'message' => 'WhatsApp conectado com sucesso'
                    ];
                }

                if (!empty($session['qr_code'])) {
                    $db->query("UPDATE whatsapp_instances SET status = 'connecting' WHERE id = ?", [$instanceId]);
                    return [
                        'success' => true,
                        'qr_code' => $session['qr_code'],
                        'status' => 'connecting',
                        'message' => 'QR code gerado com sucesso. Escaneie com seu WhatsApp.'
                    ];
                }

                $qrResponse = null;
                try {
                    $qrResponse = $this->makeApiCall('GET', '/session/qr', null, $token);
                } catch (Exception $e) {
                    if (stripos($e->getMessage(), 'client outdated') !== false) {
                        return [
                            'success' => false,
                            'message' => 'WuzAPI desatualizada. Atualize o serviço WuzAPI/whatsmeow no servidor.'
                        ];
                    }
                    if (!$this->isTransientSessionError($e->getMessage())) {
                        throw $e;
                    }
                }

                $qrCode = $this->extractQrFromResponses($statusResponse, $qrResponse);
                if (!empty($qrCode)) {
                    $db->query("UPDATE whatsapp_instances SET status = 'connecting' WHERE id = ?", [$instanceId]);
                    return [
                        'success' => true,
                        'qr_code' => $qrCode,
                        'status' => 'connecting',
                        'message' => 'QR code gerado com sucesso. Escaneie com seu WhatsApp.'
                    ];
                }

                sleep(2);
            }
            
            return [
                'success' => false,
                'message' => 'Não foi possível gerar o QR code. A sessão não conectou ao WhatsApp. Tente novamente ou verifique os logs da WuzAPI.'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::generateQRCode - Error: " . $e->getMessage());
            $message = $e->getMessage();
            if (stripos($message, 'Not connected') !== false) {
                $message = 'Sessão WhatsApp não conectou. Tente clicar em Conectar novamente.';
            }
            return [
                'success' => false,
                'message' => 'Erro ao gerar QR code: ' . $message
            ];
        }
    }
    
    /**
     * Verificar status da instância
     */
    public function getInstanceStatus($instanceId) 
    {
        try {
            // Buscar token da instância
            $db = \System\Database::getInstance();
            $instance = $db->query("SELECT wuzapi_token FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance || !$instance['wuzapi_token']) {
                return [
                    'success' => false,
                    'message' => 'Token da instância não encontrado'
                ];
            }
            
            $response = $this->makeApiCall('GET', "/session/status", null, $instance['wuzapi_token']);
            
            if ($response && isset($response['data'])) {
                $session = $this->parseSessionData($response['data']);

                $status = 'disconnected';
                if ($session['logged_in']) {
                    $status = 'connected';
                } elseif ($session['connected'] || !empty($session['qr_code'])) {
                    $status = 'connecting';
                }

                return [
                    'success' => true,
                    'status' => $status,
                    'connected' => $session['connected'],
                    'logged_in' => $session['logged_in'],
                    'message' => $response['data']['details'] ?? ''
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao verificar status'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::getInstanceStatus - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao verificar status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sincronizar status da instância no banco local
     */
    public function syncInstanceStatus($instanceId) 
    {
        try {
            $statusResponse = $this->getInstanceStatus($instanceId);
            
            if ($statusResponse['success']) {
                $db = \System\Database::getInstance();
                $db->query(
                    "UPDATE whatsapp_instances SET status = ? WHERE id = ?",
                    [$statusResponse['status'], $instanceId]
                );
                
                error_log("WuzAPIManager::syncInstanceStatus - Status sincronizado: {$statusResponse['status']} para instância $instanceId");
                
                return [
                    'success' => true,
                    'status' => $statusResponse['status'],
                    'message' => 'Status sincronizado com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao sincronizar status'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::syncInstanceStatus - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao sincronizar status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number to E.164 format for Brazil
     * Handles cases like:
     * - 5497092223 → +555497092223 (Brazil, DDD 54)
     * - 54997092223 → +555497092223 (Brazil, DDD 54, removes extra 9)
     * - 5541991710017 → +5541991710017 (Brazil, already with country code)
     * - 5554997092223 → +555497092223 (Brazil, removes extra 9 after DDD)
     * - +555497092223 → +555497092223 (already formatted)
     */
    private function formatPhoneNumber($phoneNumber) 
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If already starts with +, remove it temporarily to process
        $hasPlus = str_starts_with($cleaned, '+');
        if ($hasPlus) {
            $cleaned = substr($cleaned, 1);
        }
        
        // Fix numbers with extra 9: If starts with 55 + DDD + 9 + number (like 5554997092223)
        // Pattern: 55 + DD + 9 + 8 digits = 13 digits total (should be 55 + DD + 8 digits = 12 digits)
        if (preg_match('/^55(\d{2})9(\d{8})$/', $cleaned, $matches)) {
            $ddd = $matches[1];
            $number = $matches[2];
            // Validate DDD (Brazilian area codes are 11-99)
            if ($ddd >= 11 && $ddd <= 99) {
                $cleaned = '55' . $ddd . $number;
                error_log("WuzAPIManager::formatPhoneNumber - Removed extra 9 from: $phoneNumber → 55$ddd$number");
            }
        }
        
        // Fix numbers with extra 9: If starts with DDD + 9 + number (like 54997092223)
        // Pattern: DD + 9 + 8 digits = 11 digits total (should be DD + 8 digits = 10 digits)
        if (preg_match('/^(\d{2})9(\d{8})$/', $cleaned, $matches)) {
            $ddd = $matches[1];
            $number = $matches[2];
            // Validate DDD (Brazilian area codes are 11-99)
            if ($ddd >= 11 && $ddd <= 99) {
                $cleaned = $ddd . $number;
                error_log("WuzAPIManager::formatPhoneNumber - Removed extra 9 from: $phoneNumber → $cleaned");
            }
        }
        
        // Now format to E.164
        // If starts with 55 (Brazil country code), add +
        if (str_starts_with($cleaned, '55')) {
            return '+' . $cleaned;
        }
        
        // If starts with 0 (Brazilian local format), remove 0 and add +55
        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
            return '+55' . $cleaned;
        }
        
        // If it's a Brazilian DDD (2 digits) + 8 digits, add +55
        // Brazilian mobile: DDD (2 digits) + 8 digits = 10 digits total
        if (preg_match('/^(\d{2})(\d{8})$/', $cleaned, $matches)) {
            $ddd = $matches[1];
            $number = $matches[2];
            // Validate DDD (Brazilian area codes are 11-99)
            if ($ddd >= 11 && $ddd <= 99) {
                return '+55' . $ddd . $number;
            }
        }
        
        // If it's a Brazilian DDD (2 digits) + 9 digits, add +55
        // Brazilian mobile: DDD (2 digits) + 9 digits = 11 digits total
        if (preg_match('/^(\d{2})(\d{9})$/', $cleaned, $matches)) {
            $ddd = $matches[1];
            $number = $matches[2];
            // Validate DDD (Brazilian area codes are 11-99)
            if ($ddd >= 11 && $ddd <= 99) {
                return '+55' . $ddd . $number;
            }
        }
        
        // Default: assume Brazil and add +55
        error_log("WuzAPIManager::formatPhoneNumber - Unrecognized format for: $phoneNumber, assuming Brazil (+55)");
        return '+55' . $cleaned;
    }

    /**
     * Enviar mensagem via WuzAPI
     */
    public function sendMessage($instanceId, $phoneNumber, $message) 
    {
        try {
            // Format phone number to E.164 before sending
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);
            error_log("WuzAPIManager::sendMessage - Formatting: $phoneNumber → $formattedPhone");
            
            // Buscar token da instância
            $db = \System\Database::getInstance();
            $instance = $db->query("SELECT wuzapi_token FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance || !$instance['wuzapi_token']) {
                return [
                    'success' => false,
                    'message' => 'Token da instância não encontrado'
                ];
            }
            
            // Formato correto para WuzAPI: Phone e Body (com maiúsculas)
            // Remove o + para evitar truncamento na WuzAPI
            $phoneForWuzAPI = ltrim($formattedPhone, '+');
            $data = [
                'Phone' => $phoneForWuzAPI,
                'Body' => $message
            ];
            
            // Log the exact data being sent
            error_log("WuzAPIManager::sendMessage - Data being sent: " . json_encode($data));
            error_log("WuzAPIManager::sendMessage - Phone length: " . strlen($phoneForWuzAPI));
            error_log("WuzAPIManager::sendMessage - Original: $formattedPhone, For WuzAPI: $phoneForWuzAPI");
            
            $response = $this->makeApiCall('POST', "/chat/send/text", json_encode($data), $instance['wuzapi_token']);
            
            if ($response && isset($response['success']) && $response['success']) {
                error_log("WuzAPIManager::sendMessage - Mensagem enviada com sucesso para $formattedPhone (original: $phoneNumber)");
                return [
                    'success' => true,
                    'message' => 'Mensagem enviada com sucesso',
                    'message_id' => $response['data']['Id'] ?? null,
                    'response' => $response,
                    'formatted_phone' => $formattedPhone
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao enviar mensagem: ' . ($response['error'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::sendMessage - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar arquivo via WuzAPI
     */
    public function sendFile($instanceId, $phoneNumber, $fileUrl, $fileName, $caption = '') 
    {
        try {
            // Format phone number to E.164 before sending
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);
            error_log("WuzAPIManager::sendFile - Formatting: $phoneNumber → $formattedPhone");
            
            // Buscar token da instância
            $db = \System\Database::getInstance();
            $instance = $db->query("SELECT wuzapi_token FROM whatsapp_instances WHERE id = ?", [$instanceId])->fetch();
            
            if (!$instance || !$instance['wuzapi_token']) {
                return [
                    'success' => false,
                    'message' => 'Token da instância não encontrado'
                ];
            }
            
            // Remove o + para evitar truncamento na WuzAPI
            $phoneForWuzAPI = ltrim($formattedPhone, '+');
            
            // Determine file type based on extension
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileType = 'document'; // Default
            
            if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fileType = 'image';
            } elseif (in_array($fileExtension, ['mp4', 'avi', 'mov', 'wmv'])) {
                $fileType = 'video';
            } elseif (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'm4a'])) {
                $fileType = 'audio';
            }
            
            $data = [
                'Phone' => $phoneForWuzAPI,
                'Body' => $fileUrl,
                'Type' => $fileType,
                'Caption' => $caption
            ];
            
            // Log the exact data being sent
            error_log("WuzAPIManager::sendFile - Data being sent: " . json_encode($data));
            error_log("WuzAPIManager::sendFile - File URL: $fileUrl");
            error_log("WuzAPIManager::sendFile - File Type: $fileType");
            
            $response = $this->makeApiCall('POST', "/chat/send/{$fileType}", json_encode($data), $instance['wuzapi_token']);
            
            if ($response && isset($response['success']) && $response['success']) {
                error_log("WuzAPIManager::sendFile - Arquivo enviado com sucesso para $formattedPhone");
                return [
                    'success' => true,
                    'message' => 'Arquivo enviado com sucesso',
                    'message_id' => $response['data']['Id'] ?? null,
                    'response' => $response,
                    'formatted_phone' => $formattedPhone
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao enviar arquivo: ' . ($response['error'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::sendFile - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Iniciar sessão para ativar instância
     */
    public function startSession($token) 
    {
        try {
            error_log("WuzAPIManager::startSession - Iniciando sessão com token: $token");
            
            // Tentar conectar a sessão via API com payload vazio
            $response = $this->makeApiCall('POST', "/session/connect", '{}', $token);
            
            error_log("WuzAPIManager::startSession - Resposta da API /session/connect: " . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                error_log("WuzAPIManager::startSession - Sessão iniciada com sucesso");
                return true;
            }
            
            error_log("WuzAPIManager::startSession - Falha ao iniciar sessão: " . json_encode($response));
            return false;
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::startSession - Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter QR Code de uma instância
     */
    public function getQRCode($token) 
    {
        try {
            error_log("WuzAPIManager::getQRCode - Buscando QR Code para token: $token");
            
            // Buscar QR Code via API
            $response = $this->makeApiCall('GET', "/session/qr", null, $token);
            
            error_log("WuzAPIManager::getQRCode - Resposta da API: " . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                if (isset($response['data']['qrcode'])) {
                    return [
                        'success' => true,
                        'qr_code' => $response['data']['qrcode']
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'QR Code não disponível. A instância pode já estar conectada.'
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao buscar QR Code: ' . ($response['error'] ?? 'Erro desconhecido')
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::getQRCode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar QR Code: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Deletar instância
     */
    public function deleteInstance($instanceId) 
    {
        try {
            // Deletar diretamente do banco da WuzAPI usando as mesmas credenciais do sistema
            // Credentials must be provided via environment variables
            $dbHost = $_ENV['DB_HOST'] ?? null;
            $dbPort = $_ENV['DB_PORT'] ?? '5432';
            $dbName = $_ENV['DB_NAME'] ?? null;
            $dbUser = $_ENV['DB_USER'] ?? null;
            $dbPassword = $_ENV['DB_PASSWORD'] ?? null;
            
            if (!$dbHost || !$dbName || !$dbUser || !$dbPassword) {
                throw new Exception('Database credentials not configured. Please set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD environment variables.');
            }
            
            $pdo = new \PDO(
                "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
                $dbUser,
                $dbPassword,
                [
                    \PDO::ATTR_TIMEOUT => 120,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$instanceId]);
            
            if ($result) {
                error_log("WuzAPIManager::deleteInstance - Instância {$instanceId} deletada do banco WuzAPI");
                return [
                    'success' => true,
                    'message' => 'Instância deletada com sucesso'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Falha ao deletar instância do banco WuzAPI'
            ];
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::deleteInstance - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar instância: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer chamada para API WuzAPI
     */
    private function makeApiCall($method, $endpoint, $data = null, $customToken = null) 
    {
        try {
            $url = rtrim($this->wuzapiUrl, '/') . $endpoint;
            
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            $token = $customToken ?? $this->apiKey;
            if ($token) {
                $headers[] = 'token: ' . $token;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL Error: {$error}");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            throw new Exception("HTTP Error: {$httpCode} - {$response}");
            
        } catch (Exception $e) {
            error_log("WuzAPIManager::makeApiCall - Error: " . $e->getMessage());
            throw $e;
        }
    }
}
