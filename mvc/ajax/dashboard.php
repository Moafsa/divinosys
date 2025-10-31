<?php
header('Content-Type: application/json');

// Autoloader
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'ver_mesa':
            $mesaId = $_GET['mesa_id'] ?? '';
            
            if (empty($mesaId)) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            // Get mesa info
            $mesa = $db->fetch(
                "SELECT * FROM mesas WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada');
            }
            
            // Get pedidos ativos da mesa (excluindo quitados)
            $pedidos = $db->fetchAll(
                "SELECT * FROM pedido WHERE idmesa = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') AND status_pagamento != 'quitado' ORDER BY data DESC, hora_pedido DESC",
                [$mesaId, $tenantId, $filialId]
            );
            
            $html = '<div class="mesa-info">';
            $html .= '<h6>Mesa ' . htmlspecialchars($mesa['id_mesa']) . '</h6>';
            $html .= '<p><strong>Status:</strong> ' . ($mesa['status'] == '1' ? 'Livre' : 'Ocupada') . '</p>';
            
            if (count($pedidos) > 0) {
                $html .= '<h6>Pedidos Ativos:</h6>';
                $html .= '<div class="list-group">';
                foreach ($pedidos as $pedido) {
                    $html .= '<div class="list-group-item">';
                    $html .= '<div class="d-flex justify-content-between">';
                    $html .= '<span>Pedido #' . htmlspecialchars($pedido['idpedido']) . '</span>';
                    $html .= '<span class="badge bg-primary">R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</span>';
                    $html .= '</div>';
                    $html .= '<small class="text-muted">' . htmlspecialchars($pedido['hora_pedido']) . '</small>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            } else {
                $html .= '<p class="text-muted">Nenhum pedido ativo</p>';
            }
            
            $html .= '</div>';
            
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada');
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>