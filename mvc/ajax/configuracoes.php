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

// Simples e direto - usar require_once
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/WhatsApp/BaileysManager.php';

try {
    $action = $_POST['action'] ?? '';
    
    error_log("AJAX configuracoes.php - Ação recebida: " . $action);
    
    switch ($action) {
        case 'salvar_aparencia':
            $corPrimaria = $_POST['cor_primaria'] ?? '';
            $nomeEstabelecimento = $_POST['nome_estabelecimento'] ?? '';
            
            if (empty($corPrimaria) || empty($nomeEstabelecimento)) {
                throw new \Exception('Todos os campos são obrigatórios');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            
            // Atualizar tenant
            $db->update(
                'tenants',
                ['cor_primaria' => $corPrimaria, 'nome' => $nomeEstabelecimento],
                'id = ?',
                [$tenantId]
            );
            
            // Atualizar sessão
            $tenant = $session->getTenant();
            $tenant['cor_primaria'] = $corPrimaria;
            $tenant['nome'] = $nomeEstabelecimento;
            $session->setTenant($tenant);
            
            echo json_encode(['success' => true, 'message' => 'Configurações de aparência salvas com sucesso!']);
            break;
            
        case 'salvar_mesas':
            $numeroMesas = (int) ($_POST['numero_mesas'] ?? 0);
            $capacidadeMesa = (int) ($_POST['capacidade_mesa'] ?? 0);
            
            if ($numeroMesas <= 0 || $capacidadeMesa <= 0) {
                throw new \Exception('Número de mesas e capacidade devem ser maiores que zero');
            }
            
            $db = \System\Database::getInstance();
            
            // Usar valores padrão para tenant_id e filial_id
            $tenantId = 1;
            $filialId = 1;
            
            // Deletar mesas existentes
            $db->delete('mesas', 'tenant_id = ? AND filial_id = ?', [$tenantId, $filialId]);
            
            // Criar novas mesas
            for ($i = 1; $i <= $numeroMesas; $i++) {
                $db->insert('mesas', [
                    'id_mesa' => (string)$i,
                    'nome' => "Mesa {$i}",
                    'status' => '1', // 1 = livre, 2 = ocupada
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Configurações de mesas salvas com sucesso!']);
            break;
            
        // ===== WUZAPI FUNCTIONS =====
        
        case 'listar_caixas_entrada':
            error_log("AJAX listar_caixas_entrada - Iniciando");
            
            // Usar tenant_id fixo para teste (mesmo usado na criação)
            $tenantId = 1;
            
            error_log("AJAX listar_caixas_entrada - Tenant ID: " . $tenantId);
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $instancias = $baileysManager->getInstances($tenantId);
            
            error_log("AJAX listar_caixas_entrada - Instâncias encontradas: " . count($instancias));
            
            echo json_encode(['success' => true, 'instances' => $instancias]);
            break;
            
        case 'criar_caixa_entrada':
            $instanceName = $_POST['instance_name'] ?? '';
            $phoneNumber = $_POST['phone_number'] ?? '';
            
            if (empty($instanceName) || empty($phoneNumber)) {
                throw new \Exception('Nome e número são obrigatórios');
            }
            
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            // Usar webhook do n8n do .env
            $webhookUrl = $_ENV['N8N_WEBHOOK_URL'] ?? '';
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $result = $baileysManager->createInstance($instanceName, $phoneNumber, $tenantId, $filialId, $webhookUrl);
            
            echo json_encode($result);
            break;
            
        case 'conectar_caixa_entrada':
            $instanceId = (int) ($_POST['instance_id'] ?? 0);
            
            if ($instanceId <= 0) {
                throw new \Exception('ID da instância inválido');
            }
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $result = $baileysManager->generateQRCode($instanceId);
            
            echo json_encode($result);
            break;
            
        case 'deletar_caixa_entrada':
            $instanceId = (int) ($_POST['instance_id'] ?? 0);
            
            if ($instanceId <= 0) {
                throw new \Exception('ID da instância inválido');
            }
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $result = $baileysManager->deleteInstance($instanceId);
            
            echo json_encode($result);
            break;
            
        case 'sincronizar_status':
            $instanceId = (int) ($_POST['instance_id'] ?? 0);
            
            if ($instanceId <= 0) {
                throw new \Exception('ID da instância inválido');
            }
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $result = $baileysManager->syncInstanceStatus($instanceId);
            
            echo json_encode($result);
            break;
            
        case 'enviar_mensagem':
            $instanceId = (int) ($_POST['instance_id'] ?? 0);
            $phoneNumber = $_POST['phone_number'] ?? '';
            $message = $_POST['message'] ?? '';
            
            if ($instanceId <= 0 || empty($phoneNumber) || empty($message)) {
                throw new \Exception('Todos os campos são obrigatórios');
            }
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $result = $baileysManager->sendMessage($instanceId, $phoneNumber, $message);
            
            echo json_encode($result);
            break;
            
        // ===== USER MANAGEMENT FUNCTIONS =====
        
        case 'criar_usuario':
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipoUsuario = $_POST['tipo_usuario'] ?? 'cliente';
            $cpf = $_POST['cpf'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            
            if (empty($nome)) {
                throw new \Exception('Nome é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Criar usuário na tabela usuarios_globais
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
            
            // Se for um usuário interno (admin, cozinha, garcom, caixa, entregador)
            // criar também na tabela usuarios_estabelecimento
            if (in_array($tipoUsuario, ['admin', 'cozinha', 'garcom', 'caixa', 'entregador'])) {
                $db->insert('usuarios_estabelecimento', [
                    'usuario_global_id' => $usuarioId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'cargo' => ucfirst($tipoUsuario),
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário criado com sucesso!',
                'usuario_id' => $usuarioId
            ]);
            break;
            
        case 'listar_usuarios':
            try {
                $db = \System\Database::getInstance();
                $session = \System\Session::getInstance();
                $tenantId = $session->getTenantId() ?? 1;
                
                error_log("AJAX listar_usuarios - Tenant ID: " . $tenantId);
                
                // Buscar usuários do estabelecimento
                $usuarios = $db->fetchAll("
                    SELECT 
                        ug.id,
                        ug.nome,
                        ug.email,
                        ug.telefone,
                        ug.tipo_usuario,
                        ug.cpf,
                        ug.cnpj,
                        ug.endereco_completo,
                        ug.ativo,
                        ug.data_cadastro,
                        ue.cargo,
                        ue.ativo as ativo_estabelecimento
                    FROM usuarios_globais ug
                    LEFT JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id AND ue.tenant_id = ?
                    ORDER BY ug.nome
                ", [$tenantId]);
                
                error_log("AJAX listar_usuarios - Usuários encontrados: " . count($usuarios));
                
                echo json_encode([
                    'success' => true,
                    'usuarios' => $usuarios,
                    'count' => count($usuarios)
                ]);
            } catch (\Exception $e) {
                error_log("AJAX listar_usuarios - Erro: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao carregar usuários: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'buscar_cliente':
            $termo = $_POST['termo'] ?? '';
            
            if (empty($termo)) {
                throw new \Exception('Termo de busca é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            
            // Buscar clientes por nome, telefone ou CPF
            $clientes = $db->fetchAll("
                SELECT 
                    id,
                    nome,
                    email,
                    telefone,
                    cpf,
                    cnpj,
                    endereco_completo,
                    data_cadastro
                FROM usuarios_globais 
                WHERE tipo_usuario = 'cliente'
                AND (
                    nome ILIKE ? OR 
                    telefone ILIKE ? OR 
                    cpf ILIKE ? OR
                    email ILIKE ?
                )
                ORDER BY nome
                LIMIT 20
            ", ["%$termo%", "%$termo%", "%$termo%", "%$termo%"]);
            
            echo json_encode([
                'success' => true,
                'clientes' => $clientes
            ]);
            break;
            
        case 'editar_usuario':
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipoUsuario = $_POST['tipo_usuario'] ?? '';
            $cpf = $_POST['cpf'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            
            if ($usuarioId <= 0) {
                throw new \Exception('ID do usuário inválido');
            }
            
            if (empty($nome)) {
                throw new \Exception('Nome é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Verificar se o usuário existe
            $usuario = $db->fetch("SELECT * FROM usuarios_globais WHERE id = ?", [$usuarioId]);
            if (!$usuario) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Atualizar dados na tabela usuarios_globais
            $db->update('usuarios_globais', [
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'tipo_usuario' => $tipoUsuario,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'endereco_completo' => $endereco,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$usuarioId]);
            
            // Atualizar cargo na tabela usuarios_estabelecimento se for usuário interno
            if (in_array($tipoUsuario, ['admin', 'cozinha', 'garcom', 'caixa', 'entregador'])) {
                $db->update('usuarios_estabelecimento', [
                    'cargo' => ucfirst($tipoUsuario),
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'usuario_global_id = ? AND tenant_id = ?', [$usuarioId, $tenantId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso!'
            ]);
            break;
            
        case 'deletar_usuario':
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            
            if ($usuarioId <= 0) {
                throw new \Exception('ID do usuário inválido');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // Verificar se o usuário existe
            $usuario = $db->fetch("SELECT * FROM usuarios_globais WHERE id = ?", [$usuarioId]);
            if (!$usuario) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Não permitir deletar o próprio usuário admin
            if ($usuario['tipo_usuario'] === 'admin' && $usuario['id'] == 1) {
                throw new \Exception('Não é possível deletar o usuário administrador principal');
            }
            
            // Deletar da tabela usuarios_estabelecimento primeiro (FK constraint)
            $db->delete('usuarios_estabelecimento', 'usuario_global_id = ? AND tenant_id = ?', [$usuarioId, $tenantId]);
            
            // Deletar da tabela usuarios_globais
            $db->delete('usuarios_globais', 'id = ?', [$usuarioId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuário deletado com sucesso!'
            ]);
            break;
            
        case 'alterar_status_usuario':
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $novoStatus = $_POST['novo_status'] ?? '';
            
            if ($usuarioId <= 0) {
                throw new \Exception('ID do usuário inválido');
            }
            
            // Convert string values to proper boolean
            if ($novoStatus === 'true' || $novoStatus === '1' || $novoStatus === true) {
                $novoStatus = 'true';
            } elseif ($novoStatus === 'false' || $novoStatus === '0' || $novoStatus === false) {
                $novoStatus = 'false';
            } else {
                throw new \Exception('Status inválido: ' . $novoStatus);
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // Verificar se o usuário existe
            $usuario = $db->fetch("SELECT * FROM usuarios_globais WHERE id = ?", [$usuarioId]);
            if (!$usuario) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Não permitir desativar o próprio usuário admin principal
            if ($usuario['tipo_usuario'] === 'admin' && $usuario['id'] == 1 && $novoStatus === 'false') {
                throw new \Exception('Não é possível desativar o usuário administrador principal');
            }
            
            $statusBoolean = $novoStatus === 'true';
            
            // Atualizar status na tabela usuarios_globais
            $db->update('usuarios_globais', [
                'ativo' => $statusBoolean,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$usuarioId]);
            
            // Atualizar status na tabela usuarios_estabelecimento se existir
            $db->update('usuarios_estabelecimento', [
                'ativo' => $statusBoolean,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'usuario_global_id = ? AND tenant_id = ?', [$usuarioId, $tenantId]);
            
            $statusText = $statusBoolean ? 'ativado' : 'desativado';
            
            echo json_encode([
                'success' => true,
                'message' => "Usuário {$statusText} com sucesso!"
            ]);
            break;
            
        case 'buscar_usuario':
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            
            if ($usuarioId <= 0) {
                throw new \Exception('ID do usuário inválido');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            
            // Buscar dados completos do usuário
            $usuario = $db->fetch("
                SELECT 
                    ug.id,
                    ug.nome,
                    ug.email,
                    ug.telefone,
                    ug.tipo_usuario,
                    ug.cpf,
                    ug.cnpj,
                    ug.endereco_completo,
                    ug.ativo,
                    ug.data_cadastro,
                    ug.created_at,
                    ug.updated_at,
                    ue.cargo,
                    ue.ativo as ativo_estabelecimento
                FROM usuarios_globais ug
                LEFT JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id AND ue.tenant_id = ?
                WHERE ug.id = ?
            ", [$tenantId, $usuarioId]);
            
            if (!$usuario) {
                throw new \Exception('Usuário não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'usuario' => $usuario
            ]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
