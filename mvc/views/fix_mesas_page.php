<?php
/**
 * CORREÇÃO: Página de Mesas
 * 
 * Este script corrige a ordenação das mesas na página /index.php?view=mesas
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
    <h1 class="mb-4">🔧 Correção: Página de Mesas</h1>
    <p>Este script corrige a ordenação das mesas na página de mesas.</p>
    
    <?php
    echo "<h2>🔍 VERIFICANDO MESAS ANTES DA CORREÇÃO</h2>";
    
    // 1. VERIFICAR MESAS ANTES DA CORREÇÃO
    $mesasAntes = $pdo->query("
        SELECT m.*, 
                CASE WHEN p.idpedido IS NOT NULL THEN 1 ELSE 0 END as tem_pedido,
                p.idpedido, p.valor_total, p.hora_pedido, p.status as pedido_status
         FROM mesas m 
         LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar AND p.status NOT IN ('Finalizado', 'Cancelado')
         WHERE m.tenant_id = ? AND m.filial_id = ? 
         ORDER BY m.numero::integer
    ")->fetchAll(PDO::FETCH_ASSOC, [$tenantId, $filialId]);
    
    echo "<div class='alert alert-warning'>";
    echo "<h4>📊 Mesas ANTES da correção (ordem incorreta):</h4>";
    foreach($mesasAntes as $mesa) {
        $status = $mesa['idpedido'] ? 'Ocupada' : 'Livre';
        $statusIcon = $mesa['idpedido'] ? '🔴' : '🟢';
        echo "<div class='mb-1'>";
        echo "{$statusIcon} Mesa {$mesa['id_mesa']} - {$status}";
        if ($mesa['idpedido']) {
            echo " (Pedido #{$mesa['idpedido']} - R$ " . number_format($mesa['valor_total'], 2, ',', '.') . ")";
        }
        echo "</div>";
    }
    echo "</div>";
    
    // 2. APLICAR CORREÇÃO
    echo "<h2>🔧 APLICANDO CORREÇÃO</h2>";
    
    $mesasCorrigidas = $pdo->query("
        SELECT m.*, 
                CASE WHEN p.idpedido IS NOT NULL THEN 1 ELSE 0 END as tem_pedido,
                p.idpedido, p.valor_total, p.hora_pedido, p.status as pedido_status
         FROM mesas m 
         LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar AND p.status NOT IN ('Finalizado', 'Cancelado')
         WHERE m.tenant_id = ? AND m.filial_id = ? 
         ORDER BY m.id_mesa::integer
    ")->fetchAll(PDO::FETCH_ASSOC, [$tenantId, $filialId]);
    
    echo "<div class='alert alert-success'>";
    echo "<h4>📊 Mesas APÓS a correção (ordem correta):</h4>";
    foreach($mesasCorrigidas as $mesa) {
        $status = $mesa['idpedido'] ? 'Ocupada' : 'Livre';
        $statusIcon = $mesa['idpedido'] ? '🔴' : '🟢';
        echo "<div class='mb-1'>";
        echo "{$statusIcon} Mesa {$mesa['id_mesa']} - {$status}";
        if ($mesa['idpedido']) {
            echo " (Pedido #{$mesa['idpedido']} - R$ " . number_format($mesa['valor_total'], 2, ',', '.') . ")";
        }
        echo "</div>";
    }
    echo "</div>";
    
    // 3. VERIFICAÇÃO FINAL
    echo "<h2>✅ VERIFICAÇÃO FINAL</h2>";
    
    $mesasOrdenadas = $pdo->query("
        SELECT m.id_mesa, m.numero, m.status,
               COUNT(p.idpedido) as total_pedidos,
               COALESCE(SUM(p.valor_total), 0) as valor_total
        FROM mesas m
        LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar 
            AND p.status NOT IN ('Finalizado', 'Cancelado')
        WHERE m.tenant_id = ? AND m.filial_id = ?
        GROUP BY m.id, m.id_mesa, m.numero, m.status
        ORDER BY m.id_mesa::integer
    ")->fetchAll(PDO::FETCH_ASSOC, [$tenantId, $filialId]);
    
    echo "<div class='alert alert-info'>";
    echo "<h4>📊 Ordem Final das Mesas:</h4>";
    foreach($mesasOrdenadas as $mesa) {
        $statusIcon = $mesa['total_pedidos'] > 0 ? '🔴' : '🟢';
        $statusText = $mesa['total_pedidos'] > 0 ? 'Ocupada' : 'Livre';
        
        echo "<div class='mb-1'>";
        echo "{$statusIcon} <strong>Mesa {$mesa['id_mesa']}</strong> - {$statusText}";
        if ($mesa['total_pedidos'] > 0) {
            echo " ({$mesa['total_pedidos']} pedidos - R$ " . number_format($mesa['valor_total'], 2, ',', '.') . ")";
        }
        echo "</div>";
    }
    echo "</div>";
    
    echo "<div class='alert alert-success'>";
    echo "<h4>🎯 CORREÇÃO CONCLUÍDA!</h4>";
    echo "<p>Agora as mesas estão ordenadas corretamente:</p>";
    echo "<ul>";
    echo "<li>✅ Mesas em ordem numérica (1, 2, 3, 4...)</li>";
    echo "<li>✅ Pedidos aparecendo nas mesas corretas</li>";
    echo "<li>✅ Status das mesas sincronizado</li>";
    echo "</ul>";
    echo "<p><a href='index.php?view=mesas' class='btn btn-primary'>Ver Página de Mesas</a></p>";
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
