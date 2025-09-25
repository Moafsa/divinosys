<?php

/**
 * Teste simples do sistema
 */

echo "=== TESTE SIMPLES DO SISTEMA ===\n\n";

// Teste 1: Verificar se os arquivos existem
echo "1. Verificando arquivos do sistema...\n";

$files = [
    'system/Database.php',
    'system/Auth.php', 
    'system/EvolutionAPI.php',
    'mvc/ajax/auth.php',
    'mvc/ajax/evolution.php',
    'mvc/views/evolution_config.php',
    'webhook/evolution.php',
    'config/evolution.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file\n";
    }
}
echo "\n";

// Teste 2: Verificar banco de dados
echo "2. Testando conexão com banco...\n";
try {
    $pdo = new PDO('pgsql:host=divino-lanches-db;port=5432;dbname=divino_db', 'divino_user', 'divino_password');
    echo "✅ Conexão com banco OK\n";
    
    // Verificar tabelas
    $tables = ['usuarios_globais', 'usuarios_telefones', 'evolution_instancias'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ Tabela $table: $count registros\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
echo "\n";

// Teste 3: Verificar configurações
echo "3. Verificando configurações...\n";
if (file_exists('config/evolution.php')) {
    $config = require 'config/evolution.php';
    echo "✅ Configurações carregadas\n";
    echo "   - Base URL: {$config['base_url']}\n";
    echo "   - Webhook n8n: {$config['n8n_webhook_url']}\n";
} else {
    echo "❌ Arquivo de configuração não encontrado\n";
}
echo "\n";

echo "=== TESTE CONCLUÍDO ===\n";
echo "Para testar o sistema completo:\n";
echo "1. Acesse: http://localhost:8080/mvc/views/evolution_config.php\n";
echo "2. Configure uma instância Evolution\n";
echo "3. Teste o envio de mensagens\n";
