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
try {
    $categorias = $db->fetchAll(
        "SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY tipo, nome",
        [$tenant['id'], $filial['id']]
    );
} catch (Exception $e) {
    error_log("Erro ao buscar categorias financeiras: " . $e->getMessage());
    $categorias = [];
}

try {
    $contas = $db->fetchAll(
        "SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
        [$tenant['id'], $filial['id']]
    );
    
    // Se não encontrou contas, criar contas padrão automaticamente
    if (empty($contas) && $tenant && $filial) {
        error_log("Nenhuma conta encontrada para tenant {$tenant['id']}/filial {$filial['id']}, criando contas padrão...");
        
        $contasPadrao = [
            ['nome' => 'Caixa Principal', 'tipo' => 'caixa', 'cor' => '#28a745', 'icone' => 'fas fa-cash-register'],
            ['nome' => 'Conta Corrente', 'tipo' => 'banco', 'cor' => '#007bff', 'icone' => 'fas fa-university'],
            ['nome' => 'PIX', 'tipo' => 'pix', 'cor' => '#17a2b8', 'icone' => 'fas fa-mobile-alt'],
            ['nome' => 'Cartão de Crédito', 'tipo' => 'cartao', 'cor' => '#dc3545', 'icone' => 'fas fa-credit-card']
        ];
        
        foreach ($contasPadrao as $conta) {
            try {
                $db->insert('contas_financeiras', array_merge($conta, [
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id'],
                    'saldo_inicial' => 0.00,
                    'saldo_atual' => 0.00,
                    'ativo' => true
                ]));
            } catch (Exception $e) {
                error_log("Erro ao criar conta padrão {$conta['nome']}: " . $e->getMessage());
            }
        }
        
        // Buscar novamente após criar
        $contas = $db->fetchAll(
            "SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY nome",
            [$tenant['id'], $filial['id']]
        );
    }
} catch (Exception $e) {
    error_log("Erro ao buscar contas financeiras: " . $e->getMessage());
    $contas = [];
}

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
                    <form id="lancamentoForm" enctype="multipart/form-data" novalidate>
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
                                                <select class="form-select" name="categoria_id" id="categoria_id">
                                                    <option value="">Selecione uma categoria</option>
                                                    <?php foreach ($categorias as $categoria): ?>
                                                        <option value="<?= $categoria['id'] ?>" data-tipo="<?= $categoria['tipo'] ?>">
                                                            <?= htmlspecialchars($categoria['nome']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" onclick="abrirModalCategoriaRapida()" title="Adicionar nova categoria">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Conta <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <select class="form-select" name="conta_id" id="conta_id" required>
                                                    <option value="">Selecione uma conta</option>
                                                    <?php foreach ($contas as $conta): ?>
                                                        <option value="<?= $conta['id'] ?>" data-tipo="<?= $conta['tipo'] ?>">
                                                            <?= htmlspecialchars($conta['nome']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" onclick="abrirModalContaRapida()" title="Adicionar nova conta">
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

    <!-- Modal Categoria Rápida -->
    <div class="modal fade" id="modalCategoriaRapida" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>
                        Nova Categoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCategoriaRapida" novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="categoriaRapidaNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cor</label>
                            <input type="color" class="form-control form-control-color" id="categoriaRapidaCor" name="cor" value="#007bff">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Conta Rápida -->
    <div class="modal fade" id="modalContaRapida" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-wallet me-2"></i>
                        Nova Conta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formContaRapida" novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contaRapidaNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Saldo Inicial</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="contaRapidaSaldo" name="saldo_inicial" step="0.01" value="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Salvar
                        </button>
                    </div>
                </form>
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
            $('.form-select').select2({
                theme: 'bootstrap-5',
                allowClear: false
            });
            
            // Ensure Select2 syncs values with native select on change
            $('.form-select').on('change', function() {
                const $select = $(this);
                const nativeSelect = this;
                // Force sync Select2 value with native select
                if ($select.data('select2')) {
                    nativeSelect.value = $select.val() || '';
                }
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
            // Don't process if event came from a modal
            if ($(e.target).closest('.modal').length > 0) {
                console.log('Submit ignorado - veio de um modal');
                return;
            }
            
            console.log('Formulário principal submetido!');
            e.preventDefault();
            e.stopImmediatePropagation();
            
            // Ensure Select2 values are synced with native select elements before validation
            $('#conta_id, #categoria_id, #conta_destino_id, #tipo').each(function() {
                const $select = $(this);
                const nativeSelect = this;
                if ($select.data('select2')) {
                    // Sync Select2 value back to native select
                    const select2Value = $select.val();
                    if (select2Value !== null && select2Value !== undefined) {
                        nativeSelect.value = select2Value;
                    }
                }
            });
            
            // Validação dos campos obrigatórios - use both Select2 and native methods
            const tipoSelect = document.getElementById('tipo');
            const valorInput = document.getElementById('valor');
            const dataInput = document.getElementById('data_lancamento');
            const descricaoTextarea = document.getElementById('descricao');
            const contaSelect = document.getElementById('conta_id');
            const categoriaSelect = document.getElementById('categoria_id');
            
            const tipoLancamento = $('#tipo').val() || tipoSelect.value;
            const valor = $('#valor').val() || valorInput.value;
            const dataLancamento = $('#data_lancamento').val() || dataInput.value;
            const descricao = $('#descricao').val() || descricaoTextarea.value;
            const contaId = $('#conta_id').val() || contaSelect.value;
            const categoriaId = $('#categoria_id').val() || categoriaSelect.value;
            
            // Trim string values
            const descricaoTrimmed = descricao ? descricao.trim() : '';
            const valorNum = valor ? parseFloat(valor.toString().replace(',', '.')) : 0;
            
            console.log('Validação:', {
                tipoLancamento, valor, valorNum, dataLancamento, descricao: descricaoTrimmed, contaId, categoriaId
            });
            
            if (!tipoLancamento || !valor || valorNum <= 0 || isNaN(valorNum) || !dataLancamento || !descricaoTrimmed || !contaId) {
                console.log('Campos obrigatórios não preenchidos:', {
                    tipoLancamento: !tipoLancamento,
                    valor: !valor,
                    valorNum: valorNum,
                    dataLancamento: !dataLancamento,
                    descricao: !descricaoTrimmed,
                    contaId: !contaId
                });
                Swal.fire('Erro!', 'Todos os campos obrigatórios devem ser preenchidos', 'error');
                return;
            }
            
            // Validação específica para transferências
            if (tipoLancamento === 'transferencia') {
                const contaDestinoId = $('#conta_destino_id').val() || document.getElementById('conta_destino_id').value;
                if (!contaDestinoId) {
                    Swal.fire('Erro!', 'Para transferências, a conta destino é obrigatória', 'error');
                    return;
                }
                if (contaId === contaDestinoId) {
                    Swal.fire('Erro!', 'A conta origem e destino devem ser diferentes', 'error');
                    return;
                }
            }
            
            // Create FormData manually to ensure all values are included
            const formData = new FormData();
            
            // Add all required fields explicitly
            formData.append('tipo_lancamento', tipoLancamento);
            formData.append('valor', valorNum.toString().replace(',', '.'));
            formData.append('data_lancamento', dataLancamento);
            formData.append('descricao', descricaoTrimmed);
            formData.append('conta_id', contaId);
            
            // Add optional fields
            if (categoriaId) {
                formData.append('categoria_id', categoriaId);
            }
            
            // Add other form fields
            const status = $('#status').val() || document.getElementById('status').value || 'confirmado';
            formData.append('status', status);
            
            const observacoes = $('#observacoes').val() || document.getElementById('observacoes').value || '';
            if (observacoes) {
                formData.append('observacoes', observacoes);
            }
            
            const pedidoId = $('#pedido_id').val() || document.getElementById('pedido_id').value || '';
            if (pedidoId) {
                formData.append('pedido_id', pedidoId);
            }
            
            const dataVencimento = $('#data_vencimento').val() || document.getElementById('data_vencimento').value || '';
            if (dataVencimento) {
                formData.append('data_vencimento', dataVencimento);
            }
            
            const dataPagamento = $('#data_pagamento').val() || document.getElementById('data_pagamento').value || '';
            if (dataPagamento) {
                formData.append('data_pagamento', dataPagamento);
            }
            
            const formaPagamento = $('#forma_pagamento').val() || document.getElementById('forma_pagamento').value || '';
            if (formaPagamento) {
                formData.append('forma_pagamento', formaPagamento);
            }
            
            const recorrencia = $('#recorrencia').val() || document.getElementById('recorrencia').value || '';
            if (recorrencia && recorrencia !== 'nenhuma') {
                formData.append('recorrencia', recorrencia);
            }
            
            const dataFimRecorrencia = $('#data_fim_recorrencia').val() || document.getElementById('data_fim_recorrencia').value || '';
            if (dataFimRecorrencia) {
                formData.append('data_fim_recorrencia', dataFimRecorrencia);
            }
            
            // Handle transfer specific field
            if (tipoLancamento === 'transferencia') {
                const contaDestinoId = $('#conta_destino_id').val() || document.getElementById('conta_destino_id').value;
                if (contaDestinoId) {
                    formData.append('conta_destino_id', contaDestinoId);
                }
            }
            
            // Add file attachments if any
            const fileInput = document.getElementById('anexos');
            if (fileInput && fileInput.files) {
                for (let i = 0; i < fileInput.files.length; i++) {
                    formData.append('anexos[]', fileInput.files[i]);
                }
            }
            
            formData.append('action', 'criar_lancamento');
            
            // Debug: Log all FormData values
            console.log('FormData contents:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + (pair[1] instanceof File ? '[File: ' + pair[1].name + ']' : pair[1]));
            }

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
            .then(async response => {
                console.log('Resposta recebida:', response.status);
                console.log('Headers da resposta:', response.headers);
                
                const text = await response.text();
                console.log('Resposta em texto:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    console.error('Texto da resposta:', text);
                    throw new Error('Resposta não é um JSON válido: ' + text.substring(0, 200));
                }
                
                if (!response.ok) {
                    console.error('Erro HTTP:', response.status, response.statusText);
                    console.error('Dados do erro:', data);
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }
                
                return data;
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
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Erro ao criar lançamento',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
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
        
        // Funções para criar categoria/conta rapidamente
        function abrirModalCategoriaRapida() {
            $('#formCategoriaRapida')[0].reset();
            $('#categoriaRapidaCor').val('#007bff');
            new bootstrap.Modal(document.getElementById('modalCategoriaRapida')).show();
        }

        function abrirModalContaRapida() {
            $('#formContaRapida')[0].reset();
            $('#contaRapidaSaldo').val('0.00');
            new bootstrap.Modal(document.getElementById('modalContaRapida')).show();
        }

        // Salvar categoria rápida
        $('#formCategoriaRapida').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('=== FORMULARIO CATEGORIA RAPIDA SUBMETIDO ===');
            
            // Get values directly from inputs
            const nomeInput = document.getElementById('categoriaRapidaNome');
            const corInput = document.getElementById('categoriaRapidaCor');
            const tipoSelect = document.getElementById('tipo');
            
            if (!nomeInput) {
                console.error('Campo nomeInput não encontrado!');
                Swal.fire('Erro!', 'Erro ao acessar campo nome', 'error');
                return;
            }
            
            const nome = (nomeInput.value || $('#categoriaRapidaNome').val() || '').trim();
            const cor = (corInput ? corInput.value : null) || $('#categoriaRapidaCor').val() || '#007bff';
            const tipoLancamento = (tipoSelect ? tipoSelect.value : null) || $('#tipo').val() || '';
            
            console.log('Valores capturados:', {
                nome: nome,
                cor: cor,
                tipoLancamento: tipoLancamento,
                nomeInputValue: nomeInput.value,
                nomeInputExists: !!nomeInput
            });
            
            if (!nome) {
                console.error('Nome vazio!');
                Swal.fire('Erro!', 'O nome é obrigatório', 'error');
                return;
            }
            
            // Create FormData manually to ensure values are included
            const formData = new FormData();
            formData.append('action', 'criar_categoria_rapida');
            formData.append('nome', nome);
            formData.append('cor', cor);
            if (tipoLancamento) {
                formData.append('tipo_lancamento', tipoLancamento);
            }
            
            // Debug: Log all FormData values
            console.log('FormData contents (categoria):');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                console.log('Resposta do servidor (categoria):', text);
                console.log('Status HTTP:', response.status);
                
                if (!response.ok) {
                    try {
                        const json = JSON.parse(text);
                        console.error('Erro JSON:', json);
                        throw new Error(json.message || `HTTP ${response.status}: ${response.statusText}`);
                    } catch (e) {
                        if (e instanceof Error && e.message && e.message !== text) {
                            throw e;
                        }
                        console.error('Erro texto:', text);
                        throw new Error(text || `HTTP ${response.status}: ${response.statusText}`);
                    }
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    // Adicionar nova categoria ao select
                    const categoria = data.categoria;
                    const option = new Option(categoria.nome, categoria.id, true, true);
                    option.setAttribute('data-tipo', categoria.tipo);
                    $('#categoria_id').append(option).trigger('change');
                    
                    Swal.fire('Sucesso!', 'Categoria criada com sucesso!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalCategoriaRapida')).hide();
                    $('#formCategoriaRapida')[0].reset();
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao criar categoria', 'error');
                }
            })
            .catch(error => {
                console.error('Erro completo:', error);
                Swal.fire('Erro!', error.message || 'Erro ao processar solicitação', 'error');
            });
        });

        // Salvar conta rápida
        $('#formContaRapida').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('=== FORMULARIO CONTA RAPIDA SUBMETIDO ===');
            
            // Get values directly from inputs
            const nomeInput = document.getElementById('contaRapidaNome');
            const saldoInput = document.getElementById('contaRapidaSaldo');
            
            if (!nomeInput) {
                console.error('Campo nomeInput não encontrado!');
                Swal.fire('Erro!', 'Erro ao acessar campo nome', 'error');
                return;
            }
            
            const nome = (nomeInput.value || $('#contaRapidaNome').val() || '').trim();
            const saldoInicial = (saldoInput ? saldoInput.value : null) || $('#contaRapidaSaldo').val() || '0.00';
            
            console.log('Valores capturados (conta):', {
                nome: nome,
                saldoInicial: saldoInicial,
                nomeInputValue: nomeInput.value,
                nomeInputExists: !!nomeInput
            });
            
            if (!nome) {
                console.error('Nome vazio!');
                Swal.fire('Erro!', 'O nome é obrigatório', 'error');
                return;
            }
            
            // Create FormData manually to ensure values are included
            const formData = new FormData();
            formData.append('action', 'criar_conta_rapida');
            formData.append('nome', nome);
            formData.append('saldo_inicial', saldoInicial);
            
            // Debug: Log all FormData values
            console.log('FormData contents (conta):');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            fetch('mvc/ajax/financeiro.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                console.log('Resposta do servidor (conta):', text);
                console.log('Status HTTP:', response.status);
                
                if (!response.ok) {
                    try {
                        const json = JSON.parse(text);
                        console.error('Erro JSON:', json);
                        throw new Error(json.message || `HTTP ${response.status}: ${response.statusText}`);
                    } catch (e) {
                        if (e instanceof Error && e.message && e.message !== text) {
                            throw e;
                        }
                        console.error('Erro texto:', text);
                        throw new Error(text || `HTTP ${response.status}: ${response.statusText}`);
                    }
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                if (data.success) {
                    // Adicionar nova conta aos selects
                    const conta = data.conta;
                    const option = new Option(conta.nome, conta.id, true, true);
                    option.setAttribute('data-tipo', conta.tipo);
                    $('#conta_id').append(option).trigger('change');
                    $('#conta_destino_id').append(new Option(conta.nome, conta.id));
                    
                    Swal.fire('Sucesso!', 'Conta criada com sucesso!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalContaRapida')).hide();
                    $('#formContaRapida')[0].reset();
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao criar conta', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', error.message || 'Erro ao processar solicitação', 'error');
            });
        });

        // Inicializar página
        $(document).ready(function() {
            console.log('Inicializando página de lançamento financeiro');
            
            // Definir data padrão
            const hoje = new Date().toISOString().split('T')[0];
            $('#data_lancamento').val(hoje);
            
            // Inicializar resumo
            atualizarResumo();
            
            // Adicionar event handler direto para o botão do formulário principal apenas
            $('#lancamentoForm button[type="submit"]').on('click', function(e) {
                console.log('Botão do formulário principal clicado!');
                e.preventDefault();
                e.stopPropagation();
                $('#lancamentoForm').trigger('submit');
            });
            
            console.log('Página inicializada com sucesso');
        });
    </script>
</body>
</html>
