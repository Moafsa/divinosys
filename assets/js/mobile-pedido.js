// Mobile Interface para Garçons - Novo Pedido
class MobilePedidoInterface {
    constructor() {
        this.currentTab = 'mesas';
        this.mesaSelecionada = null;
        this.carrinho = [];
        this.produtos = [];
        this.mesas = [];
        
        this.init();
    }
    
    init() {
        console.log('🚀 Inicializando MobilePedidoInterface...');
        console.log('📱 Largura da tela:', window.innerWidth);
        
        if (window.innerWidth <= 768) {
            console.log('📱 Modo mobile detectado, criando interface...');
            this.createMobileInterface();
            
            // Aguardar um pouco para garantir que o DOM foi atualizado
            setTimeout(() => {
                this.loadData();
                this.bindEvents();
            }, 100);
        } else {
            console.log('🖥️ Modo desktop detectado, interface mobile não será criada');
        }
    }
    
    createMobileInterface() {
        // Criar container mobile
        const mobileContainer = document.createElement('div');
        mobileContainer.className = 'mobile-pedido-container';
        mobileContainer.innerHTML = `
            <!-- Header -->
            <div class="mobile-header">
                <h4>Novo Pedido</h4>
                <div class="mesa-info" id="mesa-info">Selecione uma mesa</div>
            </div>
            
            <!-- Abas -->
            <div class="mobile-tabs">
                <button class="mobile-tab active" data-tab="mesas">
                    <i class="fas fa-table"></i>
                    Mesas
                </button>
                <button class="mobile-tab" data-tab="produtos">
                    <i class="fas fa-utensils"></i>
                    Produtos
                </button>
                <button class="mobile-tab" data-tab="cliente">
                    <i class="fas fa-user"></i>
                    Cliente
                </button>
                <button class="mobile-tab" data-tab="carrinho">
                    <i class="fas fa-shopping-cart"></i>
                    Carrinho
                </button>
            </div>
            
            <!-- Conteúdo das abas -->
            <div class="mobile-tab-content active" id="tab-mesas">
                <div class="mobile-mesas-grid" id="mobile-mesas-grid">
                    <!-- Mesas serão carregadas aqui -->
                </div>
            </div>
            
            <div class="mobile-tab-content" id="tab-produtos">
                <!-- Search and Filter -->
                <div style="padding: 15px; background: white; border-bottom: 1px solid #eee;">
                    <div style="margin-bottom: 10px;">
                        <input type="text" 
                               id="mobile-search-produtos" 
                               placeholder="Buscar produtos..." 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <select id="mobile-categoria-filter" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <option value="">Todas as categorias</option>
                        </select>
                    </div>
                </div>
                <div class="mobile-produtos-grid" id="mobile-produtos-grid">
                    <!-- Produtos serão carregados aqui -->
                </div>
            </div>
            
            <div class="mobile-tab-content" id="tab-cliente">
                <div style="padding: 15px;">
                    <h5 style="margin-bottom: 20px;">
                        <i class="fas fa-user me-2"></i>
                        Informações do Cliente
                    </h5>
                    
                    <!-- Nome do Cliente -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Nome do Cliente</label>
                        <input type="text" 
                               id="mobile-cliente-nome" 
                               placeholder="Nome completo"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <!-- Telefone com busca -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Telefone</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" 
                                   id="mobile-cliente-telefone" 
                                   placeholder="(11) 99999-9999"
                                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                            <button onclick="mobilePedido.buscarClientePorTelefone()" 
                                    style="padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Digite o telefone e clique em buscar para carregar dados do cliente
                        </small>
                    </div>
                    
                    <!-- Email -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                        <input type="email" 
                               id="mobile-cliente-email" 
                               placeholder="email@exemplo.com"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <!-- CPF -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">CPF</label>
                        <input type="text" 
                               id="mobile-cliente-cpf" 
                               placeholder="000.000.000-00"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <!-- Mensagem de cliente encontrado -->
                    <div id="mobile-cliente-encontrado" style="display: none; padding: 10px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 15px; font-size: 14px;">
                        <i class="fas fa-check-circle me-1"></i>
                        <span id="mobile-cliente-encontrado-texto">Cliente encontrado e carregado!</span>
                    </div>
                    
                    <!-- Campos de Endereço (apenas para delivery) -->
                    <div id="mobile-endereco-section" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h6 style="margin-bottom: 15px; color: #007bff;">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            Endereço de Entrega
                        </h6>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Endereço *</label>
                            <input type="text" 
                                   id="mobile-endereco-rua" 
                                   placeholder="Rua, número"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Bairro *</label>
                            <input type="text" 
                                   id="mobile-endereco-bairro" 
                                   placeholder="Bairro"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Cidade *</label>
                                <input type="text" 
                                       id="mobile-endereco-cidade" 
                                       placeholder="Cidade"
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Estado *</label>
                                <input type="text" 
                                       id="mobile-endereco-estado" 
                                       placeholder="UF"
                                       maxlength="2"
                                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">CEP</label>
                            <input type="text" 
                                   id="mobile-endereco-cep" 
                                   placeholder="00000-000"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Referência</label>
                            <input type="text" 
                                   id="mobile-endereco-referencia" 
                                   placeholder="Ponto de referência (opcional)"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mobile-tab-content" id="tab-carrinho">
                <div id="mobile-carrinho-content">
                    <!-- Carrinho será exibido aqui -->
                </div>
            </div>
            
            <!-- Carrinho flutuante -->
            <div class="mobile-carrinho-flutuante">
                <div class="mobile-carrinho-info">
                    <p class="mobile-carrinho-total" id="mobile-total">R$ 0,00</p>
                    <p class="mobile-carrinho-itens" id="mobile-itens">0 itens</p>
                </div>
                <div class="mobile-carrinho-botoes">
                    <button class="mobile-btn-carrinho mobile-btn-ver-carrinho" onclick="mobilePedido.showCarrinhoModal()">
                        Ver Carrinho
                    </button>
                    <button class="mobile-btn-carrinho mobile-btn-finalizar" onclick="mobilePedido.finalizarPedido()">
                        Finalizar
                    </button>
                </div>
            </div>
            
            <!-- Botões de ação flutuantes -->
            <div class="mobile-action-buttons">
                <button class="mobile-fab" onclick="mobilePedido.scrollToTop()" title="Voltar ao topo">
                    <i class="fas fa-arrow-up"></i>
                </button>
            </div>
            
            <!-- Modal do carrinho -->
            <div class="mobile-carrinho-modal" id="mobile-carrinho-modal">
                <div class="mobile-carrinho-content">
                    <div class="mobile-carrinho-header">
                        <h3 class="mobile-carrinho-title">Carrinho</h3>
                        <button class="mobile-carrinho-close" onclick="mobilePedido.hideCarrinhoModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="mobile-carrinho-items">
                        <!-- Itens do carrinho -->
                    </div>
                </div>
            </div>
        `;
        
        // Inserir antes do conteúdo original
        const originalContent = document.querySelector('.main-content');
        if (originalContent) {
            originalContent.parentNode.insertBefore(mobileContainer, originalContent);
            originalContent.style.display = 'none';
        }
    }
    
