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
                                <div class="pagamento-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>R$ <?= number_format($pagamento['valor_pago'], 2, ',', '.') ?></strong>
                                            <span class="badge bg-secondary ms-2"><?= $pagamento['forma_pagamento'] ?></span>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($pagamento['created_at'])) ?></small>
                                    </div>
                                    <?php if ($pagamento['nome_cliente']): ?>
                                        <small class="text-muted">Cliente: <?= $pagamento['nome_cliente'] ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($pagamento['descricao']): ?>
                                        <small class="text-muted">Obs: <?= $pagamento['descricao'] ?></small>
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
                        <p class="text-danger"><strong>Saldo Devedor:</strong> R$ ${saldoDevedorMesa.toFixed(2).replace('.', ',')}</p>
                        <p class="text-info"><strong>Valor por Pessoa (${numeroPessoas} pessoas):</strong> R$ ${valorPorPessoa.toFixed(2).replace('.', ',')}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento" required onchange="toggleFiadoFields()">
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
                               <input type="number" class="form-control" id="valorPagar" step="0.01" min="0" max="${saldoDevedorMesa.toFixed(2)}" value="${saldoDevedorMesa.toFixed(2)}" required>
                               <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagar').value = '${saldoDevedorMesa.toFixed(2)}'">Saldo Total</button>
                           </div>
                           <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
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
                    const formaPagamento = document.getElementById('formaPagamento').value;
                    const valorPagar = parseFloat(document.getElementById('valorPagar').value) || 0;
                    const nomeCliente = document.getElementById('nomeCliente').value;
                    const telefoneCliente = document.getElementById('telefoneCliente').value;
                    const descricao = document.getElementById('descricao').value;
                    
                    if (!formaPagamento) {
                        Swal.showValidationMessage('Forma de pagamento é obrigatória');
                        return false;
                    }
                    
                    if (valorPagar <= 0) {
                        Swal.showValidationMessage('Valor deve ser maior que zero');
                        return false;
                    }
                    
                    if (valorPagar > saldoDevedorMesa + 0.01) {
                        Swal.showValidationMessage('Valor não pode ser maior que o saldo devedor');
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
                    
                    return { formaPagamento, valorPagar, nomeCliente, telefoneCliente, descricao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    registrarPagamentoMesa(result.value);
                }
            });
        }

        function toggleFiadoFieldsMesa() {
            const formaPagamento = document.getElementById('formaPagamentoMesa').value;
            const nomeClienteField = document.getElementById('nomeClienteMesa');
            const telefoneClienteField = document.getElementById('telefoneClienteMesa');
            
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

        function abrirModalPagamento() {
            const valorTotal = <?= $pedido['valor_total'] ?>;
            const valorPago = <?= $pedido['valor_pago'] ?? 0 ?>;
            const saldoDevedor = valorTotal - valorPago;
            
            Swal.fire({
                title: 'Registrar Pagamento',
                html: `
                    <div class="mb-3">
                        <p><strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2).replace('.', ',')}</p>
                        <p><strong>Já Pago:</strong> R$ ${valorPago.toFixed(2).replace('.', ',')}</p>
                        <p class="text-danger"><strong>Saldo Devedor:</strong> R$ ${saldoDevedor.toFixed(2).replace('.', ',')}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="formaPagamento" required onchange="toggleFiadoFields()">
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
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('valorPagar').value = '${saldoDevedor.toFixed(2)}'">Saldo Total</button>
                        </div>
                        <small class="text-muted">Informe o valor que deseja pagar agora (pode ser parcial)</small>
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
                    
                    if (valorPagar > saldoDevedor + 0.01) {
                        Swal.showValidationMessage('Valor não pode ser maior que o saldo devedor');
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

        function registrarPagamento(dados) {
            const formData = new URLSearchParams();
            formData.append('action', 'registrar_pagamento_parcial');
            formData.append('pedido_id', <?= $pedido['idpedido'] ?>);
            formData.append('forma_pagamento', dados.formaPagamento);
            formData.append('valor_pago', dados.valorPagar);
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
