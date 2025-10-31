<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();

// Get plan and period from URL parameters
$planId = $_GET['plan'] ?? null;
$period = $_GET['period'] ?? 'mensal';

// Load plan information
$db = \System\Database::getInstance();
$plan = null;
if ($planId) {
    $plan = $db->fetch("SELECT * FROM planos WHERE id = " . intval($planId));
}

// Calculate pricing based on period
$basePrice = $plan ? $plan['preco_mensal'] : 0;
$discount = 0;
$finalPrice = $basePrice;

if ($period === 'semestral') {
    $discount = 0.10; // 10% discount
    $finalPrice = $basePrice * 6 * (1 - $discount);
} elseif ($period === 'anual') {
    $discount = 0.20; // 20% discount
    $finalPrice = $basePrice * 12 * (1 - $discount);
} else {
    $finalPrice = $basePrice;
}

// Handle form submission using existing OnboardingController
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $requiredFields = ['nome_estabelecimento', 'email', 'telefone', 'cnpj', 'nome_responsavel', 'senha'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Campo obrigatório: " . ucfirst(str_replace('_', ' ', $field)));
            }
        }
        
        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }
        
        // Validate CNPJ format (basic validation)
        $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj']);
        if (strlen($cnpj) !== 14) {
            throw new Exception("CNPJ deve ter 14 dígitos");
        }
        
        // Validate password strength
        if (strlen($_POST['senha']) < 6) {
            throw new Exception("Senha deve ter pelo menos 6 caracteres");
        }
        
        // Generate subdomain from establishment name
        $subdomain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['nome_estabelecimento']));
        $subdomain = substr($subdomain, 0, 20); // Limit length
        
        // Prepare data for OnboardingController
        $onboardingData = [
            'nome' => $_POST['nome_estabelecimento'],
            'subdomain' => $subdomain,
            'email' => $_POST['email'],
            'telefone' => $_POST['telefone'],
            'cnpj' => $cnpj,
            'endereco' => $_POST['endereco'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? '',
            'cep' => $_POST['cep'] ?? '',
            'plano_id' => $planId,
            'admin_login' => $_POST['email'],
            'admin_senha' => $_POST['senha'],
            'admin_nome' => $_POST['nome_responsavel'],
            'cor_primaria' => '#667eea',
            'tem_delivery' => true,
            'tem_mesas' => true,
            'tem_balcao' => true,
            'num_mesas' => 5,
            'periodicidade' => $period,
            'valor_final' => $finalPrice
        ];
        
        // Use existing OnboardingController
        require_once __DIR__ . '/../controller/OnboardingController.php';
        $onboardingController = new OnboardingController();
        
        // Create establishment using existing method
        $result = $onboardingController->createEstablishmentWithPayment($onboardingData);
        
        if ($result['success']) {
            // Store credentials for display
            $success_data = [
                'nome' => $_POST['nome_estabelecimento'],
                'login' => $_POST['email'],
                'senha_original' => $_POST['senha'],
                'tenant_id' => $result['tenant_id'],
                'subdomain' => $subdomain,
                'pix_code' => $result['pix_code'] ?? null,
                'pix_qr_code' => $result['pix_qr_code'] ?? null,
                'invoice_url' => $result['invoice_url'] ?? null,
                'asaas_payment_id' => $result['asaas_payment_id'] ?? null,
                'asaas_subscription_id' => $result['asaas_subscription_id'] ?? null,
                'asaas_info' => $result['asaas_info'] ?? null,
                'valor' => $finalPrice,
                'plano' => $plan['nome']
            ];
            
            // Don't redirect, show success message on the same page
            $showSuccess = true;
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Register.php - Error: " . $e->getMessage());
        error_log("Register.php - Stack trace: " . $e->getTraceAsString());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Estabelecimento - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 900px;
            overflow: hidden;
            width: 95%;
        }
        
        @media (max-width: 768px) {
            .register-container {
                margin: 1rem auto;
                border-radius: 10px;
                width: 98%;
            }
            .register-header h1 {
                font-size: 1.75rem !important;
            }
            .form-section {
                padding: 1rem !important;
            }
            .btn-lg {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }
        }
        .register-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .register-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        .form-section {
            padding: 2rem;
        }
        .section-title {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .plan-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .plan-price {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        .plan-period {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .discount-badge {
            background: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 0.5rem;
            position: relative;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h1><i class="fas fa-store me-3"></i>Cadastro de Estabelecimento</h1>
                <p>Preencha os dados abaixo para criar sua conta</p>
            </div>
            
            <div class="form-section">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active">1</div>
                    <div class="step">2</div>
                    <div class="step">3</div>
                </div>
                
                <?php if (isset($showSuccess) && $showSuccess): ?>
                <div class="alert alert-success">
                    <h4><i class="fas fa-check-circle me-2"></i>Estabelecimento Criado com Sucesso!</h4>
                    <hr>
                    <p><strong>Anote suas credenciais de acesso:</strong></p>
                    <div class="bg-light p-3 rounded mb-3">
                        <p class="mb-1"><strong>Nome:</strong> <?php echo htmlspecialchars($success_data['nome']); ?></p>
                        <p class="mb-1"><strong>Usuário (login):</strong> <code><?php echo htmlspecialchars($success_data['login']); ?></code></p>
                        <p class="mb-1"><strong>Senha:</strong> <code><?php echo htmlspecialchars($success_data['senha_original']); ?></code></p>
                        <p class="mb-0"><strong>Subdomain:</strong> <?php echo htmlspecialchars($success_data['subdomain']); ?>.divinolanches.com.br</p>
                    </div>
                    <?php if (isset($success_data['asaas_subscription_id'])): ?>
                    <div class="alert alert-info mt-3">
                        <h5><i class="fas fa-file-invoice me-2"></i>Assinatura Criada no Asaas</h5>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($success_data['valor'], 2, ',', '.'); ?></p>
                        <p><strong>Plano:</strong> <?php echo htmlspecialchars($success_data['plano']); ?></p>
                        <?php if ($success_data['invoice_url']): ?>
                        <a href="<?php echo htmlspecialchars($success_data['invoice_url']); ?>" target="_blank" class="btn btn-success">
                            <i class="fas fa-qrcode me-2"></i>Ver Fatura e Gerar PIX
                        </a>
                        <p class="mt-2 mb-0"><small>O código PIX será gerado automaticamente ao abrir a fatura.</small></p>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Primeira cobrança em processamento</strong><br>
                            A fatura com código PIX será enviada para <strong><?php echo htmlspecialchars($success_data['login']); ?></strong> em alguns minutos.<br>
                            <small>Você também pode acessar suas faturas no painel do administrador após o login.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <a href="index.php?view=login_admin" class="btn btn-primary btn-lg mt-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Ir para Login
                    </a>
                </div>
                <?php elseif (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Plan Summary -->
                <?php if ($plan && !isset($showSuccess)): ?>
                <div class="plan-summary">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="plan-name"><?php echo htmlspecialchars($plan['nome']); ?></div>
                            <div class="plan-price">
                                R$ <?php echo number_format($finalPrice, 2, ',', '.'); ?>
                                <?php if ($discount > 0): ?>
                                <span class="discount-badge ms-2">
                                    <?php echo number_format($discount * 100, 0); ?>% OFF
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="plan-period">
                                <?php 
                                if ($period === 'semestral') {
                                    echo 'Pagamento semestral (6 meses)';
                                } elseif ($period === 'anual') {
                                    echo 'Pagamento anual (12 meses)';
                                } else {
                                    echo 'Pagamento mensal';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!isset($showSuccess)): ?>
                <form method="POST" id="registerForm">
                    <!-- Estabelecimento Section -->
                    <div class="mb-4">
                        <h3 class="section-title">
                            <i class="fas fa-store me-2"></i>Dados do Estabelecimento
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome_estabelecimento" class="form-label">Nome do Estabelecimento *</label>
                                <input type="text" class="form-control" id="nome_estabelecimento" name="nome_estabelecimento" 
                                       value="<?php echo htmlspecialchars($_POST['nome_estabelecimento'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cnpj" class="form-label">CNPJ *</label>
                                <input type="text" class="form-control" id="cnpj" name="cnpj" 
                                       value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>" 
                                       placeholder="00.000.000/0000-00" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefone" class="form-label">Telefone *</label>
                                <input type="tel" class="form-control" id="telefone" name="telefone" 
                                       value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>" 
                                       placeholder="(11) 99999-9999" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="endereco" name="endereco" 
                                       value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" 
                                       value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>" 
                                       placeholder="00000-000">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" 
                                       value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-control" id="estado" name="estado">
                                    <option value="">Selecione o estado</option>
                                    <option value="AC" <?php echo ($_POST['estado'] ?? '') === 'AC' ? 'selected' : ''; ?>>Acre</option>
                                    <option value="AL" <?php echo ($_POST['estado'] ?? '') === 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                                    <option value="AP" <?php echo ($_POST['estado'] ?? '') === 'AP' ? 'selected' : ''; ?>>Amapá</option>
                                    <option value="AM" <?php echo ($_POST['estado'] ?? '') === 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                                    <option value="BA" <?php echo ($_POST['estado'] ?? '') === 'BA' ? 'selected' : ''; ?>>Bahia</option>
                                    <option value="CE" <?php echo ($_POST['estado'] ?? '') === 'CE' ? 'selected' : ''; ?>>Ceará</option>
                                    <option value="DF" <?php echo ($_POST['estado'] ?? '') === 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                                    <option value="ES" <?php echo ($_POST['estado'] ?? '') === 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                                    <option value="GO" <?php echo ($_POST['estado'] ?? '') === 'GO' ? 'selected' : ''; ?>>Goiás</option>
                                    <option value="MA" <?php echo ($_POST['estado'] ?? '') === 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                                    <option value="MT" <?php echo ($_POST['estado'] ?? '') === 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                                    <option value="MS" <?php echo ($_POST['estado'] ?? '') === 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?php echo ($_POST['estado'] ?? '') === 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                                    <option value="PA" <?php echo ($_POST['estado'] ?? '') === 'PA' ? 'selected' : ''; ?>>Pará</option>
                                    <option value="PB" <?php echo ($_POST['estado'] ?? '') === 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                                    <option value="PR" <?php echo ($_POST['estado'] ?? '') === 'PR' ? 'selected' : ''; ?>>Paraná</option>
                                    <option value="PE" <?php echo ($_POST['estado'] ?? '') === 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                                    <option value="PI" <?php echo ($_POST['estado'] ?? '') === 'PI' ? 'selected' : ''; ?>>Piauí</option>
                                    <option value="RJ" <?php echo ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php echo ($_POST['estado'] ?? '') === 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php echo ($_POST['estado'] ?? '') === 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php echo ($_POST['estado'] ?? '') === 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                                    <option value="RR" <?php echo ($_POST['estado'] ?? '') === 'RR' ? 'selected' : ''; ?>>Roraima</option>
                                    <option value="SC" <?php echo ($_POST['estado'] ?? '') === 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                                    <option value="SP" <?php echo ($_POST['estado'] ?? '') === 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                                    <option value="SE" <?php echo ($_POST['estado'] ?? '') === 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                                    <option value="TO" <?php echo ($_POST['estado'] ?? '') === 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Responsável Section -->
                    <div class="mb-4">
                        <h3 class="section-title">
                            <i class="fas fa-user me-2"></i>Dados do Responsável
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome_responsavel" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="nome_responsavel" name="nome_responsavel" 
                                       value="<?php echo htmlspecialchars($_POST['nome_responsavel'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="senha" class="form-label">Senha *</label>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       placeholder="Mínimo 6 caracteres" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                Aceito os <a href="#" target="_blank">Termos de Uso</a> e <a href="#" target="_blank">Política de Privacidade</a> *
                            </label>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-register btn-lg">
                            <i class="fas fa-rocket me-2"></i>
                            Criar Estabelecimento
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CNPJ mask
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });
        
        // Phone mask
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });
        
        // CEP mask
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const requiredFields = ['nome_estabelecimento', 'email', 'telefone', 'cnpj', 'nome_responsavel', 'senha'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            // Email validation
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.value && !emailRegex.test(email.value)) {
                email.classList.add('is-invalid');
                isValid = false;
            }
            
            // Password validation
            const senha = document.getElementById('senha');
            if (senha.value && senha.value.length < 6) {
                senha.classList.add('is-invalid');
                isValid = false;
            }
            
            // CNPJ validation
            const cnpj = document.getElementById('cnpj');
            const cnpjNumbers = cnpj.value.replace(/\D/g, '');
            if (cnpj.value && cnpjNumbers.length !== 14) {
                cnpj.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios corretamente.');
            }
        });
    </script>
</body>
</html>
