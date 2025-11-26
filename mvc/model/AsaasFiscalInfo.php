<?php
/**
 * Model for Asaas Fiscal Information Management
 * Handles fiscal information for each establishment/filial
 */

class AsaasFiscalInfo {
    private $conn;
    
    public function __construct() {
        try {
            // Usar namespace completo diretamente, sem use statement
            if (!class_exists('System\\Database')) {
                throw new \Exception('System\\Database class not found. Make sure Database.php is loaded.');
            }
            $this->conn = \System\Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log('AsaasFiscalInfo constructor error: ' . $e->getMessage());
            error_log('AsaasFiscalInfo constructor stack trace: ' . $e->getTraceAsString());
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
                        t.asaas_environment
                      FROM filiais f
                      JOIN tenants t ON f.tenant_id = t.id
                      WHERE f.id = ? AND f.tenant_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$filial_id, $tenant_id]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($config) {
                // If filial doesn't have its own API key, inherit from tenant
                if (empty($config['asaas_api_key'])) {
                    $tenant_query = "SELECT asaas_api_key, asaas_customer_id FROM tenants WHERE id = ?";
                    $tenant_stmt = $this->conn->prepare($tenant_query);
                    $tenant_stmt->execute([$tenant_id]);
                    $tenant_config = $tenant_stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($tenant_config) {
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
     * Create or update fiscal information in Asaas
     */
    public function createOrUpdateFiscalInfo($tenant_id, $filial_id, $fiscal_data) {
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
            'cnpj' => $fiscal_data['cnpj'],
            'municipalServiceId' => $fiscal_data['municipal_service_id'],
            'municipalServiceCode' => $fiscal_data['municipal_service_code'],
            'municipalServiceName' => $fiscal_data['municipal_service_name'],
            'nbsCodes' => $fiscal_data['nbs_codes'] ?? []
        ];
        
        $response = $this->makeAsaasRequest('POST', $api_url . '/fiscalInfo', $data, $api_key);
        
        if ($response['success']) {
            // Save fiscal information to database
            $this->saveFiscalInfoToDb($tenant_id, $filial_id, $fiscal_data, $response['data']);
        }
        
        return $response;
    }
    
    /**
     * Get fiscal information from Asaas
     */
    public function getFiscalInfo($tenant_id, $filial_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        return $this->makeAsaasRequest('GET', $api_url . '/fiscalInfo', [], $api_key);
    }
    
    /**
     * List municipal configurations
     */
    public function listMunicipalOptions($tenant_id, $filial_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        return $this->makeAsaasRequest('GET', $api_url . '/fiscalInfo/municipalOptions', [], $api_key);
    }
    
    /**
     * List municipal services
     */
    public function listMunicipalServices($tenant_id, $filial_id, $municipality_id = null) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        $url = $api_url . '/fiscalInfo/municipalServices';
        if ($municipality_id) {
            $url .= '?municipalityId=' . urlencode($municipality_id);
        }
        
        return $this->makeAsaasRequest('GET', $url, [], $api_key);
    }
    
    /**
     * List NBS codes
     */
    public function listNBSCodes($tenant_id, $filial_id) {
        $config = $this->getAsaasConfig($tenant_id, $filial_id);
        
        if (!$config || !$config['asaas_enabled'] || empty($config['asaas_api_key'])) {
            return [
                'success' => false,
                'error' => 'Asaas integration not configured for this establishment/filial'
            ];
        }
        
        $api_url = $config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $config['asaas_api_key'];
        
        return $this->makeAsaasRequest('GET', $api_url . '/fiscalInfo/nbsCodes', [], $api_key);
    }
    
    /**
     * Configure invoice issuer portal
     */
    public function configureIssuerPortal($tenant_id, $filial_id, $portal_data) {
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
            'municipalServiceId' => $portal_data['municipal_service_id'],
            'municipalServiceCode' => $portal_data['municipal_service_code'],
            'municipalServiceName' => $portal_data['municipal_service_name'],
            'nbsCodes' => $portal_data['nbs_codes'] ?? []
        ];
        
        return $this->makeAsaasRequest('POST', $api_url . '/fiscalInfo/issuerPortal', $data, $api_key);
    }
    
    /**
     * Save fiscal information to database
     */
    private function saveFiscalInfoToDb($tenant_id, $filial_id, $fiscal_data, $asaas_response) {
        $query = "INSERT INTO informacoes_fiscais 
                  (tenant_id, filial_id, cnpj, razao_social, nome_fantasia, 
                   inscricao_estadual, inscricao_municipal, endereco, contato,
                   regime_tributario, optante_simples_nacional, municipal_service_id,
                   municipal_service_code, municipal_service_name, nbs_codes,
                   asaas_sync_status, asaas_response, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?, CURRENT_TIMESTAMP)
                  ON CONFLICT (tenant_id, filial_id, cnpj) DO UPDATE SET
                  razao_social = EXCLUDED.razao_social,
                  nome_fantasia = EXCLUDED.nome_fantasia,
                  inscricao_estadual = EXCLUDED.inscricao_estadual,
                  inscricao_municipal = EXCLUDED.inscricao_municipal,
                  endereco = EXCLUDED.endereco,
                  contato = EXCLUDED.contato,
                  regime_tributario = EXCLUDED.regime_tributario,
                  optante_simples_nacional = EXCLUDED.optante_simples_nacional,
                  municipal_service_id = EXCLUDED.municipal_service_id,
                  municipal_service_code = EXCLUDED.municipal_service_code,
                  municipal_service_name = EXCLUDED.municipal_service_name,
                  nbs_codes = EXCLUDED.nbs_codes,
                  asaas_sync_status = 'synced',
                  asaas_response = EXCLUDED.asaas_response,
                  updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            $tenant_id,
            $filial_id,
            $fiscal_data['cnpj'],
            $fiscal_data['razao_social'],
            $fiscal_data['nome_fantasia'] ?? null,
            $fiscal_data['inscricao_estadual'] ?? null,
            $fiscal_data['inscricao_municipal'] ?? null,
            json_encode($fiscal_data['endereco']),
            json_encode($fiscal_data['contato'] ?? []),
            $fiscal_data['regime_tributario'] ?? null,
            $fiscal_data['optante_simples_nacional'] ?? false,
            $fiscal_data['municipal_service_id'],
            $fiscal_data['municipal_service_code'],
            $fiscal_data['municipal_service_name'],
            json_encode($fiscal_data['nbs_codes'] ?? []),
            json_encode($asaas_response)
        ]);
        
        return $result !== false;
    }
    
    /**
     * Get fiscal information from database
     */
    public function getFiscalInfoFromDb($tenant_id, $filial_id = null) {
        $query = "SELECT * FROM informacoes_fiscais WHERE tenant_id = ?";
        
        $params = [$tenant_id];
        
        if ($filial_id) {
            $query .= " AND filial_id = ?";
            $params[] = $filial_id;
        } else {
            $query .= " AND filial_id IS NULL";
        }
        
        $query .= " AND active = true ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Validate CNPJ
     */
    public function validateCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Validate CNPJ algorithm
        $sum = 0;
        $weight = 5;
        
        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weight;
            $weight = ($weight == 2) ? 9 : $weight - 1;
        }
        
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;
        
        if ($cnpj[12] != $digit1) {
            return false;
        }
        
        $sum = 0;
        $weight = 6;
        
        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weight;
            $weight = ($weight == 2) ? 9 : $weight - 1;
        }
        
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;
        
        return $cnpj[13] == $digit2;
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
