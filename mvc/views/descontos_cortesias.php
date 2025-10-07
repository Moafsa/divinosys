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
    <h1 class="mb-4">Descontos e Cortesias</h1>
    <p>Gerencie descontos, cortesias e promoções do estabelecimento.</p>
    
    <!-- Abas de Navegação -->
    <ul class="nav nav-tabs" id="descontosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="descontos-tab" data-bs-toggle="tab" data-bs-target="#descontos" type="button" role="tab">
                <i class="fas fa-percentage"></i> Descontos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cortesias-tab" data-bs-toggle="tab" data-bs-target="#cortesias" type="button" role="tab">
                <i class="fas fa-gift"></i> Cortesias
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="promocoes-tab" data-bs-toggle="tab" data-bs-target="#promocoes" type="button" role="tab">
                <i class="fas fa-tags"></i> Promoções
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="relatorios-tab" data-bs-toggle="tab" data-bs-target="#relatorios" type="button" role="tab">
                <i class="fas fa-chart-bar"></i> Relatórios
            </button>
        </li>
    </ul>

    <!-- Conteúdo das Abas -->
    <div class="tab-content" id="descontosTabContent">
        
        <!-- Aba Descontos -->
        <div class="tab-pane fade show active" id="descontos" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Novo Desconto</h5>
                        </div>
                        <div class="card-body">
                            <form id="formNovoDesconto">
                                <div class="mb-3">
                                    <label for="nome_desconto" class="form-label">Nome do Desconto *</label>
                                    <input type="text" id="nome_desconto" name="nome_desconto" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tipo_desconto" class="form-label">Tipo de Desconto *</label>
                                    <select id="tipo_desconto" name="tipo_desconto" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="percentual">Percentual (%)</option>
                                        <option value="valor_fixo">Valor Fixo (R$)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="valor_desconto" class="form-label">Valor do Desconto *</label>
                                    <input type="number" id="valor_desconto" name="valor_desconto" class="form-control" step="0.01" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="valor_minimo" class="form-label">Valor Mínimo do Pedido</label>
                                    <input type="number" id="valor_minimo" name="valor_minimo" class="form-control" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="valor_maximo" class="form-label">Valor Máximo do Desconto</label>
                                    <input type="number" id="valor_maximo" name="valor_maximo" class="form-control" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="data_inicio" class="form-label">Data de Início</label>
                                    <input type="datetime-local" id="data_inicio" name="data_inicio" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="data_fim" class="form-label">Data de Fim</label>
                                    <input type="datetime-local" id="data_fim" name="data_fim" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="usos_maximos" class="form-label">Usos Máximos</label>
                                    <input type="number" id="usos_maximos" name="usos_maximos" class="form-control" min="1">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="observacoes_desconto" class="form-label">Observações</label>
                                    <textarea id="observacoes_desconto" name="observacoes_desconto" class="form-control" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-save"></i> Salvar Desconto
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Descontos Cadastrados</h5>
                            <button id="btn_atualizar_descontos" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_descontos" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Usos</th>
                                            <th>Período</th>
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
        
        <!-- Aba Cortesias -->
        <div class="tab-pane fade" id="cortesias" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Nova Cortesia</h5>
                        </div>
                        <div class="card-body">
                            <form id="formNovaCortesia">
                                <div class="mb-3">
                                    <label for="nome_cortesia" class="form-label">Nome da Cortesia *</label>
                                    <input type="text" id="nome_cortesia" name="nome_cortesia" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tipo_cortesia" class="form-label">Tipo de Cortesia *</label>
                                    <select id="tipo_cortesia" name="tipo_cortesia" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="produto_gratis">Produto Grátis</option>
                                        <option value="desconto_total">Desconto Total</option>
                                        <option value="brinde">Brinde</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="produto_cortesia_container" style="display: none;">
                                    <label for="produto_cortesia" class="form-label">Produto</label>
                                    <select id="produto_cortesia" name="produto_cortesia" class="form-select">
                                        <option value="">Selecione um produto</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="valor_cortesia" class="form-label">Valor da Cortesia</label>
                                    <input type="number" id="valor_cortesia" name="valor_cortesia" class="form-control" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="motivo_cortesia" class="form-label">Motivo da Cortesia *</label>
                                    <textarea id="motivo_cortesia" name="motivo_cortesia" class="form-control" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="responsavel_cortesia" class="form-label">Responsável pela Cortesia</label>
                                    <input type="text" id="responsavel_cortesia" name="responsavel_cortesia" class="form-control">
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-gift"></i> Salvar Cortesia
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Cortesias Registradas</h5>
                            <button id="btn_atualizar_cortesias" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_cortesias" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Valor</th>
                                            <th>Motivo</th>
                                            <th>Responsável</th>
                                            <th>Data</th>
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
        
        <!-- Aba Promoções -->
        <div class="tab-pane fade" id="promocoes" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Nova Promoção</h5>
                        </div>
                        <div class="card-body">
                            <form id="formNovaPromocao">
                                <div class="mb-3">
                                    <label for="nome_promocao" class="form-label">Nome da Promoção *</label>
                                    <input type="text" id="nome_promocao" name="nome_promocao" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tipo_promocao" class="form-label">Tipo de Promoção *</label>
                                    <select id="tipo_promocao" name="tipo_promocao" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="leve_pague">Leve X Pague Y</option>
                                        <option value="desconto_progressivo">Desconto Progressivo</option>
                                        <option value="combo">Combo</option>
                                        <option value="cashback">Cashback</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="regras_promocao" class="form-label">Regras da Promoção *</label>
                                    <textarea id="regras_promocao" name="regras_promocao" class="form-control" rows="4" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="data_inicio_promocao" class="form-label">Data de Início *</label>
                                    <input type="datetime-local" id="data_inicio_promocao" name="data_inicio_promocao" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="data_fim_promocao" class="form-label">Data de Fim *</label>
                                    <input type="datetime-local" id="data_fim_promocao" name="data_fim_promocao" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="ativo_promocao" class="form-label">Status</label>
                                    <select id="ativo_promocao" name="ativo_promocao" class="form-select">
                                        <option value="1">Ativa</option>
                                        <option value="0">Inativa</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-tags"></i> Salvar Promoção
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Promoções Ativas</h5>
                            <button id="btn_atualizar_promocoes" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_promocoes" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Período</th>
                                            <th>Status</th>
                                            <th>Usos</th>
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
        
        <!-- Aba Relatórios -->
        <div class="tab-pane fade" id="relatorios" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatórios de Descontos e Cortesias</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label for="relatorio_data_inicio" class="form-label">Data Início</label>
                                    <input type="date" id="relatorio_data_inicio" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label for="relatorio_data_fim" class="form-label">Data Fim</label>
                                    <input type="date" id="relatorio_data_fim" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label for="relatorio_tipo" class="form-label">Tipo</label>
                                    <select id="relatorio_tipo" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="desconto">Descontos</option>
                                        <option value="cortesia">Cortesias</option>
                                        <option value="promocao">Promoções</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-bar"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_content">
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
    carregarDescontos();
    carregarCortesias();
    carregarPromocoes();
    carregarProdutos();
    
    // Event listeners para formulários
    document.getElementById('formNovoDesconto').addEventListener('submit', salvarDesconto);
    document.getElementById('formNovaCortesia').addEventListener('submit', salvarCortesia);
    document.getElementById('formNovaPromocao').addEventListener('submit', salvarPromocao);
    
    // Event listeners para botões
    document.getElementById('btn_atualizar_descontos').addEventListener('click', carregarDescontos);
    document.getElementById('btn_atualizar_cortesias').addEventListener('click', carregarCortesias);
    document.getElementById('btn_atualizar_promocoes').addEventListener('click', carregarPromocoes);
    document.getElementById('btn_gerar_relatorio').addEventListener('click', gerarRelatorio);
    
    // Event listener para tipo de cortesia
    document.getElementById('tipo_cortesia').addEventListener('change', function() {
        const container = document.getElementById('produto_cortesia_container');
        if (this.value === 'produto_gratis') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    });
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-editar-desconto')) {
            const id = e.target.dataset.id;
            editarDesconto(id);
        }
        
        if (e.target.classList.contains('btn-excluir-desconto')) {
            const id = e.target.dataset.id;
            excluirDesconto(id);
        }
        
        if (e.target.classList.contains('btn-editar-cortesia')) {
            const id = e.target.dataset.id;
            editarCortesia(id);
        }
        
        if (e.target.classList.contains('btn-excluir-cortesia')) {
            const id = e.target.dataset.id;
            excluirCortesia(id);
        }
    });
});

