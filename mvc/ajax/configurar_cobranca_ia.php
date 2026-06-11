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

$cliente_id = $_POST['cliente_id'] ?? null;
$cobranca_automatica = isset($_POST['cobranca_automatica']) ? (bool)$_POST['cobranca_automatica'] : null;
$cobranca_frequencia = $_POST['cobranca_frequencia'] ?? null;

if (!$cliente_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Cliente não informado']);
    exit;
}

$db = \System\Database::getInstance();

try {
    $updates = [];
    $params = [];

    if ($cobranca_automatica !== null) {
        $updates['cobranca_automatica'] = $cobranca_automatica ? 'true' : 'false';
    }

    if ($cobranca_frequencia !== null) {
        $updates['cobranca_frequencia'] = $cobranca_frequencia;
    }

    if (!empty($updates)) {
        $db->update(
            'clientes_fiado', 
            $updates, 
            'id = ? AND tenant_id = ? AND filial_id = ?', 
            [$cliente_id, $tenant['id'], $filial['id']]
        );
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()]);
}
