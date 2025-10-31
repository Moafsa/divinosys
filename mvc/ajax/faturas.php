<?php
/**
 * AJAX Handler para Faturas
 * Processa requisições relacionadas a faturas e pagamentos
 */

session_start();
require_once __DIR__ . '/../controller/FaturaController.php';

try {
    $action = $_GET['action'] ?? '';
    $controller = new FaturaController();
    
    switch ($action) {
        case 'listFaturas':
            $controller->listFaturas();
            break;
            
        case 'getStats':
            $controller->getStats();
            break;
            
        case 'createFatura':
            $controller->createFatura();
            break;
            
        case 'getFaturaDetails':
            $controller->getFaturaDetails();
            break;
            
        case 'processWebhook':
            $controller->processWebhook();
            break;
            
        case 'markAsPaid':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Método não permitido']);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $asaas_payment_id = $data['asaas_payment_id'] ?? '';
            
            if (empty($asaas_payment_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID do pagamento não fornecido']);
                return;
            }
            
            $faturaModel = new Fatura();
            $success = $faturaModel->updateStatus($asaas_payment_id, 'paid', date('Y-m-d H:i:s'));
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Fatura marcada como paga']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao atualizar status da fatura']);
            }
            break;
            
        case 'getFaturaByAsaasId':
            $asaas_payment_id = $_GET['asaas_payment_id'] ?? '';
            
            if (empty($asaas_payment_id)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID do pagamento Asaas não fornecido']);
                return;
            }
            
            $faturaModel = new Fatura();
            $fatura = $faturaModel->getByAsaasPaymentId($asaas_payment_id);
            
            if ($fatura) {
                echo json_encode($fatura);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Fatura não encontrada']);
            }
            break;
            
        case 'getFaturasByStatus':
            $status = $_GET['status'] ?? '';
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            
            if (empty($status)) {
                http_response_code(400);
                echo json_encode(['error' => 'Status não fornecido']);
                return;
            }
            
            $faturaModel = new Fatura();
            $tenant_id = $_SESSION['tenant_id'] ?? null;
            
            if ($tenant_id) {
                $faturas = $faturaModel->getByTenant($tenant_id, $limit, $offset);
                // Filtrar por status
                $faturas = array_filter($faturas, function($fatura) use ($status) {
                    return $fatura['status'] === $status;
                });
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Não autenticado']);
                return;
            }
            
            echo json_encode(array_values($faturas));
            break;
            
        case 'getFaturaHistory':
            $tenant_id = $_SESSION['tenant_id'] ?? null;
            
            if (!$tenant_id) {
                http_response_code(403);
                echo json_encode(['error' => 'Não autenticado']);
                return;
            }
            
            $faturaModel = new Fatura();
            $faturas = $faturaModel->getByTenant($tenant_id, 100, 0);
            
            // Agrupar por mês
            $history = [];
            foreach ($faturas as $fatura) {
                $month = date('Y-m', strtotime($fatura['created_at']));
                if (!isset($history[$month])) {
                    $history[$month] = [
                        'month' => $month,
                        'total_faturas' => 0,
                        'total_valor' => 0,
                        'faturas_pagas' => 0,
                        'faturas_pendentes' => 0
                    ];
                }
                
                $history[$month]['total_faturas']++;
                $history[$month]['total_valor'] += floatval($fatura['valor']);
                
                if ($fatura['status'] === 'paid') {
                    $history[$month]['faturas_pagas']++;
                } else {
                    $history[$month]['faturas_pendentes']++;
                }
            }
            
            echo json_encode(array_values($history));
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Ação não encontrada']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
