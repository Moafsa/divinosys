<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?view=login');
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4">Vendas Fiadas</h1>
    <p>Gerencie vendas a prazo para clientes cadastrados no sistema de fiado.</p>
    
    <!-- Filtros e Busca -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Filtros</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="filtro_status" class="form-label">Status:</label>
                        <select id="filtro_status" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="parcial">Pago Parcial</option>
                            <option value="pago">Pago</option>
                            <option value="vencido">Vencido</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="filtro_cliente" class="form-label">Cliente:</label>
                        <select id="filtro_cliente" class="form-select">
                            <option value="">Todos os clientes</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="filtro_data_inicio" class="form-label">Data Início:</label>
                        <input type="date" id="filtro_data_inicio" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="filtro_data_fim" class="form-label">Data Fim:</label>
                        <input type="date" id="filtro_data_fim" class="form-control">
                    </div>
                    <button id="btn_aplicar_filtros" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Vendas Fiadas</h5>
                    <button id="btn_nova_venda_fiada" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nova Venda Fiada
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabela_vendas_fiadas" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Valor Total</th>
                                    <th>Valor Pago</th>
                                    <th>Saldo Devedor</th>
                                    <th>Status</th>
                                    <th>Vencimento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dados carregados via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Venda Fiada -->
<div class="modal fade" id="modalNovaVendaFiada" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Venda Fiada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaVendaFiada">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cliente_id" class="form-label">Cliente *</label>
                                <select id="cliente_id" name="cliente_id" class="form-select" required>
                                    <option value="">Selecione um cliente</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="data_vencimento" class="form-label">Data de Vencimento *</label>
                                <input type="date" id="data_vencimento" name="data_vencimento" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="valor_total" class="form-label">Valor Total *</label>
                                <input type="number" id="valor_total" name="valor_total" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="desconto" class="form-label">Desconto</label>
                                <input type="number" id="desconto" name="desconto" class="form-control" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Produtos/Serviços</label>
                        <div id="produtos_container">
                            <div class="row produto-item mb-2">
                                <div class="col-md-5">
                                    <select name="produto_id[]" class="form-select produto-select">
                                        <option value="">Selecione um produto</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="quantidade[]" class="form-control" placeholder="Qtd" min="1" value="1">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="preco_unitario[]" class="form-control preco-unitario" placeholder="Preço" step="0.01" min="0">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm remover-produto">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="btn_adicionar_produto" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus"></i> Adicionar Produto
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_salvar_venda_fiada" class="btn btn-success">Salvar Venda Fiada</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pagamento Parcial -->
<div class="modal fade" id="modalPagamentoParcial" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPagamentoParcial">
                    <input type="hidden" id="venda_id_pagamento" name="venda_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Saldo Devedor:</label>
                        <div id="saldo_devedor_info" class="alert alert-info"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor_pagamento" class="form-label">Valor do Pagamento *</label>
                        <input type="number" id="valor_pagamento" name="valor_pagamento" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento *</label>
                        <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                            <option value="">Selecione</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao_debito">Cartão Débito</option>
                            <option value="cartao_credito">Cartão Crédito</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes_pagamento" class="form-label">Observações</label>
                        <textarea id="observacoes_pagamento" name="observacoes_pagamento" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_registrar_pagamento" class="btn btn-success">Registrar Pagamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes da Venda -->
