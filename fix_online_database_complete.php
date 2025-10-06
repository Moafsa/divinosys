<?php
/**
 * Script completo para corrigir problemas de banco online
 * - Adiciona colunas wuzapi_instance_id e wuzapi_token
 * - Verifica e cria tabelas necessárias
 * - Testa a criação de instância
 */

// Configuração do banco online
$host = 'localhost';
$dbname = 'divino_db';
$username = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Conectado ao banco online com sucesso!\n\n";
    
    // 1. Verificar se a tabela whatsapp_instances existe
    $tableExists = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'whatsapp_instances'
        )
    ")->fetchColumn();
    
    if (!$tableExists) {
        echo "❌ Tabela whatsapp_instances não existe. Criando...\n";
        
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
        echo "✅ Tabela whatsapp_instances criada com sucesso!\n";
    } else {
        echo "✅ Tabela whatsapp_instances já existe.\n";
    }
    
    // 2. Verificar colunas existentes
    $existingColumns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n📋 Colunas existentes: " . implode(', ', $existingColumns) . "\n";
    
    // 3. Adicionar wuzapi_instance_id se não existir
    if (!in_array('wuzapi_instance_id', $existingColumns)) {
        echo "\n➕ Adicionando coluna wuzapi_instance_id...\n";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_instance_id INTEGER");
        echo "✅ Coluna wuzapi_instance_id adicionada!\n";
    } else {
        echo "✅ Coluna wuzapi_instance_id já existe.\n";
    }
    
    // 4. Adicionar wuzapi_token se não existir
    if (!in_array('wuzapi_token', $existingColumns)) {
        echo "\n➕ Adicionando coluna wuzapi_token...\n";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_token VARCHAR(255)");
        echo "✅ Coluna wuzapi_token adicionada!\n";
    } else {
        echo "✅ Coluna wuzapi_token já existe.\n";
    }
    
    // 5. Verificar estrutura final
    $finalStructure = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📊 Estrutura final da tabela whatsapp_instances:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-25s %-15s %-10s %-20s\n", "Coluna", "Tipo", "Nullable", "Default");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($finalStructure as $column) {
        printf("%-25s %-15s %-10s %-20s\n", 
            $column['column_name'], 
            $column['data_type'], 
            $column['is_nullable'], 
            $column['column_default'] ?? 'NULL'
        );
    }
    echo str_repeat("-", 80) . "\n";
    
    // 6. Testar inserção de dados
    echo "\n🧪 Testando inserção de dados...\n";
    
    $testData = [
        'instance_name' => 'teste_online_' . time(),
        'phone_number' => '5554997092223',
        'status' => 'disconnected',
        'wuzapi_instance_id' => 12345,
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
        echo "✅ Teste de inserção bem-sucedido! ID: $testId\n";
        
        // Limpar dados de teste
        $pdo->exec("DELETE FROM whatsapp_instances WHERE id = $testId");
        echo "🧹 Dados de teste removidos.\n";
    } else {
        echo "❌ Erro no teste de inserção.\n";
    }
    
    echo "\n🎉 Correção concluída com sucesso!\n";
    echo "Agora a criação de instâncias deve funcionar online.\n";
    echo "\n📝 Próximos passos:\n";
    echo "1. Teste a criação de instância no sistema online\n";
    echo "2. Verifique se não há mais erros de 'Database query failed'\n";
    echo "3. Continue com a implementação do sistema de caixa avançado\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
    echo "Verifique as credenciais de conexão online.\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
