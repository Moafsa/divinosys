<?php
/**
 * Wuzapi Webhook Handler — recebe mensagens do WhatsApp e responde com IA.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => __DIR__ . '/../../system/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/OpenAIService.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
require_once __DIR__ . '/../../system/TelefoneHelper.php';
require_once __DIR__ . '/../../system/WhatsApp/AiPromptBuilder.php';

header('Content-Type: application/json');

function extrairTelefoneWhatsApp(string $jid): string
{
    $jid = trim($jid);
    if ($jid === '') {
        return '';
    }

    // 555497092223:7@s.whatsapp.net ou 5554997092223@c.us
    $parte = explode('@', $jid)[0] ?? $jid;
    $parte = explode(':', $parte)[0] ?? $parte;
    $digitos = preg_replace('/[^0-9]/', '', $parte);

    return \System\TelefoneHelper::canonico($digitos) ?: $digitos;
}

function saveInlineMediaToFile(array $inline): ?string
{
    if (empty($inline['base64'])) {
        return null;
    }

    $binary = base64_decode((string) $inline['base64'], true);
    if ($binary === false || $binary === '') {
        return null;
    }

    $filename = (string) ($inline['filename'] ?? 'media');
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext === '' || $ext === $filename) {
        $mime = strtolower((string) ($inline['mimetype'] ?? ''));
        $ext = match (true) {
            str_contains($mime, 'ogg'), str_contains($mime, 'opus') => 'ogg',
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'mp3'), str_contains($mime, 'mpeg') => 'mp3',
            default => 'bin',
        };
    }

    $path = sys_get_temp_dir() . '/wuzapi_inline_' . uniqid('', true) . '.' . $ext;
    file_put_contents($path, $binary);

    return $path;
}

function applyInlineMediaMeta(array &$result, array $inner): void
{
    if (empty($inner['base64'])) {
        return;
    }

    $result['inline_media'] = [
        'base64' => (string) $inner['base64'],
        'mimetype' => (string) ($inner['mimeType'] ?? $inner['mimetype'] ?? ''),
        'filename' => (string) ($inner['fileName'] ?? $inner['filename'] ?? 'media'),
    ];

    $mime = strtolower($result['inline_media']['mimetype']);
    if (str_starts_with($mime, 'audio/') || str_contains($mime, 'ogg') || str_contains($mime, 'opus')) {
        $result['message_type'] = 'audio';
    } elseif (str_starts_with($mime, 'image/')) {
        $result['message_type'] = 'image';
    }
}

function parseWuzapiPayload(array $data): array
{
    $result = [
        'instance_id' => '',
        'from' => '',
        'message' => '',
        'message_type' => 'text',
        'is_group' => false,
        'is_from_me' => false,
        'message_id' => '',
        'push_name' => '',
        'media' => null,
        'inline_media' => null,
    ];

    // Formato asternic/wuzapi: { instanceName, jsonData, userID }
    if (!empty($data['jsonData'])) {
        $inner = json_decode((string) $data['jsonData'], true);
        if (!is_array($inner)) {
            throw new Exception('jsonData inválido no webhook WuzAPI');
        }

        $result['instance_id'] = (string) ($data['userID'] ?? '');
        $event = $inner['event'] ?? $inner;
        $info = $event['Info'] ?? $event['info'] ?? [];

        $result['is_from_me'] = (bool) ($info['IsFromMe'] ?? false);
        $result['is_group'] = (bool) ($info['IsGroup'] ?? false);
        $result['message_id'] = (string) ($info['ID'] ?? '');
        $result['push_name'] = (string) ($info['PushName'] ?? '');

        $senderAlt = (string) ($info['SenderAlt'] ?? '');
        $sender = (string) ($info['Sender'] ?? ($info['Chat'] ?? ''));
        $jid = $senderAlt !== '' ? $senderAlt : $sender;
        $result['from'] = $jid;

        $msg = $event['Message'] ?? [];
        if (!empty($msg['conversation'])) {
            $result['message'] = (string) $msg['conversation'];
            $result['message_type'] = 'text';
        } elseif (!empty($msg['extendedTextMessage']['text'])) {
            $result['message'] = (string) $msg['extendedTextMessage']['text'];
            $result['message_type'] = 'text';
        } elseif (!empty($msg['audioMessage'])) {
            $result['message_type'] = 'audio';
            $result['media'] = $msg['audioMessage'];
        } elseif (!empty($msg['imageMessage'])) {
            $result['message_type'] = 'image';
            $result['media'] = $msg['imageMessage'];
            $result['message'] = (string) ($msg['imageMessage']['caption'] ?? '');
        } else {
            $result['message_type'] = 'other';
        }

        applyInlineMediaMeta($result, $inner);

        return $result;
    }

    // WuzAPI JSON mode: event no corpo raiz
    if (!empty($data['event']) && is_array($data['event'])) {
        $wrapped = [
            'userID' => (string) ($data['userID'] ?? $data['instanceId'] ?? ''),
            'instanceName' => (string) ($data['instanceName'] ?? ''),
            'jsonData' => json_encode($data),
        ];
        return parseWuzapiPayload($wrapped);
    }

    // Formato simplificado (testes / legado)
    if (isset($data['event']) && isset($data['data']['Info'])) {
        $info = $data['data']['Info'];
        $result['instance_id'] = (string) ($data['instanceId'] ?? $data['instance_id'] ?? '');
        $result['is_from_me'] = (bool) ($info['MessageSource']['IsFromMe'] ?? $info['IsFromMe'] ?? false);
        $result['is_group'] = (bool) ($info['MessageSource']['IsGroup'] ?? $info['IsGroup'] ?? false);
        $result['from'] = (string) ($info['MessageSource']['Sender'] ?? $info['SenderAlt'] ?? $info['Sender'] ?? '');
        $msg = $data['data']['Message'] ?? [];
        $result['message'] = (string) ($msg['conversation'] ?? ($msg['extendedTextMessage']['text'] ?? ''));
        return $result;
    }

    $result['instance_id'] = (string) ($data['instanceId'] ?? $data['instance_id'] ?? $data['userID'] ?? '');
    $result['from'] = (string) ($data['from'] ?? '');
    $result['message'] = (string) ($data['message'] ?? $data['text'] ?? '');
    $result['message_type'] = (string) ($data['type'] ?? 'text');
    $result['is_group'] = (bool) ($data['isGroup'] ?? false);
    $result['is_from_me'] = (bool) ($data['isFromMe'] ?? false);

    return $result;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = file_get_contents('php://input');
    error_log('Wuzapi Webhook - Raw: ' . substr($input, 0, 500));

    $data = json_decode($input, true);
    if (!$data && !empty($_POST['jsonData'])) {
        $data = [
            'jsonData' => $_POST['jsonData'],
            'userID' => $_POST['userID'] ?? '',
            'instanceName' => $_POST['instanceName'] ?? '',
        ];
    }
    if (!$data && $input !== '') {
        $form = [];
        parse_str($input, $form);
        if (!empty($form['jsonData'])) {
            $data = [
                'jsonData' => $form['jsonData'],
                'userID' => $form['userID'] ?? '',
                'instanceName' => $form['instanceName'] ?? '',
            ];
        }
    }
    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }

    $parsed = parseWuzapiPayload($data);

    if ($parsed['is_from_me']) {
        echo json_encode(['success' => true, 'message' => 'Self messages ignored']);
        exit;
    }

    if ($parsed['is_group']) {
        echo json_encode(['success' => true, 'message' => 'Group messages ignored']);
        exit;
    }

    if (!in_array($parsed['message_type'], ['text', 'chat', 'audio', 'image'], true)) {
        echo json_encode(['success' => true, 'message' => 'Unsupported message type ignored']);
        exit;
    }

    if ($parsed['instance_id'] === '') {
        throw new Exception('Missing required field: instanceId');
    }

    if (in_array($parsed['message_type'], ['text', 'chat'], true) && trim($parsed['message']) === '') {
        throw new Exception('Missing required field: message');
    }

    if (in_array($parsed['message_type'], ['audio', 'image'], true)
        && empty($parsed['media']) && empty($parsed['inline_media'])) {
        throw new Exception('Missing media metadata');
    }

    $phoneNumber = extrairTelefoneWhatsApp($parsed['from']);
    if ($phoneNumber === '') {
        throw new Exception('Could not extract phone from: ' . $parsed['from']);
    }

    $db = \System\Database::getInstance();

    $instance = $db->fetch(
        "SELECT id, tenant_id, filial_id, instance_name, phone_number, ai_config
         FROM whatsapp_instances
         WHERE wuzapi_instance_id = ?
            OR instance_name = ?
            OR REPLACE(phone_number, '+', '') = ?
         ORDER BY CASE WHEN status = 'connected' THEN 0 ELSE 1 END, id
         LIMIT 1",
        [$parsed['instance_id'], $data['instanceName'] ?? '', $phoneNumber]
    );

    if (!$instance) {
        $instance = $db->fetch(
            "SELECT id, tenant_id, filial_id, instance_name, phone_number
             FROM whatsapp_instances
             WHERE ativo = true
             ORDER BY CASE WHEN status = 'connected' THEN 0 ELSE 1 END, id
             LIMIT 1"
        );
    }

    if (!$instance) {
        throw new Exception('No active WhatsApp instance found');
    }

    $tenantId = (int) $instance['tenant_id'];
    $filialId = (int) $instance['filial_id'];
    $instanceDbId = (int) $instance['id'];

    $variacoes = \System\TelefoneHelper::getVariacoes($phoneNumber);
    $placeholders = implode(',', array_fill(0, count($variacoes), '?'));

    $cliente = $db->fetch(
        "SELECT id, nome, telefone FROM usuarios_globais
         WHERE ativo = true
           AND REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g') IN ({$placeholders})
         LIMIT 1",
        $variacoes
    );

    $customerName = $parsed['push_name'] ?: ($cliente['nome'] ?? 'Cliente WhatsApp');

    $isAdmin = (bool) $db->fetch(
        "SELECT 1 AS ok FROM whatsapp_admins
         WHERE tenant_id = ? AND filial_id = ? AND ativo = true
           AND REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g') IN ({$placeholders})
         LIMIT 1",
        array_merge([$tenantId, $filialId], $variacoes)
    );

    $message = trim($parsed['message']);
    $aiService = new \System\OpenAIService();
    $wuzapi = new \System\WhatsApp\WuzAPIManager();
    $mediaPath = null;

    if ($parsed['message_type'] === 'audio') {
        if (!empty($parsed['inline_media'])) {
            $mediaPath = saveInlineMediaToFile($parsed['inline_media']);
        } elseif (!empty($parsed['media'])) {
            $download = $wuzapi->downloadMediaToFile($instanceDbId, 'audio', $parsed['media']);
            if (!empty($download['success'])) {
                $mediaPath = $download['path'];
            } else {
                error_log('Wuzapi Webhook - Falha download áudio: ' . ($download['message'] ?? ''));
            }
        }

        if ($mediaPath && file_exists($mediaPath)) {
            $transcription = $aiService->transcribeAudio($mediaPath);
            if (!empty($transcription['success']) && trim($transcription['text'] ?? '') !== '') {
                $message = trim($transcription['text']);
                error_log('Wuzapi Webhook - Áudio transcrito: ' . substr($message, 0, 120));
            } else {
                $message = 'Recebi um áudio, mas não consegui transcrever. Pode repetir por texto?';
            }
        } else {
            $message = 'Recebi seu áudio, mas não consegui processá-lo. Pode repetir por texto?';
        }
    } elseif ($parsed['message_type'] === 'image') {
        if (!empty($parsed['inline_media'])) {
            $mediaPath = saveInlineMediaToFile($parsed['inline_media']);
        } elseif (!empty($parsed['media'])) {
            $download = $wuzapi->downloadMediaToFile($instanceDbId, 'image', $parsed['media']);
            if (!empty($download['success'])) {
                $mediaPath = $download['path'];
            } else {
                error_log('Wuzapi Webhook - Falha download imagem: ' . ($download['message'] ?? ''));
            }
        }

        if ($mediaPath && file_exists($mediaPath)) {
            $analysis = $aiService->analyzeImageFile($mediaPath);
            if (!empty($analysis['success'])) {
                $message = 'O cliente enviou uma imagem.';
                if (trim($parsed['message']) !== '') {
                    $message .= ' Legenda: ' . trim($parsed['message']) . '.';
                }
                $message .= ' Conteúdo identificado na imagem: ' . $analysis['text'];
            } else {
                error_log('Wuzapi Webhook - Falha análise imagem: ' . ($analysis['message'] ?? ''));
                $message = trim($parsed['message']) !== ''
                    ? 'O cliente enviou uma imagem com a legenda: ' . trim($parsed['message'])
                    : 'O cliente enviou uma imagem, mas não consegui analisá-la. Pode descrever por texto?';
            }
        } else {
            $message = trim($parsed['message']) !== ''
                ? 'O cliente enviou uma imagem com a legenda: ' . trim($parsed['message'])
                : 'Recebi sua imagem, mas não consegui abri-la. Pode descrever por texto?';
        }
    }

    if ($mediaPath && file_exists($mediaPath)) {
        @unlink($mediaPath);
    }

    $tenant = $db->fetch("SELECT nome FROM tenants WHERE id = ?", [$tenantId]);
    $aiSystemPrompt = \System\WhatsApp\AiPromptBuilder::buildFromInstance($instance, $tenant['nome'] ?? null);
    $ignoreStock = \System\WhatsApp\AiPromptBuilder::ignoresStock($instance);

    $contextData = [
        'customer_phone' => $phoneNumber,
        'customer_name' => $customerName,
        'source' => 'whatsapp',
        'instance_id' => $parsed['instance_id'],
        'is_admin' => (bool) $isAdmin,
        'ai_system_prompt' => $aiSystemPrompt,
        'ignore_stock' => $ignoreStock,
    ];

    $aiResponse = $aiService->processWhatsAppMessage($message, $tenantId, $filialId, $contextData);

    if (empty($aiResponse['success'])) {
        throw new Exception('AI processing failed: ' . ($aiResponse['error'] ?? ($aiResponse['response']['message'] ?? 'Unknown error')));
    }

    $responseMessage = $aiResponse['response']['message'] ?? $aiResponse['message'] ?? 'Desculpe, não entendi. Pode repetir?';

    $sendResult = $wuzapi->sendMessage($instanceDbId, $phoneNumber, $responseMessage);

    if (empty($sendResult['success'])) {
        error_log('Wuzapi Webhook - Failed to send response: ' . ($sendResult['message'] ?? 'Unknown error'));
    }

    try {
        $db->insert('whatsapp_messages', [
            'instance_id' => $instanceDbId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'message_id' => $parsed['message_id'] ?: null,
            'from_number' => $phoneNumber,
            'to_number' => preg_replace('/[^0-9]/', '', $instance['phone_number'] ?? ''),
            'message_text' => $message,
            'message_type' => $parsed['message_type'],
            'status' => 'received',
            'source' => 'whatsapp',
            'direction' => 'inbound',
            'metadata' => json_encode(['response' => $responseMessage]),
        ]);
    } catch (\Exception $logErr) {
        error_log('Wuzapi Webhook - log insert failed: ' . $logErr->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Message processed and response sent',
        'phone' => $phoneNumber,
        'sent' => !empty($sendResult['success']),
    ]);
} catch (Exception $e) {
    error_log('Wuzapi Webhook - Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
