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
    <h1 class="mb-4">Caixa Avançado</h1>
    <p>Sistema completo de caixa com vendas, fiado, descontos, cortesias e integração de pagamentos.</p>
    
    <!-- Informações da Sessão de Caixa -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Sessão de Caixa</h5>
                    <div>
                        <span class="badge bg-success me-2" id="status_caixa">Aberto</span>
                        <button id="btn_fechar_caixa" class="btn btn-warning btn-sm">
                            <i class="fas fa-lock"></i> Fechar Caixa
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Operador:</strong> <?php echo $_SESSION['user_name'] ?? 'Usuário'; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Abertura:</strong> <span id="data_abertura"><?php echo date('d/m/Y H:i'); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Vendas Hoje:</strong> <span id="total_vendas_hoje">R$ 0,00</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Saldo Caixa:</strong> <span id="saldo_caixa" class="text-success">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Painel de Vendas -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Nova Venda</h5>
                </div>
                <div class="card-body">
                    <!-- Seleção de Cliente -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cliente_venda" class="form-label">Cliente</label>
                            <select id="cliente_venda" class="form-select">
                                <option value="">Cliente Avulso</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tipo_venda" class="form-label">Tipo de Venda</label>
                            <select id="tipo_venda" class="form-select">
                                <option value="normal">Venda Normal</option>
                                <option value="fiado">Venda Fiada</option>
                                <option value="cortesia">Cortesia</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Busca de Produtos -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="busca_produto" class="form-label">Buscar Produto</label>
                            <input type="text" id="busca_produto" class="form-control" placeholder="Digite o nome do produto...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button id="btn_buscar_produto" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Lista de Produtos -->
                    <div id="lista_produtos" class="row mb-3">
                        <!-- Produtos carregados via AJAX -->
                    </div>
                    
                    <!-- Itens da Venda -->
                    <div class="card">
                        <div class="card-header">
                            <h6>Itens da Venda</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_itens_venda" class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Qtd</th>
                                            <th>Preço</th>
                                            <th>Subtotal</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Itens adicionados dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resumo da Venda -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Resumo da Venda</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Subtotal:</span>
                                        <span id="subtotal_venda">R$ 0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Desconto:</span>
                                        <span id="desconto_venda">R$ 0,00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Total:</span>
                                        <span id="total_venda" class="fw-bold">R$ 0,00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Desconto/Cortesia</h6>
                                    <div class="mb-2">
                                        <input type="number" id="valor_desconto" class="form-control" placeholder="Valor do desconto" step="0.01" min="0">
                                    </div>
                                    <div class="mb-2">
                                        <select id="tipo_desconto" class="form-select">
                                            <option value="valor">Valor Fixo (R$)</option>
                                            <option value="percentual">Percentual (%)</option>
                                        </select>
                                    </div>
                                    <button id="btn_aplicar_desconto" class="btn btn-warning btn-sm w-100">
                                        <i class="fas fa-percentage"></i> Aplicar Desconto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Painel de Pagamento -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Finalizar Venda</h5>
                </div>
                <div class="card-body">
                    <!-- Formas de Pagamento -->
                    <div class="mb-3">
                        <label class="form-label">Formas de Pagamento</label>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-success w-100 btn-forma-pagamento" data-forma="dinheiro">
                                    <i class="fas fa-money-bill"></i> Dinheiro
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-primary w-100 btn-forma-pagamento" data-forma="pix">
                                    <i class="fas fa-qrcode"></i> PIX
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-info w-100 btn-forma-pagamento" data-forma="cartao_debito">
                                    <i class="fas fa-credit-card"></i> Débito
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-warning w-100 btn-forma-pagamento" data-forma="cartao_credito">
                                    <i class="fas fa-credit-card"></i> Crédito
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagamentos Adicionados -->
                    <div id="pagamentos_adicionados" class="mb-3">
                        <!-- Pagamentos serão adicionados aqui -->
                    </div>
                    
                    <!-- Resumo do Pagamento -->
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6>Resumo do Pagamento</h6>
                            <div class="d-flex justify-content-between">
                                <span>Total da Venda:</span>
                                <span id="total_pagamento">R$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total Pago:</span>
                                <span id="total_pago">R$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Troco:</span>
                                <span id="troco" class="text-success">R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="d-grid gap-2 mt-3">
                        <button id="btn_finalizar_venda" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-check"></i> Finalizar Venda
                        </button>
                        <button id="btn_cancelar_venda" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancelar Venda
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Pagamento -->
    <div class="modal fade" id="modalPagamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Pagamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPagamento">
                        <input type="hidden" id="forma_pagamento_selecionada" name="forma_pagamento">
                        
                        <div class="mb-3">
                            <label for="valor_pagamento" class="form-label">Valor do Pagamento *</label>
                            <input type="number" id="valor_pagamento" name="valor_pagamento" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3" id="troco_container" style="display: none;">
                            <label for="valor_troco" class="form-label">Troco</label>
                            <input type="number" id="valor_troco" name="valor_troco" class="form-control" step="0.01" min="0" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes_pagamento" class="form-label">Observações</label>
                            <textarea id="observacoes_pagamento" name="observacoes_pagamento" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn_adicionar_pagamento" class="btn btn-success">Adicionar Pagamento</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar caixa
    inicializarCaixa();
    carregarClientes();
    carregarProdutos();
    
    // Event listeners
    document.getElementById('btn_buscar_produto').addEventListener('click', buscarProdutos);
    document.getElementById('btn_aplicar_desconto').addEventListener('click', aplicarDesconto);
    document.getElementById('btn_finalizar_venda').addEventListener('click', finalizarVenda);
    document.getElementById('btn_cancelar_venda').addEventListener('click', cancelarVenda);
    document.getElementById('btn_adicionar_pagamento').addEventListener('click', adicionarPagamento);
    document.getElementById('btn_fechar_caixa').addEventListener('click', fecharCaixa);
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-adicionar-produto')) {
            const produtoId = e.target.dataset.produtoId;
            adicionarProdutoVenda(produtoId);
        }
        
        if (e.target.classList.contains('btn-remover-item')) {
            const itemId = e.target.dataset.itemId;
            removerItemVenda(itemId);
        }
        
        if (e.target.classList.contains('btn-forma-pagamento')) {
            const forma = e.target.dataset.forma;
            abrirModalPagamento(forma);
        }
        
        if (e.target.classList.contains('btn-remover-pagamento')) {
            const pagamentoId = e.target.dataset.pagamentoId;
            removerPagamento(pagamentoId);
        }
    });
    
    // Event listener para mudança de quantidade
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('quantidade-item')) {
            const itemId = e.target.dataset.itemId;
            const novaQuantidade = parseInt(e.target.value);
            atualizarQuantidadeItem(itemId, novaQuantidade);
        }
    });
});

