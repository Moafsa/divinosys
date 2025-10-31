<?php
/**
 * Asaas Configuration Interface
 * Allows establishments and filiais to configure their Asaas settings
 */

// Check if user has permission to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] < 2) {
    header('Location: index.php?view=login');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$filial_id = $_SESSION['filial_id'] ?? null;

if (!$tenant_id) {
    echo '<div class="alert alert-danger">Tenant ID not found in session</div>';
    return;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cog"></i> Configuração do Asaas
                        <?php if ($filial_id): ?>
                            <small class="text-muted">- Filial</small>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Asaas Configuration Form -->
                    <form id="asaasConfigForm">
                        <input type="hidden" id="tenant_id" value="<?php echo $tenant_id; ?>">
                        <input type="hidden" id="filial_id" value="<?php echo $filial_id; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asaas_api_key">Chave API do Asaas</label>
                                    <input type="password" class="form-control" id="asaas_api_key" 
                                           placeholder="Digite sua chave API do Asaas">
                                    <small class="form-text text-muted">
                                        Obtenha sua chave API em <a href="https://www.asaas.com/" target="_blank">www.asaas.com</a>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asaas_environment">Ambiente</label>
                                    <select class="form-control" id="asaas_environment">
                                        <option value="sandbox">Sandbox (Teste)</option>
                                        <option value="production">Produção</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asaas_customer_id">ID do Cliente no Asaas</label>
                                    <input type="text" class="form-control" id="asaas_customer_id" 
                                           placeholder="ID do cliente no Asaas">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="asaas_enabled">
                                        <label class="form-check-label" for="asaas_enabled">
                                            Habilitar integração com Asaas
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Configuração
                            </button>
                            <button type="button" class="btn btn-secondary" id="testConnection">
                                <i class="fas fa-plug"></i> Testar Conexão
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fiscal Information Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-invoice"></i> Informações Fiscais
                    </h3>
                </div>
                <div class="card-body">
                    <form id="fiscalInfoForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cnpj">CNPJ</label>
                                    <input type="text" class="form-control" id="cnpj" 
                                           placeholder="00.000.000/0000-00" maxlength="18">
                                    <small class="form-text text-muted">CNPJ da empresa</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="razao_social">Razão Social</label>
                                    <input type="text" class="form-control" id="razao_social" 
                                           placeholder="Nome da empresa">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nome_fantasia">Nome Fantasia</label>
                                    <input type="text" class="form-control" id="nome_fantasia" 
                                           placeholder="Nome comercial">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inscricao_estadual">Inscrição Estadual</label>
                                    <input type="text" class="form-control" id="inscricao_estadual" 
                                           placeholder="Inscrição estadual">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="municipal_service_id">ID do Serviço Municipal</label>
                                    <input type="text" class="form-control" id="municipal_service_id" 
                                           placeholder="ID do serviço municipal">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="municipal_service_code">Código do Serviço Municipal</label>
                                    <input type="text" class="form-control" id="municipal_service_code" 
                                           placeholder="Código do serviço municipal">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Salvar Informações Fiscais
                            </button>
                            <button type="button" class="btn btn-info" id="loadMunicipalOptions">
                                <i class="fas fa-download"></i> Carregar Opções Municipais
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invoice Management Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-receipt"></i> Gestão de Notas Fiscais
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <button class="btn btn-primary" id="createInvoiceFromOrder">
                                <i class="fas fa-plus"></i> Criar Nota de Pedido
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-info" id="refreshInvoices">
                                <i class="fas fa-sync"></i> Atualizar Lista
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select class="form-control" id="invoiceStatusFilter">
                                <option value="">Todos os Status</option>
                                <option value="pending">Pendente</option>
                                <option value="issued">Emitida</option>
                                <option value="cancelled">Cancelada</option>
                                <option value="error">Erro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="invoicesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pedido</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data Criação</th>
                                    <th>Data Emissão</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Invoices will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load current configuration
    loadAsaasConfig();
    loadFiscalInfo();
    loadInvoices();
    
    // Asaas Configuration Form
    $('#asaasConfigForm').on('submit', function(e) {
        e.preventDefault();
        saveAsaasConfig();
    });
    
    // Test Connection
    $('#testConnection').on('click', function() {
        testAsaasConnection();
    });
    
    // Fiscal Information Form
    $('#fiscalInfoForm').on('submit', function(e) {
        e.preventDefault();
        saveFiscalInfo();
    });
    
    // Load Municipal Options
    $('#loadMunicipalOptions').on('click', function() {
        loadMunicipalOptions();
    });
    
    // Invoice Management
    $('#createInvoiceFromOrder').on('click', function() {
        showCreateInvoiceModal();
    });
    
    $('#refreshInvoices').on('click', function() {
        loadInvoices();
    });
    
    $('#invoiceStatusFilter').on('change', function() {
        loadInvoices();
    });
    
    // CNPJ formatting
    $('#cnpj').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length <= 14) {
            value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            $(this).val(value);
        }
    });
});

