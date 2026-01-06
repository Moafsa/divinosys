<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

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

// Get pedido ID from URL
$pedidoId = $_GET['pedido_id'] ?? null;

if (!$pedidoId) {
    header('Location: index.php?view=dashboard');
    exit;
}

// Get pedido data
$pedido = null;
if ($tenant && $filial) {
    // Primeiro, buscar o pedido sem join para debug
    $pedidoRaw = $db->fetch(
        "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
        [$pedidoId, $tenant['id'], $filial['id']]
    );
    
    if ($pedidoRaw) {
        // Debug: verificar o idmesa
        error_log('DEBUG FecharPedido: pedido idmesa = ' . $pedidoRaw['idmesa']);
        
        // Buscar a mesa correspondente - tentar diferentes campos
        $mesa = $db->fetch(
            "SELECT numero, id_mesa FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
            [$pedidoRaw['idmesa'], $tenant['id'], $filial['id']]
        );
        
        // Se não encontrou, tentar sem filtro de tenant/filial
        if (!$mesa) {
            $mesa = $db->fetch(
                "SELECT numero, id_mesa FROM mesas WHERE id_mesa = ?",
                [$pedidoRaw['idmesa']]
            );
        }
        
        // Se ainda não encontrou, tentar pelo campo numero
        if (!$mesa) {
            $mesa = $db->fetch(
                "SELECT numero, id_mesa FROM mesas WHERE numero = ? AND tenant_id = ? AND filial_id = ?",
                [$pedidoRaw['idmesa'], $tenant['id'], $filial['id']]
            );
        }
        
        $pedido = $pedidoRaw;
        $pedido['mesa_numero'] = $mesa['numero'] ?? $mesa['id_mesa'] ?? $pedidoRaw['idmesa'] ?? 'N/A';
        
        // Buscar dados do cliente se o pedido tem um cliente associado
        if (!empty($pedidoRaw['usuario_global_id'])) {
            $cliente = $db->fetch(
                "SELECT nome, telefone, email, cpf FROM usuarios_globais WHERE id = ? AND (tipo_usuario = 'cliente' OR tipo_usuario IS NULL)",
                [$pedidoRaw['usuario_global_id']]
            );
            if ($cliente) {
                $pedido['cliente_nome'] = $cliente['nome'];
                $pedido['cliente_telefone'] = $cliente['telefone'];
                $pedido['cliente_email'] = $cliente['email'];
                $pedido['cliente_cpf'] = $cliente['cpf'];
            }
        }
        
        error_log('DEBUG FecharPedido: mesa encontrada = ' . ($mesa ? ($mesa['numero'] ?? $mesa['id_mesa']) : 'NULL'));
    }
}

if (!$pedido) {
    header('Location: index.php?view=dashboard');
    exit;
}

// Get pagamentos do pedido
$pagamentos = [];
if ($tenant && $filial) {
    $pagamentos = $db->fetchAll(
        "SELECT * FROM pagamentos_pedido 
         WHERE pedido_id = ? AND tenant_id = ? AND filial_id = ?
         ORDER BY created_at DESC",
        [$pedidoId, $tenant['id'], $filial['id']]
    );
}

// Get itens do pedido atual
$itensPedido = [];
if ($tenant && $filial) {
    $itensPedido = $db->fetchAll(
        "SELECT pi.*, pr.nome as nome_produto, pr.preco_normal as preco_produto
         FROM pedido_itens pi 
         LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = pi.filial_id
         WHERE pi.pedido_id = ? AND pi.tenant_id = ? AND pi.filial_id = ?
         ORDER BY pi.id",
        [$pedidoId, $tenant['id'], $filial['id']]
    );
    
    // Processar ingredientes para cada item
    foreach ($itensPedido as $key => $item) {
        if (!empty($item['ingredientes_com']) && trim($item['ingredientes_com']) !== '') {
            $itensPedido[$key]['ingredientes_com'] = explode(', ', $item['ingredientes_com']);
        } else {
            $itensPedido[$key]['ingredientes_com'] = [];
        }
        
        if (!empty($item['ingredientes_sem']) && trim($item['ingredientes_sem']) !== '') {
            $itensPedido[$key]['ingredientes_sem'] = explode(', ', $item['ingredientes_sem']);
        } else {
            $itensPedido[$key]['ingredientes_sem'] = [];
        }
    }
}

// Get outros pedidos da mesa (excluindo o pedido atual)
$outrosPedidos = [];
$itensOutrosPedidos = [];
if ($tenant && $filial && $pedido['idmesa']) {
    // Buscar outros pedidos da mesma mesa
    $outrosPedidos = $db->fetchAll(
        "SELECT p.*, m.numero as mesa_numero
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.idmesa = ? AND p.idpedido != ? AND p.tenant_id = ? AND p.filial_id = ?
         AND p.status IN ('Pendente', 'Em Preparo', 'Pronto', 'Entregue', 'Saiu para Entrega')
         AND p.status_pagamento != 'quitado'
         ORDER BY p.created_at ASC",
        [$pedido['idmesa'], $pedidoId, $tenant['id'], $filial['id']]
    );
    
    // Buscar itens dos outros pedidos
    if (!empty($outrosPedidos)) {
        $outrosPedidoIds = array_column($outrosPedidos, 'idpedido');
        $placeholders = str_repeat('?,', count($outrosPedidoIds) - 1) . '?';
        
        error_log('DEBUG: Buscando itens para pedidos: ' . implode(', ', $outrosPedidoIds));
        
        $itensOutrosPedidos = $db->fetchAll(
            "SELECT pi.*, pr.nome as nome_produto, pr.preco_normal as preco_produto, pi.pedido_id
             FROM pedido_itens pi 
             LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = pi.filial_id
             WHERE pi.pedido_id IN ($placeholders) AND pi.tenant_id = ? AND pi.filial_id = ?
             ORDER BY pi.pedido_id, pi.id",
            array_merge($outrosPedidoIds, [$tenant['id'], $filial['id']])
        );
        
        error_log('DEBUG: Encontrados ' . count($itensOutrosPedidos) . ' itens para outros pedidos');
        
        // Processar ingredientes para cada item dos outros pedidos
        foreach ($itensOutrosPedidos as $key => $item) {
            if (!empty($item['ingredientes_com']) && trim($item['ingredientes_com']) !== '') {
                $itensOutrosPedidos[$key]['ingredientes_com'] = explode(', ', $item['ingredientes_com']);
            } else {
                $itensOutrosPedidos[$key]['ingredientes_com'] = [];
            }
            
            if (!empty($item['ingredientes_sem']) && trim($item['ingredientes_sem']) !== '') {
                $itensOutrosPedidos[$key]['ingredientes_sem'] = explode(', ', $item['ingredientes_sem']);
            } else {
                $itensOutrosPedidos[$key]['ingredientes_sem'] = [];
            }
        }
    }
}

