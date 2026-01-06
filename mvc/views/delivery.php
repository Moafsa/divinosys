<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Ensure tenant and filial context
$context = \System\TenantHelper::ensureTenantContext();
$tenant = $context['tenant'];
$filial = $context['filial'];
$user = $session->getUser();

// Debug: Se não tem tenant/filial, usar valores padrão
if (!$tenant) {
    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = 1");
    if ($tenant) {
        $session->setTenant($tenant);
    }
}

if (!$filial) {
    $filial = $db->fetch("SELECT * FROM filiais WHERE id = 1");
    if ($filial) {
        $session->setFilial($filial);
    }
}

// Get delivery pedidos (incluindo pedidos online do tipo delivery)
$pedidos = [];
if ($tenant && $filial) {
    try {
        // Buscar pedidos tradicionais de delivery
        $pedidosDelivery = $db->fetchAll(
            "SELECT p.*, u.login as usuario_nome
             FROM pedido p 
             LEFT JOIN usuarios u ON p.usuario_id = u.id
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.delivery = true
             AND p.data >= CURRENT_DATE - INTERVAL '7 days'
             AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado')
             ORDER BY p.hora_pedido DESC",
            [$tenant['id'], $filial['id']]
        );
        
        // Buscar pedidos online do tipo delivery ou pickup (998 e 999)
        $pedidosOnlineDelivery = $db->fetchAll(
            "SELECT p.*, u.login as usuario_nome
             FROM pedido p 
             LEFT JOIN usuarios u ON p.usuario_id = u.id
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.usuario_global_id IS NOT NULL
             AND (
                 p.tipo_entrega = 'delivery' 
                 OR p.tipo_entrega = 'pickup'
                 OR p.idmesa::varchar = '999'
                 OR p.idmesa::varchar = '998'
             )
             AND p.data >= CURRENT_DATE - INTERVAL '7 days'
             AND NOT (p.status = 'Entregue' AND p.status_pagamento = 'quitado')
             ORDER BY p.hora_pedido DESC",
            [$tenant['id'], $filial['id']]
        );
        
        // Combinar e remover duplicados
        $pedidosIds = [];
        $pedidos = [];
        
        foreach ($pedidosDelivery as $pedido) {
            $pedidosIds[$pedido['idpedido']] = true;
            $pedidos[] = $pedido;
        }
        
        foreach ($pedidosOnlineDelivery as $pedido) {
            if (!isset($pedidosIds[$pedido['idpedido']])) {
                $pedidosIds[$pedido['idpedido']] = true;
                $pedidos[] = $pedido;
            }
        }
        
        // Ordenar por hora do pedido
        usort($pedidos, function($a, $b) {
            return strcmp($b['hora_pedido'], $a['hora_pedido']);
        });
        
    } catch (Exception $e) {
        error_log("Erro ao buscar pedidos de delivery: " . $e->getMessage());
        $pedidos = [];
    }
}

// Group by status
$pedidos_por_status = [
    'Pendente' => [],
    'Em Preparo' => [],
    'Pronto' => [],
    'Saiu para Entrega' => [],
    'Entregue' => [],
    'Cancelado' => []
];

