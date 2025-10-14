<?php
require_once '../../config/config.php';
require_once '../../system/Database.php';
require_once '../../system/Auth.php';
require_once '../../system/Session.php';

use System\Auth;
use System\Database;
use System\Session;

// Check authentication
$session = Auth::validateSession();
if (!$session) {
    header('Location: index.php?view=login');
    exit;
}

// Get user establishment data
$db = Database::getInstance();
$userEstablishment = $db->fetch(
    "SELECT * FROM usuarios_estabelecimento 
     WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
    [$session['usuario_global_id'], $session['tenant_id'], $session['filial_id']]
);

if (!$userEstablishment || $userEstablishment['tipo_usuario'] !== 'cliente') {
    header('Location: index.php?view=login');
    exit;
}

// Get user data
$user = $db->fetch(
    "SELECT * FROM usuarios_globais WHERE id = ?",
    [$session['usuario_global_id']]
);

// Get user orders
$pedidos = $db->fetchAll(
    "SELECT p.*, 
            CASE WHEN p.mesa_id IS NOT NULL THEN m.numero ELSE 'Delivery' END as mesa_numero,
            CASE WHEN p.tipo = 'delivery' THEN 'Delivery' 
                 WHEN p.tipo = 'mesa' THEN CONCAT('Mesa ', m.numero)
                 ELSE 'Balcão' END as tipo_pedido
     FROM pedidos p 
     LEFT JOIN mesas m ON p.mesa_id = m.id 
     WHERE p.cliente_telefone = ? AND p.tenant_id = ? AND p.filial_id = ?
     ORDER BY p.created_at DESC 
     LIMIT 20",
    [$session['usuario_global_id'], $session['tenant_id'], $session['filial_id']]
);

// Get order items for each order
foreach ($pedidos as &$pedido) {
    $pedido['itens'] = $db->fetchAll(
        "SELECT pi.*, pr.nome as produto_nome, pr.preco_normal
         FROM pedido_itens pi
         JOIN produtos pr ON pi.produto_id = pr.id
         WHERE pi.pedido_id = ?
         ORDER BY pi.id",
        [$pedido['id']]
    );
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-preparando {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-pronto {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-entregue {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .order-item {
            border-bottom: 1px solid #e9ecef;
            padding: 10px 0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-utensils"></i> Divino Lanches
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Profile Card -->
        <div class="card profile-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">Olá, <?php echo htmlspecialchars($user['nome']); ?>!</h4>
                        <p class="mb-0">Bem-vindo ao seu painel de pedidos</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-circle fa-3x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <a href="index.php?view=novo_pedido" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-plus"></i> Novo Pedido
                </a>
            </div>
            <div class="col-md-6">
                <button class="btn btn-outline-primary btn-lg w-100" onclick="refreshOrders()">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
            </div>
        </div>

        <!-- Orders History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history"></i> Meus Pedidos
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pedidos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h5>Nenhum pedido encontrado</h5>
                        <p>Você ainda não fez nenhum pedido. Que tal fazer o primeiro?</p>
                        <a href="index.php?view=novo_pedido" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Novo Pedido
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <div class="order-item">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1">Pedido #<?php echo $pedido['numero_pedido']; ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('d/m/Y H:i', strtotime($pedido['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge status-<?php echo $pedido['status']; ?>">
                                        <?php 
                                        $statusLabels = [
                                            'pendente' => 'Pendente',
                                            'preparando' => 'Preparando',
                                            'pronto' => 'Pronto',
                                            'entregue' => 'Entregue',
                                            'cancelado' => 'Cancelado'
                                        ];
                                        echo $statusLabels[$pedido['status']] ?? $pedido['status'];
                                        ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo $pedido['tipo_pedido']; ?></small>
                                </div>
                                <div class="col-md-4">
                                    <div class="order-items-preview">
                                        <?php foreach (array_slice($pedido['itens'], 0, 2) as $item): ?>
                                            <small class="d-block">
                                                <?php echo $item['quantidade']; ?>x <?php echo htmlspecialchars($item['produto_nome']); ?>
                                            </small>
                                        <?php endforeach; ?>
                                        <?php if (count($pedido['itens']) > 2): ?>
                                            <small class="text-muted">
                                                +<?php echo count($pedido['itens']) - 2; ?> item(s) mais
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <h6 class="mb-0">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewOrderDetails(<?php echo $pedido['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function logout() {
            if (confirm('Deseja realmente sair?')) {
                fetch('mvc/ajax/phone_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                })
                .then(response => response.json())
                .then(data => {
                    window.location.href = 'index.php?view=login';
                })
                .catch(error => {
                    console.error('Erro:', error);
                    window.location.href = 'index.php?view=login';
                });
            }
        }

        function refreshOrders() {
            window.location.reload();
        }

        function viewOrderDetails(orderId) {
            // This would typically fetch order details via AJAX
            // For now, we'll show a simple message
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>Carregando detalhes do pedido...</p>
                </div>
            `;
            modal.show();
            
            // Simulate loading
            setTimeout(() => {
                document.getElementById('orderDetailsContent').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-info-circle fa-2x mb-3 text-primary"></i>
                        <p>Funcionalidade de detalhes será implementada em breve.</p>
                        <p>ID do Pedido: ${orderId}</p>
                    </div>
                `;
            }, 1000);
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                // You could implement a silent refresh here
                console.log('Auto-refresh triggered');
            }
        }, 30000);
    </script>
</body>
</html>
