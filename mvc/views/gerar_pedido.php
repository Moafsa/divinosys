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

// Get produtos data
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
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo $tenant['nome'] ?? 'Divino Lanches'; ?>
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $router->url('dashboard'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a class="nav-link active" href="<?php echo $router->url('gerar_pedido'); ?>">
                            <i class="fas fa-plus-circle"></i>
                            Novo Pedido
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('pedidos'); ?>">
                            <i class="fas fa-list"></i>
                            Pedidos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('mesas'); ?>">
                            <i class="fas fa-table"></i>
                            Mesas
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('delivery'); ?>">
                            <i class="fas fa-motorcycle"></i>
                            Delivery
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('gerenciar_produtos'); ?>">
                            <i class="fas fa-box"></i>
                            Produtos
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('estoque'); ?>">
                            <i class="fas fa-warehouse"></i>
                            Estoque
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('financeiro'); ?>">
                            <i class="fas fa-chart-line"></i>
                            Financeiro
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('relatorios'); ?>">
                            <i class="fas fa-chart-bar"></i>
                            Relatórios
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('clientes'); ?>">
                            <i class="fas fa-users"></i>
                            Clientes
                        </a>
                        <a class="nav-link" href="<?php echo $router->url('configuracoes'); ?>">
                            <i class="fas fa-cog"></i>
                            Configurações
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="<?php echo $router->url('logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
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
                                                <div class="text-muted small"><?php echo htmlspecialchars($produto['categoria_nome']); ?></div>
                                            <?php endif; ?>
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
                                <button class="btn btn-primary btn-lg w-100 mt-3" onclick="finalizarPedido()">
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
                        observacao: ''
                    };
                    
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

        function adicionarAoCarrinhoComProduto(produto) {
            console.log('Adicionando produto ao carrinho:', produto);
            console.log('Carrinho antes:', carrinho);
            
            // Check if product already exists in cart
            const existingIndex = carrinho.findIndex(item => 
                item.id === produto.id && item.tamanho === produto.tamanho
            );
            
            if (existingIndex >= 0) {
                carrinho[existingIndex].quantidade += produto.quantidade;
                console.log('Produto existente, quantidade atualizada:', carrinho[existingIndex]);
            } else {
                carrinho.push(produto);
                console.log('Novo produto adicionado ao carrinho');
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
                
                html += `
                    <div class="carrinho-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold">${item.nome}</div>
                                <div class="text-muted small">${item.tamanho} - R$ ${item.preco.toFixed(2).replace('.', ',')}</div>
                                ${item.observacao ? `<div class="text-muted small">${item.observacao}</div>` : ''}
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
            
            const title = isEditing ? 'Atualizar Pedido' : 'Finalizar Pedido';
            const text = isEditing ? 
                `Confirma as alterações no pedido #${pedidoId}?` : 
                `Confirmar pedido para ${mesaSelecionada.numero}?`;
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: isEditing ? 'Sim, atualizar' : 'Sim, finalizar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Finalizar/Atualizar pedido via AJAX
                    const formData = new URLSearchParams();
                    formData.append('action', isEditing ? 'atualizar_pedido' : 'criar_pedido');
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
                            
                            if (!isEditing) {
                                // Reset form apenas para novos pedidos
                                carrinho = [];
                                mesaSelecionada = null;
                                document.querySelectorAll('.mesa-card').forEach(card => {
                                    card.classList.remove('selected');
                                });
                                atualizarCarrinho();
                            }
                            
                            // Redirecionar para pedidos após 2 segundos
                            setTimeout(() => {
                                window.location.href = '<?php echo $router->url('pedidos'); ?>';
                            }, 2000);
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Erro', 'Erro ao criar pedido', 'error');
                    });
                }
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
                
                const produto = {
                    id: item.produto_id,
                    nome: item.produto_nome || 'Produto ' + item.produto_id,
                    preco: parseFloat(item.valor_unitario) || 0,
                    quantidade: parseInt(item.quantidade) || 1,
                    observacao: item.observacao || ''
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
</body>
</html>
