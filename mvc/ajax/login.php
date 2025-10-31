<?php
// Incluir classes necessárias
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Session.php';

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
            
            // Definir tenant_id na sessão
            $tenantId = $user['tenant_id'] ?? 1;
            $session->set('tenant_id', $tenantId);
            
            // Definir filial_id na sessão
            $filialId = $user['filial_id'] ?? null;
            
            // Se não há filial específica, usar filial padrão do tenant
            if ($filialId === null) {
                $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
            $session->set('filial_id', $filialId);
            
            // Buscar e definir dados completos do tenant e filial
            $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
            if ($tenant) {
                $session->setTenant($tenant);
            }
            
            if ($filialId) {
                $filial = $db->fetch("SELECT * FROM filiais WHERE id = ? AND tenant_id = ?", [$filialId, $tenantId]);
                if ($filial) {
                    $session->setFilial($filial);
                }
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
