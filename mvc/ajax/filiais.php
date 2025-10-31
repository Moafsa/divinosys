<?php
// Capturar todos os erros e retornar como JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ERRO FATAL: " . json_encode($error));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']
            ]);
        }
    }
});

header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

use System\Database;

// Inicializar sistema
$db = Database::getInstance();

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'listar_filiais':
            // Listar filiais do tenant principal
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // CORRETO: Buscar na tabela filiais, não tenants
            $filiais = $db->fetchAll(
                "SELECT f.*, u.login, u.id as usuario_id
                 FROM filiais f
                 LEFT JOIN usuarios u ON u.filial_id = f.id AND u.tenant_id = f.tenant_id
                 WHERE f.tenant_id = ? AND f.status = 'ativo'
                 ORDER BY f.created_at DESC",
                [$tenantId]
            );
            
            echo json_encode([
                'success' => true,
                'filiais' => $filiais
            ]);
            break;
            
        case 'criar_filial':
            $nomeFilial = $_POST['nome_filial'] ?? '';
            $login = $_POST['login'] ?? '';
            $senha = $_POST['senha'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            
            // Validar campos obrigatórios
            if (empty($nomeFilial) || empty($login) || empty($senha) || empty($nome)) {
                throw new Exception('Preencha todos os campos obrigatórios');
            }
            
            // Verificar se login já existe (sistema antigo - compatível com login)
            $usuarioExistente = $db->fetch(
                "SELECT id FROM usuarios WHERE login = ?",
                [$login]
            );
            
            if ($usuarioExistente) {
                throw new Exception('Login já existe. Escolha outro login.');
            }
            
            // Verificar limite de filiais do plano
            require_once __DIR__ . '/../model/Subscription.php';
            require_once __DIR__ . '/../model/Plan.php';
            
            $subscriptionModel = new Subscription();
            $planModel = new Plan();
            
            // IMPORTANTE: Usar Session::getInstance() para garantir o tenant correto
            $session = \System\Session::getInstance();
            $tenant_id = $session->getTenantId();
            
            // Log para debug
            error_log("filiais.php::criar_filial - Tenant ID da sessão: " . ($tenant_id ?? 'NULL'));
            error_log("filiais.php::criar_filial - Tenant ID direto da SESSION: " . ($_SESSION['tenant_id'] ?? 'NULL'));
            
            if (!$tenant_id) {
                throw new Exception('Tenant não identificado na sessão. Faça login novamente.');
            }
            
            $subscription = $subscriptionModel->getByTenant($tenant_id);
            
            if ($subscription) {
                $plano = $planModel->getById($subscription['plano_id']);
                if ($plano && $plano['max_filiais'] != -1) {
                    // Contar filiais existentes (CORRETO: contar na tabela filiais)
                    $filiaisExistentes = $db->fetch(
                        "SELECT COUNT(*) as count FROM filiais WHERE tenant_id = ?",
                        [$tenant_id]
                    );
                    
                    if ($filiaisExistentes['count'] >= $plano['max_filiais']) {
                        $planoNome = $plano['nome'];
                        $limiteFiliais = $plano['max_filiais'];
                        throw new Exception("Limite de filiais atingido! Seu plano $planoNome permite apenas $limiteFiliais filiais. Faça upgrade do seu plano para criar mais filiais.");
                    }
                }
            }
            
            
            // CORRETO: Criar nova FILIAL (não tenant) vinculada ao tenant principal
            $novaFilialId = $db->insert('filiais', [
                'tenant_id' => $tenant_id, // Mesma tenant do estabelecimento principal
                'nome' => $nomeFilial,
                'email' => $email ?: 'contato@' . strtolower(str_replace(' ', '', $nomeFilial)) . '.com',
                'telefone' => $telefone ?: '',
                'endereco' => $endereco,
                'status' => 'ativo',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Criar usuário global primeiro
            $usuarioGlobalId = $db->insert('usuarios_globais', [
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'tipo_usuario' => 'admin',
                'ativo' => true,
                'data_cadastro' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Criar usuário admin para a filial (sistema antigo - compatível com login)
            $usuarioId = $db->insert('usuarios', [
                'tenant_id' => $tenant_id, // Mesma tenant
                'filial_id' => $novaFilialId, // Filial específica
                'login' => $login,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'nivel' => 1, // Admin da filial
                'pergunta' => 'Qual sua cor favorita?',
                'resposta' => 'azul',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Criar vinculo na tabela usuarios_estabelecimento
            $db->insert('usuarios_estabelecimento', [
                'usuario_global_id' => $usuarioGlobalId,
                'tenant_id' => $tenant_id,
                'filial_id' => $novaFilialId,
                'tipo_usuario' => 'admin',
                'cargo' => 'Administrador da Filial',
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("filiais.php::criar_filial - Filial criada com sucesso! ID: $novaFilialId, Tenant ID: $tenant_id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Filial criada com sucesso!',
                'filial' => [
                    'id' => $novaFilialId,
                    'tenant_id' => $tenant_id,
                    'nome' => $nomeFilial,
                    'login' => $login
                ],
                'debug' => [
                    'tenant_id_usado' => $tenant_id,
                    'tenant_id_session' => $session->getTenantId()
                ]
            ]);
            break;
            
        case 'excluir_filial':
            $filialId = $_POST['filial_id'] ?? '';
            
            // IMPORTANTE: Usar Session::getInstance() para garantir o tenant correto
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            
            if (!$tenantId) {
                throw new Exception('Tenant não identificado na sessão. Faça login novamente.');
            }
            
            if (empty($filialId)) {
                throw new Exception('ID da filial é obrigatório');
            }
            
            // Verificar se filial existe e pertence ao tenant
            $filial = $db->fetch(
                "SELECT * FROM filiais WHERE id = ? AND tenant_id = ?",
                [$filialId, $tenantId]
            );
            
            if (!$filial) {
                throw new Exception('Filial não encontrada');
            }
            
            // Verificar se não é a filial principal (primeira filial do tenant)
            $filialPrincipal = $db->fetch(
                "SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1",
                [$tenantId]
            );
            
            if ($filialPrincipal && $filialPrincipal['id'] == $filialId) {
                throw new Exception('Não é possível excluir a filial principal!');
            }
            
            // Excluir usuários vinculados à filial
            $db->query("DELETE FROM usuarios WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
            $db->query("DELETE FROM usuarios_estabelecimento WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
            
            // Excluir filial
            $db->query("DELETE FROM filiais WHERE id = ? AND tenant_id = ?", [$filialId, $tenantId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Filial excluída com sucesso!'
            ]);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
