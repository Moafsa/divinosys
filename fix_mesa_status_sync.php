<?php
/**
 * SCRIPT PARA SINCRONIZAR STATUS DAS MESAS COM PEDIDOS REAIS
 * 
 * Este script corrige o problema de mesas marcadas como ocupadas
 * mas sem pedidos ativos visíveis
 */

require_once 'system/Database.php';

echo "=== SINCRONIZANDO STATUS DAS MESAS ===\n";

try {
    $db = \System\Database::getInstance();
    echo "✅ Database connection established\n\n";
    
    // 1. OBTER TODAS AS MESAS
    $mesas = $db->fetchAll("
        SELECT id, id_mesa, numero, status 
        FROM mesas 
        ORDER BY numero::integer
    ");
    
    echo "📋 Encontradas " . count($mesas) . " mesas\n\n";
    
    $mesasCorrigidas = 0;
    $mesasOcupadas = 0;
    $mesasLivres = 0;
    
    // 2. VERIFICAR CADA MESA
    foreach ($mesas as $mesa) {
        echo "🔍 Verificando Mesa " . $mesa['numero'] . " (ID: " . $mesa['id_mesa'] . ")...\n";
        
        // Verificar pedidos ativos para esta mesa
        $pedidosAtivos = $db->fetchAll("
            SELECT p.idpedido, p.status, p.valor_total, p.created_at
            FROM pedido p 
            WHERE p.idmesa::varchar = ? 
            AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
            ORDER BY p.created_at DESC
        ", [$mesa['id_mesa']]);
        
        $temPedidosAtivos = count($pedidosAtivos) > 0;
        $novoStatus = $temPedidosAtivos ? 'ocupada' : 'livre';
        
        echo "   Status atual: " . $mesa['status'] . "\n";
        echo "   Pedidos ativos: " . count($pedidosAtivos) . "\n";
        echo "   Status correto: " . $novoStatus . "\n";
        
        // Atualizar status se necessário
        if ($mesa['status'] !== $novoStatus) {
            $db->update(
                'mesas',
                ['status' => $novoStatus],
                'id = ?',
                [$mesa['id']]
            );
            
            echo "   ✅ Status corrigido: " . $mesa['status'] . " → " . $novoStatus . "\n";
            $mesasCorrigidas++;
        } else {
            echo "   ✅ Status já correto\n";
        }
        
        if ($novoStatus === 'ocupada') {
            $mesasOcupadas++;
            echo "   📋 Pedidos ativos:\n";
            foreach ($pedidosAtivos as $pedido) {
                $idade = round((time() - strtotime($pedido['created_at'])) / 3600, 1);
                echo "      - Pedido #" . $pedido['idpedido'] . " - Status: " . $pedido['status'] . 
                     " - Valor: R$ " . number_format($pedido['valor_total'], 2, ',', '.') . 
                     " - Idade: " . $idade . "h\n";
            }
        } else {
            $mesasLivres++;
        }
        
        echo "\n";
    }
    
    // 3. RESUMO FINAL
    echo "=== RESUMO DA SINCRONIZAÇÃO ===\n";
    echo "✅ Mesas verificadas: " . count($mesas) . "\n";
    echo "🔧 Mesas corrigidas: " . $mesasCorrigidas . "\n";
    echo "🔴 Mesas ocupadas: " . $mesasOcupadas . "\n";
    echo "🟢 Mesas livres: " . $mesasLivres . "\n";
    
    if ($mesasCorrigidas > 0) {
        echo "\n✅ Sincronização concluída! " . $mesasCorrigidas . " mesa(s) corrigida(s).\n";
        echo "💡 Agora o dashboard deve mostrar o status correto das mesas.\n";
    } else {
        echo "\n✅ Todas as mesas já estavam com status correto!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
