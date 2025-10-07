<?php
session_start();
require_once '../../system/Database.php';
require_once '../../system/Utils.php';

// Configurar headers para JSON
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
$acao = $_POST['acao'] ?? '';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    switch ($acao) {
        case 'status_geral':
            statusGeral($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_mesas':
            listarMesas($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_pedidos_antigos':
            listarPedidosAntigos($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_pedidos_ativos':
            listarPedidosAtivos($pdo, $tenantId, $filialId);
            break;
            
        case 'limpar_pedidos_antigos':
            limparPedidosAntigos($pdo, $tenantId, $filialId);
            break;
            
        case 'sincronizar_mesas':
            sincronizarMesas($pdo, $tenantId, $filialId);
            break;
            
        case 'verificar_integridade':
            verificarIntegridade($pdo, $tenantId, $filialId);
            break;
            
        case 'atualizar_status':
            atualizarStatus($pdo, $tenantId, $filialId);
            break;
            
        case 'finalizar_pedido':
            finalizarPedido($pdo, $tenantId, $filialId);
            break;
            
        case 'liberar_mesa':
            liberarMesa($pdo, $tenantId, $filialId);
            break;
            
        case 'forcar_finalizacao':
            forcarFinalizacao($pdo, $tenantId, $filialId);
            break;
            
        case 'gerar_relatorio_mesas':
            gerarRelatorioMesas($pdo, $tenantId, $filialId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em gestao_mesas_pedidos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function statusGeral($pdo, $tenantId, $filialId) {
    // Mesas ocupadas
    $mesasOcupadas = $pdo->query("
        SELECT COUNT(*) as total
        FROM mesas 
        WHERE tenant_id = ? AND filial_id = ? AND status = 'ocupada'
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pedidos antigos (24h+)
    $pedidosAntigos = $pdo->query("
        SELECT COUNT(*) as total
        FROM pedido 
        WHERE tenant_id = ? AND filial_id = ? 
        AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND created_at <= NOW() - INTERVAL '24 hours'
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pedidos ativos
    $pedidosAtivos = $pdo->query("
        SELECT COUNT(*) as total
        FROM pedido 
        WHERE tenant_id = ? AND filial_id = ? 
        AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valor total dos pedidos ativos
    $valorTotal = $pdo->query("
        SELECT COALESCE(SUM(valor_total), 0) as total
        FROM pedido 
        WHERE tenant_id = ? AND filial_id = ? 
        AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
    ")->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'status' => [
            'mesas_ocupadas' => intval($mesasOcupadas),
            'pedidos_antigos' => intval($pedidosAntigos),
            'pedidos_ativos' => intval($pedidosAtivos),
            'valor_total' => floatval($valorTotal)
        ]
    ]);
}

function listarMesas($pdo, $tenantId, $filialId) {
    $sql = "
        SELECT 
            m.id,
            m.numero,
            m.status,
            COUNT(p.idpedido) as pedidos_ativos,
            MAX(p.created_at) as ultima_atividade,
            COALESCE(SUM(p.valor_total), 0) as valor_total
        FROM mesas m
        LEFT JOIN pedido p ON m.id::varchar = p.idmesa 
            AND p.tenant_id = m.tenant_id 
            AND p.filial_id = m.filial_id
            AND p.status NOT IN ('Finalizado', 'Cancelado')
        WHERE m.tenant_id = ? AND m.filial_id = ?
        GROUP BY m.id, m.numero, m.status
        ORDER BY m.numero
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'mesas' => $mesas]);
}

function listarPedidosAntigos($pdo, $tenantId, $filialId) {
    $sql = "
        SELECT 
            p.idpedido,
            p.idmesa,
            p.status,
            p.valor_total,
            p.created_at,
            m.numero as mesa_numero,
            EXTRACT(EPOCH FROM (NOW() - p.created_at))/3600 as idade_horas
        FROM pedido p
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.tenant_id = ? AND p.filial_id = ?
        AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND p.created_at <= NOW() - INTERVAL '24 hours'
        ORDER BY p.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'pedidos' => $pedidos]);
}

function listarPedidosAtivos($pdo, $tenantId, $filialId) {
    $sql = "
        SELECT 
            p.idpedido,
            p.idmesa,
            p.status,
            p.valor_total,
            p.cliente_nome,
            p.created_at,
            m.numero as mesa_numero
        FROM pedido p
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.tenant_id = ? AND p.filial_id = ?
        AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'pedidos' => $pedidos]);
}