<div class="modal fade" id="modalDetalhesVenda" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da Venda Fiada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detalhes_venda_content">
                    <!-- Conteúdo carregado via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    carregarClientes();
    carregarProdutos();
    carregarVendasFiadas();
    
    // Event listeners
    document.getElementById('btn_nova_venda_fiada').addEventListener('click', function() {
        document.getElementById('modalNovaVendaFiada').style.display = 'block';
        new bootstrap.Modal(document.getElementById('modalNovaVendaFiada')).show();
    });
    
    document.getElementById('btn_salvar_venda_fiada').addEventListener('click', salvarVendaFiada);
    document.getElementById('btn_registrar_pagamento').addEventListener('click', registrarPagamento);
    document.getElementById('btn_aplicar_filtros').addEventListener('click', aplicarFiltros);
    document.getElementById('btn_adicionar_produto').addEventListener('click', adicionarProduto);
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remover-produto')) {
            e.target.closest('.produto-item').remove();
        }
        
        if (e.target.classList.contains('btn-pagar')) {
            const vendaId = e.target.dataset.vendaId;
            abrirModalPagamento(vendaId);
        }
        
        if (e.target.classList.contains('btn-detalhes')) {
            const vendaId = e.target.dataset.vendaId;
            mostrarDetalhesVenda(vendaId);
        }
    });
});

function carregarClientes() {
    fetch('mvc/ajax/clientes_fiado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_clientes'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const selectCliente = document.getElementById('cliente_id');
            const selectFiltroCliente = document.getElementById('filtro_cliente');
            
            selectCliente.innerHTML = '<option value="">Selecione um cliente</option>';
            selectFiltroCliente.innerHTML = '<option value="">Todos os clientes</option>';
            
            data.clientes.forEach(cliente => {
                const option = `<option value="${cliente.id}">${cliente.nome} - ${cliente.telefone || 'Sem telefone'}</option>`;
                selectCliente.innerHTML += option;
                selectFiltroCliente.innerHTML += option;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar clientes:', error));
}

function carregarProdutos() {
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_produtos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const selects = document.querySelectorAll('.produto-select');
            selects.forEach(select => {
                select.innerHTML = '<option value="">Selecione um produto</option>';
                data.produtos.forEach(produto => {
                    select.innerHTML += `<option value="${produto.id}" data-preco="${produto.preco}">${produto.nome} - R$ ${produto.preco.toFixed(2)}</option>`;
                });
            });
        }
    })
    .catch(error => console.error('Erro ao carregar produtos:', error));
}

