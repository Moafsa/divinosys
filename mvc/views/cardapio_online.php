<?php
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

// Get color from filial_settings or use tenant/filial default
$corSetting = $db->fetch(
    "SELECT setting_value FROM filial_settings WHERE tenant_id = ? AND filial_id = ? AND setting_key = 'cor_primaria'",
    [$tenantId, $filialId]
);

if ($corSetting && $corSetting['setting_value']) {
    $filial['cor_primaria'] = $corSetting['setting_value'];
} elseif (!$filial['cor_primaria']) {
    $filial['cor_primaria'] = $filial['tenant_cor_primaria'] ?? '#007bff';
}

// Get products
$produtos = $db->fetchAll(
    "SELECT p.*, c.nome as categoria_nome 
     FROM produtos p 
     LEFT JOIN categorias c ON p.categoria_id = c.id 
     WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true
     ORDER BY c.nome, p.nome",
    [$tenantId, $filialId]
);

// Group products by category
$produtosPorCategoria = [];
foreach ($produtos as $produto) {
    $categoria = $produto['categoria_nome'] ?? 'Outros';
    if (!isset($produtosPorCategoria[$categoria])) {
        $produtosPorCategoria[$categoria] = [];
    }
    $produtosPorCategoria[$categoria][] = $produto;
}

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
    // Migrate old format to new format if needed
    foreach ($horarios as $dia => $config) {
        if (!is_array($config)) {
            $horarios[$dia] = ['aberto' => true, 'periodos' => [['inicio' => '08:00', 'fim' => '22:00']]];
            continue;
        }
        
        if (isset($config['inicio']) && isset($config['fim']) && !isset($config['periodos'])) {
            // Old format - convert to new format
            $horarios[$dia] = [
                'aberto' => $config['aberto'] ?? true,
                'periodos' => [['inicio' => $config['inicio'], 'fim' => $config['fim']]]
            ];
        } elseif (!isset($config['periodos'])) {
            // Ensure periodos exists
            $horarios[$dia]['periodos'] = [['inicio' => '08:00', 'fim' => '22:00']];
        }
    }
}

// Check if currently open - support multiple periods
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

// Use filial logo or tenant logo, or default
$logoUrl = $filial['logo_url'] ?? null;
if (!$logoUrl) {
    $tenant = $db->fetch("SELECT logo_url FROM tenants WHERE id = ?", [$tenantId]);
    $logoUrl = $tenant['logo_url'] ?? null;
}

// Use filial color (already set above)
$primaryColor = $filial['cor_primaria'] ?? '#007bff';

