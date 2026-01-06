<?php
/**
 * API endpoint for online menu orders
 * Handles order creation from public online menu
 */

// Start output buffering FIRST to prevent any output before JSON
ob_start();

// Error handling - don't display errors, log them instead
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("PEDIDOS_ONLINE FATAL ERROR: " . json_encode($error));
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => 'Fatal error: ' . $error['message'] . ' em ' . basename($error['file']) . ':' . $error['line']
            ]);
        }
        exit;
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Clean any output before requiring files
ob_clean();

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/TimeHelper.php';

// Handle GET request for payment status check
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_payment_status') {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    try {
        $db = \System\Database::getInstance();
        $pedidoId = $_GET['pedido_id'] ?? null;
        
        if (!$pedidoId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Pedido ID não fornecido']);
            exit;
        }
        
        error_log("PEDIDOS_ONLINE - Checking payment status for pedido_id: $pedidoId");
        
        // Get order payment status - handle case where column might not exist
        try {
            $pedido = $db->fetch(
                "SELECT status_pagamento, asaas_payment_id FROM pedido WHERE id = ?",
                [$pedidoId]
            );
        } catch (\Exception $e) {
            error_log("PEDIDOS_ONLINE - Error fetching pedido: " . $e->getMessage());
            // Try without asaas_payment_id if column doesn't exist
            $pedido = $db->fetch(
                "SELECT status_pagamento FROM pedido WHERE id = ?",
                [$pedidoId]
            );
        }
        
        if (!$pedido) {
            error_log("PEDIDOS_ONLINE - Pedido not found: $pedidoId");
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
            exit;
        }
        
        $status = 'pending';
        $statusPagamento = $pedido['status_pagamento'] ?? 'pendente';
        
        if ($statusPagamento === 'pago' || $statusPagamento === 'paid') {
            $status = 'paid';
        } else if ($statusPagamento === 'cancelado' || $statusPagamento === 'cancelled') {
            $status = 'cancelled';
        }
        
        error_log("PEDIDOS_ONLINE - Payment status: $status (status_pagamento: $statusPagamento)");
        
        echo json_encode([
            'success' => true,
            'status' => $status,
            'status_pagamento' => $statusPagamento
        ]);
    } catch (\Exception $e) {
        error_log("PEDIDOS_ONLINE - Error checking payment status: " . $e->getMessage());
        error_log("PEDIDOS_ONLINE - Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao verificar status do pagamento',
            'error' => $e->getMessage()
        ]);
    } catch (\Error $e) {
        error_log("PEDIDOS_ONLINE - Fatal error checking payment status: " . $e->getMessage());
        error_log("PEDIDOS_ONLINE - Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao verificar status do pagamento',
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle GET request for PIX QR code
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_pix_qrcode') {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    try {
        $db = \System\Database::getInstance();
        $paymentId = $_GET['payment_id'] ?? null;
        $tenantId = $_GET['tenant_id'] ?? null;
        $filialId = $_GET['filial_id'] ?? null;
        
        if (!$paymentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payment ID não fornecido']);
            exit;
        }
        
        error_log("PEDIDOS_ONLINE - get_pix_qrcode: payment_id=$paymentId, tenant_id=$tenantId, filial_id=$filialId");
        
        // Get Asaas config - prefer provided tenant/filial, fallback to pedido
        require_once __DIR__ . '/../model/AsaasInvoice.php';
        $asaasInvoice = new AsaasInvoice();
        $asaasConfig = null;
        
        if ($tenantId && $filialId) {
            $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
            error_log("PEDIDOS_ONLINE - Using provided tenant/filial for Asaas config");
        }
        
        // If config not found, try to get from pedido
        if (!$asaasConfig) {
            $pedido = $db->fetch(
                "SELECT filial_id, tenant_id FROM pedido WHERE asaas_payment_id = ? LIMIT 1",
                [$paymentId]
            );
            
            if ($pedido) {
                $asaasConfig = $asaasInvoice->getAsaasConfig($pedido['tenant_id'], $pedido['filial_id']);
                error_log("PEDIDOS_ONLINE - Found pedido, using tenant_id={$pedido['tenant_id']}, filial_id={$pedido['filial_id']}");
            }
        }
        
        if (!$asaasConfig) {
            error_log("PEDIDOS_ONLINE - Asaas config not found");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Não foi possível determinar configuração do Asaas']);
            exit;
        }
        
        if (!$asaasConfig['asaas_enabled'] || empty($asaasConfig['asaas_api_key'])) {
            error_log("PEDIDOS_ONLINE - Asaas not enabled or API key missing");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Asaas não configurado']);
            exit;
        }
        
        $api_url = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
        $api_key = $asaasConfig['asaas_api_key'];
        
        error_log("PEDIDOS_ONLINE - Fetching payment from Asaas: $api_url/payments/$paymentId");
        
        // Use the same makeAsaasRequest function for consistency
        $makeAsaasRequest = function($method, $endpoint, $data = null) use ($api_url, $api_key) {
            $url = $api_url . $endpoint;
            
            $headers = [
                'access_token: ' . $api_key,
                'Content-Type: application/json',
                'User-Agent: DivinoSYS/2.0'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => $curlError];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'data' => $data];
            } else {
                return ['success' => false, 'error' => $data ?? $response, 'http_code' => $httpCode];
            }
        };
        
        // Get payment details from Asaas
        $paymentDetails = $makeAsaasRequest('GET', '/payments/' . $paymentId);
        
        if (!$paymentDetails['success']) {
            $errorMsg = is_array($paymentDetails['error']) ? json_encode($paymentDetails['error']) : ($paymentDetails['error'] ?? 'Unknown error');
            error_log("PEDIDOS_ONLINE - Asaas API error: " . $errorMsg);
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Erro ao buscar dados do PIX: ' . $errorMsg,
                'debug' => [
                    'http_code' => $paymentDetails['http_code'] ?? null,
                    'payment_id' => $paymentId
                ]
            ]);
            exit;
        }
        
        $paymentData = $paymentDetails['data'];
        
        if (!$paymentData) {
            error_log("PEDIDOS_ONLINE - Invalid response from Asaas");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Resposta inválida do Asaas']);
            exit;
        }
        
        error_log("PEDIDOS_ONLINE - Payment data keys: " . implode(', ', array_keys($paymentData)));
        error_log("PEDIDOS_ONLINE - Full payment response: " . json_encode($paymentData));
        
        // Try multiple possible field names for PIX data
        $pixQrCode = $paymentData['pixQrCode'] ?? 
                    $paymentData['pix_qr_code'] ?? 
                    $paymentData['encodedPixQrCode'] ?? 
                    (isset($paymentData['pixQrCode']) && is_array($paymentData['pixQrCode']) ? ($paymentData['pixQrCode']['base64'] ?? null) : null) ??
                    (isset($paymentData['pix']) && is_array($paymentData['pix']) && isset($paymentData['pix']['qrCode']) ? $paymentData['pix']['qrCode'] : null);
        
        $pixCopyPaste = $paymentData['pixCopyPaste'] ?? 
                        $paymentData['pix_copy_paste'] ?? 
                        $paymentData['pixCopiaECola'] ?? 
                        (isset($paymentData['pixQrCode']) && is_array($paymentData['pixQrCode']) ? ($paymentData['pixQrCode']['payload'] ?? null) : null) ??
                        (isset($paymentData['pix']) && is_array($paymentData['pix']) && isset($paymentData['pix']['copyPaste']) ? $paymentData['pix']['copyPaste'] : null);
        
        error_log("PEDIDOS_ONLINE - PIX QR Code found: " . ($pixQrCode ? 'yes (' . strlen($pixQrCode) . ' chars)' : 'no'));
        error_log("PEDIDOS_ONLINE - PIX Copy Paste found: " . ($pixCopyPaste ? 'yes (' . strlen($pixCopyPaste) . ' chars)' : 'no'));
        
        // If QR code is not available, return success but indicate it's not ready yet
        if (!$pixQrCode && !$pixCopyPaste) {
            error_log("PEDIDOS_ONLINE - PIX QR code not available yet. Payment status: " . ($paymentData['status'] ?? 'unknown'));
            // Return success but with null values - frontend will retry
            echo json_encode([
                'success' => true,
                'pix_qr_code' => null,
                'pix_copy_paste' => null,
                'not_ready_yet' => true,
                'debug' => [
                    'payment_id' => $paymentId,
                    'billing_type' => $paymentData['billingType'] ?? null,
                    'status' => $paymentData['status'] ?? null,
                    'available_keys' => array_keys($paymentData),
                    'message' => 'QR code ainda não disponível. O Asaas pode levar alguns segundos para gerar.'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'pix_qr_code' => $pixQrCode,
                'pix_copy_paste' => $pixCopyPaste,
                'debug' => [
                    'payment_id' => $paymentId,
                    'billing_type' => $paymentData['billingType'] ?? null,
                    'status' => $paymentData['status'] ?? null,
                    'available_keys' => array_keys($paymentData)
                ]
            ]);
        }
    } catch (\Exception $e) {
        error_log("PEDIDOS_ONLINE - Exception in get_pix_qrcode: " . $e->getMessage());
        error_log("PEDIDOS_ONLINE - Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar QR code: ' . $e->getMessage()
        ]);
    } catch (\Error $e) {
        error_log("PEDIDOS_ONLINE - Fatal error in get_pix_qrcode: " . $e->getMessage());
        error_log("PEDIDOS_ONLINE - Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar QR code: ' . $e->getMessage()
        ]);
    }
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $db = \System\Database::getInstance();
    
    // Validate required fields
    $filialId = $data['filial_id'] ?? null;
    $tenantId = $data['tenant_id'] ?? null;
    $itens = $data['itens'] ?? [];
    $tipoEntrega = $data['tipo_entrega'] ?? 'pickup';
    $clienteNome = $data['cliente_nome'] ?? '';
    $clienteTelefone = $data['cliente_telefone'] ?? '';
    $clienteEmail = $data['cliente_email'] ?? '';
    $enderecoEntrega = $data['endereco_entrega'] ?? null;
    $taxaEntrega = floatval($data['taxa_entrega'] ?? 0);
    $formaPagamento = $data['forma_pagamento'] ?? 'on_delivery';
    $formaPagamentoDetalhada = $data['forma_pagamento_detalhada'] ?? null;
    $trocoPara = isset($data['troco_para']) ? floatval($data['troco_para']) : null;
    
    if (!$filialId || !$tenantId || empty($itens) || !$clienteNome || !$clienteTelefone) {
        throw new Exception('Dados obrigatórios não fornecidos');
    }
    
    // Verify filial exists and online menu is active
    $filial = $db->fetch(
        "SELECT f.*, t.asaas_api_key, t.asaas_enabled, t.asaas_api_url, t.asaas_customer_id
         FROM filiais f
         INNER JOIN tenants t ON f.tenant_id = t.id
         WHERE f.id = ? AND f.tenant_id = ? AND f.cardapio_online_ativo = true AND f.status = 'ativo'",
        [$filialId, $tenantId]
    );
    
    if (!$filial) {
        throw new Exception('Cardápio online não disponível para esta filial');
    }
    
    // Calculate order total
    $valorTotal = 0;
    $itensDetalhados = [];
    
    foreach ($itens as $item) {
        // Get product details
        $produto = $db->fetch(
            "SELECT id, nome, preco_normal FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
            [$item['id'], $tenantId, $filialId]
        );
        
        if (!$produto) {
            throw new Exception("Produto ID {$item['id']} não encontrado ou inativo");
        }
        
        $quantidade = intval($item['quantity'] ?? 1);
        $precoUnitario = floatval($item['preco'] ?? $produto['preco_normal']);
        
        // Add ingredient prices if any
        if (isset($item['ingredientes_adicionados']) && is_array($item['ingredientes_adicionados'])) {
            foreach ($item['ingredientes_adicionados'] as $ing) {
                if (is_array($ing) && isset($ing['preco_adicional'])) {
                    $precoUnitario += floatval($ing['preco_adicional']);
                }
            }
        }
        
        $subtotal = $precoUnitario * $quantidade;
        $valorTotal += $subtotal;
        
        $itensDetalhados[] = [
            'produto_id' => $produto['id'],
            'produto_nome' => $produto['nome'],
            'quantidade' => $quantidade,
            'preco_unitario' => $precoUnitario,
            'subtotal' => $subtotal,
            'observacao' => $item['observacao'] ?? '',
            'ingredientes_adicionados' => $item['ingredientes_adicionados'] ?? [],
            'ingredientes_removidos' => $item['ingredientes_removidos'] ?? []
        ];
    }
    
    // Add delivery fee if delivery
    if ($tipoEntrega === 'delivery') {
        $valorTotal += $taxaEntrega;
    }
    
    // Create or find customer
    $clienteId = $data['cliente_id'] ?? null;
    
    if (!$clienteId) {
        try {
            // Try to find existing client by phone
            $clienteExistente = $db->fetch(
                "SELECT id FROM usuarios_globais WHERE telefone = ? LIMIT 1",
                [preg_replace('/[^0-9]/', '', $clienteTelefone)]
            );
            
            if ($clienteExistente) {
                $clienteId = $clienteExistente['id'];
                
                // Update client data if provided
                $updateData = [];
                if (!empty($clienteNome)) $updateData['nome'] = $clienteNome;
                if (!empty($clienteEmail)) $updateData['email'] = $clienteEmail;
                if (!empty($data['cliente_cpf'])) $updateData['cpf'] = $data['cliente_cpf'];
                
                if (!empty($updateData)) {
                    $updateData['updated_at'] = \System\TimeHelper::now('Y-m-d H:i:s', $filialId);
                    $db->update('usuarios_globais', $updateData, 'id = ?', [$clienteId]);
                }
            } else {
                // Create new client
                $clienteId = $db->insert('usuarios_globais', [
                    'nome' => $clienteNome,
                    'telefone' => preg_replace('/[^0-9]/', '', $clienteTelefone),
                    'email' => $clienteEmail ?: null,
                    'cpf' => $data['cliente_cpf'] ?? null,
                    'tipo_usuario' => 'cliente',
                    'ativo' => true,
                    'created_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId),
                    'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
                ]);
            }
            
            // Add address if delivery and address provided
            if ($tipoEntrega === 'delivery' && $enderecoEntrega && $clienteId) {
                $enderecoExistente = $db->fetch(
                    "SELECT id FROM enderecos WHERE usuario_global_id = ? AND logradouro = ? AND numero = ? LIMIT 1",
                    [$clienteId, $enderecoEntrega['endereco'] ?? '', $enderecoEntrega['numero'] ?? '']
                );
                
                if (!$enderecoExistente) {
                    $db->insert('enderecos', [
                        'usuario_global_id' => $clienteId,
                        'tenant_id' => $tenantId,
                        'tipo' => 'entrega',
                        'cep' => $enderecoEntrega['cep'] ?? null,
                        'logradouro' => $enderecoEntrega['endereco'] ?? null,
                        'numero' => $enderecoEntrega['numero'] ?? null,
                        'complemento' => $enderecoEntrega['complemento'] ?? null,
                        'bairro' => $enderecoEntrega['bairro'] ?? null,
                        'cidade' => $enderecoEntrega['cidade'] ?? null,
                        'estado' => $enderecoEntrega['estado'] ?? null,
                        'pais' => 'Brasil',
                        'referencia' => $enderecoEntrega['referencia'] ?? null,
                        'principal' => true,
                        'ativo' => true,
                        'created_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId),
                        'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao processar cliente: " . $e->getMessage());
            // Continue without client ID - order can still be created
        }
    }
    
    // Determine mesa ID
    $mesaId = ($tipoEntrega === 'delivery') ? '999' : '998'; // 999 = delivery, 998 = pickup
    
    // Create order
    $observacao = '';
    if ($tipoEntrega === 'delivery' && $enderecoEntrega) {
        $observacao = "Delivery - Endereço: " . 
            ($enderecoEntrega['endereco'] ?? '') . ", " .
            ($enderecoEntrega['bairro'] ?? '') . ", " .
            ($enderecoEntrega['cidade'] ?? '') . " - CEP: " .
            ($enderecoEntrega['cep'] ?? '');
    } else {
        $observacao = "Pedido Online - Retirada no Balcão";
    }
    
    // Add payment details to observation if payment is on delivery
    if ($formaPagamento === 'on_delivery' && $formaPagamentoDetalhada) {
        $observacao .= "\nForma de Pagamento: " . $formaPagamentoDetalhada;
        if ($trocoPara && $trocoPara > 0 && $formaPagamentoDetalhada === 'Dinheiro') {
            $observacao .= "\nTroco para: R$ " . number_format($trocoPara, 2, ',', '.');
        }
    }
    
    // Get a default user from usuarios table for the order
    // usuario_id references usuarios table, not usuarios_globais
    // Since the field allows NULL, we can use NULL if no valid user is found
    $usuarioId = null;
    try {
        // Try to find a default admin user for this tenant
        // nivel: 999=SuperAdmin, 1=Admin Estabelecimento, 0=Admin Filial, -1=Operador
        $usuarioPadrao = $db->fetch(
            "SELECT id FROM usuarios WHERE tenant_id = ? AND nivel >= 0 LIMIT 1",
            [$tenantId]
        );
        if ($usuarioPadrao) {
            $usuarioId = $usuarioPadrao['id'];
        } else {
            // If no admin found, try any user from this tenant
            $qualquerUsuario = $db->fetch(
                "SELECT id FROM usuarios WHERE tenant_id = ? LIMIT 1",
                [$tenantId]
            );
            if ($qualquerUsuario) {
                $usuarioId = $qualquerUsuario['id'];
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar usuário padrão para pedido online: " . $e->getMessage());
        // Continue with null - campo permite NULL
    }
    
    // For online payment, process payment FIRST before creating order
    // This allows customer to retry or choose another payment method if it fails
    $paymentProcessed = false;
    $paymentDataResult = null;
    $clienteAsaasId = null;
    $billingType = null; // Initialize billing type
    
    if ($formaPagamento === 'online') {
        try {
            require_once __DIR__ . '/../model/AsaasInvoice.php';
            
            // Get Asaas configuration for filial (or tenant if filial doesn't have one)
            $asaasInvoice = new AsaasInvoice();
            $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
            
            error_log("PEDIDOS_ONLINE - Asaas Config: " . json_encode([
                'enabled' => $asaasConfig['asaas_enabled'] ?? false,
                'has_api_key' => !empty($asaasConfig['asaas_api_key']),
                'api_url' => $asaasConfig['asaas_api_url'] ?? 'not set',
                'environment' => $asaasConfig['asaas_environment'] ?? 'not set'
            ]));
            
            if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || empty($asaasConfig['asaas_api_key'])) {
                error_log("PEDIDOS_ONLINE - Erro: Integração Asaas não configurada");
                throw new Exception('Integração Asaas não configurada para esta filial');
            }
            
            $api_url = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            $api_key = $asaasConfig['asaas_api_key'];
            
            error_log("PEDIDOS_ONLINE - Usando API URL: " . $api_url . " | Environment: " . ($asaasConfig['asaas_environment'] ?? 'not set'));
            
            // Use same pattern as AsaasPayment::makeRequest (working implementation)
            $makeAsaasRequest = function($method, $endpoint, $data = null) use ($api_url, $api_key) {
                $url = $api_url . $endpoint;
                
                $headers = [
                    'access_token: ' . $api_key,
                    'Content-Type: application/json',
                    'User-Agent: DivinoSYS/2.0'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } elseif ($method === 'DELETE') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                }
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $decodedResponse = json_decode($response, true);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    return [
                        'success' => true,
                        'data' => $decodedResponse
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $decodedResponse['errors'] ?? 'Erro na API do Asaas',
                        'http_code' => $httpCode
                    ];
                }
            };
            
            // Create customer in Asaas
            // CPF is optional - try without it first, only add if provided
            $customerData = [
                'name' => $clienteNome,
                'phone' => preg_replace('/[^0-9]/', '', $clienteTelefone),
                'externalReference' => 'cliente_pedido_' . ($clienteId ?? 'temp_' . time())
            ];
            
            // Add CPF/CNPJ only if provided (required for BOLETO, optional for PIX and CREDIT_CARD)
            if (!empty($data['cliente_cpf'])) {
                $customerData['cpfCnpj'] = preg_replace('/[^0-9]/', '', $data['cliente_cpf']);
            }
            
            if ($clienteEmail) {
                $customerData['email'] = $clienteEmail;
            }
            
            $customerResult = $makeAsaasRequest('POST', '/customers', $customerData);
            
            if ($customerResult['success'] && isset($customerResult['data']['id'])) {
                $clienteAsaasId = $customerResult['data']['id'];
            } else {
                // If customer already exists, try to find by email
                $errorMsg = is_array($customerResult['error']) ? json_encode($customerResult['error']) : ($customerResult['error'] ?? '');
                if (strpos($errorMsg, 'já existe') !== false || strpos($errorMsg, 'already exists') !== false || strpos($errorMsg, 'duplicate') !== false) {
                    if ($clienteEmail) {
                        $searchResult = $makeAsaasRequest('GET', '/customers?email=' . urlencode($clienteEmail));
                        if ($searchResult['success'] && isset($searchResult['data']['data']) && count($searchResult['data']['data']) > 0) {
                            $clienteAsaasId = $searchResult['data']['data'][0]['id'];
                        }
                    }
                }
                
                if (!$clienteAsaasId) {
                    throw new Exception('Não foi possível criar ou encontrar cliente no Asaas');
                }
            }
            
            // Get billing type from request (PIX or CREDIT_CARD)
            $billingType = $data['online_payment_method'] ?? 'PIX';
            if (!in_array($billingType, ['PIX', 'CREDIT_CARD', 'BOLETO'])) {
                $billingType = 'PIX';
            }
            
            // Get webhook URL (from config or construct from current domain)
            $webhookUrl = null;
            if (!empty($asaasConfig['asaas_webhook_url'])) {
                $webhookUrl = $asaasConfig['asaas_webhook_url'];
            } else {
                // Construct webhook URL from current domain
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
                $webhookUrl = $protocol . '://' . $host . '/webhook/asaas.php';
            }
            
            // Asaas has a minimum charge value of R$ 5,00 in production
            // Check if order value meets minimum requirement
            $valorMinimoAsaas = ($asaasConfig['asaas_environment'] === 'production') ? 5.00 : 0.01;
            
            if ($valorTotal < $valorMinimoAsaas) {
                throw new Exception("O valor mínimo para pagamento online é R$ " . number_format($valorMinimoAsaas, 2, ',', '.') . ". Seu pedido é de R$ " . number_format($valorTotal, 2, ',', '.') . ". Por favor, adicione mais itens ao pedido ou escolha pagar na hora.");
            }
            
            // Create payment charge in Asaas BEFORE creating order
            // Use temporary reference - will update with real order ID after
            try {
                $dt = \System\TimeHelper::createDateTime('+1 day', $filialId);
                $dueDate = $dt->format('Y-m-d');
            } catch (\Exception $e) {
                error_log("TimeHelper error, using fallback: " . $e->getMessage());
                $dueDate = date('Y-m-d', strtotime('+1 day'));
            }
            
            $paymentData = [
                'customer' => $clienteAsaasId,
                'billingType' => $billingType,
                'value' => number_format($valorTotal, 2, '.', ''),
                'dueDate' => $dueDate,
                'description' => "Pedido - {$filial['nome']}",
                'externalReference' => 'pedido_temp_' . time(),
                'webhook' => $webhookUrl
            ];
            
            $paymentResult = $makeAsaasRequest('POST', '/payments', $paymentData);
            
            if ($paymentResult['success'] && isset($paymentResult['data']['id'])) {
                $paymentDataResult = $paymentResult['data'];
                $paymentProcessed = true;
                
                // Log full payment response for debugging
                error_log("PEDIDOS_ONLINE - Payment created - Full response keys: " . implode(', ', array_keys($paymentDataResult)));
                error_log("PEDIDOS_ONLINE - Payment response (first 500 chars): " . substr(json_encode($paymentDataResult), 0, 500));
                
                // For PIX, always fetch payment details to get QR code (Asaas may not return it immediately)
                if ($billingType === 'PIX') {
                    error_log("PEDIDOS_ONLINE - PIX payment created, ID: " . $paymentDataResult['id']);
                    error_log("PEDIDOS_ONLINE - Initial response keys: " . implode(', ', array_keys($paymentDataResult)));
                    
                    // Check if QR code is already in initial response
                    $hasQrInResponse = isset($paymentDataResult['pixQrCode']) || 
                                      isset($paymentDataResult['pixCopyPaste']) ||
                                      isset($paymentDataResult['encodedPixQrCode']);
                    
                    if (!$hasQrInResponse) {
                        error_log("PEDIDOS_ONLINE - QR code not in initial response, waiting 2 seconds and fetching details...");
                        // Wait for Asaas to process the payment
                        sleep(2);
                        
                        // Try multiple times (Asaas may take time to generate QR code)
                        $maxRetries = 3;
                        $retryCount = 0;
                        $foundQrCode = false;
                        
                        while ($retryCount < $maxRetries && !$foundQrCode) {
                            $paymentDetails = $makeAsaasRequest('GET', '/payments/' . $paymentDataResult['id']);
                            
                            if ($paymentDetails['success'] && isset($paymentDetails['data'])) {
                                error_log("PEDIDOS_ONLINE - Payment details attempt " . ($retryCount + 1) . " - Keys: " . implode(', ', array_keys($paymentDetails['data'])));
                                error_log("PEDIDOS_ONLINE - Full payment details: " . json_encode($paymentDetails['data']));
                                
                                // Merge all PIX-related data
                                $pixFields = ['pixQrCode', 'pixCopyPaste', 'pixQrCodeId', 'encodedPixQrCode', 'pixCopiaECola'];
                                foreach ($pixFields as $field) {
                                    if (isset($paymentDetails['data'][$field])) {
                                        $paymentDataResult[$field] = $paymentDetails['data'][$field];
                                        error_log("PEDIDOS_ONLINE - Found PIX field: $field");
                                        $foundQrCode = true;
                                    }
                                }
                                
                                // Also check for nested structures
                                if (isset($paymentDetails['data']['pix']) && is_array($paymentDetails['data']['pix'])) {
                                    error_log("PEDIDOS_ONLINE - Found nested pix object");
                                    if (isset($paymentDetails['data']['pix']['qrCode'])) {
                                        $paymentDataResult['pixQrCode'] = $paymentDetails['data']['pix']['qrCode'];
                                        $foundQrCode = true;
                                    }
                                    if (isset($paymentDetails['data']['pix']['copyPaste'])) {
                                        $paymentDataResult['pixCopyPaste'] = $paymentDetails['data']['pix']['copyPaste'];
                                        $foundQrCode = true;
                                    }
                                }
                                
                                if ($foundQrCode) {
                                    error_log("PEDIDOS_ONLINE - QR code found after " . ($retryCount + 1) . " attempts");
                                    break;
                                }
                            } else {
                                error_log("PEDIDOS_ONLINE - Failed to fetch payment details (attempt " . ($retryCount + 1) . "): " . json_encode($paymentDetails['error'] ?? 'Unknown error'));
                            }
                            
                            $retryCount++;
                            if ($retryCount < $maxRetries) {
                                sleep(2); // Wait 2 more seconds before next try
                            }
                        }
                        
                        if (!$foundQrCode) {
                            error_log("PEDIDOS_ONLINE - WARNING: QR code not found after $maxRetries attempts. Frontend will need to fetch it.");
                        }
                    } else {
                        error_log("PEDIDOS_ONLINE - QR code found in initial response!");
                    }
                }
            } else {
                // Extract error message from Asaas response
                $errorMsg = 'Erro desconhecido';
                if (is_array($paymentResult['error'])) {
                    if (isset($paymentResult['error'][0]['description'])) {
                        $errorMsg = $paymentResult['error'][0]['description'];
                    } elseif (isset($paymentResult['error'][0]['code'])) {
                        $errorMsg = $paymentResult['error'][0]['code'];
                    } else {
                        $errorMsg = json_encode($paymentResult['error']);
                    }
                } elseif (!empty($paymentResult['error'])) {
                    $errorMsg = $paymentResult['error'];
                }
                
                // Payment failed - don't create order, return error so customer can retry
                throw new Exception('Erro ao processar pagamento online: ' . $errorMsg);
            }
        } catch (Exception $e) {
            // Payment failed - return error without creating order
            error_log("PEDIDOS_ONLINE - Payment Exception: " . $e->getMessage());
            ob_end_clean(); // Clean any output before JSON
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'payment_error' => true,
                'can_retry' => true
            ]);
            exit;
        }
    }
    
    // Now create the order (only if payment was processed successfully for online, or if it's on_delivery)
    // Use establishment timezone for order date/time
    try {
        $orderDate = \System\TimeHelper::today($filialId);
        $orderTime = \System\TimeHelper::currentTime($filialId);
    } catch (\Exception $e) {
        error_log("TimeHelper error, using fallback: " . $e->getMessage());
        $orderDate = date('Y-m-d');
        $orderTime = date('H:i:s');
    }
    
    $pedidoData = [
        'idmesa' => $mesaId,
        'cliente' => $clienteNome,
        'usuario_global_id' => $clienteId,
        'telefone_cliente' => $clienteTelefone,
        'delivery' => ($tipoEntrega === 'delivery') ? 1 : 0,
        'tipo_entrega' => $tipoEntrega,
        'data' => $orderDate,
        'hora_pedido' => $orderTime,
        'valor_total' => $valorTotal,
        'valor_pago' => 0.00,
        'saldo_devedor' => $valorTotal,
        'status_pagamento' => ($formaPagamento === 'online') ? 'pendente' : 'pendente',
        'status' => 'Pendente',
        'observacao' => $observacao,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId
    ];
    
    // Add payment information if online payment was processed
    if ($paymentProcessed && $paymentDataResult) {
        $pedidoData['asaas_payment_id'] = $paymentDataResult['id'];
        $pedidoData['asaas_payment_url'] = $paymentDataResult['invoiceUrl'] ?? null;
    }
    
    // Add forma_pagamento if payment is on delivery and detailed payment method is provided
    if ($formaPagamento === 'on_delivery' && $formaPagamentoDetalhada) {
        $pedidoData['forma_pagamento'] = $formaPagamentoDetalhada;
    }
    
    // Only add usuario_id if we found a valid user (field allows NULL)
    if ($usuarioId) {
        $pedidoData['usuario_id'] = $usuarioId;
    }
    
    $pedidoId = $db->insert('pedido', $pedidoData);
    
    if (!$pedidoId) {
        // If order creation fails after payment was processed, we should cancel the payment
        // For now, just throw error
        throw new Exception('Erro ao criar pedido no banco de dados');
    }
    
    // Update payment external reference with real order ID
    if ($paymentProcessed && $paymentDataResult && isset($paymentDataResult['id'])) {
        // Update payment description with order ID
        $updatePaymentData = [
            'description' => "Pedido #{$pedidoId} - {$filial['nome']}",
            'externalReference' => 'pedido_' . $pedidoId
        ];
        // Note: Asaas may not support updating externalReference, but we try
        // The description update is more important
    }
    
    // Create order items
    foreach ($itensDetalhados as $item) {
        // Prepare ingredients
        $ingredientesCom = [];
        $ingredientesSem = [];
        
        if (isset($item['ingredientes_adicionados']) && is_array($item['ingredientes_adicionados']) && !empty($item['ingredientes_adicionados'])) {
            foreach ($item['ingredientes_adicionados'] as $ing) {
                if (is_array($ing)) {
                    // If it's an object with id and nome
                    if (isset($ing['id'])) {
                        // Get ingredient name from database
                        $ingrediente = $db->fetch(
                            "SELECT nome FROM ingredientes WHERE id = ? AND tenant_id = ?",
                            [$ing['id'], $tenantId]
                        );
                        if ($ingrediente) {
                            $ingredientesCom[] = $ingrediente['nome'];
                        } elseif (isset($ing['nome'])) {
                            // Use nome from the object if database lookup fails
                            $ingredientesCom[] = $ing['nome'];
                        }
                    } elseif (isset($ing['nome'])) {
                        $ingredientesCom[] = $ing['nome'];
                    }
                } elseif (is_string($ing) && !empty($ing)) {
                    $ingredientesCom[] = $ing;
                }
            }
        }
        
        if (isset($item['ingredientes_removidos']) && is_array($item['ingredientes_removidos']) && !empty($item['ingredientes_removidos'])) {
            foreach ($item['ingredientes_removidos'] as $ing) {
                if (is_array($ing)) {
                    // If it's an object with id and nome
                    if (isset($ing['id'])) {
                        $ingrediente = $db->fetch(
                            "SELECT nome FROM ingredientes WHERE id = ? AND tenant_id = ?",
                            [$ing['id'], $tenantId]
                        );
                        if ($ingrediente) {
                            $ingredientesSem[] = $ingrediente['nome'];
                        } elseif (isset($ing['nome'])) {
                            // Use nome from the object if database lookup fails
                            $ingredientesSem[] = $ing['nome'];
                        }
                    } elseif (isset($ing['nome'])) {
                        $ingredientesSem[] = $ing['nome'];
                    }
                } elseif (is_string($ing) && !empty($ing)) {
                    $ingredientesSem[] = $ing;
                }
            }
        }
        
        error_log("Item ingredientes - COM: " . implode(', ', $ingredientesCom) . " | SEM: " . implode(', ', $ingredientesSem));
        
        $db->insert('pedido_itens', [
            'pedido_id' => $pedidoId,
            'produto_id' => $item['produto_id'],
            'quantidade' => $item['quantidade'],
            'valor_unitario' => $item['preco_unitario'],
            'valor_total' => $item['subtotal'],
            'tamanho' => 'normal',
            'observacao' => $item['observacao'] ?? '',
            'ingredientes_com' => implode(', ', $ingredientesCom),
            'ingredientes_sem' => implode(', ', $ingredientesSem),
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
    }
    
    // Delivery fee is already included in valor_total, no need to add as separate item
    // If you need to track delivery fee separately, add a 'taxa_entrega' column to pedido table
    
    // Send WhatsApp notification if online payment was processed
    if ($paymentProcessed && $paymentDataResult && $billingType) {
        try {
            require_once __DIR__ . '/../../system/WhatsApp/PaymentNotificationService.php';
            $paymentNotification = new \System\WhatsApp\PaymentNotificationService();
            
            // Get PIX copy-paste code if available
            $pixCopyPaste = null;
            if ($billingType === 'PIX') {
                $pixCopyPaste = $paymentDataResult['pixCopyPaste'] ?? 
                               $paymentDataResult['pix_copy_paste'] ?? 
                               $paymentDataResult['pixCopiaECola'] ?? 
                               $paymentDataResult['pixQrCode']['payload'] ?? null;
            }
            
            // Send notification
            $notificationResult = $paymentNotification->sendPaymentNotification(
                $pedidoId,
                $tenantId,
                $filialId,
                $clienteTelefone,
                $clienteNome,
                $valorTotal,
                $paymentDataResult['id'],
                $paymentDataResult['invoiceUrl'] ?? null,
                $pixCopyPaste,
                $billingType
            );
            
            if ($notificationResult['success']) {
                error_log("PEDIDOS_ONLINE - Notificação WhatsApp enviada com sucesso para $clienteTelefone");
            } else {
                error_log("PEDIDOS_ONLINE - Erro ao enviar notificação WhatsApp: " . ($notificationResult['message'] ?? 'Erro desconhecido'));
            }
        } catch (Exception $e) {
            // Não falhar o pedido se o WhatsApp falhar
            error_log("PEDIDOS_ONLINE - Exception ao enviar notificação WhatsApp: " . $e->getMessage());
        }
    }
    
    // Enviar notificação WhatsApp para o admin sobre o novo pedido online
    try {
        require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
        $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
        
        // Buscar instância WhatsApp ativa para o tenant/filial
        $instancia = null;
        
        // Primeiro, tentar buscar instância específica da filial
        if ($filialId) {
            $instancia = $db->fetch(
                "SELECT * FROM whatsapp_instances 
                 WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
                 AND status IN ('open', 'connected', 'ativo', 'active') 
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId, $filialId]
            );
        }
        
        // Se não encontrou, tentar instância global do tenant
        if (!$instancia) {
            $instancia = $db->fetch(
                "SELECT * FROM whatsapp_instances 
                 WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = 0) AND ativo = true 
                 AND status IN ('open', 'connected', 'ativo', 'active') 
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId]
            );
        }
        
        // Se ainda não encontrou, tentar qualquer instância ativa do tenant
        if (!$instancia) {
            $instancia = $db->fetch(
                "SELECT * FROM whatsapp_instances 
                 WHERE tenant_id = ? AND ativo = true 
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId]
            );
        }
        
        if ($instancia && !empty($instancia['phone_number']) && !empty($instancia['id'])) {
            // Formatar mensagem com detalhes do pedido
            $tipoEntregaTexto = ($tipoEntrega === 'delivery') ? 'Delivery' : 'Retirada no Balcão';
            $formaPagamentoTexto = ($formaPagamento === 'online') ? 'Pagamento Online' : 'Pagamento na Entrega';
            
            $mensagem = "🛒 *NOVO PEDIDO ONLINE*\n\n";
            $mensagem .= "📋 Pedido #{$pedidoId}\n";
            $mensagem .= "👤 Cliente: {$clienteNome}\n";
            $mensagem .= "📞 Telefone: {$clienteTelefone}\n";
            $mensagem .= "🏪 Filial: {$filial['nome']}\n";
            $mensagem .= "🚚 Tipo: {$tipoEntregaTexto}\n";
            $mensagem .= "💳 Pagamento: {$formaPagamentoTexto}\n";
            $mensagem .= "💰 Valor Total: R$ " . number_format($valorTotal, 2, ',', '.') . "\n";
            $mensagem .= "📅 Data/Hora: {$orderDate} {$orderTime}\n\n";
            
            // Adicionar itens do pedido
            if (!empty($itensDetalhados)) {
                $mensagem .= "*Itens do Pedido:*\n";
                foreach ($itensDetalhados as $item) {
                    $mensagem .= "• {$item['quantidade']}x {$item['produto_nome']} - R$ " . number_format($item['subtotal'], 2, ',', '.') . "\n";
                }
                $mensagem .= "\n";
            }
            
            // Adicionar endereço se for delivery
            if ($tipoEntrega === 'delivery' && $enderecoEntrega) {
                $mensagem .= "*Endereço de Entrega:*\n";
                $mensagem .= "{$enderecoEntrega['endereco']}, {$enderecoEntrega['numero']}\n";
                if (!empty($enderecoEntrega['complemento'])) {
                    $mensagem .= "Complemento: {$enderecoEntrega['complemento']}\n";
                }
                $mensagem .= "{$enderecoEntrega['bairro']}, {$enderecoEntrega['cidade']} - {$enderecoEntrega['estado']}\n";
                if (!empty($enderecoEntrega['cep'])) {
                    $mensagem .= "CEP: {$enderecoEntrega['cep']}\n";
                }
                $mensagem .= "\n";
            }
            
            // Adicionar informações de pagamento se for online
            if ($formaPagamento === 'online' && $paymentProcessed && $paymentDataResult) {
                $mensagem .= "*Informações de Pagamento:*\n";
                $mensagem .= "ID Pagamento: {$paymentDataResult['id']}\n";
                $mensagem .= "Tipo: {$billingType}\n";
                if (!empty($paymentDataResult['invoiceUrl'])) {
                    $mensagem .= "Link: {$paymentDataResult['invoiceUrl']}\n";
                }
                $mensagem .= "\n";
            }
            
            $mensagem .= "✅ Acesse o sistema para mais detalhes.";
            
            // Enviar mensagem para o número do admin (phone_number da instância)
            $resultado = $wuzapiManager->sendMessage(
                $instancia['id'],
                $instancia['phone_number'],
                $mensagem
            );
            
            if ($resultado['success']) {
                error_log("PEDIDOS_ONLINE - Notificação WhatsApp enviada com sucesso para admin ({$instancia['phone_number']})");
            } else {
                error_log("PEDIDOS_ONLINE - Erro ao enviar notificação WhatsApp para admin: " . ($resultado['message'] ?? 'Erro desconhecido'));
            }
            
            // Enviar mensagem de confirmação para o cliente
            if (!empty($clienteTelefone)) {
                $mensagemCliente = "✅ *Pedido Confirmado!*\n\n";
                $mensagemCliente .= "Olá, {$clienteNome}!\n\n";
                $mensagemCliente .= "Seu pedido foi recebido com sucesso!\n\n";
                $mensagemCliente .= "📋 *Pedido #{$pedidoId}*\n";
                $mensagemCliente .= "🏪 {$filial['nome']}\n";
                $mensagemCliente .= "💰 Valor Total: R$ " . number_format($valorTotal, 2, ',', '.') . "\n";
                $mensagemCliente .= "🚚 Tipo: {$tipoEntregaTexto}\n";
                $mensagemCliente .= "💳 Pagamento: {$formaPagamentoTexto}\n\n";
                
                // Adicionar itens do pedido
                if (!empty($itensDetalhados)) {
                    $mensagemCliente .= "*Seu pedido:*\n";
                    foreach ($itensDetalhados as $item) {
                        $mensagemCliente .= "• {$item['quantidade']}x {$item['produto_nome']} - R$ " . number_format($item['subtotal'], 2, ',', '.') . "\n";
                    }
                    $mensagemCliente .= "\n";
                }
                
                // Adicionar endereço se for delivery
                if ($tipoEntrega === 'delivery' && $enderecoEntrega) {
                    $mensagemCliente .= "*Endereço de Entrega:*\n";
                    $mensagemCliente .= "{$enderecoEntrega['endereco']}, {$enderecoEntrega['numero']}\n";
                    if (!empty($enderecoEntrega['complemento'])) {
                        $mensagemCliente .= "Complemento: {$enderecoEntrega['complemento']}\n";
                    }
                    $mensagemCliente .= "{$enderecoEntrega['bairro']}, {$enderecoEntrega['cidade']} - {$enderecoEntrega['estado']}\n";
                    if (!empty($enderecoEntrega['cep'])) {
                        $mensagemCliente .= "CEP: {$enderecoEntrega['cep']}\n";
                    }
                    $mensagemCliente .= "\n";
                }
                
                // Adicionar informações de pagamento se for online
                if ($formaPagamento === 'online' && $paymentProcessed && $paymentDataResult) {
                    $mensagemCliente .= "*Pagamento Online:*\n";
                    if ($billingType === 'PIX') {
                        $mensagemCliente .= "💰 Pagamento via PIX\n";
                        if (!empty($paymentDataResult['pixCopyPaste'])) {
                            $mensagemCliente .= "Código PIX copiado! Use-o para realizar o pagamento.\n";
                        }
                    } elseif ($billingType === 'CREDIT_CARD') {
                        $mensagemCliente .= "💳 Pagamento via Cartão de Crédito\n";
                    } elseif ($billingType === 'BOLETO') {
                        $mensagemCliente .= "📄 Boleto gerado\n";
                    }
                    
                    if (!empty($paymentDataResult['invoiceUrl'])) {
                        $mensagemCliente .= "Link para pagamento: {$paymentDataResult['invoiceUrl']}\n";
                    }
                    $mensagemCliente .= "\n";
                }
                
                // Adicionar tempo estimado se disponível
                if (!empty($filial['tempo_medio_preparo'])) {
                    $mensagemCliente .= "⏱️ Tempo estimado: {$filial['tempo_medio_preparo']} minutos\n\n";
                }
                
                $mensagemCliente .= "Acompanhe o status do seu pedido em tempo real!\n";
                $mensagemCliente .= "Obrigado pela preferência! 🎉";
                
                // Enviar mensagem para o cliente
                $resultadoCliente = $wuzapiManager->sendMessage(
                    $instancia['id'],
                    $clienteTelefone,
                    $mensagemCliente
                );
                
                if ($resultadoCliente['success']) {
                    error_log("PEDIDOS_ONLINE - Notificação WhatsApp enviada com sucesso para cliente ({$clienteTelefone})");
                } else {
                    error_log("PEDIDOS_ONLINE - Erro ao enviar notificação WhatsApp para cliente: " . ($resultadoCliente['message'] ?? 'Erro desconhecido'));
                }
            }
        } else {
            error_log("PEDIDOS_ONLINE - Instância WhatsApp não encontrada ou inativa para tenant_id={$tenantId}, filial_id={$filialId}");
        }
    } catch (Exception $e) {
        // Não falhar o pedido se o WhatsApp falhar
        error_log("PEDIDOS_ONLINE - Exception ao enviar notificação WhatsApp: " . $e->getMessage());
    }
    
    // Build response
    $response = [
        'success' => true,
        'pedido_id' => $pedidoId,
        'message' => 'Pedido criado com sucesso!'
    ];
    
    // If online payment was processed successfully, return payment data for transparent checkout
    if ($paymentProcessed && $paymentDataResult && $billingType) {
        $response['payment_id'] = $paymentDataResult['id'];
        
        // Get payment URL - try multiple sources
        $paymentUrl = $paymentDataResult['invoiceUrl'] ?? 
                     $paymentDataResult['invoice_url'] ?? 
                     $paymentDataResult['invoiceURL'] ?? 
                     null;
        
        // If invoiceUrl is not available, construct it from payment ID
        if (!$paymentUrl && isset($paymentDataResult['id'])) {
            $apiUrl = $filial['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            $baseUrl = str_replace('/api/v3', '', $apiUrl);
            $paymentUrl = $baseUrl . '/payment/' . $paymentDataResult['id'];
        }
        
        $response['payment_url'] = $paymentUrl;
        $response['billing_type'] = $billingType;
        
        // For PIX: return QR code data (check multiple possible field names)
        if ($billingType === 'PIX') {
            // Try different field names that Asaas might use
            $response['pix_qr_code_id'] = $paymentDataResult['pixQrCodeId'] ?? 
                                          $paymentDataResult['pix_qr_code_id'] ?? 
                                          $paymentDataResult['pixQrCode']['id'] ?? null;
            
            $response['pix_qr_code'] = $paymentDataResult['pixQrCode'] ?? 
                                      $paymentDataResult['pix_qr_code'] ?? 
                                      $paymentDataResult['encodedPixQrCode'] ?? 
                                      $paymentDataResult['pixQrCode']['base64'] ?? null;
            
            $response['pix_copy_paste'] = $paymentDataResult['pixCopyPaste'] ?? 
                                         $paymentDataResult['pix_copy_paste'] ?? 
                                         $paymentDataResult['pixCopiaECola'] ?? 
                                         $paymentDataResult['pixQrCode']['payload'] ?? null;
            
            // Log what we found
            error_log("PEDIDOS_ONLINE - PIX data in response: " . json_encode([
                'has_qr_code' => !empty($response['pix_qr_code']),
                'has_copy_paste' => !empty($response['pix_copy_paste']),
                'qr_code_length' => $response['pix_qr_code'] ? strlen($response['pix_qr_code']) : 0,
                'payment_id' => $response['payment_id'] ?? null
            ]));
            
            // If QR code is still not available, log full payment data for debugging
            if (empty($response['pix_qr_code']) && empty($response['pix_copy_paste'])) {
                error_log("PEDIDOS_ONLINE - WARNING: PIX QR code not found in payment data. Full payment data: " . json_encode($paymentDataResult));
            }
        }
        
        // For CREDIT_CARD: we'll need to tokenize on frontend
        // The payment is already created, but we need to handle card tokenization separately
    }
    
    ob_end_clean(); // Clean any output before JSON
    echo json_encode($response);
    exit;
    
} catch (Exception $e) {
    error_log("PEDIDOS_ONLINE - Exception: " . $e->getMessage());
    error_log("PEDIDOS_ONLINE - Stack trace: " . $e->getTraceAsString());
    ob_end_clean(); // Clean any output before JSON
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
    exit;
}

