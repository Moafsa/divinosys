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
    <h1 class="mb-4">Integração de Pagamentos</h1>
    <p>Configure e gerencie integrações com gateways de pagamento e máquinas de cartão.</p>
    
    <!-- Abas de Navegação -->
    <ul class="nav nav-tabs" id="pagamentosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="gateways-tab" data-bs-toggle="tab" data-bs-target="#gateways" type="button" role="tab">
                <i class="fas fa-credit-card"></i> Gateways de Pagamento
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="maquinas-tab" data-bs-toggle="tab" data-bs-target="#maquinas" type="button" role="tab">
                <i class="fas fa-cash-register"></i> Máquinas de Cartão
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pix-tab" data-bs-toggle="tab" data-bs-target="#pix" type="button" role="tab">
                <i class="fas fa-qrcode"></i> PIX
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="relatorios-tab" data-bs-toggle="tab" data-bs-target="#relatorios" type="button" role="tab">
                <i class="fas fa-chart-line"></i> Relatórios Financeiros
            </button>
        </li>
    </ul>

    <!-- Conteúdo das Abas -->
    <div class="tab-content" id="pagamentosTabContent">
        
        <!-- Aba Gateways de Pagamento -->
        <div class="tab-pane fade show active" id="gateways" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Configurar Gateway de Pagamento</h5>
                        </div>
                        <div class="card-body">
                            <form id="formGatewayPagamento">
                                <div class="mb-3">
                                    <label for="gateway_provedor" class="form-label">Provedor *</label>
                                    <select id="gateway_provedor" name="gateway_provedor" class="form-select" required>
                                        <option value="">Selecione um provedor</option>
                                        <option value="stripe">Stripe</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="mercadopago">Mercado Pago</option>
                                        <option value="pagseguro">PagSeguro</option>
                                        <option value="cieloecommerce">Cielo e-Commerce</option>
                                        <option value="getnet">GetNet</option>
                                        <option value="stone">Stone</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_nome" class="form-label">Nome da Configuração *</label>
                                    <input type="text" id="gateway_nome" name="gateway_nome" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_ambiente" class="form-label">Ambiente *</label>
                                    <select id="gateway_ambiente" name="gateway_ambiente" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="sandbox">Sandbox/Teste</option>
                                        <option value="production">Produção</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_client_id" class="form-label">Client ID / API Key *</label>
                                    <input type="text" id="gateway_client_id" name="gateway_client_id" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_client_secret" class="form-label">Client Secret / API Secret *</label>
                                    <input type="password" id="gateway_client_secret" name="gateway_client_secret" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_webhook_url" class="form-label">Webhook URL</label>
                                    <input type="url" id="gateway_webhook_url" name="gateway_webhook_url" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_taxa_fixa" class="form-label">Taxa Fixa (R$)</label>
                                    <input type="number" id="gateway_taxa_fixa" name="gateway_taxa_fixa" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gateway_taxa_percentual" class="form-label">Taxa Percentual (%)</label>
                                    <input type="number" id="gateway_taxa_percentual" name="gateway_taxa_percentual" class="form-control" step="0.01" min="0" max="100" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" id="gateway_ativo" name="gateway_ativo" class="form-check-input" checked>
                                        <label for="gateway_ativo" class="form-check-label">Gateway Ativo</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-save"></i> Salvar Configuração
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Gateways Configurados</h5>
                            <button id="btn_atualizar_gateways" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_gateways" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Provedor</th>
                                            <th>Ambiente</th>
                                            <th>Status</th>
                                            <th>Taxa</th>
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
        
        <!-- Aba Máquinas de Cartão -->
        <div class="tab-pane fade" id="maquinas" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Configurar Máquina de Cartão</h5>
                        </div>
                        <div class="card-body">
                            <form id="formMaquinaCartao">
                                <div class="mb-3">
                                    <label for="maquina_fabricante" class="form-label">Fabricante *</label>
                                    <select id="maquina_fabricante" name="maquina_fabricante" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="stone">Stone</option>
                                        <option value="cielo">Cielo</option>
                                        <option value="getnet">GetNet</option>
                                        <option value="rede">Rede</option>
                                        <option value="pagseguro">PagSeguro</option>
                                        <option value="mercadopago">Mercado Pago</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_modelo" class="form-label">Modelo da Máquina *</label>
                                    <input type="text" id="maquina_modelo" name="maquina_modelo" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_serial" class="form-label">Número de Série</label>
                                    <input type="text" id="maquina_serial" name="maquina_serial" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_terminal_id" class="form-label">Terminal ID</label>
                                    <input type="text" id="maquina_terminal_id" name="maquina_terminal_id" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_api_key" class="form-label">API Key</label>
                                    <input type="password" id="maquina_api_key" name="maquina_api_key" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_taxa_debito" class="form-label">Taxa Débito (%)</label>
                                    <input type="number" id="maquina_taxa_debito" name="maquina_taxa_debito" class="form-control" step="0.01" min="0" max="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="maquina_taxa_credito" class="form-label">Taxa Crédito (%)</label>
                                    <input type="number" id="maquina_taxa_credito" name="maquina_taxa_credito" class="form-control" step="0.01" min="0" max="100">
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" id="maquina_ativo" name="maquina_ativo" class="form-check-input" checked>
                                        <label for="maquina_ativo" class="form-check-label">Máquina Ativa</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-cash-register"></i> Salvar Máquina
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Máquinas Configuradas</h5>
                            <button id="btn_atualizar_maquinas" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_maquinas" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fabricante</th>
                                            <th>Modelo</th>
                                            <th>Terminal ID</th>
                                            <th>Status</th>
                                            <th>Taxas</th>
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
        
        <!-- Aba PIX -->
        <div class="tab-pane fade" id="pix" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Configurar PIX</h5>
                        </div>
                        <div class="card-body">
                            <form id="formPix">
                                <div class="mb-3">
                                    <label for="pix_chave" class="form-label">Chave PIX *</label>
                                    <input type="text" id="pix_chave" name="pix_chave" class="form-control" required>
                                    <div class="form-text">CPF, CNPJ, email ou telefone</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pix_tipo_chave" class="form-label">Tipo da Chave *</label>
                                    <select id="pix_tipo_chave" name="pix_tipo_chave" class="form-select" required>
                                        <option value="">Selecione</option>
                                        <option value="cpf">CPF</option>
                                        <option value="cnpj">CNPJ</option>
                                        <option value="email">Email</option>
                                        <option value="telefone">Telefone</option>
                                        <option value="aleatoria">Chave Aleatória</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pix_banco" class="form-label">Banco</label>
                                    <input type="text" id="pix_banco" name="pix_banco" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pix_agencia" class="form-label">Agência</label>
                                    <input type="text" id="pix_agencia" name="pix_agencia" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pix_conta" class="form-label">Conta</label>
                                    <input type="text" id="pix_conta" name="pix_conta" class="form-control">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pix_qr_code" class="form-label">QR Code Estático</label>
                                    <input type="file" id="pix_qr_code" name="pix_qr_code" class="form-control" accept="image/*">
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" id="pix_ativo" name="pix_ativo" class="form-check-input" checked>
                                        <label for="pix_ativo" class="form-check-label">PIX Ativo</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-qrcode"></i> Salvar PIX
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Configurações PIX</h5>
                            <button id="btn_atualizar_pix" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="pix_config_content">
                                <!-- Conteúdo carregado via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aba Relatórios Financeiros -->
        <div class="tab-pane fade" id="relatorios" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatórios Financeiros de Pagamentos</h5>
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
                                    <label for="relatorio_forma_pagamento" class="form-label">Forma de Pagamento</label>
                                    <select id="relatorio_forma_pagamento" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="pix">PIX</option>
                                        <option value="cartao_debito">Cartão Débito</option>
                                        <option value="cartao_credito">Cartão Crédito</option>
                                        <option value="transferencia">Transferência</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_financeiro" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-line"></i> Gerar Relatório
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    carregarGateways();
    carregarMaquinas();
    carregarPix();
    
    // Event listeners para formulários
    document.getElementById('formGatewayPagamento').addEventListener('submit', salvarGateway);
    document.getElementById('formMaquinaCartao').addEventListener('submit', salvarMaquina);
    document.getElementById('formPix').addEventListener('submit', salvarPix);
    
    // Event listeners para botões
    document.getElementById('btn_atualizar_gateways').addEventListener('click', carregarGateways);
    document.getElementById('btn_atualizar_maquinas').addEventListener('click', carregarMaquinas);
    document.getElementById('btn_atualizar_pix').addEventListener('click', carregarPix);
    document.getElementById('btn_gerar_relatorio_financeiro').addEventListener('click', gerarRelatorioFinanceiro);
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-testar-gateway')) {
            const id = e.target.dataset.id;
            testarGateway(id);
        }
        
        if (e.target.classList.contains('btn-editar-gateway')) {
            const id = e.target.dataset.id;
            editarGateway(id);
        }
        
        if (e.target.classList.contains('btn-excluir-gateway')) {
            const id = e.target.dataset.id;
            excluirGateway(id);
        }
    });
});

