<?php
// Start output buffering to prevent any output before JSON
ob_start();

// Capturar todos os erros e retornar como JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ERRO FATAL: " . json_encode($error));
        ob_clean();
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

// Autoloader do Composer (necessário para AWS SDK)
require_once __DIR__ . '/../../vendor/autoload.php';

// Simples e direto - usar require_once
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/WhatsApp/BaileysManager.php';
require_once __DIR__ . '/../../system/Middleware/SubscriptionCheck.php';
require_once __DIR__ . '/../../system/Storage/MinIO.php';

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
            $filialId = $session->getFilialId();
            
            // Salvar cor_primaria isolada por filial na tabela filial_settings
            $db->query("
                INSERT INTO filial_settings (tenant_id, filial_id, setting_key, setting_value, updated_at)
                VALUES (?, ?, 'cor_primaria', ?, CURRENT_TIMESTAMP)
                ON CONFLICT (tenant_id, filial_id, setting_key) 
                DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
            ", [$tenantId, $filialId, $corPrimaria]);
            
            // Atualizar nome do tenant (nome é global para o tenant)
            $db->update(
                'tenants',
                ['nome' => $nomeEstabelecimento],
                'id = ?',
                [$tenantId]
            );
            
            // Atualizar sessão
            $tenant = $session->getTenant();
            $tenant['nome'] = $nomeEstabelecimento;
            $session->setTenant($tenant);
            
            echo json_encode(['success' => true, 'message' => 'Configurações de aparência salvas com sucesso!']);
            break;
            
        case 'salvar_estabelecimento':
            $nome = $_POST['nome'] ?? '';
            $endereco = $_POST['endereco'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $email = $_POST['email'] ?? '';
            $cnpj = $_POST['cnpj'] ?? '';
            
            if (empty($nome)) {
                throw new \Exception('Nome do estabelecimento é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            if (!$filialId) {
                throw new \Exception('Filial não encontrada na sessão');
            }
            
            // Atualizar informações da filial
            $db->update(
                'filiais',
                [
                    'nome' => $nome,
                    'endereco' => $endereco ?: null,
                    'telefone' => $telefone ?: null,
                    'email' => $email ?: null,
                    'cnpj' => $cnpj ?: null,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ? AND tenant_id = ?',
                [$filialId, $tenantId]
            );
            
            // Atualizar sessão
            $filial = $session->getFilial();
            if ($filial) {
                $filial['nome'] = $nome;
                $filial['endereco'] = $endereco;
                $filial['telefone'] = $telefone;
                $filial['email'] = $email;
                $filial['cnpj'] = $cnpj;
                $session->setFilial($filial);
            }
            
            echo json_encode(['success' => true, 'message' => 'Informações do estabelecimento salvas com sucesso!']);
            break;
            
        case 'salvar_mesas':
            $numeroMesas = (int) ($_POST['numero_mesas'] ?? 0);
            $capacidadeMesa = (int) ($_POST['capacidade_mesa'] ?? 0);
            
            if ($numeroMesas <= 0 || $capacidadeMesa <= 0) {
                throw new \Exception('Número de mesas e capacidade devem ser maiores que zero');
            }
            
            $db = \System\Database::getInstance();
            
            // Usar valores da sessão
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId();
            
            // Se não há filial específica, usar filial padrão do tenant
            if ($filialId === null) {
                $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
                $filialId = $filial_padrao ? $filial_padrao['id'] : null;
            }
            
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
            
            // Usar tenant_id e filial_id da sessão atual
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId();
            
            error_log("AJAX listar_caixas_entrada - Tenant ID: " . $tenantId . ", Filial ID: " . ($filialId ?? 'NULL'));
            
            $baileysManager = new \System\WhatsApp\BaileysManager();
            $instancias = $baileysManager->getInstances($tenantId, $filialId);
            
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
            // VERIFICAÇÃO DE ASSINATURA - Bloquear criação se trial expirado ou fatura vencida
            if (!\System\Middleware\SubscriptionCheck::canPerformCriticalAction()) {
                $status = \System\Middleware\SubscriptionCheck::checkSubscriptionStatus();
                throw new Exception($status['message'] . ' Para criar usuários, regularize sua situação.');
            }
            
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
                    'tipo_usuario' => $tipoUsuario,
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
                $filialId = $session->getFilialId();
                
                error_log("AJAX listar_usuarios - Tenant ID: $tenantId, Filial ID: " . ($filialId ?? 'NULL'));
                
                // Buscar apenas usuários internos da filial específica (funcionários, não clientes)
                $usuarios = $db->fetchAll("
                    SELECT 
                        ug.id,
                        ug.nome,
                        ug.email,
                        ug.telefone,
                        ue.tipo_usuario,
                        ue.cargo,
                        CASE WHEN ue.ativo = true OR ue.ativo IS NULL THEN 'Ativo' ELSE 'Inativo' END as status,
                        COALESCE(ue.ativo, true) as ativo
                    FROM usuarios_globais ug
                    INNER JOIN usuarios_estabelecimento ue ON ug.id = ue.usuario_global_id
                    WHERE ue.tenant_id = ? 
                    AND ue.filial_id = ?
                    AND ue.tipo_usuario IN ('admin', 'cozinha', 'garcom', 'caixa', 'entregador')
                    ORDER BY ug.nome
                ", [$tenantId, $filialId]);
                
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
            
            // Atualizar cargo e tipo_usuario na tabela usuarios_estabelecimento se for usuário interno
            if (in_array($tipoUsuario, ['admin', 'cozinha', 'garcom', 'caixa', 'entregador'])) {
                $db->update('usuarios_estabelecimento', [
                    'tipo_usuario' => $tipoUsuario, // Atualizar tipo_usuario
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
            
            error_log("AJAX alterar_status_usuario - usuarioId: " . $usuarioId . ", novoStatus: '" . $novoStatus . "'");
            
            if ($usuarioId <= 0) {
                throw new \Exception('ID do usuário inválido');
            }
            
            // Validate and convert status
            if (empty($novoStatus)) {
                throw new \Exception('Status não pode estar vazio');
            }
            
            // Convert string values to proper boolean
            if ($novoStatus === 'true' || $novoStatus === '1' || $novoStatus === true) {
                $novoStatus = 'true';
            } elseif ($novoStatus === 'false' || $novoStatus === '0' || $novoStatus === false) {
                $novoStatus = 'false';
            } else {
                throw new \Exception('Status inválido: "' . $novoStatus . '" (tipo: ' . gettype($novoStatus) . ')');
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
            
            error_log("AJAX buscar_usuario - usuarioId: " . $usuarioId);
            
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
            
        case 'salvar_cardapio_online':
            // Clear any previous output
            ob_clean();
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            error_log("=== SALVAR CARDAPIO ONLINE ===");
            error_log("Tenant ID: " . $tenantId);
            error_log("Filial ID: " . $filialId);
            error_log("POST data: " . json_encode($_POST));
            
            if (!$tenantId || !$filialId) {
                throw new \Exception('Tenant ou Filial não encontrados na sessão');
            }
            
            // Verify filial exists
            $filialCheck = $db->fetch(
                "SELECT id, nome, cardapio_online_ativo FROM filiais WHERE id = ? AND tenant_id = ?",
                [$filialId, $tenantId]
            );
            
            if (!$filialCheck) {
                error_log("ERRO: Filial não encontrada - ID: $filialId, Tenant: $tenantId");
                throw new \Exception('Filial não encontrada');
            }
            
            error_log("Filial encontrada: " . json_encode($filialCheck));
            
            // Handle logo upload
            $logoUrl = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['logo'];
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($uploadedFile['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new \Exception('Tipo de arquivo não suportado. Use JPG, PNG, GIF ou WEBP');
                }
                
                // Validate file size (max 2MB)
                if ($uploadedFile['size'] > 2 * 1024 * 1024) {
                    throw new \Exception('Arquivo muito grande (máximo 2MB)');
                }
                
                // Upload para MinIO
                try {
                    $minio = \System\Storage\MinIO::getInstance();
                    $logoUrl = $minio->uploadFile($uploadedFile, 'logos');
                } catch (\Exception $e) {
                    error_log('Erro ao fazer upload do logo para MinIO: ' . $e->getMessage());
                    throw new \Exception('Erro ao fazer upload do logo: ' . $e->getMessage());
                }
            }
            
            // Prepare update data
            $cardapioAtivo = ($_POST['cardapio_online_ativo'] ?? '0') === '1';
            $usarCalculo = ($_POST['usar_calculo_distancia'] ?? '0') === '1';
            
            error_log("Valores recebidos:");
            error_log("  cardapio_online_ativo (POST): " . ($_POST['cardapio_online_ativo'] ?? 'não definido'));
            error_log("  cardapio_online_ativo (bool): " . ($cardapioAtivo ? 'true' : 'false'));
            error_log("  usar_calculo_distancia (POST): " . ($_POST['usar_calculo_distancia'] ?? 'não definido'));
            error_log("  usar_calculo_distancia (bool): " . ($usarCalculo ? 'true' : 'false'));
            
            // Parse opening hours
            $horarioFuncionamento = null;
            if (isset($_POST['horario_funcionamento'])) {
                $horarioFuncionamento = json_decode($_POST['horario_funcionamento'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Erro ao decodificar horario_funcionamento: " . json_last_error_msg());
                    $horarioFuncionamento = null;
                }
            }
            
            $updateData = [
                'cardapio_online_ativo' => $cardapioAtivo,
                'taxa_delivery_fixa' => floatval($_POST['taxa_delivery_fixa'] ?? 0),
                'usar_calculo_distancia' => $usarCalculo,
                'raio_entrega_km' => floatval($_POST['raio_entrega_km'] ?? 5),
                'tempo_medio_preparo' => intval($_POST['tempo_medio_preparo'] ?? 30),
                'aceita_pagamento_online' => ($_POST['aceita_pagamento_online'] ?? '0') === '1',
                'aceita_pagamento_na_hora' => ($_POST['aceita_pagamento_na_hora'] ?? '0') === '1',
                'dominio_cardapio_online' => trim($_POST['dominio_cardapio_online'] ?? ''),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add opening hours if provided
            if ($horarioFuncionamento !== null) {
                $updateData['horario_funcionamento'] = json_encode($horarioFuncionamento);
            }
            
            error_log("Update data preparado: " . json_encode($updateData));
            
            // Add logo URL if uploaded
            if ($logoUrl) {
                $updateData['logo_url'] = $logoUrl;
            }
            
            // Build SQL query manually to ensure proper boolean handling
            $setParts = [];
            $params = [];
            
            foreach ($updateData as $key => $value) {
                if (is_bool($value)) {
                    $setParts[] = "{$key} = " . ($value ? 'true' : 'false');
                } elseif ($key === 'horario_funcionamento' && is_string($value)) {
                    // JSONB field needs to be cast
                    $setParts[] = "{$key} = ?::jsonb";
                    $params[] = $value;
                } else {
                    $setParts[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            
            $sql = "UPDATE filiais SET " . implode(', ', $setParts) . " WHERE id = ? AND tenant_id = ?";
            $params[] = $filialId;
            $params[] = $tenantId;
            
            error_log("SQL a executar: " . $sql);
            error_log("Parâmetros: " . json_encode($params));
            
            $stmt = $db->query($sql, $params);
            $rowsAffected = $stmt->rowCount();
            
            error_log("Linhas afetadas: " . $rowsAffected);
            
            if ($rowsAffected === 0) {
                // Verify if filial still exists
                $filialAfter = $db->fetch(
                    "SELECT id, nome, cardapio_online_ativo, usar_calculo_distancia FROM filiais WHERE id = ? AND tenant_id = ?",
                    [$filialId, $tenantId]
                );
                error_log("Filial após tentativa de update: " . json_encode($filialAfter));
                throw new \Exception('Nenhuma linha foi atualizada. Verifique se a filial existe e você tem permissão.');
            }
            
            // Reload filial from database to update session cache
            $filialAtualizada = $db->fetch(
                "SELECT * FROM filiais WHERE id = ? AND tenant_id = ?",
                [$filialId, $tenantId]
            );
            
            if ($filialAtualizada) {
                // Update session with fresh data from database
                $session->setFilial($filialAtualizada);
                error_log("Sessão atualizada com dados do banco");
            }
            
            // Final verification
            $filialFinal = $db->fetch(
                "SELECT cardapio_online_ativo, usar_calculo_distancia FROM filiais WHERE id = ? AND tenant_id = ?",
                [$filialId, $tenantId]
            );
            
            error_log("Valores finais salvos:");
            error_log("  cardapio_online_ativo: " . ($filialFinal['cardapio_online_ativo'] ? 'true' : 'false'));
            error_log("  usar_calculo_distancia: " . ($filialFinal['usar_calculo_distancia'] ? 'true' : 'false'));
            
            $response = [
                'success' => true,
                'message' => 'Configurações do cardápio online salvas com sucesso!',
                'debug' => [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'cardapio_online_ativo' => $filialFinal['cardapio_online_ativo'],
                    'usar_calculo_distancia' => $filialFinal['usar_calculo_distancia']
                ]
            ];
            
            error_log("Enviando resposta: " . json_encode($response));
            echo json_encode($response);
            break;
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    ob_clean();
    error_log("Erro em configuracoes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// End output buffering and send JSON
ob_end_flush();
?>
