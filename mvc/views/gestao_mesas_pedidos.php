hp
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?view=login');
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4">Gestão de Mesas e Pedidos</h1>
    <p>Gerencie mesas, pedidos antigos e mantenha o sistema sincronizado.</p>
    
    <!-- Cards de Status -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Mesas Ocupadas</h6>
                            <h4 id="mesas_ocupadas">0</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-table fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>Pedidos Antigos</h6>
                            <h4 id="pedidos_antigos">0</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
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
                            <h6>Pedidos Ativos</h6>
                            <h4 id="pedidos_ativos">0</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shopping-cart fa-2x"></i>
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
                            <h6>Valor Total</h6>
                            <h4 id="valor_total">R$ 0,00</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões de Ação -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Ações de Limpeza e Sincronização</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button id="btn_limpar_pedidos_antigos" class="btn btn-warning w-100">
                                <i class="fas fa-broom"></i> Limpar Pedidos Antigos
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button id="btn_sincronizar_mesas" class="btn btn-info w-100">
                                <i class="fas fa-sync"></i> Sincronizar Mesas
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button id="btn_verificar_integridade" class="btn btn-primary w-100">
                                <i class="fas fa-check-circle"></i> Verificar Integridade
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button id="btn_atualizar_status" class="btn btn-success w-100">
                                <i class="fas fa-refresh"></i> Atualizar Status
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Abas de Gestão -->
    <ul class="nav nav-tabs" id="gestaoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="mesas-tab" data-bs-toggle="tab" data-bs-target="#mesas" type="button" role="tab">
                <i class="fas fa-table"></i> Mesas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pedidos-antigos-tab" data-bs-toggle="tab" data-bs-target="#pedidos-antigos" type="button" role="tab">
                <i class="fas fa-clock"></i> Pedidos Antigos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pedidos-ativos-tab" data-bs-toggle="tab" data-bs-target="#pedidos-ativos" type="button" role="tab">
                <i class="fas fa-shopping-cart"></i> Pedidos Ativos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="relatorios-tab" data-bs-toggle="tab" data-bs-target="#relatorios" type="button" role="tab">
                <i class="fas fa-chart-bar"></i> Relatórios
            </button>
        </li>
    </ul>

    <!-- Conteúdo das Abas -->
    <div class="tab-content" id="gestaoTabContent">
        
        <!-- Aba Mesas -->
        <div class="tab-pane fade show active" id="mesas" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Status das Mesas</h5>
                            <button id="btn_atualizar_mesas" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_mesas" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Número</th>
                                            <th>Status</th>
                                            <th>Pedidos Ativos</th>
                                            <th>Última Atividade</th>
                                            <th>Valor Total</th>
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
        
        <!-- Aba Pedidos Antigos -->
        <div class="tab-pane fade" id="pedidos-antigos" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Pedidos Antigos (24h+)</h5>
                            <button id="btn_limpar_todos_antigos" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Limpar Todos
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
        
        <!-- Aba Pedidos Ativos -->
        <div class="tab-pane fade" id="pedidos-ativos" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Pedidos Ativos</h5>
                            <button id="btn_atualizar_pedidos_ativos" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync"></i> Atualizar
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabela_pedidos_ativos" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Mesa</th>
                                            <th>Status</th>
                                            <th>Valor</th>
                                            <th>Cliente</th>
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
        
        <!-- Aba Relatórios -->
        <div class="tab-pane fade" id="relatorios" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Relatórios de Mesas e Pedidos</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="relatorio_data_inicio" class="form-label">Data Início</label>
                                    <input type="date" id="relatorio_data_inicio" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label for="relatorio_data_fim" class="form-label">Data Fim</label>
                                    <input type="date" id="relatorio_data_fim" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button id="btn_gerar_relatorio_mesas" class="btn btn-primary w-100">
                                        <i class="fas fa-chart-bar"></i> Gerar Relatório
                                    </button>
                                </div>
                            </div>
                            
                            <div id="relatorio_mesas_content">
                                <!-- Conteúdo do relatório será carregado aqui -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    carregarStatusGeral();
    carregarMesas();
    carregarPedidosAntigos();
    carregarPedidosAtivos();
    
    // Event listeners
    document.getElementById('btn_limpar_pedidos_antigos').addEventListener('click', limparPedidosAntigos);
    document.getElementById('btn_sincronizar_mesas').addEventListener('click', sincronizarMesas);
    document.getElementById('btn_verificar_integridade').addEventListener('click', verificarIntegridade);
    document.getElementById('btn_atualizar_status').addEventListener('click', atualizarStatus);
    document.getElementById('btn_atualizar_mesas').addEventListener('click', carregarMesas);
    document.getElementById('btn_limpar_todos_antigos').addEventListener('click', limparTodosAntigos);
    document.getElementById('btn_atualizar_pedidos_ativos').addEventListener('click', carregarPedidosAtivos);
    document.getElementById('btn_gerar_relatorio_mesas').addEventListener('click', gerarRelatorioMesas);
    
    // Event delegation para botões dinâmicos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-finalizar-pedido')) {
            const pedidoId = e.target.dataset.pedidoId;
            finalizarPedido(pedidoId);
        }
        
        if (e.target.classList.contains('btn-liberar-mesa')) {
            const mesaId = e.target.dataset.mesaId;
            liberarMesa(mesaId);
        }
        
        if (e.target.classList.contains('btn-forcar-finalizacao')) {
            const pedidoId = e.target.dataset.pedidoId;
            forcarFinalizacao(pedidoId);
        }
    });
    
    // Auto-refresh a cada 30 segundos
    setInterval(function() {
        carregarStatusGeral();
    }, 30000);
});

