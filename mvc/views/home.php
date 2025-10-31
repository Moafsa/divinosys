<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();

// Carregar planos do banco de dados
$db = \System\Database::getInstance();
$planos = $db->fetchAll("SELECT * FROM planos ORDER BY preco_mensal ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config->get('app.name'); ?> - Sistema de Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero-section {
            padding: 100px 0;
            color: white;
            text-align: center;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ffd700;
        }
        .btn-custom {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: none;
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .stats-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            margin: 3rem 0;
        }
        .stat-item {
            text-align: center;
            color: white;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffd700;
        }
        .footer {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
        }
        
        /* Pricing Section Styles */
        .pricing-section {
            background: white;
            padding: 80px 0;
        }
        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .discount-info {
            margin-top: 5px;
        }
        .discount-info .original-price {
            text-decoration: line-through;
            color: #6c757d;
        }
        .discount-info .discount-amount {
            color: #28a745;
            font-weight: bold;
        }
        .btn-group .badge {
            margin-left: 5px;
        }
        .pricing-card.featured {
            border: 3px solid #667eea;
            transform: scale(1.05);
        }
        .pricing-card.featured::before {
            content: 'MAIS POPULAR';
            position: absolute;
            top: 20px;
            right: -30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 40px;
            font-size: 12px;
            font-weight: bold;
            transform: rotate(45deg);
        }
        .plan-name {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .plan-price {
            font-size: 48px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 5px;
        }
        .plan-period {
            color: #718096;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }
        .plan-features li {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
        }
        .plan-features li:last-child {
            border-bottom: none;
        }
        .plan-features li i {
            color: #48bb78;
            margin-right: 15px;
            font-size: 18px;
        }
        .plan-features li.unlimited i {
            color: #667eea;
        }
        .btn-plan {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-starter {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        .btn-starter:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            transform: translateY(-2px);
        }
        .btn-professional {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-professional:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
        }
        .btn-business {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
        }
        .btn-business:hover {
            background: linear-gradient(135deg, #dd6b20 0%, #c05621 100%);
            transform: translateY(-2px);
        }
        .btn-enterprise {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
            color: white;
        }
        .btn-enterprise:hover {
            background: linear-gradient(135deg, #805ad5 0%, #6b46c1 100%);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-transparent">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-utensils me-2"></i>
                <?php echo $config->get('app.name'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Recursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Planos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#faq">FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Sobre</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $router->url('login'); ?>">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Entrar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h1 class="hero-title">
                        <i class="fas fa-utensils me-3"></i>
                        Sistema de Gestão Completo
                    </h1>
                    <p class="hero-subtitle">
                        Gerencie seu restaurante ou lanchonete com eficiência e praticidade
                    </p>
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                        <a href="<?php echo $router->url('login'); ?>" class="btn btn-custom btn-lg">
                            <i class="fas fa-rocket me-2"></i>
                            Começar Agora
                        </a>
                        <a href="#pricing" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-tags me-2"></i>
                            Ver Planos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h4>Gestão de Pedidos</h4>
                        <p>Controle completo de pedidos em mesa e delivery com pipeline visual e atualizações em tempo real.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Relatórios Avançados</h4>
                        <p>Análises detalhadas de vendas, produtos mais vendidos e performance do negócio.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <h4>Controle de Estoque</h4>
                        <p>Monitore produtos em baixo estoque e gerencie fornecedores de forma eficiente.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h4>Gestão Financeira</h4>
                        <p>Controle de receitas, despesas e fluxo de caixa com categorização automática.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Clientes e Entregadores</h4>
                        <p>Cadastro completo de clientes e gestão de entregadores com histórico de pedidos.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Multi-tenant SaaS</h4>
                        <p>Arquitetura preparada para múltiplos estabelecimentos com isolamento de dados.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="display-4 fw-bold text-dark mb-3">
                        <i class="fas fa-tags text-primary me-3"></i>
                        Escolha o Plano Ideal
                    </h2>
                    <p class="lead text-muted">
                        Planos flexíveis para atender desde pequenos estabelecimentos até grandes redes
                    </p>
                </div>
            </div>
            
            <!-- Seletor de Periodicidade -->
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <div class="btn-group" role="group" aria-label="Periodicidade">
                        <input type="radio" class="btn-check" name="periodicidade" id="mensal" value="mensal" checked>
                        <label class="btn btn-outline-primary" for="mensal">Mensal</label>
                        
                        <input type="radio" class="btn-check" name="periodicidade" id="semestral" value="semestral">
                        <label class="btn btn-outline-primary" for="semestral">Semestral <span class="badge bg-success">-10%</span></label>
                        
                        <input type="radio" class="btn-check" name="periodicidade" id="anual" value="anual">
                        <label class="btn btn-outline-primary" for="anual">Anual <span class="badge bg-success">-20%</span></label>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($planos as $index => $plano): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="pricing-card <?php echo $index == 1 ? 'featured' : ''; ?>" data-plan-id="<?php echo $plano['id']; ?>">
                        <div class="plan-name"><?php echo htmlspecialchars($plano['nome']); ?></div>
                        <div class="plan-price" data-base-price="<?php echo $plano['preco_mensal']; ?>">
                            R$ <span class="price-value"><?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="plan-period">
                            <span class="period-text">por mês</span>
                            <div class="discount-info" style="display: none;">
                                <small class="text-success">
                                    <span class="original-price"></span> 
                                    <span class="discount-amount"></span>
                                </small>
                            </div>
                        </div>
                        <ul class="plan-features">
                            <li><i class="fas fa-check"></i> <?php echo $plano['max_mesas'] == -1 ? '∞' : $plano['max_mesas']; ?> mesas</li>
                            <li><i class="fas fa-check"></i> <?php echo $plano['max_usuarios'] == -1 ? '∞' : $plano['max_usuarios']; ?> usuários</li>
                            <li><i class="fas fa-check"></i> <?php echo $plano['max_produtos'] == -1 ? '∞' : $plano['max_produtos']; ?> produtos</li>
                            <li><i class="fas fa-check"></i> <?php echo $plano['max_pedidos_mes'] == -1 ? '∞' : number_format($plano['max_pedidos_mes']); ?> pedidos/mês</li>
                            <?php if ($plano['max_filiais'] > 0): ?>
                            <li><i class="fas fa-check"></i> <?php echo $plano['max_filiais']; ?> filiais</li>
                            <?php elseif ($plano['max_filiais'] == -1): ?>
                            <li><i class="fas fa-check unlimited"></i> <i class="fas fa-infinity"></i> Filiais ilimitadas</li>
                            <?php else: ?>
                            <li><i class="fas fa-times text-danger"></i> Sem filiais</li>
                            <?php endif; ?>
                            
                            <?php 
                            $recursos = json_decode($plano['recursos'], true);
                            if (is_array($recursos)) {
                                foreach ($recursos as $recurso => $ativo) {
                                    if ($ativo) {
                                        echo '<li><i class="fas fa-check"></i> ' . ucfirst(str_replace('_', ' ', $recurso)) . '</li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                        <button class="btn-plan btn-<?php echo strtolower($plano['nome']); ?>" onclick="selectPlan(<?php echo $plano['id']; ?>)">
                            <?php if ($index == 1): ?>
                            <i class="fas fa-star"></i> Mais Popular
                            <?php elseif ($plano['nome'] == 'Enterprise'): ?>
                            <i class="fas fa-crown"></i> Enterprise
                            <?php elseif ($plano['nome'] == 'Business'): ?>
                            <i class="fas fa-briefcase"></i> Para Empresas
                            <?php else: ?>
                            <i class="fas fa-rocket"></i> Começar Agora
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Comparison Table -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Comparação Detalhada dos Planos
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Recursos</th>
                                    <?php foreach ($planos as $plano): ?>
                                    <th class="text-center"><?php echo htmlspecialchars($plano['nome']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Mesas</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><?php echo $plano['max_mesas'] == -1 ? '<i class="fas fa-infinity text-primary"></i>' : $plano['max_mesas']; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Usuários</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><?php echo $plano['max_usuarios'] == -1 ? '<i class="fas fa-infinity text-primary"></i>' : $plano['max_usuarios']; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Produtos</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><?php echo $plano['max_produtos'] == -1 ? '<i class="fas fa-infinity text-primary"></i>' : $plano['max_produtos']; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Pedidos/Mês</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><?php echo $plano['max_pedidos_mes'] == -1 ? '<i class="fas fa-infinity text-primary"></i>' : number_format($plano['max_pedidos_mes']); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Filiais</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><?php echo $plano['max_filiais'] == -1 ? '<i class="fas fa-infinity text-primary"></i>' : $plano['max_filiais']; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Preço</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center"><strong>R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?></strong></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td><strong>Recursos</strong></td>
                                    <?php foreach ($planos as $plano): ?>
                                    <td class="text-center">
                                        <?php 
                                        $recursos = json_decode($plano['recursos'], true);
                                        if (is_array($recursos)) {
                                            $recursosList = [];
                                            foreach ($recursos as $recurso => $ativo) {
                                                if ($ativo) {
                                                    $recursosList[] = ucfirst(str_replace('_', ' ', $recurso));
                                                }
                                            }
                                            echo implode('<br>', $recursosList);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 text-center mb-5">
                    <h2 class="display-5 fw-bold text-dark mb-3">
                        <i class="fas fa-question-circle text-primary me-3"></i>
                        Perguntas Frequentes
                    </h2>
                    <p class="lead text-muted">
                        Tire suas dúvidas sobre nossos planos e serviços
                    </p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                    <i class="fas fa-rocket me-2 text-primary"></i>
                                    Como funciona o período de teste?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oferecemos 7 dias de teste gratuito para todos os planos. Durante este período, você tem acesso completo a todas as funcionalidades do plano escolhido, sem compromisso de pagamento.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                    <i class="fas fa-credit-card me-2 text-primary"></i>
                                    Quais formas de pagamento são aceitas?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Aceitamos cartões de crédito (Visa, Mastercard, Elo), PIX, boleto bancário e débito automático. Todos os pagamentos são processados de forma segura através do Asaas.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                    <i class="fas fa-sync-alt me-2 text-primary"></i>
                                    Posso alterar meu plano a qualquer momento?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim! Você pode fazer upgrade ou downgrade do seu plano a qualquer momento. As alterações são aplicadas imediatamente e os valores são ajustados proporcionalmente.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                                    <i class="fas fa-shield-alt me-2 text-primary"></i>
                                    Meus dados estão seguros?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Absolutamente! Utilizamos criptografia SSL, backups automáticos e servidores seguros. Seus dados são isolados por tenant e nunca são compartilhados entre estabelecimentos.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
                                    <i class="fas fa-headset me-2 text-primary"></i>
                                    Que tipo de suporte vocês oferecem?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oferecemos suporte por email para o plano Starter, WhatsApp para Professional, suporte prioritário para Business e suporte dedicado 24/7 para Enterprise.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq6">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
                                    <i class="fas fa-mobile-alt me-2 text-primary"></i>
                                    O sistema funciona em dispositivos móveis?
                                </button>
                            </h2>
                            <div id="collapse6" class="accordion-collapse collapse" aria-labelledby="faq6" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim! Nosso sistema é 100% responsivo e funciona perfeitamente em smartphones, tablets e desktops. Você pode gerenciar seu estabelecimento de qualquer lugar.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div>Responsivo</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div>Disponibilidade</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <div>Pedidos/Dia</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <div class="stat-number">99.9%</div>
                        <div>Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="text-white mb-4">Sobre o Sistema</h2>
                    <p class="text-white-50 fs-5">
                        O <?php echo $config->get('app.name'); ?> é um sistema completo de gestão desenvolvido especificamente 
                        para restaurantes e lanchonetes. Com interface moderna e intuitiva, oferece todas as ferramentas 
                        necessárias para gerenciar seu negócio de forma eficiente.
                    </p>
                    <p class="text-white-50 fs-5">
                        Desenvolvido com as mais modernas tecnologias web, o sistema é seguro, escalável e preparado 
                        para crescer junto com seu negócio.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h5><i class="fas fa-utensils me-2"></i><?php echo $config->get('app.name'); ?></h5>
                    <p class="mb-0">Sistema de gestão completo para restaurantes e lanchonetes.</p>
                </div>
                <div class="col-lg-6 text-end">
                    <p class="mb-0">
                        <i class="fas fa-code me-1"></i>
                        Desenvolvido com <i class="fas fa-heart text-danger"></i> para o setor de alimentação
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Observe pricing cards
        document.querySelectorAll('.pricing-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Periodicidade e Descontos
        function updatePricing() {
            const selectedPeriod = document.querySelector('input[name="periodicidade"]:checked').value;
            const cards = document.querySelectorAll('.pricing-card');
            
            cards.forEach(card => {
                const basePrice = parseFloat(card.querySelector('[data-base-price]').getAttribute('data-base-price'));
                const priceValue = card.querySelector('.price-value');
                const periodText = card.querySelector('.period-text');
                const discountInfo = card.querySelector('.discount-info');
                const originalPrice = card.querySelector('.original-price');
                const discountAmount = card.querySelector('.discount-amount');
                
                let newPrice, period, discount = 0, originalPriceValue = 0;
                
                switch(selectedPeriod) {
                    case 'mensal':
                        newPrice = basePrice;
                        period = 'por mês';
                        discountInfo.style.display = 'none';
                        break;
                    case 'semestral':
                        originalPriceValue = basePrice * 6;
                        discount = originalPriceValue * 0.10; // 10% desconto
                        newPrice = (originalPriceValue - discount) / 6;
                        period = 'por mês';
                        discountInfo.style.display = 'block';
                        originalPrice.textContent = `De R$ ${originalPriceValue.toFixed(2).replace('.', ',')}`;
                        discountAmount.textContent = `Economize R$ ${discount.toFixed(2).replace('.', ',')}`;
                        break;
                    case 'anual':
                        originalPriceValue = basePrice * 12;
                        discount = originalPriceValue * 0.20; // 20% desconto
                        newPrice = (originalPriceValue - discount) / 12;
                        period = 'por mês';
                        discountInfo.style.display = 'block';
                        originalPrice.textContent = `De R$ ${originalPriceValue.toFixed(2).replace('.', ',')}`;
                        discountAmount.textContent = `Economize R$ ${discount.toFixed(2).replace('.', ',')}`;
                        break;
                }
                
                priceValue.textContent = newPrice.toFixed(2).replace('.', ',');
                periodText.textContent = period;
            });
        }
        
        // Adicionar event listeners para os radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const periodRadios = document.querySelectorAll('input[name="periodicidade"]');
            periodRadios.forEach(radio => {
                radio.addEventListener('change', updatePricing);
            });
        });

        // Plan selection functionality
        function selectPlan(planId) {
            const selectedPeriod = document.querySelector('input[name="periodicidade"]:checked').value;
            
            // Redirect directly to registration page with plan info
            window.location.href = 'index.php?view=register&plan=' + planId + '&period=' + selectedPeriod;
        }
    </script>
</body>
</html>