function loadAsaasConfig() {
    // This would load current configuration from database
    // Implementation depends on your existing API structure
}

function saveAsaasConfig() {
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val(),
        asaas_api_key: $('#asaas_api_key').val(),
        asaas_environment: $('#asaas_environment').val(),
        asaas_customer_id: $('#asaas_customer_id').val(),
        asaas_enabled: $('#asaas_enabled').is(':checked')
    };
    
    $.ajax({
        url: 'mvc/ajax/asaas_config.php',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('Configuração salva com sucesso!', 'success');
            } else {
                showAlert('Erro ao salvar configuração: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar configuração', 'danger');
        }
    });
}

function testAsaasConnection() {
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val()
    };
    
    $.ajax({
        url: 'mvc/ajax/asaas_config.php?action=testConnection',
        method: 'GET',
        data: data,
        success: function(response) {
            if (response.success) {
                showAlert('Conexão com Asaas testada com sucesso!', 'success');
            } else {
                showAlert('Erro na conexão: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao testar conexão', 'danger');
        }
    });
}

function saveFiscalInfo() {
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val(),
        cnpj: $('#cnpj').val(),
        razao_social: $('#razao_social').val(),
        nome_fantasia: $('#nome_fantasia').val(),
        inscricao_estadual: $('#inscricao_estadual').val(),
        municipal_service_id: $('#municipal_service_id').val(),
        municipal_service_code: $('#municipal_service_code').val(),
        endereco: {
            // This would be populated from address fields
        }
    };
    
    $.ajax({
        url: 'mvc/ajax/fiscal_info.php?action=createOrUpdateFiscalInfo',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('Informações fiscais salvas com sucesso!', 'success');
            } else {
                showAlert('Erro ao salvar informações fiscais: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar informações fiscais', 'danger');
        }
    });
}

function loadInvoices() {
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val(),
        status: $('#invoiceStatusFilter').val()
    };
    
    $.ajax({
        url: 'mvc/ajax/invoices.php?action=listInvoices',
        method: 'GET',
        data: data,
        success: function(response) {
            if (response.success) {
                populateInvoicesTable(response.data);
            } else {
                showAlert('Erro ao carregar notas fiscais: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar notas fiscais', 'danger');
        }
    });
}

function populateInvoicesTable(invoices) {
    const tbody = $('#invoicesTable tbody');
    tbody.empty();
    
    if (invoices.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center">Nenhuma nota fiscal encontrada</td></tr>');
        return;
    }
    
    invoices.forEach(function(invoice) {
        const row = `
            <tr>
                <td>${invoice.asaas_invoice_id}</td>
                <td>${invoice.pedido_id || '-'}</td>
                <td>R$ ${parseFloat(invoice.valor_total).toFixed(2)}</td>
                <td><span class="badge badge-${getStatusBadgeClass(invoice.status)}">${getStatusText(invoice.status)}</span></td>
                <td>${formatDate(invoice.created_at)}</td>
                <td>${invoice.data_emissao ? formatDate(invoice.data_emissao) : '-'}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewInvoice('${invoice.asaas_invoice_id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${invoice.status === 'pending' ? `
                        <button class="btn btn-sm btn-success" onclick="issueInvoice('${invoice.asaas_invoice_id}')">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                    ${invoice.status === 'issued' ? `
                        <button class="btn btn-sm btn-warning" onclick="cancelInvoice('${invoice.asaas_invoice_id}')">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'warning',
        'issued': 'success',
        'cancelled': 'danger',
        'error': 'danger'
    };
    return classes[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'pending': 'Pendente',
        'issued': 'Emitida',
        'cancelled': 'Cancelada',
        'error': 'Erro'
    };
    return texts[status] || status;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
}

function issueInvoice(invoiceId) {
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val(),
        asaas_invoice_id: invoiceId
    };
    
    $.ajax({
        url: 'mvc/ajax/invoices.php?action=issueInvoice',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('Nota fiscal emitida com sucesso!', 'success');
                loadInvoices();
            } else {
                showAlert('Erro ao emitir nota fiscal: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao emitir nota fiscal', 'danger');
        }
    });
}

function cancelInvoice(invoiceId) {
    const reason = prompt('Motivo do cancelamento:');
    if (!reason) return;
    
    const data = {
        tenant_id: $('#tenant_id').val(),
        filial_id: $('#filial_id').val(),
        asaas_invoice_id: invoiceId,
        reason: reason
    };
    
    $.ajax({
        url: 'mvc/ajax/invoices.php?action=cancelInvoice',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('Nota fiscal cancelada com sucesso!', 'success');
                loadInvoices();
            } else {
                showAlert('Erro ao cancelar nota fiscal: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao cancelar nota fiscal', 'danger');
        }
    });
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('.container-fluid').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