    loadData() {
        console.log('📊 Carregando dados...');
        
        // Sempre carregar produtos dinamicamente via AJAX
        console.log('🔄 Carregando produtos via API...');
        this.loadProdutos();
        
        if (window.mesasData) {
            console.log('🏢 Usando dados PHP para mesas:', window.mesasData.length);
            this.mesas = window.mesasData;
            this.renderMesas();
        } else {
            console.log('🔄 Dados PHP não encontrados, carregando via API...');
            this.loadMesas();
        }
        
        // Carregar categorias para filtro
        this.loadCategorias();
        
        // Limpar seleção de mesa ao carregar
        this.mesaSelecionada = null;
        const mesaInfo = document.getElementById('mesa-info');
        if (mesaInfo) {
            mesaInfo.textContent = 'Nenhuma mesa selecionada';
        }
    }
    
    async loadMesas() {
        try {
            const response = await fetch('api/mesas.php');
            const data = await response.json();
            this.mesas = data.mesas || [];
            this.renderMesas();
        } catch (error) {
            console.error('Erro ao carregar mesas:', error);
            // Fallback para mesas padrão
            this.mesas = [
                { id_mesa: '1', nome: 'Mesa 1', status: 'livre' },
                { id_mesa: '2', nome: 'Mesa 2', status: 'livre' },
                { id_mesa: '3', nome: 'Mesa 3', status: 'ocupada' },
                { id_mesa: '4', nome: 'Mesa 4', status: 'livre' },
                { id_mesa: '5', nome: 'Mesa 5', status: 'livre' },
                { id_mesa: '6', nome: 'Mesa 6', status: 'livre' },
                { id_mesa: '7', nome: 'Mesa 7', status: 'livre' },
                { id_mesa: '8', nome: 'Mesa 8', status: 'livre' },
                { id_mesa: '9', nome: 'Mesa 9', status: 'livre' },
                { id_mesa: '10', nome: 'Mesa 10', status: 'livre' },
                { id_mesa: 'delivery', nome: 'Delivery', status: 'livre' }
            ];
            this.renderMesas();
        }
    }
    
    async loadProdutos(query = '', categoriaId = '') {
        try {
            console.log('🔄 Carregando produtos...', { query, categoriaId });
            
            // Construir URL com parâmetros
            // Sempre usar buscar_produtos para manter consistência (aceita query vazia)
            let url = 'index.php?action=produtos&buscar_produtos=1';
            
            if (query && query.trim()) {
                url += `&q=${encodeURIComponent(query.trim())}`;
            }
            
            if (categoriaId && categoriaId.trim()) {
                url += `&categoria_id=${encodeURIComponent(categoriaId.trim())}`;
            }
            
            console.log('📡 URL da busca:', url);
            
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('📡 Status da resposta:', response.status, response.statusText);
            
            if (response.ok) {
                const data = await response.json();
                console.log('📦 Dados recebidos:', data);
                
                if (!data.success) {
                    console.error('❌ Erro na resposta:', data.message || 'Erro desconhecido');
                    throw new Error(data.message || 'Erro ao buscar produtos');
                }
                
                let produtosRecebidos = data.produtos || [];
                console.log('📦 Produtos recebidos da API:', produtosRecebidos.length);
                
                // Remover duplicados por ID (caso haja duplicação na query)
                // Usar Map para garantir unicidade por ID
                const produtosMap = new Map();
                
                produtosRecebidos.forEach(produto => {
                    const produtoId = parseInt(produto.id); // Garantir que ID seja sempre inteiro
                    if (!produtosMap.has(produtoId)) {
                        produtosMap.set(produtoId, produto);
                    }
                });
                
                this.produtos = Array.from(produtosMap.values());
                console.log('🍔 Produtos únicos após remoção de duplicados:', this.produtos.length);
                
                // Se não encontrou produtos e não há query, tentar usar dados PHP
                if (this.produtos.length === 0 && !query && window.produtosData && Array.isArray(window.produtosData)) {
                    console.log('🔄 Usando dados PHP como fallback...', window.produtosData.length, 'produtos');
                    // Sempre remover duplicados dos dados PHP (mesmo que já venham únicos)
                    const produtosPHPMap = new Map();
                    
                    window.produtosData.forEach(produto => {
                        const produtoId = parseInt(produto.id); // Garantir que ID seja sempre inteiro
                        if (!produtosPHPMap.has(produtoId)) {
                            produtosPHPMap.set(produtoId, produto);
                        } else {
                            console.warn('⚠️ Produto duplicado encontrado nos dados PHP:', produtoId, produto.nome);
                        }
                    });
                    
                    this.produtos = Array.from(produtosPHPMap.values());
                    console.log('🍔 Produtos únicos do PHP:', this.produtos.length);
                }
            } else {
                const errorText = await response.text();
                console.error('❌ Erro HTTP:', response.status, errorText);
                throw new Error(`Erro HTTP ${response.status}: ${errorText}`);
            }
            
            this.renderProdutos();
        } catch (error) {
            console.error('❌ Erro ao carregar produtos:', error);
            
            // Tentar usar dados PHP se disponíveis
            if (window.produtosData && window.produtosData.length > 0) {
                console.log('🔄 Usando dados PHP como fallback...');
                this.produtos = window.produtosData;
                this.renderProdutos();
            } else {
                console.log('⚠️ Nenhum produto encontrado');
                this.produtos = [];
                this.renderProdutos();
            }
        }
    }
    
