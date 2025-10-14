/**
 * Partial Payment Management
 * Handles partial payment interface for orders
 */

class PartialPaymentManager {
    constructor() {
        this.currentOrderId = null;
        this.currentOrderData = null;
    }

    /**
     * Open payment modal for an order
     */
    async openPaymentModal(pedidoId) {
        this.currentOrderId = pedidoId;
        
        try {
            // Fetch order balance information
            const response = await fetch('mvc/ajax/pagamentos_parciais.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=consultar_saldo_pedido&pedido_id=${pedidoId}`
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch order data');
            }
            
            this.currentOrderData = data.pedido;
            this.showPaymentModal(data);
            
        } catch (error) {
            console.error('Error fetching order data:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Failed to load order information'
            });
        }
    }

    /**
     * Show payment modal with order details
     */
    showPaymentModal(data) {
        const pedido = data.pedido;
        const pagamentos = data.pagamentos || [];
        
        const valorTotal = parseFloat(pedido.valor_total);
        const valorPago = parseFloat(pedido.valor_pago || 0);
        const saldoDevedor = parseFloat(pedido.saldo_devedor || valorTotal);
        
        // Build payment history HTML
        let historyHtml = '';
        if (pagamentos.length > 0) {
            historyHtml = `
                <div class="payment-history mb-3">
                    <h6 class="mb-2">Histórico de Pagamentos:</h6>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Forma</th>
                                    <th>Valor</th>
                                    <th>Cliente</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${pagamentos.map(p => `
                                    <tr>
                                        <td>${this.formatDateTime(p.created_at)}</td>
                                        <td>${p.forma_pagamento}</td>
                                        <td class="text-end"><strong>R$ ${parseFloat(p.valor_pago).toFixed(2)}</strong></td>
                                        <td>${p.nome_cliente || '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        // Build modal HTML
        const modalHtml = `
            <div class="payment-summary mb-3">
                <div class="row text-center">
                    <div class="col-4">
                        <small class="text-muted">Valor Total</small>
                        <h5 class="mb-0">R$ ${valorTotal.toFixed(2)}</h5>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Já Pago</small>
                        <h5 class="mb-0 text-success">R$ ${valorPago.toFixed(2)}</h5>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Saldo Devedor</small>
                        <h5 class="mb-0 text-danger">R$ ${saldoDevedor.toFixed(2)}</h5>
                    </div>
                </div>
                ${saldoDevedor > 0 ? `
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: ${(valorPago / valorTotal * 100)}%" 
                             aria-valuenow="${valorPago}" 
                             aria-valuemin="0" 
                             aria-valuemax="${valorTotal}">
                        </div>
                    </div>
                ` : ''}
            </div>
            
            ${historyHtml}
            
            ${saldoDevedor > 0 ? `
                <div class="payment-form">
                    <hr class="my-3">
                    <h6 class="mb-3">Registrar Novo Pagamento:</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                        <select class="form-select" id="formaPagamento" required>
                            <option value="">Selecione...</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão de Débito">Cartão de Débito</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="PIX">PIX</option>
                            <option value="Vale Refeição">Vale Refeição</option>
                            <option value="Fiado">Fiado</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Valor a Pagar <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valorPago" 
                                   step="0.01" min="0.01" max="${saldoDevedor}" 
                                   placeholder="0.00" required>
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="partialPaymentManager.fillRemainingValue(${saldoDevedor})">
                                Valor Total
                            </button>
                        </div>
                        <small class="text-muted">Máximo: R$ ${saldoDevedor.toFixed(2)}</small>
                    </div>
                    
                    <div class="mb-3" id="dinheiroFields" style="display: none;">
                        <label class="form-label">Troco para (se dinheiro)</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="trocoPara" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        <small class="text-muted" id="trocoInfo"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome do Cliente</label>
                        <input type="text" class="form-control" id="nomeCliente" 
                               value="${pedido.cliente || ''}" placeholder="Nome do cliente">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Telefone do Cliente</label>
                        <input type="tel" class="form-control" id="telefoneCliente" 
                               value="${pedido.telefone_cliente || ''}" 
                               placeholder="(00) 00000-0000">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição / Observações</label>
                        <textarea class="form-control" id="descricaoPagamento" 
                                  rows="2" placeholder="Observações adicionais..."></textarea>
                    </div>
                </div>
            ` : `
                <div class="alert alert-success text-center mb-0">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h6 class="mb-0">Pedido Totalmente Quitado!</h6>
                </div>
            `}
        `;
        
        Swal.fire({
            title: saldoDevedor > 0 ? 'Pagamento do Pedido' : 'Pedido Quitado',
            html: modalHtml,
            width: '600px',
            showCancelButton: saldoDevedor > 0,
            confirmButtonText: saldoDevedor > 0 ? 'Confirmar Pagamento' : 'Fechar',
            cancelButtonText: 'Cancelar',
            showCloseButton: true,
            allowOutsideClick: false,
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-secondary'
            },
            didOpen: () => {
                // Add event listeners
                const formaPagamento = document.getElementById('formaPagamento');
                const valorPago = document.getElementById('valorPago');
                const trocoPara = document.getElementById('trocoPara');
                
                if (formaPagamento) {
                    formaPagamento.addEventListener('change', () => {
                        const dinheiroFields = document.getElementById('dinheiroFields');
                        if (formaPagamento.value === 'Dinheiro') {
                            dinheiroFields.style.display = 'block';
                        } else {
                            dinheiroFields.style.display = 'none';
                            if (trocoPara) trocoPara.value = '';
                        }
                    });
                }
                
                if (valorPago && trocoPara) {
                    const updateTrocoInfo = () => {
                        const valor = parseFloat(valorPago.value) || 0;
                        const troco = parseFloat(trocoPara.value) || 0;
                        const trocoInfo = document.getElementById('trocoInfo');
                        
                        if (troco > 0 && valor > 0) {
                            const trocoDevolver = troco - valor;
                            if (trocoDevolver >= 0) {
                                trocoInfo.innerHTML = `<span class="text-success">Troco a devolver: R$ ${trocoDevolver.toFixed(2)}</span>`;
                            } else {
                                trocoInfo.innerHTML = `<span class="text-danger">Valor insuficiente!</span>`;
                            }
                        } else {
                            trocoInfo.innerHTML = '';
                        }
                    };
                    
                    valorPago.addEventListener('input', updateTrocoInfo);
                    trocoPara.addEventListener('input', updateTrocoInfo);
                }
            },
            preConfirm: () => {
                if (saldoDevedor <= 0) return true;
                
                const formaPagamento = document.getElementById('formaPagamento').value;
                const valorPago = parseFloat(document.getElementById('valorPago').value) || 0;
                const nomeCliente = document.getElementById('nomeCliente').value.trim();
                const telefoneCliente = document.getElementById('telefoneCliente').value.trim();
                const descricao = document.getElementById('descricaoPagamento').value.trim();
                const trocoPara = parseFloat(document.getElementById('trocoPara').value) || 0;
                
                // Validation
                if (!formaPagamento) {
                    Swal.showValidationMessage('Selecione a forma de pagamento');
                    return false;
                }
                
                if (valorPago <= 0) {
                    Swal.showValidationMessage('Informe o valor a pagar');
                    return false;
                }
                
                if (valorPago > saldoDevedor) {
                    Swal.showValidationMessage(`Valor não pode ser maior que o saldo devedor (R$ ${saldoDevedor.toFixed(2)})`);
                    return false;
                }
                
                if (formaPagamento === 'Dinheiro' && trocoPara > 0 && trocoPara < valorPago) {
                    Swal.showValidationMessage('Valor do troco deve ser maior ou igual ao valor a pagar');
                    return false;
                }
                
                return {
                    formaPagamento,
                    valorPago,
                    nomeCliente,
                    telefoneCliente,
                    descricao,
                    trocoPara
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value !== true) {
                this.processPayment(result.value);
            }
        });
    }

    /**
     * Process the payment
     */
    async processPayment(paymentData) {
        try {
            // Show loading
            Swal.fire({
                title: 'Processando pagamento...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Prepare form data
            const formData = new URLSearchParams();
            formData.append('action', 'registrar_pagamento_parcial');
            formData.append('pedido_id', this.currentOrderId);
            formData.append('valor_pago', paymentData.valorPago);
            formData.append('forma_pagamento', paymentData.formaPagamento);
            formData.append('nome_cliente', paymentData.nomeCliente);
            formData.append('telefone_cliente', paymentData.telefoneCliente);
            formData.append('descricao', paymentData.descricao);
            formData.append('troco_para', paymentData.trocoPara || 0);
            
            // Send request
            const response = await fetch('mvc/ajax/pagamentos_parciais.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to process payment');
            }
            
            // Show success message
            const mensagem = data.pedido_fechado 
                ? `
                    <div class="alert alert-success mb-0">
                        <h5><i class="fas fa-check-circle"></i> Pedido Quitado!</h5>
                        <p class="mb-0">O pedido foi totalmente pago e fechado com sucesso.</p>
                        ${data.troco_devolver > 0 ? `<p class="mb-0 mt-2"><strong>Troco: R$ ${data.troco_devolver.toFixed(2)}</strong></p>` : ''}
                    </div>
                `
                : `
                    <div class="alert alert-info mb-0">
                        <h5><i class="fas fa-info-circle"></i> Pagamento Registrado!</h5>
                        <p class="mb-2">Valor pago: <strong>R$ ${data.valor_pago.toFixed(2)}</strong></p>
                        <p class="mb-0">Saldo restante: <strong>R$ ${data.saldo_devedor.toFixed(2)}</strong></p>
                        ${data.troco_devolver > 0 ? `<p class="mb-0 mt-2"><strong>Troco: R$ ${data.troco_devolver.toFixed(2)}</strong></p>` : ''}
                    </div>
                `;
            
            await Swal.fire({
                icon: data.pedido_fechado ? 'success' : 'info',
                title: data.pedido_fechado ? 'Pedido Quitado!' : 'Pagamento Registrado!',
                html: mensagem,
                confirmButtonText: 'OK'
            });
            
            // Reload page or update UI
            if (typeof reloadDashboard === 'function') {
                reloadDashboard();
            } else if (typeof loadPedidos === 'function') {
                loadPedidos();
            } else {
                location.reload();
            }
            
        } catch (error) {
            console.error('Error processing payment:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Failed to process payment'
            });
        }
    }

    /**
     * Fill the remaining value
     */
    fillRemainingValue(value) {
        const valorPago = document.getElementById('valorPago');
        if (valorPago) {
            valorPago.value = value.toFixed(2);
            valorPago.dispatchEvent(new Event('input'));
        }
    }

    /**
     * Format date time
     */
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Create global instance
const partialPaymentManager = new PartialPaymentManager();

// Helper function to open payment modal (for backward compatibility)
function abrirModalPagamento(pedidoId) {
    partialPaymentManager.openPaymentModal(pedidoId);
}

