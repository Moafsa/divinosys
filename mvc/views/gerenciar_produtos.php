<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Get produtos data with ingredients
$produtos = [];
if ($tenant && $filial) {
    $produtos = $db->fetchAll(
        "SELECT p.*, c.nome as categoria_nome 
         FROM produtos p 
         LEFT JOIN categorias c ON p.categoria_id = c.id 
         WHERE p.tenant_id = ? AND p.filial_id = ? 
         ORDER BY c.nome, p.nome",
        [$tenant['id'], $filial['id']]
    );
    
    // Get ingredients for each product (if tables exist)
    foreach ($produtos as &$produto) {
        try {
            $ingredientes = $db->fetchAll(
                "SELECT i.*, pi.obrigatorio, pi.preco_adicional 
                 FROM ingredientes i 
                 JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id 
                 WHERE pi.produto_id = ? AND i.tenant_id = ? AND i.filial_id = ?",
                [$produto['id'], $tenant['id'], $filial['id']]
            );
            $produto['ingredientes'] = $ingredientes;
        } catch (Exception $e) {
            // Tables don't exist yet, set empty array
            $produto['ingredientes'] = [];
        }
    }
}

// Get categorias
$categorias = [];
if ($tenant && $filial) {
    try {
        $categorias = $db->fetchAll(
            "SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ? ORDER BY nome",
            [$tenant['id'], $filial['id']]
        );
    } catch (Exception $e) {
        $categorias = [];
    }
}

