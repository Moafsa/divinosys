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

// Get ingredientes
$ingredientes = [];
if ($tenant && $filial) {
    $ingredientes = $db->fetchAll("
        SELECT i.*, 
               COUNT(pi.produto_id) as total_produtos
        FROM ingredientes i
        LEFT JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id
        WHERE i.tenant_id = ? AND i.filial_id = ?
        GROUP BY i.id
        ORDER BY i.nome ASC
    ", [$tenant['id'], $filial['id']]);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Ingredientes - <?php echo $config->get('APP_NAME'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .ingredient-card {
            transition: transform 0.2s;
            border: 1px solid #e9ecef;
        }
        .ingredient-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .price-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php?view=dashboard">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php?view=gerenciar_produtos">
                                <i class="fas fa-box"></i> Produtos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php?view=gerenciar_categorias">
                                <i class="fas fa-tags"></i> Categorias
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="index.php?view=gerenciar_ingredientes">
                                <i class="fas fa-leaf"></i> Ingredientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php?view=gerar_pedido">
                                <i class="fas fa-plus-circle"></i> Novo Pedido
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php?view=pedidos">
                                <i class="fas fa-list"></i> Pedidos
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-leaf text-success"></i> Gerenciar Ingredientes
                    </h1>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ingredienteModal">
                        <i class="fas fa-plus"></i> Novo Ingrediente
                    </button>
                </div>

                <!-- Ingredients Grid -->
                <div class="row">
                    <?php foreach ($ingredientes as $ingrediente): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card ingredient-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($ingrediente['nome']); ?></h5>
                                        <span class="price-badge badge">
                                            R$ <?php echo number_format($ingrediente['preco_adicional'], 2, ',', '.'); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($ingrediente['descricao']): ?>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars($ingrediente['descricao']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-info">
                                            <i class="fas fa-box"></i> <?php echo $ingrediente['total_produtos']; ?> produtos
                                        </span>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editarIngrediente(<?php echo $ingrediente['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="excluirIngrediente(<?php echo $ingrediente['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($ingredientes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Nenhum ingrediente encontrado</h4>
                        <p class="text-muted">Comece criando seus primeiros ingredientes para os produtos.</p>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ingredienteModal">
                            <i class="fas fa-plus"></i> Criar Primeiro Ingrediente
                        </button>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Ingredient Modal -->
    <div class="modal fade" id="ingredienteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-leaf"></i> <span id="modalTitle">Novo Ingrediente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="ingredienteForm">
                    <div class="modal-body">
                        <input type="hidden" id="ingredienteId" name="id">
                        
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Ingrediente *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="preco_adicional" class="form-label">Preço Adicional (R$)</label>
                            <input type="number" class="form-control" id="preco_adicional" name="preco_adicional" 
                                   step="0.01" min="0" value="0.00">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                            <label class="form-check-label" for="ativo">
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
    <script>
        // Form submission
        document.getElementById('ingredienteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const isEdit = document.getElementById('ingredienteId').value;
            
            try {
                const response = await fetch('index.php?action=ingredientes', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + result.message);
                }
            } catch (error) {
                alert('Erro ao salvar ingrediente: ' + error.message);
            }
        });

        // Edit ingredient
        function editarIngrediente(id) {
            fetch(`index.php?action=ingredientes&buscar=1&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ing = data.ingrediente;
                        document.getElementById('ingredienteId').value = ing.id;
                        document.getElementById('nome').value = ing.nome;
                        document.getElementById('descricao').value = ing.descricao || '';
                        document.getElementById('preco_adicional').value = ing.preco_adicional || '0.00';
                        document.getElementById('ativo').checked = ing.ativo;
                        
                        document.getElementById('modalTitle').textContent = 'Editar Ingrediente';
                        new bootstrap.Modal(document.getElementById('ingredienteModal')).show();
                    }
                });
        }

        // Delete ingredient
        function excluirIngrediente(id) {
            if (confirm('Tem certeza que deseja excluir este ingrediente?')) {
                fetch('index.php?action=ingredientes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `excluir=1&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                });
            }
        }

        // Reset form when modal is hidden
        document.getElementById('ingredienteModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('ingredienteForm').reset();
            document.getElementById('ingredienteId').value = '';
            document.getElementById('modalTitle').textContent = 'Novo Ingrediente';
        });
    </script>
</body>
</html>
