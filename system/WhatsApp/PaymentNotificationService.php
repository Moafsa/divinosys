<?php

namespace System\WhatsApp;

use System\Database;
use Exception;

/**
 * Serviço para enviar notificações de pagamento via WhatsApp
 * Envia mensagens com fatura e código PIX quando disponível
 */
class PaymentNotificationService
{
    private $db;
    private $wuzapiManager;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        // Require WuzAPIManager class
        require_once __DIR__ . '/WuzAPIManager.php';
        $this->wuzapiManager = new WuzAPIManager();
    }
    
    /**
     * Enviar mensagem inicial com fatura e instruções de pagamento
     * 
     * @param int $pedidoId ID do pedido
     * @param int $tenantId ID do tenant
     * @param int $filialId ID da filial
     * @param string $clienteTelefone Telefone do cliente
     * @param string $clienteNome Nome do cliente
     * @param float $valorTotal Valor total do pedido
     * @param string $asaasPaymentId ID do pagamento no Asaas
     * @param string $paymentUrl URL da fatura no Asaas
     * @param string|null $pixCopyPaste Código PIX copia e cola (se disponível)
     * @param string $billingType Tipo de pagamento (PIX, CREDIT_CARD, BOLETO)
     * @return array Resultado do envio
     */
    public function sendPaymentNotification(
        $pedidoId,
        $tenantId,
        $filialId,
        $clienteTelefone,
        $clienteNome,
        $valorTotal,
        $asaasPaymentId,
        $paymentUrl,
        $pixCopyPaste = null,
        $billingType = 'PIX'
    ) {
        try {
            error_log("PaymentNotificationService::sendPaymentNotification - Pedido #$pedidoId");
            
            // Buscar instância WhatsApp ativa
            $instancia = $this->db->fetch(
                "SELECT id FROM whatsapp_instances 
                 WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) 
                 AND status IN ('open', 'connected') AND ativo = true
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId, $filialId]
            );
            
            if (!$instancia) {
                error_log("PaymentNotificationService - Nenhuma instância WhatsApp ativa encontrada");
                return [
                    'success' => false,
                    'message' => 'Nenhuma instância WhatsApp configurada'
                ];
            }
            
            // Formatar telefone
            $telefoneFormatado = $this->formatPhone($clienteTelefone);
            
            // Montar mensagem
            $mensagem = $this->buildPaymentMessage(
                $clienteNome,
                $pedidoId,
                $valorTotal,
                $paymentUrl,
                $pixCopyPaste,
                $billingType
            );
            
            // Enviar mensagem
            $result = $this->wuzapiManager->sendMessage(
                $instancia['id'],
                $telefoneFormatado,
                $mensagem
            );
            
            if ($result['success']) {
                // Agendar lembrete para 10 minutos depois
                $this->scheduleReminder(
                    $pedidoId,
                    $tenantId,
                    $filialId,
                    $asaasPaymentId,
                    $clienteTelefone,
                    $clienteNome,
                    $valorTotal,
                    $paymentUrl,
                    $pixCopyPaste,
                    $billingType,
                    $instancia['id']
                );
                
                error_log("PaymentNotificationService - Mensagem enviada com sucesso para $telefoneFormatado");
            } else {
                error_log("PaymentNotificationService - Erro ao enviar mensagem: " . ($result['message'] ?? 'Erro desconhecido'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PaymentNotificationService::sendPaymentNotification - Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar notificação: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Montar mensagem de pagamento
     */
    private function buildPaymentMessage($clienteNome, $pedidoId, $valorTotal, $paymentUrl, $pixCopyPaste, $billingType)
    {
        $mensagem = "🍔 *Olá, {$clienteNome}!*\n\n";
        $mensagem .= "Seu pedido *#{$pedidoId}* foi criado com sucesso!\n\n";
        $mensagem .= "💰 *Valor Total: R$ " . number_format($valorTotal, 2, ',', '.') . "*\n\n";
        
        if ($billingType === 'PIX' && !empty($pixCopyPaste)) {
            $mensagem .= "📱 *PAGAMENTO VIA PIX*\n\n";
            $mensagem .= "Copie o código abaixo e cole no app do seu banco:\n\n";
            $mensagem .= "```\n{$pixCopyPaste}\n```\n\n";
            $mensagem .= "Ou escaneie o QR Code na página de pagamento.\n\n";
        } elseif ($billingType === 'CREDIT_CARD') {
            $mensagem .= "💳 *PAGAMENTO COM CARTÃO*\n\n";
            $mensagem .= "Clique no link abaixo para finalizar o pagamento com cartão de crédito:\n\n";
        } elseif ($billingType === 'BOLETO') {
            $mensagem .= "📄 *PAGAMENTO VIA BOLETO*\n\n";
            $mensagem .= "Clique no link abaixo para visualizar e imprimir o boleto:\n\n";
        } else {
            $mensagem .= "💳 *FINALIZAR PAGAMENTO*\n\n";
            $mensagem .= "Clique no link abaixo para finalizar seu pagamento:\n\n";
        }
        
        if ($paymentUrl) {
            $mensagem .= "🔗 {$paymentUrl}\n\n";
        }
        
        $mensagem .= "⏰ *Importante:* Finalize o pagamento para garantir seu pedido!\n\n";
        $mensagem .= "Obrigado pela preferência! 🍽️";
        
        return $mensagem;
    }
    
    /**
     * Agendar lembrete para 10 minutos depois
     */
    private function scheduleReminder(
        $pedidoId,
        $tenantId,
        $filialId,
        $asaasPaymentId,
        $clienteTelefone,
        $clienteNome,
        $valorTotal,
        $paymentUrl,
        $pixCopyPaste,
        $billingType,
        $whatsappInstanceId
    ) {
        try {
            // Verificar se o pagamento já foi concluído antes de agendar
            // Se já estiver pago, não agendar lembrete
            $pedido = $this->db->fetch(
                "SELECT status_pagamento FROM pedido WHERE idpedido = ?",
                [$pedidoId]
            );
            
            if ($pedido && in_array($pedido['status_pagamento'], ['pago', 'paid', 'quitado'])) {
                error_log("PaymentNotificationService - Pagamento já concluído, não agendando lembrete");
                return;
            }
            
            // Agendar para 10 minutos depois
            $scheduledFor = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $this->db->insert('payment_reminders', [
                'pedido_id' => $pedidoId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'asaas_payment_id' => $asaasPaymentId,
                'cliente_telefone' => $clienteTelefone,
                'cliente_nome' => $clienteNome,
                'valor_total' => $valorTotal,
                'payment_url' => $paymentUrl,
                'pix_copy_paste' => $pixCopyPaste,
                'billing_type' => $billingType,
                'reminder_type' => 'followup',
                'scheduled_for' => $scheduledFor,
                'status' => 'pending',
                'whatsapp_instance_id' => $whatsappInstanceId
            ]);
            
            error_log("PaymentNotificationService - Lembrete agendado para $scheduledFor");
            
        } catch (Exception $e) {
            error_log("PaymentNotificationService::scheduleReminder - Exception: " . $e->getMessage());
        }
    }
    
    /**
     * Processar lembretes agendados
     * Deve ser chamado periodicamente (cron job ou endpoint)
     */
    public function processScheduledReminders()
    {
        try {
            error_log("PaymentNotificationService::processScheduledReminders - Iniciando processamento");
            
            // Buscar lembretes pendentes que devem ser enviados agora
            $reminders = $this->db->fetchAll(
                "SELECT * FROM payment_reminders 
                 WHERE status = 'pending' 
                 AND scheduled_for <= NOW()
                 ORDER BY scheduled_for ASC
                 LIMIT 50"
            );
            
            if (empty($reminders)) {
                error_log("PaymentNotificationService - Nenhum lembrete pendente");
                return [
                    'success' => true,
                    'processed' => 0,
                    'message' => 'Nenhum lembrete pendente'
                ];
            }
            
            $processed = 0;
            $failed = 0;
            
            foreach ($reminders as $reminder) {
                try {
                    // PRIMEIRO: Verificar status diretamente no Asaas (fonte da verdade)
                    $paymentPaid = false;
                    
                    if (!empty($reminder['asaas_payment_id'])) {
                        $paymentPaid = $this->checkPaymentStatusInAsaas(
                            $reminder['tenant_id'],
                            $reminder['filial_id'],
                            $reminder['asaas_payment_id']
                        );
                        
                        if ($paymentPaid) {
                            error_log("PaymentNotificationService - Pagamento #{$reminder['asaas_payment_id']} confirmado no Asaas");
                            
                            // Atualizar status no banco local também
                            $this->db->update(
                                'pedido',
                                [
                                    'status_pagamento' => 'quitado',
                                    'updated_at' => date('Y-m-d H:i:s')
                                ],
                                'idpedido = ?',
                                [$reminder['pedido_id']]
                            );
                        }
                    }
                    
                    // SEGUNDO: Verificar status no banco local (fallback)
                    if (!$paymentPaid) {
                        $pedido = $this->db->fetch(
                            "SELECT status_pagamento FROM pedido WHERE idpedido = ?",
                            [$reminder['pedido_id']]
                        );
                        
                        if ($pedido && in_array($pedido['status_pagamento'], ['pago', 'paid', 'quitado'])) {
                            $paymentPaid = true;
                        }
                    }
                    
                    // Se pagamento foi confirmado (no Asaas ou no banco), cancelar lembrete
                    if ($paymentPaid) {
                        $this->db->update(
                            'payment_reminders',
                            [
                                'status' => 'cancelled',
                                'updated_at' => date('Y-m-d H:i:s')
                            ],
                            'id = ?',
                            [$reminder['id']]
                        );
                        error_log("PaymentNotificationService - Lembrete #{$reminder['id']} cancelado (pagamento já concluído)");
                        continue;
                    }
                    
                    // Enviar lembrete
                    $result = $this->sendReminderMessage($reminder);
                    
                    if ($result['success']) {
                        $this->db->update(
                            'payment_reminders',
                            [
                                'status' => 'sent',
                                'sent_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ],
                            'id = ?',
                            [$reminder['id']]
                        );
                        $processed++;
                        error_log("PaymentNotificationService - Lembrete #{$reminder['id']} enviado com sucesso");
                    } else {
                        $this->db->update(
                            'payment_reminders',
                            [
                                'status' => 'failed',
                                'error_message' => $result['message'] ?? 'Erro desconhecido',
                                'updated_at' => date('Y-m-d H:i:s')
                            ],
                            'id = ?',
                            [$reminder['id']]
                        );
                        $failed++;
                        error_log("PaymentNotificationService - Erro ao enviar lembrete #{$reminder['id']}: " . ($result['message'] ?? 'Erro desconhecido'));
                    }
                    
                } catch (Exception $e) {
                    error_log("PaymentNotificationService - Exception ao processar lembrete #{$reminder['id']}: " . $e->getMessage());
                    $this->db->update(
                        'payment_reminders',
                        [
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'updated_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$reminder['id']]
                    );
                    $failed++;
                }
            }
            
            error_log("PaymentNotificationService - Processamento concluído: $processed enviados, $failed falhas");
            
            return [
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($reminders)
            ];
            
        } catch (Exception $e) {
            error_log("PaymentNotificationService::processScheduledReminders - Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar lembretes: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensagem de lembrete
     */
    private function sendReminderMessage($reminder)
    {
        try {
            // Buscar instância WhatsApp
            $instanciaId = $reminder['whatsapp_instance_id'];
            
            if (!$instanciaId) {
                // Tentar buscar instância ativa
                $instancia = $this->db->fetch(
                    "SELECT id FROM whatsapp_instances 
                     WHERE tenant_id = ? AND (filial_id = ? OR filial_id IS NULL) 
                     AND status IN ('open', 'connected') AND ativo = true
                     ORDER BY created_at DESC LIMIT 1",
                    [$reminder['tenant_id'], $reminder['filial_id']]
                );
                
                if (!$instancia) {
                    return [
                        'success' => false,
                        'message' => 'Nenhuma instância WhatsApp ativa encontrada'
                    ];
                }
                
                $instanciaId = $instancia['id'];
            }
            
            // Formatar telefone
            $telefoneFormatado = $this->formatPhone($reminder['cliente_telefone']);
            
            // Montar mensagem de lembrete
            $mensagem = "⏰ *Falta pouco para concluir seu pedido!*\n\n";
            $mensagem .= "Olá, {$reminder['cliente_nome']}!\n\n";
            $mensagem .= "Notamos que você ainda não finalizou o pagamento do pedido *#{$reminder['pedido_id']}*.\n\n";
            $mensagem .= "💰 *Valor: R$ " . number_format($reminder['valor_total'], 2, ',', '.') . "*\n\n";
            
            if ($reminder['billing_type'] === 'PIX' && !empty($reminder['pix_copy_paste'])) {
                $mensagem .= "📱 *Código PIX (copiar e colar):*\n\n";
                $mensagem .= "```\n{$reminder['pix_copy_paste']}\n```\n\n";
            }
            
            if ($reminder['payment_url']) {
                $mensagem .= "🔗 Finalize agora: {$reminder['payment_url']}\n\n";
            }
            
            $mensagem .= "Não perca seu pedido! Finalize o pagamento agora. 🍔\n\n";
            $mensagem .= "Obrigado pela preferência! 🍽️";
            
            // Enviar mensagem
            $result = $this->wuzapiManager->sendMessage(
                $instanciaId,
                $telefoneFormatado,
                $mensagem
            );
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PaymentNotificationService::sendReminderMessage - Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar lembrete: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status do pagamento diretamente no Asaas
     * Retorna true se o pagamento foi confirmado, false caso contrário
     */
    private function checkPaymentStatusInAsaas($tenantId, $filialId, $asaasPaymentId)
    {
        try {
            // Buscar configuração do Asaas
            $filial = $this->db->fetch(
                "SELECT f.*, t.asaas_api_key, t.asaas_api_url, t.asaas_enabled
                 FROM filiais f
                 INNER JOIN tenants t ON f.tenant_id = t.id
                 WHERE f.id = ? AND f.tenant_id = ?",
                [$filialId, $tenantId]
            );
            
            if (!$filial || !$filial['asaas_enabled'] || empty($filial['asaas_api_key'])) {
                error_log("PaymentNotificationService - Asaas não configurado para tenant $tenantId, filial $filialId");
                return false;
            }
            
            $api_url = $filial['asaas_api_url'] ?? 'https://sandbox.asaas.com/api/v3';
            $api_key = $filial['asaas_api_key'];
            
            // Consultar status do pagamento no Asaas
            $url = $api_url . '/payments/' . $asaasPaymentId;
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("PaymentNotificationService - Erro ao consultar Asaas: $curlError");
                return false;
            }
            
            if ($httpCode !== 200) {
                error_log("PaymentNotificationService - Asaas retornou HTTP $httpCode para pagamento $asaasPaymentId");
                return false;
            }
            
            $paymentData = json_decode($response, true);
            
            if (!$paymentData || !isset($paymentData['status'])) {
                error_log("PaymentNotificationService - Resposta inválida do Asaas para pagamento $asaasPaymentId");
                return false;
            }
            
            // Status do Asaas que indicam pagamento confirmado
            $paidStatuses = [
                'CONFIRMED',
                'RECEIVED',
                'RECEIVED_IN_CASH',
                'DUNNING_RECEIVED'
            ];
            
            $isPaid = in_array($paymentData['status'], $paidStatuses);
            
            if ($isPaid) {
                error_log("PaymentNotificationService - Pagamento $asaasPaymentId confirmado no Asaas (status: {$paymentData['status']})");
            }
            
            return $isPaid;
            
        } catch (Exception $e) {
            error_log("PaymentNotificationService::checkPaymentStatusInAsaas - Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formatar número de telefone
     */
    private function formatPhone($phone)
    {
        // Remove caracteres não numéricos
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Se já tem código do país, retornar como está
        if (strlen($cleaned) >= 12 && substr($cleaned, 0, 2) === '55') {
            return '+' . $cleaned;
        }
        
        // Se tem 11 dígitos (DDD + número), adicionar código do Brasil
        if (strlen($cleaned) == 11) {
            return '+55' . $cleaned;
        }
        
        // Se tem 10 dígitos, adicionar código do Brasil
        if (strlen($cleaned) == 10) {
            return '+55' . $cleaned;
        }
        
        // Default: assumir Brasil
        return '+55' . $cleaned;
    }
}

