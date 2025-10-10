<?php
/**
 * Controller do SuperAdministrador
 * Gerencia todas as operações administrativas do sistema SaaS
 */

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Plan.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/Payment.php';

class SuperAdminController {
    private $tenantModel;
    private $planModel;
    private $subscriptionModel;
    private $paymentModel;
    
    public function __construct() {
        // Verificar se é superadmin
        if (!isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
        
        $this->tenantModel = new Tenant();
        $this->planModel = new Plan();
        $this->subscriptionModel = new Subscription();
        $this->paymentModel = new Payment();
    }
    
    /**
     * Obter estatísticas do dashboard
     */
    public function getDashboardStats() {
        $stats = [
            'tenants' => $this->tenantModel->getStats(),
            'subscriptions' => $this->subscriptionModel->getStats(),
            'payments' => [
                'hoje' => $this->paymentModel->getStats('hoje'),
                'semana' => $this->paymentModel->getStats('semana'),
                'mes' => $this->paymentModel->getStats('mes')
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Listar tenants
     */
    public function listTenants() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'plano_id' => $_GET['plano_id'] ?? null,
            'search' => $_GET['search'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $tenants = $this->tenantModel->getAll($filters);
        
        header('Content-Type: application/json');
        echo json_encode($tenants);
    }
    
    /**
     * Criar tenant
     */
    public function createTenant() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar dados
        if (empty($data['nome']) || empty($data['subdomain']) || empty($data['plano_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos']);
            return;
        }
        
        // Verificar se subdomain está disponível
        if (!$this->tenantModel->isSubdomainAvailable($data['subdomain'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain já está em uso']);
            return;
        }
        
        $tenant_id = $this->tenantModel->create($data);
        
        if ($tenant_id) {
            // Criar assinatura
            $plano = $this->planModel->getById($data['plano_id']);
            $subscription_data = [
                'tenant_id' => $tenant_id,
                'plano_id' => $data['plano_id'],
                'valor' => $plano['preco_mensal'],
                'status' => $data['trial'] ?? false ? 'trial' : 'ativa'
            ];
            
            $this->subscriptionModel->create($subscription_data);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'tenant_id' => $tenant_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar tenant']);
        }
    }
    
    /**
     * Atualizar tenant
     */
    public function updateTenant() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->tenantModel->update($data['id'], $data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Suspender/Reativar tenant
     */
    public function toggleTenantStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['tenant_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID não fornecido']);
            return;
        }
        
        $tenant = $this->tenantModel->getById($data['tenant_id']);
        $new_status = $tenant['status'] == 'ativo' ? 'suspenso' : 'ativo';
        
        $result = $this->tenantModel->update($data['tenant_id'], ['status' => $new_status]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result, 'new_status' => $new_status]);
    }
    
    /**
     * Listar planos
     */
    public function listPlans() {
        $plans = $this->planModel->getAll();
        
        header('Content-Type: application/json');
        echo json_encode($plans);
    }
    
    /**
     * Criar plano
     */
    public function createPlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $plan_id = $this->planModel->create($data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$plan_id, 'plan_id' => $plan_id]);
    }
    
    /**
     * Atualizar plano
     */
    public function updatePlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->planModel->update($data['id'], $data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Deletar plano
     */
    public function deletePlan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID não fornecido']);
            return;
        }
        
        $result = $this->planModel->delete($id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Listar pagamentos
     */
    public function listPayments() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'tenant_id' => $_GET['tenant_id'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $payments = $this->paymentModel->getAll($filters);
        
        header('Content-Type: application/json');
        echo json_encode($payments);
    }
    
    /**
     * Marcar pagamento como pago manualmente
     */
    public function markPaymentAsPaid() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['payment_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID não fornecido']);
            return;
        }
        
        $result = $this->paymentModel->markAsPaid($data['payment_id'], 'manual');
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
}

// Roteamento
if (basename($_SERVER['PHP_SELF']) == 'SuperAdminController.php') {
    $controller = new SuperAdminController();
    $action = $_GET['action'] ?? 'getDashboardStats';
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ação não encontrada']);
    }
}

