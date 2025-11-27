<?php
/**
 * Endpoint para processar lembretes de pagamento agendados
 * Deve ser chamado periodicamente via cron job (a cada 1-2 minutos)
 * 
 * Exemplo de cron:
 * */2 * * * * curl -s http://localhost:8080/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/WhatsApp/PaymentNotificationService.php';

try {
    $paymentNotification = new \System\WhatsApp\PaymentNotificationService();
    $result = $paymentNotification->processScheduledReminders();
    
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("process_payment_reminders - Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar lembretes: ' . $e->getMessage()
    ]);
}

