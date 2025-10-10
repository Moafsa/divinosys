<?php
// Script para verificar estrutura da tabela pedido no ambiente online

// Conexão direta com o banco de dados usando variáveis de ambiente
try {
    // Obter configurações do ambiente
    $dbConfig = [
        'host' => getenv('DB_HOST') ?: 'postgres',
        'port' => getenv('DB_PORT') ?: '5432',
        'name' => getenv('DB_NAME') ?: 'divino_lanches',
        'user' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: 'divino_password'
    ];

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name']
    );

    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    echo "✅ Conexão com banco estabelecida com sucesso!\n";
    echo "📊 Configuração: {$dbConfig['host']}:{$dbConfig['port']}/{$dbConfig['name']}\n\n";

    // Verificar colunas da tabela pedido
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'pedido'
        ORDER BY ordinal_position
    ");

    $columns = $stmt->fetchAll();

    echo "=== ESTRUTURA ATUAL DA TABELA PEDIDO ===\n";
    foreach ($columns as $column) {
        echo sprintf(
            "%-25s | %-15s | %-10s | %s\n",
            $column['column_name'],
            $column['data_type'],
            $column['is_nullable'],
            $column['column_default'] ?? 'NULL'
        );
    }

    // Verificar se as colunas necessárias existem
    $requiredColumns = [
        'troco_para',
        'forma_pagamento',
        'observacao',
        'status',
        'valor_total'
    ];

    echo "\n=== VERIFICAÇÃO DE COLUNAS NECESSÁRIAS ===\n";
    foreach ($requiredColumns as $column) {
        $exists = false;
        foreach ($columns as $col) {
            if ($col['column_name'] === $column) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            echo "✅ {$column} - OK\n";
        } else {
            echo "❌ {$column} - FALTANDO\n";
        }
    }

    echo "\n🎯 Se alguma coluna estiver FALTANDO, execute o script fix_pedido_columns_online.php\n";

} catch (\Exception $e) {
    echo "❌ Erro ao conectar/verificar banco: " . $e->getMessage() . "\n";
}
?>