let itensVenda = [];
let pagamentosVenda = [];
let totalVenda = 0;
let totalPago = 0;

function inicializarCaixa() {
    // Carregar informações da sessão de caixa
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=inicializar_caixa'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('total_vendas_hoje').textContent = 'R$ ' + data.total_vendas.toFixed(2).replace('.', ',');
            document.getElementById('saldo_caixa').textContent = 'R$ ' + data.saldo_caixa.toFixed(2).replace('.', ',');
        }
    })
    .catch(error => console.error('Erro ao inicializar caixa:', error));
}

function carregarClientes() {
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_clientes'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('cliente_venda');
            select.innerHTML = '<option value="">Cliente Avulso</option>';
            
            data.clientes.forEach(cliente => {
                select.innerHTML += `<option value="${cliente.id}">${cliente.nome} - ${cliente.telefone || 'Sem telefone'}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar clientes:', error));
}

function carregarProdutos() {
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_produtos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('lista_produtos');
            container.innerHTML = '';
            
            data.produtos.forEach(produto => {
                const produtoHtml = `
                    <div class="col-md-3 mb-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>${produto.nome}</h6>
                                <p class="text-muted">R$ ${produto.preco.toFixed(2).replace('.', ',')}</p>
                                <button class="btn btn-primary btn-sm btn-adicionar-produto" data-produto-id="${produto.id}">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += produtoHtml;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar produtos:', error));
}

function buscarProdutos() {
    const termo = document.getElementById('busca_produto').value;
    
    if (termo.length < 2) {
        carregarProdutos();
        return;
    }
    
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=buscar_produtos&termo=${encodeURIComponent(termo)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById('lista_produtos');
            container.innerHTML = '';
            
            data.produtos.forEach(produto => {
                const produtoHtml = `
                    <div class="col-md-3 mb-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>${produto.nome}</h6>
                                <p class="text-muted">R$ ${produto.preco.toFixed(2).replace('.', ',')}</p>
                                <button class="btn btn-primary btn-sm btn-adicionar-produto" data-produto-id="${produto.id}">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += produtoHtml;
            });
        }
    })
    .catch(error => console.error('Erro ao buscar produtos:', error));
}

function adicionarProdutoVenda(produtoId) {
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=obter_produto&produto_id=${produtoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const produto = data.produto;
            
            // Verificar se o produto já está na venda
            const itemExistente = itensVenda.find(item => item.produto_id === produto.id);
            
            if (itemExistente) {
                itemExistente.quantidade += 1;
                itemExistente.subtotal = itemExistente.quantidade * itemExistente.preco;
            } else {
                const novoItem = {
                    id: Date.now(),
                    produto_id: produto.id,
                    nome: produto.nome,
                    preco: produto.preco,
                    quantidade: 1,
                    subtotal: produto.preco
                };
                itensVenda.push(novoItem);
            }
            
            atualizarTabelaItens();
            calcularTotais();
        }
    })
    .catch(error => console.error('Erro ao adicionar produto:', error));
}

function atualizarTabelaItens() {
    const tbody = document.querySelector('#tabela_itens_venda tbody');
    tbody.innerHTML = '';
    
    itensVenda.forEach(item => {
        const row = `
            <tr>
                <td>${item.nome}</td>
                <td>
                    <input type="number" class="form-control form-control-sm quantidade-item" 
                           data-item-id="${item.id}" value="${item.quantidade}" min="1">
                </td>
                <td>R$ ${item.preco.toFixed(2).replace('.', ',')}</td>
                <td>R$ ${item.subtotal.toFixed(2).replace('.', ',')}</td>
                <td>
                    <button class="btn btn-danger btn-sm btn-remover-item" data-item-id="${item.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function removerItemVenda(itemId) {
    itensVenda = itensVenda.filter(item => item.id !== parseInt(itemId));
    atualizarTabelaItens();
    calcularTotais();
}

function atualizarQuantidadeItem(itemId, novaQuantidade) {
    const item = itensVenda.find(item => item.id === parseInt(itemId));
    if (item) {
        item.quantidade = novaQuantidade;
        item.subtotal = item.quantidade * item.preco;
        atualizarTabelaItens();
        calcularTotais();
    }
}

function calcularTotais() {
    const subtotal = itensVenda.reduce((total, item) => total + item.subtotal, 0);
    const desconto = parseFloat(document.getElementById('desconto_venda').textContent.replace('R$ ', '').replace(',', '.')) || 0;
    const total = subtotal - desconto;
    
    document.getElementById('subtotal_venda').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    document.getElementById('total_venda').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('total_pagamento').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    
    totalVenda = total;
    verificarPagamentoCompleto();
}

function aplicarDesconto() {
    const valorDesconto = parseFloat(document.getElementById('valor_desconto').value) || 0;
    const tipoDesconto = document.getElementById('tipo_desconto').value;
    
    if (valorDesconto <= 0) {
        alert('Digite um valor de desconto válido');
        return;
    }
    
    let descontoFinal = valorDesconto;
    
    if (tipoDesconto === 'percentual') {
        const subtotal = itensVenda.reduce((total, item) => total + item.subtotal, 0);
        descontoFinal = (subtotal * valorDesconto) / 100;
    }
    
    document.getElementById('desconto_venda').textContent = 'R$ ' + descontoFinal.toFixed(2).replace('.', ',');
    calcularTotais();
}

function abrirModalPagamento(forma) {
    document.getElementById('forma_pagamento_selecionada').value = forma;
    document.getElementById('valor_pagamento').value = '';
    document.getElementById('valor_troco').value = '';
    document.getElementById('observacoes_pagamento').value = '';
    
    // Mostrar/ocultar campo de troco baseado na forma de pagamento
    const trocoContainer = document.getElementById('troco_container');
    if (forma === 'dinheiro') {
        trocoContainer.style.display = 'block';
    } else {
        trocoContainer.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('modalPagamento')).show();
}

function adicionarPagamento() {
    const forma = document.getElementById('forma_pagamento_selecionada').value;
    const valor = parseFloat(document.getElementById('valor_pagamento').value) || 0;
    const observacoes = document.getElementById('observacoes_pagamento').value;
    
    if (valor <= 0) {
        alert('Digite um valor válido');
        return;
    }
    
    const pagamento = {
        id: Date.now(),
        forma: forma,
        valor: valor,
        observacoes: observacoes
    };
    
    pagamentosVenda.push(pagamento);
    atualizarPagamentos();
    calcularTotais();
    
    bootstrap.Modal.getInstance(document.getElementById('modalPagamento')).hide();
}

function atualizarPagamentos() {
    const container = document.getElementById('pagamentos_adicionados');
    container.innerHTML = '';
    
    pagamentosVenda.forEach(pagamento => {
        const pagamentoHtml = `
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${getFormaPagamentoNome(pagamento.forma)}</strong>
                            <br>
                            <small>R$ ${pagamento.valor.toFixed(2).replace('.', ',')}</small>
                        </div>
                        <button class="btn btn-danger btn-sm btn-remover-pagamento" data-pagamento-id="${pagamento.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += pagamentoHtml;
    });
    
    // Calcular total pago
    totalPago = pagamentosVenda.reduce((total, pagamento) => total + pagamento.valor, 0);
    document.getElementById('total_pago').textContent = 'R$ ' + totalPago.toFixed(2).replace('.', ',');
    
    // Calcular troco
    const troco = totalPago - totalVenda;
    document.getElementById('troco').textContent = 'R$ ' + Math.max(0, troco).toFixed(2).replace('.', ',');
}

function removerPagamento(pagamentoId) {
    pagamentosVenda = pagamentosVenda.filter(pagamento => pagamento.id !== parseInt(pagamentoId));
    atualizarPagamentos();
    verificarPagamentoCompleto();
}

function getFormaPagamentoNome(forma) {
    const formas = {
        'dinheiro': 'Dinheiro',
        'pix': 'PIX',
        'cartao_debito': 'Cartão Débito',
        'cartao_credito': 'Cartão Crédito',
        'transferencia': 'Transferência'
    };
    return formas[forma] || forma;
}

function verificarPagamentoCompleto() {
    const btnFinalizar = document.getElementById('btn_finalizar_venda');
    const pagamentoCompleto = totalPago >= totalVenda;
    
    btnFinalizar.disabled = !pagamentoCompleto;
    
    if (pagamentoCompleto) {
        btnFinalizar.classList.remove('btn-secondary');
        btnFinalizar.classList.add('btn-success');
    } else {
        btnFinalizar.classList.remove('btn-success');
        btnFinalizar.classList.add('btn-secondary');
    }
}

function finalizarVenda() {
    if (itensVenda.length === 0) {
        alert('Adicione pelo menos um item à venda');
        return;
    }
    
    if (totalPago < totalVenda) {
        alert('Pagamento incompleto');
        return;
    }
    
    const clienteId = document.getElementById('cliente_venda').value;
    const tipoVenda = document.getElementById('tipo_venda').value;
    const observacoes = document.getElementById('observacoes_pagamento').value;
    
    const dadosVenda = {
        acao: 'finalizar_venda',
        cliente_id: clienteId,
        tipo_venda: tipoVenda,
        itens: itensVenda,
        pagamentos: pagamentosVenda,
        observacoes: observacoes
    };
    
    fetch('mvc/ajax/caixa_avancado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dadosVenda)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Venda finalizada com sucesso!');
            limparVenda();
        } else {
            alert('Erro ao finalizar venda: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao finalizar venda');
    });
}

function cancelarVenda() {
    if (confirm('Tem certeza que deseja cancelar esta venda?')) {
        limparVenda();
    }
}

function limparVenda() {
    itensVenda = [];
    pagamentosVenda = [];
    totalVenda = 0;
    totalPago = 0;
    
    document.getElementById('tabela_itens_venda').querySelector('tbody').innerHTML = '';
    document.getElementById('pagamentos_adicionados').innerHTML = '';
    document.getElementById('subtotal_venda').textContent = 'R$ 0,00';
    document.getElementById('desconto_venda').textContent = 'R$ 0,00';
    document.getElementById('total_venda').textContent = 'R$ 0,00';
    document.getElementById('total_pagamento').textContent = 'R$ 0,00';
    document.getElementById('total_pago').textContent = 'R$ 0,00';
    document.getElementById('troco').textContent = 'R$ 0,00';
    document.getElementById('valor_desconto').value = '';
    document.getElementById('btn_finalizar_venda').disabled = true;
}

function fecharCaixa() {
    if (confirm('Tem certeza que deseja fechar o caixa?')) {
        fetch('mvc/ajax/caixa_avancado.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'acao=fechar_caixa'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Caixa fechado com sucesso!');
                location.reload();
            } else {
                alert('Erro ao fechar caixa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao fechar caixa');
        });
    }
}
</script>
