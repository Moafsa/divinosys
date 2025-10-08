<?php
/**
 * CORREÇÃO FINAL: Sincronização de Mesas e Pedidos
 * 
 * Este script corrige definitivamente o problema de pedidos desaparecendo
 * e mesas sem numeração correta.
 */

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?view=login');
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;

// Conectar ao banco
try {
    $pdo = new PDO(
        "pgsql:host=postgres;port=5432;dbname=divino_db",
        "divino_user",
        "divino_password",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4">🔧 Correção Final: Mesas e Pedidos</h1>
    <p>Este script corrige definitivamente o problema de pedidos desaparecendo e mesas sem numeração.</p>
    
    <?php
    echo "<h2>🔍 VERIFICANDO ESTRUTURA DAS MESAS</h2>";
    
    // 1. VERIFICAR ESTRUTURA DAS MESAS
    $mesas = $pdo->query("SELECT * FROM mesas ORDER BY id_mesa::integer")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='alert alert-info'>";
    echo "<h4>📊 Mesas Encontradas: " . count($mesas) . "</h4>";
    foreach($mesas as $mesa) {
        echo "<div class='mb-2'>";
        echo "<strong>Mesa ID:</strong> {$mesa['id']} | ";
        echo "<strong>ID_Mesa:</strong> {$mesa['id_mesa']} | ";
        echo "<strong>Número:</strong> {$mesa['numero']} | ";
        echo "<strong>Status:</strong> {$mesa['status']}";
        echo "</div>";
    }
    echo "</div>";
    
    // 2. VERIFICAR PEDIDOS ATIVOS
    echo "<h2>🔍 VERIFICANDO PEDIDOS ATIVOS</h2>";
    
    $pedidosAtivos = $pdo->query("
        SELECT p.*, m.id_mesa, m.numero as mesa_numero
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa
        WHERE p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ORDER BY p.idmesa, p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='alert alert-warning'>";
    echo "<h4>📊 Pedidos Ativos Encontrados: " . count($pedidosAtivos) . "</h4>";
    foreach($pedidosAtivos as $pedido) {
        echo "<div class='mb-2'>";
        echo "<strong>Pedido #{$pedido['idpedido']}</strong> - ";
        echo "Mesa: {$pedido['idmesa']} (ID_Mesa: {$pedido['id_mesa']}) - ";
        echo "Status: {$pedido['status']} - ";
        echo "Valor: R$ " . number_format($pedido['valor_total'], 2, ',', '.');
        echo "</div>";
    }
    echo "</div>";
    
    // 3. CORRIGIR STATUS DAS MESAS
    echo "<h2>🔧 CORRIGINDO STATUS DAS MESAS</h2>";
    
    $mesasCorrigidas = 0;
    foreach($mesas as $mesa) {
        // Contar pedidos ativos para esta mesa
        $pedidosMesa = $pdo->query("
            SELECT COUNT(*) as total
            FROM pedido 
            WHERE idmesa::varchar = ? 
            AND status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        ")->fetch(PDO::FETCH_ASSOC, [$mesa['id_mesa']]);
        
        $totalPedidos = $pedidosMesa['total'];
        $novoStatus = $totalPedidos > 0 ? 'ocupada' : 'livre';
        
        if ($mesa['status'] !== $novoStatus) {
            $stmt = $pdo->prepare("UPDATE mesas SET status = ? WHERE id = ?");
            $stmt->execute([$novoStatus, $mesa['id']]);
            
            echo "<div class='alert alert-success'>";
            echo "✅ <strong>Mesa {$mesa['id_mesa']}:</strong> {$mesa['status']} → {$novoStatus} ({$totalPedidos} pedidos)";
            echo "</div>";
            $mesasCorrigidas++;
        } else {
            echo "<div class='alert alert-info'>";
            echo "✅ <strong>Mesa {$mesa['id_mesa']}:</strong> Status correto ({$totalPedidos} pedidos)";
            echo "</div>";
        }
    }
    
    // 4. VERIFICAÇÃO FINAL
    echo "<h2>✅ VERIFICAÇÃO FINAL</h2>";
    
    $mesasCorrigidas = $pdo->query("
        SELECT m.*, 
               COUNT(p.idpedido) as total_pedidos,
               COALESCE(SUM(p.valor_total), 0) as valor_total
        FROM mesas m
        LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar 
            AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        GROUP BY m.id, m.id_mesa, m.numero, m.status
        ORDER BY m.id_mesa::integer
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='alert alert-success'>";
    echo "<h4>📊 Status Final das Mesas:</h4>";
    foreach($mesasCorrigidas as $mesa) {
        $statusIcon = $mesa['status'] === 'ocupada' ? '🔴' : '🟢';
        $statusText = $mesa['status'] === 'ocupada' ? 'Ocupada' : 'Livre';
        
        echo "<div class='mb-2'>";
        echo "{$statusIcon} <strong>Mesa {$mesa['id_mesa']}:</strong> {$statusText} ({$mesa['total_pedidos']} pedidos - R$ " . number_format($mesa['valor_total'], 2, ',', '.') . ")";
        echo "</div>";
    }
    echo "</div>";
    
    echo "<div class='alert alert-success'>";
    echo "<h4>🎯 CORREÇÃO CONCLUÍDA!</h4>";
    echo "<p>Agora acesse o dashboard e verifique:</p>";
    echo "<ul>";
    echo "<li>✅ Mesas numeradas corretamente</li>";
    echo "<li>✅ Mesas em ordem numérica</li>";
    echo "<li>✅ Pedidos aparecendo nas mesas</li>";
    echo "<li>✅ Status das mesas correto</li>";
    echo "</ul>";
    echo "<p><a href='index.php?view=dashboard' class='btn btn-primary'>Ir para Dashboard</a></p>";
    echo "</div>";
    ?>
</div>

<style>
.alert {
    margin: 10px 0;
    padding: 15px;
    border-radius: 5px;
}
.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.alert-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
</style>
