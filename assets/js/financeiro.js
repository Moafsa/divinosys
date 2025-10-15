/**
 * JavaScript para Sistema Financeiro
 * Divino Lanches
 */

class FinancialSystem {
    constructor() {
        this.currentReportData = null;
        this.currentReportType = null;
        this.charts = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeComponents();
    }

    bindEvents() {
        // Verificar se jQuery está disponível
        if (typeof $ === 'undefined') {
            console.error('jQuery não está disponível');
            return;
        }
        
        // Eventos para novo lançamento
        $(document).on('change', '#tipo', this.handleTipoChange.bind(this));
        $(document).on('change', '#recorrencia', this.handleRecorrenciaChange.bind(this));
        $(document).on('input', '#valor', this.updateResumo.bind(this));
        $(document).on('change', '#conta_id, #categoria_id', this.updateResumo.bind(this));
        
        // Eventos para upload de arquivos
        this.setupFileUpload();
        
        // Eventos para formulários
        $(document).on('submit', '#lancamentoForm', this.handleLancamentoSubmit.bind(this));
    }

    initializeComponents() {
        // Inicializar Select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.form-select').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        // Inicializar tooltips
        if (typeof bootstrap !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    // ===== NOVO LANÇAMENTO =====

    handleTipoChange() {
        const tipo = $('#tipo').val();
        
        // Mostrar/ocultar conta destino para transferências
        if (tipo === 'transferencia') {
            $('#conta_destino_section').show();
            $('#conta_destino_id').prop('required', true);
        } else {
            $('#conta_destino_section').hide();
            $('#conta_destino_id').prop('required', false);
        }
        
        // Filtrar categorias por tipo
        this.filterCategoriasByTipo(tipo);
        this.updateResumo();
    }

    filterCategoriasByTipo(tipo) {
        $('#categoria_id option').each(function() {
            const categoriaTipo = $(this).data('tipo');
            if (tipo === '' || categoriaTipo === tipo || categoriaTipo === 'investimento') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        $('#categoria_id').val('').trigger('change');
    }

    handleRecorrenciaChange() {
        const recorrencia = $('#recorrencia').val();
        if (recorrencia !== 'nenhuma') {
            $('#fim_recorrencia_section').show();
        } else {
            $('#fim_recorrencia_section').hide();
        }
    }

    updateResumo() {
        const tipo = $('#tipo option:selected').text();
        const valor = $('#valor').val();
        const conta = $('#conta_id option:selected').text();
        const categoria = $('#categoria_id option:selected').text();

        $('#resumo_tipo').text(tipo || '-');
        $('#resumo_valor').text(valor ? `R$ ${parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}` : '-');
        $('#resumo_conta').text(conta || '-');
        $('#resumo_categoria').text(categoria || '-');
        $('#resumo_total').text(valor ? `R$ ${parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}` : 'R$ 0,00');
    }

    setupFileUpload() {
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('anexos');
        const filePreviews = document.getElementById('filePreviews');

        if (!fileUploadArea || !fileInput || !filePreviews) return;

        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', this.handleDragOver.bind(this));
        fileUploadArea.addEventListener('dragleave', this.handleDragLeave.bind(this));
        fileUploadArea.addEventListener('drop', this.handleDrop.bind(this));
        fileInput.addEventListener('change', this.handleFileSelect.bind(this));
    }

    handleDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('dragover');
    }

    handleDragLeave(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('dragover');
    }

    handleDrop(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('dragover');
        const files = e.dataTransfer.files;
        this.handleFiles(files);
    }

    handleFileSelect(e) {
        const files = e.target.files;
        this.handleFiles(files);
    }

    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (file.size > 5 * 1024 * 1024) {
                this.showAlert('Erro!', 'Arquivo muito grande. Máximo 5MB.', 'error');
                return;
            }
            
            const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                this.showAlert('Erro!', 'Tipo de arquivo não permitido.', 'error');
                return;
            }

            this.addFilePreview(file);
        });
    }

    addFilePreview(file) {
        const filePreviews = document.getElementById('filePreviews');
        if (!filePreviews) return;

        const preview = document.createElement('div');
        preview.className = 'file-preview';
        preview.innerHTML = `
            <div>
                <i class="fas fa-file me-2"></i>
                <span>${file.name}</span>
                <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="financialSystem.removeFilePreview(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        filePreviews.appendChild(preview);
    }

    removeFilePreview(button) {
        button.parentElement.remove();
    }

    handleLancamentoSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        formData.append('action', 'criar_lancamento');

        this.showLoading('Salvando...', 'Criando lançamento financeiro...');

        fetch('mvc/ajax/financeiro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Sucesso!', 'Lançamento criado com sucesso!', 'success', () => {
                    window.location.href = 'index.php?view=financeiro';
                });
            } else {
                this.showAlert('Erro!', data.message || 'Erro ao criar lançamento', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            this.showAlert('Erro!', 'Erro ao processar solicitação', 'error');
        });
    }

    salvarRascunho() {
        const form = document.getElementById('lancamentoForm');
        if (!form) return;

        const formData = new FormData(form);
        formData.append('action', 'salvar_rascunho');

        this.showLoading('Salvando Rascunho...', 'Salvando como rascunho...');

        fetch('mvc/ajax/financeiro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert('Sucesso!', 'Rascunho salvo com sucesso!', 'success');
            } else {
                this.showAlert('Erro!', data.message || 'Erro ao salvar rascunho', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            this.showAlert('Erro!', 'Erro ao processar solicitação', 'error');
        });
    }

    limparFormulario() {
        this.showConfirm(
            'Limpar Formulário',
            'Tem certeza que deseja limpar todos os campos?',
            'warning',
            () => {
                const form = document.getElementById('lancamentoForm');
                if (form) {
                    form.reset();
                    $('#categoria_id, #conta_id, #conta_destino_id').val('').trigger('change');
                    const filePreviews = document.getElementById('filePreviews');
                    if (filePreviews) filePreviews.innerHTML = '';
                    this.updateResumo();
                }
            }
        );
    }

    // ===== RELATÓRIOS =====

    gerarRelatorio(tipo) {
        const form = document.getElementById('filtroRelatorioForm');
        if (!form) return;

        const formData = new FormData(form);
        formData.append('action', 'gerar_relatorio');
        formData.append('tipo', tipo);

        this.showLoading('Gerando Relatório...', 'Processando dados financeiros...');

        fetch('mvc/ajax/financeiro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.currentReportData = data.data;
                this.currentReportType = tipo;
                this.exibirRelatorio(data.data, tipo);
                this.enableReportButtons();
                this.closeLoading();
            } else {
                this.showAlert('Erro!', data.message || 'Erro ao gerar relatório', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            this.showAlert('Erro!', 'Erro ao processar solicitação', 'error');
        });
    }

    exibirRelatorio(dados, tipo) {
        const content = document.getElementById('relatorioContent');
        if (!content) return;
        
        switch(tipo) {
            case 'fluxo_caixa':
                this.exibirFluxoCaixa(dados);
                break;
            case 'receitas_categoria':
                this.exibirReceitasCategoria(dados);
                break;
            case 'despesas_categoria':
                this.exibirDespesasCategoria(dados);
                break;
            case 'lucro_prejuizo':
                this.exibirLucroPrejuizo(dados);
                break;
            case 'vendas_periodo':
                this.exibirVendasPeriodo(dados);
                break;
            case 'completo':
                this.exibirRelatorioCompleto(dados);
                break;
            default:
                content.innerHTML = '<div class="text-center py-5"><h5 class="text-muted">Tipo de relatório não reconhecido</h5></div>';
        }
    }

    exibirFluxoCaixa(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5>Total Receitas</h5>
                            <h3>R$ ${dados.total_receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h5>Total Despesas</h5>
                            <h3>R$ ${dados.total_despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5>Saldo Líquido</h5>
                            <h3>R$ ${dados.saldo_liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5>Total Lançamentos</h5>
                            <h3>${dados.total_lancamentos}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="fluxoCaixaChart"></canvas>
            </div>
        `;

        this.createFluxoCaixaChart(dados);
    }

    createFluxoCaixaChart(dados) {
        const ctx = document.getElementById('fluxoCaixaChart');
        if (!ctx) return;

        // Destruir gráfico anterior se existir
        if (this.charts.fluxoCaixa) {
            this.charts.fluxoCaixa.destroy();
        }

        this.charts.fluxoCaixa = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dados.periodos,
                datasets: [{
                    label: 'Receitas',
                    data: dados.receitas,
                    borderColor: '#28a745',
                    backgroundColor: '#28a74520',
                    tension: 0.4
                }, {
                    label: 'Despesas',
                    data: dados.despesas,
                    borderColor: '#dc3545',
                    backgroundColor: '#dc354520',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Fluxo de Caixa por Período'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    exibirReceitasCategoria(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="receitasChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5>Detalhamento por Categoria</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dados.categorias.map(cat => `
                                    <tr>
                                        <td>${cat.nome}</td>
                                        <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                        <td>${cat.percentual.toFixed(1)}%</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        this.createReceitasChart(dados);
    }

    createReceitasChart(dados) {
        const ctx = document.getElementById('receitasChart');
        if (!ctx) return;

        if (this.charts.receitas) {
            this.charts.receitas.destroy();
        }

        this.charts.receitas = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: dados.categorias.map(cat => cat.nome),
                datasets: [{
                    data: dados.categorias.map(cat => cat.valor),
                    backgroundColor: [
                        '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1',
                        '#fd7e14', '#20c997', '#6c757d', '#e83e8c', '#343a40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Receitas por Categoria'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    exibirDespesasCategoria(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <canvas id="despesasChart"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <h5>Resumo</h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Despesas:</span>
                                <strong>R$ ${dados.total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Maior Categoria:</span>
                                <strong>${dados.maior_categoria}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Menor Categoria:</span>
                                <strong>${dados.menor_categoria}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.createDespesasChart(dados);
    }

    createDespesasChart(dados) {
        const ctx = document.getElementById('despesasChart');
        if (!ctx) return;

        if (this.charts.despesas) {
            this.charts.despesas.destroy();
        }

        this.charts.despesas = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dados.categorias.map(cat => cat.nome),
                datasets: [{
                    label: 'Despesas',
                    data: dados.categorias.map(cat => cat.valor),
                    backgroundColor: '#dc3545',
                    borderColor: '#dc3545',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Despesas por Categoria'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    exibirLucroPrejuizo(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <canvas id="lucroChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5>Análise de Lucratividade</h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Receitas:</span>
                                <span class="text-success">R$ ${dados.receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Despesas:</span>
                                <span class="text-danger">R$ ${dados.despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Lucro/Prejuízo:</strong>
                                <strong class="${dados.lucro >= 0 ? 'text-success' : 'text-danger'}">
                                    R$ ${dados.lucro.toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span>Margem:</span>
                                <span class="${dados.margem >= 0 ? 'text-success' : 'text-danger'}">
                                    ${dados.margem.toFixed(1)}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    exibirVendasPeriodo(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="chart-container">
                        <canvas id="vendasChart"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <h5>Estatísticas</h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Vendas:</span>
                                <strong>R$ ${dados.total_vendas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Ticket Médio:</span>
                                <strong>R$ ${dados.ticket_medio.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Pedidos:</span>
                                <strong>${dados.total_pedidos}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Melhor Dia:</span>
                                <strong>${dados.melhor_dia}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    exibirRelatorioCompleto(dados) {
        const content = document.getElementById('relatorioContent');
        content.innerHTML = `
            <div class="report-preview">
                <h4>Relatório Financeiro Completo</h4>
                <p class="text-muted">Período: ${dados.periodo_inicio} a ${dados.periodo_fim}</p>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5>Receitas</h5>
                                <h3>R$ ${dados.resumo.receitas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5>Despesas</h5>
                                <h3>R$ ${dados.resumo.despesas.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5>Saldo Líquido</h5>
                                <h3>R$ ${dados.resumo.saldo_liquido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5>Lançamentos</h5>
                                <h3>${dados.resumo.total_lancamentos}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h5>Top 5 Receitas por Categoria</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dados.top_receitas.map(cat => `
                                    <tr>
                                        <td>${cat.nome}</td>
                                        <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Top 5 Despesas por Categoria</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dados.top_despesas.map(cat => `
                                    <tr>
                                        <td>${cat.nome}</td>
                                        <td>R$ ${cat.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    enableReportButtons() {
        const btnExportar = document.getElementById('btnExportar');
        const btnImprimir = document.getElementById('btnImprimir');
        
        if (btnExportar) btnExportar.disabled = false;
        if (btnImprimir) btnImprimir.disabled = false;
    }

    exportarRelatorio() {
        if (!this.currentReportData) {
            this.showAlert('Aviso!', 'Nenhum relatório selecionado', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'exportar_relatorio');
        formData.append('tipo', this.currentReportType);
        formData.append('dados', JSON.stringify(this.currentReportData));

        this.showLoading('Exportando...', 'Preparando arquivo para download...');

        fetch('mvc/ajax/financeiro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `relatorio_${this.currentReportType}_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            this.closeLoading();
        })
        .catch(error => {
            console.error('Erro:', error);
            this.showAlert('Erro!', 'Erro ao exportar relatório', 'error');
        });
    }

    imprimirRelatorio() {
        if (!this.currentReportData) {
            this.showAlert('Aviso!', 'Nenhum relatório selecionado', 'warning');
            return;
        }

        const printWindow = window.open('', '_blank');
        const content = document.getElementById('relatorioContent');
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Relatório Financeiro</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .summary { display: flex; justify-content: space-around; margin: 20px 0; }
                        .summary-item { text-align: center; padding: 10px; border: 1px solid #ddd; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Relatório Financeiro</h1>
                        <p>Gerado em: ${new Date().toLocaleDateString('pt-BR')}</p>
                    </div>
                    <div id="printContent">
                        ${content.innerHTML}
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    // ===== UTILITÁRIOS =====

    showAlert(title, text, icon, callback = null) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: icon
            }).then(() => {
                if (callback) callback();
            });
        } else {
            alert(`${title}: ${text}`);
            if (callback) callback();
        }
    }

    showConfirm(title, text, icon, confirmCallback, cancelCallback = null) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed && confirmCallback) {
                    confirmCallback();
                } else if (result.isDismissed && cancelCallback) {
                    cancelCallback();
                }
            });
        } else {
            if (confirm(`${title}: ${text}`)) {
                if (confirmCallback) confirmCallback();
            } else if (cancelCallback) {
                cancelCallback();
            }
        }
    }

    showLoading(title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: text,
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });
        }
    }

    closeLoading() {
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
    }

    // ===== FUNÇÕES GLOBAIS =====

    // Funções para serem chamadas globalmente
    static abrirModalLancamento() {
        window.location.href = 'index.php?view=novo_lancamento';
    }

    static abrirModalRelatorio() {
        window.location.href = 'index.php?view=gerar_relatorios';
    }

    static gerarRelatorio(tipo) {
        if (window.financialSystem) {
            window.financialSystem.gerarRelatorio(tipo);
        }
    }

    static exportarRelatorio() {
        if (window.financialSystem) {
            window.financialSystem.exportarRelatorio();
        }
    }

    static imprimirRelatorio() {
        if (window.financialSystem) {
            window.financialSystem.imprimirRelatorio();
        }
    }

    static salvarRascunho() {
        if (window.financialSystem) {
            window.financialSystem.salvarRascunho();
        }
    }

    static limparFormulario() {
        if (window.financialSystem) {
            window.financialSystem.limparFormulario();
        }
    }
}

// Inicializar sistema quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    window.financialSystem = new FinancialSystem();
});

// Funções globais para compatibilidade
function abrirModalLancamento() {
    FinancialSystem.abrirModalLancamento();
}

function abrirModalRelatorio() {
    FinancialSystem.abrirModalRelatorio();
}

function gerarRelatorio(tipo) {
    FinancialSystem.gerarRelatorio(tipo);
}

function exportarRelatorio() {
    FinancialSystem.exportarRelatorio();
}

function imprimirRelatorio() {
    FinancialSystem.imprimirRelatorio();
}

function salvarRascunho() {
    FinancialSystem.salvarRascunho();
}

function limparFormulario() {
    FinancialSystem.limparFormulario();
}