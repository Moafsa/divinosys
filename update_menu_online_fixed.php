<?php
/**
 * Script para limpar e recriar dados do cardápio online - VERSÃO CORRIGIDA
 * Baseado nos cardápios fornecidos
 * 
 * Acesso: http://seu-dominio.com/update_menu_online_fixed.php
 */

// Configuração de segurança - remova após execução
$SECURITY_KEY = 'divino_lanches_2025_update_menu';
$ALLOWED_IP = ''; // Deixe vazio para permitir qualquer IP, ou coloque seu IP

// Verificação de segurança
if (isset($_GET['key']) && $_GET['key'] === $SECURITY_KEY) {
    // IP permitido (se configurado)
    if (!empty($ALLOWED_IP) && $_SERVER['REMOTE_ADDR'] !== $ALLOWED_IP) {
        die('❌ Acesso negado - IP não autorizado');
    }
} else {
    die('❌ Acesso negado - Chave de segurança inválida<br>Use: ?key=' . $SECURITY_KEY);
}

// Headers para exibição em tempo real
header('Content-Type: text/html; charset=utf-8');
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Atualizacao do Cardapio - Divino Lanches</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .progress { background: #e9ecef; border-radius: 4px; height: 20px; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 4px; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>
<h1>🍽️ Atualização do Cardápio - Divino Lanches</h1>
<div class='progress'><div class='progress-bar' id='progress' style='width: 0%'></div></div>
<div id='output'></div>
</div>
<script>
function updateProgress(percent) {
    document.getElementById('progress').style.width = percent + '%';
}
function addOutput(message, type = 'info') {
    const output = document.getElementById('output');
    const div = document.createElement('div');
    div.className = type;
    div.innerHTML = message;
    output.appendChild(div);
    output.scrollTop = output.scrollHeight;
}
</script>";

// Função para exibir mensagens
function showMessage($message, $type = 'info') {
    $color = $type === 'success' ? '#28a745' : ($type === 'error' ? '#dc3545' : '#17a2b8');
    echo "<script>addOutput('$message', '$type');</script>";
    flush();
}

// Função para atualizar progresso
function updateProgress($percent) {
    echo "<script>updateProgress($percent);</script>";
    flush();
}

try {
    showMessage("🔌 Conectando ao banco de dados...", 'info');
    
    // Configuração do banco de dados - usando variáveis de ambiente
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $dbname = $_ENV['DB_NAME'] ?? 'divino_db';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'postgres';
    
    // Debug das credenciais
    showMessage("🔍 Debug - Host: $host, Port: $port, DB: $dbname, User: $user", 'info');
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    showMessage("✅ Conectado ao banco de dados com sucesso!", 'success');
    updateProgress(10);
    
    // Iniciar transação
    $pdo->beginTransaction();
    showMessage("🔄 Iniciando transação...", 'info');
    
    // Limpar dados existentes
    showMessage("🧹 Limpando dados existentes...", 'info');
    
    // Limpar dados das tabelas (manter estrutura, apenas remover dados)
    $cleanQueries = [
        "DELETE FROM pedido_item_ingredientes",
        "DELETE FROM pedido_itens", 
        "DELETE FROM pedido",
        "DELETE FROM mesa_pedidos",
        "DELETE FROM log_pedidos",
        "DELETE FROM pagamentos_pedido",
        "DELETE FROM historico_pedidos_financeiros",
        "DELETE FROM produto_ingredientes",
        "DELETE FROM produtos",
        "DELETE FROM ingredientes",
        "DELETE FROM categorias"
    ];
    
    foreach ($cleanQueries as $query) {
        $pdo->exec($query);
        $tableName = explode(' ', $query)[2];
        showMessage("✅ Limpo: $tableName", 'success');
    }
    
    updateProgress(20);
    
    // Criar categorias
    showMessage("📂 Criando categorias...", 'info');
    
    $categories = [
        ['nome' => 'XIS', 'descricao' => 'Sanduíches XIS'],
        ['nome' => 'Cachorro-Quente', 'descricao' => 'Cachorros-quentes'],
        ['nome' => 'Bauru', 'descricao' => 'Pratos de Bauru'],
        ['nome' => 'PF e À La Minuta', 'descricao' => 'Pratos feitos e à la minuta'],
        ['nome' => 'Torrada', 'descricao' => 'Torradas'],
        ['nome' => 'Rodízio', 'descricao' => 'Rodízio de carnes'],
        ['nome' => 'Porções', 'descricao' => 'Porções e petiscos'],
        ['nome' => 'Bebidas', 'descricao' => 'Bebidas diversas']
    ];
    
    $categoryIds = [];
    foreach ($categories as $category) {
        $stmt = $pdo->prepare("INSERT INTO categorias (nome, descricao, tenant_id, filial_id) VALUES (?, ?, 1, 1)");
        $stmt->execute([$category['nome'], $category['descricao']]);
        $categoryId = $pdo->lastInsertId();
        $categoryIds[$category['nome']] = $categoryId;
        showMessage("✅ Categoria criada: " . $category['nome'], 'success');
    }
    
    updateProgress(30);
    
    // Criar ingredientes
    showMessage("🥘 Criando ingredientes...", 'info');
    
    $ingredients = [
        // Proteins
        ['nome' => 'Hambúrguer', 'tipo' => 'proteina'],
        ['nome' => 'Coração de frango', 'tipo' => 'proteina'],
        ['nome' => 'Calabresa', 'tipo' => 'proteina'],
        ['nome' => 'Bacon', 'tipo' => 'proteina'],
        ['nome' => 'Filé', 'tipo' => 'proteina'],
        ['nome' => 'Frango', 'tipo' => 'proteina'],
        ['nome' => 'Alcatra', 'tipo' => 'proteina'],
        ['nome' => 'Patinho', 'tipo' => 'proteina'],
        ['nome' => 'Coxão mole', 'tipo' => 'proteina'],
        ['nome' => 'Salsicha', 'tipo' => 'proteina'],
        ['nome' => 'Salsicha vegetariana', 'tipo' => 'proteina'],
        
        // Breads
        ['nome' => 'Pão', 'tipo' => 'pao'],
        ['nome' => 'Pão de xis', 'tipo' => 'pao'],
        ['nome' => 'Pão torrado', 'tipo' => 'pao'],
        
        // Cheeses
        ['nome' => 'Queijo', 'tipo' => 'queijo'],
        ['nome' => 'Queijo ralado', 'tipo' => 'queijo'],
        ['nome' => 'Cheddar', 'tipo' => 'queijo'],
        
        // Salads and vegetables
        ['nome' => 'Alface', 'tipo' => 'salada'],
        ['nome' => 'Tomate', 'tipo' => 'salada'],
        ['nome' => 'Rúcula', 'tipo' => 'salada'],
        ['nome' => 'Tomate seco', 'tipo' => 'salada'],
        ['nome' => 'Cebola', 'tipo' => 'salada'],
        ['nome' => 'Salada mista', 'tipo' => 'salada'],
        ['nome' => 'Palmito', 'tipo' => 'salada'],
        ['nome' => 'Pepino', 'tipo' => 'salada'],
        
        // Sauces and condiments
        ['nome' => 'Maionese', 'tipo' => 'molho'],
        ['nome' => 'Molho', 'tipo' => 'molho'],
        
        // Sides and complements
        ['nome' => 'Ovo', 'tipo' => 'complemento'],
        ['nome' => 'Ovo de codorna', 'tipo' => 'complemento'],
        ['nome' => 'Presunto', 'tipo' => 'complemento'],
        ['nome' => 'Milho', 'tipo' => 'complemento'],
        ['nome' => 'Ervilha', 'tipo' => 'complemento'],
        ['nome' => 'Batata frita', 'tipo' => 'complemento'],
        ['nome' => 'Batata palha', 'tipo' => 'complemento'],
        ['nome' => 'Arroz', 'tipo' => 'complemento'],
        ['nome' => 'Feijão', 'tipo' => 'complemento'],
        ['nome' => 'Polenta', 'tipo' => 'complemento'],
        ['nome' => 'Massa', 'tipo' => 'complemento'],
        ['nome' => 'Azeitona', 'tipo' => 'complemento'],
        
        // Drinks
        ['nome' => 'Água mineral', 'tipo' => 'complemento'],
        ['nome' => 'H2O', 'tipo' => 'complemento'],
        ['nome' => 'Refrigerante', 'tipo' => 'complemento'],
        ['nome' => 'Coca-Cola', 'tipo' => 'complemento'],
        ['nome' => 'Suco natural', 'tipo' => 'complemento']
    ];
    
    $ingredientIds = [];
    foreach ($ingredients as $ingredient) {
        $stmt = $pdo->prepare("INSERT INTO ingredientes (nome, tipo, tenant_id, filial_id) VALUES (?, ?, 1, 1)");
        $stmt->execute([$ingredient['nome'], $ingredient['tipo']]);
        $ingredientId = $pdo->lastInsertId();
        $ingredientIds[$ingredient['nome']] = $ingredientId;
        showMessage("✅ Ingrediente criado: " . $ingredient['nome'], 'success');
    }
    
    updateProgress(50);
    
    // Criar produtos
    showMessage("🍔 Criando produtos...", 'info');
    
    $products = [
        // XIS Category
        [
            'nome' => 'XIS DA CASA',
            'categoria' => 'XIS',
            'descricao' => 'Pão, hambúrguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 30.00,
            'preco_mini' => 27.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS CORAÇÃO',
            'categoria' => 'XIS',
            'descricao' => 'Pão, coração de frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 35.00,
            'preco_mini' => 30.00,
            'ingredientes' => ['Pão', 'Coração de frango', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS DUPLO',
            'categoria' => 'XIS',
            'descricao' => 'Pão, 2 hambúrgueres, 2 ovos, 2 presuntos, 2 queijos, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 37.00,
            'preco_mini' => 32.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS CALABRESA',
            'categoria' => 'XIS',
            'descricao' => 'Pão, hambúrguer, calabresa, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 35.00,
            'preco_mini' => 30.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Calabresa', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS BACON',
            'categoria' => 'XIS',
            'descricao' => 'Pão, hambúrguer, bacon, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 36.00,
            'preco_mini' => 31.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Bacon', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS VEGETARIANO',
            'categoria' => 'XIS',
            'descricao' => 'Pão, alface, tomate, queijo, palmito, pepino, milho, ervilha, maionese',
            'preco_normal' => 30.00,
            'preco_mini' => 26.00,
            'ingredientes' => ['Pão', 'Alface', 'Tomate', 'Queijo', 'Palmito', 'Pepino', 'Milho', 'Ervilha', 'Maionese']
        ],
        [
            'nome' => 'XIS FILÉ',
            'categoria' => 'XIS',
            'descricao' => 'Pão, filé, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 44.00,
            'preco_mini' => 37.00,
            'ingredientes' => ['Pão', 'Filé', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS CEBOLA',
            'categoria' => 'XIS',
            'descricao' => 'Pão, hambúrguer, cebola, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 34.00,
            'preco_mini' => 30.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Cebola', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS FRANGO',
            'categoria' => 'XIS',
            'descricao' => 'Pão, frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',
            'preco_normal' => 35.00,
            'preco_mini' => 30.00,
            'ingredientes' => ['Pão', 'Frango', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese']
        ],
        [
            'nome' => 'XIS TOMATE SECO COM RÚCULA',
            'categoria' => 'XIS',
            'descricao' => 'Pão, filé, rúcula, tomate seco, ovo, presunto, queijo, milho, ervilha, maionese',
            'preco_normal' => 45.00,
            'preco_mini' => 39.00,
            'ingredientes' => ['Pão', 'Filé', 'Rúcula', 'Tomate seco', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Maionese']
        ],
        [
            'nome' => 'XIS ENTREVERO',
            'categoria' => 'XIS',
            'descricao' => 'Pão, calabresa, coração, carne, frango, bacon, cebola, ovo, queijo, presunto, alface, tomate, milho, ervilha, maionese',
            'preco_normal' => 42.00,
            'preco_mini' => 37.00,
            'ingredientes' => ['Pão', 'Calabresa', 'Coração de frango', 'Hambúrguer', 'Frango', 'Bacon', 'Cebola', 'Ovo', 'Queijo', 'Presunto', 'Alface', 'Tomate', 'Milho', 'Ervilha', 'Maionese']
        ],

        // Cachorro-Quente Category
        [
            'nome' => 'CACHORRO-QUENTE SIMPLES',
            'categoria' => 'Cachorro-Quente',
            'descricao' => 'Pão, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha',
            'preco_normal' => 23.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Salsicha', 'Molho', 'Milho', 'Ervilha', 'Queijo ralado', 'Maionese', 'Batata palha']
        ],
        [
            'nome' => 'CACHORRO-QUENTE DUPLO',
            'categoria' => 'Cachorro-Quente',
            'descricao' => 'Pão, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha',
            'preco_normal' => 25.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Salsicha', 'Molho', 'Milho', 'Ervilha', 'Queijo ralado', 'Maionese', 'Batata palha']
        ],

        // Bauru Category
        [
            'nome' => '1/4 BAURU FILÉ (1 PESSOA)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 65.00,
            'preco_mini' => null,
            'ingredientes' => ['Filé', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],
        [
            'nome' => '1/2 BAURU FILÉ (2 PESSOAS)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 115.00,
            'preco_mini' => null,
            'ingredientes' => ['Filé', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],
        [
            'nome' => 'BAURU FILÉ (4 PESSOAS)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de filé com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 190.00,
            'preco_mini' => null,
            'ingredientes' => ['Filé', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],
        [
            'nome' => '1/4 BAURU ALCATRA (1 PESSOA)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 60.00,
            'preco_mini' => null,
            'ingredientes' => ['Alcatra', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],
        [
            'nome' => '1/2 BAURU ALCATRA (2 PESSOAS)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 100.00,
            'preco_mini' => null,
            'ingredientes' => ['Alcatra', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],
        [
            'nome' => 'BAURU ALCATRA (4 PESSOAS)',
            'categoria' => 'Bauru',
            'descricao' => 'Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',
            'preco_normal' => 175.00,
            'preco_mini' => null,
            'ingredientes' => ['Alcatra', 'Molho', 'Presunto', 'Queijo', 'Salada mista', 'Batata frita', 'Arroz']
        ],

        // PF e À La Minuta Category
        [
            'nome' => 'PRATO FEITO DA CASA',
            'categoria' => 'PF e À La Minuta',
            'descricao' => 'Patinho, arroz, feijão, batata frita, ovo, salada mista e pão',
            'preco_normal' => 32.00,
            'preco_mini' => null,
            'ingredientes' => ['Patinho', 'Arroz', 'Feijão', 'Batata frita', 'Ovo', 'Salada mista', 'Pão']
        ],
        [
            'nome' => 'PRATO FEITO FILÉ',
            'categoria' => 'PF e À La Minuta',
            'descricao' => 'Filé, arroz, feijão, batata frita, ovo, salada mista e pão',
            'preco_normal' => 48.00,
            'preco_mini' => null,
            'ingredientes' => ['Filé', 'Arroz', 'Feijão', 'Batata frita', 'Ovo', 'Salada mista', 'Pão']
        ],
        [
            'nome' => 'PRATO FEITO COXÃO MOLE',
            'categoria' => 'PF e À La Minuta',
            'descricao' => 'Coxão mole, arroz, feijão, batata frita, ovo, salada mista e pão',
            'preco_normal' => 40.00,
            'preco_mini' => null,
            'ingredientes' => ['Coxão mole', 'Arroz', 'Feijão', 'Batata frita', 'Ovo', 'Salada mista', 'Pão']
        ],
        [
            'nome' => 'À LA MINUTA ALCATRA',
            'categoria' => 'PF e À La Minuta',
            'descricao' => 'Bife de alcatra, arroz, feijão, batata frita, ovo, salada mista e pão',
            'preco_normal' => 48.00,
            'preco_mini' => null,
            'ingredientes' => ['Alcatra', 'Arroz', 'Feijão', 'Batata frita', 'Ovo', 'Salada mista', 'Pão']
        ],
        [
            'nome' => 'À LA MINUTA FILÉ',
            'categoria' => 'PF e À La Minuta',
            'descricao' => 'Bife de filé, arroz, salada e batata palha ou batata frita',
            'preco_normal' => 52.00,
            'preco_mini' => null,
            'ingredientes' => ['Filé', 'Arroz', 'Salada mista', 'Batata palha', 'Batata frita']
        ],

        // Torrada Category
        [
            'nome' => 'TORRADA AMERICANA',
            'categoria' => 'Torrada',
            'descricao' => 'Pão de xis, tomate, alface, maionese, 2 fatias de presunto, 2 fatias de queijo e ovo',
            'preco_normal' => 26.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão de xis', 'Tomate', 'Alface', 'Maionese', 'Presunto', 'Queijo', 'Ovo']
        ],
        [
            'nome' => 'TORRADA COM BACON',
            'categoria' => 'Torrada',
            'descricao' => '3 pães, 2 fatias de presunto, 4 fatias de queijo, alface, tomate e maionese',
            'preco_normal' => 30.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Presunto', 'Queijo', 'Alface', 'Tomate', 'Maionese', 'Bacon']
        ],

        // Rodízio Category
        [
            'nome' => 'RODÍZIO DE BIFES',
            'categoria' => 'Rodízio',
            'descricao' => 'Bife de gado, frango e porco, bauru, arroz, batata frita, massa, salada e pão',
            'preco_normal' => 69.00,
            'preco_mini' => null,
            'ingredientes' => ['Hambúrguer', 'Frango', 'Bacon', 'Arroz', 'Batata frita', 'Massa', 'Salada mista', 'Pão']
        ],

        // Porções Category
        [
            'nome' => 'TÁBUA DE FRIOS PEQUENA',
            'categoria' => 'Porções',
            'descricao' => 'Azeitona, queijo, palmito, pepino, pão torrado, ovo de codorna e filé',
            'preco_normal' => 62.00,
            'preco_mini' => null,
            'ingredientes' => ['Azeitona', 'Queijo', 'Palmito', 'Pepino', 'Pão torrado', 'Ovo de codorna', 'Filé']
        ],
        [
            'nome' => 'TÁBUA DE FRIOS MÉDIA',
            'categoria' => 'Porções',
            'descricao' => 'Azeitona, queijo, palmito, pepino, pão torrado, ovo de codorna e filé',
            'preco_normal' => 100.00,
            'preco_mini' => null,
            'ingredientes' => ['Azeitona', 'Queijo', 'Palmito', 'Pepino', 'Pão torrado', 'Ovo de codorna', 'Filé']
        ],
        [
            'nome' => 'TÁBUA DE FRIOS GRANDE',
            'categoria' => 'Porções',
            'descricao' => 'Carnes (frango e gado), batata, polenta, queijo, ovo de codorna e cebola',
            'preco_normal' => 115.00,
            'preco_mini' => null,
            'ingredientes' => ['Frango', 'Hambúrguer', 'Batata frita', 'Polenta', 'Queijo', 'Ovo de codorna', 'Cebola']
        ],
        [
            'nome' => 'BATATA FRITA PEQUENA (200G)',
            'categoria' => 'Porções',
            'descricao' => '200 grams of French fries',
            'preco_normal' => 20.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata frita']
        ],
        [
            'nome' => 'BATATA FRITA PEQUENA COM CHEDDAR E BACON',
            'categoria' => 'Porções',
            'descricao' => 'Small French fries with cheddar cheese and bacon',
            'preco_normal' => 35.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata frita', 'Cheddar', 'Bacon']
        ],
        [
            'nome' => 'BATATA FRITA GRANDE (400G)',
            'categoria' => 'Porções',
            'descricao' => '400 grams of French fries',
            'preco_normal' => 35.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata frita']
        ],
        [
            'nome' => 'BATATA FRITA GRANDE COM CHEDDAR E BACON',
            'categoria' => 'Porções',
            'descricao' => 'Large French fries with cheddar cheese and bacon',
            'preco_normal' => 45.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata frita', 'Cheddar', 'Bacon']
        ],
        [
            'nome' => 'POLENTA FRITA (500G)',
            'categoria' => 'Porções',
            'descricao' => '500 grams of fried polenta',
            'preco_normal' => 25.00,
            'preco_mini' => null,
            'ingredientes' => ['Polenta']
        ],
        [
            'nome' => 'QUEIJO FRITO UN',
            'categoria' => 'Porções',
            'descricao' => 'One unit of fried cheese',
            'preco_normal' => 4.00,
            'preco_mini' => null,
            'ingredientes' => ['Queijo']
        ],
        [
            'nome' => 'BATATA, POLENTA E QUEIJO',
            'categoria' => 'Porções',
            'descricao' => 'A mix of potato, polenta, and cheese',
            'preco_normal' => 45.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata frita', 'Polenta', 'Queijo']
        ],

        // Bebidas Category
        [
            'nome' => 'ÁGUA MINERAL',
            'categoria' => 'Bebidas',
            'descricao' => 'Mineral water',
            'preco_normal' => 5.00,
            'preco_mini' => null,
            'ingredientes' => ['Água mineral']
        ],
        [
            'nome' => 'H2O 500ML',
            'categoria' => 'Bebidas',
            'descricao' => 'H2O, 500ml',
            'preco_normal' => 9.00,
            'preco_mini' => null,
            'ingredientes' => ['H2O']
        ],
        [
            'nome' => 'H2O 1,5L',
            'categoria' => 'Bebidas',
            'descricao' => 'H2O, 1.5 liters',
            'preco_normal' => 12.00,
            'preco_mini' => null,
            'ingredientes' => ['H2O']
        ],
        [
            'nome' => 'REFRIGERANTE (LATA)',
            'categoria' => 'Bebidas',
            'descricao' => 'Soda in a can',
            'preco_normal' => 8.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante']
        ],
        [
            'nome' => 'REFRIGERANTE 600ML',
            'categoria' => 'Bebidas',
            'descricao' => 'Soda, 600ml',
            'preco_normal' => 8.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante']
        ],
        [
            'nome' => 'REFRIGERANTE 1L',
            'categoria' => 'Bebidas',
            'descricao' => 'Soda, 1 liter',
            'preco_normal' => 10.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante']
        ],
        [
            'nome' => 'REFRIGERANTE 2L',
            'categoria' => 'Bebidas',
            'descricao' => 'Soda, 2 liters',
            'preco_normal' => 18.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante']
        ],
        [
            'nome' => 'COCA-COLA 2L',
            'categoria' => 'Bebidas',
            'descricao' => 'Coca-Cola, 2 liters',
            'preco_normal' => 18.00,
            'preco_mini' => null,
            'ingredientes' => ['Coca-Cola']
        ],
        [
            'nome' => 'SUCO NATURAL',
            'categoria' => 'Bebidas',
            'descricao' => 'Natural juice',
            'preco_normal' => 10.00,
            'preco_mini' => null,
            'ingredientes' => ['Suco natural']
        ]
    ];
    
    $productIds = [];
    $productCount = 0;
    $totalProducts = count($products);
    
    foreach ($products as $product) {
        $stmt = $pdo->prepare("INSERT INTO produtos (nome, categoria_id, descricao, preco_normal, preco_mini, tenant_id, filial_id) VALUES (?, ?, ?, ?, ?, 1, 1)");
        $stmt->execute([
            $product['nome'],
            $categoryIds[$product['categoria']],
            $product['descricao'],
            $product['preco_normal'],
            $product['preco_mini']
        ]);
        $productId = $pdo->lastInsertId();
        $productIds[$product['nome']] = $productId;
        $productCount++;
        
        showMessage("✅ Produto criado: " . $product['nome'] . " (R$ " . number_format($product['preco_normal'], 2, ',', '.') . ")", 'success');
        
        // Link ingredients to product
        foreach ($product['ingredientes'] as $ingredientName) {
            if (isset($ingredientIds[$ingredientName])) {
                $stmt = $pdo->prepare("INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio) VALUES (?, ?, true)");
                $stmt->execute([$productId, $ingredientIds[$ingredientName]]);
            }
        }
        
        // Update progress
        $progress = 50 + (($productCount / $totalProducts) * 40);
        updateProgress($progress);
    }
    
    updateProgress(90);
    
    // Commit transaction
    $pdo->commit();
    showMessage("✅ Transação confirmada com sucesso!", 'success');
    updateProgress(100);
    
    // Final summary
    showMessage("🎉 <strong>Atualização do cardápio concluída com sucesso!</strong>", 'success');
    showMessage("📊 <strong>Resumo:</strong>", 'info');
    showMessage("• Categorias criadas: " . count($categories), 'success');
    showMessage("• Ingredientes criados: " . count($ingredients), 'success');
    showMessage("• Produtos criados: " . count($products), 'success');
    showMessage("• Associações produto-ingrediente: " . array_sum(array_map(function($p) { return count($p['ingredientes']); }, $products)), 'success');
    
    showMessage("<br>⚠️ <strong>IMPORTANTE:</strong> Remova este arquivo após a execução por segurança!", 'warning');
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    showMessage("❌ <strong>Erro:</strong> " . $e->getMessage(), 'error');
    showMessage("🔄 Transação revertida.", 'warning');
}

echo "</body></html>";
?>
