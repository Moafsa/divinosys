<?php

require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/EvolutionAPI.php';

use System\Database;
use System\EvolutionAPI;

// Inicializar sistema
$config = \System\Config::getInstance();
$db = Database::getInstance();
EvolutionAPI::init();

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
        case 'criar_instancia':
            $tenantId = $_POST['tenant_id'] ?? '';
            $filialId = $_POST['filial_id'] ?? null;
            $nomeInstancia = $_POST['nome_instancia'] ?? '';
            $numeroTelefone = $_POST['numero_telefone'] ?? '';
            
            if (empty($tenantId) || empty($nomeInstancia) || empty($numeroTelefone)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            $result = EvolutionAPI::createInstance($tenantId, $filialId, $nomeInstancia, $numeroTelefone);
            
            echo json_encode($result);
            break;
            
        case 'obter_qrcode':
            $instanceName = $_POST['instance_name'] ?? '';
            
            if (empty($instanceName)) {
                throw new Exception('Nome da instância é obrigatório');
            }
            
            $result = EvolutionAPI::getQRCode($instanceName);
            
            echo json_encode($result);
            break;
            
        case 'verificar_status':
            $instanceName = $_POST['instance_name'] ?? '';
            
            if (empty($instanceName)) {
                throw new Exception('Nome da instância é obrigatório');
            }
            
            $result = EvolutionAPI::getInstanceStatus($instanceName);
            
            echo json_encode($result);
            break;
            
        case 'enviar_mensagem':
            $instanceName = $_POST['instance_name'] ?? '';
            $numero = $_POST['numero'] ?? '';
            $mensagem = $_POST['mensagem'] ?? '';
            
            if (empty($instanceName) || empty($numero) || empty($mensagem)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            $result = EvolutionAPI::sendMessage($instanceName, $numero, $mensagem);
            
            echo json_encode($result);
            break;
            
        case 'enviar_lgpd':
            $nome = $_POST['nome'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $estancia = $_POST['estancia'] ?? '';
            $mensagem = $_POST['mensagem'] ?? '';
            
            if (empty($nome) || empty($telefone) || empty($estancia) || empty($mensagem)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            $result = EvolutionAPI::sendLGPDMessage($nome, $telefone, $estancia, $mensagem);
            
            echo json_encode($result);
            break;
            
        case 'listar_instancias':
            // Obter tenant_id da sessão do usuário logado
            session_start();
            $tenantId = $_SESSION['tenant_id'] ?? '1';
            $filialId = $_POST['filial_id'] ?? $_GET['filial_id'] ?? null;
            
            $instancias = EvolutionAPI::getInstances($tenantId, $filialId);
            
            echo json_encode([
                'success' => true,
                'instancias' => $instancias
            ]);
            break;
            
        case 'deletar_instancia':
            $instanceName = $_POST['instance_name'] ?? '';
            
            if (empty($instanceName)) {
                throw new Exception('Nome da instância é obrigatório');
            }
            
            $result = EvolutionAPI::deleteInstance($instanceName);
            
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    error_log("Erro na Evolution API: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
