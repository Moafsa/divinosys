<?php
/**
 * API endpoint for online menu orders
 * Handles order creation from public online menu
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

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
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
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
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
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
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
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
        $usuarioPadrao = $db->fetch(
            "SELECT id FROM usuarios WHERE tenant_id = ? AND (tipo = 'admin' OR tipo = 'gerente') LIMIT 1",
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
    
    if ($formaPagamento === 'online') {
        try {
            require_once __DIR__ . '/../model/AsaasInvoice.php';
            
            // Get Asaas configuration for filial (or tenant if filial doesn't have one)
            $asaasInvoice = new AsaasInvoice();
            $asaasConfig = $asaasInvoice->getAsaasConfig($tenantId, $filialId);
            
            if (!$asaasConfig || !$asaasConfig['asaas_enabled'] || empty($asaasConfig['asaas_api_key'])) {
                throw new Exception('Integração Asaas não configurada para esta filial');
            }
            
            $api_url = $asaasConfig['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            $api_key = $asaasConfig['asaas_api_key'];
            
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
            
            // CPF is required for Asaas payment
            if (empty($data['cliente_cpf'])) {
                throw new Exception('CPF é obrigatório para pagamento online');
            }
            
            // Create customer in Asaas
            $customerData = [
                'name' => $clienteNome,
                'phone' => preg_replace('/[^0-9]/', '', $clienteTelefone),
                'cpfCnpj' => preg_replace('/[^0-9]/', '', $data['cliente_cpf']),
                'externalReference' => 'cliente_pedido_' . ($clienteId ?? 'temp_' . time())
            ];
            
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
            
            // Create payment charge in Asaas BEFORE creating order
            // Use temporary reference - will update with real order ID after
            $paymentData = [
                'customer' => $clienteAsaasId,
                'billingType' => $billingType,
                'value' => number_format($valorTotal, 2, '.', ''),
                'dueDate' => date('Y-m-d', strtotime('+1 day')),
                'description' => "Pedido - {$filial['nome']}",
                'externalReference' => 'pedido_temp_' . time(),
                'webhook' => $webhookUrl
            ];
            
            $paymentResult = $makeAsaasRequest('POST', '/payments', $paymentData);
            
            if ($paymentResult['success'] && isset($paymentResult['data']['id'])) {
                $paymentDataResult = $paymentResult['data'];
                $paymentProcessed = true;
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
    $pedidoData = [
        'idmesa' => $mesaId,
        'cliente' => $clienteNome,
        'usuario_global_id' => $clienteId,
        'telefone_cliente' => $clienteTelefone,
        'delivery' => ($tipoEntrega === 'delivery') ? 1 : 0,
        'tipo_entrega' => $tipoEntrega,
        'data' => date('Y-m-d'),
        'hora_pedido' => date('H:i:s'),
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
    
    // Build response
    $response = [
        'success' => true,
        'pedido_id' => $pedidoId,
        'message' => 'Pedido criado com sucesso!'
    ];
    
    // If online payment was processed successfully, return payment URL
    if ($paymentProcessed && $paymentDataResult) {
        if (isset($paymentDataResult['invoiceUrl'])) {
            $response['payment_url'] = $paymentDataResult['invoiceUrl'];
            $response['payment_id'] = $paymentDataResult['id'];
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

