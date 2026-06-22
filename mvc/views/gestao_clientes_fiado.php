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

// Get clients with fiado balance or automatic billing enabled
$clientes = [];
if ($tenant && $filial) {
    // ---- SINCRONIZAÇÃO AUTOMÁTICA DE FIADOS LEGADOS ----
    try {
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

        foreach (array_merge($legacyFiados, $legacyPedidosFiado) as $lf) {
            $telefone = preg_replace('/[^0-9]/', '', $lf['telefone_cliente'] ?? '');
            if (empty($telefone)) {
                continue;
            }

            $nome = $lf['nome_cliente'] ?: 'Cliente Não Identificado';
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

            $db->insert('vendas_fiadas', [
                'cliente_id' => $cfId,
                'pedido_id' => $lf['pedido_id'],
                'valor_total' => $lf['valor_pago'],
                'status' => 'pendente',
                'tenant_id' => $tenant['id'],
                'filial_id' => $filial['id'],
                'data_vencimento' => date('Y-m-d', strtotime(($lf['created_at'] ?? date('Y-m-d H:i:s')) . ' + 30 days'))
            ]);

            $db->query("UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?", [$lf['valor_pago'], $cfId]);
        }
    } catch (\Exception $e) {
        error_log("Erro na sincronizacao de fiados: " . $e->getMessage());
    }

    $clientesMap = [];

    $clientesFiado = $db->fetchAll("
        SELECT
            cf.id,
            cf.nome,
            cf.telefone as wpp,
            cf.cpf_cnpj as cpf,
            COALESCE((
                SELECT COUNT(*)::int FROM vendas_fiadas vf
                WHERE vf.cliente_id = cf.id AND vf.status IN ('pendente', 'vencido')
            ), 0) as qtd_pedidos_fiado,
            cf.saldo_devedor,
            cf.limite_credito,
            cf.status,
            cf.cobranca_automatica,
            cf.cobranca_frequencia,
            'clientes_fiado' as origem
        FROM clientes_fiado cf
        WHERE cf.tenant_id = ?
          AND (cf.filial_id = ? OR cf.filial_id IS NULL)
          AND (cf.saldo_devedor > 0 OR cf.cobranca_automatica = true)
        ORDER BY cf.nome ASC
    ", [$tenant['id'], $filial['id']]);

    foreach ($clientesFiado as $cliente) {
        $cliente['status'] = $cliente['status'] ?? 'ativo';
        $cliente['limite_credito'] = $cliente['limite_credito'] ?? 0;
        $cliente['cobranca_automatica'] = $cliente['cobranca_automatica'] ?? false;
        $cliente['cobranca_frequencia'] = $cliente['cobranca_frequencia'] ?? 'semanal';
        $clientesMap['cf_' . $cliente['id']] = $cliente;
    }

    $pedidosFiado = $db->fetchAll("
        SELECT
            ug.id,
            ug.nome,
            ug.telefone as wpp,
            ug.cpf,
            COUNT(DISTINCT p.idpedido) as qtd_pedidos_fiado,
            COALESCE(SUM(p.saldo_devedor), 0) as saldo_devedor,
            0 as limite_credito,
            'ativo' as status,
            false as cobranca_automatica,
            'semanal' as cobranca_frequencia,
            'pedido' as origem
        FROM usuarios_globais ug
        JOIN pedido p ON p.usuario_global_id = ug.id
            AND p.tenant_id = ?
            AND p.filial_id = ?
            AND p.saldo_devedor > 0
        GROUP BY ug.id, ug.nome, ug.telefone, ug.cpf
        ORDER BY ug.nome ASC
    ", [$tenant['id'], $filial['id']]);

    foreach ($pedidosFiado as $cliente) {
        $key = 'ug_' . $cliente['id'];
        if (!isset($clientesMap[$key])) {
            $clientesMap[$key] = $cliente;
            continue;
        }

        $clientesMap[$key]['qtd_pedidos_fiado'] += (int) $cliente['qtd_pedidos_fiado'];
        $clientesMap[$key]['saldo_devedor'] += (float) $cliente['saldo_devedor'];
    }

    $clientes = array_values($clientesMap);
    usort($clientes, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
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
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        /* DataTables bootstrap5: evitar corte de texto nos controles e na 1ª coluna */
        #clientesTabela_wrapper {
            padding: 0 1rem 0.5rem;
            overflow: visible;
        }
        #clientesTabela_wrapper > .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        #clientesTabela_wrapper .dataTables_length label,
        #clientesTabela_wrapper .dataTables_filter label {
            margin-bottom: 0;
        }
        #clientesTabela td:first-child,
        #clientesTabela th:first-child {
            min-width: 220px;
            padding-left: 0.75rem !important;
            white-space: normal;
        }
        .card-fiados-tabela .card-body {
            overflow: visible;
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
                    <div class="card shadow-sm border-0 card-fiados-tabela">
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="clientesTabela" class="table table-hover table-striped align-middle mb-0">
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
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="abrirModalPedidosPagamento(<?= $cliente['id'] ?>, '<?= htmlspecialchars(addslashes($cliente['nome'])) ?>', <?= $cliente['saldo_devedor'] ?>, '<?= $cliente['origem'] ?? 'clientes_fiado' ?>')" title="Ver Pedidos e Receber">
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
                    <input type="hidden" id="pagamentoClienteOrigem" value="clientes_fiado">
                    
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar DataTables se não houver inicializado
            if (typeof jQuery !== 'undefined' && $.fn.DataTable) {
                $('#clientesTabela').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json',
                    },
                    pageLength: 20,
                    lengthMenu: [10, 20, 50, 100],
                    order: [[2, 'desc']], // Ordena por saldo devedor por padrão
                    columnDefs: [
                        { orderable: false, targets: 5 } // Desabilita ordenação na coluna de ações
                    ]
                });
            }
        });

        let modalPagamentoLote = null;

        function abrirModalPedidosPagamento(clienteId, clienteNome, saldoDevedor, origem = 'clientes_fiado') {
            document.getElementById('pagamentoClienteId').value = clienteId;
            document.getElementById('pagamentoClienteOrigem').value = origem;
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
            formData.append('origem', origem);

            fetch('mvc/ajax/vendas_fiadas.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                if (!data.success) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${data.message || 'Erro ao carregar pedidos.'}</td></tr>`;
                    return;
                }
                if (data.vendas && data.vendas.length > 0) {
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

            const origem = document.getElementById('pagamentoClienteOrigem').value;

            const formData = new FormData();
            formData.append('acao', 'pagamento_lote_cliente');
            formData.append('cliente_id', clienteId);
            formData.append('origem', origem);
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
            const formData = new FormData();
            formData.append('cliente_id', clienteId);
            formData.append('cobranca_automatica', cobrancaAtiva ? '1' : '0');
            formData.append('cobranca_frequencia', frequencia);

            fetch('mvc/ajax/configurar_cobranca_ia.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Configurações de cobrança atualizadas!'
                    });
                } else {
                    Swal.fire('Erro', 'Não foi possível atualizar: ' + (data.message || data.error || 'Erro desconhecido'), 'error');
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>