// Get delivery maps webhook URL from environment
$deliveryMapsWebhookUrl = $config->getEnv('DELIVERY_MAPS_WEBHOOK_URL') ?? '';
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
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding-bottom: 100px;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), #ff8c5a);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: white;
            padding: 5px;
        }
        
        .store-info h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }
        
        .store-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .container-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .store-banner {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .store-banner h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .info-row {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }
        
        .info-item i {
            color: var(--primary-color);
        }
        
        .category-section {
            margin-bottom: 3rem;
        }
        
        .category-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .products-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.75rem;
        }
        
        /* Responsive grid for mobile - show more columns */
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
            
            .product-card {
                min-width: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .product-image {
                height: 150px;
            }
            
            .product-info {
                padding: 0.75rem;
            }
            
            .product-name {
                font-size: 1rem;
            }
            
            .product-description {
                font-size: 0.85rem;
            }
        }
        
        @media (min-width: 481px) and (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1rem;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 1.25rem;
            }
        }
        
        @media (min-width: 1025px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
                gap: 1.5rem;
            }
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            width: 100%;
            max-width: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .product-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-footer .d-flex {
            display: flex;
            gap: 0.25rem;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .add-to-cart-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: #ff8c5a;
        }
        
        .cart-sidebar {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            z-index: 2000;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .cart-sidebar.open {
            right: 0;
        }
        
        .cart-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .close-cart {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .cart-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .cart-item-price {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-btn {
            background: #f5f5f5;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .quantity-btn:hover {
            background: #e0e0e0;
        }
        
        .cart-footer {
            padding: 1.5rem;
            border-top: 2px solid #eee;
            background: #f9f9f9;
        }
        
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .checkout-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .checkout-btn:hover {
            background: #ff8c5a;
        }
        
        .checkout-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .checkout-modal.show {
            display: flex;
        }
        
        .checkout-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .delivery-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #eee;
            border-radius: 10px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        
        .delivery-option.selected {
            border-color: var(--primary-color);
            background: #fff5f0;
        }
        
        .delivery-option input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .cart-sidebar {
                width: 100%;
                right: -100%;
            }
            
            .store-info h1 {
                font-size: 1.2rem;
            }
            
            .info-row {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="logo-img" style="display: none; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                        <?php echo strtoupper(substr($filial['nome'], 0, 2)); ?>
                    </div>
                <?php else: ?>
                    <div class="logo-img" style="display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                        <?php echo strtoupper(substr($filial['nome'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
                <div class="store-info">
                    <h1><?php echo htmlspecialchars($filial['nome']); ?></h1>
                    <p><?php echo htmlspecialchars($filial['endereco'] ?? ''); ?></p>
                </div>
            </div>
            <div class="cart-icon" onclick="toggleCart()">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge">0</span>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container-main">
        <!-- Store Banner -->
        <div class="store-banner">
            <h2>Sobre o Estabelecimento</h2>
            <div class="info-row">
                <?php if ($filial['telefone']): ?>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($filial['telefone']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($filial['endereco']): ?>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($filial['endereco']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <span>Tempo médio: <?php echo $filial['tempo_medio_preparo'] ?? 30; ?> min</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-<?php echo $isOpen ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                    <span><strong><?php echo $isOpen ? 'Aberto agora' : 'Fechado'; ?></strong></span>
                </div>
            </div>
            
            <!-- Opening Hours -->
            <div class="mt-3">
                <h6 class="mb-2"><i class="fas fa-calendar-alt me-2"></i>Horários de Funcionamento</h6>
                <div class="row">
                    <?php foreach ($diasSemana as $diaKey => $diaNome): 
                        $horario = $horarios[$diaKey] ?? ['aberto' => false, 'periodos' => []];
                        $periodos = $horario['periodos'] ?? [];
                    ?>
                    <div class="col-md-6 col-lg-4 mb-2">
                        <small>
                            <strong><?php echo $diaNome; ?>:</strong>
                            <?php if ($horario['aberto'] && !empty($periodos)): ?>
                                <?php 
                                $periodosStr = [];
                                foreach ($periodos as $periodo) {
                                    $periodosStr[] = $periodo['inicio'] . ' - ' . $periodo['fim'];
                                }
                                echo implode(' e ', $periodosStr);
                                ?>
                            <?php else: ?>
                                <span class="text-muted">Fechado</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="mb-4">
            <div class="input-group" style="max-width: 500px; margin: 0 auto;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="productSearch" placeholder="Buscar produtos..." onkeyup="filterProducts()">
            </div>
        </div>
        
        <!-- Products by Category -->
        <?php foreach ($produtosPorCategoria as $categoria => $produtosCategoria): ?>
            <div class="category-section">
                <h2 class="category-title"><?php echo htmlspecialchars($categoria); ?></h2>
                <div class="products-grid">
                    <?php foreach ($produtosCategoria as $produto): ?>
                        <div class="product-card" data-product-name="<?php echo htmlspecialchars(strtolower($produto['nome'])); ?>" onclick="addToCartDirect(<?php echo htmlspecialchars(json_encode($produto)); ?>)">
                            <?php if ($produto['imagem']): ?>
                                <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                <?php if ($produto['descricao']): ?>
                                    <div class="product-description"><?php echo htmlspecialchars($produto['descricao']); ?></div>
                                <?php endif; ?>
                                <div class="product-footer">
                                    <div class="product-price">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></div>
                                    <div class="d-flex gap-1">
                                        <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCartDirect(<?php echo htmlspecialchars(json_encode($produto)); ?>)">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); openProductCustomization(<?php echo $produto['id']; ?>)" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3>Carrinho</h3>
            <button class="close-cart" onclick="toggleCart()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cart-body" id="cartBody">
            <p class="text-center text-muted mt-4">Seu carrinho está vazio</p>
        </div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">R$ 0,00</span>
            </div>
            <button class="checkout-btn" onclick="openCheckout()">Finalizar Pedido</button>
        </div>
    </div>
    
    <!-- Checkout Modal -->
    <div class="checkout-modal" id="checkoutModal">
        <div class="checkout-content">
            <button class="btn btn-link position-absolute top-0 end-0 p-2" onclick="closeCheckout()" style="z-index: 10;">
                <i class="fas fa-times"></i>
            </button>
            <h2 class="mb-4">Finalizar Pedido</h2>
            
            <!-- Step 1: Phone Number -->
            <div id="checkoutStep1" class="checkout-step">
                <h5 class="mb-3">Informe seu telefone</h5>
                <div class="input-group mb-3">
                    <input type="tel" class="form-control" id="customerPhone" placeholder="(11) 99999-9999" required>
                    <button class="btn btn-primary" onclick="buscarClientePorTelefone()">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                <div id="clienteSearchResult" class="alert" style="display: none;"></div>
            </div>
            
            <!-- Step 2: Customer Data -->
            <div id="checkoutStep2" class="checkout-step" style="display: none;">
                <h5 class="mb-3">Dados do Cliente</h5>
                <input type="text" class="form-control mb-2" id="customerName" placeholder="Nome completo" required>
                <input type="email" class="form-control mb-2" id="customerEmail" placeholder="E-mail (opcional)">
                <input type="text" class="form-control mb-2" id="customerCpf" placeholder="CPF (opcional)">
                <button class="btn btn-primary w-100 mt-2" onclick="proximoPassoCheckout(2)">Continuar</button>
            </div>
            
            <!-- Step 3: Delivery Options -->
            <div id="checkoutStep3" class="checkout-step" style="display: none;">
                <h5 class="mb-3">Tipo de Entrega</h5>
                <div class="delivery-option" onclick="selectDeliveryType('pickup')">
                    <input type="radio" name="deliveryType" value="pickup" id="pickup" checked>
                    <label for="pickup" style="flex: 1; cursor: pointer;">
                        <strong>Retirar no Balcão</strong>
                        <br>
                        <small class="text-muted">Sem taxa de entrega</small>
                    </label>
                </div>
                <div class="delivery-option" onclick="selectDeliveryType('delivery')">
                    <input type="radio" name="deliveryType" value="delivery" id="delivery">
                    <label for="delivery" style="flex: 1; cursor: pointer;">
                        <strong>Delivery</strong>
                        <br>
                        <small class="text-muted" id="deliveryFeeText">Taxa será calculada</small>
                    </label>
                </div>
                <button class="btn btn-primary w-100 mt-3" onclick="proximoPassoCheckout(3)">Continuar</button>
            </div>
            
            <!-- Step 4: Delivery Address (if delivery selected) -->
            <div id="checkoutStep4" class="checkout-step" style="display: none;">
                <h5 class="mb-3">Endereço de Entrega</h5>
                <div id="enderecosCadastrados" class="mb-3" style="display: none;">
                    <label class="form-label">Escolher endereço cadastrado:</label>
                    <select class="form-control mb-2" id="enderecoSelecionado" onchange="selecionarEnderecoCadastrado()">
                        <option value="">Selecione um endereço...</option>
                    </select>
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="document.getElementById('novoEnderecoSection').style.display='block'; document.getElementById('enderecosCadastrados').style.display='none';">
                        <i class="fas fa-plus"></i> Usar novo endereço
                    </button>
                </div>
                <div id="novoEnderecoSection" style="display: none;">
                    <input type="text" class="form-control mb-2" id="deliveryAddress" placeholder="Rua, número, complemento">
                    <input type="text" class="form-control mb-2" id="deliveryNeighborhood" placeholder="Bairro">
                    <input type="text" class="form-control mb-2" id="deliveryCity" placeholder="Cidade">
                    <input type="text" class="form-control mb-2" id="deliveryCEP" placeholder="CEP">
                    <input type="text" class="form-control mb-2" id="deliveryEstado" placeholder="Estado (UF)">
                    <input type="text" class="form-control mb-2" id="deliveryReferencia" placeholder="Ponto de referência (opcional)">
                </div>
                <button class="btn btn-primary w-100 mt-2" onclick="calcularTaxaEntrega()">Calcular Taxa de Entrega</button>
                <button class="btn btn-secondary w-100 mt-2" onclick="proximoPassoCheckout(4)">Continuar</button>
            </div>
            
            <!-- Step 5: Payment Method -->
            <div id="checkoutStep5" class="checkout-step" style="display: none;">
                <h5 class="mb-3">Forma de Pagamento</h5>
                <?php if ($filial['aceita_pagamento_online']): ?>
                    <div class="delivery-option" onclick="selectPaymentMethod('online')">
                        <input type="radio" name="paymentMethod" value="online" id="paymentOnline">
                        <label for="paymentOnline" style="flex: 1; cursor: pointer;">
                            <strong>Pagar Online</strong>
                            <br>
                            <small class="text-muted">PIX, Cartão via Asaas</small>
                        </label>
                    </div>
                <?php endif; ?>
                <?php if ($filial['aceita_pagamento_na_hora']): ?>
                    <div class="delivery-option" onclick="selectPaymentMethod('on_delivery')">
                        <input type="radio" name="paymentMethod" value="on_delivery" id="paymentOnDelivery" checked>
                        <label for="paymentOnDelivery" style="flex: 1; cursor: pointer;">
                            <strong>Pagar na Hora</strong>
                            <br>
                            <small class="text-muted">Na retirada ou entrega</small>
                        </label>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100 mt-3" onclick="proximoPassoCheckout(5)">Continuar</button>
            </div>
            
            <!-- Step 6: Order Summary -->
            <div id="checkoutStep6" class="checkout-step" style="display: none;">
                <h5 class="mb-3">Resumo do Pedido</h5>
                <div id="orderSummary"></div>
                <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                    <strong>Total:</strong>
                    <strong id="orderTotal">R$ 0,00</strong>
                </div>
                
                <!-- Actions -->
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-secondary flex-fill" onclick="voltarPassoCheckout()">Voltar</button>
                    <button class="btn btn-primary flex-fill" onclick="submitOrder()">Confirmar Pedido</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cart management
        let cart = JSON.parse(localStorage.getItem('cart_<?php echo $filialId; ?>')) || [];
        let deliveryType = 'pickup';
        let paymentMethod = 'on_delivery';
        let deliveryFee = 0;
        let currentCheckoutStep = 1;
        let clienteData = null;
        let enderecosCliente = [];
        let enderecoSelecionado = null;
        
        const filialData = {
            id: <?php echo $filialId; ?>,
            tenantId: <?php echo $tenantId; ?>,
            endereco: <?php echo json_encode($filial['endereco']); ?>,
            usar_calculo_distancia: <?php echo $filial['usar_calculo_distancia'] ? 'true' : 'false'; ?>,
            taxa_delivery_fixa: <?php echo $filial['taxa_delivery_fixa'] ?? 0; ?>,
            raio_entrega_km: <?php echo $filial['raio_entrega_km'] ?? 5; ?>
        };
        
        const deliveryMapsWebhookUrl = <?php echo json_encode($deliveryMapsWebhookUrl); ?>;
        
        function updateCart() {
            localStorage.setItem('cart_<?php echo $filialId; ?>', JSON.stringify(cart));
            updateCartUI();
        }
        
        function updateCartUI() {
            const cartBody = document.getElementById('cartBody');
            const cartBadge = document.getElementById('cartBadge');
            const cartTotal = document.getElementById('cartTotal');
            
            cartBadge.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            
            if (cart.length === 0) {
                cartBody.innerHTML = '<p class="text-center text-muted mt-4">Seu carrinho está vazio</p>';
                cartTotal.textContent = 'R$ 0,00';
                return;
            }
            
            let total = 0;
            cartBody.innerHTML = cart.map((item, index) => {
                const itemTotal = parseFloat(item.preco_normal || 0) * (item.quantity || 1);
                total += itemTotal;
                const hasCustomization = (item.ingredientes && item.ingredientes.length > 0) || (item.observacao && item.observacao.trim() !== '');
                const itemKey = `${item.id}_${index}`; // Use index to make unique key for items with same product id
                
                // Build customization details
                let customizationDetails = '';
                if (hasCustomization) {
                    customizationDetails = '<small class="text-muted d-block mt-1">';
                    if (item.ingredientes && item.ingredientes.length > 0) {
                        // Get ingredient names (we'll need to fetch them or store names)
                        customizationDetails += '<i class="fas fa-edit"></i> Personalizado';
                    }
                    if (item.observacao && item.observacao.trim() !== '') {
                        customizationDetails += '<br><strong>Obs:</strong> ' + item.observacao;
                    }
                    customizationDetails += '</small>';
                }
                
                return `
                    <div class="cart-item" data-item-key="${itemKey}">
                        <div class="cart-item-info">
                            <div class="cart-item-name">
                                ${item.nome}
                                ${customizationDetails}
                            </div>
                            <div class="cart-item-price">R$ ${parseFloat(item.preco_normal || 0).toFixed(2).replace('.', ',')} x ${item.quantity || 1}</div>
                        </div>
                        <div class="cart-item-controls">
                            <button class="quantity-btn" onclick="updateQuantityByKey('${itemKey}', -1)">-</button>
                            <span>${item.quantity || 1}</span>
                            <button class="quantity-btn" onclick="updateQuantityByKey('${itemKey}', 1)">+</button>
                            <button class="quantity-btn text-danger" onclick="removeFromCartByKey('${itemKey}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
            
            cartTotal.textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
        }
        
        // Product search function
        function filterProducts() {
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();
            const categorySections = document.querySelectorAll('.category-section');
            const productCards = document.querySelectorAll('.product-card');
            
            if (!searchTerm) {
                // Show all
                categorySections.forEach(section => section.style.display = 'block');
                productCards.forEach(card => card.style.display = 'block');
                return;
            }
            
            let hasVisibleProducts = false;
            categorySections.forEach(section => {
                const products = section.querySelectorAll('.product-card');
                let sectionHasVisible = false;
                
                products.forEach(card => {
                    const productName = card.dataset.productName || '';
                    if (productName.includes(searchTerm)) {
                        card.style.display = 'block';
                        sectionHasVisible = true;
                        hasVisibleProducts = true;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                section.style.display = sectionHasVisible ? 'block' : 'none';
            });
            
            // Show message if no products found
            if (!hasVisibleProducts) {
                const container = document.querySelector('.container-main');
                let noResults = document.getElementById('noResultsMessage');
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.id = 'noResultsMessage';
                    noResults.className = 'alert alert-info text-center mt-4';
                    noResults.innerHTML = '<i class="fas fa-search me-2"></i>Nenhum produto encontrado';
                    container.appendChild(noResults);
                }
                noResults.style.display = 'block';
            } else {
                const noResults = document.getElementById('noResultsMessage');
                if (noResults) noResults.style.display = 'none';
            }
        }
        
        // Open product customization modal
        function openProductCustomization(produtoId) {
            const url = new URL('index.php', window.location.origin);
            url.searchParams.set('action', 'buscar_produto_cardapio');
            url.searchParams.set('produto_id', produtoId);
            url.searchParams.set('tenant_id', filialData.tenantId);
            url.searchParams.set('filial_id', filialData.id);
            
            fetch(url.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showCustomizationModal(data.produto, data.ingredientes, data.todos_ingredientes);
                    } else {
                        alert('Erro ao carregar produto: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar produto');
                });
        }
        
        // Show customization modal
        function showCustomizationModal(produto, ingredientes, todosIngredientes) {
            // Create modal HTML (similar to gerar_pedido.php)
            const modalHtml = `
                <div class="modal fade" id="modalCustomizacao" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Personalizar ${produto.nome}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Quantidade</label>
                                    <input type="number" class="form-control" id="quantidadeProduto" value="1" min="1" onchange="atualizarPrecoProduto()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ingredientes</label>
                                    <div id="ingredientesList" class="border p-3" style="max-height: 300px; overflow-y: auto;">
                                        <!-- Ingredientes serão inseridos aqui -->
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea class="form-control" id="observacaoProduto" rows="2" placeholder="Observações especiais..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <strong>Preço: R$ <span id="precoTotalProduto">${parseFloat(produto.preco_normal).toFixed(2).replace('.', ',')}</span></strong>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="confirmarCustomizacao(${produto.id})">Adicionar ao Carrinho</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('modalCustomizacao');
            if (existingModal) existingModal.remove();
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Populate ingredients
            const ingredientesList = document.getElementById('ingredientesList');
            ingredientesList.innerHTML = '';
            
            // Group ingredients by type
            const ingredientesPorTipo = {};
            todosIngredientes.forEach(ing => {
                if (!ingredientesPorTipo[ing.tipo]) {
                    ingredientesPorTipo[ing.tipo] = [];
                }
                ingredientesPorTipo[ing.tipo].push(ing);
            });
            
            // Show ingredients
            Object.keys(ingredientesPorTipo).forEach(tipo => {
                const tipoDiv = document.createElement('div');
                tipoDiv.className = 'mb-3';
                tipoDiv.innerHTML = `<strong>${tipo}</strong>`;
                ingredientesList.appendChild(tipoDiv);
                
                ingredientesPorTipo[tipo].forEach(ing => {
                    const ingDiv = document.createElement('div');
                    const isDefault = ingredientes.some(i => i.id === ing.id && i.padrao);
                    ingDiv.innerHTML = `
                        <div class="form-check">
                            <input class="form-check-input ingrediente-checkbox" type="checkbox" 
                                   value="${ing.id}" data-preco="${ing.preco_adicional || 0}" 
                                   ${isDefault ? 'checked' : ''} 
                                   onchange="atualizarPrecoProduto()">
                            <label class="form-check-label">
                                ${ing.nome} ${ing.preco_adicional > 0 ? `(+R$ ${parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',')})` : ''}
                            </label>
                        </div>
                    `;
                    ingredientesList.appendChild(ingDiv);
                });
            });
            
            // Store product data
            window.produtoAtual = {
                id: produto.id,
                nome: produto.nome,
                preco_normal: parseFloat(produto.preco_normal),
                ingredientes_originais: ingredientes
            };
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('modalCustomizacao'));
            modal.show();
        }
        
        // Update product price based on selected ingredients
        function atualizarPrecoProduto() {
            if (!window.produtoAtual) return;
            
            const checkboxes = document.querySelectorAll('.ingrediente-checkbox:checked');
            let precoAdicional = 0;
            checkboxes.forEach(cb => {
                precoAdicional += parseFloat(cb.dataset.preco || 0);
            });
            
            const quantidade = parseInt(document.getElementById('quantidadeProduto').value) || 1;
            const precoTotal = (window.produtoAtual.preco_normal + precoAdicional) * quantidade;
            
            document.getElementById('precoTotalProduto').textContent = precoTotal.toFixed(2).replace('.', ',');
        }
        
        // Confirm customization and add to cart
        function confirmarCustomizacao(produtoId) {
            if (!window.produtoAtual) return;
            
            const quantidade = parseInt(document.getElementById('quantidadeProduto').value) || 1;
            const observacao = document.getElementById('observacaoProduto').value;
            
            const checkboxes = document.querySelectorAll('.ingrediente-checkbox:checked');
            const ingredientesSelecionados = Array.from(checkboxes).map(cb => ({
                id: parseInt(cb.value),
                preco_adicional: parseFloat(cb.dataset.preco || 0)
            }));
            
            let precoAdicional = ingredientesSelecionados.reduce((sum, ing) => sum + ing.preco_adicional, 0);
            const precoTotal = (window.produtoAtual.preco_normal + precoAdicional) * quantidade;
            
            // Add to cart
            cart.push({
                id: produtoId,
                nome: window.produtoAtual.nome,
                preco_normal: window.produtoAtual.preco_normal + precoAdicional,
                quantity: quantidade,
                observacao: observacao,
                ingredientes: ingredientesSelecionados
            });
            
            updateCart();
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('modalCustomizacao')).hide();
        }
        
        // Add product directly to cart without customization
        function addToCartDirect(product) {
            if (!product || !product.id) {
                console.error('Produto inválido:', product);
                alert('Erro: Produto inválido');
                return;
            }
            
            // Check if product already exists in cart
            const existingItem = cart.find(item => item.id === product.id && !item.ingredientes);
            
            if (existingItem) {
                // If exists and has no customization, just increase quantity
                existingItem.quantity++;
            } else {
                // Add new item to cart
                cart.push({
                    id: product.id,
                    nome: product.nome,
                    preco_normal: parseFloat(product.preco_normal) || 0,
                    quantity: 1,
                    observacao: '',
                    ingredientes: []
                });
            }
            
            updateCart();
            
            // Show feedback
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    cartBadge.style.transform = 'scale(1)';
                }, 200);
            }
        }
        
        // Old function kept for compatibility - now opens customization modal
        function addToCart(product) {
            if (product && product.id) {
                openProductCustomization(product.id);
            } else {
                console.error('Produto inválido:', product);
                alert('Erro: Produto inválido');
            }
        }
        
        // Update quantity by item key (supports multiple items with same product id)
        function updateQuantityByKey(itemKey, change) {
            const itemIndex = parseInt(itemKey.split('_')[1]);
            if (cart[itemIndex]) {
                cart[itemIndex].quantity += change;
                if (cart[itemIndex].quantity <= 0) {
                    removeFromCartByKey(itemKey);
                } else {
                    updateCart();
                }
            }
        }
        
        // Remove from cart by item key
        function removeFromCartByKey(itemKey) {
            const itemIndex = parseInt(itemKey.split('_')[1]);
            if (cart[itemIndex]) {
                cart.splice(itemIndex, 1);
                updateCart();
            }
        }
        
        // Legacy functions for compatibility
        function updateQuantity(productId, change) {
            const item = cart.find(item => item.id === productId);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    removeFromCart(productId);
                } else {
                    updateCart();
                }
            }
        }
        
        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            updateCart();
        }
        
        function toggleCart() {
            document.getElementById('cartSidebar').classList.toggle('open');
        }
        
        function selectDeliveryType(type) {
            deliveryType = type;
            document.querySelectorAll('.delivery-option').forEach(opt => opt.classList.remove('selected'));
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('selected');
            }
            const radio = document.getElementById(type);
            if (radio) radio.checked = true;
        }
        
        function selectPaymentMethod(method) {
            paymentMethod = method;
            document.querySelectorAll('.delivery-option').forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.getElementById('payment' + (method === 'online' ? 'Online' : 'OnDelivery')).checked = true;
        }
        
        // Function calculateDeliveryFee is now renamed to calcularTaxaEntrega (above)
        
        function updateOrderSummary() {
            const summary = document.getElementById('orderSummary');
            const total = document.getElementById('orderTotal');
            
            let subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.preco_normal) * item.quantity), 0);
            let totalValue = subtotal + deliveryFee;
            
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
        
        function openCheckout() {
            if (cart.length === 0) {
                alert('Seu carrinho está vazio!');
                return;
            }
            currentCheckoutStep = 1;
            mostrarPassoCheckout(1);
            document.getElementById('checkoutModal').classList.add('show');
        }
        
        function closeCheckout() {
            document.getElementById('checkoutModal').classList.remove('show');
            currentCheckoutStep = 1;
            clienteData = null;
            enderecosCliente = [];
            enderecoSelecionado = null;
        }
        
        function mostrarPassoCheckout(step) {
            // Hide all steps
            for (let i = 1; i <= 6; i++) {
                const stepEl = document.getElementById('checkoutStep' + i);
                if (stepEl) stepEl.style.display = 'none';
            }
            // Show current step
            const currentStepEl = document.getElementById('checkoutStep' + step);
            if (currentStepEl) currentStepEl.style.display = 'block';
            currentCheckoutStep = step;
        }
        
        function proximoPassoCheckout(fromStep) {
            // Validate current step
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
                if (deliveryType === 'delivery') {
                    // Show address selection if customer has saved addresses
                    if (enderecosCliente && enderecosCliente.length > 0) {
                        const select = document.getElementById('enderecoSelecionado');
                        select.innerHTML = '<option value="">Selecione um endereço...</option>';
                        enderecosCliente.forEach(end => {
                            const option = document.createElement('option');
                            option.value = end.id;
                            option.textContent = `${end.logradouro || ''}, ${end.numero || ''} - ${end.bairro || ''}`;
                            select.appendChild(option);
                        });
                        document.getElementById('enderecosCadastrados').style.display = 'block';
                        document.getElementById('novoEnderecoSection').style.display = 'none';
                    } else {
                        document.getElementById('enderecosCadastrados').style.display = 'none';
                        document.getElementById('novoEnderecoSection').style.display = 'block';
                    }
                    mostrarPassoCheckout(4);
                    return;
                } else {
                    mostrarPassoCheckout(5);
                    return;
                }
            } else if (fromStep === 4) {
                if (deliveryType === 'delivery') {
                    if (!enderecoSelecionado && !document.getElementById('deliveryAddress').value.trim()) {
                        alert('Por favor, selecione ou informe um endereço');
                        return;
                    }
                }
                mostrarPassoCheckout(5);
                return;
            } else if (fromStep === 5) {
                mostrarPassoCheckout(6);
                updateOrderSummary();
                return;
            }
            
            mostrarPassoCheckout(fromStep + 1);
        }
        
        function voltarPassoCheckout() {
            if (currentCheckoutStep > 1) {
                if (currentCheckoutStep === 6) {
                    mostrarPassoCheckout(5);
                } else if (currentCheckoutStep === 5) {
                    if (deliveryType === 'delivery') {
                        mostrarPassoCheckout(4);
                    } else {
                        mostrarPassoCheckout(3);
                    }
                } else if (currentCheckoutStep === 4) {
                    mostrarPassoCheckout(3);
                } else if (currentCheckoutStep === 3) {
                    mostrarPassoCheckout(2);
                } else if (currentCheckoutStep === 2) {
                    mostrarPassoCheckout(1);
                }
            }
        }
        
        // Search customer by phone
        async function buscarClientePorTelefone() {
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
                const url = new URL('index.php', window.location.origin);
                url.searchParams.set('action', 'buscar_cliente_cardapio');
                url.searchParams.set('tenant_id', filialData.tenantId);
                url.searchParams.set('telefone', telefone);
                
                const response = await fetch(url.toString());
                const data = await response.json();
                
                if (data.success && data.cliente) {
                    // Customer found
                    clienteData = data.cliente;
                    enderecosCliente = data.enderecos || [];
                    
                    // Fill form
                    document.getElementById('customerName').value = data.cliente.nome || '';
                    document.getElementById('customerEmail').value = data.cliente.email || '';
                    document.getElementById('customerCpf').value = data.cliente.cpf || '';
                    
                    // Show success message
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = `✅ Cliente encontrado: ${data.cliente.nome}`;
                    
                    // Move to next step
                    setTimeout(() => {
                        proximoPassoCheckout(1);
                    }, 1000);
                } else {
                    // Customer not found - move to registration step
                    resultDiv.className = 'alert alert-warning';
                    resultDiv.innerHTML = 'ℹ️ Cliente não encontrado. Você será cadastrado ao continuar.';
                    
                    // Clear form
                    document.getElementById('customerName').value = '';
                    document.getElementById('customerEmail').value = '';
                    document.getElementById('customerCpf').value = '';
                    
                    // Move to next step after a moment
                    setTimeout(() => {
                        proximoPassoCheckout(1);
                    }, 1500);
                }
            } catch (error) {
                console.error('Erro ao buscar cliente:', error);
                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Erro ao buscar cliente. Tente novamente.';
            }
        }
        
        // Select saved address
        function selecionarEnderecoCadastrado() {
            const select = document.getElementById('enderecoSelecionado');
            const enderecoId = select.value;
            
            if (enderecoId) {
                enderecoSelecionado = enderecosCliente.find(e => e.id == enderecoId);
                if (enderecoSelecionado) {
                    document.getElementById('deliveryAddress').value = enderecoSelecionado.logradouro || '';
                    document.getElementById('deliveryNeighborhood').value = enderecoSelecionado.bairro || '';
                    document.getElementById('deliveryCity').value = enderecoSelecionado.cidade || '';
                    document.getElementById('deliveryCEP').value = enderecoSelecionado.cep || '';
                    document.getElementById('deliveryEstado').value = enderecoSelecionado.estado || '';
                    document.getElementById('deliveryReferencia').value = enderecoSelecionado.referencia || '';
                    document.getElementById('novoEnderecoSection').style.display = 'none';
                }
            } else {
                enderecoSelecionado = null;
                document.getElementById('novoEnderecoSection').style.display = 'block';
            }
        }
        
        // Calculate delivery fee (renamed from calculateDeliveryFee)
        async function calcularTaxaEntrega() {
            let address, neighborhood, city, cep;
            
            if (enderecoSelecionado) {
                address = enderecoSelecionado.logradouro || '';
                neighborhood = enderecoSelecionado.bairro || '';
                city = enderecoSelecionado.cidade || '';
                cep = enderecoSelecionado.cep || '';
            } else {
                address = document.getElementById('deliveryAddress').value;
                neighborhood = document.getElementById('deliveryNeighborhood').value;
                city = document.getElementById('deliveryCity').value;
                cep = document.getElementById('deliveryCEP').value;
            }
            
            if (!address || !city) {
                alert('Por favor, preencha pelo menos o endereço e a cidade.');
                return;
            }
            
            const clientAddress = `${address}, ${neighborhood}, ${city}, ${cep}`;
            
            if (filialData.usar_calculo_distancia) {
                const deliveryMapsWebhookUrl = <?php echo json_encode($deliveryMapsWebhookUrl); ?>;
                if (deliveryMapsWebhookUrl) {
                    try {
                        const response = await fetch(deliveryMapsWebhookUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                origin: filialData.endereco,
                                destination: clientAddress
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success && data.distancia && data.valor) {
                            deliveryFee = parseFloat(data.valor);
                            document.getElementById('deliveryFeeText').textContent = `Taxa: R$ ${deliveryFee.toFixed(2).replace('.', ',')} (${data.distancia} km)`;
                            alert('Taxa calculada: R$ ' + deliveryFee.toFixed(2).replace('.', ','));
                        } else {
                            throw new Error('Erro ao calcular distância');
                        }
                    } catch (error) {
                        console.error('Erro ao calcular distância:', error);
                        alert('Erro ao calcular taxa de entrega. Usando taxa fixa.');
                        deliveryFee = filialData.taxa_delivery_fixa;
                        document.getElementById('deliveryFeeText').textContent = `Taxa: R$ ${deliveryFee.toFixed(2).replace('.', ',')}`;
                    }
                } else {
                    deliveryFee = filialData.taxa_delivery_fixa;
                    document.getElementById('deliveryFeeText').textContent = `Taxa: R$ ${deliveryFee.toFixed(2).replace('.', ',')}`;
                }
            } else {
                deliveryFee = filialData.taxa_delivery_fixa;
                document.getElementById('deliveryFeeText').textContent = `Taxa: R$ ${deliveryFee.toFixed(2).replace('.', ',')}`;
            }
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
            
            // Register or update customer if needed
            if (!clienteData || !clienteData.id) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'cadastrar');
                    formData.append('tenant_id', filialData.tenantId);
                    formData.append('nome', customerName);
                    formData.append('telefone', customerPhone);
                    formData.append('email', customerEmail);
                    formData.append('cpf', customerCpf);
                    
                    // Add address if delivery
                    if (deliveryType === 'delivery') {
                        let enderecoData = {};
                        if (enderecoSelecionado) {
                            enderecoData = {
                                endereco: enderecoSelecionado.logradouro,
                                bairro: enderecoSelecionado.bairro,
                                cidade: enderecoSelecionado.cidade,
                                cep: enderecoSelecionado.cep,
                                estado: enderecoSelecionado.estado,
                                referencia: enderecoSelecionado.referencia
                            };
                        } else {
                            enderecoData = {
                                endereco: document.getElementById('deliveryAddress').value,
                                bairro: document.getElementById('deliveryNeighborhood').value,
                                cidade: document.getElementById('deliveryCity').value,
                                cep: document.getElementById('deliveryCEP').value,
                                estado: document.getElementById('deliveryEstado').value,
                                referencia: document.getElementById('deliveryReferencia').value
                            };
                        }
                        formData.append('endereco', JSON.stringify(enderecoData));
                    }
                    
                    const url = new URL('index.php', window.location.origin);
                    url.searchParams.set('action', 'cadastrar_cliente_cardapio');
                    url.searchParams.set('tenant_id', filialData.tenantId);
                    
                    const response = await fetch(url.toString(), {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success && data.cliente) {
                        clienteData = data.cliente;
                    }
                } catch (error) {
                    console.error('Erro ao cadastrar cliente:', error);
                    // Continue anyway - order can be created without customer ID
                }
            }
            
            if (deliveryType === 'delivery') {
                const address = enderecoSelecionado ? enderecoSelecionado.logradouro : document.getElementById('deliveryAddress').value;
                const city = enderecoSelecionado ? enderecoSelecionado.cidade : document.getElementById('deliveryCity').value;
                if (!address || !city) {
                    alert('Por favor, preencha o endereço de entrega.');
                    return;
                }
            }
            
            // Prepare order items with ingredients and observations
            const itensDetalhados = cart.map(item => ({
                id: item.id,
                quantity: item.quantity,
                preco: item.preco_normal,
                observacao: item.observacao || '',
                ingredientes_adicionados: item.ingredientes || [],
                ingredientes_removidos: [] // We don't track removed ingredients in this flow
            }));
            
            const orderData = {
                filial_id: filialData.id,
                tenant_id: filialData.tenantId,
                itens: itensDetalhados,
                tipo_entrega: deliveryType,
                taxa_entrega: deliveryFee,
                cliente_nome: customerName,
                cliente_telefone: customerPhone,
                cliente_email: customerEmail,
                cliente_cpf: customerCpf,
                cliente_id: clienteData ? clienteData.id : null,
                endereco_entrega: deliveryType === 'delivery' ? (enderecoSelecionado ? {
                    endereco: enderecoSelecionado.logradouro,
                    bairro: enderecoSelecionado.bairro,
                    cidade: enderecoSelecionado.cidade,
                    cep: enderecoSelecionado.cep,
                    estado: enderecoSelecionado.estado,
                    referencia: enderecoSelecionado.referencia
                } : {
                    endereco: document.getElementById('deliveryAddress').value,
                    bairro: document.getElementById('deliveryNeighborhood').value,
                    cidade: document.getElementById('deliveryCity').value,
                    cep: document.getElementById('deliveryCEP').value,
                    estado: document.getElementById('deliveryEstado').value,
                    referencia: document.getElementById('deliveryReferencia').value
                }) : null,
                forma_pagamento: paymentMethod
            };
            
            try {
                const response = await fetch('index.php?action=criar_pedido_online', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (paymentMethod === 'online' && result.payment_url) {
                        // Redirect to payment page
                        window.location.href = result.payment_url;
                    } else {
                        alert('Pedido criado com sucesso! Número do pedido: ' + result.pedido_id);
                        cart = [];
                        updateCart();
                        closeCheckout();
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
        
        // Initialize cart UI when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                updateCartUI();
            });
        } else {
            updateCartUI();
        }
        
        // Close cart when clicking outside
        document.addEventListener('click', function(event) {
            const cartSidebar = document.getElementById('cartSidebar');
            const cartIcon = document.querySelector('.cart-icon');
            if (cartSidebar && cartIcon && !cartSidebar.contains(event.target) && !cartIcon.contains(event.target) && cartSidebar.classList.contains('open')) {
                cartSidebar.classList.remove('open');
            }
        });
    </script>
</body>
</html>

