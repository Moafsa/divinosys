<?php
/**
 * API endpoint for online menu reservations
 * Handles reservation creation from public online menu
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
        error_log("RESERVAS_ONLINE FATAL ERROR: " . json_encode($error));
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
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
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Clean any output before requiring files
ob_clean();

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/TimeHelper.php';

/**
 * Função para buscar instância WhatsApp ativa
 */
function getWhatsAppInstance($tenantId, $filialId = null) {
    $db = \System\Database::getInstance();
    
    // Primeiro, tentar buscar instância específica da filial (com status ativo)
    $instancia = null;
    if ($filialId !== null) {
        $instancia = $db->fetch(
            "SELECT * FROM whatsapp_instances 
             WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
             AND status IN ('open', 'connected', 'ativo', 'active') 
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId, $filialId]
        );
        
        // Se não encontrou com status ativo, tentar qualquer instância da filial
        if (!$instancia) {
            $instancia = $db->fetch(
                "SELECT * FROM whatsapp_instances 
                 WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId, $filialId]
            );
        }
    }
    
    // Se não encontrou com filial específica, tentar sem filial (instância global do tenant)
    if (!$instancia) {
        $instancia = $db->fetch(
            "SELECT * FROM whatsapp_instances 
             WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = 0) AND ativo = true 
             AND status IN ('open', 'connected', 'ativo', 'active') 
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId]
        );
        
        // Se não encontrou com status ativo, tentar qualquer instância global
        if (!$instancia) {
            $instancia = $db->fetch(
                "SELECT * FROM whatsapp_instances 
                 WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = 0) AND ativo = true 
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantId]
            );
        }
    }
    
    // Se ainda não encontrou, tentar qualquer instância ativa do tenant
    if (!$instancia) {
        $instancia = $db->fetch(
            "SELECT * FROM whatsapp_instances 
             WHERE tenant_id = ? AND ativo = true 
             ORDER BY 
                CASE WHEN status IN ('open', 'connected', 'ativo', 'active') THEN 1 ELSE 2 END,
                created_at DESC 
             LIMIT 1",
            [$tenantId]
        );
    }
    
    return $instancia;
}

/**
 * Função para enviar mensagem de confirmação de reserva
 */
