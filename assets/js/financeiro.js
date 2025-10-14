/**
 * Sistema Financeiro - JavaScript
 * Gerencia lançamentos financeiros, relatórios e análises
 */

class FinanceiroManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadCategorias();
        this.loadContas();
    }

    bindEvents() {
        // Botão novo lançamento
        $(document).on('click', '#btnNovoLancamento', () => {
            this.abrirModalLancamento();
        });

        // Botão editar lançamento
        $(document).on('click', '.btn-editar-lancamento', (e) => {
            const id = $(e.currentTarget).data('id');
            this.editarLancamento(id);
        });

        // Botão excluir lançamento
        $(document).on('click', '.btn-excluir-lancamento', (e) => {
            const id = $(e.currentTarget).data('id');
            this.excluirLancamento(id);
        });

        // Botão gerar relatório
        $(document).on('click', '#btnGerarRelatorio', () => {
            this.abrirModalRelatorio();
        });

        // Filtros
        $(document).on('change', '#filtroForm', () => {
            this.aplicarFiltros();
        });
    }

    abrirModalLancamento(lancamentoId = null) {
        const modal = `
            <div class="modal fade" id="modalLancamento" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-plus-circle me-2"></i>
                                ${lancamentoId ? 'Editar Lançamento' : 'Novo Lançamento'}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="formLancamento">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo *</label>
                                            <select class="form-select" name="tipo" required>
                                                <option value="">Selecione o tipo</option>
                                                <option value="receita">Receita</option>
                                                <option value="despesa">Despesa</option>
                                                <option value="transferencia">Transferência</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Categoria</label>
                                            <select class="form-select" name="categoria_id" id="categoriaSelect">
                                                <option value="">Selecione uma categoria</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Conta *</label>
                                            <select class="form-select" name="conta_id" id="contaSelect" required>
                                                <option value="">Selecione uma conta</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="contaDestinoDiv" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">Conta Destino</label>
                                            <select class="form-select" name="conta_destino_id" id="contaDestinoSelect">
                                                <option value="">Selecione a conta destino</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Valor *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" class="form-control" name="valor" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Data de Vencimento</label>
                                            <input type="date" class="form-control" name="data_vencimento">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Data de Pagamento</label>
                                            <input type="date" class="form-control" name="data_pagamento">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Forma de Pagamento</label>
                                            <select class="form-select" name="forma_pagamento">
                                                <option value="">Selecione a forma</option>
                                                <option value="Dinheiro">Dinheiro</option>
                                                <option value="Cartão Débito">Cartão Débito</option>
                                                <option value="Cartão Crédito">Cartão Crédito</option>
                                                <option value="PIX">PIX</option>
                                                <option value="Transferência">Transferência</option>
                                                <option value="Cheque">Cheque</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Descrição *</label>
                                    <textarea class="form-control" name="descricao" rows="3" required placeholder="Descreva o lançamento..."></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" name="observacoes" rows="2" placeholder="Observações adicionais..."></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Recorrência</label>
                                            <select class="form-select" name="recorrencia">
                                                <option value="nenhuma">Nenhuma</option>
                                                <option value="diaria">Diária</option>
                                                <option value="semanal">Semanal</option>
                                                <option value="mensal">Mensal</option>
                                                <option value="anual">Anual</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="dataFimRecorrenciaDiv" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">Data Fim Recorrência</label>
                                            <input type="date" class="form-control" name="data_fim_recorrencia">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Anexos</label>
                                    <input type="file" class="form-control" name="anexos[]" multiple accept="image/*,.pdf,.doc,.docx">
                                    <small class="text-muted">Formatos permitidos: JPG, PNG, PDF, DOC, DOCX (máx. 5MB cada)</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Salvar Lançamento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modal);
        $('#modalLancamento').modal('show');

        // Bind form submit
        $('#formLancamento').on('submit', (e) => {
            e.preventDefault();
            this.salvarLancamento(lancamentoId);
        });

        // Bind tipo change
        $('select[name="tipo"]').on('change', (e) => {
            const tipo = $(e.target).val();
            if (tipo === 'transferencia') {
                $('#contaDestinoDiv').show();
                $('#contaDestinoSelect').prop('required', true);
            } else {
                $('#contaDestinoDiv').hide();
                $('#contaDestinoSelect').prop('required', false);
            }
        });

        // Bind recorrência change
        $('select[name="recorrencia"]').on('change', (e) => {
            const recorrência = $(e.target).val();
            if (recorrência !== 'nenhuma') {
                $('#dataFimRecorrenciaDiv').show();
            } else {
                $('#dataFimRecorrenciaDiv').hide();
            }
        });

        // Carregar dados se editando
        if (lancamentoId) {
            this.carregarDadosLancamento(lancamentoId);
        }
    }

    carregarDadosLancamento(id) {
        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'GET',
            data: { action: 'buscar_lancamento', id: id },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    const lancamento = response.data;
                    $('#formLancamento').find('input, select, textarea').each(function() {
                        const name = $(this).attr('name');
                        if (lancamento[name] !== undefined) {
                            $(this).val(lancamento[name]);
                        }
                    });
                }
            }
        });
    }

    salvarLancamento(id = null) {
        const formData = new FormData($('#formLancamento')[0]);
        formData.append('action', id ? 'editar_lancamento' : 'criar_lancamento');
        if (id) formData.append('lancamento_id', id);

        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        $('#modalLancamento').modal('hide');
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: response.message,
                        icon: 'error'
                    });
                }
            },
            error: () => {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao salvar lançamento',
                    icon: 'error'
                });
            }
        });
    }

    editarLancamento(id) {
        this.abrirModalLancamento(id);
    }

    excluirLancamento(id) {
        Swal.fire({
            title: 'Excluir Lançamento',
            text: 'Tem certeza que deseja excluir este lançamento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'mvc/ajax/financeiro.php',
                    method: 'POST',
                    data: { action: 'excluir_lancamento', lancamento_id: id },
                    dataType: 'json',
                    success: (response) => {
                        if (response.success) {
                            Swal.fire({
                                title: 'Excluído!',
                                text: response.message,
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Erro!',
                                text: response.message,
                                icon: 'error'
                            });
                        }
                    }
                });
            }
        });
    }

    abrirModalRelatorio() {
        const modal = `
            <div class="modal fade" id="modalRelatorio" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-bar me-2"></i>
                                Gerar Relatório
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="formRelatorio">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Relatório *</label>
                                            <select class="form-select" name="tipo" required>
                                                <option value="">Selecione o tipo</option>
                                                <option value="fluxo_caixa">Fluxo de Caixa</option>
                                                <option value="receitas_categoria">Receitas por Categoria</option>
                                                <option value="despesas_categoria">Despesas por Categoria</option>
                                                <option value="lucro_prejuizo">Lucro/Prejuízo</option>
                                                <option value="vendas_periodo">Vendas por Período</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Formato *</label>
                                            <select class="form-select" name="formato" required>
                                                <option value="pdf">PDF</option>
                                                <option value="excel">Excel</option>
                                                <option value="csv">CSV</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Data Início *</label>
                                            <input type="date" class="form-control" name="data_inicio" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Data Fim *</label>
                                            <input type="date" class="form-control" name="data_fim" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Filtros Adicionais</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="incluir_graficos" id="incluirGraficos" checked>
                                                <label class="form-check-label" for="incluirGraficos">
                                                    Incluir Gráficos
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="incluir_detalhes" id="incluirDetalhes" checked>
                                                <label class="form-check-label" for="incluirDetalhes">
                                                    Incluir Detalhes
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" name="observacoes" rows="3" placeholder="Observações para o relatório..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-pdf me-1"></i>
                                    Gerar Relatório
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modal);
        $('#modalRelatorio').modal('show');

        // Set default dates
        const hoje = new Date();
        const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        $('#formRelatorio input[name="data_inicio"]').val(primeiroDia.toISOString().split('T')[0]);
        $('#formRelatorio input[name="data_fim"]').val(hoje.toISOString().split('T')[0]);

        // Bind form submit
        $('#formRelatorio').on('submit', (e) => {
            e.preventDefault();
            this.gerarRelatorio();
        });
    }

    gerarRelatorio() {
        const formData = new FormData($('#formRelatorio')[0]);
        formData.append('action', 'gerar_relatorio');

        Swal.fire({
            title: 'Gerando Relatório',
            text: 'Aguarde enquanto processamos os dados...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false
        });

        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    Swal.fire({
                        title: 'Relatório Gerado!',
                        text: 'O relatório foi gerado com sucesso.',
                        icon: 'success'
                    }).then(() => {
                        $('#modalRelatorio').modal('hide');
                        // Aqui você pode implementar o download do relatório
                        this.downloadRelatorio(response.relatorio_id);
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: response.message,
                        icon: 'error'
                    });
                }
            },
            error: () => {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao gerar relatório',
                    icon: 'error'
                });
            }
        });
    }

    downloadRelatorio(relatorioId) {
        // Implementar download do relatório
        window.open(`mvc/ajax/financeiro.php?action=download_relatorio&id=${relatorioId}`, '_blank');
    }

    loadCategorias() {
        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'GET',
            data: { action: 'listar_categorias' },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    const select = $('#categoriaSelect');
                    select.empty().append('<option value="">Selecione uma categoria</option>');
                    
                    response.data.forEach(categoria => {
                        select.append(`<option value="${categoria.id}">${categoria.nome}</option>`);
                    });
                }
            }
        });
    }

    loadContas() {
        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'GET',
            data: { action: 'listar_contas' },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    const select = $('#contaSelect, #contaDestinoSelect');
                    select.empty().append('<option value="">Selecione uma conta</option>');
                    
                    response.data.forEach(conta => {
                        select.append(`<option value="${conta.id}">${conta.nome}</option>`);
                    });
                }
            }
        });
    }

    aplicarFiltros() {
        const form = $('#filtroForm');
        const url = new URL(window.location);
        
        form.serializeArray().forEach(field => {
            if (field.value) {
                url.searchParams.set(field.name, field.value);
            } else {
                url.searchParams.delete(field.name);
            }
        });
        
        window.location.href = url.toString();
    }

    // Métodos para relatórios
    exportarDados(formato) {
        const params = new URLSearchParams(window.location.search);
        params.set('export', formato);
        
        window.open(`mvc/ajax/financeiro.php?${params.toString()}`, '_blank');
    }

    // Métodos para gráficos
    atualizarGraficos() {
        // Implementar atualização de gráficos
        if (typeof Chart !== 'undefined') {
            // Recarregar dados dos gráficos
            this.carregarDadosGraficos();
        }
    }

    carregarDadosGraficos() {
        $.ajax({
            url: 'mvc/ajax/financeiro.php',
            method: 'GET',
            data: { action: 'dados_graficos' },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.renderizarGraficos(response.data);
                }
            }
        });
    }

    renderizarGraficos(dados) {
        // Implementar renderização de gráficos
        console.log('Dados dos gráficos:', dados);
    }
}

// Inicializar quando o documento estiver pronto
$(document).ready(function() {
    window.financeiroManager = new FinanceiroManager();
});

// Funções globais para compatibilidade
function abrirModalLancamento() {
    window.financeiroManager.abrirModalLancamento();
}

function editarLancamento(id) {
    window.financeiroManager.editarLancamento(id);
}

function excluirLancamento(id) {
    window.financeiroManager.excluirLancamento(id);
}

function abrirModalRelatorio() {
    window.financeiroManager.abrirModalRelatorio();
}

function gerarRelatorio(tipo) {
    window.financeiroManager.gerarRelatorio();
}

function exportarDados() {
    Swal.fire({
        title: 'Exportar Dados',
        text: 'Escolha o formato de exportação:',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Excel',
        cancelButtonText: 'CSV',
        showDenyButton: true,
        denyButtonText: 'PDF'
    }).then((result) => {
        if (result.isConfirmed) {
            window.financeiroManager.exportarDados('excel');
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            window.financeiroManager.exportarDados('csv');
        } else if (result.isDenied) {
            window.financeiroManager.exportarDados('pdf');
        }
    });
}
