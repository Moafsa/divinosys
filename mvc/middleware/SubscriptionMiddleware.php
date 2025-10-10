<?php
/**
 * Middleware de Verificação de Assinatura
 * Verifica se o tenant tem assinatura ativa e limites do plano
 */

require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/Plan.php';

class SubscriptionMiddleware {
    private $subscriptionModel;
    private $planModel;
    
    public function __construct() {
        $this->subscriptionModel = new Subscription();
        $this->planModel = new Plan();
    }
    
    /**
     * Verificar se assinatura está ativa
     */
    public function checkSubscription() {
        // Superadmin não precisa de assinatura
        if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == 999) {
            return true;
        }
        
        if (!isset($_SESSION['tenant_id'])) {
            return false;
        }
        
        $tenant_id = $_SESSION['tenant_id'];
        $isActive = $this->subscriptionModel->isActive($tenant_id);
        
        if (!$isActive) {
            $_SESSION['subscription_expired'] = true;
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar limite de recurso
     */
    public function checkLimit($tipo_limite) {
        // Superadmin não tem limites
        if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == 999) {
            return true;
        }
        
        if (!isset($_SESSION['tenant_id'])) {
            return false;
        }
        
        $tenant_id = $_SESSION['tenant_id'];
        $subscription = $this->subscriptionModel->getByTenant($tenant_id);
        
        if (!$subscription) {
            return false;
        }
        
        return $this->planModel->checkLimits($tenant_id, $subscription['plano_id'], $tipo_limite);
    }
    
    /**
     * Obter informações de uso e limites
     */
    public function getUsageInfo() {
        if (!isset($_SESSION['tenant_id'])) {
            return null;
        }
        
        $tenant_id = $_SESSION['tenant_id'];
        $subscription = $this->subscriptionModel->getByTenant($tenant_id);
        
        if (!$subscription) {
            return null;
        }
        
        $plano = $this->planModel->getById($subscription['plano_id']);
        $conn = Database::getInstance()->getConnection();
        
        // Buscar uso atual
        $queries = [
            'mesas' => "SELECT COUNT(*) as count FROM mesas WHERE tenant_id = $1",
            'usuarios' => "SELECT COUNT(*) as count FROM usuarios WHERE tenant_id = $1",
            'produtos' => "SELECT COUNT(*) as count FROM produtos WHERE tenant_id = $1",
            'pedidos_mes' => "SELECT COUNT(*) as count FROM pedidos 
                             WHERE tenant_id = $1 
                             AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
                             AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)"
        ];
        
        $usage = [];
        foreach ($queries as $key => $query) {
            $result = pg_query_params($conn, $query, [$tenant_id]);
            if ($result) {
                $row = pg_fetch_assoc($result);
                $usage[$key] = [
                    'usado' => $row['count'],
                    'limite' => $plano["max_$key"],
                    'porcentagem' => $plano["max_$key"] > 0 ? 
                        round(($row['count'] / $plano["max_$key"]) * 100, 2) : 0,
                    'ilimitado' => $plano["max_$key"] == -1
                ];
            }
        }
        
        return [
            'plano' => $plano,
            'subscription' => $subscription,
            'usage' => $usage
        ];
    }
    
    /**
     * Middleware para proteger rotas
     */
    public static function protect() {
        $middleware = new self();
        
        if (!$middleware->checkSubscription()) {
            // Redirecionar para página de assinatura expirada
            header('Location: /index.php?view=subscription_expired');
            exit;
        }
    }
    
    /**
     * Middleware para verificar limite antes de criar recurso
     */
    public static function checkResourceLimit($tipo) {
        $middleware = new self();
        
        if (!$middleware->checkLimit($tipo)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Limite de ' . $tipo . ' atingido',
                'message' => 'Você atingiu o limite do seu plano. Faça upgrade para continuar.'
            ]);
            exit;
        }
        
        return true;
    }
}

