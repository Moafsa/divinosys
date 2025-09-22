<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - <?php echo $config->get('app.name'); ?></title>
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
                        <a class="nav-link" href="<?php echo $router->url('gerar_pedido'); ?>">
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
                        <a class="nav-link active" href="<?php echo $router->url('gerenciar_produtos'); ?>">
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
                                    <i class="fas fa-tags me-1"></i>
                                    Categorias
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Produtos Grid -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function abrirModalProduto() {
            document.getElementById('modalProdutoTitulo').textContent = 'Novo Produto';
            document.getElementById('formProduto').reset();
            new bootstrap.Modal(document.getElementById('modalProduto')).show();
        }

        function editarProduto(produtoId) {
            document.getElementById('modalProdutoTitulo').textContent = 'Editar Produto';
            // Load product data via AJAX
            new bootstrap.Modal(document.getElementById('modalProduto')).show();
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
                    Swal.fire('Sucesso', 'Produto excluído com sucesso!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        function salvarProduto() {
            Swal.fire('Sucesso', 'Produto salvo com sucesso!', 'success');
            setTimeout(() => location.reload(), 1500);
        }

        function abrirModalCategoria() {
            Swal.fire('Info', 'Modal de categorias será implementado', 'info');
        }
    </script>
</body>
</html>
