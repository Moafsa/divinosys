<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração WhatsApp - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .instance-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        .status-connected { color: #28a745; }
        .status-disconnected { color: #dc3545; }
        .status-qrcode { color: #ffc107; }
        .qr-code {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fab fa-whatsapp text-success"></i>
                    Configuração WhatsApp
                </h2>
                
                <!-- Lista de Instâncias -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Instâncias WhatsApp</h5>
                        <button class="btn btn-primary" onclick="abrirModalNovaInstancia()">
                            <i class="fas fa-plus"></i> Nova Instância
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="instanciasList">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Instância -->
    <div class="modal fade" id="modalNovaInstancia" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Instância WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaInstancia">
                        <div class="mb-3">
                            <label for="instanceName" class="form-label">Nome da Instância</label>
                            <input type="text" class="form-control" id="instanceName" required>
                        </div>
                        <div class="mb-3">
                            <label for="phoneNumber" class="form-label">Número do WhatsApp</label>
                            <input type="text" class="form-control" id="phoneNumber" placeholder="5511999999999" required>
                        </div>
                        <div class="mb-3">
                            <label for="n8nWebhook" class="form-label">Webhook n8n (Opcional)</label>
                            <input type="url" class="form-control" id="n8nWebhook" placeholder="https://seu-n8n.com/webhook/whatsapp">
                            <div class="form-text">Configure apenas se quiser usar assistente IA</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="criarInstancia()">Criar Instância</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal QR Code -->
    <div class="modal fade" id="modalQRCode" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conectar WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Escaneie o QR Code com seu WhatsApp:</p>
                    <div id="qrCodeDisplay" class="qr-code"></div>
                    <div id="qrStatus" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Carregar instâncias ao abrir a página
        document.addEventListener('DOMContentLoaded', function() {
            carregarInstancias();
        });

        // Carregar instâncias
        function carregarInstancias() {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=listar_instancias'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    exibirInstancias(data.instances);
                } else {
                    document.getElementById('instanciasList').innerHTML = 
                        '<div class="alert alert-danger">Erro ao carregar instâncias: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('instanciasList').innerHTML = 
                    '<div class="alert alert-danger">Erro ao carregar instâncias: ' + error.message + '</div>';
            });
        }

        // Exibir instâncias
        function exibirInstancias(instances) {
            const container = document.getElementById('instanciasList');
            
            if (instances.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhuma instância encontrada</p>';
                return;
            }

            let html = '';
            instances.forEach(instance => {
                let statusClass = 'status-disconnected';
                let statusText = 'Desconectado';
                
                switch(instance.status) {
                    case 'connected':
                        statusClass = 'status-connected';
                        statusText = 'Conectado';
                        break;
                    case 'qrcode':
                        statusClass = 'status-qrcode';
                        statusText = 'Aguardando QR Code';
                        break;
                    case 'error':
                        statusClass = 'status-disconnected';
                        statusText = 'Erro';
                        break;
                }

                html += `
                    <div class="instance-card">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h6 class="mb-1">${instance.instance_name}</h6>
                                <small class="text-muted">${instance.phone_number}</small>
                            </div>
                            <div class="col-md-2">
                                <span class="${statusClass}">
                                    <i class="fas fa-circle"></i> ${statusText}
                                </span>
                            </div>
                            <div class="col-md-6 text-end">
                                ${instance.status === 'connected' ? 
                                    '<button class="btn btn-sm btn-outline-danger me-2" onclick="desconectarInstancia(' + instance.id + ')">Desconectar</button>' :
                                    '<button class="btn btn-sm btn-outline-primary me-2" onclick="conectarInstancia(' + instance.id + ')">Conectar</button>'
                                }
                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="testarInstancia(${instance.id})">Testar</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletarInstancia(${instance.id})">Deletar</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Abrir modal nova instância
        function abrirModalNovaInstancia() {
            document.getElementById('formNovaInstancia').reset();
            new bootstrap.Modal(document.getElementById('modalNovaInstancia')).show();
        }

        // Criar instância
        function criarInstancia() {
            const formData = {
                instance_name: document.getElementById('instanceName').value,
                phone_number: document.getElementById('phoneNumber').value,
                n8n_webhook: document.getElementById('n8nWebhook').value
            };

            if (!formData.instance_name || !formData.phone_number) {
                Swal.fire('Erro', 'Nome da instância e número são obrigatórios', 'error');
                return;
            }

            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=criar_instancia&' + new URLSearchParams(formData).toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNovaInstancia')).hide();
                    carregarInstancias();
                } else {
                    Swal.fire('Erro', data.error, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao criar instância: ' + error.message, 'error');
            });
        }

        // Conectar instância
        function conectarInstancia(instanceId) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'action=conectar_instancia&instance_id=' + instanceId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar QR Code
                    document.getElementById('qrCodeDisplay').textContent = data.qr_code;
                    document.getElementById('qrStatus').innerHTML = 
                        '<div class="alert alert-info">Escaneie o QR Code com seu WhatsApp</div>';
                    new bootstrap.Modal(document.getElementById('modalQRCode')).show();
                    
                    // Atualizar status
                    carregarInstancias();
                } else {
                    Swal.fire('Erro', data.error, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro', 'Erro ao conectar instância: ' + error.message, 'error');
            });
        }

        // Desconectar instância
        function desconectarInstancia(instanceId) {
            Swal.fire({
                title: 'Desconectar Instância',
                text: 'Tem certeza que deseja desconectar esta instância?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desconectar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: 'action=desconectar_instancia&instance_id=' + instanceId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            carregarInstancias();
                        } else {
                            Swal.fire('Erro', data.error, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Erro', 'Erro ao desconectar instância: ' + error.message, 'error');
                    });
                }
            });
        }

        // Deletar instância
        function deletarInstancia(instanceId) {
            Swal.fire({
                title: 'Deletar Instância',
                text: 'Tem certeza que deseja deletar esta instância? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, deletar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: 'action=deletar_instancia&instance_id=' + instanceId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', data.message, 'success');
                            carregarInstancias();
                        } else {
                            Swal.fire('Erro', data.error, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Erro', 'Erro ao deletar instância: ' + error.message, 'error');
                    });
                }
            });
        }

        // Testar instância
        function testarInstancia(instanceId) {
            Swal.fire({
                title: 'Testar Instância',
                text: 'Digite o número para testar (formato: 5511999999999)',
                input: 'text',
                inputPlaceholder: '5511999999999',
                showCancelButton: true,
                confirmButtonText: 'Enviar Teste',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const message = 'Teste de conexão - Divino Lanches';
                    
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: `action=enviar_mensagem&instance_id=${instanceId}&to=${result.value}&message=${encodeURIComponent(message)}&source=system`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Mensagem de teste enviada!', 'success');
                        } else {
                            Swal.fire('Erro', data.error, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Erro', 'Erro ao enviar teste: ' + error.message, 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>
