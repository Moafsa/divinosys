<?php

namespace MVC\Controller;

use System\Session;

class ClienteController
{
    private $clienteModel;
    private $session;

    public function __construct()
    {
        require_once __DIR__ . '/../model/Cliente.php';
        $this->clienteModel = new \MVC\Model\Cliente();
        $this->session = Session::getInstance();
    }

    /**
     * Handle AJAX requests
     */
    public function handleRequest()
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'listar':
                return $this->listar();
            case 'buscar':
                return $this->buscar();
            case 'criar':
                return $this->criar();
            case 'atualizar':
                return $this->atualizar();
            case 'buscar_por_telefone':
                return $this->buscarPorTelefone();
            case 'historico_pedidos':
                return $this->getHistoricoPedidos();
            case 'historico_pagamentos':
                return $this->getHistoricoPagamentos();
            case 'estabelecimentos':
                return $this->getEstabelecimentos();
            case 'detalhes_pedido':
                return $this->getDetalhesPedido();
            case 'estatisticas':
                return $this->getEstatisticas();
            case 'enderecos':
                return $this->getEnderecos();
            case 'adicionar_endereco':
                return $this->adicionarEndereco();
            case 'atualizar_endereco':
                return $this->atualizarEndereco();
            case 'remover_endereco':
                return $this->removerEndereco();
            case 'preferencias':
                return $this->getPreferencias();
            case 'atualizar_preferencias':
                return $this->atualizarPreferencias();
            case 'desativar':
                return $this->desativar();
            default:
                return $this->jsonResponse(['success' => false, 'message' => 'Ação não encontrada']);
        }
    }

    /**
     * List all clients
     */
    private function listar()
    {
        try {
            $filters = [
                'search' => $_GET['search'] ?? '',
                'tenant_id' => $this->session->getTenant()['id'] ?? null
            ];

            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $clientes = $this->clienteModel->getAll($filters, $limit, $offset);

            return $this->jsonResponse([
                'success' => true,
                'data' => $clientes,
                'total' => count($clientes)
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Search clients
     */
    private function buscar()
    {
        try {
            $term = $_GET['term'] ?? '';
            $tenantId = $this->session->getTenant()['id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 10);

            if (empty($term)) {
                return $this->jsonResponse(['success' => false, 'message' => 'Termo de busca não informado']);
            }

            $clientes = $this->clienteModel->search($term, $tenantId, $limit);

            return $this->jsonResponse([
                'success' => true,
                'data' => $clientes
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Create new client
     */
    private function criar()
    {
        try {
            $data = [
                'nome' => $_POST['nome'] ?? '',
                'telefone' => $_POST['telefone'] ?? null,
                'email' => $_POST['email'] ?? null,
                'cpf' => $_POST['cpf'] ?? null,
                'data_nascimento' => $_POST['data_nascimento'] ?? null,
                'telefone_secundario' => $_POST['telefone_secundario'] ?? null,
                'observacoes' => $_POST['observacoes'] ?? null
            ];

            if (empty($data['nome'])) {
                return $this->jsonResponse(['success' => false, 'message' => 'Nome é obrigatório']);
            }

            $result = $this->clienteModel->create($data);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update client
     */
    private function atualizar()
    {
        try {
            error_log("ClienteController::atualizar - POST data: " . print_r($_POST, true));
            
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                error_log("ClienteController::atualizar - ID vazio");
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $data = [
                'nome' => $_POST['nome'] ?? '',
                'telefone' => $_POST['telefone'] ?? null,
                'email' => $_POST['email'] ?? null,
                'cpf' => $_POST['cpf'] ?? null,
                'data_nascimento' => $_POST['data_nascimento'] ?? null,
                'telefone_secundario' => $_POST['telefone_secundario'] ?? null,
                'observacoes' => $_POST['observacoes'] ?? null
            ];

            error_log("ClienteController::atualizar - Data to update: " . print_r($data, true));
            
            $result = $this->clienteModel->update($id, $data);
            
            error_log("ClienteController::atualizar - Result: " . print_r($result, true));
            
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            error_log("ClienteController::atualizar - Exception: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Find client by phone
     */
    private function buscarPorTelefone()
    {
        try {
            $telefone = $_GET['telefone'] ?? '';
            error_log("ClienteController::buscarPorTelefone - Telefone recebido: " . $telefone);
            
            if (empty($telefone)) {
                error_log("ClienteController::buscarPorTelefone - Telefone vazio");
                return $this->jsonResponse(['success' => false, 'message' => 'Telefone não informado']);
            }

            $cliente = $this->clienteModel->findByTelefone($telefone);
            error_log("ClienteController::buscarPorTelefone - Cliente encontrado: " . ($cliente ? 'sim' : 'não'));
            
            if ($cliente) {
                return $this->jsonResponse([
                    'success' => true,
                    'cliente' => $cliente,
                    'message' => 'Cliente encontrado'
                ]);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ]);
            }
        } catch (Exception $e) {
            error_log("ClienteController::buscarPorTelefone - Erro: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get client order history
     */
    private function getHistoricoPedidos()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 20);

            $pedidos = $this->clienteModel->getHistoricoPedidos($clienteId, $tenantId, $limit);

            return $this->jsonResponse([
                'success' => true,
                'data' => $pedidos
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get client payment history
     */
    private function getHistoricoPagamentos()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 20);

            $pagamentos = $this->clienteModel->getHistoricoPagamentos($clienteId, $tenantId, $limit);

            return $this->jsonResponse([
                'success' => true,
                'data' => $pagamentos
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get establishments visited by client
     */
    private function getEstabelecimentos()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $estabelecimentos = $this->clienteModel->getEstabelecimentosVisitados($clienteId);

            return $this->jsonResponse([
                'success' => true,
                'data' => $estabelecimentos
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get client statistics
     */
    private function getEstatisticas()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $estatisticas = $this->clienteModel->getEstatisticas($clienteId, $tenantId);

            return $this->jsonResponse([
                'success' => true,
                'data' => $estatisticas
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get client addresses
     */
    private function getEnderecos()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $enderecos = $this->clienteModel->getEnderecos($clienteId);

            return $this->jsonResponse([
                'success' => true,
                'data' => $enderecos
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Sanitize CEP to ensure it doesn't exceed 10 characters
     * Removes spaces and keeps only numbers and hyphen
     * Format: 12345-678 (max 10 characters)
     */
    private function sanitizeCEP($cep)
    {
        if (empty($cep)) {
            return null;
        }
        
        // Remove all non-numeric characters except hyphen
        $cep = preg_replace('/[^0-9-]/', '', trim($cep));
        
        // Remove all hyphens first
        $cepNumbers = preg_replace('/[^0-9]/', '', $cep);
        
        // If we have 8 digits, format as 12345-678
        if (strlen($cepNumbers) == 8) {
            $cep = substr($cepNumbers, 0, 5) . '-' . substr($cepNumbers, 5, 3);
        } elseif (strlen($cepNumbers) > 8) {
            // If more than 8 digits, take only first 8
            $cep = substr($cepNumbers, 0, 5) . '-' . substr($cepNumbers, 5, 3);
        } else {
            // If less than 8 digits, return as is (will be validated elsewhere)
            $cep = $cepNumbers;
        }
        
        // Ensure max 10 characters
        return substr($cep, 0, 10);
    }

    /**
     * Sanitize state to ensure it doesn't exceed 2 characters
     */
    private function sanitizeEstado($estado)
    {
        if (empty($estado)) {
            return null;
        }
        
        // Remove spaces and convert to uppercase
        $estado = strtoupper(trim($estado));
        
        // Take only first 2 characters
        return substr($estado, 0, 2);
    }

    /**
     * Add client address
     */
    private function adicionarEndereco()
    {
        try {
            $clienteId = $_POST['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $data = [
                'tipo' => $_POST['tipo'] ?? 'entrega',
                'cep' => $this->sanitizeCEP($_POST['cep'] ?? null),
                'logradouro' => trim($_POST['logradouro'] ?? null) ?: null,
                'numero' => trim($_POST['numero'] ?? null) ?: null,
                'complemento' => trim($_POST['complemento'] ?? null) ?: null,
                'bairro' => trim($_POST['bairro'] ?? null) ?: null,
                'cidade' => trim($_POST['cidade'] ?? null) ?: null,
                'estado' => $this->sanitizeEstado($_POST['estado'] ?? null),
                'pais' => $_POST['pais'] ?? 'Brasil',
                'referencia' => trim($_POST['referencia'] ?? null) ?: null,
                'principal' => !empty($_POST['principal']) ? true : false
            ];

            $result = $this->clienteModel->adicionarEndereco($clienteId, $data);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update client address
     */
    private function atualizarEndereco()
    {
        try {
            $enderecoId = $_POST['id'] ?? '';
            if (empty($enderecoId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do endereço não informado']);
            }

            $data = [
                'tipo' => $_POST['tipo'] ?? 'entrega',
                'cep' => $this->sanitizeCEP($_POST['cep'] ?? null),
                'logradouro' => trim($_POST['logradouro'] ?? null) ?: null,
                'numero' => trim($_POST['numero'] ?? null) ?: null,
                'complemento' => trim($_POST['complemento'] ?? null) ?: null,
                'bairro' => trim($_POST['bairro'] ?? null) ?: null,
                'cidade' => trim($_POST['cidade'] ?? null) ?: null,
                'estado' => $this->sanitizeEstado($_POST['estado'] ?? null),
                'pais' => $_POST['pais'] ?? 'Brasil',
                'referencia' => trim($_POST['referencia'] ?? null) ?: null,
                'principal' => !empty($_POST['principal']) ? true : false
            ];

            $result = $this->clienteModel->atualizarEndereco($enderecoId, $data);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove client address
     */
    private function removerEndereco()
    {
        try {
            $enderecoId = $_POST['id'] ?? '';
            if (empty($enderecoId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do endereço não informado']);
            }

            $result = $this->clienteModel->removerEndereco($enderecoId);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get client preferences
     */
    private function getPreferencias()
    {
        try {
            $clienteId = $_GET['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $filialId = $this->session->getFilial()['id'] ?? null;

            $preferencias = $this->clienteModel->getPreferencias($clienteId, $tenantId, $filialId);

            return $this->jsonResponse([
                'success' => true,
                'data' => $preferencias
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update client preferences
     */
    private function atualizarPreferencias()
    {
        try {
            $clienteId = $_POST['cliente_id'] ?? '';
            if (empty($clienteId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $filialId = $this->session->getFilial()['id'] ?? null;

            $data = [
                'receber_promocoes' => $_POST['receber_promocoes'] ?? true,
                'receber_notificacoes' => $_POST['receber_notificacoes'] ?? true,
                'forma_pagamento_preferida' => $_POST['forma_pagamento_preferida'] ?? null,
                'observacoes_pedido' => $_POST['observacoes_pedido'] ?? null
            ];

            $result = $this->clienteModel->atualizarPreferencias($clienteId, $tenantId, $filialId, $data);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Deactivate client
     */
    private function desativar()
    {
        try {
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do cliente não informado']);
            }

            $result = $this->clienteModel->deactivate($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Create or find client for order
     */
    public function criarOuBuscarCliente($dados)
    {
        try {
            error_log("=== DEBUG CLIENTE CONTROLLER ===");
            error_log("Dados recebidos: " . json_encode($dados));
            
            // Try to find existing client by phone
            if (!empty($dados['telefone'])) {
                error_log("Buscando cliente por telefone: " . $dados['telefone']);
                $cliente = $this->clienteModel->findByTelefone($dados['telefone']);
                if ($cliente) {
                    error_log("✅ Cliente encontrado por telefone: " . json_encode($cliente));
                    // Update client data if provided
                    if (!empty($dados['nome']) || !empty($dados['email'])) {
                        $updateData = [];
                        if (!empty($dados['nome'])) $updateData['nome'] = $dados['nome'];
                        if (!empty($dados['email'])) $updateData['email'] = $dados['email'];
                        if (!empty($dados['cpf'])) $updateData['cpf'] = $dados['cpf'];
                        
                        if (!empty($updateData)) {
                            $updateData['tenant_id'] = $this->session->getTenant()['id'] ?? null;
                            $updateData['filial_id'] = $this->session->getFilial()['id'] ?? null;
                            $this->clienteModel->update($cliente['id'], $updateData);
                        }
                    }
                    return ['success' => true, 'cliente' => $cliente];
                } else {
                    error_log("❌ Cliente não encontrado por telefone");
                }
            }

            // Try to find by email
            if (!empty($dados['email'])) {
                error_log("Buscando cliente por email: " . $dados['email']);
                $cliente = $this->clienteModel->findByEmail($dados['email']);
                if ($cliente) {
                    error_log("✅ Cliente encontrado por email: " . json_encode($cliente));
                    return ['success' => true, 'cliente' => $cliente];
                } else {
                    error_log("❌ Cliente não encontrado por email");
                }
            }

            // Create new client if not found
            if (!empty($dados['nome']) || !empty($dados['telefone'])) {
                error_log("✅ Criando novo cliente");
                $createData = [
                    'nome' => $dados['nome'] ?? 'Cliente ' . ($dados['telefone'] ?? 'Sem nome'),
                    'telefone' => $dados['telefone'] ?? null,
                    'email' => $dados['email'] ?? null,
                    'cpf' => $dados['cpf'] ?? null
                    // Removed tenant_id and filial_id - they don't exist in usuarios_globais table
                ];
                
                error_log("Dados para criação: " . json_encode($createData));

                $result = $this->clienteModel->create($createData);
                error_log("Resultado da criação: " . json_encode($result));
                
                if ($result['success']) {
                    $cliente = $this->clienteModel->getById($result['id']);
                    error_log("✅ Cliente criado com sucesso: " . json_encode($cliente));
                    return ['success' => true, 'cliente' => $cliente];
                } else {
                    error_log("❌ Erro ao criar cliente: " . $result['message']);
                }
            } else {
                error_log("❌ Nenhum nome ou telefone fornecido para criar cliente");
            }

            error_log("=== FIM DEBUG CLIENTE CONTROLLER ===");
            return ['success' => false, 'message' => 'Não foi possível criar ou encontrar o cliente'];
        } catch (Exception $e) {
            error_log("❌ Exception no ClienteController: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Register client interaction for order
     */
    public function registrarInteracaoPedido($clienteId, $pedidoId, $valorPedido)
    {
        try {
            $tenantId = $this->session->getTenantId();
            $filialId = $this->session->getFilialId();
            
            // Skip if tenant_id is not available
            if (!$tenantId) {
                error_log("ClienteController::registrarInteracaoPedido - tenant_id not found, skipping");
                return ['success' => true]; // Return success to not break the order flow
            }

            // Register order interaction
            $this->clienteModel->registrarHistorico(
                $clienteId, 
                $tenantId, 
                $filialId, 
                'pedido', 
                "Pedido #$pedidoId realizado", 
                null, 
                ['pedido_id' => $pedidoId, 'valor' => $valorPedido]
            );

            // Update establishment visit
            $this->clienteModel->atualizarVisitaEstabelecimento($clienteId, $tenantId, $filialId, $valorPedido);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get order details
     */
    private function getDetalhesPedido()
    {
        try {
            $pedidoId = $_GET['pedido_id'] ?? '';
            if (empty($pedidoId)) {
                return $this->jsonResponse(['success' => false, 'message' => 'ID do pedido não informado']);
            }

            $tenantId = $this->session->getTenant()['id'] ?? null;
            $filialId = $this->session->getFilial()['id'] ?? null;

            $pedido = $this->clienteModel->getDetalhesPedido($pedidoId, $tenantId, $filialId);

            if (!$pedido) {
                return $this->jsonResponse(['success' => false, 'message' => 'Pedido não encontrado']);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $pedido
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send JSON response
     */
    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
