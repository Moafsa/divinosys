<?php
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_tenants':
            $db = \System\Database::getInstance();
            $tenants = $db->fetchAll(
                "SELECT id, nome, subdomain FROM tenants WHERE status = 'ativo' ORDER BY nome"
            );
            echo json_encode($tenants);
            break;
            
        case 'login':
            // Handle login request
            $login = $_POST['login'] ?? '';
            $senha = $_POST['senha'] ?? '';
            
            if (empty($login) || empty($senha)) {
                throw new \Exception('Usuário e senha são obrigatórios');
            }
            
            // Find tenant by user
            $db = \System\Database::getInstance();
            $user = $db->fetch(
                "SELECT u.*, t.subdomain 
                 FROM usuarios u 
                 JOIN tenants t ON u.tenant_id = t.id 
                 WHERE u.login = ? AND t.status = 'ativo'",
                [$login]
            );
            
            if (!$user) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Use AuthService for login with subdomain
            $authService = new \App\Auth\AuthService();
            $result = $authService->login($login, $senha, $user['subdomain']);
            
            echo json_encode($result);
            break;
            
        default:
            throw new \Exception('Ação não encontrada');
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
