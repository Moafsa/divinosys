<?php
/**
 * Controller de Faturas
 * Gerencia operações relacionadas a faturas e pagamentos
 */

require_once __DIR__ . '/../model/Fatura.php';
require_once __DIR__ . '/../model/AsaasPayment.php';

class FaturaController {
    private $faturaModel;
    private $asaasPayment;
    private $tenant_id;
    
    public function __construct() {
        $this->faturaModel = new Fatura();
        $this->asaasPayment = new AsaasPayment();
        
        // Verificar se usuário está autenticado
        if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999) {
            http_response_code(403);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
        
        $this->tenant_id = $_SESSION['tenant_id'] ?? null;
    }
    
    /**
     * Listar faturas do tenant
     */
    public function listFaturas() {
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        if ($this->tenant_id) {
            $faturas = $this->faturaModel->getByTenant($this->tenant_id, $limit, $offset);
        } else {
            // SuperAdmin - todas as faturas
            $faturas = $this->faturaModel->getAll($limit, $offset);
        }
        
        header('Content-Type: application/json');
        echo json_encode($faturas);
    }
    
    /**
     * Obter estatísticas de faturas
     */
    public function getStats() {
        if (!$this->tenant_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            return;
        }
        
        $stats = $this->faturaModel->getStatsByTenant($this->tenant_id);
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Criar nova fatura no Asaas
     */
    public function createFatura() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['valor']) || empty($data['descricao'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            return;
        }
        
        // Buscar dados do tenant
        $tenant_query = "SELECT * FROM tenants WHERE id = $1";
        $tenant_result = pg_query_params($this->faturaModel->conn, $tenant_query, [$this->tenant_id]);
        
        if (!$tenant_result || pg_num_rows($tenant_result) == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Tenant não encontrado']);
            return;
        }
        
        $tenant = pg_fetch_assoc($tenant_result);
        
        // Criar cobrança no Asaas
        $chargeData = [
            'asaas_customer_id' => $tenant['asaas_customer_id'],
            'valor' => $data['valor'],
            'data_vencimento' => $data['data_vencimento'] ?? date('Y-m-d', strtotime('+7 days')),
            'descricao' => $data['descricao']
        ];
        
        $result = $this->asaasPayment->createCharge($chargeData);
        
        if ($result['success']) {
            // Criar fatura no banco
            $faturaData = [
                'tenant_id' => $this->tenant_id,
                'asaas_payment_id' => $result['data']['id'],
                'valor' => $data['valor'],
                'status' => 'pending',
                'data_vencimento' => $chargeData['data_vencimento'],
                'descricao' => $data['descricao']
            ];
            
            $fatura_id = $this->faturaModel->create($faturaData);
            
            if ($fatura_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'fatura_id' => $fatura_id,
                    'asaas_payment_id' => $result['data']['id'],
                    'payment_url' => $result['data']['invoiceUrl'] ?? null
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar fatura no banco']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Erro ao criar cobrança no Asaas: ' . $result['error']]);
        }
    }
    
    /**
     * Processar webhook do Asaas
     */
    public function processWebhook() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $payload = file_get_contents('php://input');
        $webhook_data = json_decode($payload, true);
        
        if (!$webhook_data || !isset($webhook_data['event'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados do webhook inválidos']);
            return;
        }
        
        // Verificar se é evento de pagamento
        if ($webhook_data['event'] === 'PAYMENT_CONFIRMED' || 
            $webhook_data['event'] === 'PAYMENT_RECEIVED') {
            
            $payment_data = $webhook_data['payment'];
            
            // Buscar fatura existente
            $fatura = $this->faturaModel->getByAsaasPaymentId($payment_data['id']);
            
            if ($fatura) {
                // Atualizar status
                $this->faturaModel->updateStatus(
                    $payment_data['id'],
                    'paid',
                    $payment_data['confirmedDate'] ?? date('Y-m-d H:i:s'),
                    $webhook_data
                );
            } else {
                // Criar nova fatura a partir do webhook
                $this->faturaModel->createFromWebhook($webhook_data);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
    
    /**
     * Obter detalhes de uma fatura
     */
    public function getFaturaDetails() {
        $fatura_id = $_GET['id'] ?? null;
        
        if (!$fatura_id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID da fatura não fornecido']);
            return;
        }
        
        $query = "SELECT f.*, t.nome as tenant_nome, p.nome as plano_nome 
                  FROM faturas f
                  LEFT JOIN tenants t ON f.tenant_id = t.id
                  LEFT JOIN assinaturas a ON f.tenant_id = a.tenant_id
                  LEFT JOIN planos p ON a.plano_id = p.id
                  WHERE f.id = $1";
        
        $result = pg_query_params($this->faturaModel->conn, $query, [$fatura_id]);
        
        if ($result && pg_num_rows($result) > 0) {
            $fatura = pg_fetch_assoc($result);
            header('Content-Type: application/json');
            echo json_encode($fatura);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Fatura não encontrada']);
        }
    }
}
