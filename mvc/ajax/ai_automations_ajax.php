<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

$session = \System\Session::getInstance();
$tenant = $session->getTenant();
$filial = $session->getFilial();

if (!$tenant || !$filial) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = \System\Database::getInstance();
$pdo = $db->getConnection();

try {
    if ($action === 'salvar_whatsapp_admin') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
        
        if (empty($nome) || empty($telefone)) {
            echo json_encode(['success' => false, 'message' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        $db->insert('whatsapp_admins', [
            'tenant_id' => $tenant['id'],
            'filial_id' => $filial['id'],
            'nome' => $nome,
            'telefone' => $telefone,
            'ativo' => true
        ]);
        
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'remover_whatsapp_admin') {
        $id = (int)$_POST['id'];
        $db->delete('whatsapp_admins', 'id = ? AND tenant_id = ?', [$id, $tenant['id']]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'salvar_ai_automations') {
        // Abandono
        $abandono_ativo = isset($_POST['abandono_ativo']) ? 'true' : 'false';
        $abandono_tempo = (int)($_POST['abandono_tempo'] ?? 30);
        $abandono_msg = $_POST['abandono_msg'] ?? '';

        // Saudade
        $saudade_ativo = isset($_POST['saudade_ativo']) ? 'true' : 'false';
        $saudade_tempo = (int)($_POST['saudade_tempo'] ?? 15);
        $saudade_msg = $_POST['saudade_msg'] ?? '';

        // Processa Abandono
        $exists_abandono = $db->fetch("SELECT id FROM ai_automations WHERE tenant_id = ? AND filial_id = ? AND tipo = 'abandono'", [$tenant['id'], $filial['id']]);
        if ($exists_abandono) {
            $db->update('ai_automations', [
                'ativo' => $abandono_ativo === 'true',
                'tempo_espera' => $abandono_tempo,
                'mensagem_template' => $abandono_msg,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$exists_abandono['id']]);
        } else {
            $db->insert('ai_automations', [
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id'],
                'tipo' => 'abandono',
                'ativo' => $abandono_ativo === 'true',
                'tempo_espera' => $abandono_tempo,
                'mensagem_template' => $abandono_msg
            ]);
        }

        // Processa Saudade
        $exists_saudade = $db->fetch("SELECT id FROM ai_automations WHERE tenant_id = ? AND filial_id = ? AND tipo = 'saudade'", [$tenant['id'], $filial['id']]);
        if ($exists_saudade) {
            $db->update('ai_automations', [
                'ativo' => $saudade_ativo === 'true',
                'tempo_espera' => $saudade_tempo,
                'mensagem_template' => $saudade_msg,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$exists_saudade['id']]);
        } else {
            $db->insert('ai_automations', [
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id'],
                'tipo' => 'saudade',
                'ativo' => $saudade_ativo === 'true',
                'tempo_espera' => $saudade_tempo,
                'mensagem_template' => $saudade_msg
            ]);
        }

        echo json_encode(['success' => true]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
