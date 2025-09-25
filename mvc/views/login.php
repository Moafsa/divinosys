<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .logo h2 {
            color: #333;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .phone-input {
            position: relative;
        }
        
        .phone-input .form-control {
            padding-left: 50px;
        }
        
        .phone-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .estabelecimento-select {
            display: none;
        }
        
        .estabelecimento-select.show {
            display: block;
        }
        
        .estabelecimento-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .estabelecimento-card:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        
        .estabelecimento-card.selected {
            border-color: #667eea;
            background-color: #667eea;
            color: white;
        }
        
        .estabelecimento-card h6 {
            margin: 0;
            font-weight: 600;
        }
        
        .estabelecimento-card small {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-utensils"></i>
            <h2>Divino Lanches</h2>
            <p class="text-muted">Sistema de Gestão</p>
        </div>
        
        <div class="text-center mb-3">
            <a href="index.php?view=login_admin" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-user-shield"></i> Login Administrativo
            </a>
        </div>
        
        <div id="loginForm">
            <form id="formLogin">
                <div class="mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="phone-input">
                        <i class="fas fa-phone"></i>
                        <input type="tel" class="form-control" id="telefone" name="telefone" 
                               placeholder="(11) 99999-9999" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login" id="btnLogin">
                    <span class="btn-text">Solicitar Login</span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin"></i> Enviando...
                    </span>
                </button>
            </form>
        </div>
        
        <div id="estabelecimentoSelect" class="estabelecimento-select">
            <h5 class="mb-3">Selecione o Estabelecimento</h5>
            <div id="estabelecimentosList"></div>
            <button type="button" class="btn btn-login mt-3" id="btnEntrar" disabled>
                <span class="btn-text">Entrar</span>
                <span class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Entrando...
                </span>
            </button>
        </div>
        
        <div id="alertContainer"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let tokenAtual = null;
        let estabelecimentos = [];
        let estabelecimentoSelecionado = null;

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 2) {
                    value = value;
                } else if (value.length <= 7) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                }
                e.target.value = value;
            }
        });

        // Formulário de login
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const telefone = document.getElementById('telefone').value.replace(/\D/g, '');
            
            if (telefone.length < 10) {
                showAlert('Por favor, insira um telefone válido', 'warning');
                return;
            }
            
            solicitarLogin(telefone);
        });

        function solicitarLogin(telefone) {
            const btnLogin = document.getElementById('btnLogin');
            const btnText = btnLogin.querySelector('.btn-text');
            const loading = btnLogin.querySelector('.loading');
            
            btnLogin.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('mvc/ajax/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=solicitar_login&telefone=${telefone}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Link de login enviado para seu WhatsApp!', 'success');
                    
                    // Simular envio para n8n (em produção, isso seria feito pelo n8n)
                    console.log('Dados para n8n:', data.data);
                    
                    // Verificar token automaticamente
                    verificarToken(data.data.login_url);
                } else {
                    showAlert(data.message || 'Erro ao solicitar login', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao solicitar login', 'error');
            })
            .finally(() => {
                btnLogin.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            });
        }

        function verificarToken(loginUrl) {
            const url = new URL(loginUrl);
            const token = url.searchParams.get('token');
            
            if (!token) return;
            
            tokenAtual = token;
            
            // Verificar token a cada 2 segundos
            const interval = setInterval(() => {
                fetch(`mvc/ajax/auth.php?action=validar_token&token=${token}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        clearInterval(interval);
                        estabelecimentos = data.estabelecimentos;
                        mostrarEstabelecimentos();
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar token:', error);
                });
            }, 2000);
            
            // Parar verificação após 5 minutos
            setTimeout(() => {
                clearInterval(interval);
            }, 300000);
        }

        function mostrarEstabelecimentos() {
            const loginForm = document.getElementById('loginForm');
            const estabelecimentoSelect = document.getElementById('estabelecimentoSelect');
            const estabelecimentosList = document.getElementById('estabelecimentosList');
            
            loginForm.style.display = 'none';
            estabelecimentoSelect.classList.add('show');
            
            estabelecimentosList.innerHTML = '';
            
            estabelecimentos.forEach(estabelecimento => {
                const card = document.createElement('div');
                card.className = 'estabelecimento-card';
                card.onclick = () => selecionarEstabelecimento(estabelecimento, card);
                
                card.innerHTML = `
                    <h6>${estabelecimento.tenant_nome || 'Estabelecimento'}</h6>
                    <small>${estabelecimento.filial_nome || 'Filial Principal'}</small>
                    <br>
                    <small><strong>Cargo:</strong> ${estabelecimento.cargo || estabelecimento.tipo_usuario}</small>
                `;
                
                estabelecimentosList.appendChild(card);
            });
        }

        function selecionarEstabelecimento(estabelecimento, card) {
            // Remover seleção anterior
            document.querySelectorAll('.estabelecimento-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Selecionar atual
            card.classList.add('selected');
            estabelecimentoSelecionado = estabelecimento;
            
            // Habilitar botão entrar
            document.getElementById('btnEntrar').disabled = false;
        }

        // Botão entrar
        document.getElementById('btnEntrar').addEventListener('click', function() {
            if (!estabelecimentoSelecionado || !tokenAtual) {
                showAlert('Selecione um estabelecimento', 'warning');
                return;
            }
            
            const btnEntrar = this;
            const btnText = btnEntrar.querySelector('.btn-text');
            const loading = btnEntrar.querySelector('.loading');
            
            btnEntrar.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('mvc/ajax/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=fazer_login&token=${tokenAtual}&tenant_id=${estabelecimentoSelecionado.tenant_id}&filial_id=${estabelecimentoSelecionado.filial_id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Login realizado com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php?view=dashboard';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Erro ao fazer login', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao fazer login', 'error');
            })
            .finally(() => {
                btnEntrar.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            });
        });

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 'alert-danger';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Auto-dismiss após 5 segundos
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>