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
                               placeholder="+55 (11) 99999-9999 ou +34 635 13 28 30" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login" id="btnLogin">
                    <span class="btn-text">Solicitar Código</span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin"></i> Enviando...
                    </span>
                </button>
            </form>
        </div>
        
        <div id="codeForm" style="display: none;">
            <form id="formCode">
                <div class="mb-3">
                    <label for="codigo" class="form-label">Código de Acesso</label>
                    <div class="phone-input">
                        <i class="fas fa-key"></i>
                        <input type="text" class="form-control" id="codigo" name="codigo" 
                               placeholder="000000" maxlength="6" required>
                    </div>
                    <small class="text-muted">Digite o código de 6 dígitos enviado para seu WhatsApp</small>
                </div>
                
                <button type="submit" class="btn btn-login" id="btnValidateCode">
                    <span class="btn-text">Validar Código</span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin"></i> Validando...
                    </span>
                </button>
                
                <button type="button" class="btn btn-outline-secondary mt-2 w-100" id="btnBack">
                    <i class="fas fa-arrow-left"></i> Voltar
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
        let telefoneAtual = null;
        let estabelecimentos = [];
        let estabelecimentoSelecionado = null;

        // Máscara para telefone internacional
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Se começar com 55 (Brasil), aplicar máscara brasileira
            if (value.startsWith('55')) {
                let brazilNumber = value.substring(2); // Remove o 55
                if (brazilNumber.length <= 11) {
                    if (brazilNumber.length <= 2) {
                        value = `+55 (${brazilNumber}`;
                    } else if (brazilNumber.length <= 7) {
                        value = `+55 (${brazilNumber.slice(0, 2)}) ${brazilNumber.slice(2)}`;
                    } else {
                        value = `+55 (${brazilNumber.slice(0, 2)}) ${brazilNumber.slice(2, 7)}-${brazilNumber.slice(7)}`;
                    }
                }
            } else if (value.startsWith('34')) {
                // Espanha - formato +34 XXX XXX XXX
                let spainNumber = value.substring(2);
                if (spainNumber.length <= 9) {
                    if (spainNumber.length <= 3) {
                        value = `+34 ${spainNumber}`;
                    } else if (spainNumber.length <= 6) {
                        value = `+34 ${spainNumber.slice(0, 3)} ${spainNumber.slice(3)}`;
                    } else {
                        value = `+34 ${spainNumber.slice(0, 3)} ${spainNumber.slice(3, 6)} ${spainNumber.slice(6)}`;
                    }
                }
            } else if (value.length > 0) {
                // Outros países - formato genérico +XX XXXXXXXXX
                if (value.length <= 2) {
                    value = `+${value}`;
                } else if (value.length <= 5) {
                    value = `+${value.slice(0, 2)} ${value.slice(2)}`;
                } else {
                    value = `+${value.slice(0, 2)} ${value.slice(2)}`;
                }
            }
            
            e.target.value = value;
        });

        // Máscara para código (apenas números)
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });

        // Formulário de login
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const telefone = document.getElementById('telefone').value.replace(/\D/g, '');
            
            if (telefone.length < 8) {
                showAlert('Por favor, insira um telefone válido (mínimo 8 dígitos)', 'warning');
                return;
            }
            
            telefoneAtual = telefone;
            solicitarCodigo(telefone);
        });

        // Formulário de código
        document.getElementById('formCode').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const codigo = document.getElementById('codigo').value;
            
            if (codigo.length !== 6) {
                showAlert('Por favor, insira o código de 6 dígitos', 'warning');
                return;
            }
            
            validarCodigo(telefoneAtual, codigo);
        });

        // Botão voltar
        document.getElementById('btnBack').addEventListener('click', function() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('codeForm').style.display = 'none';
            document.getElementById('telefone').value = '';
            telefoneAtual = null;
        });

        function solicitarCodigo(telefone) {
            const btnLogin = document.getElementById('btnLogin');
            const btnText = btnLogin.querySelector('.btn-text');
            const loading = btnLogin.querySelector('.loading');
            
            btnLogin.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('mvc/ajax/phone_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: `action=solicitar_codigo&telefone=${telefone}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Código de acesso enviado para seu WhatsApp!', 'success');
                    
                    // Mostrar formulário de código
                    document.getElementById('loginForm').style.display = 'none';
                    document.getElementById('codeForm').style.display = 'block';
                    document.getElementById('codigo').focus();
                    
                    // Timer para expiração do código
                    startCodeTimer(data.expires_in || 300);
                } else {
                    showAlert(data.message || 'Erro ao solicitar código', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao solicitar código', 'error');
            })
            .finally(() => {
                btnLogin.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            });
        }

        function validarCodigo(telefone, codigo) {
            const btnValidateCode = document.getElementById('btnValidateCode');
            const btnText = btnValidateCode.querySelector('.btn-text');
            const loading = btnValidateCode.querySelector('.loading');
            
            btnValidateCode.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('mvc/ajax/phone_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: `action=validar_codigo&telefone=${telefone}&codigo=${codigo}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Login realizado com sucesso!', 'success');
                    
                    // Redirecionar baseado no tipo de usuário
                    setTimeout(() => {
                        redirectByUserType(data.establishment?.tipo_usuario, data.permissions);
                    }, 1500);
                } else {
                    showAlert(data.message || 'Código inválido ou expirado', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao validar código', 'error');
            })
            .finally(() => {
                btnValidateCode.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            });
        }

        function redirectByUserType(userType, permissions) {
            let redirectUrl = 'index.php?view=dashboard';
            
            switch (userType) {
                case 'admin':
                    redirectUrl = 'index.php?view=dashboard';
                    break;
                case 'cozinha':
                    redirectUrl = 'index.php?view=pedidos';
                    break;
                case 'garcom':
                    redirectUrl = 'index.php?view=dashboard';
                    break;
                case 'entregador':
                    redirectUrl = 'index.php?view=delivery';
                    break;
                case 'caixa':
                    redirectUrl = 'index.php?view=dashboard';
                    break;
                case 'cliente':
                    redirectUrl = 'index.php?view=cliente_dashboard';
                    break;
                default:
                    redirectUrl = 'index.php?view=dashboard';
            }
            
            window.location.href = redirectUrl;
        }

        function startCodeTimer(seconds) {
            const timerElement = document.createElement('div');
            timerElement.id = 'codeTimer';
            timerElement.className = 'text-center text-muted mt-2';
            document.getElementById('codeForm').appendChild(timerElement);
            
            let timeLeft = seconds;
            const timer = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `Código expira em: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    timerElement.textContent = 'Código expirado. Solicite um novo código.';
                    timerElement.className = 'text-center text-danger mt-2';
                }
                timeLeft--;
            }, 1000);
        }


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