function carregarVendasFiadas() {
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_vendas'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_vendas_fiadas tbody');
            tbody.innerHTML = '';
            
            data.vendas.forEach(venda => {
                const statusClass = getStatusClass(venda.status);
                const statusText = getStatusText(venda.status);
                
                const row = `
                    <tr>
                        <td>${venda.id}</td>
                        <td>${venda.cliente_nome}</td>
                        <td>${formatarData(venda.data_venda)}</td>
                        <td>R$ ${venda.valor_total.toFixed(2)}</td>
                        <td>R$ ${venda.valor_pago.toFixed(2)}</td>
                        <td>R$ ${venda.saldo_devedor.toFixed(2)}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${formatarData(venda.data_vencimento)}</td>
                        <td>
                            <button class="btn btn-info btn-sm btn-detalhes" data-venda-id="${venda.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${venda.saldo_devedor > 0 ? `
                                <button class="btn btn-warning btn-sm btn-pagar" data-venda-id="${venda.id}">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar vendas fiadas:', error));
}

function adicionarProduto() {
    const container = document.getElementById('produtos_container');
    const novoItem = document.createElement('div');
    novoItem.className = 'row produto-item mb-2';
    novoItem.innerHTML = `
        <div class="col-md-5">
            <select name="produto_id[]" class="form-select produto-select">
                <option value="">Selecione um produto</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="quantidade[]" class="form-control" placeholder="Qtd" min="1" value="1">
        </div>
        <div class="col-md-3">
            <input type="number" name="preco_unitario[]" class="form-control preco-unitario" placeholder="Preço" step="0.01" min="0">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remover-produto">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(novoItem);
    
    // Carregar produtos no novo select
    carregarProdutos();
    
    // Event listener para mudança de produto
    const select = novoItem.querySelector('.produto-select');
    select.addEventListener('change', function() {
        const preco = this.selectedOptions[0]?.dataset.preco || 0;
        const precoInput = novoItem.querySelector('.preco-unitario');
        precoInput.value = preco;
    });
}

function salvarVendaFiada() {
    const form = document.getElementById('formNovaVendaFiada');
    const formData = new FormData(form);
    formData.append('acao', 'criar_venda');
    
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Venda fiada criada com sucesso!');
            bootstrap.Modal.getInstance(document.getElementById('modalNovaVendaFiada')).hide();
            form.reset();
            carregarVendasFiadas();
        } else {
            alert('Erro ao criar venda fiada: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar venda fiada');
    });
}

function abrirModalPagamento(vendaId) {
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=obter_detalhes_venda&venda_id=${vendaId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('venda_id_pagamento').value = vendaId;
            document.getElementById('saldo_devedor_info').innerHTML = `
                <strong>R$ ${data.venda.saldo_devedor.toFixed(2)}</strong>
            `;
            document.getElementById('valor_pagamento').max = data.venda.saldo_devedor;
            new bootstrap.Modal(document.getElementById('modalPagamentoParcial')).show();
        }
    })
    .catch(error => console.error('Erro ao carregar detalhes da venda:', error));
}

function registrarPagamento() {
    const form = document.getElementById('formPagamentoParcial');
    const formData = new FormData(form);
    formData.append('acao', 'registrar_pagamento');
    
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pagamento registrado com sucesso!');
            bootstrap.Modal.getInstance(document.getElementById('modalPagamentoParcial')).hide();
            form.reset();
            carregarVendasFiadas();
        } else {
            alert('Erro ao registrar pagamento: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao registrar pagamento');
    });
}

function mostrarDetalhesVenda(vendaId) {
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=obter_detalhes_completos&venda_id=${vendaId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('detalhes_venda_content').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('modalDetalhesVenda')).show();
        }
    })
    .catch(error => console.error('Erro ao carregar detalhes:', error));
}

function aplicarFiltros() {
    const status = document.getElementById('filtro_status').value;
    const cliente = document.getElementById('filtro_cliente').value;
    const dataInicio = document.getElementById('filtro_data_inicio').value;
    const dataFim = document.getElementById('filtro_data_fim').value;
    
    const params = new URLSearchParams({
        acao: 'listar_vendas_filtradas',
        status: status,
        cliente: cliente,
        data_inicio: dataInicio,
        data_fim: dataFim
    });
    
    fetch('mvc/ajax/vendas_fiadas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_vendas_fiadas tbody');
            tbody.innerHTML = '';
            
            data.vendas.forEach(venda => {
                const statusClass = getStatusClass(venda.status);
                const statusText = getStatusText(venda.status);
                
                const row = `
                    <tr>
                        <td>${venda.id}</td>
                        <td>${venda.cliente_nome}</td>
                        <td>${formatarData(venda.data_venda)}</td>
                        <td>R$ ${venda.valor_total.toFixed(2)}</td>
                        <td>R$ ${venda.valor_pago.toFixed(2)}</td>
                        <td>R$ ${venda.saldo_devedor.toFixed(2)}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${formatarData(venda.data_vencimento)}</td>
                        <td>
                            <button class="btn btn-info btn-sm btn-detalhes" data-venda-id="${venda.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${venda.saldo_devedor > 0 ? `
                                <button class="btn btn-warning btn-sm btn-pagar" data-venda-id="${venda.id}">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao aplicar filtros:', error));
}

// Funções auxiliares
function getStatusClass(status) {
    switch(status) {
        case 'pendente': return 'bg-warning';
        case 'parcial': return 'bg-info';
        case 'pago': return 'bg-success';
        case 'vencido': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'pendente': return 'Pendente';
        case 'parcial': return 'Pago Parcial';
        case 'pago': return 'Pago';
        case 'vencido': return 'Vencido';
        default: return 'Desconhecido';
    }
}

function formatarData(data) {
    return new Date(data).toLocaleDateString('pt-BR');
}
</script>
