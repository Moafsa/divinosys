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

// Buscar categorias e contas para o formulário
$categorias = $db->fetchAll(
    "SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY tipo, nome",
    [$tenant['id'], $filial['id']]
);

$contas = $db->fetchAll(
    "SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
    [$tenant['id'], $filial['id']]
);

// Buscar pedidos em aberto para vincular lançamentos
$pedidos = $db->fetchAll(
    "SELECT p.idpedido, p.cliente, p.valor_total, p.data, p.hora_pedido, m.nome as mesa_nome
     FROM pedido p
     LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
     WHERE p.tenant_id = ? AND p.filial_id = ? 
     AND p.status_pagamento IN ('pendente', 'parcial')
     ORDER BY p.data DESC, p.hora_pedido DESC
     LIMIT 50",
    [$tenant['id'], $filial['id']]
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Lançamento - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-section h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 0.5rem;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            margin: 0.25rem;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: block;
        }
        
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 0.5rem;
            margin: 0.25rem 0;
        }
        
        .file-preview i {
            color: var(--primary-color);
        }
        
        .main-content {
            margin-left: 60px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            overflow-x: auto;
        }
        
        .sidebar.expanded + .main-content {
            margin-left: 250px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar collapsed" id="sidebar">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="sidebar-content">
                    <div class="sidebar-brand">
                        <div class="brand-icon text-white">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>" data-tooltip="Dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>" data-tooltip="Novo Pedido">
                            <i class="fas fa-plus-circle"></i>
                            <span>Novo Pedido</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>" data-tooltip="Pedidos">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>" data-tooltip="Delivery">
                            <i class="fas fa-motorcycle"></i>
                            <span>Delivery</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>" data-tooltip="Produtos">
                            <i class="fas fa-box"></i>
                            <span>Produtos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque</span>
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
                            <i class="fas fa-chart-line"></i>
                            <span>Financeiro</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>" data-tooltip="Relatórios">
                            <i class="fas fa-chart-bar"></i>
                            <span>Relatórios</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>" data-tooltip="Clientes">
                            <i class="fas fa-users"></i>
                            <span>Clientes</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('ai_chat'); ?>" data-tooltip="Assistente IA">
                            <i class="fas fa-robot"></i>
                            <span>Assistente IA</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content" id="mainContent">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-plus-circle text-primary me-2"></i>
                                Novo Lançamento Financeiro
                            </h2>
                            <p class="text-muted mb-0">Criar receita, despesa ou transferência</p>
                        </div>
                        <div>
                            <a href="<?php echo $router->url('financeiro'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Voltar
                            </a>
                        </div>
                    </div>

                    <!-- Formulário -->
                    <form id="lancamentoForm" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Informações Básicas -->
                            <div class="col-md-8">
                                <div class="form-section">
                                    <h6><i class="fas fa-info-circle me-2"></i>Informações Básicas</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo de Lançamento <span class="text-danger">*</span></label>
                                            <select class="form-select" name="tipo_lancamento" id="tipo" required>
                                                <option value="">Selecione o tipo</option>
                                                <option value="receita">Receita</option>
                                                <option value="despesa">Despesa</option>
                                                <option value="transferencia">Transferência</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Valor <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" class="form-control" name="valor" id="valor" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Data <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="data_lancamento" id="data_lancamento" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="descricao" id="descricao" rows="3" required placeholder="Descreva o lançamento..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Categorização -->
                                <div class="form-section">
                                    <h6><i class="fas fa-tags me-2"></i>Categorização</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Categoria</label>
                                            <div class="input-group">
                                                <select class="form-select select2-categoria" name="categoria_id" id="categoria_id">
                                                    <option value="">Selecione uma categoria</option>
                                                    <?php foreach ($categorias as $categoria): ?>
                                                        <option value="<?= $categoria['id'] ?>" data-tipo="<?= $categoria['tipo'] ?>">
                                                            <?= $categoria['tipo'] === 'receita' ? '💰' : '💸' ?> <?= htmlspecialchars($categoria['nome']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-primary" onclick="openNovaCategoria()" title="Criar nova categoria">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Conta <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <select class="form-select select2-conta" name="conta_id" id="conta_id" required>
                                                    <option value="">Selecione uma conta</option>
                                                    <?php foreach ($contas as $conta): ?>
                                                        <option value="<?= $conta['id'] ?>" data-tipo="<?= $conta['tipo'] ?>">
                                                            <?= htmlspecialchars($conta['nome']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-primary" onclick="openNovaConta()" title="Criar nova conta">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3" id="conta_destino_section" style="display: none;">
                                        <div class="col-md-6">
                                            <label class="form-label">Conta Destino</label>
                                            <select class="form-select" name="conta_destino_id" id="conta_destino_id">
                                                <option value="">Selecione a conta destino</option>
                                                <?php foreach ($contas as $conta): ?>
                                                    <option value="<?= $conta['id'] ?>">
                                                        <?= htmlspecialchars($conta['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vinculação com Pedido -->
                                <div class="form-section">
                                    <h6><i class="fas fa-shopping-cart me-2"></i>Vinculação com Pedido (Opcional)</h6>
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">Pedido</label>
                                            <select class="form-select" name="pedido_id" id="pedido_id">
                                                <option value="">Nenhum pedido vinculado</option>
                                                <?php foreach ($pedidos as $pedido): ?>
                                                    <option value="<?= $pedido['idpedido'] ?>" data-valor="<?= $pedido['valor_total'] ?>">
                                                        #<?= $pedido['idpedido'] ?> - <?= htmlspecialchars($pedido['cliente']) ?> 
                                                        (R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?>)
                                                        <?php if ($pedido['mesa_nome']): ?>
                                                            - Mesa: <?= htmlspecialchars($pedido['mesa_nome']) ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Datas e Status -->
                                <div class="form-section">
                                    <h6><i class="fas fa-calendar me-2"></i>Datas e Status</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Data de Vencimento</label>
                                            <input type="date" class="form-control" name="data_vencimento" id="data_vencimento">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Data de Pagamento</label>
                                            <input type="date" class="form-control" name="data_pagamento" id="data_pagamento">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status" id="status">
                                                <option value="confirmado">Confirmado</option>
                                                <option value="pendente">Pendente</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Forma de Pagamento e Recorrência -->
                                <div class="form-section">
                                    <h6><i class="fas fa-credit-card me-2"></i>Pagamento e Recorrência</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Forma de Pagamento</label>
                                            <select class="form-select" name="forma_pagamento" id="forma_pagamento">
                                                <option value="">Selecione a forma</option>
                                                <option value="dinheiro">Dinheiro</option>
                                                <option value="cartao_debito">Cartão de Débito</option>
                                                <option value="cartao_credito">Cartão de Crédito</option>
                                                <option value="pix">PIX</option>
                                                <option value="transferencia">Transferência</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="outros">Outros</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Recorrência</label>
                                            <select class="form-select" name="recorrencia" id="recorrencia">
                                                <option value="nenhuma">Nenhuma</option>
                                                <option value="diaria">Diária</option>
                                                <option value="semanal">Semanal</option>
                                                <option value="mensal">Mensal</option>
                                                <option value="anual">Anual</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-3" id="fim_recorrencia_section" style="display: none;">
                                        <div class="col-md-6">
                                            <label class="form-label">Data Fim da Recorrência</label>
                                            <input type="date" class="form-control" name="data_fim_recorrencia" id="data_fim_recorrencia">
                                        </div>
                                    </div>
                                </div>

                                <!-- Observações -->
                                <div class="form-section">
                                    <h6><i class="fas fa-sticky-note me-2"></i>Observações</h6>
                                    <textarea class="form-control" name="observacoes" id="observacoes" rows="3" placeholder="Observações adicionais sobre o lançamento..."></textarea>
                                </div>
                            </div>

                            <!-- Anexos -->
                            <div class="col-md-4">
                                <div class="form-section">
                                    <h6><i class="fas fa-paperclip me-2"></i>Anexos</h6>
                                    <div class="file-upload-area" id="fileUploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <p class="mb-2">Arraste arquivos aqui ou clique para selecionar</p>
                                        <p class="text-muted small">PNG, JPG, PDF até 5MB</p>
                                        <input type="file" id="anexos" name="anexos[]" multiple accept=".png,.jpg,.jpeg,.pdf" style="display: none;">
                                    </div>
                                    <div id="filePreviews"></div>
                                </div>

                                <!-- Resumo -->
                                <div class="form-section">
                                    <h6><i class="fas fa-calculator me-2"></i>Resumo</h6>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Tipo:</span>
                                                <span id="resumo_tipo" class="text-muted">-</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Valor:</span>
                                                <span id="resumo_valor" class="text-muted">-</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Conta:</span>
                                                <span id="resumo_conta" class="text-muted">-</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Categoria:</span>
                                                <span id="resumo_categoria" class="text-muted">-</span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <strong>Total:</strong>
                                                <strong id="resumo_total" class="text-primary">R$ 0,00</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="limparFormulario()">
                                        <i class="fas fa-eraser me-1"></i>
                                        Limpar
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" onclick="salvarRascunho()">
                                        <i class="fas fa-save me-1"></i>
                                        Salvar Rascunho
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check me-1"></i>
                                        Salvar Lançamento
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Categoria -->
    <div class="modal fade" id="modalNovaCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Nova Categoria Financeira</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaCategoria">
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nova_cat_nome" required placeholder="Ex: Vendas Online">
                        </div>
                        <input type="hidden" id="nova_cat_tipo">
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="nova_cat_descricao" rows="2" placeholder="Opcional"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="nova_cat_cor" value="#007bff">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarNovaCategoria()">
                        <i class="fas fa-save me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Conta -->
    <div class="modal fade" id="modalNovaConta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-wallet me-2"></i>Nova Conta Financeira</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaConta">
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nova_conta_nome" required placeholder="Ex: Caixa Loja 2">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Saldo Inicial</label>
                            <input type="number" class="form-control" id="nova_conta_saldo" step="0.01" value="0.00" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="nova_conta_cor" value="#28a745">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarNovaConta()">
                        <i class="fas fa-save me-1"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/js/financeiro.js"></script>
    <script>
        // Inicializar Select2
        $(document).ready(function() {
            // Excluir selects dos modais do Select2 (usar apenas native select)
            $('.form-select:not(#nova_cat_tipo):not(#nova_conta_tipo)').select2({
                theme: 'bootstrap-5'
            });
            
            // Definir data atual por padrão
            const hoje = new Date().toISOString().split('T')[0];
            document.getElementById('data_lancamento').value = hoje;
            
            // Atualizar resumo quando campos mudarem
            $('#tipo, #valor, #conta_id, #categoria_id').on('change', atualizarResumo);
            $('#valor').on('input', atualizarResumo);
        });

        // Mostrar/ocultar conta destino para transferências
        $('#tipo').on('change', function() {
            const tipo = $(this).val();
            if (tipo === 'transferencia') {
                $('#conta_destino_section').show();
                $('#conta_destino_id').prop('required', true);
            } else {
                $('#conta_destino_section').hide();
                $('#conta_destino_id').prop('required', false);
            }
            atualizarResumo();
        });

        // Mostrar/ocultar data fim da recorrência
        $('#recorrencia').on('change', function() {
            const recorrencia = $(this).val();
            if (recorrencia !== 'nenhuma') {
                $('#fim_recorrencia_section').show();
            } else {
                $('#fim_recorrencia_section').hide();
            }
        });

        // Filtrar categorias por tipo
        $('#tipo').on('change', function() {
            const tipo = $(this).val();
            $('#categoria_id option').each(function() {
                const categoriaTipo = $(this).data('tipo');
                if (tipo === '' || categoriaTipo === tipo || categoriaTipo === 'investimento') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            $('#categoria_id').val('').trigger('change');
        });

        // Upload de arquivos
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('anexos');
        const filePreviews = document.getElementById('filePreviews');

        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', handleDragOver);
        fileUploadArea.addEventListener('dragleave', handleDragLeave);
        fileUploadArea.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDragOver(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        }

        function handleFileSelect(e) {
            const files = e.target.files;
            handleFiles(files);
        }

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire('Erro!', 'Arquivo muito grande. Máximo 5MB.', 'error');
                    return;
                }
                
                const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire('Erro!', 'Tipo de arquivo não permitido.', 'error');
                    return;
                }

                addFilePreview(file);
            });
        }

        function addFilePreview(file) {
            const preview = document.createElement('div');
            preview.className = 'file-preview';
            preview.innerHTML = `
                <div>
                    <i class="fas fa-file me-2"></i>
                    <span>${file.name}</span>
                    <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFilePreview(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            filePreviews.appendChild(preview);
        }

        function removeFilePreview(button) {
            button.parentElement.remove();
        }

        // Atualizar resumo
        function atualizarResumo() {
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

        // Salvar lançamento
        $('#lancamentoForm').off('submit').on('submit', function(e) {
            console.log('Formulário submetido!');
            e.preventDefault();
            e.stopImmediatePropagation();
            
            // Validação dos campos obrigatórios
            const tipoLancamento = $('#tipo').val();
            const valor = $('#valor').val();
            const dataLancamento = $('#data_lancamento').val();
            const descricao = $('#descricao').val();
            const contaId = $('#conta_id').val();
            const categoriaId = $('#categoria_id').val();
            
            console.log('Validação:', {
                tipoLancamento, valor, dataLancamento, descricao, contaId, categoriaId
            });
            
            if (!tipoLancamento || !valor || !dataLancamento || !descricao || !contaId) {
                console.log('Campos obrigatórios não preenchidos:', {
                    tipoLancamento: !tipoLancamento,
                    valor: !valor,
                    dataLancamento: !dataLancamento,
                    descricao: !descricao,
                    contaId: !contaId
                });
                Swal.fire('Erro!', 'Todos os campos obrigatórios devem ser preenchidos', 'error');
                return;
            }
            
            // Validação específica para transferências
            if (tipoLancamento === 'transferencia') {
                const contaDestinoId = $('#conta_destino_id').val();
                if (!contaDestinoId) {
                    Swal.fire('Erro!', 'Para transferências, a conta destino é obrigatória', 'error');
                    return;
                }
                if (contaId === contaDestinoId) {
                    Swal.fire('Erro!', 'A conta origem e destino devem ser diferentes', 'error');
                    return;
                }
            }
            
            if (parseFloat(valor) <= 0) {
                Swal.fire('Erro!', 'O valor deve ser maior que zero', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'criar_lancamento');

            // Mostrar loading
            Swal.fire({
                title: 'Salvando...',
                text: 'Criando lançamento financeiro...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            console.log('Enviando dados para o servidor...');
            fetch('mvc/ajax/lancamentos_simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Resposta recebida:', response.status);
                console.log('Headers da resposta:', response.headers);
                if (!response.ok) {
                    console.error('Erro HTTP:', response.status, response.statusText);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text().then(text => {
                    console.log('Resposta em texto:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', e);
                        console.error('Texto da resposta:', text);
                        throw new Error('Resposta não é um JSON válido: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    console.log('Lançamento criado com sucesso, redirecionando...');
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Lançamento criado com sucesso!',
                        icon: 'success'
                    }).then(() => {
                        console.log('Redirecionando para página financeira...');
                        window.location.href = 'index.php?view=financeiro';
                    });
                } else {
                    console.error('Erro ao criar lançamento:', data.message);
                    console.error('Dados completos da resposta:', data);
                    Swal.fire('Erro!', data.message || 'Erro ao criar lançamento', 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                console.error('Stack trace:', error.stack);
                Swal.fire('Erro!', 'Erro ao processar solicitação: ' + error.message, 'error');
            });
        });

        // Salvar rascunho
        function salvarRascunho() {
            const formData = new FormData(document.getElementById('lancamentoForm'));
            formData.append('action', 'salvar_rascunho');

            Swal.fire({
                title: 'Salvando Rascunho...',
                text: 'Salvando como rascunho...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false
            });

            fetch('mvc/ajax/lancamentos_simple.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Rascunho salvo com sucesso!', 'success');
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao salvar rascunho', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Erro ao processar solicitação', 'error');
            });
        }

        // Limpar formulário
        function limparFormulario() {
            Swal.fire({
                title: 'Limpar Formulário',
                text: 'Tem certeza que deseja limpar todos os campos?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, limpar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('lancamentoForm').reset();
                    $('#categoria_id, #conta_id, #conta_destino_id').val('').trigger('change');
                    $('#filePreviews').empty();
                    atualizarResumo();
                }
            });
        }
        
        // Inicializar página
        $(document).ready(function() {
            console.log('Inicializando página de lançamento financeiro');
            
            // Definir data padrão
            const hoje = new Date().toISOString().split('T')[0];
            $('#data_lancamento').val(hoje);
            
            // Inicializar resumo
            atualizarResumo();
            
            // Adicionar event handler direto para o botão
            $('button[type="submit"]').on('click', function(e) {
                console.log('Botão clicado!');
                e.preventDefault();
                $('#lancamentoForm').trigger('submit');
            });
            
            console.log('Página inicializada com sucesso');
        });

        // ==================================
        // FUNCTIONS TO CREATE CATEGORY/ACCOUNT ON THE FLY
        // ==================================
        
        function openNovaCategoria() {
            // Get current tipo from main form
            const tipoLancamento = $('#tipo').val();
            
            if (!tipoLancamento) {
                Swal.fire('Atenção', 'Selecione primeiro o tipo do lançamento (Receita ou Despesa)', 'warning');
                return;
            }
            
            // Set tipo automatically based on lancamento tipo
            let tipoCategoria = '';
            if (tipoLancamento === 'receita') {
                tipoCategoria = 'receita';
            } else if (tipoLancamento === 'despesa') {
                tipoCategoria = 'despesa';
            } else {
                Swal.fire('Atenção', 'Selecione Receita ou Despesa para criar uma categoria', 'warning');
                return;
            }
            
            $('#nova_cat_tipo').val(tipoCategoria);
            $('#modalNovaCategoria').modal('show');
        }

        function openNovaConta() {
            $('#modalNovaConta').modal('show');
        }

        function salvarNovaCategoria() {
            const nome = $('#nova_cat_nome').val().trim();
            const tipo = $('#nova_cat_tipo').val();
            const descricao = $('#nova_cat_descricao').val().trim();
            const cor = $('#nova_cat_cor').val();

            if (!nome || !tipo) {
                Swal.fire('Atenção', 'Preencha os campos obrigatórios', 'warning');
                return;
            }

            $.ajax({
                url: 'mvc/ajax/financeiro.php',
                method: 'POST',
                data: {
                    action: 'criar_categoria',
                    nome: nome,
                    tipo: tipo,
                    descricao: descricao,
                    cor: cor
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso', 'Categoria criada com sucesso!', 'success');
                        
                        // Add new option to select
                        const emoji = tipo === 'receita' ? '💰' : '💸';
                        const newOption = new Option(`${emoji} ${nome}`, response.categoria_id, true, true);
                        $('#categoria_id').append(newOption).trigger('change');
                        
                        // Close modal and reset form
                        $('#modalNovaCategoria').modal('hide');
                        $('#formNovaCategoria')[0].reset();
                    } else {
                        Swal.fire('Erro', response.message || 'Erro ao criar categoria', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', error);
                    Swal.fire('Erro', 'Erro ao criar categoria: ' + error, 'error');
                }
            });
        }

        function salvarNovaConta() {
            const nome = $('#nova_conta_nome').val().trim();
            const tipo = 'outros'; // Default type - user just wants a simple name
            const saldo = $('#nova_conta_saldo').val() || 0;
            const cor = $('#nova_conta_cor').val();

            if (!nome) {
                Swal.fire('Atenção', 'Preencha o nome da conta', 'warning');
                return;
            }

            $.ajax({
                url: 'mvc/ajax/financeiro.php',
                method: 'POST',
                data: {
                    action: 'criar_conta',
                    nome: nome,
                    tipo: tipo,
                    saldo_inicial: saldo,
                    cor: cor
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso', 'Conta criada com sucesso!', 'success');
                        
                        // Add new option to select
                        const newOption = new Option(nome, response.conta_id, true, true);
                        $('#conta_id').append(newOption).trigger('change');
                        
                        // Close modal and reset form
                        $('#modalNovaConta').modal('hide');
                        $('#formNovaConta')[0].reset();
                    } else {
                        Swal.fire('Erro', response.message || 'Erro ao criar conta', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro:', error);
                    Swal.fire('Erro', 'Erro ao criar conta: ' + error, 'error');
                }
            });
        }
    </script>
</body>
</html>
