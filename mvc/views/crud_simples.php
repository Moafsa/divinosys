<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Simples - Produtos, Categorias e Ingredientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h4>CRUD Simples</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#" onclick="showSection('produtos')">
                            <i class="fas fa-box"></i> Produtos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#" onclick="showSection('categorias')">
                            <i class="fas fa-tags"></i> Categorias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#" onclick="showSection('ingredientes')">
                            <i class="fas fa-leaf"></i> Ingredientes
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Produtos Section -->
                <div id="produtos-section" class="section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Produtos</h2>
                        <button class="btn btn-primary" onclick="abrirModalProduto()">
                            <i class="fas fa-plus"></i> Novo Produto
                        </button>
                    </div>
                    <div id="produtos-list" class="row">
                        <!-- Produtos serão carregados aqui -->
                    </div>
                </div>
                
                <!-- Categorias Section -->
                <div id="categorias-section" class="section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Categorias</h2>
                        <button class="btn btn-primary" onclick="abrirModalCategoria()">
                            <i class="fas fa-plus"></i> Nova Categoria
                        </button>
                    </div>
                    <div id="categorias-list" class="row">
                        <!-- Categorias serão carregadas aqui -->
                    </div>
                </div>
                
                <!-- Ingredientes Section -->
                <div id="ingredientes-section" class="section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Ingredientes</h2>
                        <button class="btn btn-primary" onclick="abrirModalIngrediente()">
                            <i class="fas fa-plus"></i> Novo Ingrediente
                        </button>
                    </div>
                    <div id="ingredientes-list" class="row">
                        <!-- Ingredientes serão carregados aqui -->
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
                    <h5 class="modal-title" id="modalProdutoTitulo">Novo Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProduto">
                        <input type="hidden" id="produtoId" name="id">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" id="produtoNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="produtoDescricao" name="descricao"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço Normal</label>
                            <input type="number" step="0.01" class="form-control" id="produtoPrecoNormal" name="preco_normal" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço Mini</label>
                            <input type="number" step="0.01" class="form-control" id="produtoPrecoMini" name="preco_mini">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-control" id="produtoCategoria" name="categoria_id">
                                <option value="">Selecione uma categoria</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="produtoAtivo" name="ativo" checked>
                                <label class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarProduto()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Categoria -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCategoriaTitulo">Nova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCategoria">
                        <input type="hidden" id="categoriaId" name="id">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" id="categoriaNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="categoriaDescricao" name="descricao"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="categoriaAtivo" name="ativo" checked>
                                <label class="form-check-label">Ativa</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarCategoria()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ingrediente -->
    <div class="modal fade" id="modalIngrediente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalIngredienteTitulo">Novo Ingrediente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formIngrediente">
                        <input type="hidden" id="ingredienteId" name="id">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" id="ingredienteNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="ingredienteDescricao" name="descricao"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço Adicional</label>
                            <input type="number" step="0.01" class="form-control" id="ingredientePrecoAdicional" name="preco_adicional" value="0">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ingredienteAtivo" name="ativo" checked>
                                <label class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarIngrediente()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funções de navegação
        function showSection(section) {
            document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
            document.getElementById(section + '-section').style.display = 'block';
            loadData(section);
        }

        // Carregar dados
        function loadData(type) {
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=listar_${type}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderList(type, data.data);
                } else {
                    console.error('Erro ao carregar dados:', data.message);
                }
            })
            .catch(error => console.error('Erro:', error));
        }

        // Renderizar lista
        function renderList(type, items) {
            const container = document.getElementById(type + '-list');
            container.innerHTML = '';
            
            items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'col-md-4 mb-3';
                card.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">${item.nome}</h5>
                            <p class="card-text">${item.descricao || ''}</p>
                            ${type === 'produtos' ? `<p class="card-text"><strong>Preço:</strong> R$ ${parseFloat(item.preco_normal).toFixed(2)}</p>` : ''}
                            ${type === 'ingredientes' ? `<p class="card-text"><strong>Preço Adicional:</strong> R$ ${parseFloat(item.preco_adicional || 0).toFixed(2)}</p>` : ''}
                            <span class="badge ${item.ativo ? 'bg-success' : 'bg-danger'}">${item.ativo ? 'Ativo' : 'Inativo'}</span>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary" onclick="editar${type.charAt(0).toUpperCase() + type.slice(1)}(${item.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="excluir${type.charAt(0).toUpperCase() + type.slice(1)}(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // Funções de produtos
        function abrirModalProduto() {
            document.getElementById('modalProdutoTitulo').textContent = 'Novo Produto';
            document.getElementById('formProduto').reset();
            document.getElementById('produtoId').value = '';
            loadCategorias();
            new bootstrap.Modal(document.getElementById('modalProduto')).show();
        }

        function editarProduto(id) {
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=buscar_produto&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalProdutoTitulo').textContent = 'Editar Produto';
                    document.getElementById('produtoId').value = data.data.id;
                    document.getElementById('produtoNome').value = data.data.nome;
                    document.getElementById('produtoDescricao').value = data.data.descricao || '';
                    document.getElementById('produtoPrecoNormal').value = data.data.preco_normal;
                    document.getElementById('produtoPrecoMini').value = data.data.preco_mini || '';
                    document.getElementById('produtoCategoria').value = data.data.categoria_id || '';
                    document.getElementById('produtoAtivo').checked = data.data.ativo;
                    loadCategorias();
                    new bootstrap.Modal(document.getElementById('modalProduto')).show();
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function salvarProduto() {
            const form = document.getElementById('formProduto');
            const formData = new FormData(form);
            formData.append('action', 'salvar_produto');
            
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalProduto')).hide();
                    loadData('produtos');
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function excluirProduto(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/crud.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=excluir_produto&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            loadData('produtos');
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Funções de categorias
        function abrirModalCategoria() {
            document.getElementById('modalCategoriaTitulo').textContent = 'Nova Categoria';
            document.getElementById('formCategoria').reset();
            document.getElementById('categoriaId').value = '';
            new bootstrap.Modal(document.getElementById('modalCategoria')).show();
        }

        function editarCategoria(id) {
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=buscar_categoria&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalCategoriaTitulo').textContent = 'Editar Categoria';
                    document.getElementById('categoriaId').value = data.data.id;
                    document.getElementById('categoriaNome').value = data.data.nome;
                    document.getElementById('categoriaDescricao').value = data.data.descricao || '';
                    document.getElementById('categoriaAtivo').checked = data.data.ativo;
                    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function salvarCategoria() {
            const form = document.getElementById('formCategoria');
            const formData = new FormData(form);
            formData.append('action', 'salvar_categoria');
            
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                    loadData('categorias');
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function excluirCategoria(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/crud.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=excluir_categoria&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            loadData('categorias');
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Funções de ingredientes
        function abrirModalIngrediente() {
            document.getElementById('modalIngredienteTitulo').textContent = 'Novo Ingrediente';
            document.getElementById('formIngrediente').reset();
            document.getElementById('ingredienteId').value = '';
            new bootstrap.Modal(document.getElementById('modalIngrediente')).show();
        }

        function editarIngrediente(id) {
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=buscar_ingrediente&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalIngredienteTitulo').textContent = 'Editar Ingrediente';
                    document.getElementById('ingredienteId').value = data.data.id;
                    document.getElementById('ingredienteNome').value = data.data.nome;
                    document.getElementById('ingredienteDescricao').value = data.data.descricao || '';
                    document.getElementById('ingredientePrecoAdicional').value = data.data.preco_adicional || 0;
                    document.getElementById('ingredienteAtivo').checked = data.data.ativo;
                    new bootstrap.Modal(document.getElementById('modalIngrediente')).show();
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function salvarIngrediente() {
            const form = document.getElementById('formIngrediente');
            const formData = new FormData(form);
            formData.append('action', 'salvar_ingrediente');
            
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalIngrediente')).hide();
                    loadData('ingredientes');
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }

        function excluirIngrediente(id) {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Esta ação não pode ser desfeita!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('mvc/ajax/crud.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=excluir_ingrediente&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            loadData('ingredientes');
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Carregar categorias para o select
        function loadCategorias() {
            fetch('mvc/ajax/crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=listar_categorias'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('produtoCategoria');
                    select.innerHTML = '<option value="">Selecione uma categoria</option>';
                    data.data.forEach(categoria => {
                        const option = document.createElement('option');
                        option.value = categoria.id;
                        option.textContent = categoria.nome;
                        select.appendChild(option);
                    });
                }
            });
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            loadData('produtos');
        });
    </script>
</body>
</html>
