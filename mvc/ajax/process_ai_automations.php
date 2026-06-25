<?php
/**
 * Processa automações de IA: abandono de pedido WhatsApp e mensagem de saudade.
 * Executar via cron a cada 5 minutos.
 */

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
require_once __DIR__ . '/../../system/WhatsApp/WhatsAppOrderSessionService.php';

header('Content-Type: application/json');

try {
    $db = \System\Database::getInstance();
    $wuzapi = new \System\WhatsApp\WuzAPIManager();
    $sessions = new \System\WhatsApp\WhatsAppOrderSessionService($db);

    $sentAbandono = 0;
    $sentSaudade = 0;
    $errors = [];

    foreach ($sessions->getSessionsForAbandonFollowup() as $row) {
        try {
            $phone = (string) ($row['phone'] ?? '');
            $nome = (string) ($row['customer_name'] ?? 'Cliente');
            $instanceId = (int) ($row['instance_id'] ?? 0);
            $template = (string) ($row['mensagem_template'] ?? 'Oi {nome}! Notei que você não finalizou o pedido. Precisa de alguma ajuda?');
            $msg = str_replace('{nome}', $nome, $template);

            if ($phone === '' || $instanceId <= 0) {
                continue;
            }

            $result = $wuzapi->sendMessage($instanceId, $phone, $msg);
            if (empty($result['success'])) {
                $errors[] = "Abandono {$phone}: " . ($result['message'] ?? 'falha ao enviar');
                continue;
            }

            $sessions->markFollowupSent((int) $row['id']);
            $sentAbandono++;

            $instancePhone = $db->fetch('SELECT phone_number FROM whatsapp_instances WHERE id = ?', [$instanceId]);
            $db->insert('whatsapp_messages', [
                'instance_id' => $instanceId,
                'tenant_id' => (int) $row['tenant_id'],
                'filial_id' => $row['filial_id'] ?? null,
                'from_number' => preg_replace('/[^0-9]/', '', $instancePhone['phone_number'] ?? ''),
                'to_number' => $phone,
                'message_text' => '[automação abandono]',
                'message_type' => 'text',
                'status' => 'sent',
                'source' => 'automation',
                'direction' => 'outbound',
                'metadata' => json_encode(['response' => $msg, 'automation' => 'abandono']),
            ]);
        } catch (\Throwable $e) {
            $errors[] = 'Abandono session ' . ($row['id'] ?? '?') . ': ' . $e->getMessage();
        }
    }

    $automacoesSaudade = $db->fetchAll("SELECT * FROM ai_automations WHERE tipo = 'saudade' AND ativo = true");
    foreach ($automacoesSaudade as $auto) {
        $dias = max(1, (int) ($auto['tempo_espera'] ?? 15));
        $tenantId = (int) $auto['tenant_id'];
        $filialId = $auto['filial_id'] ?? null;
        $template = (string) ($auto['mensagem_template'] ?? 'Oi {nome}, sumiu! Que tal um lanche hoje?');

        $instance = $db->fetch(
            "SELECT id, phone_number FROM whatsapp_instances
             WHERE tenant_id = ? AND filial_id = ? AND status = 'connected' AND ativo = true
             ORDER BY id DESC LIMIT 1",
            [$tenantId, $filialId]
        );

        if (!$instance) {
            continue;
        }

        $clientes = $sessions->getCustomersForSaudade($tenantId, $filialId, $dias);
        foreach ($clientes as $cliente) {
            $phone = (string) ($cliente['phone'] ?? '');
            if ($phone === '') {
                continue;
            }

            $nome = (string) ($cliente['customer_name'] ?? 'Cliente');
            $msg = str_replace('{nome}', $nome, $template);
            $result = $wuzapi->sendMessage((int) $instance['id'], $phone, $msg);
            if (!empty($result['success'])) {
                $sentSaudade++;
            } else {
                $errors[] = "Saudade {$phone}: " . ($result['message'] ?? 'falha');
            }
        }
    }

    echo json_encode([
        'success' => true,
        'abandono_enviados' => $sentAbandono,
        'saudade_enviados' => $sentSaudade,
        'errors' => $errors,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
