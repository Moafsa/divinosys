<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Divino Lanches</title>
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
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 3;
        }
        
        .input-group .form-control {
            padding-left: 50px;
        }
        
        .switch-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .switch-login a {
            color: #667eea;
            text-decoration: none;
        }
        
        .switch-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-user-shield"></i>
            <h2>Divino Lanches</h2>
            <p class="text-muted">Login Administrativo</p>
        </div>
        
        <form id="formLogin">
            <div class="mb-3">
                <label for="login" class="form-label">Usuário</label>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" id="login" name="login" 
                           placeholder="admin" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" id="senha" name="senha" 
                           placeholder="••••••••" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login" id="btnLogin">
                <span class="btn-text">Entrar</span>
                <span class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Entrando...
                </span>
            </button>
        </form>
        
        <div class="switch-login">
            <p class="text-muted">Não é administrador?</p>
            <a href="index.php?view=login">Fazer login com telefone</a>
        </div>
        
        <div id="alertContainer"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Formulário de login
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const login = document.getElementById('login').value.trim();
            const senha = document.getElementById('senha').value;
            
            if (!login || !senha) {
                showAlert('Por favor, preencha todos os campos', 'warning');
                return;
            }
            
            fazerLogin(login, senha);
        });

        function fazerLogin(login, senha) {
            const btnLogin = document.getElementById('btnLogin');
            const btnText = btnLogin.querySelector('.btn-text');
            const loading = btnLogin.querySelector('.loading');
            
            btnLogin.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: `action=login&login=${encodeURIComponent(login)}&senha=${encodeURIComponent(senha)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Login realizado com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php?view=dashboard';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Usuário ou senha incorretos', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao fazer login', 'error');
            })
            .finally(() => {
                btnLogin.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            });
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