function enviarMensagemConfirmacaoReserva($reserva, $tenantId, $filialId) {
    try {
        require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
        
        $instancia = getWhatsAppInstance($tenantId, $filialId);
        if (!$instancia) {
            error_log("RESERVAS_ONLINE - Nenhuma instância WhatsApp encontrada para enviar mensagem de confirmação");
            return false;
        }
        
        $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
        
        // Format date and time
        $dataFormatada = date('d/m/Y', strtotime($reserva['data_reserva']));
        $horaFormatada = date('H:i', strtotime($reserva['hora_reserva']));
        
        $mensagem = "✅ *Reserva Confirmada!*\n\n";
        $mensagem .= "Olá {$reserva['nome']},\n\n";
        $mensagem .= "Sua reserva foi *confirmada* com sucesso!\n\n";
        $mensagem .= "📅 *Data:* {$dataFormatada}\n";
        $mensagem .= "🕐 *Hora:* {$horaFormatada}\n";
        $mensagem .= "👥 *Convidados:* {$reserva['num_convidados']}\n\n";
        $mensagem .= "Aguardamos você no estabelecimento!\n\n";
        $mensagem .= "Obrigado pela preferência! 🍽️";
        
        $result = $wuzapiManager->sendMessage($instancia['id'], $reserva['celular'], $mensagem);
        
        if ($result['success']) {
            error_log("RESERVAS_ONLINE - Mensagem de confirmação enviada com sucesso para {$reserva['celular']}");
        } else {
            error_log("RESERVAS_ONLINE - Erro ao enviar mensagem de confirmação: " . ($result['message'] ?? 'Erro desconhecido'));
        }
        
        return $result['success'];
    } catch (\Exception $e) {
        error_log("RESERVAS_ONLINE - Exceção ao enviar mensagem de confirmação: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para enviar mensagem de criação de reserva
 */
function enviarMensagemCriacaoReserva($reserva, $tenantId, $filialId) {
    try {
        require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';
        
        $instancia = getWhatsAppInstance($tenantId, $filialId);
        if (!$instancia) {
            error_log("RESERVAS_ONLINE - Nenhuma instância WhatsApp encontrada para enviar mensagem de criação");
            return false;
        }
        
        $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
        
        // Format date and time
        $dataFormatada = date('d/m/Y', strtotime($reserva['data_reserva']));
        $horaFormatada = date('H:i', strtotime($reserva['hora_reserva']));
        
        $mensagem = "📋 *Reserva Recebida!*\n\n";
        $mensagem .= "Olá {$reserva['nome']},\n\n";
        $mensagem .= "Recebemos sua solicitação de reserva!\n\n";
        $mensagem .= "📅 *Data:* {$dataFormatada}\n";
        $mensagem .= "🕐 *Hora:* {$horaFormatada}\n";
        $mensagem .= "👥 *Convidados:* {$reserva['num_convidados']}\n\n";
        $mensagem .= "⏳ *Status:* Pendente de confirmação\n\n";
        $mensagem .= "Em breve entraremos em contato para confirmar sua reserva.\n\n";
        $mensagem .= "Obrigado pela preferência! 🍽️";
        
        $result = $wuzapiManager->sendMessage($instancia['id'], $reserva['celular'], $mensagem);
        
        if ($result['success']) {
            error_log("RESERVAS_ONLINE - Mensagem de criação enviada com sucesso para {$reserva['celular']}");
        } else {
            error_log("RESERVAS_ONLINE - Erro ao enviar mensagem de criação: " . ($result['message'] ?? 'Erro desconhecido'));
        }
        
        return $result['success'];
    } catch (\Exception $e) {
        error_log("RESERVAS_ONLINE - Exceção ao enviar mensagem de criação: " . $e->getMessage());
        return false;
    }
}

try {
    $db = \System\Database::getInstance();
    
    // Handle GET request for listing reservations (for dashboard) or getting single reservation
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? null;
        
        // Handle get_reserva action
        if ($action === 'get_reserva') {
            require_once __DIR__ . '/../../system/Session.php';
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            $reservaId = intval($_GET['reserva_id'] ?? 0);
            
            if (!$reservaId) {
                throw new \Exception('ID da reserva é obrigatório');
            }
            
            // Get reservation
            $reserva = $db->fetch(
                "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
                $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
            );
            
            if (!$reserva) {
                throw new \Exception('Reserva não encontrada');
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'reserva' => $reserva
            ]);
            ob_end_flush();
            exit;
        }
        
        // Handle listing reservations
        $tenantId = $_GET['tenant_id'] ?? null;
        $filialId = $_GET['filial_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        if (!$tenantId) {
            throw new \Exception('Tenant ID é obrigatório');
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
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'reservas' => $reservas
        ]);
        ob_end_flush();
        exit;
    }
    
    // Handle POST request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new \Exception('Dados inválidos');
    }
    
    $action = $data['action'] ?? 'create';
    
    // Handle get_reserva action (for editing)
    if ($action === 'get_reserva') {
        require_once __DIR__ . '/../../system/Session.php';
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        $reservaId = intval($_GET['reserva_id'] ?? $data['reserva_id'] ?? 0);
        
        if (!$reservaId) {
            throw new \Exception('ID da reserva é obrigatório');
        }
        
        // Get reservation
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new \Exception('Reserva não encontrada');
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'reserva' => $reserva
        ]);
        ob_end_flush();
        exit;
    }
    
    // Handle editar action
    if ($action === 'editar') {
        require_once __DIR__ . '/../../system/Session.php';
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        $reservaId = intval($data['reserva_id'] ?? 0);
        $nome = trim($data['nome'] ?? '');
        $celular = trim($data['celular'] ?? '');
        $email = trim($data['email'] ?? '');
        $numConvidados = intval($data['num_convidados'] ?? 1);
        $dataReserva = $data['data_reserva'] ?? null;
        $horaReserva = $data['hora_reserva'] ?? null;
        $instrucoes = trim($data['instrucoes'] ?? '');
        
        if (!$reservaId || !$nome || !$celular || !$dataReserva || !$horaReserva) {
            throw new \Exception('Dados obrigatórios não fornecidos');
        }
        
        // Verify reservation exists
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new \Exception('Reserva não encontrada');
        }
        
        // Clean phone number
        $celularLimpo = preg_replace('/[^0-9]/', '', $celular);
        if (strlen($celularLimpo) > 11 && substr($celularLimpo, 0, 2) == '55') {
            $celularLimpo = substr($celularLimpo, 2);
        }
        
        // Update reservation
        $db->update('reservas', [
            'nome' => $nome,
            'celular' => $celularLimpo,
            'email' => $email ?: null,
            'num_convidados' => $numConvidados,
            'data_reserva' => $dataReserva,
            'hora_reserva' => $horaReserva,
            'instrucoes' => $instrucoes ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$reservaId]);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Reserva atualizada com sucesso!'
        ]);
        ob_end_flush();
        exit;
    }
    
    // Handle update_status action
    if ($action === 'update_status') {
        require_once __DIR__ . '/../../system/Session.php';
        $session = \System\Session::getInstance();
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        $reservaId = intval($data['reserva_id'] ?? 0);
        $status = $data['status'] ?? null;
        
        if (!$reservaId || !$status) {
            throw new \Exception('ID da reserva e status são obrigatórios');
        }
        
        // Validate status
        $statusValidos = ['pendente', 'confirmada', 'cancelada', 'concluida', 'nao_compareceu'];
        if (!in_array($status, $statusValidos)) {
            throw new \Exception('Status inválido');
        }
        
        // Verify reservation exists and belongs to tenant/filial
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new \Exception('Reserva não encontrada');
        }
        
        // Update status
        // Se está confirmando, resetar lembrete_enviado para permitir envio no dia
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status === 'confirmada') {
            $updateData['lembrete_enviado'] = false;
        }
        
        $db->update('reservas', $updateData, 'id = ?', [$reservaId]);
        
        // Send WhatsApp message if status is 'confirmada'
        if ($status === 'confirmada') {
            try {
                enviarMensagemConfirmacaoReserva($reserva, $tenantId, $filialId);
            } catch (\Exception $e) {
                error_log("RESERVAS_ONLINE - Erro ao enviar mensagem de confirmação: " . $e->getMessage());
                // Não falhar a atualização se o envio de mensagem falhar
            }
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Status da reserva atualizado com sucesso!'
        ]);
        ob_end_flush();
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
            throw new \Exception('ID da reserva e mesa são obrigatórios');
        }
        
        // Verify reservation exists
        $reserva = $db->fetch(
            "SELECT * FROM reservas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$reservaId, $tenantId, $filialId] : [$reservaId, $tenantId]
        );
        
        if (!$reserva) {
            throw new \Exception('Reserva não encontrada');
        }
        
        // Verify mesa exists
        $mesa = $db->fetch(
            "SELECT * FROM mesas WHERE id = ? AND tenant_id = ?" . ($filialId ? " AND filial_id = ?" : ""),
            $filialId ? [$mesaId, $tenantId, $filialId] : [$mesaId, $tenantId]
        );
        
        if (!$mesa) {
            throw new \Exception('Mesa não encontrada');
        }
        
        // Update reservation with mesa
        $db->update('reservas', [
            'mesa_id' => $mesaId,
            'status' => 'confirmada', // Auto-confirm when assigning mesa
            'updated_at' => \System\TimeHelper::now('Y-m-d H:i:s', $filialId)
        ], 'id = ?', [$reservaId]);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Mesa atribuída com sucesso!'
        ]);
        ob_end_flush();
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
        throw new \Exception('Dados obrigatórios não fornecidos');
    }
    
    // Validate date is not in the past - use simple DateTime to avoid TimeHelper issues
    try {
        // Combine date and time
        $reservaDateTimeStr = $dataReserva . ' ' . $horaReserva;
        
        // Use standard DateTime (more reliable)
        $reservaDateTime = new \DateTime($reservaDateTimeStr);
        $now = new \DateTime();
        
        // Allow a small buffer (5 minutes) to account for clock differences
        $bufferSeconds = 5 * 60; // 5 minutes
        $nowTimestamp = $now->getTimestamp() - $bufferSeconds;
        $reservaTimestamp = $reservaDateTime->getTimestamp();
        
        if ($reservaTimestamp < $nowTimestamp) {
            $dataFormatada = $reservaDateTime->format('d/m/Y H:i');
            $agoraFormatada = $now->format('d/m/Y H:i');
            throw new \Exception("Não é possível fazer reserva para data/hora no passado. Data selecionada: {$dataFormatada}, Agora: {$agoraFormatada}");
        }
    } catch (\Exception $e) {
        // If it's our custom exception, re-throw it
        if (strpos($e->getMessage(), 'Não é possível fazer reserva') !== false) {
            throw $e;
        }
        // Otherwise, it's a date parsing error
        error_log("RESERVAS_ONLINE - Erro ao validar data/hora: " . $e->getMessage());
        error_log("RESERVAS_ONLINE - Data recebida: $dataReserva, Hora recebida: $horaReserva");
        throw new \Exception('Data ou hora inválida. Verifique os valores informados.');
    }
    
    // Verify filial exists and online menu is active
    $filial = $db->fetch(
        "SELECT f.* FROM filiais f
         WHERE f.id = ? AND f.tenant_id = ? AND f.cardapio_online_ativo = true AND f.status = 'ativo'",
        [$filialId, $tenantId]
    );
    
    if (!$filial) {
        throw new \Exception('Cardápio online não disponível para esta filial');
    }
    
    // Clean phone number
    $celularLimpo = preg_replace('/[^0-9]/', '', $celular);
    
    // Validate phone number is not empty after cleaning
    if (empty($celularLimpo)) {
        throw new \Exception('Número de celular inválido');
    }
    
    // Generate phone variations for search
    $telefoneVariacoes = [];
    $telefoneVariacoes[] = $celularLimpo; // Original cleaned
    
    // Remove country code (55) if present
    $celularSemPais = $celularLimpo;
    if (strlen($celularLimpo) > 11 && substr($celularLimpo, 0, 2) == '55') {
        $celularSemPais = substr($celularLimpo, 2);
        if (!in_array($celularSemPais, $telefoneVariacoes)) {
            $telefoneVariacoes[] = $celularSemPais;
        }
    }
    
    // Add with country code if not present and phone is valid length
    if (strlen($celularLimpo) <= 11 && substr($celularLimpo, 0, 2) != '55' && strlen($celularLimpo) >= 10) {
        $telefoneComPais = '55' . $celularLimpo;
        if (!in_array($telefoneComPais, $telefoneVariacoes)) {
            $telefoneVariacoes[] = $telefoneComPais;
        }
    }
    
    // Find or create client by phone number
    $clienteId = null;
    try {
        error_log("RESERVAS_ONLINE - Buscando cliente com telefone: $celular (limpo: $celularLimpo)");
        error_log("RESERVAS_ONLINE - Variações de telefone para busca: " . implode(', ', $telefoneVariacoes));
        
        // Build query with multiple phone variations
        $placeholders = implode(',', array_fill(0, count($telefoneVariacoes), '?'));
        $params = array_merge($telefoneVariacoes, $telefoneVariacoes); // For exact and LIKE searches
        
        // Try multiple search patterns to handle different phone formats
        $clienteExistente = $db->fetch(
            "SELECT id, nome, email, telefone FROM usuarios_globais 
             WHERE (
                 telefone IN ($placeholders)
                 OR telefone LIKE ?
                 OR telefone LIKE ?
                 OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') IN ($placeholders)
                 OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
             )
             AND (tipo_usuario = 'cliente' OR tipo_usuario IS NULL OR tipo_usuario = '')
             AND ativo = true 
             LIMIT 1",
            array_merge(
                $telefoneVariacoes, // telefone IN
                ['%' . $celularLimpo . '%', $celularLimpo . '%'], // telefone LIKE
                $telefoneVariacoes, // REPLACE telefone IN
                ['%' . $celularLimpo . '%'] // REPLACE telefone LIKE
            )
        );
        
        if ($clienteExistente) {
            $clienteId = (int)$clienteExistente['id'];
            error_log("RESERVAS_ONLINE - Cliente encontrado: ID=$clienteId, Nome={$clienteExistente['nome']}, Telefone no BD={$clienteExistente['telefone']}");
            
            // Update client data if provided (nome, email)
            $updateData = [];
            if (!empty($nome) && $nome !== $clienteExistente['nome']) {
                $updateData['nome'] = $nome;
            }
            if (!empty($email) && $email !== $clienteExistente['email']) {
                $updateData['email'] = $email;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $db->update('usuarios_globais', $updateData, 'id = ?', [$clienteId]);
                error_log("RESERVAS_ONLINE - Dados do cliente atualizados");
            }
        } else {
            // Create new client
            // Use phone without country code for storage (standard format)
            $telefoneParaSalvar = $celularSemPais;
            error_log("RESERVAS_ONLINE - Cliente não encontrado, criando novo cliente...");
            error_log("RESERVAS_ONLINE - Telefone para salvar: $telefoneParaSalvar");
            
            $clienteId = $db->insert('usuarios_globais', [
                'nome' => $nome,
                'telefone' => $telefoneParaSalvar,
                'email' => $email ?: null,
                'tipo_usuario' => 'cliente',
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($clienteId) {
                error_log("RESERVAS_ONLINE - Novo cliente criado: ID=$clienteId, Nome=$nome, Telefone=$telefoneParaSalvar");
            } else {
                error_log("RESERVAS_ONLINE - Erro ao criar cliente, continuando sem vínculo");
            }
        }
    } catch (\Exception $e) {
        // Log error but don't fail the reservation
        error_log("RESERVAS_ONLINE - Erro ao buscar/criar cliente: " . $e->getMessage());
        error_log("RESERVAS_ONLINE - Stack trace: " . $e->getTraceAsString());
        $clienteId = null;
    }
    
    // Verify table exists (skip auto-creation to avoid issues)
    try {
        $tableExists = $db->fetch(
            "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'reservas'
            ) as exists"
        );
        
        $tableExistsBool = $tableExists && ($tableExists['exists'] === true || $tableExists['exists'] === 't' || $tableExists['exists'] === 1);
        
        if (!$tableExistsBool) {
            error_log("RESERVAS_ONLINE - Tabela 'reservas' não existe no banco de dados");
            throw new \Exception('Tabela de reservas não encontrada. Execute a migration: php run_reservas_migration.php');
        }
    } catch (\Exception $e) {
        // If it's our custom exception, re-throw it
        if (strpos($e->getMessage(), 'Tabela de reservas') !== false) {
            throw $e;
        }
        error_log("RESERVAS_ONLINE - Erro ao verificar tabela: " . $e->getMessage());
        throw new \Exception('Erro ao acessar tabela de reservas: ' . $e->getMessage());
    }
    
    // Create reservation using RETURNING for PostgreSQL
    try {
        // Use simple date() instead of TimeHelper to avoid potential issues
        $createdAt = date('Y-m-d H:i:s');
        $updatedAt = date('Y-m-d H:i:s');
        
        // Log data being inserted for debugging
        error_log("RESERVAS_ONLINE - Tentando inserir reserva: tenant_id=$tenantId, filial_id=$filialId, nome=$nome, data=$dataReserva, hora=$horaReserva");
        
        // Build INSERT query with optional cliente_id
        $columns = ['tenant_id', 'filial_id', 'num_convidados', 'data_reserva', 'hora_reserva', 'nome', 'email', 'celular', 'instrucoes', 'status', 'created_at', 'updated_at'];
        $values = [$tenantId, $filialId, $numConvidados, $dataReserva, $horaReserva, $nome, $email ?: null, $celularLimpo, $instrucoes ?: null, 'pendente', $createdAt, $updatedAt];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
        
        // Add cliente_id if available
        if ($clienteId) {
            $columns[] = 'cliente_id';
            $values[] = $clienteId;
            $placeholders[] = '?';
        }
        
        $columnsStr = implode(', ', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        
        $stmt = $db->query(
            "INSERT INTO reservas ({$columnsStr}) 
             VALUES ({$placeholdersStr}) 
             RETURNING id",
            $values
        );
        
        // Fetch the returned ID
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $reservaId = $result && isset($result['id']) ? (int)$result['id'] : null;
        
        if (!$reservaId || $reservaId <= 0) {
            error_log("RESERVAS_ONLINE - INSERT executado mas ID não retornado ou inválido. Result: " . json_encode($result));
            throw new \Exception('Erro ao criar reserva no banco de dados: ID não retornado');
        }
        
        error_log("RESERVAS_ONLINE - Reserva criada com sucesso: ID=$reservaId");
        
        // Send WhatsApp message after successful creation
        try {
            $reservaData = [
                'id' => $reservaId,
                'nome' => $nome,
                'celular' => $celularLimpo,
                'email' => $email,
                'data_reserva' => $dataReserva,
                'hora_reserva' => $horaReserva,
                'num_convidados' => $numConvidados,
                'status' => 'pendente'
            ];
            enviarMensagemCriacaoReserva($reservaData, $tenantId, $filialId);
        } catch (\Exception $e) {
            error_log("RESERVAS_ONLINE - Erro ao enviar mensagem de criação: " . $e->getMessage());
            // Não falhar a criação se o envio de mensagem falhar
        }
    } catch (\PDOException $e) {
        $errorInfo = $e->errorInfo ?? [];
        error_log("RESERVAS_ONLINE - PDO Exception: " . $e->getMessage());
        error_log("RESERVAS_ONLINE - SQL State: " . $e->getCode());
        error_log("RESERVAS_ONLINE - Error Info: " . json_encode($errorInfo));
        error_log("RESERVAS_ONLINE - SQL Query: INSERT INTO reservas...");
        error_log("RESERVAS_ONLINE - Params: " . json_encode([
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'num_convidados' => $numConvidados,
            'data_reserva' => $dataReserva,
            'hora_reserva' => $horaReserva,
            'nome' => $nome,
            'email' => $email,
            'celular' => $celularLimpo,
            'instrucoes' => $instrucoes
        ]));
        throw new \Exception('Erro ao criar reserva no banco de dados: ' . $e->getMessage());
    } catch (\Exception $e) {
        error_log("RESERVAS_ONLINE - Exception ao inserir: " . $e->getMessage());
        error_log("RESERVAS_ONLINE - Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'reserva_id' => $reservaId,
        'message' => 'Reserva criada com sucesso! Aguarde confirmação do estabelecimento.'
    ]);
    ob_end_flush();
    exit;
    
} catch (\PDOException $e) {
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $errorInfo = $e->errorInfo ?? [];
    
    error_log("RESERVAS_ONLINE - PDO Exception: " . $errorMsg);
    error_log("RESERVAS_ONLINE - SQL State: " . $errorCode);
    error_log("RESERVAS_ONLINE - Error Info: " . json_encode($errorInfo));
    error_log("RESERVAS_ONLINE - Stack trace: " . $e->getTraceAsString());
    
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    // Provide user-friendly error message
    $userMessage = 'Erro ao processar reserva no banco de dados';
    if (strpos($errorMsg, 'relation') !== false && strpos($errorMsg, 'does not exist') !== false) {
        $userMessage = 'Tabela de reservas não encontrada. Contate o administrador do sistema.';
    } elseif (strpos($errorMsg, 'foreign key') !== false) {
        $userMessage = 'Erro de validação: dados inválidos fornecidos.';
    } elseif (strpos($errorMsg, 'duplicate key') !== false) {
        $userMessage = 'Esta reserva já existe no sistema.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'error' => $errorMsg
    ]);
    exit;
} catch (\Exception $e) {
    $errorMsg = $e->getMessage();
    error_log("RESERVAS_ONLINE - Exception: " . $errorMsg);
    error_log("RESERVAS_ONLINE - Stack trace: " . $e->getTraceAsString());
    
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        // Use 400 for validation errors, 500 for server errors
        $httpCode = (strpos($errorMsg, 'obrigatório') !== false || 
                     strpos($errorMsg, 'inválid') !== false ||
                     strpos($errorMsg, 'não é possível') !== false) ? 400 : 500;
        http_response_code($httpCode);
    }
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'error' => $errorMsg
    ]);
    exit;
}

