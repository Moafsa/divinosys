<?php
/**
 * Script para executar a migration de adicionar lembrete_enviado à tabela reservas
 */

require_once __DIR__ . '/vendor/autoload.php';

use System\Database;

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance();
    
    echo "========================================\n";
    echo "EXECUTANDO MIGRATION: add_lembrete_enviado_to_reservas\n";
    echo "========================================\n\n";
    
    $migrationFile = __DIR__ . '/database/migrations/add_lembrete_enviado_to_reservas.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty(trim($sql))) {
        throw new Exception("Arquivo de migration está vazio");
    }
    
    echo "Lendo arquivo: $migrationFile\n";
    echo "Tamanho: " . strlen($sql) . " bytes\n\n";
    
    // Execute the SQL statement directly as it's a single DO block
    $db->query($sql);
    
    echo "\n✅ Migration 'add_lembrete_enviado_to_reservas' executada com sucesso!\n";
    
    echo "\n========================================\n";
    echo "✅ MIGRATION CONCLUÍDA\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "❌ ERRO NA MIGRATION\n";
    echo "========================================\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}













