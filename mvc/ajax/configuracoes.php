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
            
        default:
            throw new \Exception('Ação não encontrada: ' . $action);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
