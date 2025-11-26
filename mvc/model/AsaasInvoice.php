<?php
/**
 * Model for Asaas Invoice Management
 * Handles invoice operations for each establishment/filial
 */

class AsaasInvoice {
    private $conn;
    
    public function __construct() {
        try {
            // Usar namespace completo diretamente, sem use statement
            if (!class_exists('System\\Database')) {
                throw new \Exception('System\\Database class not found. Make sure Database.php is loaded.');
            }
            $this->conn = \System\Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log('AsaasInvoice constructor error: ' . $e->getMessage());
            error_log('AsaasInvoice constructor stack trace: ' . $e->getTraceAsString());
            throw new \Exception('Failed to initialize database connection: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Asaas configuration for establishment/filial
     */
    public function getAsaasConfig($tenant_id, $filial_id = null) {
        if ($filial_id) {
            // Get filial-specific config first
            $query = "SELECT 
                        f.asaas_api_key, 
                        f.asaas_customer_id, 
                        f.asaas_enabled,
                        f.asaas_fiscal_info,
                        f.asaas_municipal_service_id,
                        f.asaas_municipal_service_code,
                        t.asaas_api_url,
                        t.asaas_environment,
                        t.asaas_api_key as tenant_asaas_api_key,
                        t.asaas_customer_id as tenant_asaas_customer_id
                      FROM filiais f
                      JOIN tenants t ON f.tenant_id = t.id
                      WHERE f.id = ? AND f.tenant_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$filial_id, $tenant_id]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($config) {
                // If filial doesn't have its own API key, inherit from tenant
                if (empty($config['asaas_api_key'])) {
                    $config['asaas_api_key'] = $config['tenant_asaas_api_key'] ?? null;
                    $config['asaas_customer_id'] = $config['tenant_asaas_customer_id'] ?? $config['asaas_customer_id'];
                }
                
                // Ensure API URL and environment come from tenant (they're always stored there)
                if (empty($config['asaas_api_url'])) {
                    // Fallback: construct URL based on environment
                    $environment = $config['asaas_environment'] ?? 'sandbox';
                    $config['asaas_api_url'] = ($environment === 'production') 
                        ? 'https://www.asaas.com/api/v3' 
                        : 'https://sandbox.asaas.com/api/v3';
                }
                
                // Remove tenant_ prefixed fields from response
                unset($config['tenant_asaas_api_key']);
                unset($config['tenant_asaas_customer_id']);
                
                return $config;
            }
        } else {
            // Get tenant config
            $query = "SELECT 
                        asaas_api_key, 
                        asaas_customer_id, 
                        asaas_enabled,
                        asaas_fiscal_info,
                        asaas_municipal_service_id,
                        asaas_municipal_service_code,
                        asaas_api_url,
                        asaas_environment
                      FROM tenants 
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$tenant_id]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($config) {
                return $config;
            }
        }
        
        // Return empty config with defaults instead of null
        return [
            'asaas_api_key' => null,
            'asaas_customer_id' => null,
            'asaas_enabled' => false,
            'asaas_fiscal_info' => null,
            'asaas_municipal_service_id' => null,
            'asaas_municipal_service_code' => null,
            'asaas_api_url' => 'https://sandbox.asaas.com/api/v3',
            'asaas_environment' => 'sandbox'
        ];
    }
    
    /**
     * Create or find customer in Asaas automatically
     * Similar to what's done in plan onboarding
     */
    private function createOrFindCustomer($tenant_id, $filial_id, $config) {
        // If customer_id already exists, return it
        if (!empty($config['asaas_customer_id'])) {
            return $config['asaas_customer_id'];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        // Get tenant/filial data
        if ($filial_id) {
            $query = "SELECT f.*, t.nome as tenant_nome, t.email as tenant_email, t.cnpj as tenant_cnpj, t.telefone as tenant_telefone
                      FROM filiais f
                      JOIN tenants t ON f.tenant_id = t.id
                      WHERE f.id = ? AND f.tenant_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$filial_id, $tenant_id]);
            $entity = $stmt->fetch(\PDO::FETCH_ASSOC);
            $entityName = $entity['nome'] ?? $entity['tenant_nome'];
            $entityEmail = $entity['email'] ?? $entity['tenant_email'];
            $entityCnpj = $entity['cnpj'] ?? $entity['tenant_cnpj'];
            $entityPhone = $entity['telefone'] ?? $entity['tenant_telefone'];
        } else {
            $query = "SELECT * FROM tenants WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$tenant_id]);
            $entity = $stmt->fetch(\PDO::FETCH_ASSOC);
            $entityName = $entity['nome'] ?? '';
            $entityEmail = $entity['email'] ?? '';
            $entityCnpj = $entity['cnpj'] ?? '';
            $entityPhone = $entity['telefone'] ?? '';
        }
        
        if (!$entity) {
            return null;
        }
        
        // Try to find existing customer by CNPJ
        $customerId = null;
        if (!empty($entityCnpj)) {
            $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
            $searchUrl = $api_url . '/customers?cpfCnpj=' . urlencode($cnpjClean);
            
            $searchResult = $this->makeAsaasRequest('GET', $searchUrl, [], $api_key);
            if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                $customerId = $searchResult['data']['data'][0]['id'];
            }
        }
        
