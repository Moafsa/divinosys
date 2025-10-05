<?php
echo "=== INVESTIGANDO MESAS OCUPADAS ===\n";

// Database connection
$host = 'postgres';
$port = '5432';
$dbname = 'divino_db';
$user = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection established\n";

    // Verificar todas as mesas
    echo "\n=== TODAS AS MESAS ===\n";
    $stmt = $pdo->query("SELECT id, id_mesa, numero, nome, status FROM mesas ORDER BY numero");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($mesas as $mesa) {
        echo "Mesa " . $mesa['numero'] . " (ID: " . $mesa['id'] . ", ID_MESA: " . $mesa['id_mesa'] . ") - Status: " . $mesa['status'] . "\n";
    }
    
    echo "\n=== PEDIDOS ATIVOS ===\n";
    $stmt = $pdo->query("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pedidos)) {
        echo "Nenhum pedido ativo encontrado.\n";
    } else {
        foreach($pedidos as $pedido) {
            echo "Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    
    echo "\n=== VERIFICANDO MESA 3 ESPECIFICAMENTE ===\n";
    $stmt = $pdo->prepare("SELECT * FROM mesas WHERE id_mesa = ?");
    $stmt->execute(['3']);
    $mesa3 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mesa3) {
        echo "Mesa 3 - Status na tabela mesas: " . $mesa3['status'] . "\n";
        
        $stmt = $pdo->prepare("
            SELECT * FROM pedido 
            WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY created_at DESC
        ");
        $stmt->execute(['3']);
        $pedidosMesa3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pedidosMesa3)) {
            echo "Mesa 3: Nenhum pedido ativo encontrado.\n";
        } else {
            echo "Mesa 3: " . count($pedidosMesa3) . " pedido(s) ativo(s):\n";
            foreach($pedidosMesa3 as $pedido) {
                echo "  - Pedido #" . $pedido['idpedido'] . " - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . "\n";
            }
        }
    }
    
    echo "\n=== VERIFICANDO MESA 8 ESPECIFICAMENTE ===\n";
    $stmt = $pdo->prepare("SELECT * FROM mesas WHERE id_mesa = ?");
    $stmt->execute(['8']);
    $mesa8 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mesa8) {
        echo "Mesa 8 - Status na tabela mesas: " . $mesa8['status'] . "\n";
        
        $stmt = $pdo->prepare("
            SELECT * FROM pedido 
            WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado')
            ORDER BY created_at DESC
        ");
        $stmt->execute(['8']);
        $pedidosMesa8 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pedidosMesa8)) {
            echo "Mesa 8: Nenhum pedido ativo encontrado.\n";
        } else {
            echo "Mesa 8: " . count($pedidosMesa8) . " pedido(s) ativo(s):\n";
            foreach($pedidosMesa8 as $pedido) {
                echo "  - Pedido #" . $pedido['idpedido'] . " - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . "\n";
            }
        }
    }

    // Test problematic query
    echo "\n=== TESTING PROBLEMATIC QUERY ===\n";
    try {
        $stmt = $pdo->prepare("SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY numero::integer");
        $stmt->execute([1, 1]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Query executed successfully! Found " . count($results) . " records\n";
    } catch (PDOException $e) {
        echo "❌ Query failed: " . $e->getMessage() . "\n";
        
        // Try alternative query with available columns
        echo "\n=== TRYING ALTERNATIVE QUERY ===\n";
        try {
            $stmt = $pdo->prepare("SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id");
            $stmt->execute([1, 1]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "✅ Alternative query worked! Found " . count($results) . " records\n";
        } catch (PDOException $e2) {
            echo "❌ Alternative query also failed: " . $e2->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
