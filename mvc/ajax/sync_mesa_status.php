<?php
/**
 * SCRIPT PARA SINCRONIZAR STATUS DAS MESAS COM PEDIDOS REAIS
 * 
 * Este script corrige o problema de mesas marcadas como ocupadas
 * mas sem pedidos ativos visíveis
 */

session_start();
header('Content-Type: application/json');

// Autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => __DIR__ . '/../../system/',
        'App\\' => __DIR__ . '/../../app/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    // 1. OBTER TODAS AS MESAS
    $mesas = $db->fetchAll("
        SELECT id, id_mesa, numero, status 
        FROM mesas 
        ORDER BY numero::integer
    ");
    
    $mesasCorrigidas = 0;
    $mesasOcupadas = 0;
    $mesasLivres = 0;
    $resultado = [];
    
    // 2. VERIFICAR CADA MESA
    foreach ($mesas as $mesa) {
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
        
        // Atualizar status se necessário
        if ($mesa['status'] !== $novoStatus) {
            $db->update(
                'mesas',
                ['status' => $novoStatus],
                'id = ?',
                [$mesa['id']]
            );
            
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
