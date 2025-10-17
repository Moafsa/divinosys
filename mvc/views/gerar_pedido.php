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

// Check if editing a pedido
$editarPedido = null;
$pedidoId = $_GET['editar'] ?? '';
if ($pedidoId) {
    $editarPedido = $db->fetch(
        "SELECT p.*, 
                CASE 
                    WHEN p.idmesa = '999' THEN '999'
                    ELSE p.idmesa
                END as id_mesa,
                CASE 
                    WHEN p.idmesa = '999' THEN 'Delivery'
                    WHEN m.nome IS NOT NULL THEN m.nome
                    ELSE 'Mesa ' || p.idmesa
                END as mesa_nome
         FROM pedido p 
         LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa AND m.tenant_id = p.tenant_id AND m.filial_id = p.filial_id
         WHERE p.idpedido = ? AND p.tenant_id = ? AND p.filial_id = ?",
        [$pedidoId, $tenant['id'], $filial['id']]
    );
    
    if ($editarPedido) {
        try {
            // Get itens do pedido
            $editarPedido['itens'] = $db->fetchAll(
                "SELECT pi.*, pr.nome as produto_nome 
                 FROM pedido_itens pi 
                 LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = ?
                 WHERE pi.pedido_id = ? AND pi.tenant_id = ?",
                [$filial['id'], $pedidoId, $tenant['id']]
            );
            
            // Processar ingredientes para cada item
            foreach ($editarPedido['itens'] as &$item) {
                $item['ingredientes_com'] = $item['ingredientes_com'] ? explode(', ', $item['ingredientes_com']) : [];
                $item['ingredientes_sem'] = $item['ingredientes_sem'] ? explode(', ', $item['ingredientes_sem']) : [];
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar itens do pedido $pedidoId: " . $e->getMessage());
            $editarPedido['itens'] = [];
        }
    }
}

// Get mesas data
$mesas = [];
if ($tenant && $filial) {
    $mesas = $db->fetchAll(
        "SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id_mesa::integer",
        [$tenant['id'], $filial['id']]
    );
}

// Get produtos data with ingredients
$produtos = [];
if ($tenant && $filial) {
    try {
        $produtos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = 1
             ORDER BY c.nome, p.nome",
            [$tenant['id'], $filial['id']]
        );
    } catch (Exception $e) {
        // Se der erro, buscar sem filtro de ativo
        $produtos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ?
             ORDER BY c.nome, p.nome",
            [$tenant['id'], $filial['id']]
        );
    }
}

// Se não encontrou produtos com tenant/filial específicos, buscar todos
if (empty($produtos)) {
    try {
        $produtos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.ativo = 1
             ORDER BY c.nome, p.nome"
        );
    } catch (Exception $e) {
        // Se der erro, buscar sem filtro de ativo
        $produtos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             ORDER BY c.nome, p.nome"
        );
    }
}

// Buscar ingredientes para cada produto
foreach ($produtos as &$produto) {
    try {
        $ingredientes = $db->fetchAll(
            "SELECT i.id, i.nome, i.tipo, i.preco_adicional, pi.padrao
             FROM ingredientes i
             INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id
             WHERE pi.produto_id = ? AND i.disponivel = true
             ORDER BY i.tipo, i.nome",
            [$produto['id']]
        );
        $produto['ingredientes'] = $ingredientes;
    } catch (Exception $e) {
        $produto['ingredientes'] = [];
    }
}

// Get categorias
$categorias = [];
if ($tenant && $filial) {
    $categorias = $db->fetchAll(
        "SELECT * FROM categorias WHERE tenant_id = ? AND filial_id = ? ORDER BY nome",
        [$tenant['id'], $filial['id']]
    );
}

