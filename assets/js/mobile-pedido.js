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
        
        // Usar dados PHP diretamente (mesmo método da página desktop)
        if (window.produtosData) {
            console.log('🍔 Usando dados PHP para produtos:', window.produtosData.length);
            this.produtos = window.produtosData;
            this.renderProdutos();
        } else {
            console.log('🔄 Dados PHP não encontrados, carregando via API...');
            this.loadProdutos();
        }
        
        if (window.mesasData) {
            console.log('🏢 Usando dados PHP para mesas:', window.mesasData.length);
            this.mesas = window.mesasData;
            this.renderMesas();
        } else {
            console.log('🔄 Dados PHP não encontrados, carregando via API...');
            this.loadMesas();
        }
        
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
    
    async loadProdutos() {
        try {
            console.log('🔄 Carregando produtos...');
            
            // Tentar buscar produtos da página desktop (mesmo método)
            const response = await fetch('index.php?action=produtos&buscar_todos=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('📡 Resposta da API:', response);
            
            if (response.ok) {
                const data = await response.json();
                console.log('📦 Dados recebidos:', data);
                
                this.produtos = data.produtos || [];
                console.log('🍔 Produtos carregados:', this.produtos.length);
            } else {
                throw new Error('Erro na resposta da API');
            }
            
            this.renderProdutos();
        } catch (error) {
            console.error('❌ Erro ao carregar produtos:', error);
            console.log('🔄 Tentando método alternativo...');
            
            // Tentar método alternativo - buscar da página atual
            try {
                const response = await fetch(window.location.href);
                const html = await response.text();
                
                // Extrair produtos do HTML (mesmo que a página desktop)
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const produtoItems = doc.querySelectorAll('.produto-item');
                
                this.produtos = Array.from(produtoItems).map(item => {
                    const nome = item.querySelector('.produto-nome')?.textContent || 'Produto';
                    const preco = item.querySelector('.produto-preco')?.textContent || '0,00';
                    const categoria = item.querySelector('.produto-categoria')?.textContent || 'Geral';
                    const id = item.getAttribute('data-produto-id') || Math.random();
                    
                    return {
                        id: id,
                        nome: nome,
                        preco: parseFloat(preco.replace('R$', '').replace(',', '.')),
                        categoria: categoria
                    };
                });
                
                console.log('🍔 Produtos extraídos do HTML:', this.produtos.length);
                this.renderProdutos();
                
            } catch (fallbackError) {
                console.error('❌ Erro no fallback:', fallbackError);
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
        
        const html = this.produtos.map(produto => {
            // Verificar e corrigir dados do produto - usar preco_normal
            const preco = produto.preco_normal || produto.preco || produto.valor || 0;
            const nome = produto.nome || 'Produto';
            const categoria = produto.categoria || produto.categoria_nome || 'Geral';
            const id = produto.id || Math.random();
            const ingredientes = produto.ingredientes || [];
            
            console.log('🔍 Produto:', { id, nome, preco, categoria, ingredientes });
            
            return `
                <div class="mobile-produto-card">
                    <div class="mobile-produto-categoria">${categoria}</div>
                    <div class="mobile-produto-nome">${nome}</div>
                    <div class="mobile-produto-preco">R$ ${parseFloat(preco).toFixed(2)}</div>
                    <div class="mobile-produto-botoes">
                        <button class="mobile-btn-personalizar" onclick="mobilePedido.personalizarProduto(${id}, '${nome}', ${preco}, ${JSON.stringify(ingredientes).replace(/"/g, '&quot;')})">
                            <i class="fas fa-cog"></i> Personalizar
                        </button>
                        <button class="mobile-btn-adicionar" onclick="mobilePedido.adicionarProdutoRapido(${id}, '${nome}', ${preco})">
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
        // Verificar se a mesa está ocupada
        const mesaCard = document.querySelector(`[data-mesa-id="${mesaId}"]`);
        if (mesaCard && mesaCard.classList.contains('ocupada')) {
            alert('Esta mesa está ocupada! Selecione uma mesa livre.');
            return;
        }
        
        this.mesaSelecionada = { id: mesaId, nome: mesaNome };
        
        // Atualizar visual
        document.querySelectorAll('.mobile-mesa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        if (mesaCard) {
            mesaCard.classList.add('selected');
        }
        
        // Atualizar header
        document.getElementById('mesa-info').textContent = mesaNome;
        
        // Ir para aba de produtos
        this.switchTab('produtos');
    }
    
    personalizarProduto(produtoId, nome, preco, ingredientes) {
        if (!this.mesaSelecionada) {
            alert('Selecione uma mesa primeiro!');
            this.switchTab('mesas');
            return;
        }
        
        // Mostrar modal de personalização com ingredientes reais
        this.showPersonalizacaoModal(produtoId, nome, preco, ingredientes);
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
    
    showPersonalizacaoModal(produtoId, nome, preco, ingredientes) {
        // Usar TODOS os ingredientes disponíveis (igual desktop)
        const todosIngredientes = window.todosIngredientes || [];
        console.log('🍔 Ingredientes do produto:', ingredientes);
        console.log('🍔 Todos os ingredientes disponíveis:', todosIngredientes);
        
        if (!todosIngredientes || todosIngredientes.length === 0) {
            // Se não houver ingredientes, mostrar mensagem
            alert('Não há ingredientes disponíveis para personalizar.');
            return;
        }
        
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
        const ingredientesHTML = todosIngredientes.map(ing => {
            // Verificar se este ingrediente já está no produto
            const jaEstaNoProduto = ingredientes.some(ingProduto => ingProduto.id === ing.id);
            const precoAdicional = parseFloat(ing.preco_adicional || 0);
            
            console.log(`🔍 Inicializando ingrediente: ${ing.nome}`, {
                id: ing.id,
                jaEstaNoProduto,
                ingredientesDoProduto: ingredientes.map(i => ({ id: i.id, nome: i.nome }))
            });
            
            return `
                <div class="mobile-ingrediente-item" 
                     data-ingrediente-id="${ing.id}" 
                     data-ingrediente-nome="${ing.nome}"
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
                    <p style="margin: 0; color: #666; font-size: 16px;">R$ ${parseFloat(preco).toFixed(2)}</p>
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
        
        // Capturar apenas as modificações (ingredientes que mudaram)
        const modificacoes = [];
        const ingredientesItems = modal.querySelectorAll('.mobile-ingrediente-item');
        
        // Buscar ingredientes originais do produto
        const produtoOriginal = this.produtos.find(p => p.id === produtoId);
        const ingredientesOriginais = produtoOriginal?.ingredientes || [];
        
        ingredientesItems.forEach(item => {
            const ingredienteId = parseInt(item.dataset.ingredienteId);
            const ingredienteNome = item.dataset.ingredienteNome;
            const jaEstava = item.dataset.jaEstava === 'true';
            
            // Verificar estado atual (COM ou SEM) - remover quebras de linha
            const tipoDiv = item.querySelector('.mobile-ingrediente-tipo');
            const atualmenteCom = tipoDiv.textContent.trim() === 'COM';
            
            console.log(`🔍 Verificando modificação: ${ingredienteNome}`, {
                ingredienteId,
                jaEstava,
                atualmenteCom,
                mudou: jaEstava !== atualmenteCom,
                tipoDivText: tipoDiv.textContent
            });
            
            // Só adicionar modificação se mudou de estado
            if (jaEstava !== atualmenteCom) {
                if (atualmenteCom) {
                    // Estava SEM, agora está COM = adicionado
                    modificacoes.push(`+ ${ingredienteNome}`);
                    console.log(`✅ Adicionado: + ${ingredienteNome}`);
                } else {
                    // Estava COM, agora está SEM = removido
                    modificacoes.push(`- ${ingredienteNome}`);
                    console.log(`❌ Removido: - ${ingredienteNome}`);
                }
            } else {
                console.log(`➡️ Sem mudança: ${ingredienteNome} (${jaEstava ? 'estava COM' : 'estava SEM'}, continua ${atualmenteCom ? 'COM' : 'SEM'})`);
            }
        });
        
        console.log('📝 Modificações finais:', modificacoes);
        
        // Se não houve modificações, não adicionar observações vazias
        const observacoesCompletas = [
            ...modificacoes,
            observacoes
        ].filter(obs => obs.trim()).join(' | ');
        
        const itemExistente = this.carrinho.find(item => 
            item.id === produtoId && item.observacao === observacoesCompletas
        );
        
        if (itemExistente) {
            itemExistente.quantidade += quantidade;
        } else {
            this.carrinho.push({
                id: produtoId,  // Usar 'id' como o desktop
                nome,
                preco,
                quantidade,
                observacao: observacoesCompletas  // Usar 'observacao' como o desktop
            });
        }
        
        this.updateCarrinho();
        this.showFeedback('Produto personalizado adicionado!');
        modal.remove();
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
        
        const carrinhoHTML = this.carrinho.map(item => {
            // Separar modificações das observações
            const observacoes = item.observacao || '';
            const modificacoes = observacoes.split(' | ').filter(obs => obs.trim() && (obs.startsWith('+ ') || obs.startsWith('- ')));
            const obsAdicionais = observacoes.split(' | ').filter(obs => obs.trim() && !obs.startsWith('+ ') && !obs.startsWith('- '));
            
            return `
                <div class="mobile-carrinho-item">
                    <div class="mobile-carrinho-item-info">
                        <p class="mobile-carrinho-item-nome">${item.nome}</p>
                        ${modificacoes.length > 0 ? `
                            <div style="font-size: 12px; margin: 2px 0;">
                                ${modificacoes.map(mod => `
                                    <span style="display: inline-block; margin: 1px 2px; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; ${mod.startsWith('+') ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;'}">
                                        ${mod}
                                    </span>
                                `).join('')}
                            </div>
                        ` : ''}
                        ${obsAdicionais.length > 0 ? `<p style="font-size: 12px; color: #666; margin: 2px 0;">${obsAdicionais.join(' | ')}</p>` : ''}
                        <p class="mobile-carrinho-item-preco">R$ ${(item.preco * item.quantidade).toFixed(2)}</p>
                    </div>
                    <div class="mobile-carrinho-item-controls">
                        <div class="mobile-carrinho-qty">
                            <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidade(${item.id}, -1)">-</button>
                            <span class="mobile-carrinho-qty-value">${item.quantidade}</span>
                            <button class="mobile-carrinho-btn-qty" onclick="mobilePedido.alterarQuantidade(${item.id}, 1)">+</button>
                        </div>
                        <button class="mobile-carrinho-remove" onclick="mobilePedido.removerItem(${item.id})">
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
    
    removerItem(produtoId) {
        this.carrinho = this.carrinho.filter(item => item.id !== produtoId);
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
            
            // Usar a mesma API do desktop
            const formData = new URLSearchParams();
            formData.append('action', 'criar_pedido');
            formData.append('mesa_id', this.mesaSelecionada.id);
            formData.append('itens', JSON.stringify(this.carrinho));
            formData.append('observacao', document.getElementById('observacaoPedido')?.value || '');
            
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
                this.limparCarrinho();
                
                // Perguntar se quer imprimir
                if (confirm('Pedido criado! Deseja imprimir o cupom?')) {
                    this.imprimirPedido(result.pedido_id, this.mesaSelecionada, this.carrinho);
                }
            } else {
                throw new Error(result.error || 'Erro ao criar pedido');
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
                        font-size: 14px; 
                        margin: 10px; 
                        padding: 10px;
                        line-height: 1.4;
                    }
                    .header { 
                        text-align: center; 
                        border-bottom: 2px solid #000; 
                        padding-bottom: 10px; 
                        margin-bottom: 15px; 
                    }
                    .empresa { 
                        font-weight: bold; 
                        font-size: 18px; 
                        margin-bottom: 5px;
                    }
                    .pedido-info { 
                        margin: 10px 0; 
                        font-size: 14px;
                        font-weight: bold;
                    }
                    .item { 
                        margin: 5px 0; 
                        padding: 3px 0;
                        border-bottom: 1px dotted #ccc;
                    }
                    .item-nome { 
                        font-weight: bold; 
                        font-size: 16px; 
                    }
                    .item-detalhes { 
                        font-size: 14px; 
                        margin-left: 10px; 
                        margin-top: 3px;
                    }
                    .total { 
                        border-top: 2px solid #000; 
                        padding-top: 10px; 
                        margin-top: 15px; 
                        font-weight: bold; 
                        font-size: 16px;
                        text-align: center;
                    }
                    .footer { 
                        text-align: center; 
                        margin-top: 20px; 
                        font-size: 12px; 
                        font-weight: bold;
                    }
                    @media print { 
                        body { margin: 0; padding: 5px; }
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
                    Data: ${new Date().toLocaleString('pt-BR')}
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
