<?php
/**
 * Controller for Invoice Management
 * Handles invoice operations for establishments and filiais
 */

require_once __DIR__ . '/../model/AsaasInvoice.php';
require_once __DIR__ . '/../model/AsaasFiscalInfo.php';

class InvoiceController {
    private $asaasInvoice;
    private $asaasFiscalInfo;
    
    public function __construct() {
        $this->asaasInvoice = new AsaasInvoice();
        $this->asaasFiscalInfo = new AsaasFiscalInfo();
    }
    
    /**
     * Schedule invoice for an order
     */
    public function scheduleInvoice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required_fields = ['tenant_id', 'pedido_id', 'valor_total'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        $pedido_id = $data['pedido_id'];
        $valor_total = $data['valor_total'];
        
        // Prepare invoice data
        $invoice_data = [
            'pedido_id' => $pedido_id,
            'valor_total' => $valor_total,
            'description' => $data['description'] ?? "Nota Fiscal - Pedido #{$pedido_id}",
            'retain_iss' => $data['retain_iss'] ?? true,
            'iss_value' => $data['iss_value'] ?? 0,
            'cofins_value' => $data['cofins_value'] ?? 0,
            'csll_value' => $data['csll_value'] ?? 0,
            'inss_value' => $data['inss_value'] ?? 0,
            'ir_value' => $data['ir_value'] ?? 0,
            'pis_value' => $data['pis_value'] ?? 0,
            'observacoes' => $data['observacoes'] ?? null
        ];
        
        $result = $this->asaasInvoice->scheduleInvoice($tenant_id, $filial_id, $invoice_data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Issue invoice
     */
    public function issueInvoice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tenant_id']) || !isset($data['asaas_invoice_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and asaas_invoice_id are required']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        $asaas_invoice_id = $data['asaas_invoice_id'];
        
        $result = $this->asaasInvoice->issueInvoice($tenant_id, $filial_id, $asaas_invoice_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Cancel invoice
     */
    public function cancelInvoice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tenant_id']) || !isset($data['asaas_invoice_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and asaas_invoice_id are required']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        $asaas_invoice_id = $data['asaas_invoice_id'];
        $reason = $data['reason'] ?? null;
        
        $result = $this->asaasInvoice->cancelInvoice($tenant_id, $filial_id, $asaas_invoice_id, $reason);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * List invoices
     */
    public function listInvoices() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        $source = $_GET['source'] ?? 'db'; // 'db' or 'asaas'
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        if ($source === 'asaas') {
            $filters = [
                'limit' => $limit,
                'offset' => $offset
            ];
            
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            $result = $this->asaasInvoice->listInvoices($tenant_id, $filial_id, $filters);
        } else {
            $result = $this->asaasInvoice->getInvoicesFromDb($tenant_id, $filial_id, $limit, $offset);
            $result = [
                'success' => true,
                'data' => $result
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Get single invoice
     */
    public function getInvoice() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $asaas_invoice_id = $_GET['asaas_invoice_id'] ?? null;
        $source = $_GET['source'] ?? 'db'; // 'db' or 'asaas'
        
        if (!$tenant_id || !$asaas_invoice_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and asaas_invoice_id are required']);
            return;
        }
        
        if ($source === 'asaas') {
            $result = $this->asaasInvoice->getInvoice($tenant_id, $filial_id, $asaas_invoice_id);
        } else {
            // Get from database
            $query = "SELECT nf.*, t.nome as tenant_nome, f.nome as filial_nome
                      FROM notas_fiscais nf
                      JOIN tenants t ON nf.tenant_id = t.id
                      LEFT JOIN filiais f ON nf.filial_id = f.id
                      WHERE nf.asaas_invoice_id = $1 AND nf.tenant_id = $2";
            
            $params = [$asaas_invoice_id, $tenant_id];
            
            if ($filial_id) {
                $query .= " AND nf.filial_id = $" . (count($params) + 1);
                $params[] = $filial_id;
            }
            
            $result = pg_query_params($this->conn, $query, $params);
            
            if ($result && pg_num_rows($result) > 0) {
                $invoice = pg_fetch_assoc($result);
                $result = [
                    'success' => true,
                    'data' => $invoice
                ];
            } else {
                $result = [
                    'success' => false,
                    'error' => 'Invoice not found'
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Get invoice statistics
     */
    public function getInvoiceStats() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_invoices,
                    COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as error,
                    SUM(CASE WHEN status = 'issued' THEN valor_total ELSE 0 END) as total_value_issued,
                    SUM(CASE WHEN status = 'pending' THEN valor_total ELSE 0 END) as total_value_pending
                  FROM notas_fiscais 
                  WHERE tenant_id = $1";
        
        $params = [$tenant_id];
        
        if ($filial_id) {
            $query .= " AND filial_id = $" . (count($params) + 1);
            $params[] = $filial_id;
        }
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result && pg_num_rows($result) > 0) {
            $stats = pg_fetch_assoc($result);
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
     * Create invoice from order
     */
    public function createInvoiceFromOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tenant_id']) || !isset($data['pedido_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and pedido_id are required']);
            return;
        }
        
        $tenant_id = $data['tenant_id'];
        $filial_id = $data['filial_id'] ?? null;
        $pedido_id = $data['pedido_id'];
        
        // Get order details
        $order_query = "SELECT p.*, t.nome as tenant_nome, f.nome as filial_nome
                        FROM pedido p
                        JOIN tenants t ON p.tenant_id = t.id
                        LEFT JOIN filiais f ON p.filial_id = f.id
                        WHERE p.idpedido = $1 AND p.tenant_id = $2";
        
        $params = [$pedido_id, $tenant_id];
        
        if ($filial_id) {
            $order_query .= " AND p.filial_id = $" . (count($params) + 1);
            $params[] = $filial_id;
        }
        
        $order_result = pg_query_params($this->conn, $order_query, $params);
        
        if (!$order_result || pg_num_rows($order_result) == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            return;
        }
        
        $order = pg_fetch_assoc($order_result);
        
        // Prepare invoice data
        $invoice_data = [
            'pedido_id' => $pedido_id,
            'valor_total' => $order['valor_total'],
            'description' => "Nota Fiscal - Pedido #{$pedido_id} - {$order['tenant_nome']}" . 
                           ($order['filial_nome'] ? " - {$order['filial_nome']}" : ''),
            'retain_iss' => $data['retain_iss'] ?? true,
            'observacoes' => $data['observacoes'] ?? null
        ];
        
        $result = $this->asaasInvoice->scheduleInvoice($tenant_id, $filial_id, $invoice_data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