function carregarStatusGeral() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=status_geral'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('mesas_ocupadas').textContent = data.status.mesas_ocupadas;
            document.getElementById('pedidos_antigos').textContent = data.status.pedidos_antigos;
            document.getElementById('pedidos_ativos').textContent = data.status.pedidos_ativos;
            document.getElementById('valor_total').textContent = 'R$ ' + data.status.valor_total.toFixed(2).replace('.', ',');
        }
    })
    .catch(error => console.error('Erro ao carregar status geral:', error));
}

function carregarMesas() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_mesas'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_mesas tbody');
            tbody.innerHTML = '';
            
            data.mesas.forEach(mesa => {
                const statusClass = mesa.status === 'ocupada' ? 'bg-danger' : 'bg-success';
                const statusText = mesa.status === 'ocupada' ? 'Ocupada' : 'Livre';
                
                const row = `
                    <tr>
                        <td>${mesa.numero}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${mesa.pedidos_ativos}</td>
                        <td>${mesa.ultima_atividade || 'N/A'}</td>
                        <td>R$ ${mesa.valor_total.toFixed(2).replace('.', ',')}</td>
                        <td>
                            ${mesa.status === 'ocupada' ? `
                                <button class="btn btn-warning btn-sm btn-liberar-mesa" data-mesa-id="${mesa.id}">
                                    <i class="fas fa-unlock"></i> Liberar
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar mesas:', error));
}

function carregarPedidosAntigos() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_pedidos_antigos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_pedidos_antigos tbody');
            tbody.innerHTML = '';
            
            data.pedidos.forEach(pedido => {
                const idade = Math.round(pedido.idade_horas);
                const idadeClass = idade > 48 ? 'text-danger' : 'text-warning';
                
                const row = `
                    <tr>
                        <td>${pedido.idpedido}</td>
                        <td>${pedido.mesa_numero}</td>
                        <td><span class="badge bg-warning">${pedido.status}</span></td>
                        <td>R$ ${pedido.valor_total.toFixed(2).replace('.', ',')}</td>
                        <td class="${idadeClass}">${idade}h</td>
                        <td>${formatarData(pedido.created_at)}</td>
                        <td>
                            <button class="btn btn-danger btn-sm btn-forcar-finalizacao" data-pedido-id="${pedido.idpedido}">
                                <i class="fas fa-times"></i> Finalizar
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

function carregarPedidosAtivos() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=listar_pedidos_ativos'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#tabela_pedidos_ativos tbody');
            tbody.innerHTML = '';
            
            data.pedidos.forEach(pedido => {
                const statusClass = getStatusClass(pedido.status);
                
                const row = `
                    <tr>
                        <td>${pedido.idpedido}</td>
                        <td>${pedido.mesa_numero}</td>
                        <td><span class="badge ${statusClass}">${pedido.status}</span></td>
                        <td>R$ ${pedido.valor_total.toFixed(2).replace('.', ',')}</td>
                        <td>${pedido.cliente_nome || 'N/A'}</td>
                        <td>${formatarData(pedido.created_at)}</td>
                        <td>
                            <button class="btn btn-success btn-sm btn-finalizar-pedido" data-pedido-id="${pedido.idpedido}">
                                <i class="fas fa-check"></i> Finalizar
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    })
    .catch(error => console.error('Erro ao carregar pedidos ativos:', error));
}

