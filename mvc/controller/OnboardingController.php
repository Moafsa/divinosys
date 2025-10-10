<?php
/**
 * Controller de Onboarding
 * Gerencia o processo de cadastro de novos estabelecimentos
 */

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Plan.php';
require_once __DIR__ . '/../model/Subscription.php';

class OnboardingController {
    private $tenantModel;
    private $planModel;
    private $subscriptionModel;
    private $conn;
    
    public function __construct() {
        $this->tenantModel = new Tenant();
        $this->planModel = new Plan();
        $this->subscriptionModel = new Subscription();
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar novo estabelecimento completo
     */
    public function createEstablishment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar dados obrigatórios
        if (empty($data['nome']) || empty($data['subdomain']) || empty($data['email']) || 
            empty($data['telefone']) || empty($data['plano_id']) || 
            empty($data['admin_login']) || empty($data['admin_senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados obrigatórios não fornecidos']);
            return;
        }
        
        // Verificar se subdomain está disponível
        if (!$this->tenantModel->isSubdomainAvailable($data['subdomain'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain já está em uso']);
            return;
        }
        
        // Iniciar transação
        pg_query($this->conn, 'BEGIN');
        
        try {
            // 1. Criar tenant
            $tenant_data = [
                'nome' => $data['nome'],
                'subdomain' => $data['subdomain'],
                'cnpj' => $data['cnpj'] ?? null,
                'telefone' => $data['telefone'],
                'email' => $data['email'],
                'endereco' => $data['endereco'] ?? null,
                'cor_primaria' => $data['cor_primaria'] ?? '#667eea',
                'plano_id' => $data['plano_id'],
                'status' => 'ativo'
            ];
            
            $tenant_id = $this->tenantModel->create($tenant_data);
            
            if (!$tenant_id) {
                throw new Exception('Erro ao criar tenant');
            }
            
            // 2. Criar usuário administrador
            $senha_hash = password_hash($data['admin_senha'], PASSWORD_BCRYPT);
            
            $query = "INSERT INTO usuarios (login, senha, nivel, pergunta, resposta, tenant_id) 
                      VALUES ($1, $2, $3, $4, $5, $6) RETURNING id";
            
            $result = pg_query_params($this->conn, $query, [
                $data['admin_login'],
                $senha_hash,
                1, // Nível administrador do tenant
                'Sistema',
                'Sistema',
                $tenant_id
            ]);
            
            if (!$result) {
                throw new Exception('Erro ao criar usuário administrador');
            }
            
            $admin = pg_fetch_assoc($result);
            
            // 3. Criar assinatura com trial
            $plano = $this->planModel->getById($data['plano_id']);
            
            $subscription_data = [
                'tenant_id' => $tenant_id,
                'plano_id' => $data['plano_id'],
                'status' => 'trial',
                'valor' => $plano['preco_mensal'],
                'periodicidade' => 'mensal',
                'data_inicio' => date('Y-m-d'),
                'data_proxima_cobranca' => date('Y-m-d', strtotime('+14 days')),
                'trial_ate' => date('Y-m-d', strtotime('+14 days'))
            ];
            
            $subscription_id = $this->subscriptionModel->create($subscription_data);
            
            if (!$subscription_id) {
                throw new Exception('Erro ao criar assinatura');
            }
            
            // 4. Criar categorias padrão
            $this->createDefaultCategories($tenant_id);
            
            // 5. Criar mesas se configurado
            if (!empty($data['num_mesas']) && $data['tem_mesas']) {
                $this->createDefaultMesas($tenant_id, intval($data['num_mesas']));
            }
            
            // 6. Configurar opções do tenant
            $this->setupTenantConfig($tenant_id, $data);
            
            // 7. Enviar email de boas-vindas (opcional)
            // $this->sendWelcomeEmail($data['email'], $data['nome'], $data['subdomain']);
            
            // Commit da transação
            pg_query($this->conn, 'COMMIT');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'tenant_id' => $tenant_id,
                'message' => 'Estabelecimento criado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            pg_query($this->conn, 'ROLLBACK');
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Criar categorias padrão para novo tenant
     */
    private function createDefaultCategories($tenant_id) {
        $categorias = [
            ['nome' => 'Lanches', 'imagem' => null],
            ['nome' => 'Bebidas', 'imagem' => null],
            ['nome' => 'Porções', 'imagem' => null],
            ['nome' => 'Sobremesas', 'imagem' => null]
        ];
        
        $query = "INSERT INTO categorias (nome, tenant_id, imagem) VALUES ($1, $2, $3)";
        
        foreach ($categorias as $categoria) {
            pg_query_params($this->conn, $query, [
                $categoria['nome'],
                $tenant_id,
                $categoria['imagem']
            ]);
        }
    }
    
    /**
     * Criar mesas padrão para novo tenant
     */
    private function createDefaultMesas($tenant_id, $quantidade) {
        $query = "INSERT INTO mesas (numero, tenant_id, capacidade, status) VALUES ($1, $2, $3, $4)";
        
        for ($i = 1; $i <= $quantidade; $i++) {
            pg_query_params($this->conn, $query, [
                $i,
                $tenant_id,
                4,
                'livre'
            ]);
        }
    }
    
    /**
     * Configurar opções do tenant
     */
    private function setupTenantConfig($tenant_id, $data) {
        $configs = [
            ['chave' => 'tem_delivery', 'valor' => $data['tem_delivery'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'tem_mesas', 'valor' => $data['tem_mesas'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'tem_balcao', 'valor' => $data['tem_balcao'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'num_mesas', 'valor' => $data['num_mesas'] ?? 10, 'tipo' => 'integer']
        ];
        
        $query = "INSERT INTO tenant_config (tenant_id, chave, valor, tipo) VALUES ($1, $2, $3, $4)";
        
        foreach ($configs as $config) {
            $valor = is_bool($config['valor']) ? ($config['valor'] ? 'true' : 'false') : $config['valor'];
            
            pg_query_params($this->conn, $query, [
                $tenant_id,
                $config['chave'],
                $valor,
                $config['tipo']
            ]);
        }
    }
    
    /**
     * Verificar disponibilidade de subdomain
     */
    public function checkSubdomain() {
        $subdomain = $_GET['subdomain'] ?? '';
        
        if (empty($subdomain)) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain não fornecido']);
            return;
        }
        
        $available = $this->tenantModel->isSubdomainAvailable($subdomain);
        
        header('Content-Type: application/json');
        echo json_encode(['available' => $available]);
    }
}

// Roteamento
if (basename($_SERVER['PHP_SELF']) == 'OnboardingController.php') {
    $controller = new OnboardingController();
    $action = $_GET['action'] ?? 'createEstablishment';
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        // Default: create establishment
        $controller->createEstablishment();
    }
}

