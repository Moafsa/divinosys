<?php
/**
 * Controller for Fiscal Information Management
 * Handles fiscal information for establishments and filiais
 * 
 * IMPORTANTE: Este controller assume que Database.php e AsaasFiscalInfo.php
 * já foram carregados pelo arquivo AJAX que o chama.
 */

class FiscalInfoController {
    private $asaasFiscalInfo;
    private $conn;
    
    public function __construct() {
        try {
            // Verificar se Database está disponível ANTES de instanciar AsaasFiscalInfo
            if (!class_exists('System\\Database')) {
                throw new \Exception('System\\Database class not found. Database.php must be loaded before FiscalInfoController.');
            }
            
            // Verificar se AsaasFiscalInfo está disponível
            if (!class_exists('AsaasFiscalInfo')) {
                throw new \Exception('AsaasFiscalInfo class not found. AsaasFiscalInfo.php must be loaded before FiscalInfoController.');
            }
            
            // Agora instanciar (o construtor do AsaasFiscalInfo precisa do Database)
            $this->asaasFiscalInfo = new AsaasFiscalInfo();
            $this->conn = \System\Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log('FiscalInfoController constructor error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        } catch (\Error $e) {
            error_log('FiscalInfoController constructor fatal error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create or update fiscal information
     */
    public function createOrUpdateFiscalInfo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required_fields = ['tenant_id', 'cnpj', 'razao_social', 'endereco', 'municipal_service_id', 'municipal_service_code'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Validate CNPJ
        if (!$this->asaasFiscalInfo->validateCNPJ($data['cnpj'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid CNPJ format']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        
        // Prepare fiscal data
        $fiscal_data = [
            'cnpj' => $data['cnpj'],
            'razao_social' => $data['razao_social'],
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'inscricao_estadual' => $data['inscricao_estadual'] ?? null,
            'inscricao_municipal' => $data['inscricao_municipal'] ?? null,
            'endereco' => $data['endereco'],
            'contato' => $data['contato'] ?? [],
            'regime_tributario' => $data['regime_tributario'] ?? null,
            'optante_simples_nacional' => $data['optante_simples_nacional'] ?? false,
            'municipal_service_id' => $data['municipal_service_id'],
            'municipal_service_code' => $data['municipal_service_code'],
            'municipal_service_name' => $data['municipal_service_name'] ?? null,
            'nbs_codes' => $data['nbs_codes'] ?? []
        ];
        
        $result = $this->asaasFiscalInfo->createOrUpdateFiscalInfo($tenant_id, $filial_id, $fiscal_data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Get fiscal information
     */
    public function getFiscalInfo() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $source = $_GET['source'] ?? 'db'; // 'db' or 'asaas'
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        if ($source === 'asaas') {
            $result = $this->asaasFiscalInfo->getFiscalInfo($tenant_id, $filial_id);
        } else {
            $fiscal_info = $this->asaasFiscalInfo->getFiscalInfoFromDb($tenant_id, $filial_id);
            
            if ($fiscal_info) {
                $result = [
                    'success' => true,
                    'data' => $fiscal_info
                ];
            } else {
                $result = [
                    'success' => false,
                    'error' => 'Fiscal information not found'
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * List municipal options
     */
    public function listMunicipalOptions() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        $result = $this->asaasFiscalInfo->listMunicipalOptions($tenant_id, $filial_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * List municipal services
     */
    public function listMunicipalServices() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $municipality_id = $_GET['municipality_id'] ?? null;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        $result = $this->asaasFiscalInfo->listMunicipalServices($tenant_id, $filial_id, $municipality_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * List NBS codes
     */
    public function listNBSCodes() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        $result = $this->asaasFiscalInfo->listNBSCodes($tenant_id, $filial_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Configure invoice issuer portal
     */
    public function configureIssuerPortal() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tenant_id']) || !isset($data['municipal_service_id']) || !isset($data['municipal_service_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id, municipal_service_id and municipal_service_code are required']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        
        $portal_data = [
            'municipal_service_id' => $data['municipal_service_id'],
            'municipal_service_code' => $data['municipal_service_code'],
            'municipal_service_name' => $data['municipal_service_name'] ?? null,
            'nbs_codes' => $data['nbs_codes'] ?? []
        ];
        
        $result = $this->asaasFiscalInfo->configureIssuerPortal($tenant_id, $filial_id, $portal_data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Validate CNPJ
     */
    public function validateCNPJ() {
        $cnpj = $_GET['cnpj'] ?? null;
        
        if (!$cnpj) {
            http_response_code(400);
            echo json_encode(['error' => 'cnpj is required']);
            return;
        }
        
        $is_valid = $this->asaasFiscalInfo->validateCNPJ($cnpj);
        
        echo json_encode([
            'success' => true,
            'valid' => $is_valid,
            'cnpj' => $cnpj
        ]);
    }
    
    /**
     * Get fiscal information statistics
     */
    public function getFiscalStats() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_fiscal_info,
                    COUNT(CASE WHEN asaas_sync_status = 'synced' THEN 1 END) as synced,
                    COUNT(CASE WHEN asaas_sync_status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN asaas_sync_status = 'error' THEN 1 END) as error,
                    COUNT(CASE WHEN active = true THEN 1 END) as active
                  FROM informacoes_fiscais 
                  WHERE tenant_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$tenant_id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($stats) {
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to get statistics'
            ]);
        }
    }
    
    /**
     * Deactivate fiscal information
     */
    public function deactivateFiscalInfo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tenant_id']) || !isset($data['fiscal_info_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and fiscal_info_id are required']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $fiscal_info_id = $data['fiscal_info_id'];
        
        $query = "UPDATE informacoes_fiscais 
                  SET active = false, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = ? AND tenant_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([$fiscal_info_id, $tenant_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Fiscal information deactivated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to deactivate fiscal information'
            ]);
        }
    }
}