// Removido temporariamente para evitar problemas
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Fechar Pedido #<?= $pedido['idpedido'] ?> - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .pedido-header {
            background: linear-gradient(135deg, #6f42c1, #8e44ad);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .valor-total { font-size: 1.5em; font-weight: bold; }
        .saldo-devedor { color: #dc3545; font-weight: bold; font-size: 1.2em; }
        .status-pendente { color: #dc3545; }
        .status-parcial { color: #ffc107; }
        .status-quitado { color: #198754; }
        .pagamento-item {
            border-left: 4px solid #6f42c1;
            padding: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="pedido-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-receipt"></i> Pedido #<?= $pedido['idpedido'] ?></h2>
                            <p class="mb-0">Mesa: <?= $pedido['mesa_numero'] ?? 'N/A' ?> | Cliente: <?= $pedido['cliente'] ?: 'Não informado' ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="index.php?view=dashboard" class="btn btn-light">
                                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Informações do Pedido -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Informações do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p><strong>Status:</strong> <span class="status-<?= $pedido['status_pagamento'] ?>"><?= strtoupper($pedido['status_pagamento']) ?></span></p>
                                <p><strong>Valor Total:</strong> <span class="valor-total">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></span></p>
                            </div>
                            <div class="col-6">
                                <p><strong>Valor Pago:</strong> R$ <?= number_format($pedido['valor_pago'] ?? 0, 2, ',', '.') ?></p>
                                <p><strong>Saldo Devedor:</strong> <span class="saldo-devedor">R$ <?= number_format($pedido['saldo_devedor'] ?? 0, 2, ',', '.') ?></span></p>
                            </div>
                        </div>
                        
                        <!-- Divisão por Pessoas -->
                        <hr>
                        <div class="mb-3">
                            <label class="form-label"><strong>Dividir por Pessoas</strong></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="numeroPessoas" value="1" min="1" oninput="calcularValorPorPessoa()">
                                        <span class="input-group-text">pessoas</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <strong>Valor por pessoa: <span id="valorPorPessoa">R$ <?= number_format($pedido['saldo_devedor'] ?? 0, 2, ',', '.') ?></span></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($pedido['status_pagamento'] !== 'quitado'): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-primary btn-lg w-100" onclick="abrirModalPagamento()">
                                    <i class="fas fa-credit-card"></i> Registrar Pagamento
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-success btn-lg w-100" onclick="fecharMesaCompleta()">
                                    <i class="fas fa-table"></i> Fechar Mesa Completa
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Pedido quitado com sucesso!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Histórico de Pagamentos -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Histórico de Pagamentos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pagamentos)): ?>
                            <p class="text-muted">Nenhum pagamento registrado.</p>
                        <?php else: ?>
                            <?php foreach ($pagamentos as $pagamento): ?>
                                <div class="pagamento-item" style="<?= $pagamento['forma_pagamento'] === 'DESCONTO' ? 'border-left-color: #ffc107; background-color: #fff3cd;' : '' ?>">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong style="<?= $pagamento['forma_pagamento'] === 'DESCONTO' ? 'color: #856404;' : '' ?>">
                                                <?= $pagamento['forma_pagamento'] === 'DESCONTO' ? '-' : '' ?>R$ <?= number_format($pagamento['valor_pago'], 2, ',', '.') ?>
                                            </strong>
                                            <span class="badge <?= $pagamento['forma_pagamento'] === 'DESCONTO' ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-2">
                                                <?= $pagamento['forma_pagamento'] === 'DESCONTO' ? '<i class="fas fa-tag"></i> DESCONTO' : $pagamento['forma_pagamento'] ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($pagamento['created_at'])) ?></small>
                                    </div>
                                    <?php if ($pagamento['nome_cliente']): ?>
                                        <small class="text-muted">Cliente: <?= $pagamento['nome_cliente'] ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($pagamento['descricao']): ?>
                                        <small class="text-muted">Obs: <?= htmlspecialchars($pagamento['descricao']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Itens do Pedido Atual -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Itens do Pedido #<?= $pedido['idpedido'] ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($itensPedido)): ?>
                            <p class="text-muted">Nenhum item encontrado neste pedido.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Qtd</th>
                                            <th>Valor Unit.</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itensPedido as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['nome_produto'] ?? 'Produto não encontrado') ?></strong>
                                                    <?php if (!empty($item['observacao'])): ?>
                                                        <br><small class="text-muted">Obs: <?= htmlspecialchars($item['observacao']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['ingredientes_com'])): ?>
                                                        <br><small class="text-success">+ <?= implode(', ', $item['ingredientes_com']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['ingredientes_sem'])): ?>
                                                        <br><small class="text-danger">- <?= implode(', ', $item['ingredientes_sem']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $item['quantidade'] ?></td>
                                                <td>R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                                                <td><strong>R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outros Pedidos da Mesa -->
        <?php if (!empty($outrosPedidos)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list-alt"></i> Outros Pedidos da Mesa <?= $pedido['mesa_numero'] ?? 'N/A' ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($outrosPedidos as $outroPedido): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-0">
                                                <i class="fas fa-receipt"></i> Pedido #<?= $outroPedido['idpedido'] ?>
                                                <span class="badge bg-<?= $outroPedido['status'] === 'Pendente' ? 'warning' : ($outroPedido['status'] === 'Em Preparo' ? 'info' : 'success') ?> ms-2">
                                                    <?= $outroPedido['status'] ?>
                                                </span>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($outroPedido['created_at'])) ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <strong class="text-primary">R$ <?= number_format($outroPedido['valor_total'], 2, ',', '.') ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Saldo: R$ <?= number_format($outroPedido['saldo_devedor'] ?? 0, 2, ',', '.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php 
                                    // Filtrar itens deste pedido específico
                                    $itensDestePedido = array_filter($itensOutrosPedidos, function($item) use ($outroPedido) {
                                        return $item['pedido_id'] == $outroPedido['idpedido'];
                                    });
                                    
                                    // Debug: verificar se há itens
                                    error_log('DEBUG: Pedido ' . $outroPedido['idpedido'] . ' tem ' . count($itensDestePedido) . ' itens');
                                    ?>
                                    
                                    <?php if (!empty($itensDestePedido)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Qtd</th>
                                                        <th>Valor Unit.</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($itensDestePedido as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($item['nome_produto'] ?? 'Produto não encontrado') ?></strong>
                                                                <?php if (!empty($item['observacao'])): ?>
                                                                    <br><small class="text-muted">Obs: <?= htmlspecialchars($item['observacao']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['ingredientes_com'])): ?>
                                                                    <br><small class="text-success">+ <?= implode(', ', $item['ingredientes_com']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['ingredientes_sem'])): ?>
                                                                    <br><small class="text-danger">- <?= implode(', ', $item['ingredientes_sem']) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= $item['quantidade'] ?></td>
                                                            <td>R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                                                            <td><strong>R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></strong></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <a href="index.php?view=fechar_pedido&pedido_id=<?= $outroPedido['idpedido'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Gerenciar Pedido
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function calcularValorPorPessoa() {
            const numeroPessoas = parseInt(document.getElementById('numeroPessoas').value) || 1;
            
            // Buscar dados atualizados do pedido
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: `action=consultar_saldo_pedido&pedido_id=<?= $pedido['idpedido'] ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const valorTotal = data.pedido.valor_total;
                    const valorPago = data.pedido.valor_pago || 0;
                    const saldoDevedor = data.pedido.saldo_devedor;
                    const valorPorPessoa = saldoDevedor / numeroPessoas;
                    
                    document.getElementById('valorPorPessoa').textContent = 'R$ ' + valorPorPessoa.toFixed(2).replace('.', ',');
                    
                    // Atualizar também os valores na página
                    document.querySelector('.saldo-devedor').textContent = 'R$ ' + saldoDevedor.toFixed(2).replace('.', ',');
                }
            })
            .catch(error => {
                console.error('Erro ao calcular valor por pessoa:', error);
            });
        }

        // Variáveis globais para cálculo de desconto
        let saldoDevedorOriginalMesa = <?= $pedido['saldo_devedor'] ?? $pedido['valor_total'] ?? 0 ?>;
        let descontoAplicadoMesa = 0;

        function calcularDescontoMesa() {
            const valorDescontoInput = document.getElementById('valorDescontoMesa');
            const tipoDescontoSelect = document.getElementById('tipoDescontoMesa');
            const saldoComDescontoSpan = document.getElementById('saldoComDescontoMesa');
            const valorComDescontoDiv = document.getElementById('valorComDescontoMesa');
            const btnSalvarDesconto = document.getElementById('btnSalvarDescontoMesa');
            const valorPagarInput = document.getElementById('valorPagarMesa');

            if (!valorDescontoInput || !tipoDescontoSelect) return;

            const valorDesconto = parseFloat(valorDescontoInput.value) || 0;
            const tipoDesconto = tipoDescontoSelect.value;

            if (valorDesconto <= 0) {
                descontoAplicadoMesa = 0;
                if (saldoComDescontoSpan) {
                    saldoComDescontoSpan.textContent = saldoDevedorOriginalMesa.toFixed(2).replace('.', ',');
                }
                if (valorComDescontoDiv) {
                    valorComDescontoDiv.style.display = 'none';
                }
                if (btnSalvarDesconto) {
                    btnSalvarDesconto.disabled = true;
                }
                if (valorPagarInput) {
                    valorPagarInput.max = saldoDevedorOriginalMesa.toFixed(2);
                    valorPagarInput.value = saldoDevedorOriginalMesa.toFixed(2);
                }
                return;
            }

            // Calcular desconto
            if (tipoDesconto === 'percentual') {
                descontoAplicadoMesa = saldoDevedorOriginalMesa * (valorDesconto / 100);
            } else {
                descontoAplicadoMesa = valorDesconto;
            }

            // Limitar desconto ao saldo devedor
            if (descontoAplicadoMesa > saldoDevedorOriginalMesa) {
                descontoAplicadoMesa = saldoDevedorOriginalMesa;
                valorDescontoInput.value = tipoDesconto === 'percentual' ? '100' : saldoDevedorOriginalMesa.toFixed(2);
            }

            const saldoComDesconto = saldoDevedorOriginalMesa - descontoAplicadoMesa;

            // Atualizar exibição
            if (saldoComDescontoSpan) {
                saldoComDescontoSpan.textContent = saldoComDesconto.toFixed(2).replace('.', ',');
            }
            if (valorComDescontoDiv) {
                valorComDescontoDiv.style.display = 'block';
            }
            if (btnSalvarDesconto) {
                btnSalvarDesconto.disabled = false;
            }
            if (valorPagarInput) {
                valorPagarInput.max = saldoComDesconto.toFixed(2);
                valorPagarInput.value = saldoComDesconto.toFixed(2);
            }

            // Se saldo = 0, mostrar opção de fechar automaticamente
            if (saldoComDesconto <= 0.01) {
                if (valorComDescontoDiv) {
                    valorComDescontoDiv.innerHTML = `
                        <p class="text-success"><strong>Saldo Devedor com Desconto:</strong> R$ 0,00</p>
                        <p class="text-warning"><strong>⚠️ O desconto cobre todo o valor. Ao salvar, a mesa será fechada automaticamente.</strong></p>
                    `;
                }
            }
        }

        function salvarDescontoMesa() {
            const valorDescontoInput = document.getElementById('valorDescontoMesa');
            const tipoDescontoSelect = document.getElementById('tipoDescontoMesa');
            const descricaoInput = document.getElementById('descricao');

            if (!valorDescontoInput || !tipoDescontoSelect) return;

            const valorDesconto = parseFloat(valorDescontoInput.value) || 0;
            const tipoDesconto = tipoDescontoSelect.value;

            if (valorDesconto <= 0) {
                Swal.fire('Atenção', 'Informe um valor de desconto válido', 'warning');
                return;
            }

            // Calcular desconto aplicado
            let descontoAplicado = 0;
            if (tipoDesconto === 'percentual') {
                descontoAplicado = saldoDevedorOriginalMesa * (valorDesconto / 100);
            } else {
                descontoAplicado = valorDesconto;
            }

            if (descontoAplicado > saldoDevedorOriginalMesa) {
                descontoAplicado = saldoDevedorOriginalMesa;
            }

            const saldoComDesconto = saldoDevedorOriginalMesa - descontoAplicado;

            const formData = new URLSearchParams();
            formData.append('action', 'aplicar_desconto_mesa');
            formData.append('mesa_id', <?= $pedido['idmesa'] ?>);
            formData.append('valor_desconto', descontoAplicado);
            formData.append('tipo_desconto', tipoDesconto);
            formData.append('valor_desconto_original', valorDesconto);
            formData.append('descricao', descricaoInput ? descricaoInput.value : '');

            Swal.fire({
                title: 'Salvando desconto...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('mvc/ajax/mesa_pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (saldoComDesconto <= 0.01) {
                        // Fechar mesa automaticamente
                        Swal.fire({
                            icon: 'success',
                            title: 'Desconto aplicado!',
                            text: 'O desconto cobre todo o valor. Fechando a mesa automaticamente...',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Desconto salvo!',
                            text: `Desconto de R$ ${descontoAplicado.toFixed(2).replace('.', ',')} aplicado com sucesso. Saldo restante: R$ ${saldoComDesconto.toFixed(2).replace('.', ',')}`,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    }
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao salvar desconto', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar desconto', 'error');
            });
        }

        function fecharMesaCompleta() {
            // Buscar saldo devedor total da mesa
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: `action=consultar_saldo_mesa&mesa_id=<?= $pedido['idmesa'] ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const valorTotalMesa = data.valor_total_mesa;
                    const saldoDevedorMesa = data.saldo_devedor_mesa;
                    const valorPagoMesa = valorTotalMesa - saldoDevedorMesa;
                    const numeroPessoas = parseInt(document.getElementById('numeroPessoas').value) || 1;
                    const valorPorPessoa = saldoDevedorMesa / numeroPessoas;
                    
                    abrirModalFecharMesa(valorTotalMesa, valorPagoMesa, saldoDevedorMesa, valorPorPessoa, numeroPessoas);
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao buscar dados da mesa', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar saldo da mesa:', error);
                Swal.fire('Erro!', 'Erro ao buscar informações da mesa', 'error');
            });
        }

        function abrirModalFecharMesa(valorTotalMesa, valorPagoMesa, saldoDevedorMesa, valorPorPessoa, numeroPessoas) {
            Swal.fire({
                title: 'Fechar Mesa Completa',
                html: `
                    <div class="mb-3">
                        <p><strong>Valor Total da Mesa:</strong> R$ ${valorTotalMesa.toFixed(2).replace('.', ',')}</p>
                        <p><strong>Já Pago:</strong> R$ ${valorPagoMesa.toFixed(2).replace('.', ',')}</p>
                        <p class="text-danger"><strong>Saldo Devedor:</strong> R$ <span id="saldoDevedorOriginalMesa">${saldoDevedorMesa.toFixed(2).replace('.', ',')}</span></p>
                        <p class="text-info"><strong>Valor por Pessoa (${numeroPessoas} pessoas):</strong> R$ ${valorPorPessoa.toFixed(2).replace('.', ',')}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Desconto (opcional)</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorDescontoMesa" step="0.01" min="0" max="${saldoDevedorMesa.toFixed(2)}" value="0" placeholder="0,00" oninput="calcularDescontoMesa()">
                                </div>
                            </div>
                            <div class="col-4">
                                <select class="form-select" id="tipoDescontoMesa" onchange="calcularDescontoMesa()">
                                    <option value="valor_fixo">Valor Fixo</option>
                                    <option value="percentual">Percentual</option>
                                </select>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-primary w-100" onclick="salvarDescontoMesa()" id="btnSalvarDescontoMesa" disabled>
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Desconto será aplicado sobre o valor total a pagar</small>
                        <div id="valorComDescontoMesa" class="mt-2" style="display: none;">
                            <p class="text-success"><strong>Saldo Devedor com Desconto:</strong> R$ <span id="saldoComDescontoMesa">0,00</span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamentoMesa" required onchange="toggleFiadoFieldsMesa(); togglePixFaturaButtonMesa()">
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                            <option value="FIADO">FIADO</option>
                        </select>
                    </div>
                       <div class="mb-3">
                           <label class="form-label">Valor a Pagar</label>
                           <div class="input-group">
                               <span class="input-group-text">R$</span>
                               <input type="number" class="form-control" id="valorPagarMesa" step="0.01" min="0" max="${saldoDevedorMesa.toFixed(2)}" value="${saldoDevedorMesa.toFixed(2)}" required>
                               <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagarMesa').value = document.getElementById('saldoComDescontoMesa') ? parseFloat(document.getElementById('saldoComDescontoMesa').textContent.replace(',', '.')) || ${saldoDevedorMesa.toFixed(2)} : ${saldoDevedorMesa.toFixed(2)}">Saldo Total</button>
                           </div>
                           <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
                       </div>
                    <div id="pixFaturaButtonContainerMesa" class="mb-3" style="display: none;">
                        <button type="button" class="btn btn-success w-100" onclick="gerarFaturaPixMesa()">
                            <i class="fas fa-qrcode me-2"></i>Gerar Fatura de Pagamento por PIX
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do Cliente (opcional)</label>
                        <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente" value="<?= htmlspecialchars($pedido['cliente_nome'] ?? $pedido['cliente'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999" value="<?= htmlspecialchars($pedido['cliente_telefone'] ?? $pedido['telefone_cliente'] ?? '') ?>" onblur="buscarClientePorTelefone('modalFecharMesa')" oninput="debounceSearch()">
                            <button class="btn btn-outline-secondary" type="button" onclick="buscarClientePorTelefone('modalFecharMesa')">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <small class="text-muted">Digite o telefone (busca automática) ou clique no botão para buscar</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" rows="2" placeholder="Observações sobre o fechamento da mesa..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Fechar Mesa',
                cancelButtonText: 'Cancelar',
                width: '500px',
                didOpen: () => {
                    setTimeout(() => {
                        const valorInput = document.getElementById('valorPagar');
                        if (valorInput) {
                            valorInput.focus();
                            valorInput.select();
                        }
                    }, 300);
                },
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamentoMesa').value;
                    const valorPagar = parseFloat(document.getElementById('valorPagarMesa').value) || 0;
                    const valorDesconto = parseFloat(document.getElementById('valorDescontoMesa').value) || 0;
                    const tipoDesconto = document.getElementById('tipoDescontoMesa').value;
                    const nomeCliente = document.getElementById('nomeCliente').value;
                    const telefoneCliente = document.getElementById('telefoneCliente').value;
                    const descricao = document.getElementById('descricao').value;
                    
                    // Calcular saldo com desconto
                    let descontoAplicado = 0;
                    if (valorDesconto > 0) {
                        if (tipoDesconto === 'percentual') {
                            descontoAplicado = saldoDevedorMesa * (valorDesconto / 100);
                        } else {
                            descontoAplicado = valorDesconto;
                        }
                        if (descontoAplicado > saldoDevedorMesa) {
                            descontoAplicado = saldoDevedorMesa;
                        }
                    }
                    const saldoComDesconto = saldoDevedorMesa - descontoAplicado;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    if (valorPagar <= 0) {
                        Swal.showValidationMessage('Valor deve ser maior que zero');
                        return false;
                    }
                    
                    if (valorPagar > saldoComDesconto + 0.01) {
                        Swal.showValidationMessage(`Valor não pode ser maior que o saldo devedor com desconto (R$ ${saldoComDesconto.toFixed(2).replace('.', ',')})`);
                        return false;
                    }
                    
                    // Validação específica para FIADO
                    if (formaPagamento === 'FIADO') {
                        if (!nomeCliente || nomeCliente.trim() === '') {
                            Swal.showValidationMessage('Nome do cliente é obrigatório para pagamento fiado');
                            return false;
                        }
                        if (!telefoneCliente || telefoneCliente.trim() === '') {
                            Swal.showValidationMessage('Telefone do cliente é obrigatório para pagamento fiado');
                            return false;
                        }
                    }
                    
                    return { formaPagamento, valorPagar, valorDesconto, tipoDesconto, nomeCliente, telefoneCliente, descricao, saldoComDesconto };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    registrarPagamentoMesa(result.value);
                }
            });
        }

        function toggleFiadoFieldsMesa() {
            const formaPagamentoElement = document.getElementById('formaPagamentoMesa');
            if (!formaPagamentoElement) {
                return; // Element not found, exit early
            }
            
            const formaPagamento = formaPagamentoElement.value;
            const nomeClienteField = document.getElementById('nomeClienteMesa');
            const telefoneClienteField = document.getElementById('telefoneClienteMesa');
            
            // Only proceed if both fields exist
            if (!nomeClienteField || !telefoneClienteField) {
                return; // Fields not found, exit early
            }
            
            if (formaPagamento === 'FIADO') {
                nomeClienteField.required = true;
                telefoneClienteField.required = true;
                nomeClienteField.placeholder = 'Nome obrigatório para pagamento fiado';
                telefoneClienteField.placeholder = 'Telefone obrigatório para pagamento fiado';
            } else {
                nomeClienteField.required = false;
                telefoneClienteField.required = false;
                nomeClienteField.placeholder = 'Cliente Mesa';
                telefoneClienteField.placeholder = 'Telefone (opcional)';
            }
        }

        function registrarPagamentoMesa(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'registrar_pagamento_mesa');
            formData.append('mesa_id', <?= $pedido['idmesa'] ?>);
            formData.append('forma_pagamento', dados.formaPagamento);
            formData.append('valor_pago', dados.valorPagar);
            formData.append('valor_desconto', dados.valorDesconto);
            formData.append('tipo_desconto', dados.tipoDesconto);
            formData.append('nome_cliente', dados.nomeCliente);
            formData.append('telefone_cliente', dados.telefoneCliente);
            formData.append('descricao', dados.descricao);
            
            // Add client data for processing
            if (dados.nomeCliente || dados.telefoneCliente) {
                formData.append('dados_cliente', JSON.stringify({
                    nome: dados.nomeCliente,
                    telefone: dados.telefoneCliente
                }));
            }
            
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.mesa_liberada) {
                        Swal.fire('Sucesso!', 'Mesa fechada e liberada com sucesso!', 'success').then(() => {
                            window.location.href = 'index.php?view=dashboard';
                        });
                    } else if (data.saldo_restante !== undefined) {
                        Swal.fire('Sucesso!', `Pagamento registrado! Saldo restante da mesa: R$ ${data.saldo_restante.toFixed(2).replace('.', ',')}`, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Sucesso!', 'Pagamento registrado com sucesso!', 'success').then(() => {
                            location.reload();
                        });
                    }
                } else {
                    Swal.fire('Erro!', data.message || data.error || 'Erro desconhecido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar pagamento', 'error');
            });
        }

        // Variáveis globais para cálculo de desconto do pedido individual
        // Usar saldo_devedor do banco que já considera descontos aplicados
        let saldoDevedorOriginalPedido = <?= $pedido['saldo_devedor'] ?? (($pedido['valor_total'] ?? 0) - ($pedido['valor_pago'] ?? 0)) ?>;
        let descontoAplicadoPedido = 0;

        function calcularDesconto() {
            const valorDescontoInput = document.getElementById('valorDesconto');
            const tipoDescontoSelect = document.getElementById('tipoDesconto');
            const saldoComDescontoSpan = document.getElementById('saldoComDesconto');
            const valorComDescontoDiv = document.getElementById('valorComDesconto');
            const btnSalvarDesconto = document.getElementById('btnSalvarDesconto');
            const valorPagarInput = document.getElementById('valorPagar');

            if (!valorDescontoInput || !tipoDescontoSelect) return;

            const valorDesconto = parseFloat(valorDescontoInput.value) || 0;
            const tipoDesconto = tipoDescontoSelect.value;

            if (valorDesconto <= 0) {
                descontoAplicadoPedido = 0;
                if (saldoComDescontoSpan) {
                    saldoComDescontoSpan.textContent = saldoDevedorOriginalPedido.toFixed(2).replace('.', ',');
                }
                if (valorComDescontoDiv) {
                    valorComDescontoDiv.style.display = 'none';
                }
                if (btnSalvarDesconto) {
                    btnSalvarDesconto.disabled = true;
                }
                if (valorPagarInput) {
                    valorPagarInput.max = saldoDevedorOriginalPedido.toFixed(2);
                    valorPagarInput.value = saldoDevedorOriginalPedido.toFixed(2);
                }
                return;
            }

            // Calcular desconto
            if (tipoDesconto === 'percentual') {
                descontoAplicadoPedido = saldoDevedorOriginalPedido * (valorDesconto / 100);
            } else {
                descontoAplicadoPedido = valorDesconto;
            }

            // Limitar desconto ao saldo devedor
            if (descontoAplicadoPedido > saldoDevedorOriginalPedido) {
                descontoAplicadoPedido = saldoDevedorOriginalPedido;
                valorDescontoInput.value = tipoDesconto === 'percentual' ? '100' : saldoDevedorOriginalPedido.toFixed(2);
            }

            const saldoComDesconto = saldoDevedorOriginalPedido - descontoAplicadoPedido;

            // Atualizar exibição
            if (saldoComDescontoSpan) {
                saldoComDescontoSpan.textContent = saldoComDesconto.toFixed(2).replace('.', ',');
            }
            if (valorComDescontoDiv) {
                valorComDescontoDiv.style.display = 'block';
            }
            if (btnSalvarDesconto) {
                btnSalvarDesconto.disabled = false;
            }
            if (valorPagarInput) {
                valorPagarInput.max = saldoComDesconto.toFixed(2);
                valorPagarInput.value = saldoComDesconto.toFixed(2);
            }

            // Se saldo = 0, mostrar opção de fechar automaticamente
            if (saldoComDesconto <= 0.01) {
                if (valorComDescontoDiv) {
                    valorComDescontoDiv.innerHTML = `
                        <p class="text-success"><strong>Saldo Devedor com Desconto:</strong> R$ 0,00</p>
                        <p class="text-warning"><strong>⚠️ O desconto cobre todo o valor. Ao salvar, o pedido será fechado automaticamente.</strong></p>
                    `;
                }
            }
        }

        function salvarDesconto() {
            const valorDescontoInput = document.getElementById('valorDesconto');
            const tipoDescontoSelect = document.getElementById('tipoDesconto');
            const descricaoInput = document.getElementById('descricao');

            if (!valorDescontoInput || !tipoDescontoSelect) return;

            const valorDesconto = parseFloat(valorDescontoInput.value) || 0;
            const tipoDesconto = tipoDescontoSelect.value;

            if (valorDesconto <= 0) {
                Swal.fire('Atenção', 'Informe um valor de desconto válido', 'warning');
                return;
            }

            // Calcular desconto aplicado
            let descontoAplicado = 0;
            if (tipoDesconto === 'percentual') {
                descontoAplicado = saldoDevedorOriginalPedido * (valorDesconto / 100);
            } else {
                descontoAplicado = valorDesconto;
            }

            if (descontoAplicado > saldoDevedorOriginalPedido) {
                descontoAplicado = saldoDevedorOriginalPedido;
            }

            const saldoComDesconto = saldoDevedorOriginalPedido - descontoAplicado;

            const formData = new URLSearchParams();
            formData.append('action', 'aplicar_desconto_pedido');
            formData.append('pedido_id', <?= $pedido['idpedido'] ?>);
            formData.append('valor_desconto', descontoAplicado);
            formData.append('tipo_desconto', tipoDesconto);
            formData.append('valor_desconto_original', valorDesconto);
            formData.append('descricao', descricaoInput ? descricaoInput.value : '');

            // Mostrar loading sem fechar o modal de pagamento
            const loadingToast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            loadingToast.fire({
                icon: 'info',
                title: 'Salvando desconto...'
            });

            fetch('mvc/ajax/mesa_pedidos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar saldo devedor original com o novo valor retornado pelo backend
                    saldoDevedorOriginalPedido = parseFloat(data.saldo_restante) || saldoComDesconto;
                    
                    console.log('Saldo devedor atualizado:', saldoDevedorOriginalPedido);
                    
                    // Atualizar o saldo devedor no modal se estiver aberto
                    const saldoDevedorSpan = document.getElementById('saldoDevedorOriginal');
                    if (saldoDevedorSpan) {
                        saldoDevedorSpan.textContent = saldoDevedorOriginalPedido.toFixed(2).replace('.', ',');
                        console.log('Saldo devedor no modal atualizado');
                    } else {
                        console.log('Elemento saldoDevedorOriginal não encontrado');
                    }
                    
                    // Atualizar o saldo com desconto no modal
                    const saldoComDescontoSpan = document.getElementById('saldoComDesconto');
                    if (saldoComDescontoSpan) {
                        saldoComDescontoSpan.textContent = saldoDevedorOriginalPedido.toFixed(2).replace('.', ',');
                    }
                    
                    // Atualizar o valor a pagar no modal
                    const valorPagarInput = document.getElementById('valorPagar');
                    if (valorPagarInput) {
                        valorPagarInput.max = saldoDevedorOriginalPedido.toFixed(2);
                        valorPagarInput.value = saldoDevedorOriginalPedido.toFixed(2);
                        console.log('Valor a pagar atualizado:', valorPagarInput.value);
                    }
                    
                    // Atualizar o saldo devedor na página principal
                    const saldoDevedorPage = document.querySelector('.saldo-devedor');
                    if (saldoDevedorPage) {
                        saldoDevedorPage.textContent = 'R$ ' + saldoDevedorOriginalPedido.toFixed(2).replace('.', ',');
                        console.log('Saldo devedor na página atualizado');
                    }
                    
                    // Atualizar também o valor por pessoa se existir
                    const valorPorPessoaSpan = document.getElementById('valorPorPessoa');
                    if (valorPorPessoaSpan) {
                        const numeroPessoas = parseInt(document.getElementById('numeroPessoas')?.value || 1);
                        const valorPorPessoa = saldoDevedorOriginalPedido / numeroPessoas;
                        valorPorPessoaSpan.textContent = 'R$ ' + valorPorPessoa.toFixed(2).replace('.', ',');
                    }
                    
                    // Limpar o campo de desconto e recalcular
                    if (valorDescontoInput) {
                        valorDescontoInput.value = '0';
                        // Chamar calcularDesconto após um pequeno delay para garantir que o DOM foi atualizado
                        setTimeout(() => {
                            calcularDesconto();
                        }, 100);
                    }
                    
                    // Desabilitar botão de salvar desconto
                    const btnSalvarDesconto = document.getElementById('btnSalvarDesconto');
                    if (btnSalvarDesconto) {
                        btnSalvarDesconto.disabled = true;
                    }
                    
                    // Ocultar valor com desconto
                    const valorComDescontoDiv = document.getElementById('valorComDesconto');
                    if (valorComDescontoDiv) {
                        valorComDescontoDiv.style.display = 'none';
                    }
                    
                    if (saldoComDesconto <= 0.01) {
                        // Fechar pedido automaticamente
                        Swal.fire({
                            icon: 'success',
                            title: 'Desconto aplicado!',
                            text: 'O desconto cobre todo o valor. Fechando o pedido automaticamente...',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        // Mostrar toast de sucesso sem fechar o modal
                        loadingToast.fire({
                            icon: 'success',
                            title: `Desconto de R$ ${descontoAplicado.toFixed(2).replace('.', ',')} aplicado! Saldo: R$ ${saldoDevedorOriginalPedido.toFixed(2).replace('.', ',')}`
                        });
                    }
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao salvar desconto', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar desconto', 'error');
            });
        }

        function abrirModalPagamento() {
            const valorTotal = <?= $pedido['valor_total'] ?>;
            const valorPago = <?= $pedido['valor_pago'] ?? 0 ?>;
            // Usar saldo_devedor do banco que já considera descontos aplicados
            const saldoDevedor = <?= $pedido['saldo_devedor'] ?? ($pedido['valor_total'] - ($pedido['valor_pago'] ?? 0)) ?>;
            saldoDevedorOriginalPedido = saldoDevedor;
            
            Swal.fire({
                title: 'Registrar Pagamento',
                html: `
                    <div class="mb-3">
                        <p><strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2).replace('.', ',')}</p>
                        <p><strong>Já Pago:</strong> R$ ${valorPago.toFixed(2).replace('.', ',')}</p>
                        <p class="text-danger"><strong>Saldo Devedor:</strong> R$ <span id="saldoDevedorOriginal">${saldoDevedor.toFixed(2).replace('.', ',')}</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Desconto (opcional)</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorDesconto" step="0.01" min="0" max="${saldoDevedor.toFixed(2)}" value="0" placeholder="0,00" oninput="calcularDesconto()">
                                </div>
                            </div>
                            <div class="col-4">
                                <select class="form-select" id="tipoDesconto" onchange="calcularDesconto()">
                                    <option value="valor_fixo">Valor Fixo</option>
                                    <option value="percentual">Percentual</option>
                                </select>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-primary w-100" onclick="salvarDesconto()" id="btnSalvarDesconto" disabled>
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Desconto será aplicado sobre o valor total a pagar</small>
                        <div id="valorComDesconto" class="mt-2" style="display: none;">
                            <p class="text-success"><strong>Saldo Devedor com Desconto:</strong> R$ <span id="saldoComDesconto">0,00</span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento" required onchange="toggleFiadoFields(); togglePixFaturaButton()">
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão Débito">Cartão Débito</option>
                            <option value="Cartão Crédito">Cartão Crédito</option>
                            <option value="PIX">PIX</option>
                            <option value="FIADO">FIADO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor a Pagar</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valorPagar" step="0.01" min="0" max="${saldoDevedor.toFixed(2)}" value="${saldoDevedor.toFixed(2)}" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagar').value = document.getElementById('saldoComDesconto') ? parseFloat(document.getElementById('saldoComDesconto').textContent.replace(',', '.')) || ${saldoDevedor.toFixed(2)} : ${saldoDevedor.toFixed(2)}">Saldo Total</button>
                        </div>
                        <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
                    </div>
                    <div id="pixFaturaButtonContainer" class="mb-3" style="display: none;">
                        <button type="button" class="btn btn-success w-100" onclick="gerarFaturaPix()">
                            <i class="fas fa-qrcode me-2"></i>Gerar Fatura de Pagamento por PIX
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome do Cliente (opcional)</label>
                        <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente" value="<?= htmlspecialchars($pedido['cliente_nome'] ?? $pedido['cliente'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999" value="<?= htmlspecialchars($pedido['cliente_telefone'] ?? $pedido['telefone_cliente'] ?? '') ?>" onblur="buscarClientePorTelefone('modalPagamento')" oninput="debounceSearch()">
                            <button class="btn btn-outline-secondary" type="button" onclick="buscarClientePorTelefone('modalPagamento')">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <small class="text-muted">Digite o telefone (busca automática) ou clique no botão para buscar</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" rows="2" placeholder="Observações sobre o pagamento..."></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="gerarNotaFiscal" onchange="toggleNotaFiscalOptions()">
                            <label class="form-check-label" for="gerarNotaFiscal">
                                <i class="fas fa-receipt me-1"></i>
                                Gerar Nota Fiscal
                            </label>
                        </div>
                    </div>
                    <div id="notaFiscalOptions" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Valor da Nota Fiscal</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="valorNotaFiscal" step="0.01" min="0" value="${valorTotal.toFixed(2)}">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorNotaFiscal').value = '${valorTotal.toFixed(2)}'">Valor Total</button>
                            </div>
                            <small class="text-muted">Valor que aparecerá na nota fiscal (pode ser diferente do valor do pedido)</small>
                        </div>
                        
                        <!-- Dados Fiscais do Cliente -->
            <div class="mb-3">
                <label class="form-label">Dados Fiscais do Cliente <span class="text-muted">(Opcional)</span></label>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label small">CPF</label>
                        <input type="text" class="form-control" id="clienteCpf" placeholder="000.000.000-00" maxlength="14" oninput="formatarCPF(this)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">CNPJ</label>
                        <input type="text" class="form-control" id="clienteCnpj" placeholder="00.000.000/0000-00" maxlength="18" oninput="formatarCNPJ(this)">
                    </div>
                </div>
                <small class="text-muted">Informe CPF ou CNPJ para melhor identificação fiscal (recomendado mas não obrigatório)</small>
            </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enviarWhatsApp" checked>
                                <label class="form-check-label" for="enviarWhatsApp">
                                    <i class="fab fa-whatsapp me-1"></i>
                                    Enviar por WhatsApp
                                </label>
                            </div>
                            <small class="text-muted">A nota fiscal será enviada automaticamente para o cliente via WhatsApp</small>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar Pagamento',
                cancelButtonText: 'Cancelar',
                width: '500px',
                didOpen: () => {
                    setTimeout(() => {
                        const valorInput = document.getElementById('valorPagar');
                        if (valorInput) {
                            valorInput.focus();
                            valorInput.select();
                        }
                    }, 300);
                },
                preConfirm: () => {
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const valorPagar = parseFloat(document.getElementById('valorPagar').value) || 0;
                    const valorDesconto = parseFloat(document.getElementById('valorDesconto').value) || 0;
                    const tipoDesconto = document.getElementById('tipoDesconto').value;
                    const nomeCliente = document.getElementById('nomeCliente').value;
                    const telefoneCliente = document.getElementById('telefoneCliente').value;
                    const descricao = document.getElementById('descricao').value;
                    const gerarNotaFiscal = document.getElementById('gerarNotaFiscal').checked;
                    const valorNotaFiscal = parseFloat(document.getElementById('valorNotaFiscal').value) || 0;
                    const enviarWhatsApp = document.getElementById('enviarWhatsApp').checked;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    if (valorPagar <= 0) {
                        Swal.showValidationMessage('Valor deve ser maior que zero');
                        return false;
                    }
                    
                    // Calcular saldo com desconto
                    let descontoAplicado = 0;
                    if (valorDesconto > 0) {
                        if (tipoDesconto === 'percentual') {
                            descontoAplicado = saldoDevedor * (valorDesconto / 100);
                        } else {
                            descontoAplicado = valorDesconto;
                        }
                        if (descontoAplicado > saldoDevedor) {
                            descontoAplicado = saldoDevedor;
                        }
                    }
                    const saldoComDesconto = saldoDevedor - descontoAplicado;
                    
                    if (valorPagar > saldoComDesconto + 0.01) {
                        Swal.showValidationMessage(`Valor não pode ser maior que o saldo devedor com desconto (R$ ${saldoComDesconto.toFixed(2).replace('.', ',')})`);
                        return false;
                    }
                    
                    // Validação específica para FIADO
                    if (formaPagamento === 'FIADO') {
                        if (!nomeCliente || nomeCliente.trim() === '') {
                            Swal.showValidationMessage('Nome do cliente é obrigatório para pagamento fiado');
                            return false;
                        }
                        if (!telefoneCliente || telefoneCliente.trim() === '') {
                            Swal.showValidationMessage('Telefone do cliente é obrigatório para pagamento fiado');
                            return false;
                        }
                    }
                    
                    // Validação da nota fiscal
                    if (gerarNotaFiscal) {
                        if (valorNotaFiscal <= 0) {
                            Swal.showValidationMessage('Valor da nota fiscal deve ser maior que zero');
                            return false;
                        }
                        
                        const clienteCpf = document.getElementById('clienteCpf').value.trim();
                        const clienteCnpj = document.getElementById('clienteCnpj').value.trim();
                        
                        // CPF/CNPJ é opcional para notas fiscais (depende do município)
                        // Removida validação obrigatória
                        
                        if (clienteCpf && clienteCnpj) {
                            Swal.showValidationMessage('Informe apenas CPF ou CNPJ, não ambos');
                            return false;
                        }
                        
                        if (enviarWhatsApp && (!telefoneCliente || telefoneCliente.trim() === '')) {
                            Swal.showValidationMessage('Telefone é obrigatório para envio da nota fiscal por WhatsApp');
                            return false;
                        }
                    }
                    
                    return {
                        formaPagamento,
                        valorPagar,
                        valorDesconto,
                        tipoDesconto,
                        nomeCliente,
                        telefoneCliente,
                        descricao,
                        gerarNotaFiscal,
                        valorNotaFiscal,
                        enviarWhatsApp,
                        clienteCpf: document.getElementById('clienteCpf').value.trim(),
                        clienteCnpj: document.getElementById('clienteCnpj').value.trim()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    registrarPagamento(result.value);
                }
            });
        }

        function toggleNotaFiscalOptions() {
            const gerarNotaFiscal = document.getElementById('gerarNotaFiscal').checked;
            const notaFiscalOptions = document.getElementById('notaFiscalOptions');
            
            if (gerarNotaFiscal) {
                notaFiscalOptions.style.display = 'block';
            } else {
                notaFiscalOptions.style.display = 'none';
            }
        }

        function toggleFiadoFields() {
            const formaPagamento = document.getElementById('formaPagamento').value;
            const nomeClienteField = document.getElementById('nomeCliente');
            const telefoneClienteField = document.getElementById('telefoneCliente');
            
            if (formaPagamento === 'FIADO') {
                nomeClienteField.required = true;
                telefoneClienteField.required = true;
                nomeClienteField.placeholder = 'Nome obrigatório para pagamento fiado';
                telefoneClienteField.placeholder = 'Telefone obrigatório para pagamento fiado';
            } else {
                nomeClienteField.required = false;
                telefoneClienteField.required = false;
                nomeClienteField.placeholder = 'Cliente Mesa';
                telefoneClienteField.placeholder = 'Telefone (opcional)';
            }
        }

        function togglePixFaturaButton() {
            const formaPagamento = document.getElementById('formaPagamento').value;
            const pixFaturaContainer = document.getElementById('pixFaturaButtonContainer');
            
            if (formaPagamento === 'PIX') {
                pixFaturaContainer.style.display = 'block';
            } else {
                pixFaturaContainer.style.display = 'none';
            }
        }

        function togglePixFaturaButtonMesa() {
            const formaPagamento = document.getElementById('formaPagamentoMesa').value;
            const pixFaturaContainer = document.getElementById('pixFaturaButtonContainerMesa');
            
            if (formaPagamento === 'PIX') {
                pixFaturaContainer.style.display = 'block';
            } else {
                pixFaturaContainer.style.display = 'none';
            }
        }

        window.lastGeneratedPixInvoice = null;
        const PIX_INVOICE_TTL = 5 * 60 * 1000; // keep a generated invoice remembered for 5 minutes

        function setLastPixInvoice(payload) {
            window.lastGeneratedPixInvoice = {
                type: payload.type,
                pedidoId: payload.pedido_id ?? null,
                mesaId: payload.mesa_id ?? null,
                valor: Number(payload.valor || 0).toFixed(2),
                timestamp: Date.now()
            };
        }

        function clearLastPixInvoice() {
            window.lastGeneratedPixInvoice = null;
        }

        function shouldSkipPixInvoice(payload) {
            const record = window.lastGeneratedPixInvoice;
            if (!record) {
                return false;
            }

            if (record.type !== payload.type) {
                return false;
            }

            if (payload.pedido_id && String(record.pedidoId) !== String(payload.pedido_id)) {
                return false;
            }

            if (payload.mesa_id && String(record.mesaId) !== String(payload.mesa_id)) {
                return false;
            }

            if (Number(record.valor).toFixed(2) !== Number(payload.valor || 0).toFixed(2)) {
                return false;
            }

            if (Date.now() - record.timestamp > PIX_INVOICE_TTL) {
                clearLastPixInvoice();
                return false;
            }

            return true;
        }

        function requestPixInvoice(action, payload) {
            // All API calls are now done via PHP endpoints (no CORS issues)
            // Step 1: Ensure customer exists in Asaas (or create if not exists)
            // Step 2: Create payment in Asaas and get PIX QR Code
            // Step 3: Save payment to database
            
            // Get client data from order if not provided in payload
            const clienteNome = payload.nome_cliente || <?= json_encode($pedido['cliente'] ?? 'Cliente'); ?>;
            const clienteTelefone = payload.telefone_cliente || <?= json_encode($pedido['telefone_cliente'] ?? ''); ?>;
            
            // Prepare customer data
            const customerData = {
                nome_cliente: clienteNome || 'Cliente',
                telefone_cliente: clienteTelefone || '',
                email_cliente: payload.email_cliente || '',
                cpf_cnpj: payload.cpf_cnpj || '',
                external_reference: payload.external_reference || (action === 'gerar_fatura_pix' ? `PED-${payload.pedido_id}` : `MESA-${payload.mesa_id}`)
            };
            
            // Step 1: Ensure customer exists in Asaas
            const customerBody = new URLSearchParams();
            customerBody.append('action', 'ensureAsaasCustomer');
            Object.keys(customerData).forEach(key => {
                if (customerData[key] !== undefined && customerData[key] !== null) {
                    customerBody.append(key, customerData[key]);
                }
            });
            
            return fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: customerBody
            })
            .then(response => response.json())
            .then(customerResult => {
                if (!customerResult.success) {
                    throw new Error(customerResult.error || customerResult.message || 'Erro ao criar/encontrar cliente no Asaas');
                }
                
                const customerId = customerResult.customer_id;
                
                // Step 2: Create payment in Asaas
                const paymentData = {
                    customer_id: customerId,
                    valor: payload.valor,
                    due_date: payload.due_date || new Date().toISOString().split('T')[0],
                    description: payload.descricao || (action === 'gerar_fatura_pix' ? `Pedido #${payload.pedido_id}` : `Mesa #${payload.mesa_id}`),
                    external_reference: customerData.external_reference
                };
                
                const paymentBody = new URLSearchParams();
                paymentBody.append('action', 'createAsaasPayment');
                Object.keys(paymentData).forEach(key => {
                    if (paymentData[key] !== undefined && paymentData[key] !== null) {
                        paymentBody.append(key, paymentData[key]);
                    }
                });
                
                return fetch('index.php?action=pagamentos_parciais', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: paymentBody
                })
                .then(response => response.json())
                .then(paymentResult => {
                    if (!paymentResult.success) {
                        throw new Error(paymentResult.error || paymentResult.message || 'Erro ao criar pagamento no Asaas');
                    }
                    
                    const asaasResponse = paymentResult.payment;
                    
                    // Step 3: Save to database
                    return savePixInvoiceToDatabase(payload, asaasResponse);
                });
            });
        }
        
        function savePixInvoiceToDatabase(originalPayload, asaasResponse) {
            const body = new URLSearchParams();
            body.append('action', 'salvar_fatura_pix');
            body.append('pedido_id', originalPayload.pedido_id || '');
            body.append('mesa_id', originalPayload.mesa_id || '');
            body.append('valor', originalPayload.valor);
            body.append('asaas_response', JSON.stringify(asaasResponse));
            
            return fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body
            })
            .then(response => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || data.error || 'Erro ao salvar fatura');
                }
                
                // Return formatted response for display
                // Handle different response structures
                const pixQrCode = asaasResponse.pixQrCode || {};
                const pixPayload = pixQrCode.payload || pixQrCode.pixCopiaECola || '';
                const pixImage = pixQrCode.encodedImage || pixQrCode.base64 || '';
                
                return {
                    success: true,
                    pixCopiaECola: pixPayload,
                    pixQrCode: pixImage,
                    pix_copy_paste: pixPayload,
                    pix_qr_code: pixImage,
                    valor: asaasResponse.value || originalPayload.valor,
                    vencimento: asaasResponse.dueDate,
                    invoiceId: asaasResponse.id,
                    invoiceUrl: asaasResponse.invoiceUrl || asaasResponse.invoiceLink,
                    payment_url: asaasResponse.invoiceUrl || asaasResponse.invoiceLink
                };
            });
        }

        function showPixInvoiceModal(data, options = {}) {
            const copyInputId = options.copyInputId || 'pixCopyPaste';
            const title = options.title || 'Fatura PIX Gerada!';
            const formattedValor = (typeof data.valor === 'number' ? data.valor : parseFloat(data.valor || 0)).toFixed(2).replace('.', ',');
            const qrBlock = data.pix_qr_code ? `<div class="mb-3"><img src="${data.pix_qr_code}" alt="QR Code PIX" style="max-width: 250px;"></div>` : '';
            const copyBlock = data.pix_copy_paste ? `
                <div class="mb-3">
                    <label class="form-label"><strong>Código PIX (Copiar e Colar):</strong></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="${copyInputId}" value="${data.pix_copy_paste}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyPixCode('${copyInputId}')">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            ` : '';
            const linkBlock = data.payment_url ? `<p class="mt-3"><a href="${data.payment_url}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Ver Fatura Completa</a></p>` : '';

            return Swal.fire({
                title,
                html: `
                    <div class="text-center">
                        <p><strong>Valor:</strong> R$ ${formattedValor}</p>
                        ${qrBlock}
                        ${copyBlock}
                        ${linkBlock}
                    </div>
                `,
                width: '500px',
                showConfirmButton: true,
                confirmButtonText: 'Fechar'
            });
        }

        function copyPixCode(inputId) {
            const pixInput = document.getElementById(inputId);
            if (!pixInput) {
                return;
            }

            pixInput.select();
            document.execCommand('copy');

            Swal.fire({
                icon: 'success',
                title: 'Copiado!',
                text: 'Código PIX copiado para a área de transferência',
                timer: 2000,
                showConfirmButton: false
            });
        }

        const CURRENT_PEDIDO_ID = <?= json_encode($pedido['idpedido']) ?>;
        const CURRENT_MESA_ID = <?= json_encode($pedido['idmesa'] ?? null) ?>;

        function gerarFaturaPixMesa(overrides = {}) {
            const valorInput = document.getElementById('valorPagarMesa');
            const valor = overrides.valor !== undefined
                ? Number(overrides.valor)
                : valorInput ? parseFloat(valorInput.value) : 0;
            const nomeCliente = overrides.nomeCliente ?? (document.getElementById('nomeCliente')?.value || '');
            const telefoneCliente = overrides.telefoneCliente ?? (document.getElementById('telefoneCliente')?.value || '');
            const descricao = overrides.descricao ?? (document.getElementById('descricao')?.value || '');
            const mesaId = overrides.mesaId ?? CURRENT_MESA_ID;

            if (valor <= 0) {
                Swal.fire('Atenção!', 'O valor a pagar deve ser maior que zero', 'warning');
                return Promise.reject(new Error('Valor inválido'));
            }

            if (!mesaId) {
                Swal.fire('Erro!', 'Mesa inválida para gerar fatura PIX', 'error');
                return Promise.reject(new Error('Mesa inválida'));
            }

            Swal.fire({
                title: 'Gerando Fatura PIX...',
                text: 'Aguarde enquanto geramos a fatura de pagamento por PIX',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const payload = {
                type: 'mesa',
                mesa_id: mesaId,
                valor: Number(valor).toFixed(2),
                nome_cliente: nomeCliente,
                telefone_cliente: telefoneCliente,
                descricao
            };

            return requestPixInvoice('gerar_fatura_pix_mesa', payload)
                .then((data) => {
                    Swal.close();
                    setLastPixInvoice(payload);
                    return showPixInvoiceModal(data, { copyInputId: 'pixCopyPasteMesa' });
                })
                .catch((error) => {
                    Swal.close();
                    Swal.fire('Erro!', error.message || 'Erro ao gerar fatura PIX', 'error');
                    throw error;
                });
        }

        function gerarFaturaPix(overrides = {}) {
            const valorInput = document.getElementById('valorPagar');
            const valor = overrides.valor !== undefined
                ? Number(overrides.valor)
                : valorInput ? parseFloat(valorInput.value) : 0;
            const nomeCliente = overrides.nomeCliente ?? (document.getElementById('nomeCliente')?.value || '');
            const telefoneCliente = overrides.telefoneCliente ?? (document.getElementById('telefoneCliente')?.value || '');
            const descricao = overrides.descricao ?? (document.getElementById('descricao')?.value || '');

            if (valor <= 0) {
                Swal.fire('Atenção!', 'O valor a pagar deve ser maior que zero', 'warning');
                return Promise.reject(new Error('Valor inválido'));
            }

            Swal.fire({
                title: 'Gerando Fatura PIX...',
                text: 'Aguarde enquanto geramos a fatura de pagamento por PIX',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const payload = {
                type: 'pedido',
                pedido_id: CURRENT_PEDIDO_ID,
                valor: Number(valor).toFixed(2),
                nome_cliente: nomeCliente,
                telefone_cliente: telefoneCliente,
                descricao
            };

            return requestPixInvoice('gerar_fatura_pix', payload)
                .then((data) => {
                    Swal.close();
                    setLastPixInvoice(payload);
                    return showPixInvoiceModal(data);
                })
                .catch((error) => {
                    Swal.close();
                    Swal.fire('Erro!', error.message || 'Erro ao gerar fatura PIX', 'error');
                    throw error;
                });
        }

        function registrarPagamento(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'registrar_pagamento_parcial');
            formData.append('pedido_id', <?= $pedido['idpedido'] ?>);
            formData.append('forma_pagamento', dados.formaPagamento);
            formData.append('valor_pago', dados.valorPagar);
            formData.append('valor_desconto', dados.valorDesconto);
            formData.append('tipo_desconto', dados.tipoDesconto);
            formData.append('nome_cliente', dados.nomeCliente);
            formData.append('telefone_cliente', dados.telefoneCliente);
            formData.append('descricao', dados.descricao);
            
            // Add invoice data if requested
            if (dados.gerarNotaFiscal) {
                formData.append('gerar_nota_fiscal', '1');
                formData.append('valor_nota_fiscal', dados.valorNotaFiscal);
                formData.append('enviar_whatsapp', dados.enviarWhatsApp ? '1' : '0');
                formData.append('cliente_cpf', dados.clienteCpf);
                formData.append('cliente_cnpj', dados.clienteCnpj);
            }
            
            // Add client data for processing
            if (dados.nomeCliente || dados.telefoneCliente) {
                formData.append('dados_cliente', JSON.stringify({
                    nome: dados.nomeCliente,
                    telefone: dados.telefoneCliente
                }));
            }
            
            fetch('index.php?action=pagamentos_parciais', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let mensagem = '';
                    
                    if (data.pedido && data.pedido.status_pagamento === 'quitado') {
                        mensagem = 'Pedido quitado com sucesso!';
                    } else if (data.pedido && data.pedido.saldo_devedor !== undefined) {
                        mensagem = `Pagamento registrado! Saldo restante: R$ ${data.pedido.saldo_devedor.toFixed(2).replace('.', ',')}`;
                    } else {
                        mensagem = 'Pagamento registrado com sucesso!';
                    }
                    
                    // Add invoice information if generated
                    if (data.nota_fiscal) {
                        mensagem += `\n\n📄 Nota Fiscal: ${data.nota_fiscal.numero_nota || 'Gerada'}`;
                        if (data.nota_fiscal.enviada_whatsapp) {
                            mensagem += '\n📱 Enviada por WhatsApp';
                        }
                    }
                    
                    Swal.fire('Sucesso!', mensagem, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message || data.error || 'Erro desconhecido', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar pagamento', 'error');
            });
        }

        // Função para buscar cliente por telefone
        function buscarClientePorTelefone(modalType) {
            const telefone = document.getElementById('telefoneCliente').value.trim();
            if (!telefone) {
                showInlineMessage('Digite o telefone do cliente', 'warning');
                return;
            }
            
            // Show loading indicator
            showInlineMessage('Buscando cliente...', 'info');
            
            fetch(`mvc/ajax/clientes.php?action=buscar_por_telefone&telefone=${encodeURIComponent(telefone)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.cliente) {
                        // Load client data into form
                        document.getElementById('nomeCliente').value = data.cliente.nome || '';
                        
                        // Load fiscal data if available
                        if (data.cliente.cpf) {
                            document.getElementById('clienteCpf').value = data.cliente.cpf;
                        }
                        if (data.cliente.cnpj) {
                            document.getElementById('clienteCnpj').value = data.cliente.cnpj;
                        }
                        
                        // Show success message inline
                        showInlineMessage(`✅ Cliente encontrado: ${data.cliente.nome}`, 'success');
                    } else {
                        showInlineMessage('ℹ️ Cliente não encontrado. Você pode cadastrar preenchendo os dados.', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showInlineMessage('❌ Erro ao buscar cliente', 'error');
                });
        }

        // Função para mostrar mensagens inline sem fechar o modal
        function showInlineMessage(message, type) {
            // Remove existing message if any
            const existingMessage = document.getElementById('clientSearchMessage');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create new message element
            const messageDiv = document.createElement('div');
            messageDiv.id = 'clientSearchMessage';
            messageDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'info'} alert-sm mt-2`;
            messageDiv.style.fontSize = '0.875rem';
            messageDiv.style.padding = '0.5rem';
            messageDiv.innerHTML = message;
            
            // Insert after the phone field
            const telefoneField = document.getElementById('telefoneCliente').closest('.mb-3');
            telefoneField.appendChild(messageDiv);
            
            // Auto-remove after 3 seconds for success/info messages
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 3000);
            }
        }

        // Debounce function for automatic search
        let searchTimeout;
        function debounceSearch() {
            clearTimeout(searchTimeout);
            const telefone = document.getElementById('telefoneCliente').value.trim();
            
            // Only search if phone has at least 10 digits
            if (telefone.length >= 10) {
                searchTimeout = setTimeout(() => {
                    buscarClientePorTelefone('auto');
                }, 1000); // Wait 1 second after user stops typing
            }
        }

        // Formatação de CPF
        function formatarCPF(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                input.value = value;
            }
        }

        // Formatação de CNPJ
        function formatarCNPJ(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                input.value = value;
            }
        }

        // Inicializar valor por pessoa quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            calcularValorPorPessoa();
        });
    </script>
</body>
</html>
