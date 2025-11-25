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
    
    // Prepare order data
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
        throw new Exception('Erro ao criar pedido no banco de dados');
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
    
    $response = [
        'success' => true,
        'pedido_id' => $pedidoId,
        'message' => 'Pedido criado com sucesso!'
    ];
    
    // Process online payment if selected
    if ($formaPagamento === 'online' && $filial['asaas_enabled'] && $filial['asaas_api_key']) {
        try {
            require_once __DIR__ . '/../../mvc/model/AsaasPayment.php';
            
            // Create AsaasPayment instance
            // Note: AsaasPayment uses env vars, but we'll use tenant/filial config
            // For now, we'll create payment directly via cURL
            $asaasApiKey = $filial['asaas_api_key'];
            $asaasApiUrl = $filial['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            
            // Create customer in Asaas if needed
            $asaasCustomerId = $filial['asaas_customer_id'];
            if (!$asaasCustomerId && $clienteEmail) {
                // Create customer via API
                $customerData = [
                    'name' => $clienteNome,
                    'email' => $clienteEmail,
                    'phone' => preg_replace('/[^0-9]/', '', $clienteTelefone),
                    'externalReference' => 'cliente_' . ($clienteId ?? 'temp_' . time())
                ];
                
                $ch = curl_init($asaasApiUrl . '/customers');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'access_token: ' . $asaasApiKey,
                    'Content-Type: application/json'
                ]);
                
                $customerResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    $customerResult = json_decode($customerResponse, true);
                    if (isset($customerResult['id'])) {
                        $asaasCustomerId = $customerResult['id'];
                    }
                }
            }
            
            if ($asaasCustomerId) {
                // Create payment charge via API
                $paymentData = [
                    'customer' => $asaasCustomerId,
                    'billingType' => 'PIX',
                    'value' => number_format($valorTotal, 2, '.', ''),
                    'dueDate' => date('Y-m-d', strtotime('+1 day')),
                    'description' => "Pedido #{$pedidoId} - {$filial['nome']}",
                    'externalReference' => 'pedido_' . $pedidoId
                ];
                
                $ch = curl_init($asaasApiUrl . '/payments');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'access_token: ' . $asaasApiKey,
                    'Content-Type: application/json'
                ]);
                
                $paymentResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    $paymentResult = json_decode($paymentResponse, true);
                    
                    // Save payment reference
                    $db->update('pedido', [
                        'asaas_payment_id' => $paymentResult['id'] ?? null,
                        'asaas_payment_url' => $paymentResult['invoiceUrl'] ?? null
                    ], ['idpedido' => $pedidoId]);
                    
                    // Return payment URL for redirect
                    if (isset($paymentResult['invoiceUrl'])) {
                        $response['payment_url'] = $paymentResult['invoiceUrl'];
                        $response['payment_id'] = $paymentResult['id'];
                    } elseif (isset($paymentResult['pixQrCodeId'])) {
                        // PIX QR Code
                        $response['payment_url'] = '/pagamento/pix.php?payment_id=' . $paymentResult['id'];
                        $response['payment_id'] = $paymentResult['id'];
                    }
                } else {
                    error_log("Erro ao criar pagamento Asaas: HTTP $httpCode - $paymentResponse");
                    // Order created but payment failed - can be paid later
                    $response['payment_error'] = 'Pedido criado, mas houve erro ao processar pagamento online. Você pode pagar na hora.';
                }
            } else {
                error_log("Erro: Não foi possível criar ou encontrar cliente no Asaas");
                $response['payment_error'] = 'Pedido criado, mas houve erro ao processar pagamento online. Você pode pagar na hora.';
            }
        } catch (Exception $e) {
            error_log("Erro ao processar pagamento online: " . $e->getMessage());
            // Order created but payment failed
            $response['payment_error'] = 'Pedido criado, mas houve erro ao processar pagamento online. Você pode pagar na hora.';
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

