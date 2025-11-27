<?php
/**
 * API endpoint for online menu reservations
 * Handles reservation creation from public online menu
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/TimeHelper.php';

try {
    $db = \System\Database::getInstance();
    
    // Handle GET request for listing reservations (for dashboard)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $tenantId = $_GET['tenant_id'] ?? null;
        $filialId = $_GET['filial_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        if (!$tenantId) {
            throw new Exception('Tenant ID é obrigatório');
        }
        
        $query = "
            SELECT r.*, 
                   m.numero as mesa_numero,
                   m.id_mesa,
                   f.nome as filial_nome
            FROM reservas r
            LEFT JOIN mesas m ON r.mesa_id = m.id
            LEFT JOIN filiais f ON r.filial_id = f.id
            WHERE r.tenant_id = ?
        ";
        
        $params = [$tenantId];
        
        if ($filialId) {
            $query .= " AND r.filial_id = ?";
            $params[] = $filialId;
        }
        
        if ($status) {
            $query .= " AND r.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY r.data_reserva DESC, r.hora_reserva DESC LIMIT 50";
        
        $reservas = $db->fetchAll($query, $params);
        
        echo json_encode([
            'success' => true,
            'reservas' => $reservas
        ]);
        exit;
    }
    
    // Handle POST request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    $action = $data['action'] ?? 'create';
    
    // Handle update_status action
    if ($action === 'update_status') {
        require_once __DIR__ . '/../../system/Session.php';
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        $reservaId = intval($data['reserva_id'] ?? 0);
        $status = $data['status'] ?? null;
        
        if (!$reservaId || !$status) {
            throw new Exception('ID da reserva e status são obrigatórios');
        }
        
        // Validate status
        $statusValidos = ['pendente', 'confirmada', 'cancelada', 'concluida', 'nao_compareceu'];
        if (!in_array($status, $statusValidos)) {
            throw new Exception('Status inválido');
        }
        
        // Verify reservation exists and belongs to tenant/filial
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new Exception('Reserva não encontrada');
        }
        
        // Update status
        $db->update('reservas', [
            'status' => $status,
            'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
        ], 'id = ?', [$reservaId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Status da reserva atualizado com sucesso!'
        ]);
        exit;
    }
    
    // Handle atribuir_mesa action
    if ($action === 'atribuir_mesa') {
        require_once __DIR__ . '/../../system/Session.php';
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        $reservaId = intval($data['reserva_id'] ?? 0);
        $mesaId = intval($data['mesa_id'] ?? 0);
        
        if (!$reservaId || !$mesaId) {
            throw new Exception('ID da reserva e mesa são obrigatórios');
        }
        
        // Verify reservation exists
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new Exception('Reserva não encontrada');
        }
        
        // Verify mesa exists
        $mesa = $db->fetch(
            "SELECT * FROM mesas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$mesaId, $tenantId, $filialId] : [$mesaId, $tenantId]
        );
        
        if (!$mesa) {
            throw new Exception('Mesa não encontrada');
        }
        
        // Update reservation with mesa
        $db->update('reservas', [
            'mesa_id' => $mesaId,
            'status' => 'confirmada', // Auto-confirm when assigning mesa
            'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
        ], 'id = ?', [$reservaId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Mesa atribuída com sucesso!'
        ]);
        exit;
    }
    
    // Handle create reservation (default action)
    // Validate required fields
    $filialId = $data['filial_id'] ?? null;
    $tenantId = $data['tenant_id'] ?? null;
    $numConvidados = intval($data['num_convidados'] ?? 1);
    $dataReserva = $data['data_reserva'] ?? null;
    $horaReserva = $data['hora_reserva'] ?? null;
    $nome = trim($data['nome'] ?? '');
    $celular = trim($data['celular'] ?? '');
    $email = trim($data['email'] ?? '');
    $instrucoes = trim($data['instrucoes'] ?? '');
    
    if (!$filialId || !$tenantId || !$dataReserva || !$horaReserva || !$nome || !$celular) {
        throw new Exception('Dados obrigatórios não fornecidos');
    }
    
    // Validate date is not in the past
    $reservaDateTime = new DateTime($dataReserva . ' ' . $horaReserva);
    $now = new DateTime();
    if ($reservaDateTime < $now) {
        throw new Exception('Não é possível fazer reserva para data/hora no passado');
    }
    
    // Verify filial exists and online menu is active
    $filial = $db->fetch(
        "SELECT f.* FROM filiais f
         WHERE f.id = ? AND f.tenant_id = ? AND f.cardapio_online_ativo = true AND f.status = 'ativo'",
        [$filialId, $tenantId]
    );
    
    if (!$filial) {
        throw new Exception('Cardápio online não disponível para esta filial');
    }
    
    // Clean phone number
    $celularLimpo = preg_replace('/[^0-9]/', '', $celular);
    
    // Create reservation
    $reservaId = $db->insert('reservas', [
        'tenant_id' => $tenantId,
        'filial_id' => $filialId,
        'num_convidados' => $numConvidados,
        'data_reserva' => $dataReserva,
        'hora_reserva' => $horaReserva,
        'nome' => $nome,
        'email' => $email ?: null,
        'celular' => $celularLimpo,
        'instrucoes' => $instrucoes ?: null,
        'status' => 'pendente',
        'created_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId),
        'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
    ]);
    
    if (!$reservaId) {
        throw new Exception('Erro ao criar reserva no banco de dados');
    }
    
    echo json_encode([
        'success' => true,
        'reserva_id' => $reservaId,
        'message' => 'Reserva criada com sucesso! Aguarde confirmação do estabelecimento.'
    ]);
    
} catch (Exception $e) {
    error_log("RESERVAS_ONLINE - Exception: " . $e->getMessage());
    error_log("RESERVAS_ONLINE - Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
    exit;
}

