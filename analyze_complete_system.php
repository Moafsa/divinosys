<?php
$host = 'postgres';
$port = '5432';
$dbname = 'divino_lanches';
$user = 'postgres';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ANÁLISE COMPLETA DO SISTEMA ===\n";
    
    // 1. Verificar estrutura das tabelas principais
    echo "\n=== ESTRUTURA DAS TABELAS ===\n";
    $tables = ['mesas', 'pedido', 'pedido_itens', 'produtos'];
    foreach($tables as $table) {
        echo "\n--- TABELA: $table ---\n";
        $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table' ORDER BY ordinal_position");
        while ($row = $stmt->fetch()) {
            echo "- {$row['column_name']}: {$row['data_type']}\n";
        }
    }
    
    // 2. Verificar dados das mesas
    echo "\n=== DADOS DAS MESAS ===\n";
    $stmt = $pdo->query('SELECT id, id_mesa, numero, nome, status, tenant_id, filial_id FROM mesas ORDER BY numero');
    $mesas = $stmt->fetchAll();
    foreach($mesas as $mesa) {
        echo "Mesa {$mesa['numero']} (ID: {$mesa['id']}, ID_MESA: {$mesa['id_mesa']}) - Status: {$mesa['status']} - Tenant: {$mesa['tenant_id']} - Filial: {$mesa['filial_id']}\n";
    }
    
    // 3. Verificar pedidos ativos
    echo "\n=== PEDIDOS ATIVOS ===\n";
    $stmt = $pdo->query("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at, p.tenant_id, p.filial_id,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    $pedidos = $stmt->fetchAll();
    if (empty($pedidos)) {
        echo "Nenhum pedido ativo encontrado.\n";
    } else {
        foreach($pedidos as $pedido) {
            echo "Pedido #{$pedido['idpedido']} - Mesa: {$pedido['idmesa']} (Número: {$pedido['mesa_numero']}) - Status: {$pedido['status']} - Valor: R$ {$pedido['valor_total']} - Hora: {$pedido['hora_pedido']} - Tenant: {$pedido['tenant_id']} - Filial: {$pedido['filial_id']}\n";
        }
    }
    
    // 4. Verificar pedidos por mesa específica
    echo "\n=== ANÁLISE POR MESA ===\n";
    foreach($mesas as $mesa) {
        $stmt = $pdo->prepare("
            SELECT p.idpedido, p.status, p.valor_total, p.hora_pedido, p.created_at
            FROM pedido p 
            WHERE p.idmesa = ? AND p.status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$mesa['id_mesa']]);
        $pedidosMesa = $stmt->fetchAll();
        
        echo "Mesa {$mesa['numero']} (ID_MESA: {$mesa['id_mesa']}):\n";
        if (empty($pedidosMesa)) {
            echo "  - Nenhum pedido ativo\n";
        } else {
            echo "  - " . count($pedidosMesa) . " pedido(s) ativo(s):\n";
            foreach($pedidosMesa as $pedido) {
                echo "    * Pedido #{$pedido['idpedido']} - Status: {$pedido['status']} - Valor: R$ {$pedido['valor_total']} - Hora: {$pedido['hora_pedido']}\n";
            }
        }
    }
    
    // 5. Verificar inconsistências
    echo "\n=== VERIFICANDO INCONSISTÊNCIAS ===\n";
    
    // Mesas com múltiplos pedidos ativos
    $stmt = $pdo->query("
        SELECT p.idmesa, COUNT(*) as total_pedidos
        FROM pedido p 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        GROUP BY p.idmesa
        HAVING COUNT(*) > 1
    ");
    $inconsistencias = $stmt->fetchAll();
    
    if (!empty($inconsistencias)) {
        echo "⚠️ MESAS COM MÚLTIPLOS PEDIDOS ATIVOS:\n";
        foreach($inconsistencias as $inc) {
            echo "  - Mesa {$inc['idmesa']}: {$inc['total_pedidos']} pedidos ativos\n";
        }
    } else {
        echo "✅ Nenhuma mesa com múltiplos pedidos ativos\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
