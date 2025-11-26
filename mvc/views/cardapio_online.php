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

// Get most sold products - SIMPLIFICADO: sempre buscar produtos ativos
$produtosMaisVendidos = [];
try {
    // Query simples: produtos ativos, com exibir_cardapio_online se a coluna existir
    if ($hasExibirCardapioColumn) {
        $produtosMaisVendidos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true 
               AND (p.exibir_cardapio_online IS NULL OR p.exibir_cardapio_online = true)
             ORDER BY RANDOM()
             LIMIT 10",
            [$tenantId, $filialId]
        );
    } else {
        $produtosMaisVendidos = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? AND p.ativo = true
             ORDER BY RANDOM()
             LIMIT 10",
            [$tenantId, $filialId]
        );
    }
} catch (\Exception $e) {
    error_log("Erro ao buscar produtos mais vendidos: " . $e->getMessage());
    $produtosMaisVendidos = [];
}

// Get promotional products (products with em_promocao = true and preco_promocional set)
$hasEmPromocao = false;
$hasPrecoPromocional = false;
try {
    $columnCheck = $db->fetch("
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'produtos' 
          AND column_name = 'em_promocao'
        LIMIT 1
    ");
    $hasEmPromocao = !empty($columnCheck);
    
    $columnCheck = $db->fetch("
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'produtos' 
          AND column_name = 'preco_promocional'
        LIMIT 1
    ");
    $hasPrecoPromocional = !empty($columnCheck);
} catch (\Exception $e) {
    $hasEmPromocao = false;
    $hasPrecoPromocional = false;
}

// Get promotional products - SIMPLIFICADO: query direta
$produtosPromocao = [];
try {
    // Query simples e direta
    if ($hasExibirCardapioColumn) {
        $produtosPromocao = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
               AND p.ativo = true 
               AND (p.exibir_cardapio_online IS NULL OR p.exibir_cardapio_online = true)
               AND p.em_promocao = true
               AND p.preco_promocional IS NOT NULL 
               AND p.preco_promocional > 0
             ORDER BY RANDOM()
             LIMIT 10",
            [$tenantId, $filialId]
        );
    } else {
        $produtosPromocao = $db->fetchAll(
            "SELECT p.*, c.nome as categoria_nome 
             FROM produtos p 
             LEFT JOIN categorias c ON p.categoria_id = c.id 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
               AND p.ativo = true
               AND p.em_promocao = true
               AND p.preco_promocional IS NOT NULL 
               AND p.preco_promocional > 0
             ORDER BY RANDOM()
             LIMIT 10",
            [$tenantId, $filialId]
        );
    }
} catch (\Exception $e) {
    error_log("Erro ao buscar produtos em promoção: " . $e->getMessage());
    $produtosPromocao = [];
}

// Não usar fallback - só mostrar produtos realmente em promoção

// Debug simples
error_log("Cardapio Online - Mais Vendidos: " . count($produtosMaisVendidos) . ", Promoção: " . count($produtosPromocao));

// Group products by category
$produtosPorCategoria = [];
$categoriasInfo = [];
foreach ($produtos as $produto) {
    $categoria = $produto['categoria_nome'] ?? 'Outros';
    $categoriaId = $produto['categoria_id'] ?? 0;
    
    if (!isset($produtosPorCategoria[$categoria])) {
        $produtosPorCategoria[$categoria] = [];
        $categoriasInfo[$categoria] = [
            'id' => $categoriaId,
            'nome' => $categoria,
            'imagem' => null // Can be extended later
        ];
    }
    $produtosPorCategoria[$categoria][] = $produto;
}

// Sort categories in descending order (Z to A)
krsort($produtosPorCategoria);
krsort($categoriasInfo);

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

// Check if currently open - using establishment timezone
$isOpen = false;
$diaAtualKey = \System\TimeHelper::currentDayName($filialId);
$horarioHoje = $horarios[$diaAtualKey] ?? null;

if ($horarioHoje && $horarioHoje['aberto'] && isset($horarioHoje['periodos'])) {
    $horaAtual = \System\TimeHelper::currentHour($filialId);
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
    
    <?php
    // Get pixel code from filial_settings
    $pixelSetting = $db->fetch(
        "SELECT setting_value FROM filial_settings WHERE tenant_id = ? AND filial_id = ? AND setting_key = 'pixel_rastreamento'",
        [$tenantId, $filialId]
    );
    
    if ($pixelSetting && !empty($pixelSetting['setting_value'])) {
        // Output pixel code directly (it should already be valid HTML/JavaScript)
        echo $pixelSetting['setting_value'];
    }
    ?>
    <style>
        :root {
            --primary-color: <?php echo $primaryColor; ?>;
            --header-bg: <?php echo $primaryColor; ?>;
        }
        
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
            overflow-x: hidden;
        }
        
        img, video, iframe {
            max-width: 100%;
            height: auto;
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
        
        /* Search Box */
        .search-container {
            margin-bottom: 2rem;
            position: relative;
        }
        
        .search-box {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .search-box:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }
        
        .search-box:focus + .search-icon {
            color: var(--primary-color);
        }
        
        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        /* Products Grid */
            .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .category-section {
            margin-bottom: 3rem;
        }
        
        .category-section.hidden {
            display: none;
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
        
        .product-price-promo {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .price-old {
            font-size: 0.9rem;
            color: #999;
            text-decoration: line-through;
        }
        
        .price-new {
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
        }
        
        /* Carousel Styles */
        .carousel-section {
            margin: 3rem 0;
        }
        
        .carousel-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .carousel-title i {
            color: var(--primary-color);
        }
        
        .carousel-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .carousel-wrapper {
            flex: 1;
            overflow: hidden;
            position: relative;
        }
        
        .carousel-track {
            display: flex !important;
            gap: 1.5rem;
            transition: transform 0.3s ease;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            width: 100%;
            min-height: 250px;
            justify-content: center;
            padding: 0 2rem;
        }
        
        .carousel-track::-webkit-scrollbar {
            display: none;
        }
        
        .carousel-item {
            flex: 0 0 200px !important;
            min-width: 200px !important;
            display: block !important;
            visibility: visible !important;
            scroll-snap-align: center;
        }
        
        .product-card-carousel {
            background: white;
            border-radius: 10px;
            overflow: visible;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
        }
        
        .product-card-carousel:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-card-carousel .product-image {
            height: 150px;
            width: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin: 0 auto 1rem auto;
            display: block;
        }
        
        .product-card-carousel .product-info {
            padding: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            text-align: center;
        }
        
        .product-card-carousel .product-name {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        
        .product-card-carousel .product-price {
            font-size: 1.1rem;
            margin-top: auto;
        }
        
        /* Fallback image container também redondo */
        .product-card-carousel .product-image[style*="display: flex"],
        .product-card-carousel div.product-image {
            height: 150px !important;
            width: 150px !important;
            border-radius: 50% !important;
            border: 4px solid var(--primary-color) !important;
            margin: 0 auto 1rem auto !important;
            flex-shrink: 0;
        }
        
        .carousel-btn {
            background: var(--primary-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: #000;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .carousel-btn:hover {
            background: #ffd700;
            transform: scale(1.1);
        }
        
        .carousel-btn:active {
            transform: scale(0.95);
        }
        
        /* Categories Grid */
        .categories-section {
            margin: 4rem 0;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .category-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .category-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color), #ffd700);
        }
        
        .category-info {
            padding: 1.5rem;
            text-align: center;
        }
        
        .category-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .category-count {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Category Products Section */
        .category-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .btn-back-category {
            background: #f5f5f5;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-back-category:hover {
            background: #e0e0e0;
        }
        
        .category-products-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #333;
            margin: 0;
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
        
        .cart-item textarea {
            width: 100%;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            resize: vertical;
            min-height: 50px;
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
        
        /* Botões primários usando cor configurada */
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #000 !important;
        }
        
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            opacity: 0.9;
            color: #000 !important;
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
            .header-yellow {
                padding: 1.5rem 1rem;
            }
            
            .header-yellow .logo-img,
            .header-yellow .logo-initials {
                width: 80px;
                height: 80px;
                font-size: 1.8rem;
            }
            
            .header-yellow .restaurant-name {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .status-badge {
                padding: 0.4rem 1rem;
                font-size: 0.8rem;
            }
            
            .nav-tabs-custom {
                flex-wrap: wrap;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .main-content-wrapper {
                padding: 1rem;
            }
            
            .search-box {
                padding: 0.75rem 0.75rem 0.75rem 3rem;
                font-size: 0.9rem;
            }
            
            .search-icon {
                left: 0.75rem;
                font-size: 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .product-card {
                border-radius: 8px;
            }
            
            .product-image {
                height: 120px;
            }
            
            .product-info {
                padding: 0.75rem;
            }
            
            .product-name {
                font-size: 0.9rem;
            }
            
            .product-price {
                font-size: 1rem;
            }
            
            .sidebar-right {
                width: 100%;
                right: -100%;
                padding: 1rem;
            }
            
            .sidebar-header h3 {
                font-size: 1.1rem;
            }
            
            .cart-button {
                bottom: 1rem;
                right: 1rem;
                width: 50px;
                height: 50px;
            }
            
            .cart-button i {
                font-size: 1.2rem;
            }
            
            .cart-badge {
                width: 20px;
                height: 20px;
                font-size: 0.7rem;
            }
            
            .reservation-form {
                max-width: 100%;
            }
            
            .hours-list li {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .checkout-content {
                width: 95%;
                padding: 1.5rem;
                max-height: 95vh;
            }
            
            .checkout-step h5 {
                font-size: 1.1rem;
            }
            
            .payment-option {
                padding: 0.75rem;
            }
            
            .info-section {
                margin-bottom: 1.5rem;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            /* Carousel responsive */
            .carousel-section {
                margin: 2rem 0;
            }
            
            .carousel-title {
                font-size: 1.2rem;
                margin-bottom: 1rem;
            }
            
            .carousel-item {
                flex: 0 0 160px;
                min-width: 160px;
            }
            
            .product-card-carousel .product-image {
                height: 120px;
            }
            
            .product-card-carousel .product-info {
                padding: 0.75rem;
            }
            
            .product-card-carousel .product-name {
                font-size: 0.85rem;
            }
            
            .product-card-carousel .product-price {
                font-size: 1rem;
            }
            
            .carousel-btn {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            /* Categories responsive */
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .category-image {
                height: 150px;
            }
            
            .category-info {
                padding: 1rem;
            }
            
            .category-name {
                font-size: 1rem;
            }
            
            .category-count {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-yellow {
                padding: 1rem 0.5rem;
            }
            
            .header-yellow .logo-img,
            .header-yellow .logo-initials {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .header-yellow .restaurant-name {
                font-size: 1.2rem;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 0.6rem 0.3rem;
                font-size: 0.8rem;
            }
            
            .main-content-wrapper {
                padding: 0.75rem;
            }
            
            .search-box {
                padding: 0.6rem 0.6rem 0.6rem 2.5rem;
                font-size: 0.85rem;
            }
            
            .search-icon {
                left: 0.6rem;
                font-size: 0.9rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .product-image {
                height: 150px;
            }
            
            h3 {
                font-size: 1.2rem;
            }
            
            .cart-button {
                width: 45px;
                height: 45px;
                bottom: 0.75rem;
                right: 0.75rem;
            }
            
            .sidebar-right {
                padding: 0.75rem;
            }
            
            .checkout-content {
                width: 98%;
                padding: 1rem;
                border-radius: 10px;
            }
            
            .checkout-step h5 {
                font-size: 1rem;
            }
            
            /* Carousel mobile */
            .carousel-item {
                flex: 0 0 140px;
                min-width: 140px;
            }
            
            .carousel-btn {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            /* Categories mobile */
            .categories-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .category-image {
                height: 180px;
            }
            
            .close-checkout {
                top: 0.5rem;
                right: 0.5rem;
                font-size: 1.2rem;
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
                <!-- Search Box -->
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-box" placeholder="Buscar produtos..." autocomplete="off">
                </div>
                
                <!-- Most Sold Products Carousel -->
                <div class="carousel-section">
                    <h3 class="carousel-title">
                        <i class="fas fa-fire"></i> Mais Vendidos 
                        <span style="font-size: 0.8em; color: #666;">(<?php echo count($produtosMaisVendidos); ?> produtos)</span>
                    </h3>
                    <?php if (!empty($produtosMaisVendidos)): ?>
                    <div class="carousel-container">
                        <button class="carousel-btn carousel-btn-prev" onclick="scrollCarousel('maisVendidos', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="carousel-wrapper" id="carouselMaisVendidos">
                            <div class="carousel-track" style="scroll-snap-type: x mandatory;">
                                <?php foreach ($produtosMaisVendidos as $produto): ?>
                                    <div class="carousel-item">
                                        <div class="product-card-carousel" 
                                             data-product-id="<?php echo $produto['id']; ?>"
                                             data-product-data="<?php echo htmlspecialchars(json_encode($produto)); ?>"
                                             style="cursor: pointer;">
                                            <?php if ($produto['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="product-image" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="product-image" style="display: none; align-items: center; justify-content: center; background: #f5f5f5;">
                                                    <i class="fas fa-image" style="font-size: 2rem; color: #ccc;"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                                                    <i class="fas fa-image" style="font-size: 2rem; color: #ccc;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="product-info">
                                                <div class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                                <div class="product-price">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="carousel-btn carousel-btn-next" onclick="scrollCarousel('maisVendidos', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="carousel-empty">
                        <p class="text-muted text-center py-4">Nenhum produto encontrado no momento.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Promotional Products Carousel -->
                <div class="carousel-section">
                    <h3 class="carousel-title">
                        <i class="fas fa-tag"></i> Promoções do Dia
                        <span style="font-size: 0.8em; color: #666;">(<?php echo count($produtosPromocao); ?> produtos)</span>
                    </h3>
                    <?php if (!empty($produtosPromocao)): ?>
                    <div class="carousel-container">
                        <button class="carousel-btn carousel-btn-prev" onclick="scrollCarousel('promocoes', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="carousel-wrapper" id="carouselPromocoes">
                            <div class="carousel-track" style="scroll-snap-type: x mandatory;">
                                <?php foreach ($produtosPromocao as $produto): 
                                    $precoPromo = isset($produto['preco_promocional']) && $produto['preco_promocional'] > 0 ? $produto['preco_promocional'] : $produto['preco_normal'];
                                ?>
                                    <div class="carousel-item">
                                        <div class="product-card-carousel" 
                                             data-product-id="<?php echo $produto['id']; ?>"
                                             data-product-data="<?php echo htmlspecialchars(json_encode($produto)); ?>"
                                             style="cursor: pointer;">
                                            <?php if ($produto['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="product-image" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="product-image" style="display: none; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary-color), #ffd700);">
                                                    <i class="fas fa-image" style="font-size: 2rem; color: white;"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary-color), #ffd700);">
                                                    <i class="fas fa-image" style="font-size: 2rem; color: white;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="product-info">
                                                <div class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                                <?php if (isset($produto['preco_promocional']) && $produto['preco_promocional'] > 0 && $produto['preco_promocional'] < $produto['preco_normal']): ?>
                                                    <div class="product-price-promo">
                                                        <span class="price-old">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></span>
                                                        <span class="price-new">R$ <?php echo number_format($produto['preco_promocional'], 2, ',', '.'); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="product-price">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="carousel-btn carousel-btn-next" onclick="scrollCarousel('promocoes', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="carousel-empty">
                        <p class="text-muted text-center py-4">Nenhuma promoção disponível no momento.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Categories Grid -->
                <div class="categories-section">
                    <h3 class="section-title">Categorias</h3>
                    <div class="categories-grid" id="categoriesGrid">
                        <?php foreach ($categoriasInfo as $categoriaNome => $categoriaInfo): 
                            $primeiroProduto = $produtosPorCategoria[$categoriaNome][0] ?? null;
                            $categoriaImagem = $primeiroProduto['imagem'] ?? null;
                        ?>
                            <div class="category-card" 
                                 data-category="<?php echo htmlspecialchars(strtolower($categoriaNome)); ?>"
                                 data-category-name="<?php echo htmlspecialchars($categoriaNome, ENT_QUOTES, 'UTF-8'); ?>"
                                 onclick="openCategory(this.getAttribute('data-category-name'))">
                                <?php if ($categoriaImagem): ?>
                                    <img src="<?php echo htmlspecialchars($categoriaImagem); ?>" alt="<?php echo htmlspecialchars($categoriaNome); ?>" class="category-image" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="category-image" style="display: none; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary-color), #ffd700);">
                                        <i class="fas fa-utensils" style="font-size: 3rem; color: white;"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="category-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary-color), #ffd700);">
                                        <i class="fas fa-utensils" style="font-size: 3rem; color: white;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="category-info">
                                    <div class="category-name"><?php echo htmlspecialchars($categoriaNome); ?></div>
                                    <div class="category-count"><?php echo count($produtosPorCategoria[$categoriaNome]); ?> itens</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Products by Category (Hidden by default, shown when category is clicked) -->
                <div id="categoryProductsSection" style="display: none;">
                    <div class="category-header">
                        <button class="btn-back-category" onclick="closeCategory()">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </button>
                        <h3 id="categoryProductsTitle" class="category-products-title"></h3>
                    </div>
                    <div id="categoryProductsContainer" class="products-grid">
                        <!-- Products will be loaded here via JavaScript -->
                    </div>
                </div>
                
                <!-- Products by Category (Original - Hidden) -->
                <div id="productsContainer" style="display: none;">
                    <?php foreach ($produtosPorCategoria as $categoria => $produtosCategoria): ?>
                        <div class="category-section" data-category="<?php echo htmlspecialchars(strtolower($categoria)); ?>">
                            <h3 class="mt-4 mb-3"><?php echo htmlspecialchars($categoria); ?></h3>
                            <div class="products-grid">
                                <?php foreach ($produtosCategoria as $produto): ?>
                                    <div class="product-card" 
                                         data-product-name="<?php echo htmlspecialchars(strtolower($produto['nome'])); ?>" 
                                         data-category="<?php echo htmlspecialchars(strtolower($categoria)); ?>"
                                         data-product-id="<?php echo $produto['id']; ?>"
                                         data-product-data="<?php echo htmlspecialchars(json_encode($produto)); ?>"
                                         style="cursor: pointer;">
                                        <?php if ($produto['imagem']): ?>
                                            <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="product-image" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="product-image" style="display: none; align-items: center; justify-content: center; background: #f5f5f5;">
                                                <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                                                <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                            <div class="product-price">R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?></div>
                                            <button class="btn btn-primary btn-sm w-100 mt-2" onclick="event.stopPropagation(); personalizarProduto(<?php echo $produto['id']; ?>, <?php echo htmlspecialchars(json_encode($produto)); ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- No Results Message -->
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h4>Nenhum produto encontrado</h4>
                    <p>Tente buscar com outros termos</p>
                </div>
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
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ============================================
        // FUNÇÃO HELPER PARA EVENTOS DE CONVERSÃO DO PIXEL
        // ============================================
        // Detecta automaticamente o tipo de pixel e dispara eventos de conversão
        function trackPixelEvent(eventName, eventData = {}) {
            try {
                // Facebook Pixel (fbq)
                if (typeof fbq !== 'undefined' && typeof fbq === 'function') {
                    if (eventName === 'ViewContent') {
                        fbq('track', 'ViewContent', {
                            content_name: eventData.content_name || '',
                            content_category: eventData.content_category || '',
                            content_ids: eventData.content_ids || [],
                            value: eventData.value || 0,
                            currency: 'BRL'
                        });
                    } else if (eventName === 'AddToCart') {
                        fbq('track', 'AddToCart', {
                            content_name: eventData.content_name || '',
                            content_category: eventData.content_category || '',
                            content_ids: eventData.content_ids || [],
                            value: eventData.value || 0,
                            currency: 'BRL',
                            num_items: eventData.num_items || 1
                        });
                    } else if (eventName === 'InitiateCheckout') {
                        fbq('track', 'InitiateCheckout', {
                            value: eventData.value || 0,
                            currency: 'BRL',
                            num_items: eventData.num_items || 0
                        });
                    } else if (eventName === 'Purchase') {
                        fbq('track', 'Purchase', {
                            value: eventData.value || 0,
                            currency: 'BRL',
                            contents: eventData.contents || [],
                            num_items: eventData.num_items || 0
                        });
                    } else if (eventName === 'CheckoutProgress') {
                        // Evento customizado para progresso do checkout
                        fbq('trackCustom', 'CheckoutProgress', {
                            checkout_step: eventData.checkout_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL'
                        });
                    } else if (eventName === 'CheckoutAbandoned') {
                        // Evento customizado para checkout abandonado
                        fbq('trackCustom', 'CheckoutAbandoned', {
                            abandoned_step: eventData.abandoned_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL',
                            num_items: eventData.num_items || 0
                        });
                    }
                }
                
                // Google Analytics 4 (gtag)
                if (typeof gtag !== 'undefined' && typeof gtag === 'function') {
                    if (eventName === 'ViewContent') {
                        gtag('event', 'view_item', {
                            currency: 'BRL',
                            value: eventData.value || 0,
                            items: [{
                                item_id: eventData.content_ids?.[0] || '',
                                item_name: eventData.content_name || '',
                                item_category: eventData.content_category || '',
                                price: eventData.value || 0,
                                quantity: 1
                            }]
                        });
                    } else if (eventName === 'AddToCart') {
                        gtag('event', 'add_to_cart', {
                            currency: 'BRL',
                            value: eventData.value || 0,
                            items: [{
                                item_id: eventData.content_ids?.[0] || '',
                                item_name: eventData.content_name || '',
                                item_category: eventData.content_category || '',
                                price: eventData.value || 0,
                                quantity: eventData.num_items || 1
                            }]
                        });
                    } else if (eventName === 'InitiateCheckout') {
                        gtag('event', 'begin_checkout', {
                            currency: 'BRL',
                            value: eventData.value || 0,
                            items: eventData.items || []
                        });
                    } else if (eventName === 'Purchase') {
                        // Converter contents para formato GA4 se necessário
                        const ga4Items = (eventData.contents || []).map(item => ({
                            item_id: item.item_id || item.id || '',
                            item_name: item.item_name || item.nome || '',
                            item_category: item.item_category || '',
                            price: item.price || item.preco || 0,
                            quantity: item.quantity || 1
                        }));
                        
                        gtag('event', 'purchase', {
                            transaction_id: eventData.transaction_id || '',
                            value: eventData.value || 0,
                            currency: 'BRL',
                            items: ga4Items
                        });
                    } else if (eventName === 'CheckoutProgress') {
                        // Evento customizado para progresso do checkout
                        gtag('event', 'checkout_progress', {
                            checkout_step: eventData.checkout_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL'
                        });
                    } else if (eventName === 'CheckoutAbandoned') {
                        // Evento customizado para checkout abandonado
                        gtag('event', 'checkout_abandoned', {
                            abandoned_step: eventData.abandoned_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL',
                            items: eventData.items || []
                        });
                    }
                }
                
                // Google Tag Manager (dataLayer)
                if (typeof dataLayer !== 'undefined' && Array.isArray(dataLayer)) {
                    if (eventName === 'ViewContent') {
                        dataLayer.push({
                            event: 'view_item',
                            ecommerce: {
                                currency: 'BRL',
                                value: eventData.value || 0,
                                items: [{
                                    item_id: eventData.content_ids?.[0] || '',
                                    item_name: eventData.content_name || '',
                                    item_category: eventData.content_category || '',
                                    price: eventData.value || 0,
                                    quantity: 1
                                }]
                            }
                        });
                    } else if (eventName === 'AddToCart') {
                        dataLayer.push({
                            event: 'add_to_cart',
                            ecommerce: {
                                currency: 'BRL',
                                value: eventData.value || 0,
                                items: [{
                                    item_id: eventData.content_ids?.[0] || '',
                                    item_name: eventData.content_name || '',
                                    item_category: eventData.content_category || '',
                                    price: eventData.value || 0,
                                    quantity: eventData.num_items || 1
                                }]
                            }
                        });
                    } else if (eventName === 'InitiateCheckout') {
                        dataLayer.push({
                            event: 'begin_checkout',
                            ecommerce: {
                                currency: 'BRL',
                                value: eventData.value || 0,
                                items: eventData.items || []
                            }
                        });
                    } else if (eventName === 'Purchase') {
                        // Converter contents para formato GTM se necessário
                        const gtmItems = (eventData.contents || []).map(item => ({
                            item_id: item.item_id || item.id || '',
                            item_name: item.item_name || item.nome || '',
                            item_category: item.item_category || '',
                            price: item.price || item.preco || 0,
                            quantity: item.quantity || 1
                        }));
                        
                        dataLayer.push({
                            event: 'purchase',
                            ecommerce: {
                                transaction_id: eventData.transaction_id || '',
                                value: eventData.value || 0,
                                currency: 'BRL',
                                items: gtmItems
                            }
                        });
                    } else if (eventName === 'CheckoutProgress') {
                        // Evento customizado para progresso do checkout
                        dataLayer.push({
                            event: 'checkout_progress',
                            checkout_step: eventData.checkout_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL'
                        });
                    } else if (eventName === 'CheckoutAbandoned') {
                        // Evento customizado para checkout abandonado
                        dataLayer.push({
                            event: 'checkout_abandoned',
                            abandoned_step: eventData.abandoned_step || '',
                            step_name: eventData.step_name || '',
                            step_number: eventData.step_number || 0,
                            value: eventData.value || 0,
                            currency: 'BRL',
                            items: eventData.items || []
                        });
                    }
                }
            } catch (error) {
                console.warn('Erro ao disparar evento de pixel:', error);
            }
        }
        
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
        
        // Real-time search functionality
        const searchInput = document.getElementById('searchInput');
        const productsContainer = document.getElementById('productsContainer');
        const noResults = document.getElementById('noResults');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                filterProducts(searchTerm);
            });
        }
        
        function filterProducts(searchTerm) {
            if (!productsContainer) return;
            
            const productCards = productsContainer.querySelectorAll('.product-card');
            const categorySections = productsContainer.querySelectorAll('.category-section');
            let hasVisibleProducts = false;
            
            if (searchTerm === '') {
                // Show all products and categories
                categorySections.forEach(section => {
                    section.classList.remove('hidden');
                    const products = section.querySelectorAll('.product-card');
                    products.forEach(product => {
                        product.style.display = '';
                    });
                });
                if (noResults) noResults.style.display = 'none';
                return;
            }
            
            // Filter products
            categorySections.forEach(section => {
                const products = section.querySelectorAll('.product-card');
                let hasVisibleInCategory = false;
                
                products.forEach(product => {
                    const productName = product.getAttribute('data-product-name') || '';
                    const category = product.getAttribute('data-category') || '';
                    
                    if (productName.includes(searchTerm) || category.includes(searchTerm)) {
                        product.style.display = '';
                        hasVisibleInCategory = true;
                        hasVisibleProducts = true;
                    } else {
                        product.style.display = 'none';
                    }
                });
                
                // Show/hide category section based on visible products
                if (hasVisibleInCategory) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                if (hasVisibleProducts) {
                    noResults.style.display = 'none';
                } else {
                    noResults.style.display = 'block';
                }
            }
        }
        
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
                
                // Mostrar ingredientes personalizados se houver
                let ingredientesInfo = '';
                if (item.ingredientes_adicionados && item.ingredientes_adicionados.length > 0) {
                    const nomesAdicionados = item.ingredientes_adicionados.map(ing => ing.nome || ing).join(', ');
                    ingredientesInfo += `<small class="text-success d-block">+ ${nomesAdicionados}</small>`;
                }
                if (item.ingredientes_removidos && item.ingredientes_removidos.length > 0) {
                    const nomesRemovidos = item.ingredientes_removidos.map(ing => ing.nome || ing).join(', ');
                    ingredientesInfo += `<small class="text-danger d-block">- ${nomesRemovidos}</small>`;
                }
                
                html += `
                    <div class="cart-item" style="border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1rem;">
                        <div style="flex: 1; width: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <div style="flex: 1;">
                                    <div><strong>${item.nome}</strong></div>
                                    ${ingredientesInfo}
                                    <small>R$ ${parseFloat(item.preco_normal || 0).toFixed(2).replace('.', ',')} unidade</small>
                                </div>
                                <div style="margin-left: 0.5rem;">
                                    <button onclick="removeFromCart(${index})" style="background: none; border: none; color: #dc3545; cursor: pointer; padding: 0.25rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                                <label style="font-size: 0.9rem; margin: 0;">Quantidade:</label>
                                <div style="display: flex; align-items: center; gap: 0.5rem; border: 1px solid #ddd; border-radius: 5px; padding: 0.25rem;">
                                    <button onclick="updateItemQuantity(${index}, ${(item.quantity || 1) - 1})" 
                                            style="background: var(--primary-color); border: none; color: #000; width: 30px; height: 30px; border-radius: 5px; cursor: ${(item.quantity || 1) <= 1 ? 'not-allowed' : 'pointer'}; font-weight: bold; display: flex; align-items: center; justify-content: center; opacity: ${(item.quantity || 1) <= 1 ? '0.5' : '1'};"
                                            ${(item.quantity || 1) <= 1 ? 'disabled' : ''}>
                                        <i class="fas fa-minus" style="font-size: 0.8rem;"></i>
                                    </button>
                                    <span style="min-width: 30px; text-align: center; font-weight: bold;">${item.quantity || 1}</span>
                                    <button onclick="updateItemQuantity(${index}, ${(item.quantity || 1) + 1})" 
                                            style="background: var(--primary-color); border: none; color: #000; width: 30px; height: 30px; border-radius: 5px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-plus" style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                                <div style="margin-left: auto; font-weight: bold;">
                                    R$ ${(parseFloat(item.preco_normal || 0) * (item.quantity || 1)).toFixed(2).replace('.', ',')}
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <textarea 
                                    class="form-control" 
                                    id="observacao_${index}" 
                                    placeholder="Observação (opcional)" 
                                    rows="2" 
                                    style="font-size: 0.85rem; padding: 0.5rem; width: 100%; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"
                                    onchange="updateItemObservacao(${index}, this.value)"
                                    onblur="updateItemObservacao(${index}, this.value)">${item.observacao || ''}</textarea>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Add delivery options section before total
            html += `
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #e0e0e0;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.5rem;">
                        <span class="icon" style="width: 30px; height: 30px; background: var(--primary-color); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #000; margin-right: 0.5rem;">
                            <i class="fas fa-user"></i>
                        </span>
                        Opções de entrega
                    </h4>
                    <select class="form-control" id="deliveryTypeSelect" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 1rem;">
                        <option value="">(Selecione aqui)</option>
                        <option value="pickup">Retirar no Balcão</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
            `;
            
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
        
        async function personalizarProduto(produtoId, produto) {
            try {
                // Buscar ingredientes do produto
                const response = await fetch(`mvc/ajax/produtos_cardapio_online.php?action=buscar_produto&produto_id=${produtoId}&tenant_id=<?php echo $tenantId; ?>&filial_id=<?php echo $filialId; ?>`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao buscar produto');
                }
                
                const ingredientesProduto = data.ingredientes || [];
                const todosIngredientes = data.todos_ingredientes || [];
                
                // Mostrar modal de personalização
                mostrarModalPersonalizacao(produto, ingredientesProduto, todosIngredientes);
            } catch (error) {
                console.error('Erro ao buscar produto:', error);
                alert('Erro ao carregar produto. Adicionando sem personalização.');
                addToCart(produto);
            }
        }
        
        function mostrarModalPersonalizacao(produto, ingredientesProduto, todosIngredientes) {
            // Criar modal HTML
            const modalHtml = `
                <div class="checkout-modal" id="personalizacaoModal" style="z-index: 3000;">
                    <div class="checkout-content" style="max-width: 600px;">
                        <button class="close-checkout" onclick="fecharModalPersonalizacao()">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3 class="mb-3">Editar ${produto.nome}</h3>
                        <div class="mb-3">
                            <strong>Preço base: R$ ${parseFloat(produto.preco_normal || 0).toFixed(2).replace('.', ',')}</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Ingredientes do Produto (clique para remover):</strong></label>
                            <div id="ingredientesProduto" class="ingredientes-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                ${ingredientesProduto.map(ing => `
                                    <div class="ingrediente-item" data-ingrediente-id="${ing.id}" data-ingrediente-nome="${ing.nome}" data-preco="${ing.preco_adicional || 0}" data-ja-estava="true" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; margin-bottom: 5px; background: #d4edda; border-radius: 5px; cursor: pointer;" onclick="toggleIngrediente(this)">
                                        <span>${ing.nome}${ing.preco_adicional > 0 ? ` (+R$ ${parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',')})` : ''}</span>
                                        <span class="badge bg-success">COM</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Adicionar Ingredientes (clique para adicionar):</strong></label>
                            <div id="ingredientesDisponiveis" class="ingredientes-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                ${todosIngredientes.filter(ing => !ingredientesProduto.some(ip => ip.id === ing.id)).map(ing => `
                                    <div class="ingrediente-item" data-ingrediente-id="${ing.id}" data-ingrediente-nome="${ing.nome}" data-preco="${ing.preco_adicional || 0}" data-ja-estava="false" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; margin-bottom: 5px; background: #f8d7da; border-radius: 5px; cursor: pointer;" onclick="toggleIngrediente(this)">
                                        <span>${ing.nome}${ing.preco_adicional > 0 ? ` (+R$ ${parseFloat(ing.preco_adicional).toFixed(2).replace('.', ',')})` : ''}</span>
                                        <span class="badge bg-danger">SEM</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Preço Total: R$ <span id="precoTotalPersonalizado">${parseFloat(produto.preco_normal || 0).toFixed(2).replace('.', ',')}</span></strong>
                        </div>
                        <button class="btn btn-primary w-100" onclick="adicionarProdutoPersonalizado()">
                            <i class="fas fa-cart-plus"></i> Adicionar ao Carrinho
                        </button>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Armazenar dados do produto para uso posterior
            window.produtoPersonalizacao = produto;
            window.ingredientesOriginais = ingredientesProduto.map(ing => ing.id);
            window.ingredientesOriginaisData = ingredientesProduto.map(ing => ({
                id: ing.id,
                nome: ing.nome,
                preco_adicional: parseFloat(ing.preco_adicional || 0)
            }));
            window.ingredientesSelecionados = ingredientesProduto.map(ing => ({
                id: ing.id,
                nome: ing.nome,
                preco_adicional: parseFloat(ing.preco_adicional || 0),
                ja_estava: true
            }));
        }
        
        function toggleIngrediente(element) {
            const ingredienteId = parseInt(element.dataset.ingredienteId);
            const ingredienteNome = element.dataset.ingredienteNome || element.querySelector('span').textContent.split(' (+')[0];
            const jaEstava = element.dataset.jaEstava === 'true';
            const precoAdicional = parseFloat(element.dataset.preco || 0);
            const eraOriginal = window.ingredientesOriginais.includes(ingredienteId);
            
            if (jaEstava) {
                // Remover ingrediente (estava selecionado, agora vai remover)
                window.ingredientesSelecionados = window.ingredientesSelecionados.filter(ing => ing.id !== ingredienteId);
                element.style.background = '#f8d7da';
                element.querySelector('.badge').className = 'badge bg-danger';
                element.querySelector('.badge').textContent = 'SEM';
                element.dataset.jaEstava = 'false';
            } else {
                // Adicionar ingrediente
                window.ingredientesSelecionados.push({
                    id: ingredienteId,
                    nome: ingredienteNome,
                    preco_adicional: precoAdicional,
                    ja_estava: eraOriginal
                });
                element.style.background = '#d4edda';
                element.querySelector('.badge').className = 'badge bg-success';
                element.querySelector('.badge').textContent = 'COM';
                element.dataset.jaEstava = 'true';
            }
            
            atualizarPrecoPersonalizado();
        }
        
        function atualizarPrecoPersonalizado() {
            if (!window.produtoPersonalizacao) return;
            
            let precoTotal = parseFloat(window.produtoPersonalizacao.preco_normal || 0);
            
            // Adicionar preços dos ingredientes adicionados (que não estavam originalmente)
            window.ingredientesSelecionados.forEach(ing => {
                if (!ing.ja_estava) {
                    precoTotal += ing.preco_adicional;
                }
            });
            
            document.getElementById('precoTotalPersonalizado').textContent = precoTotal.toFixed(2).replace('.', ',');
        }
        
        function adicionarProdutoPersonalizado() {
            if (!window.produtoPersonalizacao) return;
            
            // Ingredientes adicionados são os que estão selecionados mas não eram originais
            const ingredientesAdicionados = window.ingredientesSelecionados
                .filter(ing => !ing.ja_estava)
                .map(ing => ({
                    id: ing.id,
                    nome: ing.nome,
                    preco_adicional: ing.preco_adicional
                }));
            
            // Ingredientes removidos são os que eram originais mas não estão mais selecionados
            const ingredientesRemovidos = window.ingredientesOriginaisData
                .filter(ingOriginal => !window.ingredientesSelecionados.some(ing => ing.id === ingOriginal.id))
                .map(ingOriginal => ({
                    id: ingOriginal.id,
                    nome: ingOriginal.nome,
                    preco_adicional: ingOriginal.preco_adicional
                }));
            
            // Calcular preço final
            let precoFinal = parseFloat(window.produtoPersonalizacao.preco_normal || 0);
            ingredientesAdicionados.forEach(ing => {
                precoFinal += ing.preco_adicional;
            });
            
            // Adicionar ao carrinho
            const existingItem = cart.find(item => 
                item.id === window.produtoPersonalizacao.id && 
                JSON.stringify(item.ingredientes_adicionados || []) === JSON.stringify(ingredientesAdicionados) &&
                JSON.stringify(item.ingredientes_removidos || []) === JSON.stringify(ingredientesRemovidos)
            );
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: window.produtoPersonalizacao.id,
                    nome: window.produtoPersonalizacao.nome,
                    preco_normal: precoFinal,
                    quantity: 1,
                    ingredientes_adicionados: ingredientesAdicionados,
                    ingredientes_removidos: ingredientesRemovidos,
                    observacao: ''
                });
            }
            
            updateCart();
            fecharModalPersonalizacao();
        }
        
        function fecharModalPersonalizacao() {
            const modal = document.getElementById('personalizacaoModal');
            if (modal) modal.remove();
            window.produtoPersonalizacao = null;
            window.ingredientesSelecionados = [];
            window.ingredientesOriginais = [];
            window.ingredientesOriginaisData = [];
        }
        
        function addToCart(product) {
            if (!product || !product.id) {
                alert('Erro: Produto inválido');
                return;
            }
            
            // Verificar se é produto sem personalização (sem ingredientes_adicionados/removidos)
            const existingItem = cart.find(item => 
                item.id === product.id && 
                (!item.ingredientes_adicionados || item.ingredientes_adicionados.length === 0) &&
                (!item.ingredientes_removidos || item.ingredientes_removidos.length === 0) &&
                (!item.observacao || item.observacao.trim() === '')
            );
            
            const productPrice = parseFloat(product.preco_normal) || 0;
            const productCategory = product.categoria_nome || 'Geral';
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    nome: product.nome,
                    preco_normal: productPrice,
                    quantity: 1,
                    ingredientes_adicionados: product.ingredientes_adicionados || [],
                    ingredientes_removidos: product.ingredientes_removidos || [],
                    observacao: product.observacao || ''
                });
            }
            
            // Disparar evento AddToCart do pixel
            trackPixelEvent('AddToCart', {
                content_name: product.nome,
                content_category: productCategory,
                content_ids: [product.id.toString()],
                value: productPrice,
                num_items: existingItem ? existingItem.quantity : 1
            });
            
            updateCart();
        }
        
        function updateItemObservacao(index, observacao) {
            if (cart[index]) {
                cart[index].observacao = observacao;
                updateCart();
            }
        }
        
        function updateItemQuantity(index, newQuantity) {
            if (cart[index] && newQuantity > 0) {
                cart[index].quantity = newQuantity;
                updateCart();
            } else if (cart[index] && newQuantity <= 0) {
                // Remove item if quantity is 0 or less
                removeFromCart(index);
            }
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
            // Resetar flag de checkout completado quando inicia novo checkout
            checkoutCompleted = false;
            
            // Calcular valor total do carrinho para o evento de conversão
            const totalValue = cart.reduce((sum, item) => {
                return sum + (parseFloat(item.preco_normal || 0) * (item.quantity || 1));
            }, 0);
            
            const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
            
            // Preparar itens para Google Analytics/GTM
            const items = cart.map(item => ({
                item_id: item.id.toString(),
                item_name: item.nome,
                price: parseFloat(item.preco_normal || 0),
                quantity: item.quantity || 1
            }));
            
            // Disparar evento InitiateCheckout do pixel
            trackPixelEvent('InitiateCheckout', {
                value: totalValue,
                num_items: totalItems,
                items: items
            });
            
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
                            
                            <!-- Campos de dados do cliente - aparecem após buscar telefone -->
                            <div id="customerDataFields" style="display: none; margin-top: 20px;">
                                <h5 class="mb-3">Dados do Cliente</h5>
                                <div id="checkoutError" class="alert alert-danger" style="display: none; margin-bottom: 15px;"></div>
                                <input type="text" class="form-control mb-2" id="customerName" placeholder="Nome completo" required>
                                <input type="email" class="form-control mb-2" id="customerEmail" placeholder="E-mail (opcional)">
                                <input type="text" class="form-control mb-2" id="customerCpf" placeholder="CPF (opcional)">
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-info-circle"></i> CPF é opcional, mas recomendado para pagamentos online
                                </small>
                                <button class="btn btn-primary w-100 mt-2" onclick="proximoPasso(1)">Continuar</button>
                            </div>
                        </div>
                        
                        <div id="checkoutStep2" class="checkout-step" style="display: none;">
                            <!-- Este passo não é mais usado, mantido para compatibilidade -->
                        </div>
                        
                        <div id="checkoutStep3" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Endereço de Entrega</h5>
                            <div id="enderecosExistentes" style="display: none;">
                                <label class="form-label">Selecione um endereço salvo:</label>
                                <select class="form-select mb-3" id="enderecoSelecionado" onchange="preencherEnderecoSelecionado()">
                                    <option value="">-- Selecione um endereço --</option>
                                </select>
                                <div id="taxaEntregaInfoSelect" class="alert alert-info mt-2 mb-3" style="display: none;">
                                    <small><i class="fas fa-info-circle"></i> <span id="taxaEntregaTextoSelect">Calculando taxa de entrega...</span></small>
                                </div>
                                <div class="text-center mb-3">
                                    <span class="text-muted">ou</span>
                                </div>
                                <button class="btn btn-outline-secondary w-100 mb-3" onclick="mostrarNovoEndereco()">
                                    <i class="fas fa-plus"></i> Adicionar Novo Endereço
                                </button>
                            </div>
                            <div id="enderecoSection" style="display: block;">
                                <input type="text" class="form-control mb-2" id="deliveryAddress" placeholder="Rua, número, complemento">
                                <input type="text" class="form-control mb-2" id="deliveryNeighborhood" placeholder="Bairro">
                                <input type="text" class="form-control mb-2" id="deliveryCity" placeholder="Cidade">
                                <input type="text" class="form-control mb-2" id="deliveryCEP" placeholder="CEP">
                                <input type="text" class="form-control mb-2" id="deliveryEstado" placeholder="Estado (UF)">
                                <button class="btn btn-primary w-100 mt-2 mb-2" id="btnAdicionarEndereco" onclick="adicionarEndereco()">
                                    <i class="fas fa-save"></i> Adicionar Endereço
                                </button>
                                <div id="taxaEntregaInfo" class="alert alert-info mt-2" style="display: none;">
                                    <small><i class="fas fa-info-circle"></i> <span id="taxaEntregaTexto">Calculando taxa de entrega...</span></small>
                                </div>
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
                                        <br><small class="text-muted">PIX ou Cartão via Asaas</small>
                                    </label>
                                </div>
                                
                                <!-- Botões de PIX e Cartão - aparecem quando Pagar Online é selecionado -->
                                <div id="onlinePaymentButtons" style="display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                    <h6 class="mb-3">Escolha o método de pagamento:</h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success btn-lg" onclick="finalizarPedidoOnline('PIX')" style="margin-bottom: 10px;">
                                            <i class="fas fa-qrcode"></i> Pagar com PIX
                                        </button>
                                        <button class="btn btn-primary btn-lg" onclick="finalizarPedidoOnline('CREDIT_CARD')">
                                            <i class="fas fa-credit-card"></i> Pagar com Cartão
                                        </button>
                                    </div>
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
                            <button class="btn btn-primary w-100 mt-3" id="btnContinuarStep4" onclick="proximoPasso(4)">Continuar</button>
                        </div>
                        
                        <div id="checkoutStep4b" class="checkout-step" style="display: none;">
                            <!-- Este passo não é mais usado, mantido para compatibilidade -->
                        </div>
                        
                        <div id="checkoutStep5" class="checkout-step" style="display: none;">
                            <h5 class="mb-3">Forma de Pagamento na Entrega</h5>
                            <div class="mb-3">
                                <label class="form-label">Selecione a forma de pagamento:</label>
                                <select class="form-select" id="formaPagamentoDetalhada" required onchange="toggleTrocoField()">
                                    <option value="">-- Selecione --</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartão de Débito">Cartão de Débito</option>
                                    <option value="Cartão de Crédito">Cartão de Crédito</option>
                                    <option value="PIX">PIX</option>
                                    <option value="Vale Refeição">Vale Refeição</option>
                                </select>
                            </div>
                            <div class="mb-3" id="trocoField" style="display: none;">
                                <label class="form-label">Troco para quanto?</label>
                                <input type="number" class="form-control" id="trocoPara" step="0.01" min="0" placeholder="0,00">
                                <small class="text-muted">Informe o valor que você vai pagar em dinheiro</small>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-secondary flex-fill" onclick="voltarPasso()">Voltar</button>
                                <button class="btn btn-primary flex-fill" onclick="proximoPasso(5)">Continuar</button>
                            </div>
                        </div>
                        
                        <div id="checkoutStep6" class="checkout-step" style="display: none;">
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
                        
                        <div id="checkoutStep7" class="checkout-step" style="display: none;">
                            <!-- PIX Payment -->
                            <div id="pixPaymentContainer" style="display: none;">
                                <h5 class="mb-3">Pagamento via PIX</h5>
                                <div class="text-center mb-3">
                                    <p class="mb-2">Escaneie o QR Code ou copie o código PIX</p>
                                    <div id="pixQrCodeContainer" class="mb-3">
                                        <img id="pixQrCodeImage" src="" alt="QR Code PIX" style="max-width: 300px; border: 1px solid #ddd; border-radius: 8px; padding: 10px; background: white;">
                                    </div>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="pixCopyPaste" readonly>
                                        <button class="btn btn-primary" onclick="copiarPix()">
                                            <i class="fas fa-copy"></i> Copiar
                                        </button>
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Aguardando confirmação do pagamento...
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Credit Card Payment -->
                            <div id="creditCardPaymentContainer" style="display: none;">
                                <h5 class="mb-3">Pagamento via Cartão de Crédito</h5>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Para pagamento com cartão, você será redirecionado para a página segura do Asaas.
                                </div>
                                <div class="text-center mt-3">
                                    <button class="btn btn-primary" onclick="abrirPagamentoCartao()">
                                        <i class="fas fa-credit-card"></i> Ir para Pagamento
                                    </button>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button class="btn btn-secondary" onclick="voltarParaResumo()">Voltar</button>
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
            
            // Setup CPF listener when modal is created
            setTimeout(() => {
                setupCpfFieldListener();
                // Verificar imediatamente se CPF já está preenchido
                verificarEesconderMensagemCpf();
                // Verificar novamente após um pequeno delay para garantir
                setTimeout(() => {
                    verificarEesconderMensagemCpf();
                }, 200);
            }, 100);
            
            // Show first step
            currentCheckoutStep = 1;
            mostrarPasso(1);
        }
        
        let currentCheckoutStep = 1;
        let clienteData = null;
        let clienteEnderecos = [];
        let deliveryFee = 0;
        let paymentMethod = 'on_delivery';
        let onlinePaymentMethod = 'PIX'; // PIX or CREDIT_CARD
        let formaPagamentoDetalhada = '';
        let trocoPara = null;
        let checkoutCompleted = false; // Flag para indicar se o checkout foi completado
        
        function mostrarPasso(step) {
            // Hide all steps
            for (let i = 1; i <= 7; i++) {
                const stepEl = document.getElementById('checkoutStep' + i);
                if (stepEl) stepEl.style.display = 'none';
            }
            // Also hide step 4b
            const step4b = document.getElementById('checkoutStep4b');
            if (step4b) {
                step4b.style.display = 'none';
            }
            
            // Show current step
            const currentStepEl = document.getElementById('checkoutStep' + step);
            if (currentStepEl) {
                currentStepEl.style.display = 'block';
                currentCheckoutStep = step;
                
                // Mapear nomes das etapas
                const stepNames = {
                    1: 'Telefone',
                    2: 'Dados do Cliente',
                    3: 'Endereço de Entrega',
                    4: 'Forma de Pagamento',
                    '4b': 'Método de Pagamento Online',
                    5: 'Forma de Pagamento na Entrega',
                    6: 'Resumo do Pedido',
                    7: 'Finalizar Pagamento'
                };
                
                const stepName = stepNames[step] || `Etapa ${step}`;
                const stepNumber = (step === '4b') ? 4.5 : parseFloat(step);
                
                // Calcular valor total do carrinho
                const totalValue = cart.reduce((sum, item) => {
                    return sum + (parseFloat(item.preco_normal || 0) * (item.quantity || 1));
                }, 0);
                
                // Disparar evento de progresso do checkout
                trackPixelEvent('CheckoutProgress', {
                    checkout_step: step.toString(),
                    step_name: stepName,
                    step_number: stepNumber,
                    value: totalValue
                });
                
                // Verificar CPF quando os campos de dados do cliente são exibidos (no step 1)
                if (step === 1) {
                    // Setup CPF listener quando os campos aparecem
                    const customerDataFields = document.getElementById('customerDataFields');
                    if (customerDataFields && customerDataFields.style.display !== 'none') {
                        setupCpfFieldListener();
                        
                        // Verificar se CPF já está preenchido e esconder mensagem
                        setTimeout(() => {
                            verificarEesconderMensagemCpf();
                        }, 50);
                        setTimeout(() => {
                            verificarEesconderMensagemCpf();
                        }, 200);
                        setTimeout(() => {
                            verificarEesconderMensagemCpf();
                        }, 500);
                    }
                }
                
                // Show address section if step 3 and delivery type
                if (step === 3) {
                    // Get delivery type from cart items section (inside cartItems)
                    const deliveryTypeSelect = document.getElementById('deliveryTypeSelect');
                    const deliveryType = deliveryTypeSelect ? deliveryTypeSelect.value : 'delivery'; // Default to delivery if not found
                    
                    console.log('Step 3 - Delivery type:', deliveryType, 'Endereços:', clienteEnderecos?.length || 0);
                    
                    // If pickup, skip this step and go to payment
                    if (deliveryType === 'pickup') {
                        console.log('Pickup selecionado, pulando passo 3');
                        mostrarPasso(4);
                        return;
                    }
                    
                    if (deliveryType === 'delivery') {
                        // Always show address selection/entry for delivery
                        // Show saved addresses if available
                        if (clienteEnderecos && clienteEnderecos.length > 0) {
                            console.log('Mostrando endereços existentes');
                            const enderecosExistentesEl = document.getElementById('enderecosExistentes');
                            const enderecoSectionEl = document.getElementById('enderecoSection');
                            if (enderecosExistentesEl) enderecosExistentesEl.style.display = 'block';
                            if (enderecoSectionEl) enderecoSectionEl.style.display = 'none';
                            carregarEnderecosExistentes();
                        } else {
                            // No saved addresses - show form to add new one
                            console.log('Mostrando formulário de novo endereço');
                            const enderecosExistentesEl = document.getElementById('enderecosExistentes');
                            const enderecoSectionEl = document.getElementById('enderecoSection');
                            if (enderecosExistentesEl) enderecosExistentesEl.style.display = 'none';
                            if (enderecoSectionEl) enderecoSectionEl.style.display = 'block';
                        }
                    } else {
                        // Not delivery - hide address sections
                        console.log('Não é delivery, escondendo seções de endereço');
                        const enderecosExistentesEl = document.getElementById('enderecosExistentes');
                        const enderecoSectionEl = document.getElementById('enderecoSection');
                        if (enderecosExistentesEl) enderecosExistentesEl.style.display = 'none';
                        if (enderecoSectionEl) enderecoSectionEl.style.display = 'none';
                    }
                }
            } else if (step === '4b') {
                // Show step 4b (online payment method selection)
                if (step4b) {
                    step4b.style.display = 'block';
                    currentCheckoutStep = '4b';
                }
            }
        }
        
        async function proximoPasso(fromStep) {
            if (fromStep === 1) {
                // Validar se telefone foi buscado
                const customerDataFields = document.getElementById('customerDataFields');
                if (!customerDataFields || customerDataFields.style.display === 'none') {
                    alert('Por favor, busque o cliente pelo telefone primeiro');
                    return;
                }
                
                // Validar dados do cliente
                const name = document.getElementById('customerName').value.trim();
                const cpf = document.getElementById('customerCpf').value.trim();
                const errorDiv = document.getElementById('checkoutError');
                const cpfRequiredMsg = document.getElementById('cpfRequiredMsg');
                
                if (errorDiv) errorDiv.style.display = 'none';
                if (cpfRequiredMsg) cpfRequiredMsg.style.display = 'none';
                
                if (!name) {
                    if (errorDiv) {
                        errorDiv.textContent = 'Por favor, informe o nome';
                        errorDiv.style.display = 'block';
                    }
                    return;
                }
                
                // CPF is now optional - remove validation requirement
                // Just hide/show message based on field state
                if (cpf && cpf.trim()) {
                    if (cpfRequiredMsg) cpfRequiredMsg.style.display = 'none';
                    document.getElementById('customerCpf').classList.remove('is-invalid');
                } else {
                    document.getElementById('customerCpf').classList.remove('is-invalid');
                }
                
                // Check delivery type - if pickup, skip step 3 (address) and go to step 4 (payment)
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                if (deliveryType === 'pickup') {
                    mostrarPasso(4);
                    return;
                } else {
                    // Go to address step
                    mostrarPasso(3);
                    return;
                }
            } else if (fromStep === 2) {
                // Este passo não é mais usado, mas mantido para compatibilidade
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                if (deliveryType === 'pickup') {
                    mostrarPasso(4);
                    return;
                } else {
                    mostrarPasso(3);
                    return;
                }
            } else if (fromStep === 3) {
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                if (deliveryType === 'delivery') {
                    // Check if address was selected from saved addresses
                    const enderecoSelecionado = document.getElementById('enderecoSelecionado').value;
                    const enderecoSectionVisible = document.getElementById('enderecoSection').style.display === 'block';
                    
                    if (enderecoSelecionado === '' && enderecoSectionVisible) {
                        // New address form is visible - user must click "Adicionar Endereço" first
                        alert('Por favor, clique em "Adicionar Endereço" para salvar o endereço antes de continuar.');
                        return;
                    } else if (enderecoSelecionado === '' && !enderecoSectionVisible) {
                        // No address selected and form not visible (shouldn't happen, but check anyway)
                        alert('Por favor, selecione ou cadastre um endereço de entrega');
                        return;
                    }
                }
            }
            
            if (fromStep === 4) {
                // Check delivery type and payment method
                const deliveryType = document.getElementById('deliveryTypeSelect').value;
                const selectedPayment = document.querySelector('input[name="paymentMethod"]:checked');
                
                // If online payment selected, show step 4b to choose PIX or Card
                if (selectedPayment && selectedPayment.value === 'online') {
                    mostrarPasso('4b');
                    return;
                }
                
                // Only show step 5 (payment details) if payment is on_delivery AND delivery type is delivery (not pickup)
                if (selectedPayment && selectedPayment.value === 'on_delivery' && deliveryType === 'delivery') {
                    mostrarPasso(5);
                } else {
                    // For pickup, go directly to summary
                    mostrarPasso(6);
                    updateOrderSummary();
                }
                return;
            }
            
            if (fromStep === '4b') {
                // After choosing online payment method (PIX or Card), generate invoice directly
                // Skip summary step and go directly to submit order
                // CPF is now optional - will try to create payment without it
                await submitOrder();
                return;
            }
            
            if (fromStep === 5) {
                // Validate payment details before proceeding
                const formaPagamento = document.getElementById('formaPagamentoDetalhada').value;
                if (!formaPagamento) {
                    alert('Por favor, selecione a forma de pagamento');
                    return;
                }
                
                if (formaPagamento === 'Dinheiro') {
                    const troco = document.getElementById('trocoPara').value;
                    if (!troco || parseFloat(troco) <= 0) {
                        alert('Por favor, informe o valor do troco');
                        return;
                    }
                    trocoPara = parseFloat(troco);
                } else {
                    trocoPara = null;
                }
                
                formaPagamentoDetalhada = formaPagamento;
                mostrarPasso(6);
                updateOrderSummary();
                return;
            }
            
            mostrarPasso(fromStep + 1);
        }
        
        function toggleTrocoField() {
            const formaPagamento = document.getElementById('formaPagamentoDetalhada').value;
            const trocoField = document.getElementById('trocoField');
            if (formaPagamento === 'Dinheiro') {
                trocoField.style.display = 'block';
                document.getElementById('trocoPara').required = true;
            } else {
                trocoField.style.display = 'none';
                document.getElementById('trocoPara').required = false;
                document.getElementById('trocoPara').value = '';
            }
        }
        
        function voltarPasso() {
            if (currentCheckoutStep > 1) {
                // If going back from step 7 (payment), go to step 6 (summary)
                if (currentCheckoutStep === 7) {
                    mostrarPasso(6);
                    updateOrderSummary();
                    return;
                }
                
                // If going back from step 6 to step 5, or from step 5 to step 4, handle navigation
                if (currentCheckoutStep === 6) {
                    // Going back from summary - determine which step to go to
                    const deliveryType = document.getElementById('deliveryTypeSelect').value;
                    const selectedPayment = document.querySelector('input[name="paymentMethod"]:checked');
                    if (selectedPayment && selectedPayment.value === 'on_delivery' && deliveryType === 'delivery') {
                        // Go back to step 5 (payment details) - preserve values
                        mostrarPasso(5);
                        // Restore values if they exist
                        if (formaPagamentoDetalhada) {
                            const formaPagamentoSelect = document.getElementById('formaPagamentoDetalhada');
                            if (formaPagamentoSelect) formaPagamentoSelect.value = formaPagamentoDetalhada;
                            toggleTrocoField();
                            if (trocoPara) {
                                const trocoInput = document.getElementById('trocoPara');
                                if (trocoInput) trocoInput.value = trocoPara;
                            }
                        }
                    } else {
                        // Go back to step 4 (payment method selection)
                        mostrarPasso(4);
                    }
                } else if (currentCheckoutStep === 5) {
                    // Going back from payment details - clear values and go to step 4
                    formaPagamentoDetalhada = '';
                    trocoPara = null;
                    const formaPagamentoSelect = document.getElementById('formaPagamentoDetalhada');
                    const trocoInput = document.getElementById('trocoPara');
                    if (formaPagamentoSelect) formaPagamentoSelect.value = '';
                    if (trocoInput) {
                        trocoInput.value = '';
                        trocoInput.required = false;
                    }
                    const trocoField = document.getElementById('trocoField');
                    if (trocoField) trocoField.style.display = 'none';
                    mostrarPasso(4);
                } else if (currentCheckoutStep === 4) {
                    // Going back from step 4 - check if should go to step 3 (address) or step 1 (customer data)
                    const deliveryType = document.getElementById('deliveryTypeSelect').value;
                    if (deliveryType === 'delivery') {
                        // Go back to step 3 (address)
                        mostrarPasso(3);
                    } else {
                        // Go back to step 1 (customer data) since pickup skips address
                        mostrarPasso(1);
                    }
                } else if (currentCheckoutStep === 3) {
                    // Going back from step 3 (address) - go to step 1 (customer data)
                    mostrarPasso(1);
                } else {
                    // Normal back navigation
                    mostrarPasso(currentCheckoutStep - 1);
                }
            }
        }
        
        function closeCheckoutModal() {
            // Verificar se o checkout foi realmente abandonado (não foi completado)
            // Se chegou até aqui sem completar, é um abandono
            if (!checkoutCompleted && currentCheckoutStep > 0 && currentCheckoutStep < 7) {
                // Mapear nomes das etapas
                const stepNames = {
                    1: 'Telefone',
                    2: 'Dados do Cliente',
                    3: 'Endereço de Entrega',
                    4: 'Forma de Pagamento',
                    '4b': 'Método de Pagamento Online',
                    5: 'Forma de Pagamento na Entrega',
                    6: 'Resumo do Pedido',
                    7: 'Finalizar Pagamento'
                };
                
                const stepName = stepNames[currentCheckoutStep] || `Etapa ${currentCheckoutStep}`;
                const stepNumber = (currentCheckoutStep === '4b') ? 4.5 : parseFloat(currentCheckoutStep);
                
                // Calcular valor total do carrinho
                const totalValue = cart.reduce((sum, item) => {
                    return sum + (parseFloat(item.preco_normal || 0) * (item.quantity || 1));
                }, 0);
                
                const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                
                // Preparar itens para o evento
                const items = cart.map(item => ({
                    item_id: item.id.toString(),
                    item_name: item.nome,
                    price: parseFloat(item.preco_normal || 0),
                    quantity: item.quantity || 1
                }));
                
                // Disparar evento de checkout abandonado
                trackPixelEvent('CheckoutAbandoned', {
                    abandoned_step: currentCheckoutStep.toString(),
                    step_name: stepName,
                    step_number: stepNumber,
                    value: totalValue,
                    num_items: totalItems,
                    items: items
                });
            }
            
            const modal = document.getElementById('checkoutModal');
            if (modal) modal.remove();
            currentCheckoutStep = 1;
            clienteData = null;
            checkoutCompleted = false; // Resetar flag para próximo checkout
            
            // Reset customer data fields visibility for next checkout
            const customerDataFields = document.getElementById('customerDataFields');
            if (customerDataFields) {
                customerDataFields.style.display = 'none';
            }
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
                    clienteEnderecos = data.enderecos || [];
                    
                    // Mostrar campos de dados do cliente
                    const customerDataFields = document.getElementById('customerDataFields');
                    if (customerDataFields) {
                        customerDataFields.style.display = 'block';
                    }
                    
                    // Preencher campos com dados do cliente (se existirem)
                    document.getElementById('customerName').value = data.cliente.nome && data.cliente.nome !== 'Cliente' ? data.cliente.nome : '';
                    document.getElementById('customerEmail').value = data.cliente.email || '';
                    const cpfField = document.getElementById('customerCpf');
                    cpfField.value = data.cliente.cpf || '';
                    
                    // Esconder mensagem de CPF obrigatório se o CPF estiver preenchido
                    // Usar setTimeout para garantir que o valor foi definido antes de verificar
                    setTimeout(() => {
                        verificarEesconderMensagemCpf();
                    }, 100);
                    
                    if (data.cliente.nome && data.cliente.nome !== 'Cliente') {
                        resultDiv.className = 'alert alert-success';
                        resultDiv.innerHTML = '✅ Cliente encontrado: ' + data.cliente.nome;
                    } else {
                        resultDiv.className = 'alert alert-info';
                        resultDiv.innerHTML = '✅ Cliente registrado. Preencha seus dados abaixo.';
                    }
                    
                    // Atualizar cliente quando nome/email/cpf forem preenchidos
                    setupCustomerDataUpdate();
                } else {
                    // Erro ao buscar/criar cliente
                    clienteData = null;
                    clienteEnderecos = [];
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.innerHTML = '❌ Erro ao processar cliente. Tente novamente.';
                }
            } catch (error) {
                console.error('Erro ao buscar cliente:', error);
                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Erro ao buscar cliente. Tente novamente.';
            }
        }
        
        // Função auxiliar para verificar e esconder mensagem de CPF se estiver preenchido
        function verificarEesconderMensagemCpf() {
            const cpfField = document.getElementById('customerCpf');
            const cpfRequiredMsg = document.getElementById('cpfRequiredMsg');
            if (cpfField && cpfRequiredMsg) {
                const cpfValue = cpfField.value.trim();
                if (cpfValue) {
                    cpfRequiredMsg.style.display = 'none';
                    cpfField.classList.remove('is-invalid');
                    return true; // CPF está preenchido
                }
            }
            return false; // CPF não está preenchido
        }
        
        // Setup CPF field listener to hide message when CPF is filled
        function setupCpfFieldListener() {
            const cpfField = document.getElementById('customerCpf');
            if (cpfField) {
                // Remove existing listener if any
                const existingHandler = cpfField._cpfHandler;
                if (existingHandler) {
                    cpfField.removeEventListener('input', existingHandler);
                }
                
                // Create new handler
                const cpfHandler = function() {
                    verificarEesconderMensagemCpf();
                };
                
                // Store handler reference and add listener
                cpfField._cpfHandler = cpfHandler;
                cpfField.addEventListener('input', cpfHandler);
                
                // Verificar imediatamente se já está preenchido
                verificarEesconderMensagemCpf();
            }
        }
        
        // Setup automatic customer data update when fields change
        function setupCustomerDataUpdate() {
            // Remove existing listeners
            const nameField = document.getElementById('customerName');
            const emailField = document.getElementById('customerEmail');
            const cpfField = document.getElementById('customerCpf');
            
            // Debounce function
            let updateTimeout;
            const debouncedUpdate = () => {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(() => {
                    atualizarDadosCliente();
                }, 1000); // Wait 1 second after user stops typing
            };
            
            // Add event listeners
            if (nameField) {
                nameField.removeEventListener('input', debouncedUpdate);
                nameField.addEventListener('input', debouncedUpdate);
            }
            if (emailField) {
                emailField.removeEventListener('input', debouncedUpdate);
                emailField.addEventListener('input', debouncedUpdate);
            }
            if (cpfField) {
                cpfField.removeEventListener('input', debouncedUpdate);
                cpfField.addEventListener('input', debouncedUpdate);
            }
            
            // Setup CPF listener separately
            setupCpfFieldListener();
        }
        
        // Update customer data automatically
        async function atualizarDadosCliente() {
            if (!clienteData || !clienteData.id) {
                console.log('atualizarDadosCliente: Cliente não encontrado');
                return; // No customer to update
            }
            
            const nome = document.getElementById('customerName').value.trim();
            const email = document.getElementById('customerEmail').value.trim();
            const cpf = document.getElementById('customerCpf').value.trim();
            
            // Don't update if name is empty
            if (!nome) {
                console.log('atualizarDadosCliente: Nome vazio, não atualizando');
                return;
            }
            
            // Check if there are any changes
            const hasChanges = (nome !== clienteData.nome) || 
                              (email !== (clienteData.email || '')) || 
                              (cpf !== (clienteData.cpf || ''));
            
            if (!hasChanges) {
                console.log('atualizarDadosCliente: Nenhuma mudança detectada');
                return;
            }
            
            console.log('atualizarDadosCliente: Atualizando dados do cliente', { nome, email, cpf });
            
            try {
                const formData = new FormData();
                formData.append('action', 'atualizar_dados');
                formData.append('cliente_id', clienteData.id);
                formData.append('tenant_id', <?php echo $tenantId; ?>);
                formData.append('nome', nome);
                formData.append('email', email); // Can be empty
                formData.append('cpf', cpf);
                
                const response = await fetch('mvc/ajax/clientes_cardapio_online.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.cliente) {
                        clienteData = data.cliente;
                        console.log('Dados do cliente atualizados:', clienteData);
                    } else {
                        console.error('Erro ao atualizar dados:', data.message);
                    }
                } else {
                    const errorText = await response.text();
                    console.error('Erro HTTP ao atualizar dados:', response.status, errorText);
                }
            } catch (error) {
                console.error('Erro ao atualizar dados do cliente:', error);
                // Don't show error to user, just log it
            }
        }
        
        function selectPayment(method) {
            paymentMethod = method;
            document.getElementById('payment' + (method === 'online' ? 'Online' : 'OnDelivery')).checked = true;
            
            // Mostrar/esconder botões de pagamento online
            const onlineButtons = document.getElementById('onlinePaymentButtons');
            if (onlineButtons) {
                onlineButtons.style.display = (method === 'online') ? 'block' : 'none';
            }
            
            // Esconder/mostrar botão Continuar
            const btnContinuar = document.getElementById('btnContinuarStep4');
            if (btnContinuar) {
                btnContinuar.style.display = (method === 'online') ? 'none' : 'block';
            }
            
            // Sempre verificar e esconder mensagem se CPF estiver preenchido
            verificarEesconderMensagemCpf();
        }
        
        function selectOnlinePaymentMethod(method) {
            onlinePaymentMethod = method;
            document.getElementById('onlinePayment' + (method === 'PIX' ? 'PIX' : 'Card')).checked = true;
        }
        
        async function salvarNovoEnderecoCliente(enderecoData) {
            if (!clienteData || !clienteData.id) {
                console.error('Cliente não encontrado. clienteData:', clienteData);
                throw new Error('Cliente não encontrado. Por favor, preencha os dados do cliente primeiro.');
            }
            
            try {
                // Check if address already exists
                const addressExists = clienteEnderecos.some(e => {
                    const existingAddress = `${e.logradouro || ''}, ${e.numero || ''}`.trim();
                    const newAddress = enderecoData.endereco.trim();
                    return existingAddress === newAddress && 
                           (e.cidade || '').trim() === (enderecoData.cidade || '').trim();
                });
                
                if (addressExists) {
                    console.log('Endereço já existe na lista');
                    return true;
                }
                
                const formData = new FormData();
                formData.append('action', 'adicionar_endereco_cardapio');
                formData.append('cliente_id', clienteData.id);
                formData.append('tenant_id', <?php echo $tenantId; ?>);
                
                // Parse address string to extract logradouro and numero
                const addressParts = enderecoData.endereco.split(',');
                const logradouro = addressParts[0]?.trim() || enderecoData.endereco;
                const numero = addressParts[1]?.trim() || '';
                
                // Send as nested array for PHP to parse correctly
                formData.append('endereco[logradouro]', logradouro);
                formData.append('endereco[numero]', numero);
                formData.append('endereco[bairro]', enderecoData.bairro || '');
                formData.append('endereco[cidade]', enderecoData.cidade || '');
                formData.append('endereco[estado]', enderecoData.estado || '');
                formData.append('endereco[cep]', enderecoData.cep || '');
                
                console.log('Enviando endereço:', {
                    cliente_id: clienteData.id,
                    logradouro,
                    numero,
                    bairro: enderecoData.bairro,
                    cidade: enderecoData.cidade,
                    estado: enderecoData.estado,
                    cep: enderecoData.cep
                });
                
                const response = await fetch('mvc/ajax/clientes_cardapio_online.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Erro HTTP ao salvar endereço:', response.status, errorText);
                    let errorMsg = `Erro HTTP: ${response.status} ${response.statusText}`;
                    try {
                        const errorData = JSON.parse(errorText);
                        errorMsg = errorData.message || errorMsg;
                    } catch (e) {
                        // Not JSON, use text as is
                    }
                    throw new Error(errorMsg);
                }
                
                const data = await response.json();
                console.log('Resposta do servidor ao salvar endereço:', data);
                
                if (data.success && data.enderecos) {
                    // Update local addresses list
                    clienteEnderecos = data.enderecos || [];
                    console.log('Endereço salvo com sucesso. Total de endereços:', clienteEnderecos.length);
                    return true;
                } else {
                    const errorMsg = data.message || 'Erro desconhecido ao salvar endereço';
                    console.error('Erro ao salvar endereço:', errorMsg, data);
                    throw new Error(errorMsg);
                }
            } catch (error) {
                console.error('Erro ao salvar endereço do cliente:', error);
                throw error; // Re-throw to be caught by caller
            }
        }
        
        async function carregarEnderecosExistentes() {
            const select = document.getElementById('enderecoSelecionado');
            select.innerHTML = '<option value="">-- Selecione um endereço --</option>';
            
            clienteEnderecos.forEach((endereco, index) => {
                const option = document.createElement('option');
                option.value = index;
                const logradouro = endereco.logradouro || '';
                const numero = endereco.numero || '';
                const bairro = endereco.bairro || '';
                const cidade = endereco.cidade || '';
                const estado = endereco.estado || '';
                const enderecoTexto = `${logradouro}${numero ? ', ' + numero : ''}${bairro ? ' - ' + bairro : ''}${cidade ? ', ' + cidade : ''}${estado ? '/' + estado : ''}`;
                option.textContent = enderecoTexto.trim() || 'Endereço sem descrição';
                select.appendChild(option);
            });
        }
        
        function preencherEnderecoSelecionado() {
            const select = document.getElementById('enderecoSelecionado');
            const index = select.value;
            
            if (index === '' || !clienteEnderecos[index]) {
                // Clear fields if no address selected
                document.getElementById('deliveryAddress').value = '';
                document.getElementById('deliveryNeighborhood').value = '';
                document.getElementById('deliveryCity').value = '';
                document.getElementById('deliveryCEP').value = '';
                document.getElementById('deliveryEstado').value = '';
                deliveryFee = 0;
                atualizarTaxaEntregaInfo();
                return;
            }
            
            // Hide address form (user selected from saved addresses)
            document.getElementById('enderecoSection').style.display = 'none';
            
            // Fill hidden fields with selected address data
            const endereco = clienteEnderecos[index];
            const logradouro = endereco.logradouro || '';
            const numero = endereco.numero || '';
            document.getElementById('deliveryAddress').value = `${logradouro}${numero ? ', ' + numero : ''}`.trim();
            document.getElementById('deliveryNeighborhood').value = endereco.bairro || '';
            document.getElementById('deliveryCity').value = endereco.cidade || '';
            document.getElementById('deliveryCEP').value = endereco.cep || '';
            document.getElementById('deliveryEstado').value = endereco.estado || '';
            
            // Calculate delivery fee automatically
            calcularTaxaEntregaAutomatico();
        }
        
        function atualizarTaxaEntregaInfo() {
            const infoDiv = document.getElementById('taxaEntregaInfo');
            const textoDiv = document.getElementById('taxaEntregaTexto');
            const infoDivSelect = document.getElementById('taxaEntregaInfoSelect');
            const textoDivSelect = document.getElementById('taxaEntregaTextoSelect');
            
            // Update both info divs (one in address form, one in select section)
            if (deliveryFee > 0) {
                if (infoDiv && textoDiv) {
                    infoDiv.style.display = 'block';
                    infoDiv.className = 'alert alert-success mt-2';
                    textoDiv.innerHTML = `<i class="fas fa-check-circle"></i> Taxa de entrega: R$ ${deliveryFee.toFixed(2).replace('.', ',')}`;
                }
                if (infoDivSelect && textoDivSelect) {
                    infoDivSelect.style.display = 'block';
                    infoDivSelect.className = 'alert alert-success mt-2 mb-3';
                    textoDivSelect.innerHTML = `<i class="fas fa-check-circle"></i> Taxa de entrega: R$ ${deliveryFee.toFixed(2).replace('.', ',')}`;
                }
            } else {
                if (infoDiv) infoDiv.style.display = 'none';
                if (infoDivSelect) infoDivSelect.style.display = 'none';
            }
            
            // Update order summary if visible
            if (currentCheckoutStep === 5) {
                updateOrderSummary();
            }
        }
        
        function mostrarNovoEndereco() {
            document.getElementById('enderecosExistentes').style.display = 'none';
            document.getElementById('enderecoSection').style.display = 'block';
            // Clear fields
            document.getElementById('deliveryAddress').value = '';
            document.getElementById('deliveryNeighborhood').value = '';
            document.getElementById('deliveryCity').value = '';
            document.getElementById('deliveryCEP').value = '';
            document.getElementById('deliveryEstado').value = '';
            document.getElementById('enderecoSelecionado').value = '';
            deliveryFee = 0;
            atualizarTaxaEntregaInfo();
        }
        
        async function adicionarEndereco() {
            const address = document.getElementById('deliveryAddress').value.trim();
            const city = document.getElementById('deliveryCity').value.trim();
            
            if (!address || !city) {
                alert('Por favor, preencha pelo menos o endereço e a cidade.');
                return;
            }
            
            // Cliente já foi criado quando inseriu o telefone, não precisa validar novamente
            
            const btn = document.getElementById('btnAdicionarEndereco');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            
            try {
                // Cliente já deve existir (criado quando inseriu o telefone)
                if (!clienteData || !clienteData.id) {
                    throw new Error('Cliente não encontrado. Por favor, volte ao passo anterior e insira o telefone.');
                }
                
                // Atualizar dados do cliente se necessário
                await atualizarDadosCliente();
                
                console.log('Cliente pronto para adicionar endereço. ID:', clienteData.id);
                
                // Now save the address
                const enderecoData = {
                    endereco: address,
                    bairro: document.getElementById('deliveryNeighborhood').value.trim(),
                    cidade: city,
                    cep: document.getElementById('deliveryCEP').value.trim(),
                    estado: document.getElementById('deliveryEstado').value.trim()
                };
                
                const saved = await salvarNovoEnderecoCliente(enderecoData);
                
                // Show success message
                const infoDiv = document.getElementById('taxaEntregaInfo');
                const textoDiv = document.getElementById('taxaEntregaTexto');
                infoDiv.style.display = 'block';
                infoDiv.className = 'alert alert-success mt-2';
                textoDiv.innerHTML = '<i class="fas fa-check-circle"></i> Endereço adicionado com sucesso!';
                
                // Hide address form and show address selection
                document.getElementById('enderecoSection').style.display = 'none';
                document.getElementById('enderecosExistentes').style.display = 'block';
                
                // Reload addresses in select
                carregarEnderecosExistentes();
                
                // Find and select the newly added address
                setTimeout(() => {
                    const select = document.getElementById('enderecoSelecionado');
                    if (clienteEnderecos.length > 0) {
                        // Select the last address (most recently added)
                        const lastIndex = clienteEnderecos.length - 1;
                        select.value = lastIndex;
                        preencherEnderecoSelecionado();
                    }
                }, 500);
            } catch (error) {
                console.error('Erro ao adicionar endereço:', error);
                alert('Erro ao adicionar endereço: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
        
        
        async function calcularTaxaEntregaAutomatico() {
            const address = document.getElementById('deliveryAddress').value.trim();
            const city = document.getElementById('deliveryCity').value.trim();
            
            if (!address || !city) {
                deliveryFee = 0;
                atualizarTaxaEntregaInfo();
                return;
            }
            
            await calcularTaxaEntrega(true);
        }
        
        async function calcularTaxaEntrega(silent = false) {
            const address = document.getElementById('deliveryAddress').value.trim();
            const city = document.getElementById('deliveryCity').value.trim();
            
            if (!address || !city) {
                if (!silent) {
                    alert('Por favor, preencha pelo menos o endereço e a cidade.');
                }
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
                    // Show loading state in info divs
                    const infoDiv = document.getElementById('taxaEntregaInfo');
                    const textoDiv = document.getElementById('taxaEntregaTexto');
                    const infoDivSelect = document.getElementById('taxaEntregaInfoSelect');
                    const textoDivSelect = document.getElementById('taxaEntregaTextoSelect');
                    
                    if (infoDiv && textoDiv) {
                        infoDiv.style.display = 'block';
                        infoDiv.className = 'alert alert-info mt-2';
                        textoDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando taxa de entrega...';
                    }
                    if (infoDivSelect && textoDivSelect) {
                        infoDivSelect.style.display = 'block';
                        infoDivSelect.className = 'alert alert-info mt-2 mb-3';
                        textoDivSelect.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando taxa de entrega...';
                    }
                    
                    try {
                        const destination = `${address}, ${city}`;
                        console.log('Enviando requisição para calcular taxa:', {
                            origin: filialData.endereco,
                            destination: destination,
                            webhookUrl: deliveryMapsWebhookUrl
                        });
                        
                        // Create AbortController for timeout
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 seconds timeout
                        
                        // Build URL with query parameters for GET request
                        const url = new URL(deliveryMapsWebhookUrl);
                        url.searchParams.append('origin', filialData.endereco);
                        url.searchParams.append('destination', destination);
                        
                        console.log('URL completa:', url.toString());
                        
                        const response = await fetch(url.toString(), {
                            method: 'GET',
                            headers: { 
                                'Accept': 'application/json'
                            },
                            signal: controller.signal
                        });
                        
                        clearTimeout(timeoutId);
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
                        }
                        
                        const data = await response.json();
                        console.log('Resposta do webhook:', data);
                        
                        // Try different response formats - n8n returns deliveryFee, distance, deliveryTime
                        let valor = null;
                        let distancia = null;
                        let tempoEntrega = null;
                        
                        // Check if data is an array (n8n might return array)
                        const responseData = Array.isArray(data) && data.length > 0 ? data[0] : data;
                        
                        // Check for deliveryFee (primary field from n8n)
                        if (responseData.deliveryFee !== undefined) {
                            valor = responseData.deliveryFee;
                        } else if (responseData.delivery_fee !== undefined) {
                            valor = responseData.delivery_fee;
                        } else if (responseData.valor !== undefined) {
                            valor = responseData.valor;
                        } else if (responseData.value !== undefined) {
                            valor = responseData.value;
                        } else if (responseData.taxa !== undefined) {
                            valor = responseData.taxa;
                        }
                        
                        // Extract distance and delivery time if available
                        if (responseData.distance !== undefined) {
                            distancia = responseData.distance;
                        }
                        if (responseData.deliveryTime !== undefined) {
                            tempoEntrega = responseData.deliveryTime;
                        }
                        
                        if (valor !== null && valor !== undefined) {
                            deliveryFee = parseFloat(valor);
                            if (isNaN(deliveryFee)) {
                                throw new Error('Valor retornado não é um número válido');
                            }
                            
                            // Update UI with delivery fee info
                            atualizarTaxaEntregaInfo();
                            
                            // Update order summary if visible
                            if (currentCheckoutStep === 5) {
                                updateOrderSummary();
                            }
                        } else {
                            // Try to parse from status string if it's a formatted message
                            const statusText = responseData.status || '';
                            const taxaMatch = statusText.match(/Taxa de entrega[:\s]*R\$\s*([\d,]+\.?\d*)/i);
                            if (taxaMatch) {
                                deliveryFee = parseFloat(taxaMatch[1].replace(',', '.'));
                                if (!isNaN(deliveryFee)) {
                                    atualizarTaxaEntregaInfo();
                                    if (currentCheckoutStep === 5) {
                                        updateOrderSummary();
                                    }
                                } else {
                                    throw new Error('Resposta do webhook não contém valor válido. Estrutura recebida: ' + JSON.stringify(data));
                                }
                            } else {
                                throw new Error('Resposta do webhook não contém valor válido. Estrutura recebida: ' + JSON.stringify(data));
                            }
                        }
                    } catch (error) {
                        console.error('Erro ao calcular distância:', error);
                        console.error('Detalhes do erro:', {
                            message: error.message,
                            name: error.name,
                            stack: error.stack
                        });
                        
                        let errorMessage = 'Erro ao calcular taxa de entrega';
                        if (error.name === 'AbortError') {
                            errorMessage = 'Timeout ao calcular taxa de entrega. O servidor demorou muito para responder.';
                        } else if (error.message.includes('Failed to fetch')) {
                            errorMessage = 'Não foi possível conectar ao servidor de cálculo de distância. Verifique sua conexão ou a configuração do webhook.';
                        } else {
                            errorMessage = 'Erro ao calcular taxa de entrega: ' + error.message;
                        }
                        
                        if (!silent) {
                            alert(errorMessage + ' Usando taxa fixa de R$ ' + filialData.taxa_delivery_fixa.toFixed(2).replace('.', ','));
                        }
                        deliveryFee = filialData.taxa_delivery_fixa;
                        atualizarTaxaEntregaInfo();
                        if (currentCheckoutStep === 5) {
                            updateOrderSummary();
                        }
                    } finally {
                        // Info div is already updated in atualizarTaxaEntregaInfo or error handler
                    }
                } else {
                    if (!silent) {
                        alert('Webhook de cálculo de distância não configurado. Usando taxa fixa de R$ ' + filialData.taxa_delivery_fixa.toFixed(2).replace('.', ','));
                    }
                    deliveryFee = filialData.taxa_delivery_fixa;
                    atualizarTaxaEntregaInfo();
                }
            } else {
                deliveryFee = filialData.taxa_delivery_fixa;
                atualizarTaxaEntregaInfo();
            }
            
            if (currentCheckoutStep === 5) {
                updateOrderSummary();
            }
        }
        
        function updateOrderSummary() {
            const summary = document.getElementById('orderSummary');
            const total = document.getElementById('orderTotal');
            
            let subtotal = cart.reduce((sum, item) => sum + (parseFloat(item.preco_normal || 0) * item.quantity), 0);
            const deliveryType = document.getElementById('deliveryTypeSelect').value;
            let totalValue = subtotal + (deliveryType === 'delivery' ? deliveryFee : 0);
            
            let paymentInfo = '';
            if (paymentMethod === 'on_delivery' && formaPagamentoDetalhada) {
                paymentInfo = `
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Pagamento:</strong></span>
                            <span>${formaPagamentoDetalhada}</span>
                        </div>
                        ${trocoPara && formaPagamentoDetalhada === 'Dinheiro' ? `
                            <div class="d-flex justify-content-between mb-2">
                                <span>Troco para:</span>
                                <span>R$ ${trocoPara.toFixed(2).replace('.', ',')}</span>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
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
                ${paymentInfo}
            `;
            
            total.textContent = `R$ ${totalValue.toFixed(2).replace('.', ',')}`;
        }
        
        // Prevent double submission
        let isSubmittingOrder = false;
        
        // Função simplificada para finalizar pedido online (PIX ou Cartão)
        async function finalizarPedidoOnline(paymentType) {
            // Prevent double submission
            if (isSubmittingOrder) {
                console.log('Pedido já está sendo processado, aguarde...');
                return;
            }
            
            isSubmittingOrder = true;
            
            // Obter dados do formulário
            const customerNameValue = document.getElementById('customerName').value.trim();
            const customerPhoneValue = document.getElementById('customerPhone').value.trim();
            const customerEmailValue = document.getElementById('customerEmail').value.trim();
            const customerCpfValue = document.getElementById('customerCpf').value.trim();
            
            // Validar dados básicos
            if (!customerNameValue || !customerPhoneValue) {
                alert('Por favor, preencha seus dados primeiro.');
                isSubmittingOrder = false;
                return;
            }
            
            // Validar endereço se for delivery
            const deliveryType = document.getElementById('deliveryTypeSelect').value;
            let enderecoEntrega = null;
            
            if (deliveryType === 'delivery') {
                const enderecoSelecionado = document.getElementById('enderecoSelecionado').value;
                
                if (enderecoSelecionado !== '' && clienteEnderecos[enderecoSelecionado]) {
                    const endereco = clienteEnderecos[enderecoSelecionado];
                    const logradouro = endereco.logradouro || '';
                    const numero = endereco.numero || '';
                    enderecoEntrega = {
                        endereco: `${logradouro}${numero ? ', ' + numero : ''}`.trim(),
                        bairro: endereco.bairro || '',
                        cidade: endereco.cidade || '',
                        cep: endereco.cep || '',
                        estado: endereco.estado || ''
                    };
                } else {
                    const address = document.getElementById('deliveryAddress').value.trim();
                    const city = document.getElementById('deliveryCity').value.trim();
                    if (!address || !city) {
                        alert('Por favor, preencha o endereço de entrega.');
                        isSubmittingOrder = false;
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
            }
            
            const itensDetalhados = cart.map(item => ({
                id: item.id,
                quantity: item.quantity,
                preco: item.preco_normal,
                observacao: item.observacao || '',
                ingredientes_adicionados: item.ingredientes_adicionados || [],
                ingredientes_removidos: item.ingredientes_removidos || []
            }));
            
            const orderData = {
                filial_id: <?php echo $filialId; ?>,
                tenant_id: <?php echo $tenantId; ?>,
                itens: itensDetalhados,
                tipo_entrega: deliveryType,
                taxa_entrega: deliveryFee,
                cliente_nome: customerNameValue,
                cliente_telefone: customerPhoneValue,
                cliente_email: customerEmailValue,
                cliente_cpf: customerCpfValue,
                cliente_id: clienteData ? clienteData.id : null,
                endereco_entrega: enderecoEntrega,
                forma_pagamento: 'online',
                online_payment_method: paymentType
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
                
                console.log('Resultado do pedido:', result);
                
                if (result.success) {
                    // Se tiver payment_url, abrir em nova janela
                    if (result.payment_url) {
                        // Abrir URL do Asaas em nova janela
                        window.open(result.payment_url, '_blank', 'width=800,height=600');
                        
                        // Fechar modal e limpar carrinho
                        checkoutCompleted = true;
                        cart = [];
                        updateCart();
                        closeCheckoutModal();
                        
                        // Mostrar mensagem de sucesso
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Pedido Criado!',
                                text: 'Você será redirecionado para a página de pagamento. Após o pagamento, seu pedido será confirmado.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Pedido criado com sucesso! Você será redirecionado para a página de pagamento.');
                        }
                        
                        // Iniciar verificação de status do pagamento
                        if (result.pedido_id) {
                            setupPaymentStatusCheck(result.pedido_id);
                        }
                    } else {
                        // Pedido criado mas sem URL de pagamento (pode ser que o pagamento foi processado diretamente)
                        checkoutCompleted = true;
                        cart = [];
                        updateCart();
                        closeCheckoutModal();
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Pedido Criado!',
                                text: 'Seu pedido foi criado com sucesso!',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Pedido criado com sucesso!');
                        }
                    }
                } else {
                    console.error('Erro ao criar pedido:', result);
                    alert('Erro ao criar pedido: ' + (result.message || 'Erro desconhecido'));
                    isSubmittingOrder = false;
                }
            } catch (error) {
                console.error('Erro ao finalizar pedido:', error);
                console.error('Stack trace:', error.stack);
                alert('Erro ao processar pedido. Por favor, tente novamente.');
                isSubmittingOrder = false;
            }
        }
        
        async function submitOrder() {
            // Prevent double submission
            if (isSubmittingOrder) {
                console.log('Pedido já está sendo processado, aguarde...');
                return;
            }
            
            // Set submitting state
            isSubmittingOrder = true;
            
            // Get submit button and disable it
            const submitButton = document.querySelector('button[onclick="submitOrder()"]');
            const originalButtonText = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                submitButton.style.opacity = '0.6';
                submitButton.style.cursor = 'not-allowed';
            }
            
            const customerName = document.getElementById('customerName').value.trim();
            const customerPhone = document.getElementById('customerPhone').value.trim();
            const customerEmail = document.getElementById('customerEmail').value.trim();
            const customerCpf = document.getElementById('customerCpf').value.trim();
            
            // Hide any previous errors
            const errorDiv = document.getElementById('checkoutError');
            const cpfRequiredMsg = document.getElementById('cpfRequiredMsg');
            if (errorDiv) errorDiv.style.display = 'none';
            if (cpfRequiredMsg) cpfRequiredMsg.style.display = 'none';
            
            if (!customerName || !customerPhone) {
                // Re-enable button on validation error
                isSubmittingOrder = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    submitButton.style.opacity = '1';
                    submitButton.style.cursor = 'pointer';
                }
                
                if (errorDiv) {
                    errorDiv.textContent = 'Por favor, preencha nome e telefone.';
                    errorDiv.style.display = 'block';
                }
                // Go back to step 1 if not already there
                const customerDataFields = document.getElementById('customerDataFields');
                if (!customerDataFields || customerDataFields.style.display === 'none') {
                    mostrarPasso(1);
                }
                return;
            }
            
            // CPF is now optional - no validation required
            // Just ensure field is not marked as invalid
            if (customerCpf && customerCpf.trim()) {
                if (cpfRequiredMsg) cpfRequiredMsg.style.display = 'none';
                document.getElementById('customerCpf').classList.remove('is-invalid');
            } else {
                document.getElementById('customerCpf').classList.remove('is-invalid');
            }
            
            // Get delivery type from cart (inside cartItems) or fallback to sidebar
            const deliveryTypeSelect = document.getElementById('deliveryTypeSelect');
            if (!deliveryTypeSelect) {
                // Re-enable button on validation error
                isSubmittingOrder = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    submitButton.style.opacity = '1';
                    submitButton.style.cursor = 'pointer';
                }
                alert('Por favor, selecione uma opção de entrega no carrinho.');
                return;
            }
            const deliveryType = deliveryTypeSelect.value;
            if (!deliveryType) {
                // Re-enable button on validation error
                isSubmittingOrder = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    submitButton.style.opacity = '1';
                    submitButton.style.cursor = 'pointer';
                }
                alert('Por favor, selecione uma opção de entrega.');
                return;
            }
            let enderecoEntrega = null;
            
            if (deliveryType === 'delivery') {
                const enderecoSelecionado = document.getElementById('enderecoSelecionado').value;
                
                if (enderecoSelecionado !== '' && clienteEnderecos[enderecoSelecionado]) {
                    // Use selected saved address
                    const endereco = clienteEnderecos[enderecoSelecionado];
                    const logradouro = endereco.logradouro || '';
                    const numero = endereco.numero || '';
                    enderecoEntrega = {
                        endereco: `${logradouro}${numero ? ', ' + numero : ''}`.trim(),
                        bairro: endereco.bairro || '',
                        cidade: endereco.cidade || '',
                        cep: endereco.cep || '',
                        estado: endereco.estado || ''
                    };
                    } else {
                        // Use manually entered address - save it to customer
                        const address = document.getElementById('deliveryAddress').value.trim();
                        const city = document.getElementById('deliveryCity').value.trim();
                        if (!address || !city) {
                            // Re-enable button on validation error
                            isSubmittingOrder = false;
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.innerHTML = originalButtonText;
                                submitButton.style.opacity = '1';
                                submitButton.style.cursor = 'pointer';
                            }
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
                        
                        // Address should already be saved by salvarClienteEEnderecoEAutomatico
                        // But ensure it's saved if not already
                        if (clienteData && clienteData.id) {
                            await salvarNovoEnderecoCliente(enderecoEntrega);
                        }
                        
                        // Save new address to customer if customer exists
                        if (clienteData && clienteData.id) {
                            await salvarNovoEnderecoCliente(enderecoEntrega);
                        }
                    }
            }
            
            const itensDetalhados = cart.map(item => ({
                id: item.id,
                quantity: item.quantity,
                preco: item.preco_normal,
                observacao: item.observacao || '',
                ingredientes_adicionados: item.ingredientes_adicionados || [],
                ingredientes_removidos: item.ingredientes_removidos || []
            }));
            
            console.log('Enviando itens com ingredientes:', itensDetalhados);
            
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
                forma_pagamento: paymentMethod,
                // Online payment method (PIX or CREDIT_CARD) - only if payment is online
                online_payment_method: (paymentMethod === 'online') ? onlinePaymentMethod : null,
                // Only send detailed payment info if payment is on_delivery AND delivery type is delivery (not pickup)
                forma_pagamento_detalhada: (paymentMethod === 'on_delivery' && deliveryType === 'delivery') ? formaPagamentoDetalhada : null,
                troco_para: (paymentMethod === 'on_delivery' && deliveryType === 'delivery' && formaPagamentoDetalhada === 'Dinheiro') ? trocoPara : null
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
                    // Marcar checkout como completado para evitar disparar evento de abandono
                    checkoutCompleted = true;
                    
                    // Calcular valor total do pedido para evento de conversão
                    const totalValue = cart.reduce((sum, item) => {
                        return sum + (parseFloat(item.preco_normal || 0) * (item.quantity || 1));
                    }, 0) + (parseFloat(deliveryFee) || 0);
                    
                    const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
                    
                    // Preparar itens para evento de conversão
                    const purchaseItems = cart.map(item => ({
                        item_id: item.id.toString(),
                        item_name: item.nome,
                        price: parseFloat(item.preco_normal || 0),
                        quantity: item.quantity || 1
                    }));
                    
                    // Disparar evento Purchase do pixel
                    trackPixelEvent('Purchase', {
                        transaction_id: result.pedido_id ? result.pedido_id.toString() : '',
                        value: totalValue,
                        num_items: totalItems,
                        contents: purchaseItems
                    });
                    
                    if (paymentMethod === 'online') {
                        console.log('Payment response:', result);
                        
                        if (result.payment_id) {
                            // Payment created successfully - show transparent checkout
                            // Save order ID to localStorage
                            if (result.pedido_id) {
                                localStorage.setItem('last_order_id', result.pedido_id);
                            }
                            
                            // Show payment step
                            mostrarPasso(7);
                            
                            // Show appropriate payment method
                            if (result.billing_type === 'PIX') {
                                console.log('Processing PIX payment, payment_id:', result.payment_id);
                                
                                // Show PIX payment
                                const pixContainer = document.getElementById('pixPaymentContainer');
                                const cardContainer = document.getElementById('creditCardPaymentContainer');
                                
                                if (pixContainer) pixContainer.style.display = 'block';
                                if (cardContainer) cardContainer.style.display = 'none';
                                
                                // Load PIX QR code from response if available
                                let hasQrCode = false;
                                if (result.pix_qr_code) {
                                    console.log('QR code from response:', result.pix_qr_code.substring(0, 50) + '...');
                                    const qrImg = document.getElementById('pixQrCodeImage');
                                    if (qrImg) {
                                        qrImg.src = result.pix_qr_code;
                                        qrImg.style.display = 'block';
                                        hasQrCode = true;
                                    }
                                }
                                
                                // Set copy-paste code from response if available
                                if (result.pix_copy_paste) {
                                    console.log('PIX copy-paste from response');
                                    const pixInput = document.getElementById('pixCopyPaste');
                                    if (pixInput) {
                                        pixInput.value = result.pix_copy_paste;
                                        hasQrCode = true;
                                    }
                                }
                                
            // Always try to fetch from API (in case it wasn't in response or to get latest)
            if (result.payment_id) {
                console.log('Fetching QR code from API...');
                // Wait longer for Asaas to generate QR code (can take 3-5 seconds)
                setTimeout(() => {
                    carregarQrCodePix(result.payment_id);
                }, 3000); // Increased from 1s to 3s
            }
                                
                                // Start polling for payment status
                                if (result.pedido_id) {
                                    setupPaymentStatusCheck(result.pedido_id);
                                }
                            } else if (result.billing_type === 'CREDIT_CARD') {
                                // Show credit card payment option
                                document.getElementById('pixPaymentContainer').style.display = 'none';
                                document.getElementById('creditCardPaymentContainer').style.display = 'block';
                                
                                // Store payment URL for credit card
                                window.asaasPaymentUrl = result.payment_url;
                            } else if (result.payment_url) {
                                // Fallback: if we have payment_url but no billing_type, show credit card option
                                document.getElementById('pixPaymentContainer').style.display = 'none';
                                document.getElementById('creditCardPaymentContainer').style.display = 'block';
                                window.asaasPaymentUrl = result.payment_url;
                            }
                        } else if (result.payment_error) {
                            // Payment failed but order was created - show error in modal
                            // Note: Button stays disabled because order was created, we're just showing payment error
                            const errorDiv = document.getElementById('checkoutError');
                            if (errorDiv) {
                                errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + result.payment_error;
                                errorDiv.style.display = 'block';
                                errorDiv.className = 'alert alert-warning';
                            }
                            // Go back to step 2 to show CPF field if needed
                            if (result.payment_error.includes('CPF') || result.payment_error.includes('cpf')) {
                                // Re-enable button to allow retry
                                isSubmittingOrder = false;
                                if (submitButton) {
                                    submitButton.disabled = false;
                                    submitButton.innerHTML = originalButtonText;
                                    submitButton.style.opacity = '1';
                                    submitButton.style.cursor = 'pointer';
                                }
                                proximoPasso(1);
                            }
                        } else {
                            // Payment method is online but no URL and no error
                            const errorDiv = document.getElementById('checkoutError');
                            if (errorDiv) {
                                errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Pedido criado, mas não foi possível gerar o link de pagamento. Entre em contato conosco.';
                                errorDiv.style.display = 'block';
                                errorDiv.className = 'alert alert-warning';
                            }
                        }
                    } else {
                        // Payment is not online (on_delivery) - success
                        // O evento Purchase já foi disparado acima antes do if
                        closeCheckoutModal();
                        toggleSidebar();
                        cart = [];
                        updateCart();
                        alert('Pedido criado com sucesso! Número do pedido: ' + result.pedido_id);
                        window.location.reload();
                    }
                } else {
                    // Re-enable button on error
                    isSubmittingOrder = false;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                        submitButton.style.opacity = '1';
                        submitButton.style.cursor = 'pointer';
                    }
                    
                    // Error creating order - show in modal
                    const errorDiv = document.getElementById('checkoutError');
                    if (errorDiv) {
                        let errorMsg = result.message || result.error || 'Erro ao criar pedido. Tente novamente.';
                        if (result.payment_error) {
                            errorMsg = result.payment_error;
                        }
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + errorMsg;
                        errorDiv.style.display = 'block';
                        errorDiv.className = 'alert alert-danger';
                    }
                    
                    // If payment error and can retry, allow customer to go back and choose another method
                    if ((result.payment_error || result.message) && result.can_retry) {
                        // If error mentions minimum value, go back to cart or payment selection
                        if (result.message && (result.message.includes('valor mínimo') || result.message.includes('mínimo'))) {
                            // Go back to payment selection to allow choosing "Pagar na Hora"
                            proximoPasso(3);
                        } else {
                            // Go back to payment selection step
                            if (paymentMethod === 'online') {
                                // Go back to step 4b (online payment method selection) or step 4 (payment type)
                                proximoPasso(3); // Will go to step 4, then 4b if online selected
                            } else {
                                proximoPasso(3);
                            }
                        }
                    }
                    
                    // If CPF error, go back to step 2
                    if (result.message && (result.message.includes('CPF') || result.message.includes('cpf'))) {
                        proximoPasso(1);
                    }
                    
                    // If minimum value error, also allow going back
                    if (result.message && (result.message.includes('valor mínimo') || result.message.includes('mínimo'))) {
                        proximoPasso(3); // Go back to payment selection
                    }
                }
            } catch (error) {
                // Re-enable button on exception
                isSubmittingOrder = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    submitButton.style.opacity = '1';
                    submitButton.style.cursor = 'pointer';
                }
                
                console.error('Erro:', error);
                const errorDiv = document.getElementById('checkoutError');
                if (errorDiv) {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro ao processar pedido. Tente novamente.';
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'alert alert-danger';
                }
            }
        }
        
        // Setup payment status check for inline checkout
        function setupPaymentStatusCheck(pedidoId) {
            if (!pedidoId) return;
            
            // Poll for payment status every 3 seconds
            const statusInterval = setInterval(async () => {
                try {
                    const response = await fetch(`mvc/ajax/pedidos_online.php?action=check_payment_status&pedido_id=${pedidoId}`);
                    const result = await response.json();
                    
                    if (result.success && result.status === 'paid') {
                        // Payment completed
                        clearInterval(statusInterval);
                        
                        // Clear cart
                        cart = [];
                        updateCart();
                        
                        // Close modal and show success
                        closeCheckoutModal();
                        toggleSidebar();
                        
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Pagamento Confirmado!',
                            text: 'Seu pedido foi confirmado e está sendo preparado.',
                            confirmButtonText: 'OK'
                        });
                    } else if (result.success && result.status === 'cancelled') {
                        // Payment cancelled
                        clearInterval(statusInterval);
                        Swal.fire({
                            icon: 'info',
                            title: 'Pagamento Cancelado',
                            text: 'O pagamento foi cancelado. Você pode tentar novamente.',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (error) {
                    console.error('Erro ao verificar status do pagamento:', error);
                }
            }, 3000);
            
            // Stop checking after 10 minutes
            setTimeout(() => {
                clearInterval(statusInterval);
            }, 600000);
        }
        
        // Voltar para resumo do pedido
        function voltarParaResumo() {
            mostrarPasso(6);
            updateOrderSummary();
        }
        
        // Carregar QR Code do PIX
        async function carregarQrCodePix(paymentId) {
            if (!paymentId) {
                console.error('Payment ID não fornecido para carregar QR code');
                return;
            }
            
            console.log('Carregando QR code do PIX para payment_id:', paymentId);
            
            const tenantId = <?php echo $tenantId; ?>;
            const filialId = <?php echo $filialId; ?>;
            
            // Try multiple times as Asaas may take a moment to generate QR code
            let attempts = 0;
            const maxAttempts = 5;
            
            const tryLoad = async () => {
                try {
                    const url = `mvc/ajax/pedidos_online.php?action=get_pix_qrcode&payment_id=${paymentId}&tenant_id=${tenantId}&filial_id=${filialId}`;
                    console.log('Fetching QR code from:', url);
                    
                    const response = await fetch(url);
                    const result = await response.json();
                    
                    console.log('QR code response:', result);
                    
                    if (result.success) {
                        // Check if QR code is available
                        if (result.pix_qr_code || result.pix_copy_paste) {
                            if (result.pix_qr_code) {
                                const qrImg = document.getElementById('pixQrCodeImage');
                                if (qrImg) {
                                    qrImg.src = result.pix_qr_code;
                                    qrImg.style.display = 'block';
                                    console.log('QR code image atualizado');
                                }
                            }
                            
                            if (result.pix_copy_paste) {
                                const pixInput = document.getElementById('pixCopyPaste');
                                if (pixInput) {
                                    pixInput.value = result.pix_copy_paste;
                                    console.log('PIX copy-paste atualizado');
                                }
                            }
                            
                            // If we got at least one, we're done
                            if (result.pix_qr_code || result.pix_copy_paste) {
                                console.log('QR code carregado com sucesso!');
                                return true;
                            }
                        } else {
                            // QR code not ready yet
                            if (result.not_ready_yet) {
                                console.log('QR code ainda não disponível no Asaas, tentando novamente...');
                            } else {
                                console.log('QR code parcialmente carregado:', {
                                    has_qr: !!result.pix_qr_code,
                                    has_copy_paste: !!result.pix_copy_paste
                                });
                            }
                        }
                    } else {
                        console.error('Erro ao buscar QR code:', result.message);
                    }
                    
                    // If not successful and we have attempts left, try again
                    if (attempts < maxAttempts) {
                        attempts++;
                        console.log(`Tentativa ${attempts} de ${maxAttempts} - QR code ainda não disponível, tentando novamente em 2 segundos...`);
                        setTimeout(tryLoad, 2000);
                    } else {
                        console.error('Não foi possível carregar QR code após', maxAttempts, 'tentativas');
                        // Show error message
                        const alertDiv = document.querySelector('#pixPaymentContainer .alert-info');
                        if (alertDiv) {
                            alertDiv.className = 'alert alert-warning';
                            alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> QR Code não disponível. Por favor, entre em contato conosco.';
                        }
                    }
                } catch (error) {
                    console.error('Erro ao carregar QR Code:', error);
                    if (attempts < maxAttempts) {
                        attempts++;
                        setTimeout(tryLoad, 2000);
                    } else {
                        const alertDiv = document.querySelector('#pixPaymentContainer .alert-info');
                        if (alertDiv) {
                            alertDiv.className = 'alert alert-danger';
                            alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao carregar QR Code. Por favor, entre em contato conosco.';
                        }
                    }
                }
            };
            
            // Start trying
            tryLoad();
        }
        
        // Copiar código PIX
        function copiarPix() {
            const pixInput = document.getElementById('pixCopyPaste');
            if (!pixInput || !pixInput.value) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Código PIX não disponível'
                });
                return;
            }
            
            pixInput.select();
            pixInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                Swal.fire({
                    icon: 'success',
                    title: 'Código PIX copiado!',
                    text: 'Cole no aplicativo do seu banco para pagar',
                    timer: 2000,
                    showConfirmButton: false
                });
            } catch (err) {
                // Fallback for modern browsers
                navigator.clipboard.writeText(pixInput.value).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Código PIX copiado!',
                        text: 'Cole no aplicativo do seu banco para pagar',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Não foi possível copiar o código PIX'
                    });
                });
            }
        }
        
        // Abrir pagamento de cartão
        function abrirPagamentoCartao() {
            if (window.asaasPaymentUrl) {
                // Open in new window
                const paymentWindow = window.open(window.asaasPaymentUrl, 'AsaasPayment', 'width=800,height=600');
                
                if (!paymentWindow) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Popup bloqueado',
                        text: 'Por favor, permita popups para este site e tente novamente'
                    });
                    return;
                }
                
                // Monitor window for payment completion
                const checkWindow = setInterval(() => {
                    if (paymentWindow.closed) {
                        clearInterval(checkWindow);
                        // Check payment status
                        const pedidoId = localStorage.getItem('last_order_id');
                        if (pedidoId) {
                            setupPaymentStatusCheck(pedidoId);
                        }
                    }
                }, 1000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'URL de pagamento não disponível'
                });
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
        
        // Add click event to product cards to add directly to cart
        // Only for cards that don't have their own event listener (original products container)
        document.addEventListener('click', function(e) {
            const productCard = e.target.closest('.product-card');
            // Only trigger if clicking on the card itself, not on buttons or their children
            // Skip if the event was already handled (stopPropagation was called)
            if (productCard && !e.target.closest('button') && !e.defaultPrevented) {
                // Check if this card is in the original products container (not category products)
                const isInCategoryProducts = productCard.closest('#categoryProductsContainer');
                if (isInCategoryProducts) {
                    // Skip - this card has its own listener
                    return;
                }
                
                // Get product data from data attributes
                const productId = productCard.getAttribute('data-product-id');
                const productDataStr = productCard.getAttribute('data-product-data');
                
                if (productId && productDataStr) {
                    try {
                        const produtoData = JSON.parse(productDataStr);
                        
                        // Disparar evento ViewContent do pixel antes de adicionar ao carrinho
                        const productPrice = parseFloat(produtoData.preco_normal) || 0;
                        const productCategory = produtoData.categoria_nome || 'Geral';
                        
                        trackPixelEvent('ViewContent', {
                            content_name: produtoData.nome,
                            content_category: productCategory,
                            content_ids: [produtoData.id.toString()],
                            value: productPrice
                        });
                        
                        // Add directly to cart when clicking on the card
                        addToCart(produtoData);
                    } catch (error) {
                        console.error('Error parsing product data:', error);
                    }
                }
            }
        });
        
        // ============================================
        // CAROUSEL FUNCTIONS
        // ============================================
        function scrollCarousel(carouselId, direction) {
            const carousel = document.getElementById('carousel' + (carouselId === 'maisVendidos' ? 'MaisVendidos' : 'Promocoes'));
            if (!carousel) return;
            
            const track = carousel.querySelector('.carousel-track');
            if (!track) return;
            
            const scrollAmount = 220; // Width of item + gap
            const currentScroll = track.scrollLeft;
            const newScroll = currentScroll + (direction * scrollAmount);
            
            track.scrollTo({
                left: newScroll,
                behavior: 'smooth'
            });
        }
        
        // ============================================
        // CATEGORY NAVIGATION FUNCTIONS
        // ============================================
        const produtosPorCategoriaData = <?php echo json_encode($produtosPorCategoria, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        
        function openCategory(categoriaNome) {
            console.log('openCategory called with:', categoriaNome);
            
            // Hide categories grid
            const categoriesGrid = document.getElementById('categoriesGrid');
            const categoryProductsSection = document.getElementById('categoryProductsSection');
            const categoryProductsTitle = document.getElementById('categoryProductsTitle');
            const categoryProductsContainer = document.getElementById('categoryProductsContainer');
            
            if (!categoriesGrid || !categoryProductsSection || !categoryProductsTitle || !categoryProductsContainer) {
                console.error('Required elements not found');
                return;
            }
            
            // Hide carousels and categories
            document.querySelectorAll('.carousel-section').forEach(section => {
                section.style.display = 'none';
            });
            categoriesGrid.style.display = 'none';
            
            // Show category products section
            categoryProductsSection.style.display = 'block';
            categoryProductsTitle.textContent = categoriaNome;
            
            // Clear container
            categoryProductsContainer.innerHTML = '';
            
            // Get products for this category
            console.log('produtosPorCategoriaData:', produtosPorCategoriaData);
            const produtos = produtosPorCategoriaData[categoriaNome] || [];
            console.log('Products for category:', produtos);
            
            // Render products
            if (produtos.length === 0) {
                categoryProductsContainer.innerHTML = '<p class="text-center text-muted mt-4">Nenhum produto encontrado nesta categoria.</p>';
                return;
            }
            
            produtos.forEach(produto => {
                try {
                    const produtoCard = document.createElement('div');
                    produtoCard.className = 'product-card';
                    produtoCard.setAttribute('data-product-id', produto.id);
                    produtoCard.setAttribute('data-product-data', JSON.stringify(produto));
                    produtoCard.style.cursor = 'pointer';
                    
                    const produtoNome = (produto.nome || '').replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                    const produtoImagem = (produto.imagem || '').replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                    const produtoPreco = parseFloat(produto.preco_normal || 0).toFixed(2).replace('.', ',');
                    const produtoDataEscaped = JSON.stringify(produto).replace(/\\/g, '\\\\').replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                    
                    const imagemHtml = produtoImagem 
                        ? `<img src="${produtoImagem}" alt="${produtoNome}" class="product-image" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                           <div class="product-image" style="display: none; align-items: center; justify-content: center; background: #f5f5f5;">
                               <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                           </div>`
                        : `<div class="product-image" style="display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                               <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                           </div>`;
                    
                    produtoCard.innerHTML = `
                        ${imagemHtml}
                        <div class="product-info">
                            <div class="product-name">${produtoNome}</div>
                            <div class="product-price">R$ ${produtoPreco}</div>
                            <button class="btn btn-primary btn-sm w-100 mt-2" onclick="event.stopPropagation(); personalizarProduto(${produto.id}, ${produtoDataEscaped})">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        </div>
                    `;
                    
                    // Add click event to add to cart (stopPropagation to prevent duplicate)
                    produtoCard.addEventListener('click', function(e) {
                        e.stopPropagation(); // Prevent the global listener from firing
                        if (!e.target.closest('button')) {
                            addToCart(produto);
                        }
                    });
                    
                    categoryProductsContainer.appendChild(produtoCard);
                } catch (error) {
                    console.error('Error rendering product:', error, produto);
                }
            });
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function closeCategory() {
            const categoriesGrid = document.getElementById('categoriesGrid');
            const categoryProductsSection = document.getElementById('categoryProductsSection');
            
            if (!categoriesGrid || !categoryProductsSection) return;
            
            // Show carousels and categories
            document.querySelectorAll('.carousel-section').forEach(section => {
                section.style.display = 'block';
            });
            categoriesGrid.style.display = 'grid';
            
            // Hide category products section
            categoryProductsSection.style.display = 'none';
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Add click events to carousel product cards and category cards
        document.addEventListener('DOMContentLoaded', function() {
            // Mais vendidos carousel
            const maisVendidosCards = document.querySelectorAll('#carouselMaisVendidos .product-card-carousel');
            maisVendidosCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent duplicate events
                    const productDataStr = card.getAttribute('data-product-data');
                    if (productDataStr) {
                        try {
                            const produtoData = JSON.parse(productDataStr);
                            addToCart(produtoData);
                        } catch (error) {
                            console.error('Error parsing product data:', error);
                        }
                    }
                });
            });
            
            // Promoções carousel
            const promocoesCards = document.querySelectorAll('#carouselPromocoes .product-card-carousel');
            promocoesCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent duplicate events
                    const productDataStr = card.getAttribute('data-product-data');
                    if (productDataStr) {
                        try {
                            const produtoData = JSON.parse(productDataStr);
                            addToCart(produtoData);
                        } catch (error) {
                            console.error('Error parsing product data:', error);
                        }
                    }
                });
            });
            
            // Category cards - add event listener as backup
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const categoriaNome = card.getAttribute('data-category-name');
                    if (categoriaNome) {
                        console.log('Category card clicked:', categoriaNome);
                        openCategory(categoriaNome);
                    }
                });
            });
        });
    </script>
</body>
</html>
