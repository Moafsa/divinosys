<?php

namespace System\WhatsApp;

class PixInvoiceHelper
{
    public static function gerarParaPedido(
        $db,
        int $tenantId,
        int $filialId,
        int $pedidoId,
        float $valor,
        string $nomeCliente = '',
        string $telefoneCliente = '',
        string $descricao = '',
        string $cpfCnpj = ''
    ): array {
        require_once __DIR__ . '/../../mvc/model/AsaasInvoice.php';

        $pedido = $db->fetch(
            'SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?',
            [$pedidoId, $tenantId, $filialId]
        );

        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido não encontrado.'];
        }

        if ($valor <= 0) {
            $valor = (float) ($pedido['valor_total'] ?? 0);
        }

        if ($valor <= 0) {
            return ['success' => false, 'message' => 'Valor do pedido inválido.'];
        }

        $asaasInvoice = new \AsaasInvoice();
        $config = $asaasInvoice->getAsaasConfig($tenantId, $filialId);

        if (empty($config['asaas_enabled']) || empty($config['asaas_api_key'])) {
            return ['success' => false, 'message' => 'Integração Asaas não configurada para este estabelecimento.'];
        }

        $apiUrl = rtrim($config['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3', '/');
        $apiKey = $config['asaas_api_key'];

        $request = function (string $method, string $endpoint, ?array $data = null) use ($apiUrl, $apiKey): array {
            $ch = curl_init($apiUrl . $endpoint);
            $headers = ['access_token: ' . $apiKey, 'Content-Type: application/json', 'User-Agent: DivinoSYS/1.0'];
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30,
            ];

            if (strtoupper($method) === 'POST') {
                $opts[CURLOPT_POST] = true;
                if ($data !== null) {
                    $opts[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            } elseif (strtoupper($method) === 'PUT') {
                $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data !== null) {
                    $opts[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            } else {
                $opts[CURLOPT_HTTPGET] = true;
            }

            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $decoded = json_decode((string) $response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'data' => $decoded];
            }

            return ['success' => false, 'error' => $decoded['errors'] ?? $response, 'http_code' => $httpCode];
        };

        $telefone = preg_replace('/[^0-9]/', '', $telefoneCliente);
        if (strlen($telefone) === 11 || strlen($telefone) === 10) {
            $telefone = '55' . $telefone;
        }
        $mobilePhone = $telefone;
        if (str_starts_with($telefone, '55') && strlen($telefone) > 11) {
            $mobilePhone = substr($telefone, 2);
        }

        $nome = trim($nomeCliente) ?: (string) ($pedido['cliente'] ?? 'Cliente WhatsApp');
        $clienteAsaasId = null;
        $lastError = '';
        $cpf = preg_replace('/[^0-9]/', '', $cpfCnpj);

        if ($mobilePhone !== '') {
            $search = $request('GET', '/customers?mobilePhone=' . urlencode($mobilePhone));
            if (!empty($search['success']) && !empty($search['data']['data'][0]['id'])) {
                $clienteAsaasId = $search['data']['data'][0]['id'];
            }
        }

        if (!$clienteAsaasId) {
            $customerData = [
                'name' => $nome,
                'mobilePhone' => $mobilePhone ?: '54999999999',
                'externalReference' => 'whatsapp_pedido_' . $pedidoId,
            ];
            if (strlen($cpf) === 11 || strlen($cpf) === 14) {
                $customerData['cpfCnpj'] = $cpf;
            }
            $created = $request('POST', '/customers', $customerData);
            if (!empty($created['success']) && !empty($created['data']['id'])) {
                $clienteAsaasId = $created['data']['id'];
            } else {
                $lastError = is_array($created['error'] ?? null)
                    ? json_encode($created['error'], JSON_UNESCAPED_UNICODE)
                    : (string) ($created['error'] ?? '');
            }
        }

        if (!$clienteAsaasId && !empty($config['asaas_customer_id'])) {
            $clienteAsaasId = $config['asaas_customer_id'];
        }

        if (!$clienteAsaasId) {
            $msg = 'Não foi possível criar ou encontrar cliente no Asaas.';
            if ($lastError !== '') {
                $msg .= ' Detalhe: ' . $lastError;
            }
            return ['success' => false, 'message' => $msg];
        }

        if (strlen($cpf) === 11 || strlen($cpf) === 14) {
            $request('PUT', '/customers/' . $clienteAsaasId, [
                'name' => $nome,
                'cpfCnpj' => $cpf,
                'mobilePhone' => $mobilePhone ?: '54999999999',
            ]);
        }

        $host = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://divsys.conext.click';
        $webhookUrl = rtrim($host, '/') . '/webhook/asaas.php';

        $filial = $db->fetch('SELECT nome FROM filiais WHERE id = ? AND tenant_id = ?', [$filialId, $tenantId]);
        $filialNome = $filial['nome'] ?? 'Estabelecimento';

        $paymentData = [
            'customer' => $clienteAsaasId,
            'billingType' => 'PIX',
            'value' => number_format($valor, 2, '.', ''),
            'dueDate' => date('Y-m-d', strtotime('+1 day')),
            'description' => $descricao ?: "Pedido #{$pedidoId} - {$filialNome}",
            'externalReference' => "PEDIDO_{$pedidoId}",
            'webhook' => $webhookUrl,
        ];

        $paymentResult = $request('POST', '/payments', $paymentData);
        if (empty($paymentResult['success']) || empty($paymentResult['data']['id'])) {
            $err = is_array($paymentResult['error'] ?? null)
                ? json_encode($paymentResult['error'])
                : (string) ($paymentResult['error'] ?? 'Erro desconhecido');
            return ['success' => false, 'message' => 'Erro ao criar pagamento PIX no Asaas: ' . $err];
        }

        $payment = $paymentResult['data'];
        $paymentId = $payment['id'];
        $pixCopyPaste = $payment['pixCopyPaste'] ?? $payment['pixCopiaECola'] ?? null;

        if (!$pixCopyPaste) {
            sleep(1);
            $details = $request('GET', '/payments/' . $paymentId);
            if (!empty($details['success'])) {
                $pixCopyPaste = $details['data']['pixCopyPaste'] ?? $details['data']['pixCopiaECola'] ?? null;
            }
        }

        $db->update(
            'pedido',
            [
                'asaas_payment_id' => $paymentId,
                'asaas_payment_url' => $payment['invoiceUrl'] ?? null,
                'forma_pagamento' => 'pix',
                'status_pagamento' => 'pendente',
            ],
            'idpedido = ? AND tenant_id = ? AND filial_id = ?',
            [$pedidoId, $tenantId, $filialId]
        );

        return [
            'success' => true,
            'message' => $pixCopyPaste
                ? "Pedido registrado! Pague via PIX (R$ " . number_format($valor, 2, ',', '.') . "):\n\n" . $pixCopyPaste
                : 'Fatura PIX gerada. Link: ' . ($payment['invoiceUrl'] ?? 'consulte seu e-mail.'),
            'payment_id' => $paymentId,
            'valor' => $valor,
            'pix_copy_paste' => $pixCopyPaste,
            'payment_url' => $payment['invoiceUrl'] ?? null,
        ];
    }
}
