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
    // ---- SINCRONIZAÇÃO AUTOMÁTICA DE FIADOS LEGADOS ----
    try {
        // 1. Sincroniza pagamentos com forma_pagamento = 'FIADO'
        $legacyFiados = $db->fetchAll("
            SELECT 
                pp.id as pagamento_id, pp.pedido_id, pp.valor_pago, 
                COALESCE(pp.nome_cliente, ug.nome, p.cliente) as nome_cliente, 
                COALESCE(pp.telefone_cliente, ug.telefone, p.telefone_cliente) as telefone_cliente, 
                pp.created_at
            FROM pagamentos_pedido pp
            LEFT JOIN pedido p ON p.idpedido = pp.pedido_id
            LEFT JOIN usuarios_globais ug ON ug.id = p.usuario_global_id
            LEFT JOIN vendas_fiadas vf ON vf.pedido_id = pp.pedido_id AND vf.valor_total = pp.valor_pago
            WHERE pp.forma_pagamento = 'FIADO' AND pp.tenant_id = ? AND vf.id IS NULL
        ", [$tenant['id']]);

        // 2. Sincroniza pedidos com status = 'fiado' que não tenham pagamentos_pedido = FIADO
        $legacyPedidosFiado = $db->fetchAll("
            SELECT 
                p.idpedido as pedido_id, p.valor_total as valor_pago, 
                COALESCE(ug.nome, p.cliente) as nome_cliente, 
                COALESCE(ug.telefone, p.telefone_cliente) as telefone_cliente, 
                p.created_at
            FROM pedido p
            LEFT JOIN usuarios_globais ug ON ug.id = p.usuario_global_id
            LEFT JOIN vendas_fiadas vf ON vf.pedido_id = p.idpedido
            LEFT JOIN pagamentos_pedido pp ON pp.pedido_id = p.idpedido AND pp.forma_pagamento = 'FIADO'
            WHERE p.status = 'fiado' AND p.tenant_id = ? AND vf.id IS NULL AND pp.id IS NULL
        ", [$tenant['id']]);

        $todosLegados = array_merge($legacyFiados, $legacyPedidosFiado);

        foreach ($todosLegados as $lf) {
            $telefone = preg_replace('/[^0-9]/', '', $lf['telefone_cliente'] ?? '');
            if (empty($telefone)) continue;
            
            $nome = $lf['nome_cliente'] ?: 'Cliente Não Identificado';
            
            // Verifica ou cria o cliente_fiado
            $cf = $db->fetch("SELECT id FROM clientes_fiado WHERE telefone = ? AND tenant_id = ?", [$telefone, $tenant['id']]);
            if (!$cf) {
                $cfId = $db->insert('clientes_fiado', [
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id'],
                    'saldo_devedor' => 0
                ]);
            } else {
                $cfId = $cf['id'];
            }
            
            // Insere a venda fiada pendente
            $db->insert('vendas_fiadas', [
                'cliente_id' => $cfId,
                'pedido_id' => $lf['pedido_id'],
                'valor_total' => $lf['valor_pago'],
                'status' => 'pendente',
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id'],
                'data_vencimento' => date('Y-m-d', strtotime(($lf['created_at'] ?? date('Y-m-d H:i:s')) . ' + 30 days'))
            ]);
            
            // Atualiza saldo devedor
            $db->query("UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?", [$lf['valor_pago'], $cfId]);
        }
    } catch (\Exception $e) {
        error_log("Erro na sincronizacao de fiados: " . $e->getMessage());
    }
    // ----------------------------------------------------

    // Busca direto da tabela clientes_fiado onde há saldo devedor ou a cobrança automática está ativa
    $clientesData = $db->fetchAll("
        SELECT 
            cf.id, cf.nome, cf.telefone as wpp, cf.cpf_cnpj as cpf,
            cf.limite_credito, cf.status, cf.cobranca_automatica, cf.cobranca_frequencia,
            cf.saldo_devedor,
            (SELECT COUNT(*) FROM vendas_fiadas vf WHERE vf.cliente_id = cf.id AND vf.status = 'pendente') as qtd_pedidos_fiado
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

                    <!-- Clients Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Telefone</th>
                                            <th>Saldo Devedor</th>
                                            <th>Pedidos Fiados</th>
                                            <th>Cobrança IA (WhatsApp)</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold"><?= htmlspecialchars($cliente['nome']) ?></span>
                                                    <?php if($cliente['saldo_devedor'] > 0): ?>
                                                        <span class="badge bg-danger ms-2" style="font-size:0.7em">Inadimplente</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success ms-2" style="font-size:0.7em">Ok</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $cliente['wpp']) ?>" target="_blank" class="text-decoration-none text-dark">
                                                        <i class="fab fa-whatsapp text-success"></i> <?= $cliente['wpp'] ?: '-' ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <strong class="text-danger">R$ <?= number_format($cliente['saldo_devedor'], 2, ',', '.') ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary rounded-pill"><?= $cliente['qtd_pedidos_fiado'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox" id="cob-<?= $cliente['id'] ?>" <?= $cliente['cobranca_automatica'] ? 'checked' : '' ?> onchange="updateCobranca(<?= $cliente['id'] ?>, this.checked, document.getElementById('freq-<?= $cliente['id'] ?>').value)">
                                                        </div>
                                                        <select id="freq-<?= $cliente['id'] ?>" class="form-select form-select-sm" style="width: auto;" onchange="updateCobranca(<?= $cliente['id'] ?>, document.getElementById('cob-<?= $cliente['id'] ?>').checked, this.value)">
                                                            <option value="diaria" <?= $cliente['cobranca_frequencia'] == 'diaria' ? 'selected' : '' ?>>Diária</option>
                                                            <option value="semanal" <?= $cliente['cobranca_frequencia'] == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                                                            <option value="mensal" <?= $cliente['cobranca_frequencia'] == 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="abrirModalVincular(<?= $cliente['id'] ?>, '<?= htmlspecialchars(addslashes($cliente['nome'])) ?>')" title="Vincular Pedido">
                                                            <i class="fas fa-link"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalPedidosPagamento(<?= $cliente['id'] ?>, '<?= htmlspecialchars(addslashes($cliente['nome'])) ?>', <?= $cliente['saldo_devedor'] ?>)" title="Ver Pedidos e Receber">
                                                            <i class="fas fa-list"></i> Receber
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($clientes)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                                    <br>Nenhum cliente com fiado no momento.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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

    <!-- Modal Pedidos e Pagamento Lote -->
    <div class="modal fade" id="modalPedidosCliente" tabindex="-1" aria-labelledby="modalPedidosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="modalPedidosLabel"><i class="fas fa-list text-primary"></i> Pedidos de <span id="pagamentoClienteNome" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pagamentoClienteId">
                    
                    <div class="table-responsive mb-4" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>ID Pedido</th>
                                    <th>Data</th>
                                    <th>Valor Total</th>
                                    <th>Saldo Restante</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPedidosPendente">
                                <!-- Preenchido via AJAX -->
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <h6 class="mb-0 fw-bold">Registrar Pagamento</h6>
                            <span class="badge bg-danger fs-6">Dívida Total: <span id="pagamentoSaldoDevedor"></span></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Valor a Pagar (R$)</label>
                                <input type="number" class="form-control" id="valor_pagamento_lote" step="0.01" min="0.01" placeholder="0.00">
                                <small class="text-muted">Abaterá automaticamente os pedidos mais antigos.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Forma de Pagamento</label>
                                <select id="forma_pagamento_lote" class="form-select">
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="pix">PIX</option>
                                    <option value="cartao_debito">Cartão Débito</option>
                                    <option value="cartao_credito">Cartão Crédito</option>
                                    <option value="transferencia">Transferência</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 mt-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-success px-4" onclick="confirmarPagamentoLote()"><i class="fas fa-check"></i> Processar Pagamento</button>
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

        let modalPagamentoLote = null;

        function abrirModalPedidosPagamento(clienteId, clienteNome, saldoDevedor) {
            document.getElementById('pagamentoClienteId').value = clienteId;
            document.getElementById('pagamentoClienteNome').innerText = clienteNome;
            document.getElementById('pagamentoSaldoDevedor').innerText = saldoDevedor.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            document.getElementById('valor_pagamento_lote').value = '';
            document.getElementById('valor_pagamento_lote').max = saldoDevedor;

            if (!modalPagamentoLote) {
                modalPagamentoLote = new bootstrap.Modal(document.getElementById('modalPedidosCliente'));
            }

            // Fetch pending orders
            const tbody = document.getElementById('tbodyPedidosPendente');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando pedidos...</td></tr>';
            
            const formData = new FormData();
            formData.append('acao', 'listar_vendas_pendentes_cliente');
            formData.append('cliente_id', clienteId);

            fetch('mvc/ajax/vendas_fiadas.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                if (data.success && data.vendas && data.vendas.length > 0) {
                    data.vendas.forEach(v => {
                        let statusBadge = v.status === 'pendente' ? '<span class="badge bg-warning">Pendente</span>' : '<span class="badge bg-info">Parcial</span>';
                        tbody.innerHTML += `
                            <tr>
                                <td>${v.id}</td>
                                <td>${new Date(v.data_venda).toLocaleDateString('pt-BR')}</td>
                                <td>R$ ${parseFloat(v.valor_total).toFixed(2).replace('.', ',')}</td>
                                <td>R$ ${parseFloat(v.saldo_devedor).toFixed(2).replace('.', ',')}</td>
                                <td>${statusBadge}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum pedido pendente encontrado (possível erro de sincronização).</td></tr>';
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Erro ao carregar pedidos.</td></tr>';
            });

            modalPagamentoLote.show();
        }

        function confirmarPagamentoLote() {
            const clienteId = document.getElementById('pagamentoClienteId').value;
            const valor = document.getElementById('valor_pagamento_lote').value;
            const forma = document.getElementById('forma_pagamento_lote').value;

            if (!valor || valor <= 0 || !forma) {
                Swal.fire('Atenção', 'Preencha o valor e a forma de pagamento.', 'warning');
                return;
            }

            const btn = document.querySelector('#modalPedidosCliente .btn-success');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('acao', 'pagamento_lote_cliente');
            formData.append('cliente_id', clienteId);
            formData.append('valor_pagamento', valor);
            formData.append('forma_pagamento', forma);

            fetch('mvc/ajax/vendas_fiadas.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    modalPagamentoLote.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Pagamento Registrado!',
                        text: 'O valor foi abatido dos pedidos mais antigos.',
                        timer: 2500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message || 'Falha ao registrar.', 'error');
                }
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                console.error(err);
                Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
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
