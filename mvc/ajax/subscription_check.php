<?php
/**
 * AJAX Handler para verificação de status de assinatura
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/Middleware/SubscriptionCheck.php';

use System\Middleware\SubscriptionCheck;

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'check_can_create':
            // Verificar se pode realizar ação crítica (criar pedido, produto, etc)
            $can = SubscriptionCheck::canPerformCriticalAction();
            $status = SubscriptionCheck::checkSubscriptionStatus();
            
            if (!$can) {
                echo json_encode([
                    'success' => false,
                    'blocked' => true,
                    'message' => $status['message'],
                    'type' => $status['type'],
                    'details' => $status
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'blocked' => false,
                    'message' => 'Permitido',
                    'details' => $status
                ]);
            }
            break;
            
        case 'get_status':
            // Obter status completo da assinatura
            $status = SubscriptionCheck::checkSubscriptionStatus();
            echo json_encode($status);
            break;
            
        case 'get_alert':
            // Obter mensagem de alerta (se houver)
            $alert = SubscriptionCheck::getAlertMessage();
            echo json_encode($alert ?? ['type' => 'success', 'message' => 'OK']);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

