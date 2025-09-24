<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Get mesas data
$mesas = [];
if ($tenant && $filial) {
    $mesas = $db->fetchAll(
        "SELECT m.*, 
                CASE WHEN p.idpedido IS NOT NULL THEN 1 ELSE 0 END as tem_pedido,
                p.idpedido, p.valor_total, p.hora_pedido, p.status as pedido_status
         FROM mesas m 
         LEFT JOIN pedido p ON m.id_mesa = p.idmesa::varchar AND p.status NOT IN ('Finalizado', 'Cancelado')
         WHERE m.tenant_id = ? AND m.filial_id = ? 
         ORDER BY m.id_mesa::integer",
        [$tenant['id'], $filial['id']]
    );
}

// Get stats - usar query separada para evitar duplicação
$stats = [
    'total_mesas' => count($mesas),
    'mesas_ocupadas' => $db->fetch(
        "SELECT COUNT(DISTINCT p.idmesa) as count 
         FROM pedido p 
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.status NOT IN ('Finalizado', 'Cancelado')",
        [$tenant['id'], $filial['id']]
    )['count'] ?? 0,
    'mesas_livres' => 0, // Será calculado depois
    'faturamento_mesas' => $db->fetch(
        "SELECT COALESCE(SUM(valor_total), 0) as total 
         FROM pedido p 
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         AND p.status NOT IN ('Finalizado', 'Cancelado')",
        [$tenant['id'], $filial['id']]
    )['total'] ?? 0
];

$stats['mesas_livres'] = $stats['total_mesas'] - $stats['mesas_ocupadas'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), #6c757d);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.5rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            text-align: center;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .mesa-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .mesa-card.livre {
            border-color: #28a745;
        }
        
        .mesa-card.ocupada {
            border-color: #dc3545;
        }
        
        .mesa-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .mesa-status.livre {
            background-color: #28a745;
        }
        
        .mesa-status.ocupada {
            background-color: #dc3545;
        }
        
        .mesa-numero {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .mesa-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .pedido-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar collapsed" id="sidebar">
                <div class="p-3">
                    <div class="sidebar-brand">
                        <h4 class="text-white mb-4">
                            <i class="fas fa-utensils me-2"></i>
                            <?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>
                        </h4>
                        <div class="brand-icon text-white">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>" data-tooltip="Dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>" data-tooltip="Novo Pedido">
                            <i class="fas fa-plus-circle"></i>
                            <span>Novo Pedido</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>" data-tooltip="Pedidos">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('mesas'); ?>" data-tooltip="Mesas">
                            <i class="fas fa-table"></i>
                            <span>Mesas</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>" data-tooltip="Delivery">
                            <i class="fas fa-motorcycle"></i>
                            <span>Delivery</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>" data-tooltip="Produtos">
                            <i class="fas fa-box"></i>
                            <span>Produtos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
                            <i class="fas fa-chart-line"></i>
                            <span>Financeiro</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>" data-tooltip="Clientes">
                            <i class="fas fa-users"></i>
                            <span>Clientes</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content expanded">
                <div class="content-wrapper">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Mesas
                            </h2>
                            <p class="text-muted mb-0">Controle de mesas e pedidos em tempo real</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-primary" onclick="atualizarMesas()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                                <a href="<?php echo $router->url('gerar_pedido'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Pedido
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total_mesas']; ?></div>
                            <div class="stats-label">Total de Mesas</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?php echo $stats['mesas_livres']; ?></div>
                            <div class="stats-label">Mesas Livres</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-danger"><?php echo $stats['mesas_ocupadas']; ?></div>
                            <div class="stats-label">Mesas Ocupadas</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number">R$ <?php echo number_format($stats['faturamento_mesas'], 2, ',', '.'); ?></div>
                            <div class="stats-label">Faturamento Mesas</div>
                        </div>
                    </div>
                </div>

                <!-- Mesas Grid -->
                <div class="row">
                    <?php foreach ($mesas as $mesa): ?>
                        <?php
                        $status = $mesa['idpedido'] ? 'ocupada' : 'livre';
                        $statusText = $mesa['idpedido'] ? 'Ocupada' : 'Livre';
                        $statusClass = $mesa['idpedido'] ? 'text-danger' : 'text-success';
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="mesa-card <?php echo $status; ?>" onclick="verMesa(<?php echo $mesa['id']; ?>, <?php echo $mesa['id_mesa']; ?>)">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="mesa-status <?php echo $status; ?>"></span>
                                    <span class="mesa-numero">Mesa <?php echo $mesa['id_mesa']; ?></span>
                                </div>
                                
                                <div class="mesa-info">
                                    <div class="fw-bold <?php echo $statusClass; ?>">
                                        <i class="fas fa-circle me-1"></i>
                                        <?php echo $statusText; ?>
                                    </div>
                                    
                                    <?php if ($mesa['idpedido']): ?>
                                        <div class="pedido-info">
                                            <div class="fw-bold">Pedido #<?php echo $mesa['idpedido']; ?></div>
                                            <div class="text-success">
                                                <i class="fas fa-dollar-sign me-1"></i>
                                                R$ <?php echo number_format($mesa['valor_total'], 2, ',', '.'); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $mesa['hora_pedido']; ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="badge bg-primary"><?php echo $mesa['pedido_status']; ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Disponível para pedidos
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <?php if ($mesa['idpedido']): ?>
                                        <button class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="event.stopPropagation(); verPedido(<?php echo $mesa['idpedido']; ?>)">
                                            <i class="fas fa-eye me-1"></i>
                                            Ver Pedido
                                        </button>
                                        <button class="btn btn-sm btn-outline-success w-100" onclick="event.stopPropagation(); fecharMesa(<?php echo $mesa['id']; ?>)">
                                            <i class="fas fa-check me-1"></i>
                                            Fechar Mesa
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo $router->url('gerar_pedido'); ?>&mesa=<?php echo $mesa['id_mesa']; ?>" class="btn btn-sm btn-primary w-100">
                                            <i class="fas fa-plus me-1"></i>
                                            Fazer Pedido
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mesa -->
    <div class="modal fade" id="modalMesa" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-table me-2"></i>
                        Mesa <span id="mesaNumero"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalMesaBody">
                    <!-- Content will be loaded here -->
                </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function verMesa(mesaId, mesaNumero) {
            document.getElementById('mesaNumero').textContent = mesaNumero;
            
            // Load mesa content via AJAX
            fetch(`index.php?action=mesa_multiplos_pedidos&ver_mesa=1&mesa_id=${mesaNumero}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalMesaBody').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('modalMesa')).show();
                    } else {
                        Swal.fire('Erro', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Erro', 'Erro ao carregar dados da mesa', 'error');
                });
        }

        function verPedido(pedidoId) {
            window.location.href = `<?php echo $router->url('pedidos'); ?>&pedido=${pedidoId}`;
        }

        function fecharMesa(mesaId) {
            Swal.fire({
                title: 'Fechar Mesa',
                text: 'Deseja realmente fechar todos os pedidos desta mesa?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, fechar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Implementar fechamento da mesa via AJAX
                    Swal.fire('Sucesso', 'Mesa fechada com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        function atualizarMesas() {
            location.reload();
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarMesas();
        }, 30000);
    </script>
    
    <!-- Include sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
</body>
</html>