function carregarGateways() {
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_gateways'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_gateways tbody');
            tbody.innerHTML = '';
            
            data.gateways.forEach(gateway => {
                const statusClass = gateway.ativo ? 'bg-success' : 'bg-secondary';
                const statusText = gateway.ativo ? 'Ativo' : 'Inativo';
                const taxa = gateway.taxa_fixa > 0 ? 
                    `R$ ${gateway.taxa_fixa.toFixed(2)}` : 
                    `${gateway.taxa_percentual}%`;
                
                const row = `
                    <tr>
                        <td>${gateway.nome}</td>
                        <td>${gateway.provedor}</td>
                        <td>${gateway.ambiente}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${taxa}</td>
                        <td>
                            <button class="btn btn-info btn-sm btn-testar-gateway" data-id="${gateway.id}">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-warning btn-sm btn-editar-gateway" data-id="${gateway.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-excluir-gateway" data-id="${gateway.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar gateways:', error));
}

function carregarMaquinas() {
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_maquinas'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_maquinas tbody');
            tbody.innerHTML = '';
            
            data.maquinas.forEach(maquina => {
                const statusClass = maquina.ativo ? 'bg-success' : 'bg-secondary';
                const statusText = maquina.ativo ? 'Ativa' : 'Inativa';
                const taxas = `Débito: ${maquina.taxa_debito}% | Crédito: ${maquina.taxa_credito}%`;
                
                const row = `
                    <tr>
                        <td>${maquina.fabricante}</td>
                        <td>${maquina.modelo}</td>
                        <td>${maquina.terminal_id}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${taxas}</td>
                        <td>
                            <button class="btn btn-info btn-sm">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-warning btn-sm">
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
    .catch(error => console.error('Erro ao carregar máquinas:', error));
}

function carregarPix() {
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_pix'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const content = document.getElementById('pix_config_content');
            
            if (data.pix) {
                content.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h6>Configuração PIX Ativa</h6>
                            <p><strong>Chave:</strong> ${data.pix.chave}</p>
                            <p><strong>Tipo:</strong> ${data.pix.tipo_chave}</p>
                            <p><strong>Banco:</strong> ${data.pix.banco || 'Não informado'}</p>
                            <p><strong>Status:</strong> <span class="badge ${data.pix.ativo ? 'bg-success' : 'bg-secondary'}">${data.pix.ativo ? 'Ativo' : 'Inativo'}</span></p>
                            ${data.pix.qr_code ? `<img src="${data.pix.qr_code}" class="img-fluid" alt="QR Code PIX">` : ''}
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = '<p class="text-muted">Nenhuma configuração PIX encontrada.</p>';
            }
        }
    })
    .catch(error => console.error('Erro ao carregar PIX:', error));
}

