<?php
/**
 * Script para executar a migration da tabela payment_reminders
 * Execute: php database/migrations/run_payment_reminders_migration.php
 */

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Executando migration: create_payment_reminders_table...\n";
    
    // Ler o arquivo SQL
    $sqlFile = __DIR__ . '/create_payment_reminders_table.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Executar SQL
    $conn->exec($sql);
    
    echo "✅ Migration executada com sucesso!\n";
    echo "Tabela payment_reminders criada.\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}













