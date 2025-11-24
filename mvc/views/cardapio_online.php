<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = null;
$db = null;

try {
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();

    // Get filial_id from URL parameter
    $filialId = $_GET['filial'] ?? null;
    $tenantId = $_GET['tenant'] ?? null;

    if (!$filialId || !$tenantId) {
        die('Parâmetros inválidos. Acesso: ?view=cardapio_online&tenant=ID&filial=ID');
    }

    // Get filial data with tenant info
    $filial = $db->fetch(
        "SELECT f.*, t.nome as tenant_nome, t.cor_primaria as tenant_cor_primaria, t.asaas_api_key, t.asaas_enabled, t.asaas_api_url
         FROM filiais f
         INNER JOIN tenants t ON f.tenant_id = t.id
         WHERE f.id = ? AND f.tenant_id = ? AND f.cardapio_online_ativo = true AND f.status = 'ativo'",
        [$filialId, $tenantId]
    );

    if (!$filial) {
        die('Cardápio online não disponível para esta filial.');
    }
} catch (\Exception $e) {
    error_log('Cardapio Online Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erro</title></head><body>';
    echo '<h1>Erro ao carregar cardápio online</h1>';
    echo '<p><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
    exit;
}

// Get color from filial_settings or use tenant/filial default
$corSetting = $db->fetch(
    "SELECT setting_value FROM filial_settings WHERE tenant_id = ? AND filial_id = ? AND setting_key = 'cor_primaria'",
    [$tenantId, $filialId]
);

if ($corSetting && $corSetting['setting_value']) {
    $filial['cor_primaria'] = $corSetting['setting_value'];
} elseif (!$filial['cor_primaria']) {
    $filial['cor_primaria'] = $filial['tenant_cor_primaria'] ?? '#FFD700';
}

// Get products - check if column exists first
$hasExibirCardapioColumn = false;
try {
    $columnCheck = $db->fetch("
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'produtos' 
          AND column_name = 'exibir_cardapio_online'
        LIMIT 1
    ");
    $hasExibirCardapioColumn = !empty($columnCheck);
} catch (\Exception $e) {
    // Column check failed, assume it doesn't exist
    $hasExibirCardapioColumn = false;
}

// Build query based on whether column exists
if ($hasExibirCardapioColumn) {
    $produtos = $db->fetchAll(
        "SELECT p.*, c.nome as categoria_nome 
         FROM produtos p 
         LEFT JOIN categorias c ON p.categoria_id = c.id 
         WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true 
           AND (p.exibir_cardapio_online IS NULL OR p.exibir_cardapio_online = true)
         ORDER BY c.nome, p.nome",
        [$tenantId, $filialId]
    );
} else {
    // Column doesn't exist yet - query without it
    $produtos = $db->fetchAll(
        "SELECT p.*, c.nome as categoria_nome 
         FROM produtos p 
         LEFT JOIN categorias c ON p.categoria_id = c.id 
         WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true
         ORDER BY c.nome, p.nome",
        [$tenantId, $filialId]
    );
}

// Group products by category
$produtosPorCategoria = [];
foreach ($produtos as $produto) {
    $categoria = $produto['categoria_nome'] ?? 'Outros';
    if (!isset($produtosPorCategoria[$categoria])) {
        $produtosPorCategoria[$categoria] = [];
    }
    $produtosPorCategoria[$categoria][] = $produto;
}

// Sort categories in descending order (Z to A)
krsort($produtosPorCategoria);

// Parse opening hours - support multiple periods per day
$horarios = json_decode($filial['horario_funcionamento'] ?? '{}', true);
if (empty($horarios)) {
    $horarios = [
        'segunda' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'terca' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'quarta' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'quinta' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'sexta' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'sabado' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]],
        'domingo' => ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]]
    ];
} else {
    foreach ($horarios as $dia => $horarioConfig) {
        if (!is_array($horarioConfig)) {
            $horarios[$dia] = ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]];
            continue;
        }
        
        if (isset($horarioConfig['inicio']) && isset($horarioConfig['fim']) && !isset($horarioConfig['periodos'])) {
            $horarios[$dia] = [
                'aberto' => $horarioConfig['aberto'] ?? true,
                'periodos' => [['inicio' => $horarioConfig['inicio'], 'fim' => $horarioConfig['fim']]]
            ];
        } elseif (!isset($horarioConfig['periodos'])) {
            $horarios[$dia]['periodos'] = [['inicio' => '08:00', 'fim' => '22:00']];
        }
    }
}