// Get mesa from URL parameter
$mesaSelecionada = $_GET['mesa'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
    <link href="assets/css/mobile-pedido.css" rel="stylesheet">
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
        
        .mesa-selector {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .mesa-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
        }
        
        .mesa-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .mesa-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        
        .mesa-card.livre {
            border-color: #28a745;
        }
        
        .mesa-card.ocupada {
            border-color: #dc3545;
            opacity: 0.6;
        }
        
        .produtos-grid {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .produto-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
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
        
        .produto-nome {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .produto-preco {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .carrinho {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: sticky;
            top: 2rem;
        }
        
        .carrinho-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .carrinho-total {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
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
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box input {
            padding-left: 3rem;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .categoria-filter {
            margin-bottom: 1rem;
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
                        <a class="nav-link active" href="<?php echo $router->url('gerar_pedido'); ?>" data-tooltip="Novo Pedido">
                            <i class="fas fa-plus-circle"></i>
                            <span>Novo Pedido</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>" data-tooltip="Pedidos">
                            <i class="fas fa-list"></i>
                            <span>Pedidos</span>
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('mesas'); ?>" data-tooltip="Mesas">
                            <i class="fas fa-table"></i>
                            <span>Mesas</span>
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
                                <i class="fas fa-<?php echo $editarPedido ? 'edit' : 'plus-circle'; ?> me-2"></i>
                                <?php echo $editarPedido ? 'Editar Pedido #' . $editarPedido['idpedido'] : 'Novo Pedido'; ?>
                            </h2>
                            <p class="text-muted mb-0"><?php echo $editarPedido ? 'Edite o pedido existente' : 'Selecione uma mesa e adicione produtos ao pedido'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?php echo $router->url('pedidos'); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-list me-1"></i>
                                    Ver Pedidos
                                </a>
                                <button class="btn btn-outline-primary" onclick="limparCarrinho()">
                                    <i class="fas fa-trash me-1"></i>
                                    Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Mesa Selector -->
                    <div class="col-12">
                        <div class="mesa-selector">
                            <h5 class="mb-3">
                                <i class="fas fa-table me-2"></i>
                                Selecionar Mesa
                            </h5>
                            <div class="row" id="mesasGrid">
                                <?php if (count($mesas) > 0): ?>
                                    <?php foreach ($mesas as $mesa): ?>
                                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                                            <div class="mesa-card livre" data-mesa-id="<?php echo $mesa['id_mesa']; ?>" data-mesa-numero="<?php echo $mesa['id_mesa']; ?>" onclick="selecionarMesa(this)">
                                                <div class="mesa-numero">
                                                    <i class="fas fa-table me-2"></i>
                                                    <?php echo $mesa['id_mesa']; ?>
                                                </div>
                                                <div class="mesa-status text-success">
                                                    <i class="fas fa-circle me-1"></i>
                                                    Livre
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12 text-center text-muted py-4">
                                        <i class="fas fa-table fa-3x mb-3"></i>
                                        <p>Nenhuma mesa encontrada</p>
                                        <small>Configure as mesas nas configurações do sistema</small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Delivery Option -->
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                                    <div class="mesa-card livre" data-mesa-id="delivery" data-mesa-numero="Delivery" onclick="selecionarMesa(this)">
                                        <div class="mesa-numero">
                                            <i class="fas fa-motorcycle me-2"></i>
                                            Delivery
                                        </div>
                                        <div class="mesa-status text-success">
                                            <i class="fas fa-circle me-1"></i>
                                            Disponível
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Produtos -->
                    <div class="col-lg-8">
                        <div class="produtos-grid">
                            <h5 class="mb-3">
                                <i class="fas fa-box me-2"></i>
                                Produtos
                            </h5>
                            
                            <!-- Search and Filters -->
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchProdutos" placeholder="Buscar produtos...">
                            </div>
                            
                            <div class="categoria-filter">
                                <select class="form-select" id="categoriaFilter">
                                    <option value="">Todas as categorias</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row" id="produtosGrid">
                                <?php foreach ($produtos as $produto): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3 produto-item" data-categoria="<?php echo $produto['categoria_id']; ?>" data-nome="<?php echo strtolower($produto['nome']); ?>">
                                        <div class="produto-card" onclick="adicionarProduto(<?php echo $produto['id']; ?>)">
                                            <?php if ($produto['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" class="produto-imagem" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                            <?php else: ?>
                                                <div class="produto-imagem d-flex align-items-center justify-content-center bg-light">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="produto-nome"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                            <div class="produto-preco">
                                                R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?>
                                            </div>
                                            <?php if ($produto['categoria_nome']): ?>
                                                <div class="text-muted small produto-categoria"><?php echo htmlspecialchars($produto['categoria_nome']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="produto-botoes mt-2" onclick="event.stopPropagation();">
                                                <button class="btn btn-outline-primary btn-sm w-100" onclick="personalizarProduto(<?php echo $produto['id']; ?>)">
                                                    <i class="fas fa-cog me-1"></i> Personalizar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Carrinho -->
                    <div class="col-lg-4">
                        <div class="carrinho">
                            <h5 class="mb-3">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Carrinho
                            </h5>
                            
                            <div id="carrinhoItens">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                    <p>Carrinho vazio</p>
                                    <small>Selecione uma mesa e adicione produtos</small>
                                </div>
                            </div>
                            
                            <div class="carrinho-total" id="carrinhoTotal" style="display: none;">
                                <div class="mb-3">
                                    <label for="observacaoPedido" class="form-label">Observação do Pedido</label>
                                    <textarea class="form-control" id="observacaoPedido" rows="2" placeholder="Observações especiais para o pedido..."></textarea>
                                </div>
                                <div class="h4 mb-0">Total: R$ <span id="totalValor">0,00</span></div>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="imprimirPedido" checked>
                                    <label class="form-check-label" for="imprimirPedido">
                                        <i class="fas fa-print me-1"></i>
                                        Imprimir cupom fiscal
                                    </label>
                                </div>
                                <button class="btn btn-primary btn-lg w-100 mt-2" onclick="finalizarPedido()">
                                    <i class="fas fa-check me-2"></i>
                                    Finalizar Pedido
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Produto -->
    <div class="modal fade" id="modalProduto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-box me-2"></i>
                        <span id="produtoNome"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <img id="produtoImagem" src="" class="img-fluid rounded" alt="">
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantidade</label>
                                <input type="number" class="form-control" id="produtoQuantidade" value="1" min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tamanho</label>
                                <select class="form-select" id="produtoTamanho">
                                    <option value="normal">Normal</option>
                                    <option value="mini">Mini</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observação</label>
                                <textarea class="form-control" id="produtoObservacao" rows="3" placeholder="Ex: Sem cebola, bem assado..."></textarea>
                            </div>
                            <div class="h5 text-primary">
                                Preço: R$ <span id="produtoPreco">0,00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="adicionarAoCarrinho()">
                        <i class="fas fa-plus me-2"></i>
                        Adicionar ao Carrinho
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ingredientes -->
    <div class="modal fade" id="modalIngredientes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-hamburger me-2"></i>
                        <span id="modalIngredientesTitulo">Personalizar Lanche</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Ingredientes Disponíveis</h6>
                            <div id="ingredientesDisponiveis" class="d-flex flex-wrap gap-2">
                                <!-- Ingredientes serão carregados aqui -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Ingredientes Selecionados</h6>
                            <div id="ingredientesSelecionados" class="d-flex flex-wrap gap-2">
                                <!-- Ingredientes selecionados aparecerão aqui -->
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="observacaoItem" class="form-label">Observação do Item</label>
                                <textarea class="form-control" id="observacaoItem" rows="2" placeholder="Observações especiais para este item..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarIngredientes()">
                        <i class="fas fa-check me-2"></i>
                        Adicionar ao Carrinho
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let mesaSelecionada = null;
        let carrinho = [];
        let produtoAtual = null;

        // Mesa selection
        function selecionarMesa(element) {
            // Remove previous selection
            document.querySelectorAll('.mesa-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            element.classList.add('selected');
            
            mesaSelecionada = {
                id: element.dataset.mesaId,
                numero: element.dataset.mesaNumero
            };
            
            console.log('Mesa selecionada:', mesaSelecionada);
        }

        // Product search and filter
        document.getElementById('searchProdutos').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filterProdutos();
        });

        document.getElementById('categoriaFilter').addEventListener('change', function() {
            filterProdutos();
        });

        function filterProdutos() {
            const searchTerm = document.getElementById('searchProdutos').value.toLowerCase();
            const categoriaId = document.getElementById('categoriaFilter').value;
            
            document.querySelectorAll('.produto-item').forEach(item => {
                const nome = item.dataset.nome;
                const categoria = item.dataset.categoria;
                
                const matchSearch = nome.includes(searchTerm);
                const matchCategoria = !categoriaId || categoria === categoriaId;
                
                if (matchSearch && matchCategoria) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Add product to cart
        function adicionarProduto(produtoId) {
            if (!mesaSelecionada) {
                Swal.fire('Atenção', 'Selecione uma mesa primeiro!', 'warning');
                return;
            }
            
            // Buscar dados do produto via AJAX
            fetch('index.php?action=produtos&buscar_produto=1&produto_id=' + produtoId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const produto = {
                        id: data.produto.id,
                        nome: data.produto.nome,
                        preco: parseFloat(data.produto.preco_normal),
                        quantidade: 1,
                        tamanho: 'normal',
                        observacao: '',
                        ingredientes: []
                    };
                    
                    // Adicionar diretamente ao carrinho (sem personalização)
                    adicionarAoCarrinhoComProduto(produto);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao carregar produto', 'error');
            });
        }

        function personalizarProduto(produtoId) {
            if (!mesaSelecionada) {
                Swal.fire('Atenção', 'Selecione uma mesa primeiro!', 'warning');
                return;
            }
            
            // Buscar dados do produto via AJAX
            fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=buscar_produto&produto_id=' + produtoId
            })
            .then(response => response.json())
            .then(data => {
                console.log('Dados do produto:', data);
                if (data.success) {
                    const produto = {
                        id: data.produto.id,
                        nome: data.produto.nome,
                        preco: parseFloat(data.produto.preco_normal),
                        quantidade: 1,
                        tamanho: 'normal',
                        observacao: '',
                        ingredientes: []
                    };
                    
                    // Abrir modal de ingredientes
                    abrirModalIngredientes(produto, data.ingredientes || [], data.todos_ingredientes || []);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao carregar produto', 'error');
            });
        }

        // Variáveis para o modal de ingredientes
        let ingredientesDisponiveis = [];
        let ingredientesSelecionados = [];

        function abrirModalIngredientes(produto, ingredientes, todosIngredientes) {
            produtoAtual = produto;
            ingredientesDisponiveis = todosIngredientes || [];
            ingredientesSelecionados = ingredientes || [];
            
            // Salvar ingredientes originais para comparação
            produtoAtual.ingredientes_originais = [...ingredientes];

            // Atualizar título do modal
            document.getElementById('modalIngredientesTitulo').textContent = `Personalizar ${produto.nome}`;
            
            // Carregar ingredientes disponíveis
            carregarIngredientesDisponiveis();
            
            // Carregar ingredientes selecionados (que já compõem o lanche)
            atualizarIngredientesSelecionados();
            
            // Limpar observação
            document.getElementById('observacaoItem').value = '';
            
            // Abrir modal
            new bootstrap.Modal(document.getElementById('modalIngredientes')).show();
        }

        function carregarIngredientesDisponiveis() {
            const container = document.getElementById('ingredientesDisponiveis');
            container.innerHTML = '';
            
            // Filtrar ingredientes que não estão selecionados
            const ingredientesNaoSelecionados = ingredientesDisponiveis.filter(ingrediente => 
                !ingredientesSelecionados.some(selecionado => selecionado.id === ingrediente.id)
            );
            
            ingredientesNaoSelecionados.forEach(ingrediente => {
                const tag = document.createElement('span');
                tag.className = 'badge bg-light text-dark border cursor-pointer';
                tag.style.cursor = 'pointer';
                tag.innerHTML = `
                    ${ingrediente.nome} (R$ ${parseFloat(ingrediente.preco_adicional).toFixed(2).replace('.', ',')})
                    <i class="fas fa-plus ms-1"></i>
                `;
                tag.onclick = () => adicionarIngrediente(ingrediente.id);
                container.appendChild(tag);
            });
        }

        function adicionarIngrediente(ingredienteId) {
            const ingrediente = ingredientesDisponiveis.find(i => i.id == ingredienteId);
            if (ingrediente) {
                ingredientesSelecionados.push(ingrediente);
                atualizarIngredientesSelecionados();
            }
        }

        function removerIngrediente(ingredienteId) {
            ingredientesSelecionados = ingredientesSelecionados.filter(i => i.id != ingredienteId);
            atualizarIngredientesSelecionados();
        }

        function atualizarIngredientesSelecionados() {
            const container = document.getElementById('ingredientesSelecionados');
            container.innerHTML = '';
            
            ingredientesSelecionados.forEach(ingrediente => {
                const tag = document.createElement('span');
                tag.className = 'badge bg-primary me-2 mb-2 d-inline-flex align-items-center';
                tag.innerHTML = `
                    ${ingrediente.nome} (R$ ${parseFloat(ingrediente.preco_adicional).toFixed(2).replace('.', ',')})
                    <button type="button" class="btn-close btn-close-white ms-2" onclick="removerIngrediente(${ingrediente.id})" style="font-size: 0.7em;"></button>
                `;
                container.appendChild(tag);
            });
        }

        function confirmarIngredientes() {
            if (produtoAtual) {
                // Obter ingredientes originais do produto
                const ingredientesOriginais = produtoAtual.ingredientes_originais || [];
                
                // Calcular ingredientes adicionados (que não estavam no produto original)
                const ingredientesAdicionados = ingredientesSelecionados.filter(ing => 
                    !ingredientesOriginais.some(orig => orig.id === ing.id)
                );
                
                // Calcular ingredientes removidos (que estavam no produto original mas não estão selecionados)
                const ingredientesRemovidos = ingredientesOriginais.filter(orig => 
                    !ingredientesSelecionados.some(sel => sel.id === orig.id)
                );
                
                // Salvar apenas os ingredientes modificados
                produtoAtual.ingredientes_adicionados = ingredientesAdicionados;
                produtoAtual.ingredientes_removidos = ingredientesRemovidos;
                produtoAtual.observacao = document.getElementById('observacaoItem').value;
                
                // Calcular preço total (produto + ingredientes adicionados - ingredientes removidos)
                let precoTotal = produtoAtual.preco;
                ingredientesAdicionados.forEach(ingrediente => {
                    precoTotal += parseFloat(ingrediente.preco_adicional);
                });
                ingredientesRemovidos.forEach(ingrediente => {
                    precoTotal -= parseFloat(ingrediente.preco_adicional);
                });
                produtoAtual.preco = precoTotal;
                
                // Adicionar ao carrinho
                adicionarAoCarrinhoComProduto(produtoAtual);
                
                // Fechar modal
                bootstrap.Modal.getInstance(document.getElementById('modalIngredientes')).hide();
            }
        }

        function adicionarAoCarrinhoComProduto(produto) {
            console.log('Adicionando produto ao carrinho:', produto);
            console.log('Carrinho antes:', carrinho);
            
            // Verificar se é um produto personalizado (tem ingredientes modificados)
            const temIngredientesModificados = (produto.ingredientes_adicionados && produto.ingredientes_adicionados.length > 0) || 
                                             (produto.ingredientes_removidos && produto.ingredientes_removidos.length > 0);
            
            if (temIngredientesModificados) {
                // Produto personalizado - sempre adicionar como novo item
                carrinho.push(produto);
                console.log('Produto personalizado adicionado como novo item');
            } else {
                // Produto normal - verificar se já existe
                const existingIndex = carrinho.findIndex(item => 
                    item.id === produto.id && 
                    item.tamanho === produto.tamanho &&
                    (!item.ingredientes_adicionados || item.ingredientes_adicionados.length === 0) &&
                    (!item.ingredientes_removidos || item.ingredientes_removidos.length === 0)
                );
                
                if (existingIndex >= 0) {
                    carrinho[existingIndex].quantidade += produto.quantidade;
                    console.log('Produto normal existente, quantidade atualizada:', carrinho[existingIndex]);
                } else {
                    carrinho.push(produto);
                    console.log('Novo produto normal adicionado ao carrinho');
                }
            }
            
            console.log('Carrinho depois:', carrinho);
            atualizarCarrinho();
        }

        function atualizarCarrinho() {
            console.log('Atualizando carrinho, itens:', carrinho);
            const carrinhoItens = document.getElementById('carrinhoItens');
            const carrinhoTotal = document.getElementById('carrinhoTotal');
            const totalValor = document.getElementById('totalValor');
            
            if (carrinho.length === 0) {
                carrinhoItens.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Carrinho vazio</p>
                        <small>Selecione uma mesa e adicione produtos</small>
                    </div>
                `;
                carrinhoTotal.style.display = 'none';
                return;
            }
            
            let html = '';
            let total = 0;
            
            carrinho.forEach((item, index) => {
                const subtotal = item.preco * item.quantidade;
                total += subtotal;
                
                // Calcular preço base do produto (sem ingredientes)
                let precoBase = item.preco;
                if (item.ingredientes && item.ingredientes.length > 0) {
                    precoBase = item.preco - item.ingredientes.reduce((sum, ing) => sum + parseFloat(ing.preco_adicional), 0);
                }
                
                html += `
                    <div class="carrinho-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold">${item.nome}</div>
                                <div class="text-muted small">${item.tamanho} - R$ ${precoBase.toFixed(2).replace('.', ',')}</div>
                                ${(item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0) || (item.ingredientes_removidos && item.ingredientes_removidos.length > 0) ? `
                                    <div class="mt-1">
                                        <small class="text-muted">Modificações:</small>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            ${item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0 ? item.ingredientes_adicionados.map(ing => {
                                                const nome = typeof ing === 'string' ? ing : (ing.nome || 'Ingrediente');
                                                const preco = typeof ing === 'object' && ing.preco_adicional ? parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',') : '0,00';
                                                return `<span class="badge bg-success" style="font-size: 0.7em;">+${nome} (+R$ ${preco})</span>`;
                                            }).join('') : ''}
                                            ${item.ingredientes_removidos && item.ingredientes_removidos.length > 0 ? item.ingredientes_removidos.map(ing => {
                                                const nome = typeof ing === 'string' ? ing : (ing.nome || 'Ingrediente');
                                                const preco = typeof ing === 'object' && ing.preco_adicional ? parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',') : '0,00';
                                                return `<span class="badge bg-danger" style="font-size: 0.7em;">-${nome} (-R$ ${preco})</span>`;
                                            }).join('') : ''}
                                        </div>
                                    </div>
                                ` : ''}
                                ${item.observacao ? `<div class="text-muted small mt-1"><strong>Obs:</strong> ${item.observacao}</div>` : ''}
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removerDoCarrinho(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-outline-secondary" onclick="alterarQuantidade(${index}, -1)">-</button>
                                <input type="number" class="form-control text-center" value="${item.quantidade}" min="1" onchange="alterarQuantidade(${index}, 0, this.value)">
                                <button class="btn btn-outline-secondary" onclick="alterarQuantidade(${index}, 1)">+</button>
                            </div>
                            <div class="fw-bold">R$ ${subtotal.toFixed(2).replace('.', ',')}</div>
                        </div>
                    </div>
                `;
            });
            
            carrinhoItens.innerHTML = html;
            totalValor.textContent = total.toFixed(2).replace('.', ',');
            carrinhoTotal.style.display = 'block';
        }

        function alterarQuantidade(index, delta, newValue = null) {
            if (newValue !== null) {
                carrinho[index].quantidade = parseInt(newValue);
            } else {
                carrinho[index].quantidade += delta;
            }
            
            if (carrinho[index].quantidade <= 0) {
                carrinho.splice(index, 1);
            }
            
            atualizarCarrinho();
        }

        function removerDoCarrinho(index) {
            carrinho.splice(index, 1);
            atualizarCarrinho();
        }

        function limparCarrinho() {
            Swal.fire({
                title: 'Limpar Carrinho',
                text: 'Deseja realmente limpar todos os itens do carrinho?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, limpar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    carrinho = [];
                    atualizarCarrinho();
                }
            });
        }

        function gerarCupomFiscal(pedidoData) {
            const agora = new Date();
            const dataHora = agora.toLocaleString('pt-BR');
            
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Cupom Fiscal - Pedido #${pedidoData.id}</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; padding: 10px; }
                        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
                        .empresa { font-weight: bold; font-size: 14px; }
                        .endereco { font-size: 10px; }
                        .pedido-info { margin: 10px 0; }
                        .item { margin: 5px 0; }
                        .item-nome { font-weight: bold; font-size: 14px; }
                        .item-detalhes { font-size: 13px; margin-left: 10px; }
                        .modificacoes { margin-left: 20px; font-size: 13px; }
                        .adicionado { color: green; }
                        .removido { color: red; }
                        .total { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; font-weight: bold; }
                        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="empresa">DIVINO LANCHES</div>
                        <div class="endereco">Rua das Flores, 123 - Centro</div>
                        <div class="endereco">Tel: (11) 99999-9999</div>
                    </div>
                    
                    <div class="pedido-info">
                        <strong>PEDIDO #${pedidoData.id}</strong><br>
                        Data/Hora: ${dataHora}<br>
                        ${pedidoData.tipo === 'mesa' ? `Mesa: ${pedidoData.mesa}` : 'DELIVERY'}<br>
                        ${pedidoData.cliente ? `Cliente: ${pedidoData.cliente}` : ''}
                        ${pedidoData.telefone ? `<br>Telefone: ${pedidoData.telefone}` : ''}
                        ${pedidoData.endereco ? `<br>Endereço: ${pedidoData.endereco}` : ''}
                        ${pedidoData.atendente ? `<br>Atendente: ${pedidoData.atendente}` : ''}
                        ${pedidoData.telefone_atendente ? `<br>Tel. Atendente: ${pedidoData.telefone_atendente}` : ''}
                        ${pedidoData.estabelecimento ? `<br>Estabelecimento: ${pedidoData.estabelecimento}` : ''}
                    </div>
                    
                    <div class="itens">
                        <strong>ITENS DO PEDIDO:</strong><br>
            `;
            
            pedidoData.itens.forEach(item => {
                html += `
                    <div class="item">
                        <div class="item-nome">${item.quantidade}x ${item.nome}</div>
                        <div class="item-detalhes">${item.tamanho} - R$ ${item.preco.toFixed(2).replace('.', ',')}</div>
                `;
                
                if (item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0) {
                    html += `<div class="modificacoes">`;
                    item.ingredientes_adicionados.forEach(ing => {
                        html += `<div class="adicionado">+ ${ing.nome} (+R$ ${parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',')})</div>`;
                    });
                    html += `</div>`;
                }
                
                if (item.ingredientes_removidos && item.ingredientes_removidos.length > 0) {
                    html += `<div class="modificacoes">`;
                    item.ingredientes_removidos.forEach(ing => {
                        html += `<div class="removido">- ${ing.nome} (-R$ ${parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',')})</div>`;
                    });
                    html += `</div>`;
                }
                
                if (item.observacao) {
                    html += `<div class="item-detalhes">Obs: ${item.observacao}</div>`;
                }
                
                html += `</div>`;
            });
            
            html += `
                    </div>
                    
                    <div class="total">
                        <strong>TOTAL: R$ ${pedidoData.valor_total.toFixed(2).replace('.', ',')}</strong>
                    </div>
                    
                    ${pedidoData.observacao ? `<div class="pedido-info"><strong>Observação:</strong> ${pedidoData.observacao}</div>` : ''}
                    
                    <div class="footer">
                        Obrigado pela preferência!<br>
                        Volte sempre!
                    </div>
                </body>
                </html>
            `;
            
            return html;
        }

        function imprimirCupom(pedidoData) {
            console.log('=== FUNÇÃO IMPRIMIRCUPOM CHAMADA ===');
            console.log('Dados do pedido recebidos:', pedidoData);
            console.log('=====================================');
            
            try {
                const cupomHtml = gerarCupomFiscal(pedidoData);
                console.log('HTML do cupom gerado:', cupomHtml.substring(0, 200) + '...');
                
                const janelaImpressao = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
                console.log('Janela de impressão criada:', janelaImpressao);
                
                if (!janelaImpressao) {
                    console.error('Erro: Não foi possível abrir janela de impressão');
                    alert('Erro: Não foi possível abrir janela de impressão. Verifique se o popup está bloqueado.');
                    return;
                }
                
                janelaImpressao.document.write(cupomHtml);
                janelaImpressao.document.close();
                
                // Aguardar carregamento e imprimir automaticamente
                janelaImpressao.addEventListener('load', function() {
                    console.log('Janela de impressão carregada');
                    setTimeout(() => {
                        try {
                            janelaImpressao.focus();
                            janelaImpressao.print();
                            console.log('Comando de impressão enviado');
                            
                            // Fechar janela após um tempo
                            setTimeout(() => {
                                janelaImpressao.close();
                            }, 3000);
                        } catch (error) {
                            console.error('Erro ao imprimir:', error);
                            alert('Erro ao imprimir. Verifique se há uma impressora configurada.');
                        }
                    }, 500);
                });
                
                // Fallback caso o evento load não funcione
                setTimeout(() => {
                    if (!janelaImpressao.closed) {
                        try {
                            janelaImpressao.focus();
                            janelaImpressao.print();
                            console.log('Comando de impressão enviado (fallback)');
                            
                            setTimeout(() => {
                                janelaImpressao.close();
                            }, 3000);
                        } catch (error) {
                            console.error('Erro ao imprimir (fallback):', error);
                            janelaImpressao.close();
                        }
                    }
                }, 1500);
                
            } catch (error) {
                console.error('Erro na função imprimirCupom:', error);
                alert('Erro ao gerar cupom fiscal: ' + error.message);
            }
        }
        
        function finalizarPedido() {
            if (!mesaSelecionada) {
                Swal.fire('Atenção', 'Selecione uma mesa primeiro!', 'warning');
                return;
            }
            
            if (carrinho.length === 0) {
                Swal.fire('Atenção', 'Adicione pelo menos um produto ao carrinho!', 'warning');
                return;
            }
            
            const isEditing = <?php echo $editarPedido ? 'true' : 'false'; ?>;
            const pedidoId = <?php echo $editarPedido ? $editarPedido['idpedido'] : 'null'; ?>;
            
            // Finalizar/Atualizar pedido via AJAX diretamente
            console.log('Carrinho antes de enviar:', carrinho);
            carrinho.forEach((item, index) => {
                console.log(`Item ${index}:`, {
                    id: item.id,
                    nome: item.nome,
                    ingredientes_adicionados: item.ingredientes_adicionados,
                    ingredientes_removidos: item.ingredientes_removidos
                });
            });
            
            const formData = new URLSearchParams();
            formData.append('action', isEditing ? 'atualizar_pedido_completo' : 'criar_pedido');
            formData.append('mesa_id', mesaSelecionada.id);
            formData.append('itens', JSON.stringify(carrinho));
            formData.append('observacao', document.getElementById('observacaoPedido').value || '');
            
            if (isEditing) {
                formData.append('pedido_id', pedidoId);
            }
            
            fetch('index.php?action=pedidos', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    
                    // Verificar se deve imprimir
                    const deveImprimir = document.getElementById('imprimirPedido').checked;
                    console.log('=== DEBUG IMPRESSÃO ===');
                    console.log('Deve imprimir:', deveImprimir);
                    console.log('Checkbox elemento:', document.getElementById('imprimirPedido'));
                    console.log('Dados do pedido:', data.pedido);
                    console.log('========================');
                    
                    if (deveImprimir && data.pedido) {
                        console.log('=== INICIANDO IMPRESSÃO ===');
                        
                        // Preparar dados básicos para impressão (versão simplificada)
                        const pedidoData = {
                            id: data.pedido.idpedido || data.pedido.id,
                            tipo: mesaSelecionada.tipo || 'mesa',
                            mesa: mesaSelecionada.numero || mesaSelecionada.nome,
                            cliente: mesaSelecionada.cliente || 'Cliente Mesa',
                            telefone: mesaSelecionada.telefone || '',
                            endereco: mesaSelecionada.endereco || '',
                            itens: carrinho,
                            valor_total: carrinho.reduce((total, item) => total + (item.preco * item.quantidade), 0),
                            observacao: document.getElementById('observacaoPedido').value || '',
                            atendente: 'Usuário',
                            telefone_atendente: '',
                            estabelecimento: 'Divino Lanches'
                        };
                        
                        console.log('Dados preparados para impressão (simplificado):', pedidoData);
                        
                        // Imprimir imediatamente
                        console.log('=== CHAMANDO IMPRIMIRCUPOM (VERSÃO SIMPLIFICADA) ===');
                        setTimeout(() => {
                            console.log('Executando imprimirCupom após timeout (versão simplificada)');
                            imprimirCupom(pedidoData);
                        }, 1000);
                        
                    } else {
                        console.log('Não imprimindo - deveImprimir:', deveImprimir, 'data.pedido:', data.pedido);
                    }
                    
                    if (!isEditing) {
                        // Reset form apenas para novos pedidos
                        carrinho = [];
                        mesaSelecionada = null;
                        document.querySelectorAll('.mesa-card').forEach(card => {
                            card.classList.remove('selected');
                        });
                        atualizarCarrinho();
                    }
                    
                    // Redirecionar para pedidos após 3 segundos (para dar tempo da impressão)
                    setTimeout(() => {
                        window.location.href = '<?php echo $router->url('pedidos'); ?>';
                    }, 3000);
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Erro', 'Erro ao criar pedido', 'error');
            });
        }

        // Initialize with selected mesa if provided
        <?php if ($mesaSelecionada): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const mesaElement = document.querySelector(`[data-mesa-numero="<?php echo $mesaSelecionada; ?>"]`);
            if (mesaElement) {
                selecionarMesa(mesaElement);
            } else {
                console.log('Mesa não encontrada:', '<?php echo $mesaSelecionada; ?>');
            }
        });
        <?php endif; ?>
        
        <?php if ($editarPedido): ?>
        // Carregar dados do pedido para edição
        document.addEventListener('DOMContentLoaded', function() {
            // Selecionar mesa do pedido
        const mesaId = '<?php echo $editarPedido['id_mesa']; ?>';
        let mesaElement = null;
        
        if (mesaId === '999') {
            // Delivery
            mesaElement = document.querySelector('[data-mesa-id="delivery"]');
        } else {
            // Mesa normal
            mesaElement = document.querySelector(`[data-mesa-id="${mesaId}"]`);
        }
            
            if (mesaElement) {
                selecionarMesa(mesaElement);
            }
            
            // Carregar itens do pedido no carrinho
            const itensPedido = <?php echo json_encode($editarPedido['itens']); ?>;
            console.log('Itens do pedido:', itensPedido);
            itensPedido.forEach(item => {
                console.log('Item completo:', item);
                console.log('Produto ID:', item.produto_id);
                console.log('Produto Nome:', item.produto_nome);
                console.log('Valor Unitário:', item.valor_unitario);
                console.log('Quantidade:', item.quantidade);
                
                // Processar ingredientes corretamente
                let ingredientesAdicionados = [];
                let ingredientesRemovidos = [];
                
                if (item.ingredientes_com && Array.isArray(item.ingredientes_com)) {
                    ingredientesAdicionados = item.ingredientes_com.filter(ing => ing && ing.trim() !== '');
                } else if (item.ingredientes_com && typeof item.ingredientes_com === 'string' && item.ingredientes_com.trim() !== '') {
                    // Se for string, dividir por vírgula e limpar
                    ingredientesAdicionados = item.ingredientes_com.split(',').map(ing => ing.trim()).filter(ing => ing && ing !== 'Array');
                }
                
                if (item.ingredientes_sem && Array.isArray(item.ingredientes_sem)) {
                    ingredientesRemovidos = item.ingredientes_sem.filter(ing => ing && ing.trim() !== '');
                } else if (item.ingredientes_sem && typeof item.ingredientes_sem === 'string' && item.ingredientes_sem.trim() !== '') {
                    // Se for string, dividir por vírgula e limpar
                    ingredientesRemovidos = item.ingredientes_sem.split(',').map(ing => ing.trim()).filter(ing => ing && ing !== 'Array');
                }
                
                console.log('Ingredientes processados:', {
                    com: ingredientesAdicionados,
                    sem: ingredientesRemovidos
                });
                
                const produto = {
                    id: item.produto_id,
                    nome: item.produto_nome || 'Produto ' + item.produto_id,
                    preco: parseFloat(item.valor_unitario) || 0,
                    quantidade: parseInt(item.quantidade) || 1,
                    tamanho: item.tamanho || 'normal',
                    observacao: item.observacao || '',
                    ingredientes_adicionados: ingredientesAdicionados,
                    ingredientes_removidos: ingredientesRemovidos
                };
                
                console.log('Produto processado:', produto);
                adicionarAoCarrinhoComProduto(produto);
            });
            
            // Carregar observação do pedido
            if (document.getElementById('observacaoPedido')) {
                document.getElementById('observacaoPedido').value = '<?php echo addslashes($editarPedido['observacao'] ?? ''); ?>';
            }
            
            // Verificar se pedido está "Pronto" e aplicar restrições
            const statusPedido = '<?php echo $editarPedido['status']; ?>';
            if (statusPedido === 'Pronto') {
                aplicarRestricoesPedidoPronto();
            }
        });
        <?php endif; ?>
        
        function aplicarRestricoesPedidoPronto() {
            // Ocultar produtos de lanches quando pedido está "Pronto"
            const produtosCards = document.querySelectorAll('.produto-card');
            produtosCards.forEach(card => {
                const categoria = card.querySelector('.produto-categoria').textContent.toLowerCase();
                if (categoria.includes('lanche') || categoria.includes('cachorro') || categoria.includes('hambúrguer') || categoria.includes('xis')) {
                    card.style.display = 'none';
                }
            });
            
            // Mostrar aviso
            const aviso = document.createElement('div');
            aviso.className = 'alert alert-warning mt-3';
            aviso.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><strong>Atenção:</strong> Este pedido está "Pronto". Apenas bebidas e itens adicionais podem ser adicionados. Para lanches, crie um novo pedido para a mesma mesa.';
            document.querySelector('.produtos-section').appendChild(aviso);
        }
    </script>
    
    <!-- Sidebar JavaScript -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- Mobile Interface JavaScript -->
    <script>
        // Passar dados PHP para JavaScript (mesmo método da página desktop)
        window.produtosData = <?php echo json_encode($produtos); ?>;
        window.mesasData = <?php echo json_encode($mesas); ?>;
        window.tenantData = <?php echo json_encode($tenant); ?>;
        window.filialData = <?php echo json_encode($filial); ?>;
    </script>
    <script src="assets/js/mobile-pedido.js"></script>
    
    <!-- Mobile Menu -->
    <?php include __DIR__ . '/components/mobile_menu.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
