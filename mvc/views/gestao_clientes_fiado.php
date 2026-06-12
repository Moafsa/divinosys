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

if (!$tenant || !$filial) {
    header('Location: index.php?view=login');
    exit;
}

// Get clients with fiado orders or AI settings enabled
$clientes = [];
if ($tenant && $filial) {
    // Busca direto da tabela clientes_fiado onde há saldo devedor ou a cobrança automática está ativa
    $clientesData = $db->fetchAll("
        SELECT 
            cf.id, cf.nome, cf.telefone as wpp, cf.cpf_cnpj as cpf,
            cf.limite_credito, cf.status, cf.cobranca_automatica, cf.cobranca_frequencia,
            cf.saldo_devedor, cf.qtd_pedidos_fiado
        FROM clientes_fiado cf
        WHERE cf.tenant_id = ? AND (cf.saldo_devedor > 0 OR cf.cobranca_automatica = true)
        ORDER BY cf.nome ASC
    ", [$tenant['id']]);
    
    foreach ($clientesData as $cliente) {
        $cliente['status'] = $cliente['status'] ?? 'ativo';
        $cliente['limite_credito'] = $cliente['limite_credito'] ?? 0;
        $cliente['cobranca_automatica'] = $cliente['cobranca_automatica'] ?? false;
        $cliente['cobranca_frequencia'] = $cliente['cobranca_frequencia'] ?? 'semanal';
        
        $clientes[] = $cliente;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Fiados - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive-fix.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .client-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .client-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
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
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/components/sidebar.php'; ?>
        
        <!-- Mobile Menu -->
        <?php include __DIR__ . '/components/mobile_menu.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1 p-4 w-100 position-relative">
            <!-- Subscription Alert -->
            <?php include __DIR__ . '/components/subscription_alert.php'; ?>

            <div class="container-fluid">
                <div class="content-wrapper">
                    <!-- Header -->
                    <div class="header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-0">
                                    <i class="fas fa-hand-holding-usd me-2"></i>
                                    Gestão de Fiados
                                </h2>
                                <p class="text-muted mb-0">Clientes com pedidos em aberto</p>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Total Clientes c/ Fiado</h6>
                                            <h3><?= count($clientes) ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Total em Débito</h6>
                                            <h3>R$ <?= number_format(array_sum(array_column($clientes, 'saldo_devedor')), 2, ',', '.') ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Pedidos Fiado Pendentes</h6>
                                            <h3><?= array_sum(array_column($clientes, 'qtd_pedidos_fiado')) ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-receipt fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clients Grid -->
                    <div class="row" id="clientesGrid">
                        <?php foreach ($clientes as $cliente): ?>
                            <div class="col-md-6 col-lg-4 mb-4 cliente-card">
                                <div class="card client-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= htmlspecialchars($cliente['nome']) ?></h6>
                                        <span class="badge bg-<?= $cliente['saldo_devedor'] > 0 ? 'danger' : 'success' ?>">
                                            <?= $cliente['saldo_devedor'] > 0 ? 'Inadimplente' : 'Ok' ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <small class="text-muted">Telefone:</small><br>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $cliente['wpp']) ?>" target="_blank" class="text-decoration-none">
                                                <i class="fab fa-whatsapp text-success"></i> <?= $cliente['wpp'] ?: 'Não informado' ?>
                                            </a>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Dívida Atual:</span>
                                            <span class="debt-amount text-danger">R$ <?= number_format($cliente['saldo_devedor'], 2, ',', '.') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Pedidos Fiado:</span>
                                            <span><?= $cliente['qtd_pedidos_fiado'] ?></span>
                                        </div>
                                        
                                        <!-- AI settings -->
                                        <div class="mt-3 p-2 border rounded bg-light">
                                            <small class="fw-bold d-block mb-1"><i class="fas fa-robot text-primary"></i> Assistente de Cobrança</small>
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox" id="cob-<?= $cliente['id'] ?>" <?= $cliente['cobranca_automatica'] ? 'checked' : '' ?> onchange="updateCobranca(<?= $cliente['id'] ?>, this.checked, document.getElementById('freq-<?= $cliente['id'] ?>').value)">
                                                <label class="form-check-label" style="font-size: 0.85em;" for="cob-<?= $cliente['id'] ?>">Cobrar via WhatsApp</label>
                                            </div>
                                            <select id="freq-<?= $cliente['id'] ?>" class="form-select form-select-sm" onchange="updateCobranca(<?= $cliente['id'] ?>, document.getElementById('cob-<?= $cliente['id'] ?>').checked, this.value)">
                                                <option value="diaria" <?= $cliente['cobranca_frequencia'] == 'diaria' ? 'selected' : '' ?>>Diária</option>
                                                <option value="semanal" <?= $cliente['cobranca_frequencia'] == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                                                <option value="mensal" <?= $cliente['cobranca_frequencia'] == 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex gap-2">
                                            <a href="index.php?view=vendas_fiadas" class="btn btn-sm btn-outline-primary flex-fill">
                                                <i class="fas fa-list"></i> Ver Histórico
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success flex-fill" onclick="abrirModalVincular(<?= $cliente['id'] ?>, '<?= htmlspecialchars(addslashes($cliente['nome'])) ?>')">
                                                <i class="fas fa-link"></i> Vincular Pedido
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($clientes)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <h4>Nenhum cliente com fiado</h4>
                                <p class="text-muted">Não há pedidos com status de fiado no momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Vincular Pedido -->
    <div class="modal fade" id="modalVincularPedido" tabindex="-1" aria-labelledby="modalVincularLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="modalVincularLabel"><i class="fas fa-link text-success"></i> Vincular Pedido a <span id="vinculoClienteNome" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Insira o ID do pedido ou o número da comanda em aberto para transferir o débito para a conta deste cliente.</p>
                    <input type="hidden" id="vinculoClienteId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ID do Pedido / Comanda</label>
                        <input type="number" class="form-control form-control-lg text-center" id="vinculoPedidoId" placeholder="Ex: 1045" autofocus>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" onclick="confirmarVinculoPedido()">Confirmar Vínculo</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        let modalVincular = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            modalVincular = new bootstrap.Modal(document.getElementById('modalVincularPedido'));
        });

        function abrirModalVincular(clienteId, clienteNome) {
            document.getElementById('vinculoClienteId').value = clienteId;
            document.getElementById('vinculoClienteNome').innerText = clienteNome;
            document.getElementById('vinculoPedidoId').value = '';
            modalVincular.show();
        }

        function confirmarVinculoPedido() {
            const clienteId = document.getElementById('vinculoClienteId').value;
            const pedidoId = document.getElementById('vinculoPedidoId').value;
            
            if (!pedidoId) {
                Swal.fire('Atenção', 'Digite o ID do pedido ou comanda', 'warning');
                return;
            }

            const btn = document.querySelector('#modalVincularPedido .btn-success');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'vincular_fiado');
            formData.append('cliente_id', clienteId);
            formData.append('pedido_id', pedidoId);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    modalVincular.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Pedido Vinculado!',
                        text: 'O valor foi adicionado à dívida do cliente com sucesso.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message || 'Falha ao vincular pedido.', 'error');
                }
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                console.error(err);
                Swal.fire('Erro', 'Erro de comunicação com o servidor', 'error');
            });
        }

        function updateCobranca(clienteId, cobrancaAtiva, frequencia) {
            fetch('api/update_fiado_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    usuario_global_id: clienteId,
                    cobranca_automatica: cobrancaAtiva,
                    cobranca_frequencia: frequencia
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Configurações de cobrança atualizadas!'
                    });
                } else {
                    Swal.fire('Erro', 'Não foi possível atualizar: ' + data.error, 'error');
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>
