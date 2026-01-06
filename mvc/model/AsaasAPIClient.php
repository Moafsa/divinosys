<?php
/**
 * Centralized Asaas API Client
 * Handles all API requests to Asaas with proper timeout handling and error management
 */

class AsaasAPIClient {
    private $apiUrl;
    private $apiKey;
    private $timeout;
    private $connectTimeout;
    
    /**
     * Constructor
     * 
     * @param string $apiKey API key from Asaas
     * @param string $apiUrl API URL (defaults to sandbox)
     * @param int $timeout Request timeout in seconds (default: 10)
     * @param int $connectTimeout Connection timeout in seconds (default: 5)
     */
    public function __construct($apiKey, $apiUrl = 'https://sandbox.asaas.com/api/v3', $timeout = 10, $connectTimeout = 5) {
        $this->apiKey = $apiKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }
    
    /**
     * Make HTTP request to Asaas API
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., '/customers', '/payments')
     * @param array|null $data Request body data (for POST/PUT)
     * @return array Response array with 'success', 'data' or 'error', 'http_code'
     */
    public function request($method, $endpoint, $data = null) {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        $headers = [
            'access_token: ' . $this->apiKey,
            'Content-Type: application/json',
            'User-Agent: DivinoSYS/2.0'
        ];
        
        $ch = curl_init();
        
        // Basic cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Critical: Set timeouts to prevent system hangs
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        
        // Force IPv4 to avoid DNS resolution issues
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        // Don't wait for body on errors
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        
        // Method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
                
            case 'GET':
            default:
                // GET is default, no additional options needed
                break;
        }
        
        // Execute request with error handling
        $response = @curl_exec($ch);
        
        // Check if curl_exec returned false (error occurred)
        if ($response === false) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $errorMessage = $this->getCurlErrorMessage($curlErrno, $curlError);
            error_log("AsaasAPIClient::request - cURL Error ($curlErrno): $errorMessage");
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'http_code' => 0,
                'curl_errno' => $curlErrno
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        // Handle cURL errors (timeout, connection errors, etc.)
        if ($curlErrno !== 0) {
            $errorMessage = $this->getCurlErrorMessage($curlErrno, $curlError);
            error_log("AsaasAPIClient::request - cURL Error ($curlErrno): $errorMessage");
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'http_code' => 0,
                'curl_errno' => $curlErrno
            ];
        }
        
        // Decode JSON response
        $decodedResponse = json_decode($response, true);
        
        // Handle JSON decode errors
        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("AsaasAPIClient::request - JSON decode error: " . json_last_error_msg());
            error_log("AsaasAPIClient::request - Raw response: " . substr($response, 0, 500));
            
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'http_code' => $httpCode,
                'raw_response' => $response
            ];
        }
        
        // Handle HTTP response codes
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decodedResponse,
                'http_code' => $httpCode
            ];
        } else {
            // Extract error message from response
            $errorMsg = 'Unknown error';
            
            if (isset($decodedResponse['errors']) && is_array($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
                $errorMsg = $decodedResponse['errors'][0]['description'] ?? $decodedResponse['errors'][0]['code'] ?? 'API Error';
            } elseif (isset($decodedResponse['error'])) {
                $errorMsg = $decodedResponse['error'];
            } elseif (isset($decodedResponse['message'])) {
                $errorMsg = $decodedResponse['message'];
            }
            
            error_log("AsaasAPIClient::request - API Error (HTTP $httpCode): $errorMsg");
            
            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $httpCode,
                'response' => $decodedResponse
            ];
        }
    }
    
    /**
     * Get user-friendly error message from cURL error code
     * 
     * @param int $errno cURL error number
     * @param string $error cURL error message
     * @return string User-friendly error message
     */
    private function getCurlErrorMessage($errno, $error) {
        $errorMessages = [
            CURLE_OPERATION_TIMEOUTED => 'Timeout: A requisição excedeu o tempo limite. Verifique sua conexão com a internet.',
            CURLE_COULDNT_CONNECT => 'Erro de conexão: Não foi possível conectar ao servidor do Asaas.',
            CURLE_COULDNT_RESOLVE_HOST => 'Erro de DNS: Não foi possível resolver o endereço do servidor.',
            CURLE_SSL_CONNECT_ERROR => 'Erro SSL: Falha na conexão segura com o servidor.',
        ];
        
        if (isset($errorMessages[$errno])) {
            return $errorMessages[$errno];
        }
        
        return $error ?: 'Erro de conexão desconhecido (código: ' . $errno . ')';
    }
    
    /**
     * Test connection to Asaas API
     * Makes a simple GET request to /customers endpoint
     * 
     * @return array Response array
     */
    public function testConnection() {
        return $this->request('GET', '/customers?limit=1');
    }
    
    /**
     * Get customers
     * 
     * @param array $params Query parameters (limit, offset, etc.)
     * @return array Response array
     */
    public function getCustomers($params = []) {
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }
        return $this->request('GET', '/customers' . $queryString);
    }
    
    /**
     * Create customer
     * 
     * @param array $customerData Customer data
     * @return array Response array
     */
    public function createCustomer($customerData) {
        return $this->request('POST', '/customers', $customerData);
    }
    
    /**
     * Get customer by ID
     * 
     * @param string $customerId Customer ID
     * @return array Response array
     */
    public function getCustomer($customerId) {
        return $this->request('GET', '/customers/' . $customerId);
    }
    
    /**
     * Create payment
     * 
     * @param array $paymentData Payment data
     * @return array Response array
     */
    public function createPayment($paymentData) {
        return $this->request('POST', '/payments', $paymentData);
    }
    
    /**
     * Get payment by ID
     * 
     * @param string $paymentId Payment ID
     * @return array Response array
     */
    public function getPayment($paymentId) {
        return $this->request('GET', '/payments/' . $paymentId);
    }
    
    /**
     * Update payment
     * 
     * @param string $paymentId Payment ID
     * @param array $data Update data
     * @return array Response array
     */
    public function updatePayment($paymentId, $data) {
        return $this->request('PUT', '/payments/' . $paymentId, $data);
    }
    
    /**
     * Delete payment
     * 
     * @param string $paymentId Payment ID
     * @return array Response array
     */
    public function deletePayment($paymentId) {
        return $this->request('DELETE', '/payments/' . $paymentId);
    }
    
    /**
     * Get API URL
     * 
     * @return string API URL
     */
    public function getApiUrl() {
        return $this->apiUrl;
    }
}