// Get ingredientes (if table exists)
$ingredientes = [];
if ($tenant && $filial) {
    try {
        $ingredientes = $db->fetchAll(
            "SELECT * FROM ingredientes WHERE tenant_id = ? AND filial_id = ? ORDER BY nome",
            [$tenant['id'], $filial['id']]
        );
    } catch (Exception $e) {
        $ingredientes = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), #6c757d);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.5rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .produtos-grid {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .produto-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .produto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }
        
        .produto-imagem {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            opacity: 0.9;
        }
        
        .ingredientes-container {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        
        .ingrediente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
        
        .ingrediente-item .ingrediente-info {
            flex-grow: 1;
        }
        
        .ingrediente-item .ingrediente-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
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
                        <a class="nav-link active" href="<?php echo $router->url('gerenciar_produtos'); ?>" data-tooltip="Produtos">
                            <i class="fas fa-box"></i>
                            <span>Produtos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>" data-tooltip="Estoque">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>" data-tooltip="Financeiro">
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
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>" data-tooltip="Configurações">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>" data-tooltip="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content expanded">
                <div class="content-wrapper">
                <!-- Header -->
                <div class="header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0">
                                <i class="fas fa-box me-2"></i>
                                Produtos
                            </h2>
                            <p class="text-muted mb-0">Gerenciamento de produtos e categorias</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-primary" onclick="abrirModalProduto()">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Produto
                                </button>
                                <button class="btn btn-outline-secondary" onclick="abrirModalCategoria()">
                                    <i class="fas fa-plus me-1"></i>
                                    Nova Categoria
                                </button>
                                <button class="btn btn-outline-success" onclick="abrirModalIngrediente()">
                                    <i class="fas fa-plus me-1"></i>
                                    Novo Ingrediente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="produtos-tab" data-bs-toggle="tab" data-bs-target="#produtos" type="button" role="tab">
                            <i class="fas fa-box me-2"></i>Produtos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categorias-tab" data-bs-toggle="tab" data-bs-target="#categorias" type="button" role="tab">
                            <i class="fas fa-tags me-2"></i>Categorias
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ingredientes-tab" data-bs-toggle="tab" data-bs-target="#ingredientes" type="button" role="tab">
                            <i class="fas fa-leaf me-2"></i>Ingredientes
                        </button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="managementTabsContent">
                    <!-- Produtos Tab -->
                    <div class="tab-pane fade show active" id="produtos" role="tabpanel">
                        <div class="produtos-grid">
                    <div class="row">
                        <?php foreach ($produtos as $produto): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                <div class="produto-card">
                                    <?php if ($produto['imagem']): ?>
                                        <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" class="produto-imagem" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                    <?php else: ?>
                                        <div class="produto-imagem d-flex align-items-center justify-content-center bg-light">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="produto-nome fw-bold mb-2"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                    
                                    <?php if ($produto['categoria_nome']): ?>
                                        <div class="text-muted small mb-2">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($produto['categoria_nome']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="produto-preco h5 text-primary mb-2">
                                        R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary flex-fill" onclick="editarProduto(<?php echo $produto['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(<?php echo $produto['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                        </div>
                    </div>
                    
                    <!-- Categorias Tab -->
                    <div class="tab-pane fade" id="categorias" role="tabpanel">
                        <div class="categorias-grid">
                            <div class="row">
                                <?php foreach ($categorias as $categoria): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="produto-card">
                                            <?php if ($categoria['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($categoria['imagem']); ?>" class="produto-imagem" alt="<?php echo htmlspecialchars($categoria['nome']); ?>">
                                            <?php else: ?>
                                                <div class="produto-imagem d-flex align-items-center justify-content-center bg-light">
                                                    <i class="fas fa-tags text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="produto-nome fw-bold mb-2"><?php echo htmlspecialchars($categoria['nome']); ?></div>
                                            
                                            <?php if ($categoria['descricao']): ?>
                                                <div class="text-muted small mb-2">
                                                    <?php echo htmlspecialchars($categoria['descricao']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-2">
                                                <span class="badge <?php echo $categoria['ativo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $categoria['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                                </span>
                                                <?php if ($categoria['parent_id']): ?>
                                                    <span class="badge bg-secondary">Subcategoria</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary flex-fill" onclick="editarCategoria(<?php echo $categoria['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="excluirCategoria(<?php echo $categoria['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ingredientes Tab -->
                    <div class="tab-pane fade" id="ingredientes" role="tabpanel">
                        <div class="ingredientes-grid">
                            <div class="row">
                                <?php foreach ($ingredientes as $ingrediente): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="produto-card">
                                            <div class="produto-imagem d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-leaf text-success"></i>
                                            </div>
                                            
                                            <div class="produto-nome fw-bold mb-2"><?php echo htmlspecialchars($ingrediente['nome']); ?></div>
                                            
                                            <?php if ($ingrediente['descricao']): ?>
                                                <div class="text-muted small mb-2">
                                                    <?php echo htmlspecialchars($ingrediente['descricao']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="produto-preco h5 text-primary mb-2">
                                                +R$ <?php echo number_format($ingrediente['preco_adicional'] ?? 0, 2, ',', '.'); ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <span class="badge <?php echo $ingrediente['ativo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $ingrediente['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary flex-fill" onclick="editarIngrediente(<?php echo $ingrediente['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="excluirIngrediente(<?php echo $ingrediente['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Produto -->
    <div class="modal fade" id="modalProduto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-box me-2"></i>
                        <span id="modalProdutoTitulo">Novo Produto</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProduto">
                        <input type="hidden" id="produtoId" value="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome do Produto</label>
                                    <input type="text" class="form-control" id="produtoNome" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Categoria</label>
                                    <select class="form-select" id="produtoCategoria">
                                        <option value="">Selecione uma categoria</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Preço Normal</label>
                                    <input type="number" class="form-control" id="produtoPrecoNormal" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Preço Mini</label>
                                    <input type="number" class="form-control" id="produtoPrecoMini" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Descrição</label>
                                    <textarea class="form-control" id="produtoDescricao" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Estoque Atual</label>
                                    <input type="number" class="form-control" id="produtoEstoque" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Estoque Mínimo</label>
                                    <input type="number" class="form-control" id="produtoEstoqueMinimo" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Preço de Custo</label>
                                    <input type="number" class="form-control" id="produtoPrecoCusto" step="0.01">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Imagem do Produto</label>
                                    <input type="file" class="form-control" id="produtoImagem" accept="image/*">
                                    <div class="form-text">Formatos aceitos: JPG, PNG, GIF (máx. 2MB)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="produtoAtivo" checked>
                                        <label class="form-check-label" for="produtoAtivo">
                                            Produto Ativo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Ingredientes</label>
                                    <div class="ingredientes-container">
                                        <div class="d-flex gap-2 mb-2">
                                            <select class="form-select" id="ingrediente_select">
                                                <option value="">Selecione um ingrediente</option>
                                                <?php foreach ($ingredientes as $ingrediente): ?>
                                                    <option value="<?php echo $ingrediente['id']; ?>" data-preco="<?php echo $ingrediente['preco_adicional']; ?>">
                                                        <?php echo htmlspecialchars($ingrediente['nome']); ?> - R$ <?php echo number_format($ingrediente['preco_adicional'], 2, ',', '.'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-outline-primary" onclick="adicionarIngrediente()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div id="ingredientes_lista" class="ingredientes-lista d-flex flex-wrap gap-2">
                                            <!-- Ingredientes serão adicionados aqui como tags -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarProduto()">
                        <i class="fas fa-save me-2"></i>
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Categoria -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>
                        <span id="modalCategoriaTitulo">Nova Categoria</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCategoria" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" id="categoriaId" name="id">
                        
                        <div class="mb-3">
                            <label for="categoriaNome" class="form-label">Nome da Categoria *</label>
                            <input type="text" class="form-control" id="categoriaNome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoriaDescricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="categoriaDescricao" name="descricao" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoriaParent" class="form-label">Categoria Pai</label>
                            <select class="form-select" id="categoriaParent" name="parent_id">
                                <option value="">Categoria Principal</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoriaImagem" class="form-label">Imagem da Categoria</label>
                            <input type="file" class="form-control" id="categoriaImagem" name="imagem" accept="image/*">
                            <div class="form-text">Formatos aceitos: JPG, PNG, GIF (máx. 2MB)</div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="categoriaAtivo" name="ativo" checked>
                            <label class="form-check-label" for="categoriaAtivo">
                                Categoria Ativa
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Categoria
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ingrediente -->
    <div class="modal fade" id="modalIngrediente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-leaf me-2"></i>
                        <span id="modalIngredienteTitulo">Novo Ingrediente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formIngrediente" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" id="ingredienteId" name="id">
                        
                        <div class="mb-3">
                            <label for="ingredienteNome" class="form-label">Nome do Ingrediente *</label>
                            <input type="text" class="form-control" id="ingredienteNome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ingredienteDescricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="ingredienteDescricao" name="descricao" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ingredientePreco" class="form-label">Preço Adicional (R$)</label>
                            <input type="number" class="form-control" id="ingredientePreco" name="preco_adicional" 
                                   step="0.01" min="0" value="0.00">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ingredienteAtivo" name="ativo" checked>
                            <label class="form-check-label" for="ingredienteAtivo">
                                Ingrediente Ativo
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Salvar Ingrediente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function abrirModalProduto() {
            document.getElementById('modalProdutoTitulo').textContent = 'Novo Produto';
            document.getElementById('formProduto').reset();
            document.getElementById('produtoId').value = '';
            document.getElementById('produtoPrecoMini').value = '0';
            new bootstrap.Modal(document.getElementById('modalProduto')).show();
        }

        function editarProduto(produtoId) {
            console.log('Editando produto ID:', produtoId);
            
            // Buscar dados do produto
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_produto&id=${produtoId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Preencher modal com dados do produto
                    document.getElementById('produtoId').value = data.produto.id;
                    document.getElementById('produtoNome').value = data.produto.nome;
                    document.getElementById('produtoDescricao').value = data.produto.descricao || '';
                    document.getElementById('produtoPrecoNormal').value = data.produto.preco_normal;
                    document.getElementById('produtoPrecoMini').value = data.produto.preco_mini || '0';
                    document.getElementById('produtoCategoria').value = data.produto.categoria_id || '';
                    document.getElementById('produtoAtivo').checked = data.produto.ativo;
                    document.getElementById('produtoEstoque').value = data.produto.estoque_atual || '0';
                    document.getElementById('produtoEstoqueMinimo').value = data.produto.estoque_minimo || '0';
                    document.getElementById('produtoPrecoCusto').value = data.produto.preco_custo || '0';
                    
                    // Limpar ingredientes existentes
                    document.getElementById('ingredientes_lista').innerHTML = '';
                    
                    // Carregar ingredientes do produto se existirem
                    if (data.ingredientes && data.ingredientes.length > 0) {
                        data.ingredientes.forEach(ingrediente => {
                            const tag = document.createElement('span');
                            tag.className = 'badge bg-primary me-2 mb-2 d-inline-flex align-items-center';
                            tag.setAttribute('data-ingrediente-id', ingrediente.id);
                            tag.innerHTML = `
                                ${ingrediente.nome} (R$ ${parseFloat(ingrediente.preco_adicional).toFixed(2).replace('.', ',')})
                                <button type="button" class="btn-close btn-close-white ms-2" onclick="removerIngrediente(this)" style="font-size: 0.7em;"></button>
                            `;
                            document.getElementById('ingredientes_lista').appendChild(tag);
                        });
                    }
                    
                    // Abrir modal
                    document.getElementById('modalProdutoTitulo').textContent = 'Editar Produto';
                    new bootstrap.Modal(document.getElementById('modalProduto')).show();
                } else {
                    alert('Erro ao buscar produto: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao buscar produto: ' + error.message);
            });
        }

        function excluirProduto(produtoId) {
            Swal.fire({
                title: 'Excluir Produto',
                text: 'Deseja realmente excluir este produto?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=excluir_produto&id=${produtoId}`
            })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire('Erro', 'Erro ao excluir produto', 'error');
                    });
                }
            });
        }

        function salvarProduto() {
            // Coletar dados manualmente
            const produtoId = document.getElementById('produtoId').value;
            const nome = document.getElementById('produtoNome').value;
            const descricao = document.getElementById('produtoDescricao').value;
            const precoNormal = document.getElementById('produtoPrecoNormal').value;
            const precoMini = document.getElementById('produtoPrecoMini').value || '0';
            const categoriaId = document.getElementById('produtoCategoria').value || null;
            const ativo = document.getElementById('produtoAtivo').checked ? 1 : 0;
            const estoqueAtual = document.getElementById('produtoEstoque').value || '0';
            const estoqueMinimo = document.getElementById('produtoEstoqueMinimo').value || '0';
            const precoCusto = document.getElementById('produtoPrecoCusto').value || '0';
            
            // Coletar ingredientes selecionados
            const ingredientesSelecionados = [];
            document.querySelectorAll('#ingredientes_lista .badge[data-ingrediente-id]').forEach(tag => {
                ingredientesSelecionados.push(tag.getAttribute('data-ingrediente-id'));
            });
            
            console.log('Dados coletados:', {
                produtoId, nome, descricao, precoNormal, precoMini, categoriaId, ativo, estoqueAtual, estoqueMinimo, precoCusto, ingredientesSelecionados
            });
            
            // Validar dados obrigatórios
            if (!nome || !precoNormal) {
                Swal.fire({
                    title: 'Campos Obrigatórios!',
                    text: 'Nome e preço normal são obrigatórios!',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validar categoria obrigatória
            if (!categoriaId) {
                Swal.fire({
                    title: 'Categoria Obrigatória!',
                    text: 'Por favor, selecione uma categoria para o produto!',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                }).then(() => {
                    document.getElementById('produtoCategoria').focus();
                });
                return;
            }
            
            // Criar FormData
            const formData = new FormData();
            formData.append('action', 'salvar_produto');
            if (produtoId) formData.append('produto_id', produtoId);
            formData.append('nome', nome);
            formData.append('descricao', descricao);
            formData.append('preco_normal', precoNormal);
            formData.append('preco_mini', precoMini);
            if (categoriaId) formData.append('categoria_id', categoriaId);
            formData.append('ativo', ativo);
            formData.append('estoque_atual', estoqueAtual);
            formData.append('estoque_minimo', estoqueMinimo);
            formData.append('preco_custo', precoCusto);
            formData.append('ingredientes', JSON.stringify(ingredientesSelecionados));
            
            // Adicionar imagem se selecionada
            const imagemInput = document.getElementById('produtoImagem');
            if (imagemInput.files.length > 0) {
                formData.append('imagem', imagemInput.files[0]);
            }
            
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalProduto')).hide();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Erro desconhecido ao salvar produto',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro de Conexão!',
                    text: 'Erro ao salvar produto: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        function abrirModalCategoria() {
            document.getElementById('modalCategoriaTitulo').textContent = 'Nova Categoria';
            document.getElementById('formCategoria').reset();
            document.getElementById('categoriaId').value = '';
            new bootstrap.Modal(document.getElementById('modalCategoria')).show();
        }

        function salvarCategoria() {
            // Coletar dados manualmente
            const categoriaId = document.getElementById('categoriaId').value;
            const nome = document.getElementById('categoriaNome').value;
            const descricao = document.getElementById('categoriaDescricao').value;
            const parentId = document.getElementById('categoriaParent').value;
            const ativo = document.getElementById('categoriaAtivo').checked ? 1 : 0;
            
            console.log('Dados coletados:', {
                categoriaId, nome, descricao, parentId, ativo
            });
            
            // Criar FormData manualmente
            const formData = new FormData();
            formData.append('action', 'salvar_categoria');
            if (categoriaId) formData.append('categoria_id', categoriaId);
            formData.append('nome', nome);
            formData.append('descricao', descricao);
            formData.append('parent_id', parentId);
            formData.append('ativo', ativo);
            
            console.log('Enviando dados da categoria:', formData);
            
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                    // Recarregar a página mantendo a aba de categorias
                    window.location.href = 'index.php?view=gerenciar_produtos#categorias';
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar categoria');
            });
        }
        
        function abrirModalIngrediente() {
            document.getElementById('modalIngredienteTitulo').textContent = 'Novo Ingrediente';
            document.getElementById('formIngrediente').reset();
            document.getElementById('ingredienteId').value = '';
            document.getElementById('ingredientePreco').value = '0.00';
            new bootstrap.Modal(document.getElementById('modalIngrediente')).show();
        }

        function salvarIngrediente() {
            // Coletar dados manualmente
            const ingredienteId = document.getElementById('ingredienteId').value;
            const nome = document.getElementById('ingredienteNome').value;
            const descricao = document.getElementById('ingredienteDescricao').value;
            const precoAdicional = document.getElementById('ingredientePreco').value;
            const ativo = document.getElementById('ingredienteAtivo').checked ? 1 : 0;
            
            console.log('Dados coletados:', {
                ingredienteId, nome, descricao, precoAdicional, ativo
            });
            
            // Criar FormData manualmente
            const formData = new FormData();
            formData.append('action', 'salvar_ingrediente');
            if (ingredienteId) formData.append('ingrediente_id', ingredienteId);
            formData.append('nome', nome);
            formData.append('descricao', descricao);
            formData.append('preco_adicional', precoAdicional);
            formData.append('ativo', ativo);
            
            console.log('Enviando dados do ingrediente:', formData);
            
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalIngrediente')).hide();
                    // Recarregar a página mantendo a aba de ingredientes
                    window.location.href = 'index.php?view=gerenciar_produtos#ingredientes';
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar ingrediente');
            });
        }

        // Funções para editar e excluir categorias
        function editarCategoria(id) {
            console.log('Editando categoria ID:', id);
            
            // Buscar dados da categoria via AJAX
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_categoria&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta da categoria:', data);
                if (data.success) {
                    // Preencher modal com dados da categoria
                    document.getElementById('categoriaId').value = data.categoria.id;
                    document.getElementById('categoriaNome').value = data.categoria.nome;
                    document.getElementById('categoriaDescricao').value = data.categoria.descricao || '';
                    document.getElementById('categoriaParent').value = data.categoria.parent_id || '';
                    document.getElementById('categoriaAtivo').checked = data.categoria.ativo;
                    
                    // Abrir modal
                    document.getElementById('modalCategoriaTitulo').textContent = 'Editar Categoria';
                    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
                } else {
                    alert('Erro ao buscar categoria: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao buscar categoria: ' + error.message);
            });
        }

        function excluirCategoria(id) {
            if (confirm('Tem certeza que deseja excluir esta categoria?')) {
                fetch('mvc/ajax/produtos_fix.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=excluir_categoria&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Categoria excluída com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao excluir categoria: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir categoria');
                });
            }
        }

        // Funções para editar e excluir ingredientes
        function editarIngrediente(id) {
            console.log('Editando ingrediente ID:', id);
            
            // Buscar dados do ingrediente via AJAX
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=buscar_ingrediente&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do ingrediente:', data);
                if (data.success) {
                    // Preencher modal com dados do ingrediente
                    document.getElementById('ingredienteId').value = data.ingrediente.id;
                    document.getElementById('ingredienteNome').value = data.ingrediente.nome;
                    document.getElementById('ingredienteDescricao').value = data.ingrediente.descricao || '';
                    document.getElementById('ingredientePreco').value = data.ingrediente.preco_adicional || '0.00';
                    document.getElementById('ingredienteAtivo').checked = data.ingrediente.ativo;
                    
                    // Abrir modal
                    document.getElementById('modalIngredienteTitulo').textContent = 'Editar Ingrediente';
                    new bootstrap.Modal(document.getElementById('modalIngrediente')).show();
                } else {
                    alert('Erro ao buscar ingrediente: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao buscar ingrediente: ' + error.message);
            });
        }

        function excluirIngrediente(id) {
            if (confirm('Tem certeza que deseja excluir este ingrediente?')) {
                fetch('mvc/ajax/produtos_fix.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=excluir_ingrediente&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ingrediente excluído com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao excluir ingrediente: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir ingrediente');
                });
            }
        }
        
        function adicionarIngrediente() {
            const select = document.getElementById('ingrediente_select');
            const ingredienteId = select.value;
            const ingredienteNome = select.options[select.selectedIndex].text.split(' - ')[0];
            const ingredientePreco = select.options[select.selectedIndex].dataset.preco || 0;
            
            if (!ingredienteId) {
                Swal.fire('Atenção', 'Selecione um ingrediente', 'warning');
                return;
            }
            
            // Verificar se já foi adicionado
            const existing = document.querySelector(`[data-ingrediente-id="${ingredienteId}"]`);
            if (existing) {
                Swal.fire('Atenção', 'Este ingrediente já foi adicionado', 'warning');
                return;
            }
            
            const lista = document.getElementById('ingredientes_lista');
            const tag = document.createElement('span');
            tag.className = 'badge bg-primary me-2 mb-2 d-inline-flex align-items-center';
            tag.setAttribute('data-ingrediente-id', ingredienteId);
            tag.innerHTML = `
                ${ingredienteNome} (R$ ${parseFloat(ingredientePreco).toFixed(2).replace('.', ',')})
                <button type="button" class="btn-close btn-close-white ms-2" onclick="removerIngrediente(this)" style="font-size: 0.7em;"></button>
            `;
            
            lista.appendChild(tag);
            select.value = '';
        }
        
        function removerIngrediente(button) {
            button.closest('.badge').remove();
        }
        
        // Funções para recarregar as abas
        function carregarCategorias() {
            // Recarregar apenas a aba de categorias
            const tabCategorias = document.getElementById('categorias-tab');
            if (tabCategorias) {
                tabCategorias.click();
            }
        }
        
        function carregarIngredientes() {
            // Recarregar apenas a aba de ingredientes
            const tabIngredientes = document.getElementById('ingredientes-tab');
            if (tabIngredientes) {
                tabIngredientes.click();
            }
        }
        
        // Ativar aba correta quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash === '#categorias') {
                const tabCategorias = document.getElementById('categorias-tab');
                if (tabCategorias) {
                    tabCategorias.click();
                }
            } else if (hash === '#ingredientes') {
                const tabIngredientes = document.getElementById('ingredientes-tab');
                if (tabIngredientes) {
                    tabIngredientes.click();
                }
            }
            
            // Adicionar event listeners para os formulários
            document.getElementById('formCategoria').addEventListener('submit', function(e) {
                e.preventDefault();
                salvarCategoria();
            });
            
            document.getElementById('formIngrediente').addEventListener('submit', function(e) {
                e.preventDefault();
                salvarIngrediente();
            });
        });
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Menu Component -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
