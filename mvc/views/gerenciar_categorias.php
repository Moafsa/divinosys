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

// Get categories with hierarchy
$categorias = [];
if ($tenant && $filial) {
    $categorias = $db->fetchAll("
        SELECT c.*, 
               parent.nome as parent_nome,
               COUNT(p.id) as total_produtos
        FROM categorias c
        LEFT JOIN categorias parent ON c.parent_id = parent.id
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.tenant_id = c.tenant_id AND p.filial_id = c.filial_id
        WHERE c.tenant_id = ? AND c.filial_id = ?
        GROUP BY c.id, parent.nome
        ORDER BY c.parent_id IS NULL DESC, c.ordem ASC, c.nome ASC
    ", [$tenant['id'], $filial['id']]);
}

// Get parent categories for dropdown
$categoriasPai = [];
if ($tenant && $filial) {
    $categoriasPai = $db->fetchAll("
        SELECT * FROM categorias 
        WHERE tenant_id = ? AND filial_id = ? AND parent_id IS NULL AND ativo = true
        ORDER BY ordem ASC, nome ASC
    ", [$tenant['id'], $filial['id']]);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - <?php echo $config->get('APP_NAME'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-card {
            transition: transform 0.2s;
            border: 1px solid #e9ecef;
        }
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .category-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .subcategory {
            margin-left: 30px;
            border-left: 3px solid #007bff;
            padding-left: 15px;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s;
        }
        .upload-area:hover {
            border-color: #007bff;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
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
                            <a class="nav-link text-white active" href="index.php?view=gerenciar_categorias">
                                <i class="fas fa-tags"></i> Categorias
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
                        <i class="fas fa-tags text-primary"></i> Gerenciar Categorias
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                        <i class="fas fa-plus"></i> Nova Categoria
                    </button>
                </div>

                <!-- Categories Grid -->
                <div class="row">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="col-md-6 col-lg-4 mb-4 <?php echo $categoria['parent_id'] ? 'subcategory' : ''; ?>">
                            <div class="card category-card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <?php if ($categoria['imagem']): ?>
                                            <img src="<?php echo htmlspecialchars($categoria['imagem']); ?>" 
                                                 class="category-image me-3" alt="<?php echo htmlspecialchars($categoria['nome']); ?>">
                                        <?php else: ?>
                                            <div class="category-image me-3 bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-tag text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($categoria['nome']); ?></h5>
                                            <?php if ($categoria['parent_nome']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($categoria['parent_nome']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($categoria['descricao']): ?>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-info">
                                            <i class="fas fa-box"></i> <?php echo $categoria['total_produtos']; ?> produtos
                                        </span>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editarCategoria(<?php echo $categoria['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="excluirCategoria(<?php echo $categoria['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($categorias)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Nenhuma categoria encontrada</h4>
                        <p class="text-muted">Comece criando sua primeira categoria de produtos.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                            <i class="fas fa-plus"></i> Criar Primeira Categoria
                        </button>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoriaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tag"></i> <span id="modalTitle">Nova Categoria</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="categoriaForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="categoriaId" name="id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="nome" class="form-label">Nome da Categoria *</label>
                                    <input type="text" class="form-control" id="nome" name="nome" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="descricao" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="parent_id" class="form-label">Categoria Pai</label>
                                            <select class="form-select" id="parent_id" name="parent_id">
                                                <option value="">Categoria Principal</option>
                                                <?php foreach ($categoriasPai as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ordem" class="form-label">Ordem</label>
                                            <input type="number" class="form-control" id="ordem" name="ordem" value="0">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                                    <label class="form-check-label" for="ativo">
                                        Categoria Ativa
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Imagem da Categoria</label>
                                    <div class="upload-area" id="uploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-2">Clique ou arraste uma imagem</p>
                                        <input type="file" id="imagem" name="imagem" accept="image/*" class="d-none">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('imagem').click()">
                                            Selecionar Imagem
                                        </button>
                                    </div>
                                    <div id="imagePreview" class="mt-2"></div>
                                </div>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Upload area functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('imagem');
        const imagePreview = document.getElementById('imagePreview');

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                previewImage(files[0]);
            }
        });

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                previewImage(e.target.files[0]);
            }
        });

        function previewImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.innerHTML = `
                    <img src="${e.target.result}" class="image-preview" alt="Preview">
                    <button type="button" class="btn btn-sm btn-danger mt-1" onclick="removeImage()">
                        <i class="fas fa-trash"></i> Remover
                    </button>
                `;
            };
            reader.readAsDataURL(file);
        }

        function removeImage() {
            fileInput.value = '';
            imagePreview.innerHTML = '';
        }

        // Form submission
        document.getElementById('categoriaForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const isEdit = document.getElementById('categoriaId').value;
            
            try {
                const response = await fetch('index.php?action=categorias', {
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
                alert('Erro ao salvar categoria: ' + error.message);
            }
        });

        // Edit category
        function editarCategoria(id) {
            // Fetch category data and populate form
            fetch(`index.php?action=categorias&buscar=1&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cat = data.categoria;
                        document.getElementById('categoriaId').value = cat.id;
                        document.getElementById('nome').value = cat.nome;
                        document.getElementById('descricao').value = cat.descricao || '';
                        document.getElementById('parent_id').value = cat.parent_id || '';
                        document.getElementById('ordem').value = cat.ordem || 0;
                        document.getElementById('ativo').checked = cat.ativo;
                        
                        if (cat.imagem) {
                            imagePreview.innerHTML = `
                                <img src="${cat.imagem}" class="image-preview" alt="Preview">
                                <button type="button" class="btn btn-sm btn-danger mt-1" onclick="removeImage()">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            `;
                        }
                        
                        document.getElementById('modalTitle').textContent = 'Editar Categoria';
                        new bootstrap.Modal(document.getElementById('categoriaModal')).show();
                    }
                });
        }

        // Delete category
        function excluirCategoria(id) {
            if (confirm('Tem certeza que deseja excluir esta categoria?')) {
                fetch('index.php?action=categorias', {
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
        document.getElementById('categoriaModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('categoriaForm').reset();
            document.getElementById('categoriaId').value = '';
            document.getElementById('modalTitle').textContent = 'Nova Categoria';
            imagePreview.innerHTML = '';
        });
    </script>
</body>
</html>
