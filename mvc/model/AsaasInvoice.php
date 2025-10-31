<?php
/**
 * Model for Asaas Invoice Management
 * Handles invoice operations for each establishment/filial
 */

use System\Database;

class AsaasInvoice {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
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
                        t.asaas_environment
                      FROM filiais f
                      JOIN tenants t ON f.tenant_id = t.id
                      WHERE f.id = $1 AND f.tenant_id = $2";
            
            $result = pg_query_params($this->conn, $query, [$filial_id, $tenant_id]);
            
            if ($result && pg_num_rows($result) > 0) {
                $config = pg_fetch_assoc($result);
                
                // If filial doesn't have its own API key, inherit from tenant
                if (empty($config['asaas_api_key'])) {
                    $tenant_query = "SELECT asaas_api_key, asaas_customer_id FROM tenants WHERE id = $1";
                    $tenant_result = pg_query_params($this->conn, $tenant_query, [$tenant_id]);
                    
                    if ($tenant_result && pg_num_rows($tenant_result) > 0) {
                        $tenant_config = pg_fetch_assoc($tenant_result);
                        $config['asaas_api_key'] = $tenant_config['asaas_api_key'];
                        $config['asaas_customer_id'] = $tenant_config['asaas_customer_id'];
                    }
                }
                
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
                      WHERE id = $1";
            
            $result = pg_query_params($this->conn, $query, [$tenant_id]);
            
            if ($result && pg_num_rows($result) > 0) {
                return pg_fetch_assoc($result);
            }
        }
        
        return null;
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
        
        $data = [
            'customer' => $config['asaas_customer_id'],
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
                  VALUES ($1, $2, $3, $4, $5, $6, $7, CURRENT_TIMESTAMP) 
                  ON CONFLICT (asaas_invoice_id) DO UPDATE SET
                  asaas_response = EXCLUDED.asaas_response,
                  updated_at = CURRENT_TIMESTAMP";
        
        $result = pg_query_params($this->conn, $query, [
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
                  SET status = $2, asaas_response = $3, updated_at = CURRENT_TIMESTAMP";
        
        if ($status === 'issued') {
            $query .= ", data_emissao = CURRENT_TIMESTAMP";
        } elseif ($status === 'cancelled') {
            $query .= ", data_cancelamento = CURRENT_TIMESTAMP";
        }
        
        $query .= " WHERE asaas_invoice_id = $1";
        
        $result = pg_query_params($this->conn, $query, [
            $asaas_invoice_id,
            $status,
            $asaas_data ? json_encode($asaas_data) : null
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
                  WHERE nf.tenant_id = $1";
        
        $params = [$tenant_id];
        
        if ($filial_id) {
            $query .= " AND nf.filial_id = $" . (count($params) + 1);
            $params[] = $filial_id;
        }
        
        $query .= " ORDER BY nf.created_at DESC LIMIT $" . (count($params) + 1) . " OFFSET $" . (count($params) + 2);
        $params[] = $limit;
        $params[] = $offset;
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Make request to Asaas API
     */
    private function makeAsaasRequest($method, $url, $data = [], $api_key) {
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
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error
            ];
        }
        
        $decoded_response = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'data' => $decoded_response,
                'http_code' => $http_code
            ];
        } else {
            return [
                'success' => false,
                'error' => $decoded_response['errors'][0]['description'] ?? 'Unknown error',
                'http_code' => $http_code,
                'response' => $decoded_response
            ];
        }
    }
}