function limparPedidosAntigos($pdo, $tenantId, $filialId) {
    // CORREÇÃO: NÃO finalizar pedidos automaticamente
    // Apenas identificar pedidos antigos para atenção do caixa
    
    try {
        // 1. Identificar pedidos antigos (apenas para relatório)
        $pedidosAntigos = $pdo->query("
            SELECT p.*, m.numero as mesa_numero
            FROM pedido p 
            LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
            WHERE p.tenant_id = ? AND p.filial_id = ?
            AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
            AND p.created_at <= NOW() - INTERVAL '24 hours'
            ORDER BY p.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $totalPedidos = count($pedidosAntigos);
        $valorTotal = array_sum(array_column($pedidosAntigos, 'valor_total'));
        
        // 2. Apenas atualizar status das mesas baseado nos pedidos reais
        atualizarStatusMesas($pdo, $tenantId, $filialId);
        
        echo json_encode([
            'success' => true, 
            'message' => "Identificados {$totalPedidos} pedido(s) antigo(s) que precisam de atenção do caixa. Valor total: R$ " . number_format($valorTotal, 2, ',', '.'),
            'pedidos_antigos' => $pedidosAntigos,
            'total_pedidos' => $totalPedidos,
            'valor_total' => $valorTotal
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sincronizarMesas($pdo, $tenantId, $filialId) {
    atualizarStatusMesas($pdo, $tenantId, $filialId);
    echo json_encode(['success' => true, 'message' => 'Mesas sincronizadas com sucesso']);
}

function verificarIntegridade($pdo, $tenantId, $filialId) {
    $inconsistencias = [];
    
    // Verificar mesas com status inconsistente
    $sql = "
        SELECT m.numero, m.status, COUNT(p.idpedido) as pedidos_ativos
        FROM mesas m
        LEFT JOIN pedido p ON m.id::varchar = p.idmesa 
            AND p.tenant_id = m.tenant_id 
            AND p.filial_id = m.filial_id
            AND p.status NOT IN ('Finalizado', 'Cancelado')
        WHERE m.tenant_id = ? AND m.filial_id = ?
        GROUP BY m.id, m.numero, m.status
        HAVING (m.status = 'ocupada' AND COUNT(p.idpedido) = 0) 
            OR (m.status = 'livre' AND COUNT(p.idpedido) > 0)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resultados as $resultado) {
        if ($resultado['status'] === 'ocupada' && $resultado['pedidos_ativos'] == 0) {
            $inconsistencias[] = [
                'numero' => $resultado['numero'],
                'descricao' => "Mesa marcada como ocupada mas sem pedidos ativos"
            ];
        } elseif ($resultado['status'] === 'livre' && $resultado['pedidos_ativos'] > 0) {
            $inconsistencias[] = [
                'numero' => $resultado['numero'],
                'descricao' => "Mesa marcada como livre mas com {$resultado['pedidos_ativos']} pedido(s) ativo(s)"
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'inconsistencias' => $inconsistencias
    ]);
}

function atualizarStatus($pdo, $tenantId, $filialId) {
    atualizarStatusMesas($pdo, $tenantId, $filialId);
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
}

function atualizarStatusMesas($pdo, $tenantId, $filialId) {
    // Obter todas as mesas
    $mesas = $pdo->query("
        SELECT id, numero, status 
        FROM mesas 
        WHERE tenant_id = ? AND filial_id = ?
        ORDER BY numero
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mesas as $mesa) {
        // Verificar pedidos ativos para esta mesa
        $pedidosAtivos = $pdo->query("
            SELECT COUNT(*) as total
            FROM pedido 
            WHERE idmesa::varchar = ? 
            AND tenant_id = ? AND filial_id = ?
            AND status NOT IN ('Finalizado', 'Cancelado')
        ")->fetch(PDO::FETCH_ASSOC)['total'];
        
        $novoStatus = $pedidosAtivos > 0 ? 'ocupada' : 'livre';
        
        // Atualizar se necessário
        if ($mesa['status'] !== $novoStatus) {
            $stmt = $pdo->prepare("
                UPDATE mesas 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$novoStatus, $mesa['id']]);
        }
    }
}

function finalizarPedido($pdo, $tenantId, $filialId) {
    $pedidoId = $_POST['pedido_id'] ?? '';
    
    if (empty($pedidoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do pedido não fornecido']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Obter dados do pedido
        $pedido = $pdo->query("
            SELECT * FROM pedido 
            WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?
        ")->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            throw new Exception('Pedido não encontrado');
        }
        
        // Registrar movimentação financeira
        if ($pedido['valor_total'] > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes_financeiras (
                    tenant_id, filial_id, tipo, categoria_id, descricao,
                    valor, data_movimentacao, referencia_id, created_at
                ) VALUES (?, ?, 'entrada', 1, ?, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([
                $tenantId, $filialId,
                "Pedido #{$pedidoId} finalizado manualmente",
                $pedido['valor_total'], $pedidoId
            ]);
        }
        
        // Finalizar pedido
        $stmt = $pdo->prepare("
            UPDATE pedido 
            SET status = 'Finalizado', updated_at = NOW()
            WHERE idpedido = ?
        ");
        $stmt->execute([$pedidoId]);
        
        // Atualizar status da mesa
        atualizarStatusMesas($pdo, $tenantId, $filialId);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pedido finalizado com sucesso']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function liberarMesa($pdo, $tenantId, $filialId) {
    $mesaId = $_POST['mesa_id'] ?? '';
    
    if (empty($mesaId)) {
        echo json_encode(['success' => false, 'message' => 'ID da mesa não fornecido']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Finalizar todos os pedidos ativos da mesa
        $stmt = $pdo->prepare("
            UPDATE pedido 
            SET status = 'Finalizado', 
                observacoes = 'Mesa liberada manualmente',
                updated_at = NOW()
            WHERE idmesa::varchar = ? 
            AND tenant_id = ? AND filial_id = ?
            AND status NOT IN ('Finalizado', 'Cancelado')
        ");
        $stmt->execute([$mesaId, $tenantId, $filialId]);
        $pedidosFinalizados = $stmt->rowCount();
        
        // Liberar mesa
        $stmt = $pdo->prepare("
            UPDATE mesas 
            SET status = 'livre', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$mesaId]);
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Mesa liberada com sucesso. {$pedidosFinalizados} pedido(s) finalizado(s)."
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function forcarFinalizacao($pdo, $tenantId, $filialId) {
    $pedidoId = $_POST['pedido_id'] ?? '';
    
    if (empty($pedidoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do pedido não fornecido']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Obter dados do pedido
        $pedido = $pdo->query("
            SELECT * FROM pedido 
            WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?
        ")->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            throw new Exception('Pedido não encontrado');
        }
        
        // Registrar movimentação financeira
        if ($pedido['valor_total'] > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes_financeiras (
                    tenant_id, filial_id, tipo, categoria_id, descricao,
                    valor, data_movimentacao, referencia_id, created_at
                ) VALUES (?, ?, 'entrada', 1, ?, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([
                $tenantId, $filialId,
                "Pedido #{$pedidoId} finalizado forçadamente (antigo)",
                $pedido['valor_total'], $pedidoId
            ]);
        }
        
        // Finalizar pedido
        $stmt = $pdo->prepare("
            UPDATE pedido 
            SET status = 'Finalizado', 
                observacoes = 'Finalizado forçadamente por ser muito antigo',
                updated_at = NOW()
            WHERE idpedido = ?
        ");
        $stmt->execute([$pedidoId]);
        
        // Atualizar status da mesa
        atualizarStatusMesas($pdo, $tenantId, $filialId);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pedido antigo finalizado com sucesso']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function gerarRelatorioMesas($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    
    $whereConditions = ["p.tenant_id = ?", "p.filial_id = ?"];
    $params = [$tenantId, $filialId];
    
    if (!empty($dataInicio)) {
        $whereConditions[] = "DATE(p.created_at) >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $whereConditions[] = "DATE(p.created_at) <= ?";
        $params[] = $dataFim;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            m.numero as mesa_numero,
            COUNT(p.idpedido) as total_pedidos,
            COALESCE(SUM(p.valor_total), 0) as valor_total,
            COUNT(CASE WHEN p.status = 'Finalizado' THEN 1 END) as pedidos_finalizados,
            COUNT(CASE WHEN p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue') THEN 1 END) as pedidos_ativos,
            AVG(EXTRACT(EPOCH FROM (p.updated_at - p.created_at))/60) as tempo_medio_minutos
        FROM mesas m
        LEFT JOIN pedido p ON m.id::varchar = p.idmesa AND $whereClause
        WHERE m.tenant_id = ? AND m.filial_id = ?
        GROUP BY m.id, m.numero
        ORDER BY m.numero
    ";
    
    $params[] = $tenantId;
    $params[] = $filialId;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $relatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioMesas($relatorio);
    echo json_encode(['success' => true, 'html' => $html]);
}

function gerarHtmlRelatorioMesas($relatorio) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Mesa</th>
                        <th>Total Pedidos</th>
                        <th>Valor Total</th>
                        <th>Pedidos Finalizados</th>
                        <th>Pedidos Ativos</th>
                        <th>Tempo Médio (min)</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($relatorio as $mesa) {
        $html .= "
            <tr>
                <td>{$mesa['mesa_numero']}</td>
                <td>{$mesa['total_pedidos']}</td>
                <td>R$ " . number_format($mesa['valor_total'], 2, ',', '.') . "</td>
                <td>{$mesa['pedidos_finalizados']}</td>
                <td>{$mesa['pedidos_ativos']}</td>
                <td>" . number_format($mesa['tempo_medio_minutos'], 1) . "</td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}
?>
