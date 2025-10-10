<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Divino Lanches SaaS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .onboarding-container {
            max-width: 900px;
            width: 100%;
            margin: 30px;
        }
        .onboarding-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .onboarding-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .onboarding-header h2 {
            margin-bottom: 10px;
        }
        .onboarding-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #e9ecef;
            z-index: -1;
        }
        .step:last-child::before {
            display: none;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #adb5bd;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        .step-label {
            font-size: 14px;
            color: #6c757d;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .plan-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
        }
        .plan-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .plan-card.selected {
            border-color: #667eea;
            background: #f8f9fe;
        }
        .plan-card .plan-price {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin: 15px 0;
        }
        .plan-card .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .plan-card .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .plan-card .plan-features li:last-child {
            border-bottom: none;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="onboarding-card">
            <div class="onboarding-header">
                <h2><i class="fas fa-rocket"></i> Bem-vindo ao Divino Lanches</h2>
                <p class="mb-0">Vamos configurar seu estabelecimento em poucos passos</p>
            </div>
            
            <div class="onboarding-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Dados Básicos</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Escolha o Plano</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Configurações</div>
                    </div>
                    <div class="step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Finalizar</div>
                    </div>
                </div>

                <!-- Step 1: Dados Básicos -->
                <div class="step-content active" id="step1">
                    <h4 class="mb-4">Dados do Estabelecimento</h4>
                    <form id="form-step1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome do Estabelecimento *</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subdomain * <small class="text-muted">(ex: seu-negocio)</small></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="subdomain" id="subdomain" required>
                                    <span class="input-group-text">.divinolanches.com.br</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" name="cnpj" data-mask="00.000.000/0000-00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone *</label>
                                <input type="text" class="form-control" name="telefone" data-mask="(00) 00000-0000" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Endereço</label>
                            <input type="text" class="form-control" name="endereco">
                        </div>
                        <hr class="my-4">
                        <h5 class="mb-3">Dados do Administrador</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuário (Login) *</label>
                                <input type="text" class="form-control" name="admin_login" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Senha *</label>
                                <input type="password" class="form-control" name="admin_senha" required>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Step 2: Escolha do Plano -->
                <div class="step-content" id="step2">
                    <h4 class="mb-4 text-center">Escolha o Plano Ideal</h4>
                    <div class="row" id="plans-container">
                        <!-- Plans will be loaded here -->
                    </div>
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-gift"></i> <strong>14 dias grátis!</strong> Teste qualquer plano sem compromisso
                    </div>
                </div>

                <!-- Step 3: Configurações -->
                <div class="step-content" id="step3">
                    <h4 class="mb-4">Configurações Iniciais</h4>
                    <form id="form-step3">
                        <div class="mb-4">
                            <label class="form-label">Quantas mesas você tem?</label>
                            <input type="number" class="form-control" name="num_mesas" value="10" min="1" max="100">
                            <small class="text-muted">Você pode adicionar ou remover mesas depois</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Cor do seu Sistema</label>
                            <input type="color" class="form-control form-control-color" name="cor_primaria" value="#667eea">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Tipo de Operação</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tem_delivery" id="tem_delivery" checked>
                                <label class="form-check-label" for="tem_delivery">
                                    Trabalho com Delivery
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tem_mesas" id="tem_mesas" checked>
                                <label class="form-check-label" for="tem_mesas">
                                    Tenho Mesas
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tem_balcao" id="tem_balcao" checked>
                                <label class="form-check-label" for="tem_balcao">
                                    Atendo no Balcão
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Step 4: Finalizar -->
                <div class="step-content" id="step4">
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                        <h3 class="mt-4">Tudo Pronto!</h3>
                        <p class="text-muted">Seu estabelecimento está configurado e pronto para uso</p>
                        <div class="alert alert-info mt-4">
                            <strong>Próximos Passos:</strong>
                            <ul class="list-unstyled mt-3 mb-0">
                                <li><i class="fas fa-check text-success"></i> Cadastrar produtos</li>
                                <li><i class="fas fa-check text-success"></i> Adicionar categorias</li>
                                <li><i class="fas fa-check text-success"></i> Configurar mesas</li>
                                <li><i class="fas fa-check text-success"></i> Começar a receber pedidos!</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary" id="btn-prev" style="display:none;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </button>
                    <div></div>
                    <button class="btn btn-gradient" id="btn-next">
                        Próximo <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        let currentStep = 1;
        let totalSteps = 4;
        let formData = {};
        let selectedPlan = null;

        // Apply masks
        $('[data-mask]').each(function() {
            $(this).mask($(this).data('mask'));
        });

        // Load plans
        function loadPlans() {
            $.get('mvc/controller/SuperAdminController.php?action=listPlans', function(plans) {
                let html = '';
                plans.forEach(plan => {
                    const recursos = typeof plan.recursos === 'string' ? JSON.parse(plan.recursos) : plan.recursos;
                    html += `
                        <div class="col-md-3 mb-3">
                            <div class="plan-card" data-plan-id="${plan.id}">
                                <h5 class="text-center">${plan.nome}</h5>
                                <div class="plan-price text-center">
                                    R$ ${parseFloat(plan.preco_mensal).toFixed(2)}
                                    <small style="font-size: 16px; color: #6c757d;">/mês</small>
                                </div>
                                <ul class="plan-features">
                                    <li><i class="fas fa-check text-success"></i> ${plan.max_mesas == -1 ? 'Ilimitado' : plan.max_mesas} mesas</li>
                                    <li><i class="fas fa-check text-success"></i> ${plan.max_usuarios == -1 ? 'Ilimitado' : plan.max_usuarios} usuários</li>
                                    <li><i class="fas fa-check text-success"></i> ${plan.max_produtos == -1 ? 'Ilimitado' : plan.max_produtos} produtos</li>
                                    <li><i class="fas fa-check text-success"></i> ${plan.max_pedidos_mes == -1 ? 'Ilimitado' : plan.max_pedidos_mes} pedidos/mês</li>
                                </ul>
                            </div>
                        </div>
                    `;
                });
                $('#plans-container').html(html);
                
                // Plan selection
                $('.plan-card').click(function() {
                    $('.plan-card').removeClass('selected');
                    $(this).addClass('selected');
                    selectedPlan = $(this).data('plan-id');
                });
            });
        }

        // Navigation
        $('#btn-next').click(function() {
            if (validateStep(currentStep)) {
                saveStepData(currentStep);
                
                if (currentStep < totalSteps) {
                    goToStep(currentStep + 1);
                } else {
                    submitOnboarding();
                }
            }
        });

        $('#btn-prev').click(function() {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });

        function goToStep(step) {
            currentStep = step;
            
            $('.step-content').removeClass('active');
            $(`#step${step}`).addClass('active');
            
            $('.step').removeClass('active completed');
            $(`.step[data-step="${step}"]`).addClass('active');
            for (let i = 1; i < step; i++) {
                $(`.step[data-step="${i}"]`).addClass('completed');
            }
            
            $('#btn-prev').toggle(step > 1);
            $('#btn-next').html(step < totalSteps ? 
                'Próximo <i class="fas fa-arrow-right"></i>' : 
                'Começar a Usar <i class="fas fa-rocket"></i>'
            );
        }

        function validateStep(step) {
            switch(step) {
                case 1:
                    const form1 = $('#form-step1')[0];
                    if (!form1.checkValidity()) {
                        form1.reportValidity();
                        return false;
                    }
                    return true;
                case 2:
                    if (!selectedPlan) {
                        Swal.fire('Atenção', 'Selecione um plano', 'warning');
                        return false;
                    }
                    return true;
                case 3:
                    return true;
                case 4:
                    return true;
                default:
                    return true;
            }
        }

        function saveStepData(step) {
            switch(step) {
                case 1:
                    formData = {...formData, ...$('#form-step1').serializeArray().reduce((obj, item) => {
                        obj[item.name] = item.value;
                        return obj;
                    }, {})};
                    break;
                case 2:
                    formData.plano_id = selectedPlan;
                    break;
                case 3:
                    formData = {...formData, ...$('#form-step3').serializeArray().reduce((obj, item) => {
                        obj[item.name] = item.value;
                        return obj;
                    }, {})};
                    formData.tem_delivery = $('#tem_delivery').is(':checked');
                    formData.tem_mesas = $('#tem_mesas').is(':checked');
                    formData.tem_balcao = $('#tem_balcao').is(':checked');
                    break;
            }
        }

        function submitOnboarding() {
            Swal.fire({
                title: 'Criando seu estabelecimento...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'mvc/controller/OnboardingController.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Seu estabelecimento foi criado com sucesso!',
                            confirmButtonText: 'Ir para o Sistema'
                        }).then(() => {
                            window.location.href = 'index.php?view=login';
                        });
                    } else {
                        Swal.fire('Erro!', response.error || 'Erro ao criar estabelecimento', 'error');
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire('Erro!', 'Erro de comunicação com o servidor', 'error');
                }
            });
        }

        // Initialize
        $(document).ready(function() {
            loadPlans();
            
            // Check subdomain availability
            let subdomainTimeout;
            $('#subdomain').on('input', function() {
                clearTimeout(subdomainTimeout);
                const subdomain = $(this).val();
                
                if (subdomain.length < 3) return;
                
                subdomainTimeout = setTimeout(() => {
                    // Check availability (to be implemented)
                }, 500);
            });
        });
    </script>
</body>
</html>

