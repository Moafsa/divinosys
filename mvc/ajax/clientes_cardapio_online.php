<?php
/**
 * Public API endpoint for online menu customer operations
 * Does not require authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../model/Cliente.php';

try {
    $db = \System\Database::getInstance();
    $clienteModel = new \MVC\Model\Cliente();
    
    // Get action from URL or POST - handle multiple action name variations
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Map action names to internal actions
    $actionMap = [
        'buscar_cliente_cardapio' => 'buscar_por_telefone',
        'cadastrar_cliente_cardapio' => 'cadastrar',
        'adicionar_endereco_cardapio' => 'adicionar_endereco'
    ];
    
    if (isset($actionMap[$action])) {
        $action = $actionMap[$action];
    }
    
    error_log("clientes_cardapio_online - Action recebida: " . ($_GET['action'] ?? $_POST['action'] ?? '') . " -> Mapeada para: $action");
    
    $tenantId = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? null;
    
    if (!$tenantId) {
        throw new Exception('Tenant ID é obrigatório');
    }
    
    switch ($action) {
        case 'buscar_por_telefone':
            $telefone = $_GET['telefone'] ?? $_POST['telefone'] ?? '';
            
            if (empty($telefone)) {
                throw new Exception('Telefone é obrigatório');
            }
            
            // Normalize phone number (remove non-numeric characters)
            $telefoneNormalizado = preg_replace('/[^0-9]/', '', $telefone);
            
            error_log("clientes_cardapio_online::buscar_por_telefone - Telefone original: $telefone, Normalizado: $telefoneNormalizado");
            
            // Search for client by phone (include clients with tipo_usuario = 'cliente' or NULL or empty for backward compatibility)
            // Try multiple search patterns to handle different phone formats
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf 
                 FROM usuarios_globais 
                 WHERE (
                     telefone = ? 
                     OR telefone = ?
                     OR telefone LIKE ?
                     OR telefone LIKE ?
                     OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
                 )
                 AND (tipo_usuario = 'cliente' OR tipo_usuario IS NULL OR tipo_usuario = '')
                 AND ativo = true
                 LIMIT 1",
                [
                    $telefone, 
                    $telefoneNormalizado,
                    '%' . $telefoneNormalizado . '%', 
                    $telefoneNormalizado . '%',
                    $telefoneNormalizado
                ]
            );
            
            error_log("clientes_cardapio_online::buscar_por_telefone - Cliente encontrado: " . ($cliente ? json_encode($cliente) : 'null'));
            
            if ($cliente) {
                // Get client addresses
                $enderecos = $clienteModel->getEnderecos($cliente['id']);
                
                echo json_encode([
                    'success' => true,
                    'cliente' => $cliente,
                    'enderecos' => $enderecos
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cliente não encontrado',
                    'cliente' => null,
                    'enderecos' => []
                ]);
            }
            break;
            
        case 'cadastrar':
            $nome = $_POST['nome'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $email = $_POST['email'] ?? '';
            $cpf = $_POST['cpf'] ?? '';
            
            if (empty($nome) || empty($telefone)) {
                throw new Exception('Nome e telefone são obrigatórios');
            }
            
            // Normalize phone for search
            $telefoneNormalizado = preg_replace('/[^0-9]/', '', $telefone);
            
            // Check if client already exists (try multiple formats)
            $clienteExistente = $db->fetch(
                "SELECT id FROM usuarios_globais 
                 WHERE (
                     telefone = ? 
                     OR telefone = ?
                     OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
                 )
                 LIMIT 1",
                [$telefone, $telefoneNormalizado, $telefoneNormalizado]
            );
            
            if ($clienteExistente) {
                echo json_encode([
                    'success' => true,
                    'cliente' => $clienteExistente,
                    'message' => 'Cliente já cadastrado'
                ]);
                break;
            }
            
            // Create new client
            $clienteId = $db->insert('usuarios_globais', [
                'nome' => $nome,
                'telefone' => preg_replace('/[^0-9]/', '', $telefone),
                'email' => $email ?: null,
                'cpf' => $cpf ?: null,
                'tipo_usuario' => 'cliente',
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$clienteId) {
                throw new Exception('Erro ao cadastrar cliente');
            }
            
            // Get created client
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf FROM usuarios_globais WHERE id = ?",
                [$clienteId]
            );
            
            // Add address if provided
            $enderecoId = null;
            $enderecoJson = $_POST['endereco'] ?? null;
            if ($enderecoJson) {
                // Handle both JSON string and array
                if (is_string($enderecoJson)) {
                    $enderecoData = json_decode($enderecoJson, true);
                } else {
                    $enderecoData = $enderecoJson;
                }
                
                if (is_array($enderecoData) && !empty($enderecoData)) {
                    $enderecoId = $db->insert('enderecos', [
                        'usuario_global_id' => $clienteId,
                        'tenant_id' => $tenantId,
                        'tipo' => 'entrega',
                        'cep' => $enderecoData['cep'] ?? null,
                        'logradouro' => $enderecoData['logradouro'] ?? $enderecoData['endereco'] ?? null,
                        'numero' => $enderecoData['numero'] ?? null,
                        'complemento' => $enderecoData['complemento'] ?? null,
                        'bairro' => $enderecoData['bairro'] ?? null,
                        'cidade' => $enderecoData['cidade'] ?? null,
                        'estado' => $enderecoData['estado'] ?? null,
                        'pais' => 'Brasil',
                        'referencia' => $enderecoData['referencia'] ?? null,
                        'principal' => true,
                        'ativo' => true,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Get addresses
            $enderecos = $clienteModel->getEnderecos($clienteId);
            
            echo json_encode([
                'success' => true,
                'cliente' => $cliente,
                'enderecos' => $enderecos,
                'message' => 'Cliente cadastrado com sucesso'
            ]);
            break;
            
        case 'adicionar_endereco':
        case 'adicionar_endereco_cardapio':
            $clienteId = $_POST['cliente_id'] ?? null;
            $enderecoData = $_POST['endereco'] ?? [];
            
            if (!$clienteId || empty($enderecoData)) {
                throw new Exception('Cliente ID e dados do endereço são obrigatórios');
            }
            
            $enderecoId = $db->insert('enderecos', [
                'usuario_global_id' => $clienteId,
                'tenant_id' => $tenantId,
                'tipo' => 'entrega',
                'cep' => $enderecoData['cep'] ?? null,
                'logradouro' => $enderecoData['logradouro'] ?? $enderecoData['endereco'] ?? null,
                'numero' => $enderecoData['numero'] ?? null,
                'complemento' => $enderecoData['complemento'] ?? null,
                'bairro' => $enderecoData['bairro'] ?? null,
                'cidade' => $enderecoData['cidade'] ?? null,
                'estado' => $enderecoData['estado'] ?? null,
                'pais' => 'Brasil',
                'referencia' => $enderecoData['referencia'] ?? null,
                'principal' => false,
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$enderecoId) {
                throw new Exception('Erro ao adicionar endereço');
            }
            
            // Get updated addresses
            $enderecos = $clienteModel->getEnderecos($clienteId);
            
            echo json_encode([
                'success' => true,
                'enderecos' => $enderecos,
                'message' => 'Endereço adicionado com sucesso'
            ]);
            break;
            
        default:
            // Log unknown action for debugging
            error_log("clientes_cardapio_online - Ação desconhecida: $action");
            throw new Exception('Ação não encontrada: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

