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
        
        // Buscar a mesa correspondente
        $mesa = $db->fetch(
            "SELECT numero FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
            [$pedidoRaw['idmesa'], $tenant['id'], $filial['id']]
        );
        
        $pedido = $pedidoRaw;
        $pedido['mesa_numero'] = $mesa['numero'] ?? 'N/A';
        
        error_log('DEBUG FecharPedido: mesa encontrada = ' . ($mesa ? $mesa['numero'] : 'NULL'));
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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                        <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente" value="<?= htmlspecialchars($pedido['cliente'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                        <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999" value="<?= htmlspecialchars($pedido['telefone_cliente'] ?? '') ?>">
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
                        <input type="text" class="form-control" id="nomeCliente" placeholder="Nome do cliente" value="<?= htmlspecialchars($pedido['cliente'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone (opcional)</label>
                        <input type="text" class="form-control" id="telefoneCliente" placeholder="(11) 99999-9999" value="<?= htmlspecialchars($pedido['telefone_cliente'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" rows="2" placeholder="Observações sobre o pagamento..."></textarea>
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
                    
                    return { formaPagamento, valorPagar, nomeCliente, telefoneCliente, descricao };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    registrarPagamento(result.value);
                }
            });
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
                    if (data.pedido && data.pedido.status_pagamento === 'quitado') {
                        Swal.fire('Sucesso!', 'Pedido quitado com sucesso!', 'success').then(() => {
                            location.reload();
                        });
                    } else if (data.pedido && data.pedido.saldo_devedor !== undefined) {
                        Swal.fire('Sucesso!', `Pagamento registrado! Saldo restante: R$ ${data.pedido.saldo_devedor.toFixed(2).replace('.', ',')}`, 'success').then(() => {
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

        // Inicializar valor por pessoa quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            calcularValorPorPessoa();
        });
    </script>
</body>
</html>
