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
            this.loadData();
            this.bindEvents();
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
                <div class="mobile-produtos-grid" id="mobile-produtos-grid">
                    <!-- Produtos serão carregados aqui -->
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
        
        // Carregar mesas
        console.log('🏢 Carregando mesas...');
        this.loadMesas();
        
        // Carregar produtos
        console.log('🍔 Carregando produtos...');
        this.loadProdutos();
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
    
    async loadProdutos() {
        try {
            console.log('🔄 Carregando produtos...');
            const response = await fetch('api/produtos.php');
            console.log('📡 Resposta da API:', response);
            
            const data = await response.json();
            console.log('📦 Dados recebidos:', data);
            
            this.produtos = data.produtos || [];
            console.log('🍔 Produtos carregados:', this.produtos.length);
            
            this.renderProdutos();
        } catch (error) {
            console.error('❌ Erro ao carregar produtos:', error);
            console.log('🔄 Usando produtos de fallback...');
            
            // Fallback para produtos padrão
            this.produtos = [
                { id: 1, nome: 'Hambúrguer Clássico', preco: 25.90, categoria: 'Lanches' },
                { id: 2, nome: 'Batata Frita', preco: 12.90, categoria: 'Acompanhamentos' },
                { id: 3, nome: 'Refrigerante', preco: 5.90, categoria: 'Bebidas' },
                { id: 4, nome: 'Pizza Margherita', preco: 35.90, categoria: 'Pizzas' },
                { id: 5, nome: 'Salada Caesar', preco: 18.90, categoria: 'Saladas' },
                { id: 6, nome: 'Suco Natural', preco: 8.90, categoria: 'Bebidas' }
            ];
            this.renderProdutos();
        }
    }
    
    renderMesas() {
        const grid = document.getElementById('mobile-mesas-grid');
        if (!grid) return;
        
        grid.innerHTML = this.mesas.map(mesa => `
            <div class="mobile-mesa-card ${mesa.status}" 
                 data-mesa-id="${mesa.id_mesa}" 
                 onclick="mobilePedido.selecionarMesa('${mesa.id_mesa}', '${mesa.nome}')">
                <div class="mobile-mesa-numero">${mesa.nome}</div>
                <div class="mobile-mesa-status">${mesa.status === 'livre' ? 'Livre' : 'Ocupada'}</div>
            </div>
        `).join('');
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
        
        const html = this.produtos.map(produto => `
            <div class="mobile-produto-card" 
                 onclick="mobilePedido.adicionarProduto(${produto.id}, '${produto.nome}', ${produto.preco})">
                <div class="mobile-produto-categoria">${produto.categoria}</div>
                <div class="mobile-produto-nome">${produto.nome}</div>
                <div class="mobile-produto-preco">R$ ${produto.preco.toFixed(2)}</div>
            </div>
        `).join('');
        
        console.log('📝 HTML gerado:', html);
        grid.innerHTML = html;
        console.log('✅ Produtos renderizados com sucesso!');
    }
    
    selecionarMesa(mesaId, mesaNome) {
        this.mesaSelecionada = { id: mesaId, nome: mesaNome };
        
        // Atualizar visual
        document.querySelectorAll('.mobile-mesa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        document.querySelector(`[data-mesa-id="${mesaId}"]`).classList.add('selected');
        
        // Atualizar header
        document.getElementById('mesa-info').textContent = mesaNome;
        
        // Ir para aba de produtos
        this.switchTab('produtos');
    }
    
    adicionarProduto(produtoId, nome, preco) {
        if (!this.mesaSelecionada) {
            alert('Selecione uma mesa primeiro!');
            this.switchTab('mesas');
            return;
        }
        
        const itemExistente = this.carrinho.find(item => item.produtoId === produtoId);
        
        if (itemExistente) {
            itemExistente.quantidade++;
        } else {
            this.carrinho.push({
                produtoId,
                nome,
                preco,
                quantidade: 1
            });
        }
        
        this.updateCarrinho();
        this.showFeedback('Produto adicionado!');
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
        
        const carrinhoHTML = this.carrinho.map(item => `
            <div class="mobile-carrinho-item">
                <div class="mobile-carrinho-item-info">
                    <p class="mobile-carrinho-item-nome">${item.nome}</p>
                    <p class="mobile-carrinho-item-preco">R$ ${(item.preco * item.quantidade).toFixed(2)}</p>
                </div>
                <div class="mobile-carrinho-item-controls">
                    <div class="mobile-carrinho-qty">
                        <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidade(${item.produtoId}, -1)">-</button>
                        <span class="mobile-carrinho-qty-value">${item.quantidade}</span>
                        <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidade(${item.produtoId}, 1)">+</button>
                    </div>
                    <button class="mobile-carrinho-remove" onclick="mobilePedido.removerItem(${item.produtoId})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
        
        content.innerHTML = carrinhoHTML;
        modalContent.innerHTML = carrinhoHTML;
    }
    
    alterarQuantidade(produtoId, delta) {
        const item = this.carrinho.find(item => item.produtoId === produtoId);
        if (!item) return;
        
        item.quantidade += delta;
        
        if (item.quantidade <= 0) {
            this.removerItem(produtoId);
        } else {
            this.updateCarrinho();
        }
    }
    
    removerItem(produtoId) {
        this.carrinho = this.carrinho.filter(item => item.produtoId !== produtoId);
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
            const pedidoData = {
                mesa: this.mesaSelecionada.id,
                itens: this.carrinho
            };
            
            // Integrar com a API existente
            const response = await fetch('api/criar-pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(pedidoData)
            });
            
            if (response.ok) {
                this.showFeedback('Pedido criado com sucesso!');
                this.limparCarrinho();
            } else {
                throw new Error('Erro ao criar pedido');
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao criar pedido. Tente novamente.');
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
