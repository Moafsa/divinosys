<?php
// Incluir classes necessárias
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Config.php';

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
            
            // Find user in usuarios table
            $db = \System\Database::getInstance();
            $user = $db->fetch(
                "SELECT * FROM usuarios WHERE login = ?",
                [$login]
            );
            
            if (!$user) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Verify password
            if (!password_verify($senha, $user['senha'])) {
                throw new \Exception('Senha incorreta');
            }
            
            // Start session and set user data
            $session = \System\Session::getInstance();
            $session->setUser($user);
            
            // Definir tenant_id na sessão se não estiver definido
            if (!$session->getTenantId()) {
                $session->set('tenant_id', $user['tenant_id'] ?? '1');
            }
            
            // Definir filial_id na sessão se não estiver definido
            if (!$session->getFilialId()) {
                $session->set('filial_id', $user['filial_id'] ?? '1');
            }
            
            // Definir user_type na sessão para controle de acesso
            $session->set('user_type', 'admin'); // Assumindo que login admin é sempre admin
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login realizado com sucesso!',
                'user' => [
                    'id' => $user['id'],
                    'login' => $user['login'],
                    'nivel' => $user['nivel']
                ]
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada');
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
