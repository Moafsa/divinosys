<?php

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Auth.php';

use System\Database;
use System\Auth;

// Inicializar sistema
$config = \System\Config::getInstance();
$db = Database::getInstance();

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
        case 'login_admin':
            $usuario = $_POST['usuario'] ?? $_POST['login'] ?? '';
            $senha = $_POST['senha'] ?? '';
            
            if (empty($usuario) || empty($senha)) {
                throw new Exception('Usuário e senha são obrigatórios');
            }
            
            // Buscar usuário admin (aceitar nivel 1, NULL ou 999 para superadmin)
            $user = $db->fetch(
                "SELECT * FROM usuarios WHERE login = ? AND (nivel = 1 OR nivel IS NULL OR nivel = 999)",
                [$usuario]
            );
            
            if (!$user) {
                throw new Exception('Usuário não encontrado');
            }
            
            // Verificar senha
            if (!password_verify($senha, $user['senha'])) {
                throw new Exception('Senha incorreta');
            }
            
            // Set session data (session already started)
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nivel'] = $user['nivel']; // Adicionar nível na sessão
            $_SESSION['tenant_id'] = $user['tenant_id'] ?? 1;
            $_SESSION['filial_id'] = $user['filial_id'] ?? null;
            
            // Buscar e definir dados completos do tenant e filial
            $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$_SESSION['tenant_id']]);
            if ($tenant) {
                $_SESSION['tenant'] = $tenant;
            }
            
            if ($_SESSION['filial_id']) {
                $filial = $db->fetch("SELECT * FROM filiais WHERE id = ? AND tenant_id = ?", [$_SESSION['filial_id'], $_SESSION['tenant_id']]);
                if ($filial) {
                    $_SESSION['filial'] = $filial;
                }
            }
            
            // Log para debug
            error_log("Login Admin: User {$user['id']} - tenant_id: {$user['tenant_id']}, filial_id: {$user['filial_id']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => $user
            ]);
            break;
            
        case 'solicitar_login':
            $telefone = $_POST['telefone'] ?? '';
            
            if (empty($telefone)) {
                throw new Exception('Telefone é obrigatório');
            }
            
            // Limpar telefone (remover caracteres especiais)
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            
            // Buscar usuário
            $usuario = Auth::findUserByPhone($telefone);
            
            if (!$usuario) {
                // Verificar se telefone está em uso por outro usuário
                $phoneInUse = Auth::isPhoneInUse($telefone);
                
                if ($phoneInUse) {
                    // Telefone já está em uso - solicitar transferência
                    echo json_encode([
                        'success' => false,
                        'message' => 'Este telefone já está cadastrado para outro usuário',
                        'phone_in_use' => true,
                        'current_user' => $phoneInUse['nome']
                    ]);
                    break;
                }
                
                // Criar novo usuário
                $usuarioId = Auth::createUser([
                    'nome' => 'Usuário ' . $telefone,
                    'ativo' => true
                ]);
                
                // Adicionar telefone
                Auth::addUserPhone($usuarioId, $telefone, 'principal');
                
                $usuario = Auth::findUserByPhone($telefone);
            }
            
            // Gerar token de autenticação
            $token = Auth::generateToken($usuario['id'], 'login');
            
            // URL do link mágico
            $baseUrl = $_SERVER['HTTP_HOST'];
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $loginUrl = $protocol . '://' . $baseUrl . '/index.php?action=auth&token=' . $token;
            
            // Dados para enviar para o n8n
            $n8nData = [
                'telefone' => $telefone,
                'nome' => $usuario['nome'],
                'login_url' => $loginUrl,
                'expira_em' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
            ];
            
            // Log de acesso
            Auth::logAccess($usuario['id'], 'solicitar_login');
            
            echo json_encode([
                'success' => true,
                'message' => 'Link de login gerado com sucesso',
                'data' => $n8nData
            ]);
            break;
            
        case 'validar_token':
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                throw new Exception('Token é obrigatório');
            }
            
            $tokenData = Auth::validateToken($token);
            
            if (!$tokenData) {
                throw new Exception('Token inválido ou expirado');
            }
            
            // Buscar estabelecimentos do usuário
            $estabelecimentos = Database::getInstance()->fetchAll(
                "SELECT ue.*, t.nome as tenant_nome, f.nome as filial_nome 
                 FROM usuarios_estabelecimento ue 
                 LEFT JOIN tenant t ON ue.tenant_id = t.id 
                 LEFT JOIN filial f ON ue.filial_id = f.id 
                 WHERE ue.usuario_global_id = ? AND ue.ativo = true",
                [$tokenData['usuario_global_id']]
            );
            
            echo json_encode([
                'success' => true,
                'usuario' => $tokenData,
                'estabelecimentos' => $estabelecimentos
            ]);
            break;
            
        case 'fazer_login':
            $token = $_POST['token'] ?? '';
            $tenantId = $_POST['tenant_id'] ?? '';
            $filialId = $_POST['filial_id'] ?? null;
            
            if (empty($token) || empty($tenantId)) {
                throw new Exception('Token e tenant são obrigatórios');
            }
            
            $tokenData = Auth::validateToken($token);
            
            if (!$tokenData) {
                throw new Exception('Token inválido ou expirado');
            }
            
            // Verificar se usuário tem acesso ao estabelecimento
            $userEstablishment = Database::getInstance()->fetch(
                "SELECT * FROM usuarios_estabelecimento 
                 WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                [$tokenData['usuario_global_id'], $tenantId, $filialId]
            );
            
            if (!$userEstablishment) {
                throw new Exception('Usuário não tem acesso a este estabelecimento');
            }
            
            // Criar sessão
            $sessionToken = Auth::createSession($tokenData['usuario_global_id'], $tenantId, $filialId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'session_token' => $sessionToken,
                'usuario' => $tokenData,
                'estabelecimento' => $userEstablishment
            ]);
            break;
            
        case 'buscar_cliente':
            $telefone = $_POST['telefone'] ?? '';
            
            if (empty($telefone)) {
                throw new Exception('Telefone é obrigatório');
            }
            
            // Limpar telefone
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            
            $cliente = Auth::findUserByPhone($telefone);
            
            if ($cliente) {
                // Buscar histórico de pedidos
                $historico = Database::getInstance()->fetchAll(
                    "SELECT p.*, ue.tipo_usuario, t.nome as tenant_nome, f.nome as filial_nome
                     FROM pedido p
                     JOIN usuarios_estabelecimento ue ON p.usuario_id = ue.usuario_global_id
                     LEFT JOIN tenant t ON p.tenant_id = t.id
                     LEFT JOIN filial f ON p.filial_id = f.id
                     WHERE p.cliente_telefone = ? OR p.usuario_id = ?
                     ORDER BY p.data DESC, p.hora_pedido DESC
                     LIMIT 10",
                    [$telefone, $cliente['id']]
                );
                
                $cliente['historico_pedidos'] = $historico;
            }
            
            echo json_encode([
                'success' => true,
                'cliente' => $cliente
            ]);
            break;
            
        case 'criar_cliente':
            $telefone = $_POST['telefone'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $cpf = $_POST['cpf'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $ponto_referencia = $_POST['ponto_referencia'] ?? '';
            
            if (empty($telefone)) {
                throw new Exception('Telefone é obrigatório');
            }
            
            // Limpar telefone
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            
            // Verificar se já existe
            $clienteExistente = Auth::findUserByPhone($telefone);
            if ($clienteExistente) {
                throw new Exception('Cliente já existe com este telefone');
            }
            
            // Criar cliente
            $clienteId = Auth::createUser([
                'telefone' => $telefone,
                'nome' => $nome ?: 'Cliente ' . $telefone,
                'email' => $email,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'endereco_completo' => $endereco,
                'latitude' => $latitude ?: null,
                'longitude' => $longitude ?: null,
                'ponto_referencia' => $ponto_referencia,
                'ativo' => true
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cliente criado com sucesso',
                'cliente_id' => $clienteId
            ]);
            break;
            
        case 'logout':
            Auth::logout();
            
            // Instead of returning JSON, redirect to login
            header('Location: index.php?view=login');
            exit;
            break;
            
        case 'verificar_sessao':
            $sessionData = Auth::validateSession();
            
            if ($sessionData) {
                echo json_encode([
                    'success' => true,
                    'usuario' => $sessionData
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sessão inválida ou expirada'
                ]);
            }
            break;
            
        case 'verificar_consentimento_lgpd':
            $telefone = $_POST['telefone'] ?? '';
            $tenantId = $_POST['tenant_id'] ?? '';
            $filialId = $_POST['filial_id'] ?? null;
            $finalidade = $_POST['finalidade'] ?? 'pedidos';
            
            if (empty($telefone) || empty($tenantId)) {
                throw new Exception('Telefone e tenant são obrigatórios');
            }
            
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            $usuario = Auth::findUserByPhone($telefone);
            
            if (!$usuario) {
                echo json_encode([
                    'success' => true,
                    'consentimento_necessario' => true,
                    'message' => 'Cliente não encontrado - consentimento necessário'
                ]);
                break;
            }
            
            $consentimento = Auth::checkLGPDConsent($usuario['id'], $tenantId, $filialId, $finalidade);
            
            echo json_encode([
                'success' => true,
                'consentimento_necessario' => !$consentimento || !$consentimento['consentimento'],
                'consentimento' => $consentimento,
                'usuario' => $usuario
            ]);
            break;
            
        case 'registrar_consentimento_lgpd':
            $telefone = $_POST['telefone'] ?? '';
            $tenantId = $_POST['tenant_id'] ?? '';
            $filialId = $_POST['filial_id'] ?? null;
            $finalidade = $_POST['finalidade'] ?? 'pedidos';
            $consentimento = $_POST['consentimento'] === 'true';
            
            if (empty($telefone) || empty($tenantId)) {
                throw new Exception('Telefone e tenant são obrigatórios');
            }
            
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            $usuario = Auth::findUserByPhone($telefone);
            
            if (!$usuario) {
                throw new Exception('Usuário não encontrado');
            }
            
            // Registrar consentimento
            Auth::registerLGPDConsent($usuario['id'], $tenantId, $filialId, $finalidade, $consentimento);
            
            // Log de acesso
            Auth::logAccess($usuario['id'], 'consentimento_lgpd', $tenantId, $filialId, [
                'finalidade' => $finalidade,
                'consentimento' => $consentimento
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Consentimento registrado com sucesso'
            ]);
            break;
            
        case 'buscar_cliente_completo':
            $telefone = $_POST['telefone'] ?? '';
            
            if (empty($telefone)) {
                throw new Exception('Telefone é obrigatório');
            }
            
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            $usuario = Auth::findUserByPhone($telefone);
            
            if ($usuario) {
                $dadosCompletos = Auth::getUserCompleteData($usuario['id']);
                
                // Buscar histórico de pedidos
                $historico = Database::getInstance()->fetchAll(
                    "SELECT p.*, ue.tipo_usuario, t.nome as tenant_nome, f.nome as filial_nome
                     FROM pedido p
                     JOIN usuarios_estabelecimento ue ON p.usuario_id = ue.usuario_global_id
                     LEFT JOIN tenant t ON p.tenant_id = t.id
                     LEFT JOIN filial f ON p.filial_id = f.id
                     WHERE p.cliente_telefone = ? OR p.usuario_id = ?
                     ORDER BY p.data DESC, p.hora_pedido DESC
                     LIMIT 10",
                    [$telefone, $usuario['id']]
                );
                
                $dadosCompletos['historico_pedidos'] = $historico;
            }
            
            echo json_encode([
                'success' => true,
                'cliente' => $dadosCompletos ?? null
            ]);
            break;
            
        case 'adicionar_telefone':
            $usuarioId = $_POST['usuario_id'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipo = $_POST['tipo'] ?? 'secundario';
            
            if (empty($usuarioId) || empty($telefone)) {
                throw new Exception('Usuário e telefone são obrigatórios');
            }
            
            $telefone = preg_replace('/[^0-9]/', '', $telefone);
            
            // Verificar se telefone já está em uso
            $phoneInUse = Auth::isPhoneInUse($telefone, $usuarioId);
            if ($phoneInUse) {
                throw new Exception('Este telefone já está em uso por outro usuário');
            }
            
            Auth::addUserPhone($usuarioId, $telefone, $tipo);
            
            echo json_encode([
                'success' => true,
                'message' => 'Telefone adicionado com sucesso'
            ]);
            break;
            
        case 'adicionar_endereco':
            $usuarioId = $_POST['usuario_id'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            $pontoReferencia = $_POST['ponto_referencia'] ?? null;
            $tipo = $_POST['tipo'] ?? 'residencial';
            
            if (empty($usuarioId) || empty($endereco)) {
                throw new Exception('Usuário e endereço são obrigatórios');
            }
            
            Auth::addUserAddress($usuarioId, $endereco, $latitude, $longitude, $pontoReferencia, $tipo);
            
            echo json_encode([
                'success' => true,
                'message' => 'Endereço adicionado com sucesso'
            ]);
            break;
            
        case 'criar_usuario':
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipoUsuario = $_POST['tipo_usuario'] ?? 'cliente';
            $cpf = $_POST['cpf'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório');
            }
            
            // Criar usuário na tabela usuarios_globais
            $db = Database::getInstance();
            $usuarioId = $db->insert('usuarios_globais', [
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'tipo_usuario' => $tipoUsuario,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'endereco_completo' => $endereco,
                'ativo' => true,
                'data_cadastro' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário criado com sucesso!',
                'usuario_id' => $usuarioId
            ]);
            break;
            
        case 'listar_usuarios':
            $db = Database::getInstance();
            
            // Verificar se a tabela usuarios_globais existe, senão usar usuarios
            $tableExists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'usuarios_globais')");
            
            if ($tableExists && $tableExists['exists']) {
                // Usar nova estrutura
                $usuarios = $db->fetchAll(
                    "SELECT * FROM usuarios_globais WHERE ativo = true AND tipo_usuario != 'cliente' ORDER BY created_at DESC"
                );
            } else {
                // Usar estrutura antiga
                $usuarios = $db->fetchAll(
                    "SELECT id, login as nome, login as email, '' as telefone, 'admin' as tipo_usuario, '' as cpf, '' as cnpj, '' as endereco_completo, created_at FROM usuarios WHERE nivel = 1 ORDER BY id DESC"
                );
            }
            
            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios
            ]);
            break;
            
        case 'editar_usuario':
            $id = $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipoUsuario = $_POST['tipo_usuario'] ?? 'cliente';
            $cpf = $_POST['cpf'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            
            if (empty($id) || empty($nome)) {
                throw new Exception('ID e nome são obrigatórios');
            }
            
            $db = Database::getInstance();
            $db->query(
                "UPDATE usuarios_globais SET nome = ?, email = ?, telefone = ?, tipo_usuario = ?, cpf = ?, cnpj = ?, endereco_completo = ?, updated_at = ? WHERE id = ?",
                [$nome, $email, $telefone, $tipoUsuario, $cpf, $cnpj, $endereco, date('Y-m-d H:i:s'), $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso!'
            ]);
            break;
            
        case 'deletar_usuario':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID é obrigatório');
            }
            
            $db = Database::getInstance();
            $db->query(
                "UPDATE usuarios_globais SET ativo = false, updated_at = ? WHERE id = ?",
                [date('Y-m-d H:i:s'), $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário removido com sucesso!'
            ]);
            break;
            
        case 'buscar_usuario':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID é obrigatório');
            }
            
            $db = Database::getInstance();
            $usuario = $db->fetch(
                "SELECT * FROM usuarios_globais WHERE id = ? AND ativo = true",
                [$id]
            );
            
            if ($usuario) {
                echo json_encode([
                    'success' => true,
                    'usuario' => $usuario
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ]);
            }
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    error_log("Erro na autenticação: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
