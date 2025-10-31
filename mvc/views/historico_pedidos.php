<?php
// Client Order History Page
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user
$user = $session->getUser();
$userId = $_SESSION['usuario_global_id'] ?? $_SESSION['user_id'] ?? $user['id'] ?? null;

if (!$userId) {
    header('Location: index.php?view=login');
    exit();
}

// Get filter parameters
$filterEstablishment = $_GET['estabelecimento'] ?? null;
$filterStatus = $_GET['status'] ?? null;
$filterDateFrom = $_GET['data_de'] ?? null;
$filterDateTo = $_GET['data_ate'] ?? null;

// Build query
$query = "SELECT p.*, 
          t.nome as tenant_nome, 
          f.nome as filial_nome,
          COALESCE(p.valor_pago, 0) as total_pago
          FROM pedido p
          LEFT JOIN tenants t ON p.tenant_id = t.id
          LEFT JOIN filiais f ON p.filial_id = f.id
          WHERE p.usuario_id = ?";

$params = [$userId];

if ($filterEstablishment) {
    $query .= " AND p.tenant_id = ?";
    $params[] = $filterEstablishment;
}

if ($filterStatus) {
    $query .= " AND p.status = ?";
    $params[] = $filterStatus;
}

if ($filterDateFrom) {
    $query .= " AND DATE(p.data) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $query .= " AND DATE(p.data) <= ?";
    $params[] = $filterDateTo;
}

$query .= " ORDER BY p.data DESC, p.hora_pedido DESC LIMIT 50";

// Get orders
$orders = $db->fetchAll($query, $params);

// Get establishments for filter
$establishments = $db->fetchAll(
    "SELECT DISTINCT t.id, t.nome 
     FROM pedido p
     JOIN tenants t ON p.tenant_id = t.id
     WHERE p.usuario_global_id = ?
     ORDER BY t.nome",
    [$userId]
);

// Calculate totals
$totalPedidos = count($orders);
$totalGasto = array_sum(array_column($orders, 'valor_total'));
$totalPago = array_sum(array_column($orders, 'total_pago'));

// Group orders by establishment
$ordersByEstablishment = [];
foreach ($orders as $order) {
    $key = $order['tenant_id'] . '_' . ($order['filial_id'] ?? '0');
    $name = $order['tenant_nome'];
    if ($order['filial_nome']) {
        $name .= ' - ' . $order['filial_nome'];
    }
    
    if (!isset($ordersByEstablishment[$key])) {
        $ordersByEstablishment[$key] = [
            'name' => $name,
            'orders' => [],
            'total' => 0,
            'count' => 0
        ];
    }
    
    $ordersByEstablishment[$key]['orders'][] = $order;
    $ordersByEstablishment[$key]['total'] += $order['valor_total'];
    $ordersByEstablishment[$key]['count']++;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Pedidos - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .history-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .stats-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
        .order-card {
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-id {
            font-weight: 600;
            color: #667eea;
            font-size: 1.1rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-pendente { background: #fff3cd; color: #856404; }
        .status-em-preparo { background: #cfe2ff; color: #084298; }
        .status-pronto { background: #d1e7dd; color: #0f5132; }
        .status-entregue { background: #d1e7dd; color: #0f5132; }
        .status-finalizado { background: #d1e7dd; color: #0f5132; }
        .status-cancelado { background: #f8d7da; color: #842029; }
        
        .payment-status {
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .establishment-section {
            margin-bottom: 3rem;
        }
        .establishment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        .filter-card {
            background: #f8f9ff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <a href="<?php echo $router->url('cliente_dashboard'); ?>" class="btn btn-primary btn-back">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <div class="container">
        <!-- Header -->
        <div class="history-card">
            <h2 class="mb-4">
                <i class="fas fa-history"></i> Histórico de Pedidos
            </h2>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3><?php echo $totalPedidos; ?></h3>
                        <p>Total de Pedidos</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3>R$ <?php echo number_format($totalGasto, 2, ',', '.'); ?></h3>
                        <p>Total Gasto</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h3>R$ <?php echo number_format($totalPago, 2, ',', '.'); ?></h3>
                        <p>Total Pago</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <h5 class="mb-3"><i class="fas fa-filter"></i> Filtros</h5>
                <form method="GET" action="">
                    <input type="hidden" name="view" value="historico_pedidos">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Estabelecimento</label>
                            <select name="estabelecimento" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($establishments as $est): ?>
                                    <option value="<?php echo $est['id']; ?>" 
                                        <?php echo $filterEstablishment == $est['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="Pendente" <?php echo $filterStatus == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="Em Preparo" <?php echo $filterStatus == 'Em Preparo' ? 'selected' : ''; ?>>Em Preparo</option>
                                <option value="Pronto" <?php echo $filterStatus == 'Pronto' ? 'selected' : ''; ?>>Pronto</option>
                                <option value="Entregue" <?php echo $filterStatus == 'Entregue' ? 'selected' : ''; ?>>Entregue</option>
                                <option value="Finalizado" <?php echo $filterStatus == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                <option value="Cancelado" <?php echo $filterStatus == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data De</label>
                            <input type="date" name="data_de" class="form-control" value="<?php echo $filterDateFrom; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Até</label>
                            <input type="date" name="data_ate" class="form-control" value="<?php echo $filterDateTo; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders by Establishment -->
        <?php if (empty($orders)): ?>
            <div class="history-card">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Nenhum pedido encontrado com os filtros selecionados.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($ordersByEstablishment as $estKey => $estData): ?>
                <div class="establishment-section">
                    <div class="establishment-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">
                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($estData['name']); ?>
                                </h4>
                                <p class="mb-0 opacity-75">
                                    <?php echo $estData['count']; ?> pedido(s) - 
                                    Total: R$ <?php echo number_format($estData['total'], 2, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($estData['orders'] as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">
                                        <i class="fas fa-receipt"></i> Pedido #<?php echo $order['id']; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($order['data_pedido'])); ?>
                                    </small>
                                </div>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <?php if ($order['tipo_pedido']): ?>
                                        <p class="mb-2">
                                            <strong>Tipo:</strong> 
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($order['tipo_pedido']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['observacoes']): ?>
                                        <p class="mb-2">
                                            <strong>Observações:</strong> 
                                            <?php echo htmlspecialchars($order['observacoes']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Payment Status -->
                                    <div class="payment-status">
                                        <?php
                                        $valorTotal = $order['valor_total'];
                                        $valorPago = $order['total_pago'];
                                        $valorRestante = $valorTotal - $valorPago;
                                        
                                        if ($valorPago >= $valorTotal) {
                                            echo '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Pago</span>';
                                        } elseif ($valorPago > 0) {
                                            echo '<span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i> Pagamento Parcial</span>';
                                            echo ' <small class="text-muted">Falta: R$ ' . number_format($valorRestante, 2, ',', '.') . '</small>';
                                        } else {
                                            echo '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Pendente</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h4 class="text-primary mb-2">
                                        R$ <?php echo number_format($order['valor_total'], 2, ',', '.'); ?>
                                    </h4>
                                    <?php if ($order['forma_pagamento']): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-credit-card"></i>
                                            <?php echo htmlspecialchars($order['forma_pagamento']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalhes(pedidoId) {
            // TODO: Implement order details modal or redirect
            alert('Detalhes do pedido #' + pedidoId + '\n\nFuncionalidade em desenvolvimento.');
        }
    </script>
</body>
</html>










