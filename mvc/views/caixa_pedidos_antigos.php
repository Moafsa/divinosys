<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?view=login');
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4">Caixa - Pedidos Antigos</h1>
    <p>Gerencie pedidos antigos que precisam de atenção do caixa.</p>
    
    <!-- Alertas -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Atenção!</h5>
                <p>Estes pedidos são antigos e precisam ser finalizados pelo caixa. <strong>Não finalize automaticamente!</strong></p>
                <p>Para cada pedido, você deve:</p>
                <ul>
                    <li>Verificar se o cliente pagou</li>
                    <li>Registrar a forma de pagamento (PIX, cartão, dinheiro)</li>
                    <li>Finalizar apenas quando confirmar o pagamento</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Resumo -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Pedidos Antigos</h6>
                            <h4 id="total_pedidos_antigos">0</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Valor Total</h6>
                            <h4 id="valor_total_antigos">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Mesas Afetadas</h6>
                            <h4 id="mesas_afetadas">0</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-table fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Mais Antigo</h6>
                            <h4 id="pedido_mais_antigo">0h</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-hourglass-end fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Pedidos Antigos -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Pedidos Antigos que Precisam de Atenção</h5>
                    <button id="btn_atualizar_pedidos" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-sync"></i> Atualizar
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabela_pedidos_antigos" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Mesa</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Cliente</th>
                                    <th>Idade</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dados carregados via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Finalizar Pedido -->
<div class="modal fade" id="modalFinalizarPedido" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Finalizar Pedido Antigo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formFinalizarPedido">
                    <input type="hidden" id="pedido_id_finalizar" name="pedido_id">
                    
                    <!-- Informações do Pedido -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ID do Pedido</label>
                            <input type="text" id="pedido_id_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mesa</label>
                            <input type="text" id="mesa_display" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Valor Total</label>
                            <input type="text" id="valor_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cliente</label>
                            <input type="text" id="cliente_display" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <!-- Forma de Pagamento -->
                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento *</label>
                        <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao_debito">Cartão Débito</option>
                            <option value="cartao_credito">Cartão Crédito</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>
                    
                    <!-- Valor Pago -->
                    <div class="mb-3">
                        <label for="valor_pago" class="form-label">Valor Pago *</label>
                        <input type="number" id="valor_pago" name="valor_pago" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <!-- Troco -->
                    <div class="mb-3" id="troco_container" style="display: none;">
                        <label for="troco" class="form-label">Troco</label>
                        <input type="number" id="troco" name="troco" class="form-control" step="0.01" min="0" readonly>
                    </div>
                    
                    <!-- Observações -->
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea id="observacoes" name="observacoes" class="form-control" rows="3" 
                                  placeholder="Ex: Cliente pagou em dinheiro, troco de R$ 5,00"></textarea>
                    </div>
                    
                    <!-- Confirmação -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Confirmação</h6>
                        <p>Você confirma que este pedido foi pago e pode ser finalizado?</p>
                        <div class="form-check">
                            <input type="checkbox" id="confirmar_pagamento" class="form-check-input" required>
                            <label for="confirmar_pagamento" class="form-check-label">
                                Sim, confirmo que o pedido foi pago e pode ser finalizado
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_confirmar_finalizacao" class="btn btn-success" disabled>
                    <i class="fas fa-check"></i> Finalizar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    carregarPedidosAntigos();
    
    // Event listeners
    document.getElementById('btn_atualizar_pedidos').addEventListener('click', carregarPedidosAntigos);
    document.getElementById('btn_confirmar_finalizacao').addEventListener('click', finalizarPedido);
    document.getElementById('forma_pagamento').addEventListener('change', function() {
        const trocoContainer = document.getElementById('troco_container');
        if (this.value === 'dinheiro') {
            trocoContainer.style.display = 'block';
        } else {
            trocoContainer.style.display = 'none';
        }
    });
    
    // Event listeners para campos
    document.getElementById('valor_pago').addEventListener('input', calcularTroco);
    document.getElementById('confirmar_pagamento').addEventListener('change', function() {
        const btn = document.getElementById('btn_confirmar_finalizacao');
        btn.disabled = !this.checked;
    });
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-finalizar-pedido')) {
            const pedidoId = e.target.dataset.pedidoId;
            abrirModalFinalizar(pedidoId);
        }
    });
});

