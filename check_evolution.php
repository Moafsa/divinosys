<?php

try {
    $pdo = new PDO('pgsql:host=divino-lanches-db;port=5432;dbname=divino_db', 'divino_user', 'divino_password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== INSTÂNCIAS EVOLUTION ===\n";
    $stmt = $pdo->query('SELECT * FROM evolution_instancias');
    $instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($instancias as $instancia) {
        echo "ID: {$instancia['id']}\n";
        echo "Nome: {$instancia['nome_instancia']}\n";
        echo "Telefone: {$instancia['numero_telefone']}\n";
        echo "Status: {$instancia['status']}\n";
        echo "Webhook: {$instancia['webhook_url']}\n";
        echo "---\n";
    }
    
    echo "\n=== CONFIGURAÇÕES ===\n";
    $config = require_once 'config/evolution.php';
    echo "Base URL: {$config['base_url']}\n";
    echo "Webhook n8n: {$config['n8n_webhook_url']}\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
