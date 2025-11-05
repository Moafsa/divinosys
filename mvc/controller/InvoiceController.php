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
    private $conn;
    
    public function __construct() {
        $this->asaasInvoice = new AsaasInvoice();
        $this->asaasFiscalInfo = new AsaasFiscalInfo();
        $this->conn = \System\Database::getInstance()->getConnection();
    }
    
    /**
     * List invoices
     */
    public function listInvoices() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        if (!$tenant_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id is required']);
            return;
        }
        
        // Get invoices from database
        $invoices = $this->asaasInvoice->getInvoicesFromDb($tenant_id, $filial_id, $limit, $offset);
        
        // Filter by status if provided
        if ($status) {
            $invoices = array_filter($invoices, function($invoice) use ($status) {
                return $invoice['status'] === $status;
            });
            $invoices = array_values($invoices); // Re-index array
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $invoices,
            'count' => count($invoices)
        ]);
    }
    
    /**
     * Get single invoice
     */
    public function getInvoice() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $invoice_id = $_GET['invoice_id'] ?? null;
        
        if (!$tenant_id || !$invoice_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and invoice_id are required']);
            return;
        }
        
        $result = $this->asaasInvoice->getInvoice($tenant_id, $filial_id, $invoice_id);
        
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
        $reason = $data['reason'] ?? 'Cancelamento solicitado pelo cliente';
        
        $result = $this->asaasInvoice->cancelInvoice($tenant_id, $filial_id, $asaas_invoice_id, $reason);
        
        header('Content-Type: application/json');
        echo json_encode($result);
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
        $query = "SELECT * FROM pedido WHERE idpedido = $1 AND tenant_id = $2";
        $result = pg_query_params($this->conn, $query, [$pedido_id, $tenant_id]);
        
        if (!$result || pg_num_rows($result) == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            return;
        }
        
        $pedido = pg_fetch_assoc($result);
        
        // Check if invoice already exists for this order
        $check_query = "SELECT * FROM notas_fiscais WHERE pedido_id = $1 AND tenant_id = $2";
        $check_result = pg_query_params($this->conn, $check_query, [$pedido_id, $tenant_id]);
        
        if ($check_result && pg_num_rows($check_result) > 0) {
            $existing_invoice = pg_fetch_assoc($check_result);
            echo json_encode([
                'success' => false,
                'error' => 'Invoice already exists for this order',
                'invoice_id' => $existing_invoice['asaas_invoice_id']
            ]);
            return;
        }
        
        // Prepare invoice data
        $invoice_data = [
            'pedido_id' => $pedido_id,
            'payment_id' => $data['payment_id'] ?? null,
            'description' => 'Nota Fiscal - Pedido #' . $pedido_id,
            'retain_iss' => $data['retain_iss'] ?? true,
            'iss_value' => $data['iss_value'] ?? 0,
            'cofins_value' => $data['cofins_value'] ?? 0,
            'csll_value' => $data['csll_value'] ?? 0,
            'inss_value' => $data['inss_value'] ?? 0,
            'ir_value' => $data['ir_value'] ?? 0,
            'pis_value' => $data['pis_value'] ?? 0,
            'valor_total' => $pedido['valortotal']
        ];
        
        $result = $this->asaasInvoice->scheduleInvoice($tenant_id, $filial_id, $invoice_data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Download invoice PDF
     */
    public function downloadPdf() {
        $tenant_id = $_GET['tenant_id'] ?? null;
        $filial_id = $_GET['filial_id'] ?? null;
        $invoice_id = $_GET['invoice_id'] ?? null;
        
        if (!$tenant_id || !$invoice_id) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id and invoice_id are required']);
            return;
        }
        
        $result = $this->asaasInvoice->downloadInvoicePdf($tenant_id, $filial_id, $invoice_id);
        
        if ($result['success'] && isset($result['pdf_content'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="nota_fiscal_' . $invoice_id . '.pdf"');
            echo $result['pdf_content'];
        } else {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode($result);
        }
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
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_invoices,
                    COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_invoices,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_invoices,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as error_invoices,
                    COALESCE(SUM(CASE WHEN status = 'issued' THEN valor_total ELSE 0 END), 0) as total_issued_value
                  FROM notas_fiscais 
                  WHERE tenant_id = $1";
        
        $params = [$tenant_id];
        
        if ($filial_id) {
            $query .= " AND filial_id = $2";
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
}