function carregarPedidosAntigos() {
    fetch('mvc/ajax/caixa_pedidos_antigos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_pedidos_antigos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar resumo
            document.getElementById('total_pedidos_antigos').textContent = data.resumo.total_pedidos;
            document.getElementById('valor_total_antigos').textContent = 'R$ ' + data.resumo.valor_total.toFixed(2).replace('.', ',');
            document.getElementById('mesas_afetadas').textContent = data.resumo.mesas_afetadas;
            document.getElementById('pedido_mais_antigo').textContent = data.resumo.mais_antigo + 'h';
            
            // Atualizar tabela
            const tbody = document.querySelector('#tabela_pedidos_antigos tbody');
            tbody.innerHTML = '';
            
            data.pedidos.forEach(pedido => {
                const idade = Math.round(pedido.idade_horas);
                const idadeClass = idade > 48 ? 'text-danger' : 'text-warning';
                const statusClass = getStatusClass(pedido.status);
                
                const row = `
                    <tr>
                        <td>${pedido.idpedido}</td>
                        <td>${pedido.mesa_numero}</td>
                        <td><span class="badge ${statusClass}">${pedido.status}</span></td>
                        <td>R$ ${pedido.valor_total.toFixed(2).replace('.', ',')}</td>
                        <td>${pedido.cliente_nome || 'N/A'}</td>
                        <td class="${idadeClass}">${idade}h</td>
                        <td>${formatarData(pedido.created_at)}</td>
                        <td>
                            <button class="btn btn-warning btn-sm btn-finalizar-pedido" data-pedido-id="${pedido.idpedido}">
                                <i class="fas fa-cash-register"></i> Finalizar
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar pedidos antigos:', error));
}

function abrirModalFinalizar(pedidoId) {
    // Buscar dados do pedido
    fetch('mvc/ajax/caixa_pedidos_antigos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=obter_pedido&pedido_id=${pedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const pedido = data.pedido;
            
            // Preencher modal
            document.getElementById('pedido_id_finalizar').value = pedido.idpedido;
            document.getElementById('pedido_id_display').value = pedido.idpedido;
            document.getElementById('mesa_display').value = pedido.mesa_numero;
            document.getElementById('valor_display').value = 'R$ ' + pedido.valor_total.toFixed(2).replace('.', ',');
            document.getElementById('cliente_display').value = pedido.cliente_nome || 'N/A';
            document.getElementById('valor_pago').value = pedido.valor_total;
            
            // Limpar formulário
            document.getElementById('forma_pagamento').value = '';
            document.getElementById('observacoes').value = '';
            document.getElementById('confirmar_pagamento').checked = false;
            document.getElementById('btn_confirmar_finalizacao').disabled = true;
            
            // Mostrar modal
            new bootstrap.Modal(document.getElementById('modalFinalizarPedido')).show();
        }
    })
    .catch(error => {
        console.error('Erro ao obter pedido:', error);
        alert('Erro ao carregar dados do pedido');
    });
}

function calcularTroco() {
    const valorTotal = parseFloat(document.getElementById('valor_display').value.replace('R$ ', '').replace(',', '.')) || 0;
    const valorPago = parseFloat(document.getElementById('valor_pago').value) || 0;
    const troco = valorPago - valorTotal;
    
    document.getElementById('troco').value = Math.max(0, troco).toFixed(2);
}

function finalizarPedido() {
    const formData = new FormData(document.getElementById('formFinalizarPedido'));
    formData.append('acao', 'finalizar_pedido_antigo');
    
    fetch('mvc/ajax/caixa_pedidos_antigos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pedido finalizado com sucesso!');
            bootstrap.Modal.getInstance(document.getElementById('modalFinalizarPedido')).hide();
            carregarPedidosAntigos();
        } else {
            alert('Erro ao finalizar pedido: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao finalizar pedido');
    });
}

// Funções auxiliares
function getStatusClass(status) {
    switch(status) {
        case 'Pendente': return 'bg-warning';
        case 'Preparando': return 'bg-info';
        case 'Pronto': return 'bg-success';
        case 'Entregue': return 'bg-primary';
        default: return 'bg-secondary';
    }
}

function formatarData(data) {
    return new Date(data).toLocaleString('pt-BR');
}
</script>
