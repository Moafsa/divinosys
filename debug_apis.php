<?php
echo "<h1>Debug das APIs</h1>";

// Testar configuração do banco
echo "<h2>1. Testando Configuração do Banco</h2>";
try {
    require_once 'config/database.php';
    echo "Configuração carregada:<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_PORT: " . DB_PORT . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
    echo "DB_PASS: " . (DB_PASS ? '***' : 'vazio') . "<br>";
} catch (Exception $e) {
    echo "Erro ao carregar configuração: " . $e->getMessage() . "<br>";
}

// Testar conexão
echo "<h2>2. Testando Conexão</h2>";
try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexão com banco OK<br>";
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "<br>";
    exit;
}

// Verificar estrutura da tabela produtos
echo "<h2>3. Estrutura da Tabela Produtos</h2>";
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'produtos' ORDER BY ordinal_position");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Coluna</th><th>Tipo</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>" . $col['column_name'] . "</td><td>" . $col['data_type'] . "</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erro ao verificar estrutura: " . $e->getMessage() . "<br>";
}

// Verificar produtos
echo "<h2>4. Verificando Produtos</h2>";
try {
    // Contar total
    $stmt = $pdo->query("SELECT COUNT(*) FROM produtos");
    $total = $stmt->fetchColumn();
    echo "Total de produtos: " . $total . "<br>";
    
    // Contar por tenant_id
    $stmt = $pdo->query("SELECT tenant_id, COUNT(*) as count FROM produtos GROUP BY tenant_id");
    $tenantCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Produtos por tenant_id:<br>";
    foreach ($tenantCounts as $row) {
        echo "- tenant_id " . $row['tenant_id'] . ": " . $row['count'] . " produtos<br>";
    }
    
    // Listar alguns produtos
    $stmt = $pdo->query("SELECT id, nome, preco, categoria, tenant_id, filial_id, ativo FROM produtos LIMIT 10");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Primeiros 10 produtos:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Preço</th><th>Categoria</th><th>Tenant</th><th>Filial</th><th>Ativo</th></tr>";
    foreach ($produtos as $produto) {
        echo "<tr>";
        echo "<td>" . $produto['id'] . "</td>";
        echo "<td>" . $produto['nome'] . "</td>";
        echo "<td>R$ " . number_format($produto['preco'], 2, ',', '.') . "</td>";
        echo "<td>" . $produto['categoria'] . "</td>";
        echo "<td>" . $produto['tenant_id'] . "</td>";
        echo "<td>" . $produto['filial_id'] . "</td>";
        echo "<td>" . ($produto['ativo'] ? 'Sim' : 'Não') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Erro ao verificar produtos: " . $e->getMessage() . "<br>";
}

// Verificar mesas
echo "<h2>5. Verificando Mesas</h2>";
try {
    // Contar total
    $stmt = $pdo->query("SELECT COUNT(*) FROM mesas");
    $total = $stmt->fetchColumn();
    echo "Total de mesas: " . $total . "<br>";
    
    // Contar por tenant_id
    $stmt = $pdo->query("SELECT tenant_id, COUNT(*) as count FROM mesas GROUP BY tenant_id");
    $tenantCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Mesas por tenant_id:<br>";
    foreach ($tenantCounts as $row) {
        echo "- tenant_id " . $row['tenant_id'] . ": " . $row['count'] . " mesas<br>";
    }
    
    // Listar mesas
    $stmt = $pdo->query("SELECT id_mesa, nome, status, tenant_id, filial_id FROM mesas LIMIT 10");
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Primeiras 10 mesas:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID Mesa</th><th>Nome</th><th>Status</th><th>Tenant</th><th>Filial</th></tr>";
    foreach ($mesas as $mesa) {
        echo "<tr>";
        echo "<td>" . $mesa['id_mesa'] . "</td>";
        echo "<td>" . $mesa['nome'] . "</td>";
        echo "<td>" . $mesa['status'] . "</td>";
        echo "<td>" . $mesa['tenant_id'] . "</td>";
        echo "<td>" . $mesa['filial_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Erro ao verificar mesas: " . $e->getMessage() . "<br>";
}

// Testar APIs diretamente
echo "<h2>6. Testando APIs</h2>";
echo "<h3>API de Produtos:</h3>";
$produtosApi = file_get_contents('http://localhost:8080/api/produtos.php');
echo "<pre>" . htmlspecialchars($produtosApi) . "</pre>";

echo "<h3>API de Mesas:</h3>";
$mesasApi = file_get_contents('http://localhost:8080/api/mesas.php');
echo "<pre>" . htmlspecialchars($mesasApi) . "</pre>";
?>
