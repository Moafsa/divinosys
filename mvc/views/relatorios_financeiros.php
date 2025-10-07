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
    <h1 class="mb-4">Relatórios Financeiros</h1>
    <p>Relatórios completos de vendas, financeiro, clientes e operações do estabelecimento.</p>
    
    <!-- Filtros Gerais -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Filtros do Relatório</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" id="data_inicio" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" id="data_fim" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="filial_relatorio" class="form-label">Filial</label>
                            <select id="filial_relatorio" class="form-select">
                                <option value="">Todas as filiais</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button id="btn_aplicar_filtros" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Resumo -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Total Vendas</h6>
                            <h4 id="total_vendas">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shopping-cart fa-2x"></i>
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
                            <h6>Vendas Fiadas</h6>
                            <h4 id="vendas_fiadas">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-credit-card fa-2x"></i>
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
                            <h6>Descontos</h6>
                            <h4 id="total_descontos">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-percentage fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Cortesias</h6>
                            <h4 id="total_cortesias">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-gift fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Abas de Relatórios -->
    <ul class="nav nav-tabs" id="relatoriosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="vendas-tab" data-bs-toggle="tab" data-bs-target="#vendas" type="button" role="tab">
                <i class="fas fa-chart-line"></i> Vendas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab">
                <i class="fas fa-money-bill-wave"></i> Financeiro
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" type="button" role="tab">
                <i class="fas fa-users"></i> Clientes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="produtos-tab" data-bs-toggle="tab" data-bs-target="#produtos" type="button" role="tab">
                <i class="fas fa-box"></i> Produtos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="operacional-tab" data-bs-toggle="tab" data-bs-target="#operacional" type="button" role="tab">
                <i class="fas fa-cogs"></i> Operacional
            </button>
        </li>
    </ul>

    <!-- Conteúdo das Abas -->
    <div class="tab-content" id="relatoriosTabContent">
        
        <!-- Aba Vendas -->
        <div class="tab-pane fade show active" id="vendas" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatório de Vendas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="tipo_venda_relatorio" class="form-label">Tipo de Venda</label>
                                    <select id="tipo_venda_relatorio" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="normal">Vendas Normais</option>
                                        <option value="fiado">Vendas Fiadas</option>
                                        <option value="cortesia">Cortesias</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="forma_pagamento_relatorio" class="form-label">Forma de Pagamento</label>
                                    <select id="forma_pagamento_relatorio" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="pix">PIX</option>
                                        <option value="cartao_debito">Cartão Débito</option>
                                        <option value="cartao_credito">Cartão Crédito</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_vendas" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-bar"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_vendas_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Financeiro -->
        <div class="tab-pane fade" id="financeiro" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatório Financeiro</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="categoria_financeira" class="form-label">Categoria</label>
                                    <select id="categoria_financeira" class="form-select">
                                        <option value="">Todas</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="tipo_movimentacao" class="form-label">Tipo de Movimentação</label>
                                    <select id="tipo_movimentacao" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="entrada">Entradas</option>
                                        <option value="saida">Saídas</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_financeiro" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-pie"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_financeiro_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Clientes -->
        <div class="tab-pane fade" id="clientes" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatório de Clientes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="status_cliente" class="form-label">Status do Cliente</label>
                                    <select id="status_cliente" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo">Ativos</option>
                                        <option value="inativo">Inativos</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="tipo_cliente" class="form-label">Tipo de Cliente</label>
                                    <select id="tipo_cliente" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="fiado">Clientes Fiado</option>
                                        <option value="normal">Clientes Normais</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_clientes" class="btn btn-primary w-100">
                                        <i class="fas fa-users"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_clientes_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Produtos -->
        <div class="tab-pane fade" id="produtos" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatório de Produtos</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="categoria_produto" class="form-label">Categoria</label>
                                    <select id="categoria_produto" class="form-select">
                                        <option value="">Todas</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="status_produto" class="form-label">Status</label>
                                    <select id="status_produto" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo">Ativos</option>
                                        <option value="inativo">Inativos</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_produtos" class="btn btn-primary w-100">
                                        <i class="fas fa-box"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_produtos_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Operacional -->
        <div class="tab-pane fade" id="operacional" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatório Operacional</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="tipo_operacao" class="form-label">Tipo de Operação</label>
                                    <select id="tipo_operacao" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="vendas">Vendas</option>
                                        <option value="estoque">Estoque</option>
                                        <option value="caixa">Caixa</option>
                                        <option value="usuarios">Usuários</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="usuario_operacao" class="form-label">Usuário</label>
                                    <select id="usuario_operacao" class="form-select">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_operacional" class="btn btn-primary w-100">
                                        <i class="fas fa-cogs"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_operacional_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    carregarFiliais();
    carregarCategoriasFinanceiras();
    carregarCategoriasProdutos();
    carregarUsuarios();
    
    // Definir datas padrão (últimos 30 dias)
    const hoje = new Date();
    const trintaDiasAtras = new Date(hoje.getTime() - (30 * 24 * 60 * 60 * 1000));
    
    document.getElementById('data_inicio').value = trintaDiasAtras.toISOString().split('T')[0];
    document.getElementById('data_fim').value = hoje.toISOString().split('T')[0];
    
    // Event listeners
    document.getElementById('btn_aplicar_filtros').addEventListener('click', aplicarFiltros);
    document.getElementById('btn_gerar_relatorio_vendas').addEventListener('click', gerarRelatorioVendas);
    document.getElementById('btn_gerar_relatorio_financeiro').addEventListener('click', gerarRelatorioFinanceiro);
    document.getElementById('btn_gerar_relatorio_clientes').addEventListener('click', gerarRelatorioClientes);
    document.getElementById('btn_gerar_relatorio_produtos').addEventListener('click', gerarRelatorioProdutos);
    document.getElementById('btn_gerar_relatorio_operacional').addEventListener('click', gerarRelatorioOperacional);
});

