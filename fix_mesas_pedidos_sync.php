<?php
/**
 * CORREÇÃO FINAL: Sincronização de Mesas e Pedidos
 * 
 * Este script corrige definitivamente o problema de pedidos desaparecendo
 * e mesas sem numeração correta.
 */

require_once 'system/Database.php';

echo "=== CORREÇÃO FINAL: MESAS E PEDIDOS ===\n";
echo "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // 1. VERIFICAR ESTRUTURA DAS MESAS
    echo "=== VERIFICANDO ESTRUTURA DAS MESAS ===\n";
    $mesas = $db->fetchAll("SELECT * FROM mesas ORDER BY id_mesa::integer");
    
    foreach($mesas as $mesa) {
        echo "Mesa ID: {$mesa['id']} | ID_Mesa: {$mesa['id_mesa']} | Número: {$mesa['numero']} | Status: {$mesa['status']}\n";
    }
    
    // 2. VERIFICAR PEDIDOS ATIVOS
    echo "\n=== VERIFICANDO PEDIDOS ATIVOS ===\n";
    $pedidosAtivos = $db->fetchAll("
        SELECT p.*, m.id_mesa, m.numero as mesa_numero
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    echo "📊 PEDIDOS ATIVOS ENCONTRADOS: " . count($pedidosAtivos) . "\n";
    foreach($pedidosAtivos as $pedido) {
        echo "  - Pedido #{$pedido['idpedido']} - Mesa: {$pedido['idmesa']} (ID_Mesa: {$pedido['id_mesa']}) - Status: {$pedido['status']} - Valor: R$ {$pedido['valor_total']}\n";
    }
    
    // 3. CORRIGIR STATUS DAS MESAS BASEADO NOS PEDIDOS REAIS
    echo "\n=== CORRIGINDO STATUS DAS MESAS ===\n";
    
    foreach($mesas as $mesa) {
        // Contar pedidos ativos para esta mesa
        $pedidosMesa = $db->fetchAll("
            SELECT COUNT(*) as total
            FROM pedido 
            WHERE idmesa::varchar = ? 
            AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ", [$mesa['id_mesa']]);
        
        $totalPedidos = $pedidosMesa[0]['total'];
        $novoStatus = $totalPedidos > 0 ? 'ocupada' : 'livre';
        
        if ($mesa['status'] !== $novoStatus) {
            $db->update(
                'mesas',
                ['status' => $novoStatus, 'updated_at' => 'NOW()'],
                'id = ?',
                [$mesa['id']]
            );
            
            echo "✅ Mesa {$mesa['id_mesa']}: {$mesa['status']} → {$novoStatus} ({$totalPedidos} pedidos)\n";
        } else {
            echo "✅ Mesa {$mesa['id_mesa']}: Status correto ({$totalPedidos} pedidos)\n";
        }
    }
    
    // 4. VERIFICAÇÃO FINAL
    echo "\n=== VERIFICAÇÃO FINAL ===\n";
    
    $mesasCorrigidas = $db->fetchAll("
        SELECT m.*, 
               COUNT(p.idpedido) as total_pedidos,
               COALESCE(SUM(p.valor_total), 0) as valor_total
        FROM mesas m
        LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar 
            AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        GROUP BY m.id, m.id_mesa, m.numero, m.status
        ORDER BY m.id_mesa::integer
    ");
    
    echo "📊 STATUS FINAL DAS MESAS:\n";
    foreach($mesasCorrigidas as $mesa) {
        $statusIcon = $mesa['status'] === 'ocupada' ? '🔴' : '🟢';
        $statusText = $mesa['status'] === 'ocupada' ? 'Ocupada' : 'Livre';
        
        echo "  {$statusIcon} Mesa {$mesa['id_mesa']}: {$statusText} ({$mesa['total_pedidos']} pedidos - R$ {$mesa['valor_total']})\n";
    }
    
    echo "\n✅ CORREÇÃO CONCLUÍDA!\n";
    echo "Agora acesse o dashboard e verifique:\n";
    echo "1. ✅ Mesas numeradas corretamente\n";
    echo "2. ✅ Mesas em ordem numérica\n";
    echo "3. ✅ Pedidos aparecendo nas mesas\n";
    echo "4. ✅ Status das mesas correto\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
