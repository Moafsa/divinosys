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
            display: flex;
            gap: 10px;
        }
        
        .country-select {
            width: 120px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 10px;
            font-size: 16px;
            background-color: white;
            cursor: pointer;
        }
        
        .country-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        
        .country-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .phone-input-wrapper {
            position: relative;
            flex: 1;
        }
        
        .phone-input-wrapper .form-control {
            padding-left: 50px;
        }
        
        .phone-input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 1;
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
                        <select class="country-select" id="countryCode" name="countryCode">
                            <option value="55" data-flag="🇧🇷" selected>🇧🇷 Brasil</option>
                            <option value="34" data-flag="🇪🇸">🇪🇸 Espanha</option>
                            <option value="351" data-flag="🇵🇹">🇵🇹 Portugal</option>
                            <option value="1" data-flag="🇺🇸">🇺🇸 EUA/Canadá</option>
                            <option value="54" data-flag="🇦🇷">🇦🇷 Argentina</option>
                            <option value="598" data-flag="🇺🇾">🇺🇾 Uruguai</option>
                            <option value="595" data-flag="🇵🇾">🇵🇾 Paraguai</option>
                            <option value="56" data-flag="🇨🇱">🇨🇱 Chile</option>
                            <option value="57" data-flag="🇨🇴">🇨🇴 Colômbia</option>
                            <option value="51" data-flag="🇵🇪">🇵🇪 Peru</option>
                            <option value="593" data-flag="🇪🇨">🇪🇨 Equador</option>
                            <option value="52" data-flag="🇲🇽">🇲🇽 México</option>
                            <option value="39" data-flag="🇮🇹">🇮🇹 Itália</option>
                            <option value="33" data-flag="🇫🇷">🇫🇷 França</option>
                            <option value="49" data-flag="🇩🇪">🇩🇪 Alemanha</option>
                            <option value="44" data-flag="🇬🇧">🇬🇧 Reino Unido</option>
                        </select>
                        <div class="phone-input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" class="form-control" id="telefone" name="telefone" 
                                   placeholder="(11) 99999-9999" required>
                        </div>
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
        
        <div id="accessTypeSelect" style="display: none;">
            <h5 class="mb-3">Como deseja acessar?</h5>
            <div id="accessTypeList"></div>
            <button type="button" class="btn btn-outline-secondary mt-3 w-100" id="btnBackAccessType">
                <i class="fas fa-arrow-left"></i> Voltar
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

        // Máscara para telefone brasileiro (DDD + número)
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            const countryCode = document.getElementById('countryCode').value;
            
            // Se for Brasil (55), aplicar máscara brasileira: (DDD) 99999-9999
            if (countryCode === '55') {
                if (value.length <= 2) {
                    value = `(${value}`;
                } else if (value.length <= 7) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else if (value.length <= 11) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                } else {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7, 11)}`;
                }
            } else {
                // Para outros países, apenas números sem formatação especial
                // O usuário digita o número completo do país
            }
            
            e.target.value = value;
        });

        // Atualizar placeholder quando o país mudar
        document.getElementById('countryCode').addEventListener('change', function(e) {
            const countryCode = e.target.value;
            const telefoneInput = document.getElementById('telefone');
            
            if (countryCode === '55') {
                telefoneInput.placeholder = '(11) 99999-9999';
            } else if (countryCode === '34') {
                telefoneInput.placeholder = '635 13 28 30';
            } else if (countryCode === '351') {
                telefoneInput.placeholder = '912 345 678';
            } else if (countryCode === '1') {
                telefoneInput.placeholder = '(555) 123-4567';
            } else {
                telefoneInput.placeholder = 'Digite o número';
            }
            
            // Limpar o campo quando mudar o país
            telefoneInput.value = '';
        });

        // Máscara para código (apenas números)
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });

        // Formulário de login
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const countryCode = document.getElementById('countryCode').value;
            const telefoneDigits = document.getElementById('telefone').value.replace(/\D/g, '');
            
            // Validação para Brasil: DDD (2 dígitos) + número (8 ou 9 dígitos)
            if (countryCode === '55') {
                if (telefoneDigits.length < 10 || telefoneDigits.length > 11) {
                    showAlert('Por favor, insira um telefone válido: DDD + número (10 ou 11 dígitos)', 'warning');
                    return;
                }
            } else {
                // Para outros países, validar mínimo de 8 dígitos
                if (telefoneDigits.length < 8) {
                showAlert('Por favor, insira um telefone válido (mínimo 8 dígitos)', 'warning');
                return;
                }
            }
            
            // Concatenar código do país + telefone
            const telefoneCompleto = countryCode + telefoneDigits;
            telefoneAtual = telefoneCompleto;
            solicitarCodigo(telefoneCompleto);
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
            // Resetar para Brasil ao voltar
            document.getElementById('countryCode').value = '55';
            document.getElementById('telefone').placeholder = '(11) 99999-9999';
            // NÃO resetar selectedEstablishment e accessType aqui - eles devem ser mantidos
            console.log('Botão voltar clicado - Mantendo selectedEstablishment:', window.selectedEstablishment);
            console.log('Botão voltar clicado - Mantendo accessType:', window.accessType);
        });

        function solicitarCodigo(telefone) {
            const btnLogin = document.getElementById('btnLogin');
            const btnText = btnLogin.querySelector('.btn-text');
            const loading = btnLogin.querySelector('.loading');
            
            btnLogin.disabled = true;
            btnText.style.display = 'none';
            loading.classList.add('show');
            
            fetch('mvc/ajax/phone_auth_clean.php', {
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
                    
                    // Se usuário tem múltiplos estabelecimentos, mostrar seleção
                    if (data.requires_selection && data.estabelecimentos && data.estabelecimentos.length > 1) {
                        showEstablishmentSelection(data.estabelecimentos, data.usuario);
                        return;
                    }
                    
                    // Se usuário tem apenas um estabelecimento, mas pode acessar como cliente também
                    if (data.estabelecimentos && data.estabelecimentos.length === 1) {
                        showAccessTypeSelection(data.estabelecimentos[0], data.usuario);
                        return;
                    }
                    
                    // Mostrar formulário de código (acesso como cliente)
                    document.getElementById('loginForm').style.display = 'none';
                    document.getElementById('codeForm').style.display = 'block';
                    document.getElementById('codigo').focus();
                    
                    // Armazenar tipo de acesso
                    window.accessType = data.access_type || 'cliente';
                    // NÃO resetar selectedEstablishment aqui se já foi escolhido
                    if (data.access_type === 'cliente') {
                        window.selectedEstablishment = null;
                    }
                    // Se access_type não é 'cliente', manter selectedEstablishment se existir
                    
                    console.log('Código enviado - accessType:', window.accessType);
                    console.log('Código enviado - selectedEstablishment:', window.selectedEstablishment);
                    
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
            
            // Preparar dados para envio
            let bodyData = `action=validar_codigo&telefone=${telefone}&codigo=${codigo}`;
            
            console.log('=== VALIDAÇÃO DE CÓDIGO ===');
            console.log('selectedEstablishment:', window.selectedEstablishment);
            console.log('accessType:', window.accessType);
            console.log('estabelecimentosDisponiveis:', window.estabelecimentosDisponiveis);
            
            // Se tem estabelecimento selecionado e acesso como usuário, enviar
            if (window.selectedEstablishment && window.accessType === 'usuario') {
                bodyData += `&tenant_id=${window.selectedEstablishment.tenant_id}`;
                if (window.selectedEstablishment.filial_id) {
                    bodyData += `&filial_id=${window.selectedEstablishment.filial_id}`;
                }
                bodyData += `&tipo_usuario=${window.selectedEstablishment.tipo_usuario}`;
                bodyData += `&access_type=usuario`;
                console.log('✓ Enviando como USUÁRIO');
                console.log('  Tenant:', window.selectedEstablishment.tenant_id);
                console.log('  Filial:', window.selectedEstablishment.filial_id);
                console.log('  Tipo:', window.selectedEstablishment.tipo_usuario);
            } else {
                // Acesso como cliente - não enviar tenant/filial específico
                bodyData += `&access_type=cliente`;
                console.log('✓ Enviando como CLIENTE');
                if (!window.selectedEstablishment) {
                    console.warn('⚠ selectedEstablishment está null/undefined');
                }
                if (window.accessType !== 'cliente') {
                    console.warn('⚠ accessType não é "cliente":', window.accessType);
                }
            }
            
            console.log('Body data completo:', bodyData);
            
            fetch('mvc/ajax/phone_auth_clean.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: bodyData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta da validação:', data);
                
                if (data.success) {
                    showAlert('Login realizado com sucesso!', 'success');
                    
                    // Garantir que a sessão foi salva antes de redirecionar
                    // Forçar um pequeno delay e verificar se realmente está autenticado
                    setTimeout(() => {
                        console.log('Dados completos da resposta:', data);
                        console.log('Dados do establishment:', data.establishment);
                        console.log('Tipo de usuário (establishment):', data.establishment?.tipo_usuario);
                        console.log('Tipo de usuário (direto):', data.tipo_usuario);
                        
                        // Usar tipo_usuario da resposta (pode estar em establishment ou diretamente)
                        const tipoUsuario = data.tipo_usuario || data.establishment?.tipo_usuario;
                        
                        if (tipoUsuario) {
                            console.log('Redirecionando para tipo:', tipoUsuario);
                            redirectByUserType(tipoUsuario, data.permissions);
                        } else {
                            // Fallback: sempre redirecionar para dashboard se não tiver tipo
                            console.warn('Tipo de usuário não encontrado, redirecionando para dashboard');
                            console.warn('Dados disponíveis:', JSON.stringify(data));
                            window.location.href = 'index.php?view=dashboard';
                        }
                    }, 500); // Reduzido para 500ms para ser mais rápido
                } else {
                    console.error('Erro na validação:', data);
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
            if (!userType) {
                console.warn('Tipo de usuário não informado, usando padrão (dashboard)');
                userType = 'admin'; // Default
            }
            
            let redirectUrl = 'index.php?view=dashboard';
            
            switch (userType.toLowerCase()) {
                case 'admin':
                case 'administrador':
                    redirectUrl = 'index.php?view=dashboard';
                    break;
                case 'cozinha':
                    redirectUrl = 'index.php?view=pedidos';
                    break;
                case 'garcom':
                case 'garçom':
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
                    console.warn('Tipo de usuário desconhecido:', userType, '- usando dashboard como padrão');
                    redirectUrl = 'index.php?view=dashboard';
            }
            
            console.log('Redirecionando para:', redirectUrl, 'baseado no tipo:', userType);
            
            // Usar location.replace para evitar que o botão "voltar" volte ao login
            window.location.replace(redirectUrl);
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


        // Variáveis globais para controle
        window.selectedEstablishment = null;
        window.accessType = 'cliente';
        window.estabelecimentosDisponiveis = [];
        window.usuarioAtual = null;

        function showEstablishmentSelection(estabelecimentos, usuario) {
            window.estabelecimentosDisponiveis = estabelecimentos;
            window.usuarioAtual = usuario;
            
            const estabelecimentoSelect = document.getElementById('estabelecimentoSelect');
            const estabelecimentosList = document.getElementById('estabelecimentosList');
            const loginForm = document.getElementById('loginForm');
            const codeForm = document.getElementById('codeForm');
            
            loginForm.style.display = 'none';
            codeForm.style.display = 'none';
            estabelecimentoSelect.style.display = 'block';
            estabelecimentoSelect.classList.add('show');
            
            estabelecimentosList.innerHTML = '';
            
            estabelecimentos.forEach((est, index) => {
                const card = document.createElement('div');
                card.className = 'estabelecimento-card';
                card.dataset.index = index;
                
                const nomeEstabelecimento = est.filial_nome 
                    ? `${est.tenant_nome} - ${est.filial_nome}` 
                    : est.tenant_nome || 'Estabelecimento';
                
                const tipoUsuario = est.tipo_usuario.charAt(0).toUpperCase() + est.tipo_usuario.slice(1);
                
                card.innerHTML = `
                    <h6>${nomeEstabelecimento}</h6>
                    <small>Cargo: ${tipoUsuario}</small>
                `;
                
                card.addEventListener('click', function() {
                    document.querySelectorAll('.estabelecimento-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    window.selectedEstablishment = est;
                    document.getElementById('btnEntrar').disabled = false;
                });
                
                estabelecimentosList.appendChild(card);
            });
            
            // Adicionar opção de acessar como cliente
            const clienteCard = document.createElement('div');
            clienteCard.className = 'estabelecimento-card';
            clienteCard.innerHTML = `
                <h6>Acessar como Cliente</h6>
                <small>Fazer pedidos e compras</small>
            `;
            clienteCard.addEventListener('click', function() {
                document.querySelectorAll('.estabelecimento-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                window.selectedEstablishment = null;
                window.accessType = 'cliente';
                document.getElementById('btnEntrar').disabled = false;
            });
            estabelecimentosList.appendChild(clienteCard);
            
            // Botão entrar
            document.getElementById('btnEntrar').addEventListener('click', function() {
                if (window.selectedEstablishment) {
                    window.accessType = 'usuario';
                    // Mostrar formulário de código
                    estabelecimentoSelect.style.display = 'none';
                    codeForm.style.display = 'block';
                    document.getElementById('codigo').focus();
                } else if (window.accessType === 'cliente') {
                    // Mostrar formulário de código como cliente
                    estabelecimentoSelect.style.display = 'none';
                    codeForm.style.display = 'block';
                    document.getElementById('codigo').focus();
                }
            });
        }

        function showAccessTypeSelection(estabelecimento, usuario) {
            window.selectedEstablishment = estabelecimento;
            window.usuarioAtual = usuario;
            
            const accessTypeSelect = document.getElementById('accessTypeSelect');
            const accessTypeList = document.getElementById('accessTypeList');
            const loginForm = document.getElementById('loginForm');
            const codeForm = document.getElementById('codeForm');
            
            loginForm.style.display = 'none';
            codeForm.style.display = 'none';
            accessTypeSelect.style.display = 'block';
            
            const nomeEstabelecimento = estabelecimento.filial_nome 
                ? `${estabelecimento.tenant_nome} - ${estabelecimento.filial_nome}` 
                : estabelecimento.tenant_nome || 'Estabelecimento';
            
            const tipoUsuario = estabelecimento.tipo_usuario.charAt(0).toUpperCase() + estabelecimento.tipo_usuario.slice(1);
            
            accessTypeList.innerHTML = `
                <div class="estabelecimento-card" id="accessAsUser" style="cursor: pointer;">
                    <h6>Acessar como ${tipoUsuario}</h6>
                    <small>${nomeEstabelecimento}</small>
                </div>
                <div class="estabelecimento-card" id="accessAsClient" style="cursor: pointer;">
                    <h6>Acessar como Cliente</h6>
                    <small>Fazer pedidos e compras</small>
                </div>
            `;
            
            document.getElementById('accessAsUser').addEventListener('click', function() {
                window.accessType = 'usuario';
                // selectedEstablishment já está definido (foi definido em showAccessTypeSelection)
                console.log('✓ Acessar como USUÁRIO escolhido');
                console.log('✓ selectedEstablishment mantido:', window.selectedEstablishment);
                console.log('✓ accessType definido como:', window.accessType);
                console.log('✓ Tipo do estabelecimento:', window.selectedEstablishment?.tipo_usuario);
                accessTypeSelect.style.display = 'none';
                codeForm.style.display = 'block';
                document.getElementById('codigo').focus();
            });
            
            document.getElementById('accessAsClient').addEventListener('click', function() {
                window.accessType = 'cliente';
                window.selectedEstablishment = null; // Limpar seleção para acessar como cliente
                accessTypeSelect.style.display = 'none';
                codeForm.style.display = 'block';
                document.getElementById('codigo').focus();
            });
            
            document.getElementById('btnBackAccessType').addEventListener('click', function() {
                accessTypeSelect.style.display = 'none';
                loginForm.style.display = 'block';
                document.getElementById('telefone').value = '';
                telefoneAtual = null;
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