foreach ($pedidos as $pedido) {
    $status = $pedido['status'] ?? 'Pendente';
    if (isset($pedidos_por_status[$status])) {
        $pedidos_por_status[$status][] = $pedido;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
            --online-order-color: <?php echo $tenant['cor_primaria'] ?? '#dc3545'; ?>;
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
        
        .kanban-column {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            min-height: 600px;
        }
        
        .column-header {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 600;
            color: white;
        }
        
        .column-header.pendente { background: linear-gradient(45deg, #ffc107, #fd7e14); }
        .column-header.em_preparo { background: linear-gradient(45deg, #28a745, #20c997); }
        .column-header.pronto { background: linear-gradient(45deg, #28a745, #20c997); }
        .column-header.saiu_para_entrega { background: linear-gradient(45deg, #17a2b8, #20c997); }
        .column-header.entregue { background: linear-gradient(45deg, #007bff, #6610f2); }
        .column-header.cancelado { background: linear-gradient(45deg, #dc3545, #e83e8c); }
        
        .pedido-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .pedido-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .pedido-card-online {
            background: var(--online-order-color) !important;
            color: white !important;
            border-left: 4px solid rgba(255, 255, 255, 0.3);
        }

        .pedido-card-online .text-primary,
        .pedido-card-online .fw-bold {
            color: white !important;
        }

        .pedido-card-online .text-muted {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .pedido-card-online .text-success {
            color: white !important;
        }

        .pedido-card-online .btn {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: white !important;
        }

        .pedido-card-online .btn:hover {
            background: rgba(255, 255, 255, 0.3) !important;
        }

        .pedido-card-online:hover {
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
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
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/components/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content expanded">
                <div class="content-wrapper">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-motorcycle me-2"></i>
                                Delivery
                            </h2>
                            <p class="text-muted mb-0">Gerenciamento de pedidos de delivery</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-primary" onclick="atualizarDelivery()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Atualizar
                                </button>
                                <a href="<?php echo $router->url('gerar_pedido'); ?>&mesa=Delivery" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Delivery
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kanban Board -->
                <div class="row">
                    <?php foreach ($pedidos_por_status as $status => $pedidos_status): ?>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="kanban-column">
                                <div class="column-header <?php echo strtolower(str_replace(' ', '_', $status)); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo $status; ?></span>
                                        <span class="badge bg-light text-dark"><?php echo count($pedidos_status); ?></span>
                                    </div>
                                </div>
                                
                                <div class="pedidos-list">
                                    <?php foreach ($pedidos_status as $pedido): ?>
                                        <?php
                                        // Verificar se é pedido do cardápio online
                                        // Pedidos online têm usuario_global_id e (tipo_entrega definido OU idmesa = '998' ou '999')
                                        $isOnlineOrder = (
                                            !empty($pedido['usuario_global_id']) && 
                                            (
                                                (!empty($pedido['tipo_entrega']) && in_array($pedido['tipo_entrega'], ['delivery', 'pickup']))
                                                || in_array($pedido['idmesa'], ['998', '999'])
                                            )
                                        );
                                        $cardClass = $isOnlineOrder ? 'pedido-card pedido-card-online' : 'pedido-card';
                                        ?>
                                        <div class="<?php echo $cardClass; ?>" onclick="verPedido(<?php echo $pedido['idpedido']; ?>)">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="fw-bold text-primary">#<?php echo $pedido['idpedido']; ?></div>
                                                <div class="text-muted small"><?php echo $pedido['hora_pedido']; ?></div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <i class="fas fa-motorcycle me-1 text-warning"></i>
                                                <span class="fw-bold">Delivery</span>
                                                <?php if ($isOnlineOrder): ?>
                                                    <span class="badge bg-light text-dark ms-2" style="font-size: 0.7rem;">
                                                        <i class="fas fa-shopping-cart me-1"></i>Cardápio Online
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="h5 text-success mb-2">
                                                R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?>
                                            </div>
                                            
                                            <?php if ($pedido['cliente']): ?>
                                                <div class="text-muted small mb-2">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($pedido['cliente']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <?php if ($status === 'Entregue'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); fecharPedidoDelivery(<?php echo $pedido['idpedido']; ?>)" title="Fechar Pedido">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="event.stopPropagation(); imprimirPedidoDelivery(<?php echo $pedido['idpedido']; ?>)" title="Imprimir Pedido">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); atualizarStatus(<?php echo $pedido['idpedido']; ?>, '<?php echo $status; ?>')">
                                                    <i class="fas fa-arrow-right"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); excluirPedido(<?php echo $pedido['idpedido']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Partial Payment System -->
    <script src="assets/js/pagamentos-parciais.js"></script>
    
    <script>
        // Dados do estabelecimento
        const estabelecimento = {
            nome: <?php echo json_encode($filial['nome'] ?? 'Divino Lanches'); ?>,
            endereco: <?php echo json_encode($filial['endereco'] ?? ''); ?>,
            telefone: <?php echo json_encode($filial['telefone'] ?? ''); ?>,
            email: <?php echo json_encode($filial['email'] ?? ''); ?>,
            cnpj: <?php echo json_encode($filial['cnpj'] ?? ''); ?>
        };
        
        function verPedido(pedidoId) {
            console.log('Buscando pedido:', pedidoId);
            // Buscar dados do pedido via AJAX
            fetch('mvc/ajax/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_pedido&pedido_id=${pedidoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    const itens = data.itens || [];
                    console.log('Dados recebidos:', data);
                    console.log('Pedido:', pedido);
                    console.log('Itens:', itens);
                    
                    // Construir HTML do popup
                    let itensHtml = '';
                    itens.forEach(item => {
                        let ingredientesHtml = '';
                        if (item.ingredientes_com && item.ingredientes_com.length > 0) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-success">+ ' + item.ingredientes_com.join(', ') + '</small></div>';
                        }
                        if (item.ingredientes_sem && item.ingredientes_sem.length > 0) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-danger">- ' + item.ingredientes_sem.join(', ') + '</small></div>';
                        }
                        if (item.observacao) {
                            ingredientesHtml += '<div class="mb-1"><small class="text-info">Obs: ' + item.observacao + '</small></div>';
                        }
                        
                        itensHtml += `
                            <tr>
                                <td>${item.quantidade}x</td>
                                <td>${item.nome_produto || 'Produto não encontrado'}</td>
                                <td>R$ ${parseFloat(item.valor_unitario || 0).toFixed(2)}</td>
                                <td>R$ ${parseFloat(item.valor_total || 0).toFixed(2)}</td>
                            </tr>
                            ${ingredientesHtml ? '<tr><td colspan="4">' + ingredientesHtml + '</td></tr>' : ''}
                        `;
                    });
                    
                    const popupHtml = `
                        <div class="modal fade" id="modalPedido" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Pedido #${pedido.idpedido}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Cliente:</strong> ${pedido.cliente || 'Cliente Mesa'}<br>
                                                <strong>Data:</strong> ${pedido.data}<br>
                                                <strong>Hora:</strong> ${pedido.hora_pedido}
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Status:</strong> <span class="badge bg-primary">${pedido.status}</span><br>
                                                <strong>Valor Total:</strong> R$ ${parseFloat(pedido.valor_total).toFixed(2)}
                                            </div>
                                        </div>
                                        
                                        <h6>Itens do Pedido:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Qtd</th>
                                                        <th>Produto</th>
                                                        <th>Preço Unit.</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${itensHtml}
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        ${pedido.observacao ? '<div class="mt-3"><strong>Observação:</strong> ' + pedido.observacao + '</div>' : ''}
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-success" onclick="imprimirPedidoDelivery(${pedido.idpedido})">
                                            <i class="fas fa-print me-1"></i>
                                            Imprimir
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="editarPedido(${pedido.idpedido})">
                                            <i class="fas fa-edit me-1"></i>
                                            Editar Pedido
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remover modal existente se houver
                    const existingModal = document.getElementById('modalPedido');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Adicionar novo modal
                    document.body.insertAdjacentHTML('beforeend', popupHtml);
                    
                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('modalPedido'));
                    modal.show();
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do pedido', 'error');
            });
        }

        function editarPedido(pedidoId) {
            // Fechar modal atual
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalPedido'));
            if (modal) {
                modal.hide();
            }
            
            // Redirecionar para página de edição
            window.location.href = `<?php echo $router->url('gerar_pedido'); ?>&editar=${pedidoId}`;
        }
        
        function atualizarStatus(pedidoId, statusAtual) {
            const statuses = ['Pendente', 'Em Preparo', 'Pronto', 'Saiu para Entrega', 'Entregue'];
            const currentIndex = statuses.indexOf(statusAtual);
            
            if (currentIndex < statuses.length - 1) {
                const novoStatus = statuses[currentIndex + 1];
                
                // Atualizar status diretamente sem confirmação
                fetch('mvc/ajax/pedidos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=atualizar_status&pedido_id=${pedidoId}&status=${novoStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recarregar a página para mostrar o novo status
                        location.reload();
                    } else {
                        console.error('Erro ao atualizar status:', data.message);
                        // Mostrar erro apenas no console, sem popup
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
            }
        }

        function excluirPedido(pedidoId) {
            Swal.fire({
                title: 'Excluir Pedido',
                text: `Deseja realmente excluir o pedido #${pedidoId}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/pedidos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=excluir_pedido&pedido_id=${pedidoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Pedido excluído com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message || 'Erro ao excluir pedido', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao excluir pedido', 'error');
                    });
                }
            });
        }

        function fecharPedidoDelivery(pedidoId) {
            // Redirecionar para a página de fechar pedido (mesmo modelo da mesa)
            window.location.href = `index.php?view=fechar_pedido&pedido_id=${pedidoId}`;
        }

        function atualizarDelivery() {
            location.reload();
        }

        function imprimirPedidoDelivery(pedidoId) {
            console.log('Imprimindo pedido delivery:', pedidoId);
            
            // Fetch pedido data
            fetch('mvc/ajax/pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=buscar_pedido&pedido_id=${pedidoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pedido = data.pedido;
                    const itens = data.itens || [];
                    const agora = new Date();
                    const dataHora = agora.toLocaleString('pt-BR');
                    
                    // Determinar tipo (delivery ou retirada)
                    let tipoTexto = 'DELIVERY';
                    if (pedido.idmesa === '998') {
                        tipoTexto = 'RETIRADA NO BALCÃO';
                    }
                    
                    // Generate print HTML using the same format as gerar_pedido.php
                    let printHtml = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Cupom Fiscal - Pedido Delivery #${pedido.idpedido}</title>
                            <style>
                                body { 
                                    font-family: 'Courier New', monospace; 
                                    font-size: 16px; 
                                    margin: 0; 
                                    padding: 15px; 
                                    line-height: 1.4;
                                }
                                .header { 
                                    text-align: center; 
                                    border-bottom: 2px solid #000; 
                                    padding-bottom: 15px; 
                                    margin-bottom: 15px; 
                                }
                                .empresa { 
                                    font-weight: bold; 
                                    font-size: 20px; 
                                    margin-bottom: 5px;
                                }
                                .endereco { 
                                    font-size: 14px; 
                                    margin-bottom: 10px;
                                }
                                .pedido-info { 
                                    margin: 15px 0; 
                                    font-size: 16px;
                                    font-weight: bold;
                                }
                                .item { 
                                    margin: 8px 0; 
                                    padding: 5px 0;
                                    border-bottom: 1px dotted #ccc;
                                }
                                .item-nome { 
                                    font-weight: bold; 
                                    font-size: 18px; 
                                    color: #000;
                                }
                                .item-detalhes { 
                                    font-size: 16px; 
                                    margin-left: 15px; 
                                    margin-top: 5px;
                                }
                                .modificacoes { 
                                    margin-left: 25px; 
                                    font-size: 15px; 
                                    margin-top: 8px;
                                }
                                .adicionado { 
                                    color: #006400; 
                                    font-weight: bold;
                                }
                                .removido { 
                                    color: #DC143C; 
                                    font-weight: bold;
                                }
                                .total { 
                                    border-top: 2px solid #000; 
                                    padding-top: 15px; 
                                    margin-top: 20px; 
                                    font-weight: bold; 
                                    font-size: 20px;
                                    text-align: center;
                                }
                                .footer { 
                                    text-align: center; 
                                    margin-top: 25px; 
                                    font-size: 14px; 
                                    font-weight: bold;
                                }
                                .observacao {
                                    margin-top: 15px;
                                    padding: 10px;
                                    background-color: #f0f0f0;
                                    border: 1px solid #000;
                                    font-size: 16px;
                                    font-weight: bold;
                                }
                                @media print { 
                                    body { 
                                        margin: 0; 
                                        padding: 10px;
                                        font-size: 14px;
                                    }
                                    .item-nome { font-size: 16px; }
                                    .total { font-size: 18px; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div class="empresa">${estabelecimento.nome.toUpperCase()}</div>
                                ${estabelecimento.endereco ? `<div class="endereco">${estabelecimento.endereco}</div>` : ''}
                                ${estabelecimento.telefone ? `<div class="endereco">Tel: ${estabelecimento.telefone}</div>` : ''}
                            </div>
                            
                            <div class="pedido-info">
                                <strong>PEDIDO #${pedido.idpedido}</strong><br>
                                Data/Hora: ${pedido.data} ${pedido.hora_pedido}<br>
                                ${tipoTexto}<br>
                                ${pedido.cliente ? `Cliente: ${pedido.cliente}` : ''}
                                ${pedido.telefone_cliente ? `<br>Telefone: ${pedido.telefone_cliente}` : ''}
                                ${pedido.usuario_nome ? `<br>Atendente: ${pedido.usuario_nome}` : ''}
                            </div>
                            
                            <div class="itens">
                                <strong>ITENS DO PEDIDO:</strong><br>`;
                    
                    itens.forEach(item => {
                        // Processar ingredientes (podem vir como string separada por vírgula)
                        let ingredientesCom = [];
                        let ingredientesSem = [];
                        
                        if (item.ingredientes_com) {
                            if (typeof item.ingredientes_com === 'string') {
                                ingredientesCom = item.ingredientes_com.split(',').map(i => i.trim()).filter(i => i);
                            } else if (Array.isArray(item.ingredientes_com)) {
                                ingredientesCom = item.ingredientes_com;
                            }
                        }
                        
                        if (item.ingredientes_sem) {
                            if (typeof item.ingredientes_sem === 'string') {
                                ingredientesSem = item.ingredientes_sem.split(',').map(i => i.trim()).filter(i => i);
                            } else if (Array.isArray(item.ingredientes_sem)) {
                                ingredientesSem = item.ingredientes_sem;
                            }
                        }
                        
                        printHtml += `
                            <div class="item">
                                <div class="item-nome">${item.quantidade}x ${item.nome_produto || 'Produto'}</div>
                                <div class="item-detalhes">R$ ${parseFloat(item.valor_unitario || 0).toFixed(2).replace('.', ',')}</div>`;
                        
                        if (ingredientesCom.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            ingredientesCom.forEach(ing => {
                                printHtml += `<div class="adicionado">+ ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (ingredientesSem.length > 0) {
                            printHtml += `<div class="modificacoes">`;
                            ingredientesSem.forEach(ing => {
                                printHtml += `<div class="removido">- ${ing}</div>`;
                            });
                            printHtml += `</div>`;
                        }
                        
                        if (item.observacao) {
                            printHtml += `<div class="item-detalhes">Obs: ${item.observacao}</div>`;
                        }
                        
                        printHtml += `</div>`;
                    });
                    
                    printHtml += `
                            </div>
                            
                            <div class="total">
                                <strong>TOTAL: R$ ${parseFloat(pedido.valor_total || 0).toFixed(2).replace('.', ',')}</strong>
                            </div>
                            
                            ${pedido.observacao ? `<div class="observacao"><strong>OBSERVAÇÃO:</strong> ${pedido.observacao}</div>` : ''}
                            
                            <div class="footer">
                                Obrigado pela preferência!<br>
                                Volte sempre!
                            </div>
                        </body>
                        </html>`;
                    
                    // Create a new window for printing
                    const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
                    
                    if (!printWindow) {
                        alert('Erro: Não foi possível abrir janela de impressão. Verifique se o popup está bloqueado.');
                        return;
                    }
                    
                    printWindow.document.write(printHtml);
                    printWindow.document.close();
                    
                    // Aguardar carregamento e imprimir automaticamente
                    printWindow.addEventListener('load', function() {
                        setTimeout(() => {
                            try {
                                printWindow.focus();
                                printWindow.print();
                                
                                // Fechar janela após um tempo
                                setTimeout(() => {
                                    printWindow.close();
                                }, 3000);
                            } catch (error) {
                                console.error('Erro ao imprimir:', error);
                                alert('Erro ao imprimir. Verifique se há uma impressora configurada.');
                            }
                        }, 500);
                    });
                    
                    // Fallback caso o evento load não funcione
                    setTimeout(() => {
                        try {
                            printWindow.focus();
                            printWindow.print();
                            
                            setTimeout(() => {
                                printWindow.close();
                            }, 3000);
                        } catch (error) {
                            console.error('Erro ao imprimir (fallback):', error);
                            printWindow.close();
                        }
                    }, 1500);
                    
                } else {
                    Swal.fire('Erro', 'Erro ao carregar dados do pedido para impressão', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao imprimir pedido:', error);
                Swal.fire('Erro', 'Erro ao imprimir pedido', 'error');
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            atualizarDelivery();
        }, 30000);
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