    renderMesas() {
        const grid = document.getElementById('mobile-mesas-grid');
        if (!grid) return;
        
        let html = this.mesas.map(mesa => `
            <div class="mobile-mesa-card ${mesa.status}" 
                 data-mesa-id="${mesa.id_mesa}" 
                 onclick="mobilePedido.selecionarMesa('${mesa.id_mesa}', '${mesa.nome}')">
                <div class="mobile-mesa-numero">${mesa.nome}</div>
                <div class="mobile-mesa-status">${mesa.status === 'livre' ? 'Livre' : 'Ocupada'}</div>
            </div>
        `).join('');
        
        // Adicionar opção de Delivery
        html += `
            <div class="mobile-mesa-card livre" 
                 data-mesa-id="delivery" 
                 onclick="mobilePedido.selecionarMesa('delivery', 'Delivery')">
                <div class="mobile-mesa-numero">
                    <i class="fas fa-motorcycle me-1"></i> Delivery
                </div>
                <div class="mobile-mesa-status">Disponível</div>
            </div>
        `;
        
        grid.innerHTML = html;
    }
    
    renderProdutos() {
        console.log('🎨 Renderizando produtos...');
        const grid = document.getElementById('mobile-produtos-grid');
        console.log('📋 Grid encontrado:', grid);
        
        if (!grid) {
            console.error('❌ Grid mobile-produtos-grid não encontrado!');
            return;
        }
        
        console.log('🍔 Produtos para renderizar:', this.produtos);
        
        if (this.produtos.length === 0) {
            console.log('⚠️ Nenhum produto para renderizar');
            grid.innerHTML = '<p style="text-align: center; color: #666; margin-top: 50px;">Nenhum produto encontrado</p>';
            return;
        }
        
        const html = this.produtos.map(produto => {
            // Verificar e corrigir dados do produto - usar preco_normal
            const preco = produto.preco_normal || produto.preco || produto.valor || 0;
            const nome = produto.nome || 'Produto';
            const categoria = produto.categoria_nome || produto.categoria || 'Geral';
            const id = produto.id || Math.random();
            const ingredientes = produto.ingredientes || [];
            
            // Escapar nome para evitar problemas com aspas
            const nomeEscapado = nome.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            
            console.log('🔍 Produto:', { id, nome, preco, categoria, ingredientes: ingredientes.length });
            
            return `
                <div class="mobile-produto-card">
                    <div class="mobile-produto-categoria">${categoria}</div>
                    <div class="mobile-produto-nome">${nome}</div>
                    <div class="mobile-produto-preco">R$ ${parseFloat(preco).toFixed(2).replace('.', ',')}</div>
                    <div class="mobile-produto-botoes">
                        <button class="mobile-btn-personalizar" onclick="mobilePedido.personalizarProduto(${id}, '${nomeEscapado}', ${preco}, ${JSON.stringify(ingredientes).replace(/"/g, '&quot;')})">
                            <i class="fas fa-cog"></i> Personalizar
                        </button>
                        <button class="mobile-btn-adicionar" onclick="mobilePedido.adicionarProdutoRapido(${id}, '${nomeEscapado}', ${preco})">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        console.log('📝 HTML gerado:', html);
        grid.innerHTML = html;
        console.log('✅ Produtos renderizados com sucesso!');
    }
    
    selecionarMesa(mesaId, mesaNome) {
        // Verificar se a mesa está ocupada (exceto delivery)
        if (mesaId !== 'delivery') {
            const mesaCard = document.querySelector(`[data-mesa-id="${mesaId}"]`);
            if (mesaCard && mesaCard.classList.contains('ocupada')) {
                alert('Esta mesa está ocupada! Selecione uma mesa livre.');
                return;
            }
        }
        
        this.mesaSelecionada = { id: mesaId, nome: mesaNome, tipo: mesaId === 'delivery' ? 'delivery' : 'mesa' };
        
        // Atualizar visual
        document.querySelectorAll('.mobile-mesa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        const mesaCard = document.querySelector(`[data-mesa-id="${mesaId}"]`);
        if (mesaCard) {
            mesaCard.classList.add('selected');
        }
        
        // Atualizar header
        const mesaInfo = document.getElementById('mesa-info');
        if (mesaInfo) {
            if (mesaId === 'delivery') {
                mesaInfo.textContent = 'Delivery';
            } else {
                mesaInfo.textContent = `Mesa: ${mesaNome}`;
            }
        }
        
        // Mostrar/ocultar campos de endereço baseado no tipo
        const enderecoSection = document.getElementById('mobile-endereco-section');
        if (enderecoSection) {
            if (mesaId === 'delivery') {
                enderecoSection.style.display = 'block';
            } else {
                enderecoSection.style.display = 'none';
                // Limpar campos de endereço se não for delivery
                const camposEndereco = [
                    'mobile-endereco-rua',
                    'mobile-endereco-bairro',
                    'mobile-endereco-cidade',
                    'mobile-endereco-estado',
                    'mobile-endereco-cep',
                    'mobile-endereco-referencia'
                ];
                camposEndereco.forEach(id => {
                    const campo = document.getElementById(id);
                    if (campo) campo.value = '';
                });
            }
        }
        
        // Ir para aba de produtos
        this.switchTab('produtos');
    }
    
    async personalizarProduto(produtoId, nome, preco, ingredientes) {
        if (!this.mesaSelecionada) {
            alert('Selecione uma mesa primeiro!');
            this.switchTab('mesas');
            return;
        }
        
        // Buscar dados completos do produto via AJAX (igual desktop)
        try {
            console.log('🔄 Buscando dados do produto:', produtoId);
            const response = await fetch('mvc/ajax/produtos_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=buscar_produto&produto_id=' + produtoId
            });
            
            const data = await response.json();
            console.log('📦 Dados do produto recebidos:', data);
            
            if (data.success) {
                // Usar ingredientes do produto e todos os ingredientes disponíveis
                const ingredientesProduto = data.ingredientes || ingredientes || [];
                const todosIngredientes = data.todos_ingredientes || window.todosIngredientes || [];
                
                // Atualizar preço se necessário
                const precoAtualizado = parseFloat(data.produto.preco_normal || preco);
                const precoMini = parseFloat(data.produto.preco_mini || 0);
                
                // Mostrar modal de personalização com ingredientes reais
                this.showPersonalizacaoModal(produtoId, nome, precoAtualizado, ingredientesProduto, todosIngredientes, precoMini);
            } else {
                // Fallback para ingredientes passados
                this.showPersonalizacaoModal(produtoId, nome, preco, ingredientes || [], window.todosIngredientes || []);
            }
        } catch (error) {
            console.error('❌ Erro ao buscar produto:', error);
            // Fallback para ingredientes passados
            this.showPersonalizacaoModal(produtoId, nome, preco, ingredientes || [], window.todosIngredientes || []);
        }
    }
    
    adicionarProdutoRapido(produtoId, nome, preco) {
        if (!this.mesaSelecionada) {
            alert('Selecione uma mesa primeiro!');
            this.switchTab('mesas');
            return;
        }
        
        // Adicionar diretamente sem personalização
        const itemExistente = this.carrinho.find(item => item.id === produtoId);
        
        if (itemExistente) {
            itemExistente.quantidade++;
        } else {
            this.carrinho.push({
                id: produtoId,  // Usar 'id' como o desktop
                nome,
                preco,
                quantidade: 1,
                observacao: ''  // Usar 'observacao' como o desktop
            });
        }
        
        this.updateCarrinho();
        this.showFeedback('Produto adicionado!');
    }
    
    showPersonalizacaoModal(produtoId, nome, preco, ingredientesProduto = [], todosIngredientes = [], precoMini = 0) {
        // Usar TODOS os ingredientes disponíveis (igual desktop)
        const todosIngredientesDisponiveis = todosIngredientes.length > 0 ? todosIngredientes : (window.todosIngredientes || []);
        const ingredientesDoProduto = ingredientesProduto.length > 0 ? ingredientesProduto : [];
        
        console.log('🍔 Ingredientes do produto:', ingredientesDoProduto);
        console.log('🍔 Todos os ingredientes disponíveis:', todosIngredientesDisponiveis);
        
        if (!todosIngredientesDisponiveis || todosIngredientesDisponiveis.length === 0) {
            // Se não houver ingredientes, mostrar mensagem
            alert('Não há ingredientes disponíveis para personalizar.');
            return;
        }
        
        // Salvar dados do produto para uso no modal
        this.produtoAtualPersonalizacao = {
            id: produtoId,
            nome: nome,
            preco: preco,
            preco_normal: preco,
            preco_mini: precoMini,
            ingredientes_originais: [...ingredientesDoProduto]
        };
        
        // Criar modal de personalização
        const modal = document.createElement('div');
        modal.className = 'mobile-personalizacao-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            display: flex;
            align-items: flex-end;
        `;
        
        // Criar lista de ingredientes clicáveis (igual desktop)
        const ingredientesHTML = todosIngredientesDisponiveis.map(ing => {
            // Verificar se este ingrediente já está no produto
            const jaEstaNoProduto = ingredientesDoProduto.some(ingProduto => {
                const ingId = typeof ingProduto === 'object' ? ingProduto.id : ingProduto;
                return ingId == ing.id;
            });
            const precoAdicional = parseFloat(ing.preco_adicional || 0);
            
            console.log(`🔍 Inicializando ingrediente: ${ing.nome}`, {
                id: ing.id,
                jaEstaNoProduto,
                ingredientesDoProduto: ingredientesDoProduto.map(i => ({ 
                    id: typeof i === 'object' ? i.id : i, 
                    nome: typeof i === 'object' ? i.nome : i 
                }))
            });
            
            return `
                <div class="mobile-ingrediente-item" 
                     data-ingrediente-id="${ing.id}" 
                     data-ingrediente-nome="${ing.nome}"
                     data-preco-adicional="${precoAdicional}"
                     data-ja-estava="${jaEstaNoProduto}"
                     style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: all 0.3s;"
                     onclick="mobilePedido.toggleIngrediente(this)">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="mobile-ingrediente-status" style="width: 20px; height: 20px; border-radius: 50%; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                            <span class="status-icon">${jaEstaNoProduto ? '✓' : '○'}</span>
                        </div>
                        <span style="font-weight: 500; font-size: 14px;">
                            ${ing.nome}
                            ${precoAdicional > 0 ? ` (+R$ ${precoAdicional.toFixed(2)})` : ''}
                        </span>
                    </div>
                    <div class="mobile-ingrediente-tipo" style="padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; ${jaEstaNoProduto ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;'}">
                        ${jaEstaNoProduto ? 'COM' : 'SEM'}
                    </div>
                </div>
            `;
        }).join('');
        
        modal.innerHTML = `
            <div style="background: white; width: 100%; border-radius: 20px 20px 0 0; padding: 20px; max-height: 80vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 18px;">Personalizar ${nome}</h3>
                    <button onclick="this.closest('.mobile-personalizacao-modal').remove()" style="background: none; border: none; font-size: 24px; color: #666; cursor: pointer;">×</button>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">${nome}</h4>
                    <p style="margin: 0; color: #666; font-size: 16px;">
                        Normal: R$ ${parseFloat(preco).toFixed(2)}
                        ${precoMini > 0 ? ` | Mini: R$ ${parseFloat(precoMini).toFixed(2)}` : ''}
                    </p>
                    <div style="margin-top: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tamanho:</label>
                        <select id="mobile-tamanho-item" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 8px;">
                            <option value="normal">Normal</option>
                            ${precoMini > 0 ? '<option value="mini">Mini</option>' : ''}
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">Ingredientes:</h5>
                    <div style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                        ${ingredientesHTML}
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Quantidade:</label>
                    <div style="display: flex; align-items: center; gap: 15px; background: #f8f9fa; border-radius: 8px; padding: 10px;">
                        <button onclick="this.parentElement.querySelector('.qty-value').textContent = Math.max(1, parseInt(this.parentElement.querySelector('.qty-value').textContent) - 1)" style="background: #6f42c1; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">-</button>
                        <span class="qty-value" style="font-size: 18px; font-weight: 600; min-width: 30px; text-align: center;">1</span>
                        <button onclick="this.parentElement.querySelector('.qty-value').textContent = parseInt(this.parentElement.querySelector('.qty-value').textContent) + 1" style="background: #6f42c1; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">+</button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Observações adicionais:</label>
                    <textarea id="observacoes" placeholder="Ex: Bem assado, sem sal..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; resize: vertical; min-height: 60px;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button onclick="this.closest('.mobile-personalizacao-modal').remove()" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancelar</button>
                    <button onclick="mobilePedido.confirmarPersonalizacao(${produtoId}, '${nome}', ${preco}, this.closest('.mobile-personalizacao-modal'))" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer;">Adicionar</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    toggleIngrediente(element) {
        const ingredienteId = parseInt(element.dataset.ingredienteId);
        const ingredienteNome = element.dataset.ingredienteNome;
        const jaEstava = element.dataset.jaEstava === 'true';
        
        // Alternar estado
        const statusIcon = element.querySelector('.status-icon');
        const tipoDiv = element.querySelector('.mobile-ingrediente-tipo');
        const statusDiv = element.querySelector('.mobile-ingrediente-status');
        
        // Verificar estado atual
        const atualmenteCom = tipoDiv.textContent === 'COM';
        const novoEstado = !atualmenteCom;
        
        // Atualizar visual
        if (novoEstado) {
            statusIcon.textContent = '✓';
            tipoDiv.textContent = 'COM';
            tipoDiv.style.background = '#d4edda';
            tipoDiv.style.color = '#155724';
            statusDiv.style.borderColor = '#28a745';
            statusDiv.style.backgroundColor = '#d4edda';
        } else {
            statusIcon.textContent = '○';
            tipoDiv.textContent = 'SEM';
            tipoDiv.style.background = '#f8d7da';
            tipoDiv.style.color = '#721c24';
            statusDiv.style.borderColor = '#dc3545';
            statusDiv.style.backgroundColor = '#f8d7da';
        }
        
        // Adicionar efeito visual
        element.style.transform = 'scale(0.98)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 150);
    }
    
    confirmarPersonalizacao(produtoId, nome, preco, modal) {
        const quantidade = parseInt(modal.querySelector('.qty-value').textContent);
        const observacoes = modal.querySelector('#observacoes').value;
        const tamanho = modal.querySelector('#mobile-tamanho-item')?.value || 'normal';
        
        // Obter dados do produto atual
        const produtoAtual = this.produtoAtualPersonalizacao || { preco_normal: preco, preco_mini: 0 };
        const precoBase = tamanho === 'mini' && produtoAtual.preco_mini > 0 ? produtoAtual.preco_mini : produtoAtual.preco_normal;
        
        // Capturar ingredientes adicionados e removidos (igual desktop)
        const ingredientesAdicionados = [];
        const ingredientesRemovidos = [];
        const ingredientesItems = modal.querySelectorAll('.mobile-ingrediente-item');
        
        // Buscar ingredientes originais do produto
        const ingredientesOriginais = produtoAtual.ingredientes_originais || [];
        
        ingredientesItems.forEach(item => {
            const ingredienteId = parseInt(item.dataset.ingredienteId);
            const ingredienteNome = item.dataset.ingredienteNome;
            const precoAdicional = parseFloat(item.dataset.precoAdicional || 0);
            const jaEstava = item.dataset.jaEstava === 'true';
            
            // Verificar estado atual (COM ou SEM)
            const tipoDiv = item.querySelector('.mobile-ingrediente-tipo');
            const atualmenteCom = tipoDiv.textContent.trim() === 'COM';
            
            // Só adicionar modificação se mudou de estado
            if (jaEstava !== atualmenteCom) {
                const ingredienteObj = {
                    id: ingredienteId,
                    nome: ingredienteNome,
                    preco_adicional: precoAdicional
                };
                
                if (atualmenteCom) {
                    // Estava SEM, agora está COM = adicionado
                    ingredientesAdicionados.push(ingredienteObj);
                } else {
                    // Estava COM, agora está SEM = removido
                    ingredientesRemovidos.push(ingredienteObj);
                }
            }
        });
        
        // Calcular preço total (produto base + apenas ingredientes adicionados)
        let precoTotal = precoBase;
        ingredientesAdicionados.forEach(ing => {
            precoTotal += parseFloat(ing.preco_adicional);
        });
        
        // Criar item do carrinho (igual desktop)
        const itemCarrinho = {
            id: produtoId,
            nome: nome,
            preco: precoTotal,
            preco_normal: produtoAtual.preco_normal,
            preco_mini: produtoAtual.preco_mini || 0,
            quantidade: quantidade,
            tamanho: tamanho,
            observacao: observacoes,
            ingredientes_adicionados: ingredientesAdicionados,
            ingredientes_removidos: ingredientesRemovidos
        };
        
        // Verificar se é produto personalizado (tem ingredientes modificados)
        const temIngredientesModificados = ingredientesAdicionados.length > 0 || ingredientesRemovidos.length > 0;
        
        if (temIngredientesModificados) {
            // Produto personalizado - sempre adicionar como novo item
            this.carrinho.push(itemCarrinho);
        } else {
            // Produto normal - verificar se já existe
            const existingIndex = this.carrinho.findIndex(item => 
                item.id === produtoId && 
                item.tamanho === tamanho &&
                (!item.ingredientes_adicionados || item.ingredientes_adicionados.length === 0) &&
                (!item.ingredientes_removidos || item.ingredientes_removidos.length === 0)
            );
            
            if (existingIndex >= 0) {
                this.carrinho[existingIndex].quantidade += quantidade;
            } else {
                this.carrinho.push(itemCarrinho);
            }
        }
        
        this.updateCarrinho();
        this.showFeedback('Produto personalizado adicionado!');
        modal.remove();
        
        // Limpar produto atual
        this.produtoAtualPersonalizacao = null;
    }
    
    updateCarrinho() {
        const total = this.carrinho.reduce((sum, item) => sum + (item.preco * item.quantidade), 0);
        const itens = this.carrinho.reduce((sum, item) => sum + item.quantidade, 0);
        
        document.getElementById('mobile-total').textContent = `R$ ${total.toFixed(2)}`;
        document.getElementById('mobile-itens').textContent = `${itens} itens`;
        
        this.renderCarrinho();
    }
    
    renderCarrinho() {
        const content = document.getElementById('mobile-carrinho-content');
        const modalContent = document.getElementById('mobile-carrinho-items');
        
        if (this.carrinho.length === 0) {
            content.innerHTML = '<p style="text-align: center; color: #666; margin-top: 50px;">Carrinho vazio</p>';
            modalContent.innerHTML = '<p style="text-align: center; color: #666; margin-top: 50px;">Carrinho vazio</p>';
            return;
        }
        
        const carrinhoHTML = this.carrinho.map((item, index) => {
            // Usar estrutura igual ao desktop (ingredientes_adicionados e ingredientes_removidos)
            const temModificacoes = (item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0) || 
                                   (item.ingredientes_removidos && item.ingredientes_removidos.length > 0);
            
            return `
                <div class="mobile-carrinho-item">
                    <div class="mobile-carrinho-item-info">
                        <p class="mobile-carrinho-item-nome">${item.nome}</p>
                        ${item.tamanho ? `<p style="font-size: 11px; color: #666; margin: 2px 0;">Tamanho: ${item.tamanho}</p>` : ''}
                        ${temModificacoes ? `
                            <div style="font-size: 12px; margin: 4px 0;">
                                ${item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0 ? item.ingredientes_adicionados.map(ing => {
                                    const nome = typeof ing === 'string' ? ing : (ing.nome || 'Ingrediente');
                                    const preco = typeof ing === 'object' && ing.preco_adicional ? parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',') : '0,00';
                                    return `<span style="display: inline-block; margin: 1px 2px; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; background: #d4edda; color: #155724;">+${nome} (+R$ ${preco})</span>`;
                                }).join('') : ''}
                                ${item.ingredientes_removidos && item.ingredientes_removidos.length > 0 ? item.ingredientes_removidos.map(ing => {
                                    const nome = typeof ing === 'string' ? ing : (ing.nome || 'Ingrediente');
                                    return `<span style="display: inline-block; margin: 1px 2px; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; background: #f8d7da; color: #721c24;">-${nome}</span>`;
                                }).join('') : ''}
                            </div>
                        ` : ''}
                        ${item.observacao ? `<p style="font-size: 11px; color: #666; margin: 2px 0;">Obs: ${item.observacao}</p>` : ''}
                        <p class="mobile-carrinho-item-preco">R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}</p>
                    </div>
                    <div class="mobile-carrinho-item-controls">
                        <div class="mobile-carrinho-qty">
                            <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidadePorIndex(${index}, -1)">-</button>
                            <span class="mobile-carrinho-qty-value">${item.quantidade}</span>
                            <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidadePorIndex(${index}, 1)">+</button>
                        </div>
                        <button class="mobile-carrinho-remove" onclick="mobilePedido.removerItemPorIndex(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        content.innerHTML = carrinhoHTML;
        modalContent.innerHTML = carrinhoHTML;
    }
    
    alterarQuantidade(produtoId, delta) {
        const item = this.carrinho.find(item => item.id === produtoId);
        if (!item) return;
        
        item.quantidade += delta;
        
        if (item.quantidade <= 0) {
            this.removerItem(produtoId);
        } else {
            this.updateCarrinho();
        }
    }
    
    alterarQuantidadePorIndex(index, delta) {
        if (index < 0 || index >= this.carrinho.length) return;
        
        this.carrinho[index].quantidade += delta;
        
        if (this.carrinho[index].quantidade <= 0) {
            this.removerItemPorIndex(index);
        } else {
            this.updateCarrinho();
        }
    }
    
    removerItem(produtoId) {
        this.carrinho = this.carrinho.filter(item => item.id !== produtoId);
        this.updateCarrinho();
    }
    
    removerItemPorIndex(index) {
        if (index < 0 || index >= this.carrinho.length) return;
        this.carrinho.splice(index, 1);
        this.updateCarrinho();
    }
    
    switchTab(tabName) {
        // Atualizar abas
        document.querySelectorAll('.mobile-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Atualizar conteúdo
        document.querySelectorAll('.mobile-tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`tab-${tabName}`).classList.add('active');
        
        this.currentTab = tabName;
    }
    
    showCarrinhoModal() {
        document.getElementById('mobile-carrinho-modal').classList.add('active');
    }
    
    hideCarrinhoModal() {
        document.getElementById('mobile-carrinho-modal').classList.remove('active');
    }
    
    buscarClientePorTelefone() {
        const telefone = document.getElementById('mobile-cliente-telefone')?.value.trim();
        if (!telefone) {
            this.showFeedback('Digite o telefone do cliente', 'warning');
            return;
        }
        
        fetch(`mvc/ajax/clientes.php?action=buscar_por_telefone&telefone=${encodeURIComponent(telefone)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cliente) {
                    // Carregar dados do cliente nos campos
                    document.getElementById('mobile-cliente-nome').value = data.cliente.nome || '';
                    document.getElementById('mobile-cliente-email').value = data.cliente.email || '';
                    document.getElementById('mobile-cliente-cpf').value = data.cliente.cpf || '';
                    
                    // Se o cliente tem endereço e é delivery, preencher campos de endereço
                    if (this.mesaSelecionada && this.mesaSelecionada.id === 'delivery' && data.cliente.endereco) {
                        const endereco = typeof data.cliente.endereco === 'string' 
                            ? JSON.parse(data.cliente.endereco) 
                            : data.cliente.endereco;
                        
                        if (endereco) {
                            document.getElementById('mobile-endereco-rua').value = endereco.endereco || '';
                            document.getElementById('mobile-endereco-bairro').value = endereco.bairro || '';
                            document.getElementById('mobile-endereco-cidade').value = endereco.cidade || '';
                            document.getElementById('mobile-endereco-estado').value = endereco.estado || '';
                            document.getElementById('mobile-endereco-cep').value = endereco.cep || '';
                            document.getElementById('mobile-endereco-referencia').value = endereco.referencia || '';
                        }
                    }
                    
                    // Mostrar mensagem de sucesso
                    const alertDiv = document.getElementById('mobile-cliente-encontrado');
                    const alertTexto = document.getElementById('mobile-cliente-encontrado-texto');
                    if (alertDiv && alertTexto) {
                        alertTexto.textContent = `Cliente encontrado: ${data.cliente.nome}`;
                        alertDiv.style.display = 'block';
                        setTimeout(() => {
                            alertDiv.style.display = 'none';
                        }, 3000);
                    }
                    
                    this.showFeedback(`Cliente encontrado: ${data.cliente.nome}`, 'success');
                } else {
                    this.showFeedback('Cliente não encontrado. Você pode cadastrar preenchendo os dados.', 'info');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar cliente:', error);
                this.showFeedback('Erro ao buscar cliente', 'error');
            });
    }
    
    obterDadosCliente() {
        const nome = document.getElementById('mobile-cliente-nome')?.value.trim() || '';
        const telefone = document.getElementById('mobile-cliente-telefone')?.value.trim() || '';
        const email = document.getElementById('mobile-cliente-email')?.value.trim() || '';
        const cpf = document.getElementById('mobile-cliente-cpf')?.value.trim() || '';
        
        // Se não tem nome, retornar null
        if (!nome) {
            return null;
        }
        
        const dados = {
            nome: nome,
            telefone: telefone || null,
            email: email || null,
            cpf: cpf || null
        };
        
        // Se for delivery, adicionar endereço
        if (this.mesaSelecionada && this.mesaSelecionada.id === 'delivery') {
            const enderecoRua = document.getElementById('mobile-endereco-rua')?.value.trim() || '';
            const enderecoBairro = document.getElementById('mobile-endereco-bairro')?.value.trim() || '';
            const enderecoCidade = document.getElementById('mobile-endereco-cidade')?.value.trim() || '';
            const enderecoEstado = document.getElementById('mobile-endereco-estado')?.value.trim() || '';
            const enderecoCep = document.getElementById('mobile-endereco-cep')?.value.trim() || '';
            const enderecoReferencia = document.getElementById('mobile-endereco-referencia')?.value.trim() || '';
            
            // Validar campos obrigatórios de endereço
            if (!enderecoRua || !enderecoBairro || !enderecoCidade || !enderecoEstado) {
                this.showFeedback('Preencha todos os campos obrigatórios do endereço (Rua, Bairro, Cidade e Estado)', 'warning');
                return null;
            }
            
            dados.endereco = {
                endereco: enderecoRua,
                bairro: enderecoBairro,
                cidade: enderecoCidade,
                estado: enderecoEstado,
                cep: enderecoCep || null,
                referencia: enderecoReferencia || null
            };
        }
        
        return dados;
    }
    
    finalizarPedido() {
        if (!this.mesaSelecionada) {
            alert('Selecione uma mesa primeiro!');
            this.switchTab('mesas');
            return;
        }
        
        if (this.carrinho.length === 0) {
            alert('Adicione produtos ao carrinho!');
            this.switchTab('produtos');
            return;
        }
        
        // Aqui você pode integrar com a API existente
        this.enviarPedido();
    }
    
    async enviarPedido() {
        try {
            console.log('📤 Enviando pedido...', {
                mesa: this.mesaSelecionada,
                carrinho: this.carrinho
            });
            
            const pedidoData = {
                mesa: this.mesaSelecionada.id,
                itens: this.carrinho,
                observacao: document.getElementById('observacaoPedido')?.value || ''
            };
            
            console.log('📋 Dados do pedido:', pedidoData);
            
            // Obter dados do cliente
            const dadosCliente = this.obterDadosCliente();
            
            // Se for delivery e não tiver dados do cliente, avisar
            if (this.mesaSelecionada.id === 'delivery' && !dadosCliente) {
                this.showFeedback('Para delivery, é necessário preencher os dados do cliente e endereço', 'warning');
                this.switchTab('cliente');
                return;
            }
            
            // Usar a mesma API do desktop
            const formData = new URLSearchParams();
            formData.append('action', 'criar_pedido');
            formData.append('mesa_id', this.mesaSelecionada.id);
            formData.append('itens', JSON.stringify(this.carrinho));
            formData.append('observacao', document.getElementById('observacaoPedido')?.value || '');
            
            // Adicionar dados do cliente se houver
            if (dadosCliente) {
                formData.append('dados_cliente', JSON.stringify(dadosCliente));
            }
            
            const response = await fetch('index.php?action=pedidos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            console.log('📡 Resposta da API:', response.status, response.statusText);
            
            const result = await response.json();
            console.log('📄 Resultado:', result);
            
            if (result.success) {
                this.showFeedback('Pedido criado com sucesso!');
                
                // CAPTURAR DADOS ANTES DE LIMPAR
                const mesaParaImprimir = this.mesaSelecionada;
                const carrinhoParaImprimir = [...this.carrinho];
                
                this.limparCarrinho();
                
                // Perguntar se quer imprimir
                const deveImprimir = confirm('Pedido criado! Deseja imprimir o cupom?');
                if (deveImprimir) {
                    const pedidoId = result.pedido_id || result.pedido?.idpedido || result.pedido?.id;
                    this.imprimirPedido(pedidoId, mesaParaImprimir, carrinhoParaImprimir);
                }
                
                // Redirecionar para dashboard após 2 segundos (para dar tempo da impressão se necessário)
                setTimeout(() => {
                    const dashboardUrl = window.dashboardUrl || 'index.php?view=dashboard';
                    window.location.href = dashboardUrl;
                }, 2000);
            } else {
                throw new Error(result.error || result.message || 'Erro ao criar pedido');
            }
        } catch (error) {
            console.error('❌ Erro ao enviar pedido:', error);
            alert('Erro ao criar pedido: ' + error.message);
        }
    }
    
    limparCarrinho() {
        this.carrinho = [];
        this.mesaSelecionada = null;
        this.updateCarrinho();
        this.switchTab('mesas');
        
        // Limpar seleção visual
        document.querySelectorAll('.mobile-mesa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        document.getElementById('mesa-info').textContent = 'Selecione uma mesa';
    }
    
    getUsuarioNome() {
        // Usar nome do usuário passado pelo PHP
        return window.usuarioNome || 'Garçom';
    }
    
    imprimirPedido(pedidoId, mesaSelecionada, carrinho) {
        console.log('🖨️ Imprimindo pedido:', pedidoId);
        
        // Criar janela de impressão
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        
        if (!printWindow) {
            alert('Erro: Não foi possível abrir janela de impressão. Verifique se o popup está bloqueado.');
            return;
        }
        
        // HTML do cupom (versão simplificada para mobile)
        const cupomHtml = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Cupom - Pedido #${pedidoId}</title>
                <style>
                    body { 
                        font-family: 'Courier New', monospace; 
                        font-size: 18px; 
                        margin: 15px; 
                        padding: 15px;
                        line-height: 1.5;
                        font-weight: bold;
                    }
                    .header { 
                        text-align: center; 
                        border-bottom: 3px solid #000; 
                        padding-bottom: 15px; 
                        margin-bottom: 20px; 
                    }
                    .empresa { 
                        font-weight: bold; 
                        font-size: 24px; 
                        margin-bottom: 8px;
                    }
                    .endereco {
                        font-size: 18px;
                        font-weight: bold;
                    }
                    .pedido-info { 
                        margin: 15px 0; 
                        font-size: 18px;
                        font-weight: bold;
                        background: #f0f0f0;
                        padding: 10px;
                        border-radius: 5px;
                    }
                    .item { 
                        margin: 8px 0; 
                        padding: 5px 0;
                        border-bottom: 2px dotted #ccc;
                    }
                    .item-nome { 
                        font-weight: bold; 
                        font-size: 20px; 
                    }
                    .item-detalhes { 
                        font-size: 18px; 
                        margin-left: 15px; 
                        margin-top: 5px;
                        font-weight: bold;
                    }
                    .total { 
                        border-top: 3px solid #000; 
                        padding-top: 15px; 
                        margin-top: 20px; 
                        font-weight: bold; 
                        font-size: 22px;
                        text-align: center;
                        background: #f0f0f0;
                        padding: 15px;
                        border-radius: 5px;
                    }
                    .footer { 
                        text-align: center; 
                        margin-top: 25px; 
                        font-size: 16px; 
                        font-weight: bold;
                    }
                    .usuario {
                        font-size: 16px;
                        font-weight: bold;
                        color: #333;
                        margin-top: 10px;
                    }
                    @media print { 
                        body { 
                            margin: 0; 
                            padding: 10px; 
                            font-size: 16px;
                        }
                        .empresa { font-size: 20px; }
                        .total { font-size: 18px; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="empresa">DIVINO LANCHES</div>
                    <div>Rua das Flores, 123 - Centro</div>
                </div>
                
                <div class="pedido-info">
                    <strong>Pedido #${pedidoId}</strong><br>
                    Mesa: ${mesaSelecionada.nome}<br>
                    Data: ${new Date().toLocaleString('pt-BR')}<br>
                    <div class="usuario">Atendente: ${this.getUsuarioNome()}</div>
                </div>
                
                <div class="items">
                    ${carrinho.map(item => `
                        <div class="item">
                            <div class="item-nome">${item.nome} x${item.quantidade}</div>
                            <div class="item-detalhes">
                                R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}
                                ${item.observacao ? `<br><small>${item.observacao}</small>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="total">
                    <strong>TOTAL: R$ ${carrinho.reduce((total, item) => total + (item.preco * item.quantidade), 0).toFixed(2).replace('.', ',')}</strong>
                </div>
                
                <div class="footer">
                    Obrigado pela preferência!<br>
                    Volte sempre!
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(cupomHtml);
        printWindow.document.close();
        
        // Aguardar carregamento e imprimir
        printWindow.addEventListener('load', function() {
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                
                // Fechar janela após impressão
                setTimeout(() => {
                    printWindow.close();
                }, 3000);
            }, 500);
        });
    }
    
    scrollToTop() {
        document.querySelector('.mobile-tab-content.active').scrollTop = 0;
    }
    
    showFeedback(message) {
        // Criar toast de feedback
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            z-index: 3000;
            font-size: 14px;
            font-weight: 500;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    }
    
    bindEvents() {
        // Eventos das abas
        document.querySelectorAll('.mobile-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.currentTarget.dataset.tab;
                this.switchTab(tabName);
            });
        });
        
        // Fechar modal ao clicar fora
        document.getElementById('mobile-carrinho-modal').addEventListener('click', (e) => {
            if (e.target.classList.contains('mobile-carrinho-modal')) {
                this.hideCarrinhoModal();
            }
        });
        
        // Busca de produtos em tempo real (igual desktop)
        const searchInput = document.getElementById('mobile-search-produtos');
        console.log('🔍 Input de busca encontrado:', searchInput);
        
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                console.log('🔍 Buscando produtos com query:', query);
                
                // Buscar em tempo real com debounce mínimo (150ms)
                searchTimeout = setTimeout(() => {
                    const categoriaId = document.getElementById('mobile-categoria-filter')?.value || '';
                    console.log('🔍 Executando busca:', { query, categoriaId });
                    this.loadProdutos(query, categoriaId);
                }, 150);
            });
            
            // Também buscar ao pressionar Enter
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    const query = e.target.value.trim();
                    const categoriaId = document.getElementById('mobile-categoria-filter')?.value || '';
                    console.log('🔍 Busca via Enter:', { query, categoriaId });
                    this.loadProdutos(query, categoriaId);
                }
            });
        } else {
            console.error('❌ Input de busca não encontrado!');
        }
        
        // Filtro de categoria
        const categoriaFilter = document.getElementById('mobile-categoria-filter');
        if (categoriaFilter) {
            categoriaFilter.addEventListener('change', (e) => {
                const categoriaId = e.target.value;
                const query = document.getElementById('mobile-search-produtos')?.value.trim() || '';
                this.loadProdutos(query, categoriaId);
            });
        }
    }
    
    loadCategorias() {
        // Carregar categorias para o filtro
        if (window.categoriasData && Array.isArray(window.categoriasData)) {
            const categoriaFilter = document.getElementById('mobile-categoria-filter');
            if (categoriaFilter) {
                const options = window.categoriasData.map(cat => 
                    `<option value="${cat.id}">${cat.nome}</option>`
                ).join('');
                categoriaFilter.innerHTML = '<option value="">Todas as categorias</option>' + options;
            }
        }
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    if (window.innerWidth <= 768) {
        window.mobilePedido = new MobilePedidoInterface();
    }
});

// Re-inicializar ao redimensionar
window.addEventListener('resize', () => {
    if (window.innerWidth <= 768 && !window.mobilePedido) {
        window.mobilePedido = new MobilePedidoInterface();
    } else if (window.innerWidth > 768 && window.mobilePedido) {
        // Remover interface mobile se voltar ao desktop
        const mobileContainer = document.querySelector('.mobile-pedido-container');
        if (mobileContainer) {
            mobileContainer.remove();
        }
        window.mobilePedido = null;
        
        // Mostrar conteúdo original
        const originalContent = document.querySelector('.main-content');
        if (originalContent) {
            originalContent.style.display = 'block';
        }
    }
});
