<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();

// Redirect if already logged in
if ($session->isLoggedIn()) {
    $router->redirect('dashboard');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .login-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <h2 class="login-title"><?php echo $config->get('app.name'); ?></h2>
                <p class="login-subtitle">Faça login para acessar o sistema</p>
            </div>

            <div id="alertContainer"></div>

            <form id="loginForm">
                <div class="form-floating">
                    <input type="text" class="form-control" id="login" name="login" placeholder="Usuário" value="admin" required>
                    <label for="login">
                        <i class="fas fa-user me-1"></i>
                        Usuário
                    </label>
                </div>

           <div class="form-floating">
               <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" value="password" required>
               <label for="senha">
                   <i class="fas fa-lock me-1"></i>
                   Senha
               </label>
           </div>

                <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Entrar
                    </span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Entrando...
                    </span>
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?php echo $router->url('home'); ?>" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i>
                    Voltar ao início
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const loginBtn = document.getElementById('loginBtn');
            const btnText = loginBtn.querySelector('.btn-text');
            const loading = loginBtn.querySelector('.loading');
            const alertContainer = document.getElementById('alertContainer');
            
            // Show loading state
            loginBtn.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            // Clear previous alerts
            alertContainer.innerHTML = '';
            
            try {
                // Add action parameter
                formData.append('action', 'login');
                
                const response = await fetch('<?php echo $router->url('login'); ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    await Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Redirect to dashboard
                    window.location.href = '<?php echo $router->url('dashboard'); ?>';
                } else {
                    // Show error message
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${result.message}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Login error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erro de conexão. Tente novamente.
                    </div>
                `;
            } finally {
                // Hide loading state
                loginBtn.disabled = false;
                btnText.style.display = 'inline';
                loading.classList.remove('show');
            }
        });

        // Auto-focus on login input
        document.getElementById('login').focus();

        // Handle Enter key navigation
        document.getElementById('login').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('senha').focus();
            }
        });
    </script>
</body>
</html>
