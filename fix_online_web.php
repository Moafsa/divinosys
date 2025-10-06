<?php
/**
 * Script web para corrigir problemas de banco online
 * Acesse via: https://seudominio.com/fix_online_web.php
 */

// Configuração de segurança
$allowedIPs = ['127.0.0.1', '::1']; // Adicione IPs permitidos
$currentIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($currentIP, $allowedIPs) && !isset($_GET['force'])) {
    die('❌ Acesso negado. Use ?force=1 para forçar execução.');
}

echo "<h1>🔧 Correção de Banco Online - WuzAPI</h1>";
echo "<p>Executando em: " . date('Y-m-d H:i:s') . "</p>";

// Configuração do banco
$host = 'localhost';
$dbname = 'divino_db';
$username = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Conectado ao banco online com sucesso!</p>";
    
    // Verificar tabela whatsapp_instances
    $tableExists = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'whatsapp_instances'
        )
    ")->fetchColumn();
    
    if (!$tableExists) {
        echo "<p style='color: orange;'>⚠️ Tabela whatsapp_instances não existe. Criando...</p>";
        
        $createTable = "
            CREATE TABLE whatsapp_instances (
                id SERIAL PRIMARY KEY,
                instance_name VARCHAR(100) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                status VARCHAR(20) DEFAULT 'disconnected',
                qr_code TEXT,
                wuzapi_instance_id INTEGER,
                wuzapi_token VARCHAR(255),
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $pdo->exec($createTable);
        echo "<p style='color: green;'>✅ Tabela whatsapp_instances criada!</p>";
    } else {
        echo "<p style='color: green;'>✅ Tabela whatsapp_instances já existe.</p>";
    }
    
    // Verificar colunas existentes
    $existingColumns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Colunas existentes:</strong> " . implode(', ', $existingColumns) . "</p>";
    
    // Adicionar wuzapi_instance_id
    if (!in_array('wuzapi_instance_id', $existingColumns)) {
        echo "<p style='color: blue;'>➕ Adicionando coluna wuzapi_instance_id...</p>";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_instance_id INTEGER");
        echo "<p style='color: green;'>✅ Coluna wuzapi_instance_id adicionada!</p>";
    } else {
        echo "<p style='color: green;'>✅ Coluna wuzapi_instance_id já existe.</p>";
    }
    
    // Adicionar wuzapi_token
    if (!in_array('wuzapi_token', $existingColumns)) {
        echo "<p style='color: blue;'>➕ Adicionando coluna wuzapi_token...</p>";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_token VARCHAR(255)");
        echo "<p style='color: green;'>✅ Coluna wuzapi_token adicionada!</p>";
    } else {
        echo "<p style='color: green;'>✅ Coluna wuzapi_token já existe.</p>";
    }
    
    // Estrutura final
    $finalStructure = $pdo->query("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📊 Estrutura Final da Tabela</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Nullable</th></tr>";
    
    foreach ($finalStructure as $column) {
        echo "<tr>";
        echo "<td>{$column['column_name']}</td>";
        echo "<td>{$column['data_type']}</td>";
        echo "<td>{$column['is_nullable']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Teste de inserção
    echo "<h3>🧪 Teste de Inserção</h3>";
    
    $testData = [
        'instance_name' => 'teste_web_' . time(),
        'phone_number' => '5554997092223',
        'status' => 'disconnected',
        'wuzapi_instance_id' => 99999,
        'wuzapi_token' => 'test_token_' . time(),
        'tenant_id' => 1,
        'filial_id' => 1
    ];
    
    $insertSql = "
        INSERT INTO whatsapp_instances 
        (instance_name, phone_number, status, wuzapi_instance_id, wuzapi_token, tenant_id, filial_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $pdo->prepare($insertSql);
    $result = $stmt->execute([
        $testData['instance_name'],
        $testData['phone_number'],
        $testData['status'],
        $testData['wuzapi_instance_id'],
        $testData['wuzapi_token'],
        $testData['tenant_id'],
        $testData['filial_id']
    ]);
    
    if ($result) {
        $testId = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Teste de inserção bem-sucedido! ID: $testId</p>";
        
        // Limpar dados de teste
        $pdo->exec("DELETE FROM whatsapp_instances WHERE id = $testId");
        echo "<p style='color: blue;'>🧹 Dados de teste removidos.</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro no teste de inserção.</p>";
    }
    
    echo "<h2 style='color: green;'>🎉 Correção Concluída com Sucesso!</h2>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Teste a criação de instância no sistema online</li>";
    echo "<li>Verifique se não há mais erros de 'Database query failed'</li>";
    echo "<li>Continue com a implementação do sistema de caixa avançado</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php?view=configuracoes' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Testar Criação de Instância</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro de conexão: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>
