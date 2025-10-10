<?php
// Script para adicionar colunas faltantes na tabela pedido no ambiente online
require_once 'mvc/bootstrap.php';

try {
    $db = \System\Database::getInstance();

    echo "=== ADICIONANDO COLUNAS FALTANTES ===\n";

    // Adicionar coluna troco_para se não existir
    try {
        $db->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS troco_para DECIMAL(10,2)");
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
            $db->query("ALTER TABLE pedido ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            echo "✅ Coluna '{$column}' adicionada/verificada\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao adicionar coluna '{$column}': " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== VERIFICAÇÃO FINAL ===\n";

    // Verificar estrutura final
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'pedido' AND column_name IN ('troco_para', 'forma_pagamento', 'observacao', 'status', 'valor_total')
        ORDER BY ordinal_position
    ");

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
