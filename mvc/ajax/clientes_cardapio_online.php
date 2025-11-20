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
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
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
            
            // Search for client by phone
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf 
                 FROM usuarios_globais 
                 WHERE telefone = ? OR telefone LIKE ? OR telefone LIKE ? 
                 LIMIT 1",
                [$telefone, '%' . $telefoneNormalizado . '%', $telefoneNormalizado . '%']
            );
            
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
            
            // Check if client already exists
            $clienteExistente = $db->fetch(
                "SELECT id FROM usuarios_globais WHERE telefone = ? LIMIT 1",
                [preg_replace('/[^0-9]/', '', $telefone)]
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
            throw new Exception('Ação não encontrada');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

