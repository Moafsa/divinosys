<?php
/**
 * Controller do Tenant
 * Gerencia operações específicas de cada estabelecimento
 */

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/Payment.php';

class TenantController {
    private $tenantModel;
    private $subscriptionModel;
    private $paymentModel;
    private $tenant_id;
    
    public function __construct() {
        // Verificar se usuário está autenticado
        if (!isset($_SESSION['tenant_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
        
        $this->tenant_id = $_SESSION['tenant_id'];
        $this->tenantModel = new Tenant();
        $this->subscriptionModel = new Subscription();
        $this->paymentModel = new Payment();
    }
    
    /**
     * Obter informações do tenant atual
     */
    public function getTenantInfo() {
        $tenant = $this->tenantModel->getById($this->tenant_id);
        $subscription = $this->subscriptionModel->getByTenant($this->tenant_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'tenant' => $tenant,
            'subscription' => $subscription
        ]);
    }
    
    /**
     * Atualizar informações do tenant
     */
    public function updateTenantInfo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Remover campos que não podem ser atualizados pelo próprio tenant
        unset($data['status'], $data['plano_id'], $data['subdomain']);
        
        $result = $this->tenantModel->update($this->tenant_id, $data);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Listar filiais do tenant
     */
    public function listFiliais() {
        $filiais = $this->tenantModel->getFiliais($this->tenant_id);
        
        header('Content-Type: application/json');
        echo json_encode($filiais);
    }
    
    /**
     * Criar filial
     */
    public function createFilial() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Verificar limite de filiais do plano
        $subscription = $this->subscriptionModel->getByTenant($this->tenant_id);
        if ($subscription) {
            $plano_recursos = json_decode($subscription['recursos'], true);
            // Implementar verificação de limite
        }
        
        $data['tenant_id'] = $this->tenant_id;
        
        $conn = Database::getInstance()->getConnection();
        $query = "INSERT INTO filiais (tenant_id, nome, endereco, telefone, email, cnpj, status) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id";
        
        $result = pg_query_params($conn, $query, [
            $data['tenant_id'],
            $data['nome'],
            $data['endereco'] ?? null,
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['cnpj'] ?? null,
            $data['status'] ?? 'ativo'
        ]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'filial_id' => $row['id']]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar filial']);
        }
    }
    
    /**
     * Atualizar filial
     */
    public function updateFilial() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        
        $conn = Database::getInstance()->getConnection();
        $query = "UPDATE filiais SET 
                  nome = $1, endereco = $2, telefone = $3, email = $4, cnpj = $5, status = $6,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = $7 AND tenant_id = $8";
        
        $result = pg_query_params($conn, $query, [
            $data['nome'],
            $data['endereco'] ?? null,
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['cnpj'] ?? null,
            $data['status'] ?? 'ativo',
            $data['id'],
            $this->tenant_id
        ]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Deletar filial
     */
    public function deleteFilial() {
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
        
        $conn = Database::getInstance()->getConnection();
        $query = "UPDATE filiais SET status = 'inativo' WHERE id = $1 AND tenant_id = $2";
        $result = pg_query_params($conn, $query, [$id, $this->tenant_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$result]);
    }
    
    /**
     * Obter histórico de pagamentos
     */
    public function getPaymentHistory() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 50
        ];
        
        $payments = $this->paymentModel->getByTenant($this->tenant_id, $filters);
        
        header('Content-Type: application/json');
        echo json_encode($payments);
    }
    
    /**
     * Verificar status da assinatura
     */
    public function checkSubscriptionStatus() {
        $isActive = $this->subscriptionModel->isActive($this->tenant_id);
        $subscription = $this->subscriptionModel->getByTenant($this->tenant_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'is_active' => $isActive,
            'subscription' => $subscription
        ]);
    }
}

// Roteamento
if (basename($_SERVER['PHP_SELF']) == 'TenantController.php') {
    $controller = new TenantController();
    $action = $_GET['action'] ?? 'getTenantInfo';
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ação não encontrada']);
    }
}

