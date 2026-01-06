<?php
/**
 * Endpoint para processar lembretes de reservas agendados
 * Deve ser chamado periodicamente via cron job (a cada 5-10 minutos)
 * 
 * Exemplo de cron (executa às 8h da manhã):
 * 0 8 * * * curl -s http://localhost:8080/mvc/ajax/process_reservation_reminders.php > /dev/null 2>&1
 * 
 * Ou para executar a cada 10 minutos (verifica se é 8h):
 * */10 * * * * curl -s http://localhost:8080/mvc/ajax/process_reservation_reminders.php > /dev/null 2>&1
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';

try {
    $db = \System\Database::getInstance();
    
    // Verificar se é 8h da manhã (com margem de 15 minutos antes e depois)
    $horaAtual = (int)date('H');
    $minutoAtual = (int)date('i');
    
    // Só processa entre 7:45 e 8:15 (janela de 30 minutos)
    $deveProcessar = false;
    
    if ($horaAtual === 7 && $minutoAtual >= 45) {
        $deveProcessar = true;
    } elseif ($horaAtual === 8 && $minutoAtual <= 15) {
        $deveProcessar = true;
    }
    
    if (!$deveProcessar) {
        echo json_encode([
            'success' => true,
            'message' => 'Fora do horário de envio de lembretes (8h da manhã - janela: 7:45 às 8:15)',
            'hora_atual' => date('H:i'),
            'reservas_processadas' => 0
        ]);
        exit;
    }
    
    // Buscar reservas confirmadas para hoje que ainda não receberam lembrete
    $hoje = date('Y-m-d');
    
    $reservas = $db->fetchAll(
        "SELECT r.*, f.nome as filial_nome
         FROM reservas r
         LEFT JOIN filiais f ON r.filial_id = f.id
         WHERE r.data_reserva = ?
         AND r.status = 'confirmada'
         AND (r.lembrete_enviado IS NULL OR r.lembrete_enviado = false)
         AND r.celular IS NOT NULL
         AND r.celular != ''
         ORDER BY r.hora_reserva ASC",
        [$hoje]
    );
    
    if (empty($reservas)) {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhuma reserva pendente de lembrete para hoje',
            'reservas_processadas' => 0
        ]);
        exit;
    }
    
    $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
    $processadas = 0;
    $erros = 0;
    $mensagens = [];
    
    foreach ($reservas as $reserva) {
        try {
            // Buscar instância WhatsApp ativa
            $instancia = getWhatsAppInstance($reserva['tenant_id'], $reserva['filial_id']);
            
            if (!$instancia) {
                error_log("RESERVAS_LEMBRETE - Nenhuma instância WhatsApp encontrada para tenant {$reserva['tenant_id']}, filial {$reserva['filial_id']}");
                $erros++;
                $mensagens[] = "Reserva #{$reserva['id']}: Instância WhatsApp não encontrada";
                continue;
            }
            
            // Formatar data e hora
            $dataFormatada = date('d/m/Y', strtotime($reserva['data_reserva']));
            $horaFormatada = date('H:i', strtotime($reserva['hora_reserva']));
            
            // Montar mensagem de lembrete
            $mensagem = "👋 *Bom dia!*\n\n";
            $mensagem .= "Olá {$reserva['nome']},\n\n";
            $mensagem .= "Hoje é o dia da sua reserva! 🎉\n\n";
            $mensagem .= "📅 *Data:* {$dataFormatada}\n";
            $mensagem .= "🕐 *Hora:* {$horaFormatada}\n";
            $mensagem .= "👥 *Convidados:* {$reserva['num_convidados']}\n\n";
            $mensagem .= "Está tudo certo para sua reserva? Confirme se conseguirá comparecer.\n\n";
            $mensagem .= "Aguardamos você! 🍽️";
            
            // Enviar mensagem
            $result = $wuzapiManager->sendMessage(
                $instancia['id'],
                $reserva['celular'],
                $mensagem
            );
            
            if ($result['success']) {
                // Marcar lembrete como enviado
                $db->update('reservas', [
                    'lembrete_enviado' => true,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$reserva['id']]);
                
                $processadas++;
                $mensagens[] = "Reserva #{$reserva['id']}: Lembrete enviado com sucesso para {$reserva['celular']}";
                error_log("RESERVAS_LEMBRETE - Lembrete enviado para reserva #{$reserva['id']} - {$reserva['nome']} ({$reserva['celular']})");
            } else {
                $erros++;
                $mensagens[] = "Reserva #{$reserva['id']}: Erro ao enviar - " . ($result['message'] ?? 'Erro desconhecido');
                error_log("RESERVAS_LEMBRETE - Erro ao enviar lembrete para reserva #{$reserva['id']}: " . ($result['message'] ?? 'Erro desconhecido'));
            }
            
        } catch (\Exception $e) {
            $erros++;
            $mensagens[] = "Reserva #{$reserva['id']}: Exceção - " . $e->getMessage();
            error_log("RESERVAS_LEMBRETE - Exceção ao processar reserva #{$reserva['id']}: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Processamento concluído: {$processadas} lembretes enviados, {$erros} erros",
        'reservas_processadas' => $processadas,
        'erros' => $erros,
        'detalhes' => $mensagens
    ]);
    
} catch (Exception $e) {
    error_log("process_reservation_reminders - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar lembretes: ' . $e->getMessage()
    ]);
}

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

