<?php
/**
 * SCRIPT SIMPLES PARA SINCRONIZAR STATUS DAS MESAS
 */

header('Content-Type: application/json');

// Configuração direta do banco
$host = 'postgres';
$dbname = 'divino_lanches';
$username = 'postgres';
$password = 'postgres';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. OBTER TODAS AS MESAS
    $stmt = $pdo->query("SELECT id, id_mesa, numero, status FROM mesas ORDER BY numero::integer");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $mesasCorrigidas = 0;
    $mesasOcupadas = 0;
    $mesasLivres = 0;
    $resultado = [];
    
    // 2. VERIFICAR CADA MESA
    foreach ($mesas as $mesa) {
        // Verificar pedidos ativos para esta mesa
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM pedido 
            WHERE idmesa::varchar = ? 
            AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ");
        $stmt->execute([$mesa['id_mesa']]);
        $pedidosAtivos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $temPedidosAtivos = $pedidosAtivos['total'] > 0;
        $novoStatus = $temPedidosAtivos ? 'ocupada' : 'livre';
        
        // Atualizar status se necessário
        if ($mesa['status'] !== $novoStatus) {
            $stmt = $pdo->prepare("
                UPDATE mesas 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$novoStatus, $mesa['id']]);
            
            $mesasCorrigidas++;
            $resultado[] = "Mesa " . $mesa['numero'] . ": " . $mesa['status'] . " → " . $novoStatus;
        }
        
        if ($novoStatus === 'ocupada') {
            $mesasOcupadas++;
        } else {
            $mesasLivres++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Sincronização concluída!',
        'mesas_verificadas' => count($mesas),
        'mesas_corrigidas' => $mesasCorrigidas,
        'mesas_ocupadas' => $mesasOcupadas,
        'mesas_livres' => $mesasLivres,
        'correcoes' => $resultado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
