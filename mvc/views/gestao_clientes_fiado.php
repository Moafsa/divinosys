<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Get clients data
$clientes = [];
if ($tenant && $filial) {
    $clientes = $db->fetchAll(
        'SELECT * FROM clientes_fiado WHERE tenant_id = ? AND filial_id = ? ORDER BY nome ASC',
        [$tenant['id'], $filial['id']]
    );
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Clientes Fiado - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .client-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .client-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8em;
        }
        .credit-limit {
            font-size: 1.1em;
            font-weight: bold;
        }
        .debt-amount {
            font-size: 1.1em;
            font-weight: bold;
        }
        .available-credit {
            color: #28a745;
        }
        .debt-warning {
            color: #dc3545;
        }
        .debt-danger {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-users"></i> Gestão de Clientes Fiado</h2>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                            <i class="fas fa-plus"></i> Novo Cliente
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Clientes</h6>
                                <h3><?= count($clientes) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Clientes Ativos</h6>
                                <h3><?= count(array_filter($clientes, fn($c) => $c['status'] === 'ativo')) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Limite Total</h6>
                                <h3>R$ <?= number_format(array_sum(array_column($clientes, 'limite_credito')), 2, ',', '.') ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-credit-card fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="searchClientes" placeholder="Buscar por nome, CPF ou telefone...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">Todos os Status</option>
                    <option value="ativo">Ativo</option>
                    <option value="bloqueado">Bloqueado</option>
                    <option value="suspenso">Suspenso</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterDebt">
                    <option value="">Todos</option>
                    <option value="com_debito">Com Débito</option>
                    <option value="sem_debito">Sem Débito</option>
                    <option value="limite_esgotado">Limite Esgotado</option>
                </select>
            </div>
        </div>

        <!-- Clients Grid -->
        <div class="row" id="clientesGrid">
            <?php foreach ($clientes as $cliente): ?>
                <?php 
                $limiteDisponivel = $cliente['limite_credito'] - $cliente['saldo_devedor'];
                $percentualUso = $cliente['limite_credito'] > 0 ? ($cliente['saldo_devedor'] / $cliente['limite_credito']) * 100 : 0;
                ?>
                <div class="col-md-6 col-lg-4 mb-4 cliente-card" 
                     data-nome="<?= strtolower($cliente['nome']) ?>"
                     data-cpf="<?= $cliente['cpf_cnpj'] ?>"
                     data-telefone="<?= $cliente['telefone'] ?>"
                     data-status="<?= $cliente['status'] ?>"
                     data-debito="<?= $cliente['saldo_devedor'] > 0 ? 'com_debito' : 'sem_debito' ?>"
                     data-limite="<?= $limiteDisponivel <= 0 ? 'limite_esgotado' : 'com_limite' ?>">
                    <div class="card client-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= htmlspecialchars($cliente['nome']) ?></h6>
                            <span class="badge status-badge bg-<?= $cliente['status'] === 'ativo' ? 'success' : ($cliente['status'] === 'bloqueado' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($cliente['status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted">CPF/CNPJ:</small><br>
                                <span><?= $cliente['cpf_cnpj'] ?: 'Não informado' ?></span>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Telefone:</small><br>
                                <span><?= $cliente['telefone'] ?: 'Não informado' ?></span>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Email:</small><br>
                                <span><?= $cliente['email'] ?: 'Não informado' ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted">Limite de Crédito</small><br>
                                    <span class="credit-limit">R$ <?= number_format($cliente['limite_credito'], 2, ',', '.') ?></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Saldo Devedor</small><br>
                                    <span class="debt-amount <?= $cliente['saldo_devedor'] > 0 ? 'debt-warning' : '' ?>">
                                        R$ <?= number_format($cliente['saldo_devedor'], 2, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">Limite Disponível</small><br>
                                <span class="<?= $limiteDisponivel >= 0 ? 'available-credit' : 'debt-danger' ?>">
                                    R$ <?= number_format($limiteDisponivel, 2, ',', '.') ?>
                                </span>
                            </div>
                            
                            <?php if ($percentualUso > 0): ?>
                                <div class="mt-2">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $percentualUso > 80 ? 'bg-danger' : ($percentualUso > 60 ? 'bg-warning' : 'bg-success') ?>" 
                                             style="width: <?= min($percentualUso, 100) ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= number_format($percentualUso, 1) ?>% do limite usado</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="editarCliente(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="verHistorico(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="novaVendaFiada(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="receberPagamento(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($clientes)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Nenhum cliente cadastrado</h4>
                        <p class="text-muted">Comece cadastrando seu primeiro cliente para vendas fiadas.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                            <i class="fas fa-plus"></i> Cadastrar Primeiro Cliente
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Novo Cliente -->
    <div class="modal fade" id="modalNovoCliente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Novo Cliente
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNovoCliente">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nome Completo *</label>
                                    <input type="text" class="form-control" name="nome" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">CPF/CNPJ</label>
                                    <input type="text" class="form-control" name="cpf_cnpj" placeholder="000.000.000-00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control" name="telefone" placeholder="(00) 00000-0000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Endereço</label>
                            <textarea class="form-control" name="endereco" rows="2" placeholder="Rua, número, bairro, cidade..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Limite de Crédito (R$) *</label>
                                    <input type="number" class="form-control" name="limite_credito" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="ativo">Ativo</option>
                                        <option value="bloqueado">Bloqueado</option>
                                        <option value="suspenso">Suspenso</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="3" placeholder="Informações adicionais sobre o cliente..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search and Filter functionality
        document.getElementById('searchClientes').addEventListener('input', filterClientes);
        document.getElementById('filterStatus').addEventListener('change', filterClientes);
        document.getElementById('filterDebt').addEventListener('change', filterClientes);

        function filterClientes() {
            const search = document.getElementById('searchClientes').value.toLowerCase();
            const status = document.getElementById('filterStatus').value;
            const debt = document.getElementById('filterDebt').value;
            
            const cards = document.querySelectorAll('.cliente-card');
            
            cards.forEach(card => {
                const nome = card.dataset.nome;
                const cpf = card.dataset.cpf;
                const telefone = card.dataset.telefone;
                const clienteStatus = card.dataset.status;
                const debito = card.dataset.debito;
                const limite = card.dataset.limite;
                
                let show = true;
                
                // Search filter
                if (search && !nome.includes(search) && !cpf.includes(search) && !telefone.includes(search)) {
                    show = false;
                }
                
                // Status filter
                if (status && clienteStatus !== status) {
                    show = false;
                }
                
                // Debt filter
                if (debt) {
                    if (debt === 'com_debito' && debito !== 'com_debito') show = false;
                    if (debt === 'sem_debito' && debito !== 'sem_debito') show = false;
                    if (debt === 'limite_esgotado' && limite !== 'limite_esgotado') show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

        // Form submission
        document.getElementById('formNovoCliente').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'criar_cliente_fiado');
            
            fetch('index.php?action=ajax_clientes_fiado', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Cliente cadastrado com sucesso!', 'success');
                    location.reload();
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro!', 'Erro ao cadastrar cliente', 'error');
            });
        });

        // Action functions
        function editarCliente(id) {
            // TODO: Implementar edição de cliente
            Swal.fire('Em desenvolvimento', 'Funcionalidade de edição será implementada em breve', 'info');
        }

        function verHistorico(id) {
            // TODO: Implementar histórico de vendas
            Swal.fire('Em desenvolvimento', 'Funcionalidade de histórico será implementada em breve', 'info');
        }

        function novaVendaFiada(id) {
            // TODO: Implementar nova venda fiada
            Swal.fire('Em desenvolvimento', 'Funcionalidade de venda fiada será implementada em breve', 'info');
        }

        function receberPagamento(id) {
            // TODO: Implementar recebimento de pagamento
            Swal.fire('Em desenvolvimento', 'Funcionalidade de pagamento será implementada em breve', 'info');
        }
    </script>
</body>
</html>