function carregarDescontos() {
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_descontos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_descontos tbody');
            tbody.innerHTML = '';
            
            data.descontos.forEach(desconto => {
                const statusClass = desconto.ativo ? 'bg-success' : 'bg-secondary';
                const statusText = desconto.ativo ? 'Ativo' : 'Inativo';
                
                const row = `
                    <tr>
                        <td>${desconto.id}</td>
                        <td>${desconto.nome}</td>
                        <td>${desconto.tipo}</td>
                        <td>${desconto.tipo === 'percentual' ? desconto.valor + '%' : 'R$ ' + desconto.valor}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${desconto.usos_atual}/${desconto.usos_maximo || '∞'}</td>
                        <td>${formatarPeriodo(desconto.data_inicio, desconto.data_fim)}</td>
                        <td>
                            <button class="btn btn-info btn-sm btn-editar-desconto" data-id="${desconto.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-excluir-desconto" data-id="${desconto.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar descontos:', error));
}

function carregarCortesias() {
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_cortesias'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_cortesias tbody');
            tbody.innerHTML = '';
            
            data.cortesias.forEach(cortesia => {
                const row = `
                    <tr>
                        <td>${cortesia.id}</td>
                        <td>${cortesia.nome}</td>
                        <td>${cortesia.tipo}</td>
                        <td>R$ ${cortesia.valor.toFixed(2)}</td>
                        <td>${cortesia.motivo}</td>
                        <td>${cortesia.responsavel}</td>
                        <td>${formatarData(cortesia.data_cortesia)}</td>
                        <td>
                            <button class="btn btn-info btn-sm btn-editar-cortesia" data-id="${cortesia.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-excluir-cortesia" data-id="${cortesia.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar cortesias:', error));
}

function carregarPromocoes() {
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_promocoes'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_promocoes tbody');
            tbody.innerHTML = '';
            
            data.promocoes.forEach(promocao => {
                const statusClass = promocao.ativo ? 'bg-success' : 'bg-secondary';
                const statusText = promocao.ativo ? 'Ativa' : 'Inativa';
                
                const row = `
                    <tr>
                        <td>${promocao.id}</td>
                        <td>${promocao.nome}</td>
                        <td>${promocao.tipo}</td>
                        <td>${formatarPeriodo(promocao.data_inicio, promocao.data_fim)}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${promocao.usos_atual || 0}</td>
                        <td>
                            <button class="btn btn-info btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar promoções:', error));
}

function carregarProdutos() {
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_produtos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('produto_cortesia');
            select.innerHTML = '<option value="">Selecione um produto</option>';
            
            data.produtos.forEach(produto => {
                select.innerHTML += `<option value="${produto.id}">${produto.nome} - R$ ${produto.preco.toFixed(2)}</option>`;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar produtos:', error));
}

function salvarDesconto(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_desconto');
    
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Desconto salvo com sucesso!');
            e.target.reset();
            carregarDescontos();
        } else {
            alert('Erro ao salvar desconto: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar desconto');
    });
}

function salvarCortesia(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_cortesia');
    
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Cortesia salva com sucesso!');
            e.target.reset();
            carregarCortesias();
        } else {
            alert('Erro ao salvar cortesia: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar cortesia');
    });
}

function salvarPromocao(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_promocao');
    
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Promoção salva com sucesso!');
            e.target.reset();
            carregarPromocoes();
        } else {
            alert('Erro ao salvar promoção: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar promoção');
    });
}

function gerarRelatorio() {
    const dataInicio = document.getElementById('relatorio_data_inicio').value;
    const dataFim = document.getElementById('relatorio_data_fim').value;
    const tipo = document.getElementById('relatorio_tipo').value;
    
    const params = new URLSearchParams({
        acao: 'gerar_relatorio',
        data_inicio: dataInicio,
        data_fim: dataFim,
        tipo: tipo
    });
    
    fetch('mvc/ajax/descontos_cortesias.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório');
    });
}

// Funções auxiliares
function formatarData(data) {
    return new Date(data).toLocaleDateString('pt-BR');
}

function formatarPeriodo(dataInicio, dataFim) {
    if (!dataInicio && !dataFim) return 'Sem período definido';
    if (!dataInicio) return `Até ${formatarData(dataFim)}`;
    if (!dataFim) return `A partir de ${formatarData(dataInicio)}`;
    return `${formatarData(dataInicio)} - ${formatarData(dataFim)}`;
}
</script>
