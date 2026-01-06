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
            
            // Generate phone variations for search
            $telefoneVariacoes = [];
            $telefoneVariacoes[] = $telefoneNormalizado; // Original cleaned
            
            // Remove country code (55) if present
            $telefoneSemPais = $telefoneNormalizado;
            if (strlen($telefoneNormalizado) > 11 && substr($telefoneNormalizado, 0, 2) == '55') {
                $telefoneSemPais = substr($telefoneNormalizado, 2);
                if (!in_array($telefoneSemPais, $telefoneVariacoes)) {
                    $telefoneVariacoes[] = $telefoneSemPais;
                }
            }
            
            // Add with country code if not present and phone is valid length
            if (strlen($telefoneNormalizado) <= 11 && substr($telefoneNormalizado, 0, 2) != '55' && strlen($telefoneNormalizado) >= 10) {
                $telefoneComPais = '55' . $telefoneNormalizado;
                if (!in_array($telefoneComPais, $telefoneVariacoes)) {
                    $telefoneVariacoes[] = $telefoneComPais;
                }
            }
            
            error_log("clientes_cardapio_online::buscar_por_telefone - Telefone original: $telefone, Normalizado: $telefoneNormalizado");
            error_log("clientes_cardapio_online::buscar_por_telefone - Variações: " . implode(', ', $telefoneVariacoes));
            
            // Build query with multiple phone variations
            $placeholders = implode(',', array_fill(0, count($telefoneVariacoes), '?'));
            
            // Search for client by phone with multiple variations
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf 
                 FROM usuarios_globais 
                 WHERE (
                     telefone IN ($placeholders)
                     OR telefone LIKE ?
                     OR telefone LIKE ?
                     OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') IN ($placeholders)
                     OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                 )
                 AND (tipo_usuario = 'cliente' OR tipo_usuario IS NULL OR tipo_usuario = '')
                 AND ativo = true
                 LIMIT 1",
                array_merge(
                    $telefoneVariacoes, // telefone IN
                    ['%' . $telefoneNormalizado . '%', $telefoneNormalizado . '%'], // telefone LIKE
                    $telefoneVariacoes, // REPLACE telefone IN
                    ['%' . $telefoneNormalizado . '%'] // REPLACE telefone LIKE
                )
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
                // Cliente não encontrado - retornar sucesso false (não criar automaticamente na busca)
                echo json_encode([
                    'success' => false,
                    'message' => 'Cliente não encontrado',
                    'cliente' => null
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
                // Get full client data
                $cliente = $db->fetch(
                    "SELECT id, nome, telefone, email, cpf FROM usuarios_globais WHERE id = ?",
                    [$clienteExistente['id']]
                );
                
                // Update client data if provided
                $updateData = [];
                if (!empty($nome)) $updateData['nome'] = $nome;
                if (!empty($email)) $updateData['email'] = $email;
                if (!empty($cpf)) $updateData['cpf'] = $cpf;
                
                if (!empty($updateData)) {
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('usuarios_globais', $updateData, 'id = ?', [$clienteExistente['id']]);
                    // Reload client data
                    $cliente = $db->fetch(
                        "SELECT id, nome, telefone, email, cpf FROM usuarios_globais WHERE id = ?",
                        [$clienteExistente['id']]
                    );
                }
                
                // Get addresses
                $enderecos = $clienteModel->getEnderecos($clienteExistente['id']);
                
                echo json_encode([
                    'success' => true,
                    'cliente' => $cliente,
                    'enderecos' => $enderecos,
                    'message' => 'Cliente já cadastrado'
                ]);
                break;
            }
            
            // Create new client
            $telefoneNormalizado = preg_replace('/[^0-9]/', '', $telefone);
            $clienteId = $db->insert('usuarios_globais', [
                'nome' => $nome,
                'telefone' => $telefoneNormalizado,
                'email' => $email ?: null,
                'cpf' => $cpf ?: null,
                'tipo_usuario' => 'cliente',
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$clienteId) {
                error_log("Erro ao inserir cliente: nome=$nome, telefone=$telefoneNormalizado");
                throw new Exception('Erro ao cadastrar cliente no banco de dados');
            }
            
            // Get created client
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf FROM usuarios_globais WHERE id = ?",
                [$clienteId]
            );
            
            if (!$cliente) {
                error_log("Cliente criado mas não encontrado: ID=$clienteId");
                throw new Exception('Cliente criado mas não foi possível recuperar os dados');
            }
            
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
            
            // Handle FormData array notation (endereco[logradouro], endereco[cidade], etc.)
            $enderecoData = [];
            if (isset($_POST['endereco']) && is_array($_POST['endereco'])) {
                $enderecoData = $_POST['endereco'];
            } else {
                // Try to build from individual fields
                $enderecoData = [
                    'logradouro' => $_POST['endereco']['logradouro'] ?? $_POST['logradouro'] ?? null,
                    'numero' => $_POST['endereco']['numero'] ?? $_POST['numero'] ?? null,
                    'bairro' => $_POST['endereco']['bairro'] ?? $_POST['bairro'] ?? null,
                    'cidade' => $_POST['endereco']['cidade'] ?? $_POST['cidade'] ?? null,
                    'estado' => $_POST['endereco']['estado'] ?? $_POST['estado'] ?? null,
                    'cep' => $_POST['endereco']['cep'] ?? $_POST['cep'] ?? null,
                ];
            }
            
            if (!$clienteId) {
                throw new Exception('Cliente ID é obrigatório');
            }
            
            // Validate minimum required fields
            $logradouro = $enderecoData['logradouro'] ?? $enderecoData['endereco'] ?? null;
            $cidade = $enderecoData['cidade'] ?? null;
            
            if (empty($logradouro) || empty($cidade)) {
                error_log("Erro ao adicionar endereço - dados incompletos. Cliente ID: $clienteId, Dados recebidos: " . json_encode($_POST));
                throw new Exception('Endereço e cidade são obrigatórios');
            }
            
            // Log received data for debugging
            error_log("Adicionar endereço - Cliente ID: $clienteId, Dados recebidos: " . json_encode($_POST));
            error_log("Endereço processado: " . json_encode($enderecoData));
            
            // Prepare data ensuring boolean values are proper booleans, not strings
            // Convert empty strings to null for optional fields
            $insertData = [
                'usuario_global_id' => (int)$clienteId,
                'tenant_id' => (int)$tenantId,
                'tipo' => 'entrega',
                'logradouro' => $logradouro,
                'cidade' => $cidade,
                'pais' => 'Brasil',
                'principal' => false, // Explicit boolean
                'ativo' => true, // Explicit boolean
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add optional fields only if they have values
            if (!empty($enderecoData['cep'])) {
                $insertData['cep'] = $enderecoData['cep'];
            }
            if (!empty($enderecoData['numero'])) {
                $insertData['numero'] = $enderecoData['numero'];
            }
            if (!empty($enderecoData['complemento'])) {
                $insertData['complemento'] = $enderecoData['complemento'];
            }
            if (!empty($enderecoData['bairro'])) {
                $insertData['bairro'] = $enderecoData['bairro'];
            }
            if (!empty($enderecoData['estado'])) {
                $insertData['estado'] = $enderecoData['estado'];
            }
            if (!empty($enderecoData['referencia'])) {
                $insertData['referencia'] = $enderecoData['referencia'];
            }
            
            $enderecoId = $db->insert('enderecos', $insertData);
            
            if (!$enderecoId) {
                error_log("Erro ao inserir endereço no banco. Cliente ID: $clienteId");
                throw new Exception('Erro ao adicionar endereço no banco de dados');
            }
            
            // Get updated addresses
            $enderecos = $clienteModel->getEnderecos($clienteId);
            
            echo json_encode([
                'success' => true,
                'enderecos' => $enderecos,
                'message' => 'Endereço adicionado com sucesso'
            ]);
            break;
            
        case 'atualizar_dados':
            $clienteId = $_POST['cliente_id'] ?? null;
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $cpf = $_POST['cpf'] ?? '';
            
            if (!$clienteId) {
                throw new Exception('Cliente ID é obrigatório');
            }
            
            // Verify client exists
            $clienteExistente = $db->fetch(
                "SELECT id FROM usuarios_globais WHERE id = ?",
                [$clienteId]
            );
            
            if (!$clienteExistente) {
                throw new Exception('Cliente não encontrado');
            }
            
            // Build update data - always update nome if provided
            $updateData = ['updated_at' => date('Y-m-d H:i:s')];
            if (!empty($nome)) {
                $updateData['nome'] = $nome;
            }
            // Always update email (can be empty/null)
            $updateData['email'] = !empty($email) ? $email : null;
            // Always update cpf (can be empty/null)
            $updateData['cpf'] = !empty($cpf) ? $cpf : null;
            
            // Update client
            $db->update('usuarios_globais', $updateData, 'id = ?', [$clienteId]);
            
            // Get updated client
            $cliente = $db->fetch(
                "SELECT id, nome, telefone, email, cpf FROM usuarios_globais WHERE id = ?",
                [$clienteId]
            );
            
            echo json_encode([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Dados atualizados com sucesso'
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

