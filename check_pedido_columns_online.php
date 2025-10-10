<?php
// Script para verificar estrutura da tabela pedido no ambiente online
require_once 'mvc/bootstrap.php';

try {
    $db = \System\Database::getInstance();

    // Verificar colunas da tabela pedido
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'pedido'
        ORDER BY ordinal_position
    ");

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

} catch (\Exception $e) {
    echo "Erro ao conectar/verificar banco: " . $e->getMessage() . "\n";
}
?>