function salvarGateway(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_gateway');
    
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Gateway salvo com sucesso!');
            e.target.reset();
            carregarGateways();
        } else {
            alert('Erro ao salvar gateway: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar gateway');
    });
}

function salvarMaquina(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_maquina');
    
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Máquina salva com sucesso!');
            e.target.reset();
            carregarMaquinas();
        } else {
            alert('Erro ao salvar máquina: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar máquina');
    });
}

function salvarPix(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('acao', 'salvar_pix');
    
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('PIX salvo com sucesso!');
            e.target.reset();
            carregarPix();
        } else {
            alert('Erro ao salvar PIX: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar PIX');
    });
}

function testarGateway(id) {
    fetch('mvc/ajax/integracao_pagamentos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=testar_gateway&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Gateway testado com sucesso!');
        } else {
            alert('Erro ao testar gateway: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao testar gateway');
    });
}

function gerarRelatorioFinanceiro() {
    const dataInicio = document.getElementById('relatorio_data_inicio').value;
    const dataFim = document.getElementById('relatorio_data_fim').value;
    const formaPagamento = document.getElementById('relatorio_forma_pagamento').value;
    
    const params = new URLSearchParams({
        acao: 'gerar_relatorio_financeiro',
        data_inicio: dataInicio,
        data_fim: dataFim,
        forma_pagamento: formaPagamento
    });
    
    fetch('mvc/ajax/integracao_pagamentos.php', {
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
            alert('Erro ao gerar relatório: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório');
    });
}
</script>