function limparPedidosAntigos() {
    if (confirm('Tem certeza que deseja limpar todos os pedidos antigos? Esta ação não pode ser desfeita.')) {
        fetch('mvc/ajax/gestao_mesas_pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'acao=limpar_pedidos_antigos'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pedidos antigos limpos com sucesso!');
                carregarStatusGeral();
                carregarPedidosAntigos();
                carregarMesas();
            } else {
                alert('Erro ao limpar pedidos antigos: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao limpar pedidos antigos');
        });
    }
}

function sincronizarMesas() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=sincronizar_mesas'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mesas sincronizadas com sucesso!');
            carregarStatusGeral();
            carregarMesas();
        } else {
            alert('Erro ao sincronizar mesas: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao sincronizar mesas');
    });
}

function verificarIntegridade() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=verificar_integridade'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.inconsistencias.length === 0) {
                alert('✅ Sistema íntegro - Nenhuma inconsistência encontrada!');
            } else {
                let mensagem = '⚠️ Inconsistências encontradas:\n';
                data.inconsistencias.forEach(inc => {
                    mensagem += `- Mesa ${inc.numero}: ${inc.descricao}\n`;
                });
                alert(mensagem);
            }
        } else {
            alert('Erro ao verificar integridade: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao verificar integridade');
    });
}

function atualizarStatus() {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=atualizar_status'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status atualizado com sucesso!');
            carregarStatusGeral();
            carregarMesas();
        } else {
            alert('Erro ao atualizar status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar status');
    });
}

function finalizarPedido(pedidoId) {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=finalizar_pedido&pedido_id=${pedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pedido finalizado com sucesso!');
            carregarStatusGeral();
            carregarPedidosAtivos();
            carregarMesas();
        } else {
            alert('Erro ao finalizar pedido: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao finalizar pedido');
    });
}

function liberarMesa(mesaId) {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=liberar_mesa&mesa_id=${mesaId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mesa liberada com sucesso!');
            carregarStatusGeral();
            carregarMesas();
            carregarPedidosAtivos();
        } else {
            alert('Erro ao liberar mesa: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao liberar mesa');
    });
}

function forcarFinalizacao(pedidoId) {
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=forcar_finalizacao&pedido_id=${pedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pedido finalizado com sucesso!');
            carregarStatusGeral();
            carregarPedidosAntigos();
            carregarMesas();
        } else {
            alert('Erro ao finalizar pedido: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao finalizar pedido');
    });
}

function gerarRelatorioMesas() {
    const dataInicio = document.getElementById('relatorio_data_inicio').value;
    const dataFim = document.getElementById('relatorio_data_fim').value;
    
    const params = new URLSearchParams({
        acao: 'gerar_relatorio_mesas',
        data_inicio: dataInicio,
        data_fim: dataFim
    });
    
    fetch('mvc/ajax/gestao_mesas_pedidos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('relatorio_mesas_content').innerHTML = data.html;
        } else {
            alert('Erro ao gerar relatório: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório');
    });
}

// Funções auxiliares
function getStatusClass(status) {
    switch(status) {
        case 'Pendente': return 'bg-warning';
        case 'Preparando': return 'bg-info';
        case 'Pronto': return 'bg-success';
        case 'Entregue': return 'bg-primary';
        case 'Finalizado': return 'bg-secondary';
        case 'Cancelado': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function formatarData(data) {
    return new Date(data).toLocaleString('pt-BR');
}
</script>
