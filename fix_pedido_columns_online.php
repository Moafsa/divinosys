<?php
// Script para adicionar colunas faltantes na tabela pedido no ambiente online

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

    echo "=== ADICIONANDO COLUNAS FALTANTES ===\n";

    // Adicionar coluna troco_para se não existir
    try {
        $pdo->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS troco_para DECIMAL(10,2)");
        echo "✅ Coluna 'troco_para' adicionada/verificada\n";
    } catch (\Exception $e) {
        echo "❌ Erro ao adicionar coluna 'troco_para': " . $e->getMessage() . "\n";
    }

    // Verificar outras colunas necessárias
    $columnsToCheck = [
        'forma_pagamento' => "VARCHAR(50)",
        'observacao' => "TEXT",
        'status' => "VARCHAR(50) DEFAULT 'Pendente'",
        'valor_total' => "DECIMAL(10,2) DEFAULT 0.00"
    ];

    foreach ($columnsToCheck as $column => $definition) {
        try {
            $pdo->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            echo "✅ Coluna '{$column}' adicionada/verificada\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao adicionar coluna '{$column}': " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== VERIFICAÇÃO FINAL ===\n";

    // Verificar estrutura final
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'pedido' AND column_name IN ('troco_para', 'forma_pagamento', 'observacao', 'status', 'valor_total')
        ORDER BY ordinal_position
    ");

    $columns = $stmt->fetchAll();

    foreach ($columns as $column) {
        echo sprintf(
            "%-20s | %-15s | %-10s\n",
            $column['column_name'],
            $column['data_type'],
            $column['is_nullable']
        );
    }

    echo "\n✅ Processo concluído! Tente fechar o pedido novamente.\n";

} catch (\Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
}
?>