        // If not found, create new customer
        if (!$customerId) {
            $customerData = [
                'name' => $entityName,
                'email' => $entityEmail,
                'phone' => preg_replace('/[^0-9]/', '', $entityPhone),
                'externalReference' => ($filial_id ? 'filial_' : 'tenant_') . ($filial_id ?? $tenant_id)
            ];
            
            if (!empty($entityCnpj)) {
                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $entityCnpj);
            }
            
            $createResult = $this->makeAsaasRequest('POST', $api_url . '/customers', $customerData, $api_key);
            
            if ($createResult['success'] && isset($createResult['data']['id'])) {
                $customerId = $createResult['data']['id'];
            } else {
                // If error is "already exists", try to find again
                $errorMsg = $createResult['error'] ?? '';
                if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false) {
                    if (!empty($entityCnpj)) {
                        $cnpjClean = preg_replace('/[^0-9]/', '', $entityCnpj);
                        $searchUrl = $api_url . '/customers?cpfCnpj=' . urlencode($cnpjClean);
                        $searchResult = $this->makeAsaasRequest('GET', $searchUrl, [], $api_key);
                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                            $customerId = $searchResult['data']['data'][0]['id'];
                        }
                    }
                }
            }
        }
        
        // Save customer_id to database
        if ($customerId) {
            if ($filial_id) {
                $updateQuery = "UPDATE filiais SET asaas_customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?";
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->execute([$customerId, $filial_id, $tenant_id]);
            } else {
                $updateQuery = "UPDATE tenants SET asaas_customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $this->conn->prepare($updateQuery);
                $stmt->execute([$customerId, $tenant_id]);
            }
        }
        
        return $customerId;
    }
    
    /**
     * Schedule invoice in Asaas
     */
    public function scheduleInvoice($tenant_id, $filial_id, $invoice_data) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        // Create or find customer automatically if not set
        $customerId = $this->createOrFindCustomer($tenant_id, $filial_id, $config);
        
        if (!$customerId) {
            return [
                'success' => false,
                'error' => 'Não foi possível criar ou encontrar o cliente no Asaas. Verifique os dados do estabelecimento (nome, email, CNPJ).'
            ];
        }
        
        $data = [
            'customer' => $customerId,
            'payment' => $invoice_data['payment_id'] ?? null,
            'installment' => $invoice_data['installment_id'] ?? null,
            'municipalServiceId' => $config['asaas_municipal_service_id'],
            'municipalServiceCode' => $config['asaas_municipal_service_code'],
            'description' => $invoice_data['description'] ?? 'Nota Fiscal - Pedido #' . $invoice_data['pedido_id'],
            'externalReference' => 'invoice_' . $invoice_data['pedido_id'] . '_' . time(),
            'taxes' => [
                'retainIss' => $invoice_data['retain_iss'] ?? true,
                'iss' => $invoice_data['iss_value'] ?? 0,
                'cofins' => $invoice_data['cofins_value'] ?? 0,
                'csll' => $invoice_data['csll_value'] ?? 0,
                'inss' => $invoice_data['inss_value'] ?? 0,
                'ir' => $invoice_data['ir_value'] ?? 0,
                'pis' => $invoice_data['pis_value'] ?? 0
            ]
        ];
        
        $response = $this->makeAsaasRequest('POST', $api_url . '/invoices', $data, $api_key);
        
        if ($response['success']) {
            // Save invoice record
            $this->saveInvoiceRecord($tenant_id, $filial_id, $response['data'], $invoice_data);
        }
        
        return $response;
    }
    
    /**
     * Issue invoice in Asaas
     */
    public function issueInvoice($tenant_id, $filial_id, $asaas_invoice_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        $response = $this->makeAsaasRequest('POST', $api_url . '/invoices/' . $asaas_invoice_id . '/issue', [], $api_key);
        
        if ($response['success']) {
            // Update invoice status
            $this->updateInvoiceStatus($asaas_invoice_id, 'issued', $response['data']);
        }
        
        return $response;
    }
    
    /**
     * Cancel invoice in Asaas
     */
    public function cancelInvoice($tenant_id, $filial_id, $asaas_invoice_id, $reason = null) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        $data = [];
        if ($reason) {
            $data['reason'] = $reason;
        }
        
        $response = $this->makeAsaasRequest('POST', $api_url . '/invoices/' . $asaas_invoice_id . '/cancel', $data, $api_key);
        
        if ($response['success']) {
            // Update invoice status
            $this->updateInvoiceStatus($asaas_invoice_id, 'cancelled', $response['data']);
        }
        
        return $response;
    }
    
    /**
     * List invoices from Asaas
     */
    public function listInvoices($tenant_id, $filial_id, $filters = []) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        $query_params = [];
        if (isset($filters['customer'])) {
            $query_params[] = 'customer=' . urlencode($filters['customer']);
        }
        if (isset($filters['status'])) {
            $query_params[] = 'status=' . urlencode($filters['status']);
        }
        if (isset($filters['limit'])) {
            $query_params[] = 'limit=' . $filters['limit'];
        }
        if (isset($filters['offset'])) {
            $query_params[] = 'offset=' . $filters['offset'];
        }
        
        $url = $api_url . '/invoices';
        if (!empty($query_params)) {
            $url .= '?' . implode('&', $query_params);
        }
        
        return $this->makeAsaasRequest('GET', $url, [], $api_key);
    }
    
    /**
     * Get single invoice from Asaas
     */
    public function getInvoice($tenant_id, $filial_id, $asaas_invoice_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        return $this->makeAsaasRequest('GET', $api_url . '/invoices/' . $asaas_invoice_id, [], $api_key);
    }
    
    /**
     * Get invoice PDF URL from Asaas
     */
    public function getInvoicePdfUrl($tenant_id, $filial_id, $asaas_invoice_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        // Get invoice details first
        $invoiceResponse = $this->makeAsaasRequest('GET', $api_url . '/invoices/' . $asaas_invoice_id, [], $api_key);
        
        if (!$invoiceResponse['success']) {
            return $invoiceResponse;
        }
        
        $invoiceData = $invoiceResponse['data'];
        
        // Check if PDF URL is available in the response
        if (isset($invoiceData['pdfUrl']) && !empty($invoiceData['pdfUrl'])) {
            return [
                'success' => true,
                'pdf_url' => $invoiceData['pdfUrl'],
                'invoice_data' => $invoiceData
            ];
        }
        
        // If PDF URL is not directly available, try to construct it
        // Asaas typically provides PDFs at: /invoices/{id}/pdf
        $pdfUrl = $api_url . '/invoices/' . $asaas_invoice_id . '/pdf';
        
        return [
            'success' => true,
            'pdf_url' => $pdfUrl,
            'invoice_data' => $invoiceData
        ];
    }
    
    /**
     * Download invoice PDF content
     */
    public function downloadInvoicePdf($tenant_id, $filial_id, $asaas_invoice_id) {
        $pdfUrlResult = $this->getInvoicePdfUrl($tenant_id, $filial_id, $asaas_invoice_id);
        
        if (!$pdfUrlResult['success']) {
            return $pdfUrlResult;
        }
        
        $pdfUrl = $pdfUrlResult['pdf_url'];
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        $api_key = $config['asaas_api_key'];
        
        // Make request to download PDF
        $headers = [
            'access_token: ' . $api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pdfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $pdfContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP Error: ' . $httpCode,
                'pdf_url' => $pdfUrl
            ];
        }
        
        return [
            'success' => true,
            'pdf_content' => $pdfContent,
            'pdf_url' => $pdfUrl,
            'content_type' => 'application/pdf'
        ];
    }
    
    /**
     * Save invoice record to database
     */
    private function saveInvoiceRecord($tenant_id, $filial_id, $asaas_data, $invoice_data) {
        $query = "INSERT INTO notas_fiscais 
                  (tenant_id, filial_id, asaas_invoice_id, asaas_payment_id, valor_total, 
                   asaas_response, observacoes, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) 
                  ON CONFLICT (asaas_invoice_id) DO UPDATE SET
                  asaas_response = EXCLUDED.asaas_response,
                  updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            $tenant_id,
            $filial_id,
            $asaas_data['id'],
            $asaas_data['payment'] ?? null,
            $asaas_data['value'] ?? $invoice_data['valor_total'],
            json_encode($asaas_data),
            $invoice_data['observacoes'] ?? null
        ]);
        
        return $result !== false;
    }
    
    /**
     * Update invoice status
     */
    private function updateInvoiceStatus($asaas_invoice_id, $status, $asaas_data = null) {
        $query = "UPDATE notas_fiscais 
                  SET status = ?, asaas_response = ?, updated_at = CURRENT_TIMESTAMP";
        
        if ($status === 'issued') {
            $query .= ", data_emissao = CURRENT_TIMESTAMP";
        } elseif ($status === 'cancelled') {
            $query .= ", data_cancelamento = CURRENT_TIMESTAMP";
        }
        
        $query .= " WHERE asaas_invoice_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            $status,
            $asaas_data ? json_encode($asaas_data) : null,
            $asaas_invoice_id
        ]);
        
        return $result !== false;
    }
    
    /**
     * Get invoices from database
     */
    public function getInvoicesFromDb($tenant_id, $filial_id = null, $limit = 50, $offset = 0) {
        $query = "SELECT nf.*, t.nome as tenant_nome, f.nome as filial_nome
                  FROM notas_fiscais nf
                  JOIN tenants t ON nf.tenant_id = t.id
                  LEFT JOIN filiais f ON nf.filial_id = f.id
                  WHERE nf.tenant_id = ?";
        
        $params = [$tenant_id];
        
        if ($filial_id) {
            $query .= " AND nf.filial_id = ?";
            $params[] = $filial_id;
        }
        
        $query .= " ORDER BY nf.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $result ?: [];
    }
    
    /**
     * Make request to Asaas API
     */
    public function makeAsaasRequest($method, $url, $data = [], $api_key) {
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $api_key
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Asaas CURL Error: $error");
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error
            ];
        }
        
        // Log raw response for debugging
        error_log("Asaas API Response (HTTP $http_code): " . substr($response, 0, 500));
        
        $decoded_response = json_decode($response, true);
        
        // If JSON decode failed, log the raw response
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("Asaas JSON decode error: " . json_last_error_msg());
            error_log("Raw response: " . $response);
            return [
                'success' => false,
                'error' => 'Invalid JSON response from Asaas: ' . json_last_error_msg(),
                'http_code' => $http_code,
                'raw_response' => $response
            ];
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'data' => $decoded_response,
                'http_code' => $http_code
            ];
        } else {
            // Try to extract error message from different possible formats
            $errorMsg = 'Unknown error';
            
            if (isset($decoded_response['errors']) && is_array($decoded_response['errors']) && count($decoded_response['errors']) > 0) {
                $errorMsg = $decoded_response['errors'][0]['description'] ?? $decoded_response['errors'][0]['code'] ?? 'Unknown error';
            } elseif (isset($decoded_response['error'])) {
                $errorMsg = $decoded_response['error'];
            } elseif (isset($decoded_response['message'])) {
                $errorMsg = $decoded_response['message'];
            } elseif (is_string($decoded_response)) {
                $errorMsg = $decoded_response;
            }
            
            error_log("Asaas API Error (HTTP $http_code): $errorMsg");
            error_log("Full error response: " . json_encode($decoded_response));
            
            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $http_code,
                'response' => $decoded_response
            ];
        }
    }
}