// Check if currently open
$isOpen = false;
$diaAtual = strtolower(date('l'));
$diaAtualPt = [
    'monday' => 'segunda',
    'tuesday' => 'terca',
    'wednesday' => 'quarta',
    'thursday' => 'quinta',
    'friday' => 'sexta',
    'saturday' => 'sabado',
    'sunday' => 'domingo'
];
$diaAtualKey = $diaAtualPt[$diaAtual] ?? 'segunda';
$horarioHoje = $horarios[$diaAtualKey] ?? null;

if ($horarioHoje && $horarioHoje['aberto'] && isset($horarioHoje['periodos'])) {
    $horaAtual = date('H:i');
    foreach ($horarioHoje['periodos'] as $periodo) {
        if ($horaAtual >= $periodo['inicio'] && $horaAtual <= $periodo['fim']) {
            $isOpen = true;
            break;
        }
    }
}

// Format opening hours for display
$diasSemana = [
    'segunda' => 'Segunda',
    'terca' => 'Terça',
    'quarta' => 'Quarta',
    'quinta' => 'Quinta',
    'sexta' => 'Sexta',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

// Use filial logo or tenant logo
$logoUrl = $filial['logo_url'] ?? null;
if (!$logoUrl) {
    $tenant = $db->fetch("SELECT logo_url FROM tenants WHERE id = ?", [$tenantId]);
    $logoUrl = $tenant['logo_url'] ?? null;
}

// Get primary color
$primaryColor = $filial['cor_primaria'] ?? '#FFD700';

// Get payment methods - check if there's a configuration
$formasPagamento = [];
if ($filial['aceita_pagamento_online'] || $filial['aceita_pagamento_na_hora']) {
    $formasPagamento[] = ['nome' => 'Dinheiro', 'icone' => 'money-bill'];
    $formasPagamento[] = ['nome' => 'Pix', 'icone' => 'qrcode', 'chave' => 'CNPJ ' . ($filial['cnpj'] ?? '')];
    if ($filial['aceita_pagamento_online']) {
        $formasPagamento[] = ['nome' => 'Cartão de Crédito', 'icone' => 'credit-card'];
        $formasPagamento[] = ['nome' => 'Cartão de Débito', 'icone' => 'credit-card'];
    }
}

// Get delivery maps webhook URL
$deliveryMapsWebhookUrl = $config->getEnv('DELIVERY_MAPS_WEBHOOK_URL') ?? '';

// Parse address for display
$enderecoCompleto = $filial['endereco'] ?? '';
$enderecoParts = explode(',', $enderecoCompleto);
$enderecoRua = trim($enderecoParts[0] ?? '');
$enderecoBairro = '';
$enderecoCidade = '';
if (count($enderecoParts) > 1) {
    $enderecoBairro = trim($enderecoParts[1] ?? '');
}
if (count($enderecoParts) > 2) {
    $enderecoCidade = trim($enderecoParts[2] ?? '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($filial['nome']); ?> - Cardápio Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $primaryColor; ?>;
            --header-bg: <?php echo $primaryColor; ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
        }
        
        /* Header Styles */
        .header-yellow {
            background-color: var(--header-bg);
            padding: 2rem 0;
            text-align: center;
            position: relative;
        }
        
        .header-yellow .logo-container {
            margin-bottom: 1rem;
        }
        
        .header-yellow .logo-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #000;
            background: white;
            padding: 5px;
            margin: 0 auto;
            display: block;
        }
        
        .header-yellow .logo-initials {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #000;
            color: var(--header-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto;
            border: 4px solid #000;
        }
        
        .header-yellow .restaurant-name {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 0.5rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .status-badge.fechado {
            background-color: #dc3545;
            color: white;
        }
        
        .status-badge.aberto {
            background-color: #28a745;
            color: white;
        }
        
        /* Navigation Tabs */
        .nav-tabs-container {
            background: white;
            border-bottom: 2px solid #e0e0e0;
            padding: 0;
        }
        
        .nav-tabs-custom {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .nav-tabs-custom .nav-item {
            flex: 1;
        }
        
        .nav-tabs-custom .nav-link {
            padding: 1rem 1.5rem;
            color: #666;
            text-decoration: none;
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-weight: 500;
        }
        
        .nav-tabs-custom .nav-link:hover {
            color: #333;
            background-color: #f5f5f5;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: #000;
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Products Grid */
            .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: #f5f5f5;
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Cart Button */
        .cart-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
            transition: transform 0.3s;
        }
        
        .cart-button:hover {
            transform: scale(1.1);
        }
        
        .cart-button i {
            font-size: 1.5rem;
            color: #000;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        /* Sidebar */
        .sidebar-right {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            z-index: 1001;
            overflow-y: auto;
            padding: 1.5rem;
            transition: right 0.3s ease;
        }
        
        .sidebar-right.open {
            right: 0;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .close-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .close-sidebar:hover {
            color: #000;
        }
        
        .sidebar-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .sidebar-section:last-child {
            border-bottom: none;
        }
        
        .sidebar-section h4 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-section .icon {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
        }
        
        .cart-items {
            min-height: 100px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .delivery-options select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .alert-closed {
            background-color: #dc3545;
            color: white;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        /* Hours Tab */
        .hours-list {
            list-style: none;
            padding: 0;
        }
        
        .hours-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
        }
        
        .hours-list li:last-child {
            border-bottom: none;
        }
        
        /* Reservation Form */
        .reservation-form {
            max-width: 600px;
        }
        
        .reservation-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .reservation-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .reservation-form input,
        .reservation-form textarea,
        .reservation-form select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .reservation-form textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-reserve {
            background-color: var(--primary-color);
            color: #000;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-reserve:hover {
            opacity: 0.9;
        }
        
        /* Checkout Modal */
        .checkout-modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .checkout-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close-checkout {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .checkout-step {
            display: block;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #eee;
            border-radius: 10px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .payment-option:hover {
            border-color: var(--primary-color);
        }
        
        .payment-option input[type="radio"]:checked + label {
            color: var(--primary-color);
        }
        
        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .payment-option label {
            flex: 1;
            cursor: pointer;
        }
        
        /* Info Tab */
        .info-section {
            margin-bottom: 2rem;
        }
        
        .info-section h5 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .info-item {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .info-item i {
            color: var(--primary-color);
            margin-top: 0.25rem;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .payment-method i {
            font-size: 1.5rem;
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar-right {
                width: 100%;
                right: -100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .header-yellow .restaurant-name {
                font-size: 1.8rem;
            }
            
            .cart-button {
                bottom: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Yellow -->
    <div class="header-yellow">
        <div class="logo-container">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-initials" style="display: none;">
                        <?php echo strtoupper(substr($filial['nome'], 0, 2)); ?>
                    </div>
                <?php else: ?>
                <div class="logo-initials">
                        <?php echo strtoupper(substr($filial['nome'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
                </div>
        <h1 class="restaurant-name"><?php echo htmlspecialchars($filial['nome']); ?></h1>
        <span class="status-badge <?php echo $isOpen ? 'aberto' : 'fechado'; ?>">
            <?php echo $isOpen ? 'Aberto' : 'Fechado'; ?>
        </span>
            </div>
    
    <!-- Navigation Tabs -->
    <div class="nav-tabs-container">
        <ul class="nav-tabs-custom">
            <li class="nav-item">
                <a class="nav-link active" data-tab="itens">Itens</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="horarios">Horários</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="reservas">Reservas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-tab="informacoes">Informações</a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content-wrapper">
        <!-- Tab: Itens -->
        <div id="tab-itens" class="tab-content active">
            <?php if (empty($produtosPorCategoria)): ?>
                <p class="text-center text-muted mt-4">Nenhum produto disponível no momento.</p>
            <?php else: ?>
                <?php foreach ($produtosPorCategoria as $categoria => $produtosCategoria): ?>
                    <h3 class="mt-4 mb-3"><?php echo htmlspecialchars($categoria); ?></h3>
                    <div class="products-grid">
                        <?php foreach ($produtosCategoria as $produto): ?>
                            <div class="product-card" onclick="addToCart(<?php echo htmlspecialchars(json_encode($produto)); ?>)">
                                <?php if ($produto['imagem']): ?>
                                    <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                                        <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                    </div>
                <?php endif; ?>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                    <div class="product-price">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></div>
                    </div>
                </div>
                        <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
            
        <!-- Tab: Horários -->
        <div id="tab-horarios" class="tab-content">
            <h3 class="mb-4">Horário de funcionamento</h3>
            <ul class="hours-list">
                    <?php foreach ($diasSemana as $diaKey => $diaNome): 
                        $horario = $horarios[$diaKey] ?? ['aberto' => false, 'periodos' => []];
                        $periodos = $horario['periodos'] ?? [];
                    ?>
                    <li>
                        <span><strong><?php echo $diaNome; ?></strong></span>
                        <span>
                            <?php if ($horario['aberto'] && !empty($periodos)): ?>
                                <?php 
                                $periodosStr = [];
                                foreach ($periodos as $periodo) {
                                    $periodosStr[] = $periodo['inicio'] . ' - ' . $periodo['fim'];
                                }
                                echo implode(' / ', $periodosStr);
                                ?>
                            <?php else: ?>
                                <span class="text-muted">Fechado</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Tab: Reservas -->
        <div id="tab-reservas" class="tab-content">
            <h3 class="mb-4">Informações para reservar uma mesa</h3>
            <form class="reservation-form" id="reservationForm">
                <div class="form-group">
                    <label for="numConvidados">Número de convidados</label>
                    <input type="number" id="numConvidados" name="num_convidados" min="1" required>
            </div>
                <div class="form-group">
                    <label for="dataReserva">Data da Reserva</label>
                    <input type="date" id="dataReserva" name="data_reserva" required>
        </div>
                <div class="form-group">
                    <label for="horaReserva">Hora</label>
                    <input type="time" id="horaReserva" name="hora_reserva" required>
                                </div>
                <h5 class="mt-4 mb-3">Informações de contato</h5>
                <div class="form-group">
                    <label for="nomeReserva">Nome</label>
                    <input type="text" id="nomeReserva" name="nome" required>
                                    </div>
                <div class="form-group">
                    <label for="emailReserva">E-mail</label>
                    <input type="email" id="emailReserva" name="email">
                                </div>
                <div class="form-group">
                    <label for="celularReserva">Celular</label>
                    <input type="tel" id="celularReserva" name="celular" required>
                            </div>
                <div class="form-group">
                    <label for="instrucoesReserva">Suas Instruções</label>
                    <textarea id="instrucoesReserva" name="instrucoes" placeholder="Observações especiais..."></textarea>
                        </div>
                <button type="submit" class="btn-reserve">Reservar Mesa</button>
            </form>
    </div>
    
        <!-- Tab: Informações -->
        <div id="tab-informacoes" class="tab-content">
            <div class="info-section">
                <h5><i class="fas fa-map-marker-alt"></i> Endereço</h5>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <div><?php echo htmlspecialchars($enderecoRua); ?></div>
                        <?php if ($enderecoBairro): ?>
                            <div>Bairro: <?php echo htmlspecialchars($enderecoBairro); ?></div>
                        <?php endif; ?>
                        <?php if ($enderecoCidade): ?>
                            <div><?php echo htmlspecialchars($enderecoCidade); ?></div>
                        <?php endif; ?>
        </div>
        </div>
    </div>
    
            <div class="info-section">
                <h5><i class="fas fa-clock"></i> Tempo de Preparo</h5>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <div>Estimativa de retirada: <?php echo $filial['tempo_medio_preparo'] ?? 20; ?> minutos</div>
                        <div>Estimativa de entrega: <?php echo ($filial['tempo_medio_preparo'] ?? 20) + 10; ?> minutos</div>
                </div>
            </div>
            </div>
            
            <div class="info-section">
                <h5>FORMAS DE PAGAMENTO:</h5>
                <div class="payment-methods">
                    <?php foreach ($formasPagamento as $forma): ?>
                        <div class="payment-method">
                            <i class="fas fa-<?php echo $forma['icone']; ?>"></i>
                            <div>
                                <div><strong><?php echo $forma['nome']; ?></strong></div>
                                <?php if (isset($forma['chave'])): ?>
                                    <small class="text-muted"><?php echo $forma['chave']; ?></small>
                                <?php endif; ?>
                </div>
                </div>
                    <?php endforeach; ?>
            </div>
                </div>
                </div>
            </div>
            
    <!-- Cart Button -->
    <div class="cart-button" onclick="toggleSidebar()">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar Right -->
    <div class="sidebar-right" id="sidebarRight">
        <div class="sidebar-header">
            <h3>Seu pedido</h3>
            <button class="close-sidebar" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-section">
            <div class="cart-items" id="cartItems">
                <p class="text-muted">Nenhum item adicionado ainda</p>
            </div>
        </div>
        
        <div class="sidebar-section">
            <h4>
                <span class="icon"><i class="fas fa-user"></i></span>
                Opções de entrega
            </h4>
            <select class="form-control" id="deliveryTypeSelect">
                <option value="">(Selecione aqui)</option>
                <option value="pickup">Retirar no Balcão</option>
                <option value="delivery">Delivery</option>
            </select>
            <?php if (!$isOpen): ?>
                <div class="alert-closed">
                    No momento, este comerciante está fechado. Verifique o horário de funcionamento.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Show/hide content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById('tab-' + tabName).classList.add('active');
            });
        });
        
        // Cart management
        let cart = JSON.parse(localStorage.getItem('cart_<?php echo $filialId; ?>')) || [];
        
        function updateCart() {
            localStorage.setItem('cart_<?php echo $filialId; ?>', JSON.stringify(cart));
            updateCartUI();
        }
        
        function updateCartUI() {
            const cartItems = document.getElementById('cartItems');
            const cartBadge = document.getElementById('cartBadge');
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            
            // Update badge
            if (totalItems > 0) {
                cartBadge.textContent = totalItems;
                cartBadge.style.display = 'flex';
            } else {
                cartBadge.style.display = 'none';
            }
            
            if (cart.length === 0) {
                cartItems.innerHTML = '<p class="text-muted">Nenhum item adicionado ainda</p>';
                return;
            }
            
            let html = '';
            let total = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = parseFloat(item.preco_normal || 0) * (item.quantity || 1);
                total += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div>
                            <div><strong>${item.nome}</strong></div>
                            <small>R$ ${parseFloat(item.preco_normal || 0).toFixed(2).replace('.', ',')} x ${item.quantity || 1}</small>
                        </div>
                        <div>
                            <button onclick="removeFromCart(${index})" style="background: none; border: none; color: #dc3545; cursor: pointer;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #e0e0e0;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem;">
                        <span>Total:</span>
                        <span>R$ ${total.toFixed(2).replace('.', ',')}</span>
                    </div>
                    <button class="btn-reserve" style="margin-top: 1rem;" onclick="finalizarPedido()">Finalizar Pedido</button>
                </div>
            `;
            
            cartItems.innerHTML = html;
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarRight');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
        
        function addToCart(product) {
            if (!product || !product.id) {
                alert('Erro: Produto inválido');
                return;
            }
            
            const existingItem = cart.find(item => item.id === product.id);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    nome: product.nome,
                    preco_normal: parseFloat(product.preco_normal) || 0,
                    quantity: 1
                });
            }
            
            updateCart();
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
                    updateCart();
        }
        
        function finalizarPedido() {
            if (cart.length === 0) {
                alert('Seu carrinho está vazio!');
                return;
            }
            
            const deliveryType = document.getElementById('deliveryTypeSelect').value;
            if (!deliveryType) {
                alert('Por favor, selecione uma opção de entrega');
                return;
            }
            
            if (!<?php echo $isOpen ? 'true' : 'false'; ?>) {
                alert('O estabelecimento está fechado no momento. Verifique o horário de funcionamento.');
                return;
            }
            
            // Show checkout modal
            showCheckoutModal();
        }
        
        function showCheckoutModal() {
            // Create modal HTML
            const modalHtml = `
                <div class="checkout-modal" id="checkoutModal">
                    <div class="checkout-content">
                        <button class="close-checkout" onclick="closeCheckoutModal()">
                            <i class="fas fa-times"></i>
                        </button>
                        <h2 class="mb-4">Finalizar Pedido</h2>
                        
                        <div id="checkoutStep1" class="checkout-step">
                            <h5 class="mb-3">Informe seu telefone</h5>
                            <div class="input-group mb-3">
                                <input type="tel" class="form-control" id="customerPhone" placeholder="(11) 99999-9999" required>
                                <button class="btn btn-primary" onclick="buscarCliente()">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                            <div id="clienteSearchResult" class="alert" style="display: none;"></div>
                        </div>
                        
                        <div id="checkoutStep2" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Dados do Cliente</h5>
                            <input type="text" class="form-control mb-2" id="customerName" placeholder="Nome completo" required>
                            <input type="email" class="form-control mb-2" id="customerEmail" placeholder="E-mail (opcional)">
                            <input type="text" class="form-control mb-2" id="customerCpf" placeholder="CPF (opcional)">
                            <button class="btn btn-primary w-100 mt-2" onclick="proximoPasso(2)">Continuar</button>
                        </div>
                        
                        <div id="checkoutStep3" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Endereço de Entrega</h5>
                            <div id="enderecoSection" style="display: none;">
                                <input type="text" class="form-control mb-2" id="deliveryAddress" placeholder="Rua, número, complemento">
                                <input type="text" class="form-control mb-2" id="deliveryNeighborhood" placeholder="Bairro">
                                <input type="text" class="form-control mb-2" id="deliveryCity" placeholder="Cidade">
                                <input type="text" class="form-control mb-2" id="deliveryCEP" placeholder="CEP">
                                <input type="text" class="form-control mb-2" id="deliveryEstado" placeholder="Estado (UF)">
                                <button class="btn btn-primary w-100 mt-2" onclick="calcularTaxaEntrega()">Calcular Taxa de Entrega</button>
                            </div>
                            <button class="btn btn-primary w-100 mt-2" onclick="proximoPasso(3)">Continuar</button>
                        </div>
                        
                        <div id="checkoutStep4" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Forma de Pagamento</h5>
                            <?php if ($filial['aceita_pagamento_online']): ?>
                                <div class="payment-option" onclick="selectPayment('online')">
                                    <input type="radio" name="paymentMethod" value="online" id="paymentOnline">
                                    <label for="paymentOnline">
                                        <strong>Pagar Online</strong>
                                        <br><small class="text-muted">PIX, Cartão via Asaas</small>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <?php if ($filial['aceita_pagamento_na_hora']): ?>
                                <div class="payment-option" onclick="selectPayment('on_delivery')">
                                    <input type="radio" name="paymentMethod" value="on_delivery" id="paymentOnDelivery" checked>
                                    <label for="paymentOnDelivery">
                                        <strong>Pagar na Hora</strong>
                                        <br><small class="text-muted">Na retirada ou entrega</small>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-primary w-100 mt-3" onclick="proximoPasso(4)">Continuar</button>
                        </div>
                        
                        <div id="checkoutStep5" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Resumo do Pedido</h5>
                            <div id="orderSummary"></div>
                            <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                                <strong>Total:</strong>
                                <strong id="orderTotal">R$ 0,00</strong>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-secondary flex-fill" onclick="voltarPasso()">Voltar</button>
                                <button class="btn btn-primary flex-fill" onclick="submitOrder()">Confirmar Pedido</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal
            const existingModal = document.getElementById('checkoutModal');
            if (existingModal) existingModal.remove();
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show first step
            currentCheckoutStep = 1;
            mostrarPasso(1);
        }
        
        let currentCheckoutStep = 1;
        let clienteData = null;
        let deliveryFee = 0;
        let paymentMethod = 'on_delivery';
        
        function mostrarPasso(step) {
            for (let i = 1; i <= 5; i++) {
                const stepEl = document.getElementById('checkoutStep' + i);
                if (stepEl) stepEl.style.display = 'none';
            }
            const currentStepEl = document.getElementById('checkoutStep' + step);
            if (currentStepEl) {
                currentStepEl.style.display = 'block';
                currentCheckoutStep = step;
            }
        }
        
        function proximoPasso(fromStep) {
            if (fromStep === 1) {
                const phone = document.getElementById('customerPhone').value.trim();
                if (!phone) {
                    alert('Por favor, informe o telefone');
                    return;
                }
            } else if (fromStep === 2) {
                const name = document.getElementById('customerName').value.trim();
                if (!name) {
                    alert('Por favor, informe o nome');
                    return;
                }
            } else if (fromStep === 3) {
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                if (deliveryType === 'delivery') {
                    const address = document.getElementById('deliveryAddress').value.trim();
                    const city = document.getElementById('deliveryCity').value.trim();
                    if (!address || !city) {
                        alert('Por favor, preencha o endereço de entrega');
                        return;
                    }
                }
            }
            
            if (fromStep === 3) {
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                if (deliveryType === 'delivery') {
                    document.getElementById('enderecoSection').style.display = 'block';
                }
            }
            
            if (fromStep === 4) {
                mostrarPasso(5);
                updateOrderSummary();
                return;
            }
            
            mostrarPasso(fromStep + 1);
        }
        
        function voltarPasso() {
            if (currentCheckoutStep > 1) {
                mostrarPasso(currentCheckoutStep - 1);
            }
        }
        
        function closeCheckoutModal() {
            const modal = document.getElementById('checkoutModal');
            if (modal) modal.remove();
            currentCheckoutStep = 1;
            clienteData = null;
        }
        
        async function buscarCliente() {
            const telefone = document.getElementById('customerPhone').value.trim();
            if (!telefone) {
                alert('Por favor, informe o telefone');
                return;
            }
            
            const resultDiv = document.getElementById('clienteSearchResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-info';
            resultDiv.textContent = 'Buscando cliente...';
            
            try {
                const url = new URL('mvc/ajax/clientes_cardapio_online.php', window.location.origin);
                url.searchParams.set('action', 'buscar_cliente_cardapio');
                url.searchParams.set('tenant_id', <?php echo $tenantId; ?>);
                url.searchParams.set('telefone', telefone);
                
                const response = await fetch(url.toString());
                const data = await response.json();
                
                if (data.success && data.cliente) {
                    clienteData = data.cliente;
                    document.getElementById('customerName').value = data.cliente.nome || '';
                    document.getElementById('customerEmail').value = data.cliente.email || '';
                    document.getElementById('customerCpf').value = data.cliente.cpf || '';
                    
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = '✅ Cliente encontrado: ' + data.cliente.nome;
                    
                    setTimeout(() => {
                        proximoPasso(1);
                    }, 1000);
                } else {
                    // Cliente não encontrado - continuar para cadastro
                    clienteData = null;
                    resultDiv.className = 'alert alert-warning';
                    resultDiv.innerHTML = 'ℹ️ Cliente não encontrado. Você será cadastrado ao continuar.';
                    
                    document.getElementById('customerName').value = '';
                    document.getElementById('customerEmail').value = '';
                    document.getElementById('customerCpf').value = '';
                    
                    setTimeout(() => {
                        proximoPasso(1);
                    }, 1500);
                }
            } catch (error) {
                console.error('Erro ao buscar cliente:', error);
                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Erro ao buscar cliente. Tente novamente.';
            }
        }
        
        function selectPayment(method) {
            paymentMethod = method;
            document.getElementById('payment' + (method === 'online' ? 'Online' : 'OnDelivery')).checked = true;
        }
        
        async function calcularTaxaEntrega() {
            const address = document.getElementById('deliveryAddress').value;
            const city = document.getElementById('deliveryCity').value;
            
            if (!address || !city) {
                alert('Por favor, preencha pelo menos o endereço e a cidade.');
                return;
            }
            
            const filialData = {
                id: <?php echo $filialId; ?>,
                tenantId: <?php echo $tenantId; ?>,
                endereco: <?php echo json_encode($filial['endereco']); ?>,
                usar_calculo_distancia: <?php echo $filial['usar_calculo_distancia'] ? 'true' : 'false'; ?>,
                taxa_delivery_fixa: <?php echo $filial['taxa_delivery_fixa'] ?? 0; ?>
            };
            
            if (filialData.usar_calculo_distancia) {
                const deliveryMapsWebhookUrl = <?php echo json_encode($deliveryMapsWebhookUrl); ?>;
                if (deliveryMapsWebhookUrl) {
                    try {
                        const response = await fetch(deliveryMapsWebhookUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                origin: filialData.endereco,
                                destination: `${address}, ${city}`
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success && data.distancia && data.valor) {
                            deliveryFee = parseFloat(data.valor);
                            alert('Taxa calculada: R$ ' + deliveryFee.toFixed(2).replace('.', ','));
                        } else {
                            throw new Error('Erro ao calcular distância');
                        }
                    } catch (error) {
                        console.error('Erro ao calcular distância:', error);
                        alert('Erro ao calcular taxa de entrega. Usando taxa fixa.');
                        deliveryFee = filialData.taxa_delivery_fixa;
                    }
                } else {
                    deliveryFee = filialData.taxa_delivery_fixa;
                }
            } else {
                deliveryFee = filialData.taxa_delivery_fixa;
            }
        }
        
        function updateOrderSummary() {
            const summary = document.getElementById('orderSummary');
            const total = document.getElementById('orderTotal');
            
            let subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.preco_normal || 0) * item.quantity), 0);
            const deliveryType = document.getElementById('deliveryTypeSelect').value;
            let totalValue = subtotal + (deliveryType === 'delivery' ? deliveryFee : 0);
            
            summary.innerHTML = `
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>R$ ${subtotal.toFixed(2).replace('.', ',')}</span>
                </div>
                ${deliveryType === 'delivery' ? `
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de Entrega:</span>
                        <span>R$ ${deliveryFee.toFixed(2).replace('.', ',')}</span>
                    </div>
                ` : ''}
            `;
            
            total.textContent = `R$ ${totalValue.toFixed(2).replace('.', ',')}`;
        }
        
        async function submitOrder() {
            const customerName = document.getElementById('customerName').value.trim();
            const customerPhone = document.getElementById('customerPhone').value.trim();
            const customerEmail = document.getElementById('customerEmail').value.trim();
            const customerCpf = document.getElementById('customerCpf').value.trim();
            
            if (!customerName || !customerPhone) {
                alert('Por favor, preencha nome e telefone.');
                return;
            }
            
            const deliveryType = document.getElementById('deliveryTypeSelect').value;
            let enderecoEntrega = null;
            
            if (deliveryType === 'delivery') {
                const address = document.getElementById('deliveryAddress').value.trim();
                const city = document.getElementById('deliveryCity').value.trim();
                if (!address || !city) {
                    alert('Por favor, preencha o endereço de entrega.');
                    return;
                }
                
                enderecoEntrega = {
                    endereco: address,
                    bairro: document.getElementById('deliveryNeighborhood').value.trim(),
                    cidade: city,
                    cep: document.getElementById('deliveryCEP').value.trim(),
                    estado: document.getElementById('deliveryEstado').value.trim()
                };
            }
            
            const itensDetalhados = cart.map(item => ({
                id: item.id,
                quantity: item.quantity,
                preco: item.preco_normal,
                observacao: item.observacao || '',
                ingredientes_adicionados: item.ingredientes || [],
                ingredientes_removidos: []
            }));
            
            const orderData = {
                filial_id: <?php echo $filialId; ?>,
                tenant_id: <?php echo $tenantId; ?>,
                itens: itensDetalhados,
                tipo_entrega: deliveryType,
                taxa_entrega: deliveryFee,
                cliente_nome: customerName,
                cliente_telefone: customerPhone,
                cliente_email: customerEmail,
                cliente_cpf: customerCpf,
                cliente_id: clienteData ? clienteData.id : null,
                endereco_entrega: enderecoEntrega,
                forma_pagamento: paymentMethod
            };
            
            try {
                const response = await fetch('mvc/ajax/pedidos_online.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (paymentMethod === 'online' && result.payment_url) {
                        window.location.href = result.payment_url;
                    } else {
                        alert('Pedido criado com sucesso! Número do pedido: ' + result.pedido_id);
                        cart = [];
                        updateCart();
                        closeCheckoutModal();
                        toggleSidebar();
                        window.location.reload();
                    }
                } else {
                    alert('Erro ao criar pedido: ' + result.message);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao processar pedido. Tente novamente.');
            }
        }
        
        // Reservation form
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            // Here you would implement the reservation submission
            alert('Funcionalidade de reserva será implementada em breve.');
        });
        
        // Close sidebar when clicking outside
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });
        
        // Initialize cart UI
        updateCartUI();
    </script>
</body>
</html>