function carregarFiliais() {
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_filiais'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('filial_relatorio');
            select.innerHTML = '<option value="">Todas as filiais</option>';
            
            data.filiais.forEach(filial => {
                select.innerHTML += `<option value="${filial.id}">${filial.nome}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar filiais:', error));
}

function carregarCategoriasFinanceiras() {
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_categorias_financeiras'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('categoria_financeira');
            select.innerHTML = '<option value="">Todas</option>';
            
            data.categorias.forEach(categoria => {
                select.innerHTML += `<option value="${categoria.id}">${categoria.nome}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar categorias financeiras:', error));
}

function carregarCategoriasProdutos() {
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_categorias_produtos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('categoria_produto');
            select.innerHTML = '<option value="">Todas</option>';
            
            data.categorias.forEach(categoria => {
                select.innerHTML += `<option value="${categoria.id}">${categoria.nome}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar categorias de produtos:', error));
}

function carregarUsuarios() {
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_usuarios'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('usuario_operacao');
            select.innerHTML = '<option value="">Todos</option>';
            
            data.usuarios.forEach(usuario => {
                select.innerHTML += `<option value="${usuario.id}">${usuario.nome}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar usuários:', error));
}

function aplicarFiltros() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    
    // Carregar resumo geral
    carregarResumoGeral(dataInicio, dataFim, filial);
}

function carregarResumoGeral(dataInicio, dataFim, filial) {
    const params = new URLSearchParams({
        acao: 'resumo_geral',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('total_vendas').textContent = 'R$ ' + data.resumo.total_vendas.toFixed(2).replace('.', ',');
            document.getElementById('vendas_fiadas').textContent = 'R$ ' + data.resumo.vendas_fiadas.toFixed(2).replace('.', ',');
            document.getElementById('total_descontos').textContent = 'R$ ' + data.resumo.total_descontos.toFixed(2).replace('.', ',');
            document.getElementById('total_cortesias').textContent = 'R$ ' + data.resumo.total_cortesias.toFixed(2).replace('.', ',');
        }
    })
    .catch(error => console.error('Erro ao carregar resumo geral:', error));
}

function gerarRelatorioVendas() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    const tipoVenda = document.getElementById('tipo_venda_relatorio').value;
    const formaPagamento = document.getElementById('forma_pagamento_relatorio').value;
    
    const params = new URLSearchParams({
        acao: 'relatorio_vendas',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial,
        tipo_venda: tipoVenda,
        forma_pagamento: formaPagamento
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_vendas_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório de vendas: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório de vendas');
    });
}

function gerarRelatorioFinanceiro() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    const categoria = document.getElementById('categoria_financeira').value;
    const tipoMovimentacao = document.getElementById('tipo_movimentacao').value;
    
    const params = new URLSearchParams({
        acao: 'relatorio_financeiro',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial,
        categoria: categoria,
        tipo_movimentacao: tipoMovimentacao
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_financeiro_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório financeiro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório financeiro');
    });
}

function gerarRelatorioClientes() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    const statusCliente = document.getElementById('status_cliente').value;
    const tipoCliente = document.getElementById('tipo_cliente').value;
    
    const params = new URLSearchParams({
        acao: 'relatorio_clientes',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial,
        status_cliente: statusCliente,
        tipo_cliente: tipoCliente
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_clientes_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório de clientes: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório de clientes');
    });
}

function gerarRelatorioProdutos() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    const categoria = document.getElementById('categoria_produto').value;
    const status = document.getElementById('status_produto').value;
    
    const params = new URLSearchParams({
        acao: 'relatorio_produtos',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial,
        categoria: categoria,
        status: status
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_produtos_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório de produtos: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório de produtos');
    });
}

function gerarRelatorioOperacional() {
    const dataInicio = document.getElementById('data_inicio').value;
    const dataFim = document.getElementById('data_fim').value;
    const filial = document.getElementById('filial_relatorio').value;
    const tipoOperacao = document.getElementById('tipo_operacao').value;
    const usuario = document.getElementById('usuario_operacao').value;
    
    const params = new URLSearchParams({
        acao: 'relatorio_operacional',
        data_inicio: dataInicio,
        data_fim: dataFim,
        filial: filial,
        tipo_operacao: tipoOperacao,
        usuario: usuario
    });
    
    fetch('mvc/ajax/relatorios_financeiros.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_operacional_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório operacional: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório operacional');
    });
}
</script>
