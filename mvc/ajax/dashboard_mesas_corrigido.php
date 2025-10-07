<?php
/**
 * DASHBOARD MESAS CORRIGIDO
 * 
 * Corrige o problema de pedidos desaparecendo das mesas
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Usar valores padrão se não houver sessão
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    // 1. OBTER MESAS
    $mesas = $db->fetchAll("
        SELECT id, numero, status, nome
        FROM mesas 
        WHERE tenant_id = ? AND filial_id = ?
        ORDER BY numero::integer
    ", [$tenantId, $filialId]);
    
    // 2. OBTER PEDIDOS ATIVOS (SEM FILTRO DE TEMPO)
    $pedidosAtivos = $db->fetchAll("
        SELECT 
            p.idpedido,
            p.idmesa,
            p.status,
            p.valor_total,
            p.created_at,
            p.cliente_nome,
            m.numero as mesa_numero,
            m.id as mesa_id
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
            AND m.tenant_id = p.tenant_id 
            AND m.filial_id = p.filial_id
        WHERE p.tenant_id = ? AND p.filial_id = ? 
        AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ORDER BY p.idmesa, p.created_at ASC
    ", [$tenantId, $filialId]);
    
    // 3. AGRUPAR PEDIDOS POR MESA
    $pedidosPorMesa = [];
    foreach ($pedidosAtivos as $pedido) {
        $mesaId = $pedido['idmesa'];
        if (!isset($pedidosPorMesa[$mesaId])) {
            $pedidosPorMesa[$mesaId] = [];
        }
        $pedidosPorMesa[$mesaId][] = $pedido;
    }
    
    // 4. ATUALIZAR STATUS DAS MESAS BASEADO NOS PEDIDOS REAIS
    foreach ($mesas as &$mesa) {
        $temPedidosAtivos = isset($pedidosPorMesa[$mesa['id']]) && count($pedidosPorMesa[$mesa['id']]) > 0;
        $novoStatus = $temPedidosAtivos ? 'ocupada' : 'livre';
        
        // Atualizar status se necessário
        if ($mesa['status'] !== $novoStatus) {
            $db->update(
                'mesas',
                ['status' => $novoStatus, 'updated_at' => 'NOW()'],
                'id = ?',
                [$mesa['id']]
            );
            $mesa['status'] = $novoStatus;
        }
        
        // Adicionar informações dos pedidos
        $mesa['pedidos'] = $pedidosPorMesa[$mesa['id']] ?? [];
        $mesa['total_pedidos'] = count($mesa['pedidos']);
        $mesa['valor_total'] = array_sum(array_column($mesa['pedidos'], 'valor_total'));
    }
    
    // 5. GERAR HTML DAS MESAS
    $html = '';
    foreach ($mesas as $mesa) {
        $statusClass = $mesa['status'] === 'ocupada' ? 'mesa-ocupada' : 'mesa-livre';
        $statusIcon = $mesa['status'] === 'ocupada' ? '🔴' : '🟢';
        $statusText = $mesa['status'] === 'ocupada' ? 'Ocupada' : 'Livre';
        
        $html .= '<div class="mesa-card ' . $statusClass . '" data-mesa-id="' . $mesa['id'] . '">';
        $html .= '<div class="mesa-header">';
        $html .= '<h6>Mesa ' . $mesa['numero'] . '</h6>';
        $html .= '<span class="mesa-status">' . $statusIcon . ' ' . $statusText . '</span>';
        $html .= '</div>';
        
        if ($mesa['total_pedidos'] > 0) {
            $html .= '<div class="mesa-pedidos">';
            $html .= '<p><strong>Pedidos Ativos:</strong> ' . $mesa['total_pedidos'] . '</p>';
            $html .= '<p><strong>Valor Total:</strong> R$ ' . number_format($mesa['valor_total'], 2, ',', '.') . '</p>';
            
            foreach ($mesa['pedidos'] as $pedido) {
                $idade = round((time() - strtotime($pedido['created_at'])) / 3600, 1);
                $idadeClass = $idade > 24 ? 'text-danger' : ($idade > 2 ? 'text-warning' : 'text-success');
                
                $html .= '<div class="pedido-item">';
                $html .= '<div class="pedido-info">';
                $html .= '<span class="pedido-id">#' . $pedido['idpedido'] . '</span>';
                $html .= '<span class="pedido-status badge bg-' . getStatusColor($pedido['status']) . '">' . $pedido['status'] . '</span>';
                $html .= '</div>';
                $html .= '<div class="pedido-details">';
                $html .= '<span class="pedido-valor">R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</span>';
                $html .= '<span class="pedido-idade ' . $idadeClass . '">' . $idade . 'h</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        } else {
            $html .= '<div class="mesa-livre">';
            $html .= '<p>Mesa livre</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    echo json_encode([
        'success' => true,
        'mesas' => $mesas,
        'html' => $html,
        'total_pedidos_ativos' => count($pedidosAtivos),
        'total_mesas_ocupadas' => count(array_filter($mesas, function($m) { return $m['status'] === 'ocupada'; }))
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

function getStatusColor($status) {
    switch($status) {
        case 'Pendente': return 'warning';
        case 'Preparando': return 'info';
        case 'Pronto': return 'success';
        case 'Entregue': return 'primary';
        default: return 'secondary';
    }
}
?>
