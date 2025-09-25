<?php

try {
    // Conectar no banco da Evolution
    $pdo = new PDO('pgsql:host=postgres;port=5432;dbname=evolutiona9T1BfvBoz', 'postgres', '122334Qw!!Conext');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CONECTADO NO BANCO DA EVOLUTION ===\n\n";
    
    // Listar tabelas
    echo "1. TABELAS DISPONÍVEIS:\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
    echo "\n";
    
    // Verificar tabela de instâncias
    if (in_array('instances', $tables)) {
        echo "2. INSTÂNCIAS NA EVOLUTION:\n";
        $stmt = $pdo->query("SELECT * FROM instances");
        $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($instances as $instance) {
            echo "   - ID: {$instance['id']}\n";
            echo "     Nome: {$instance['instanceName']}\n";
            echo "     Status: {$instance['status']}\n";
            echo "     Criado: {$instance['createdAt']}\n";
            echo "     ---\n";
        }
    }
    
    // Verificar outras tabelas relevantes
    if (in_array('webhook', $tables)) {
        echo "3. WEBHOOKS CONFIGURADOS:\n";
        $stmt = $pdo->query("SELECT * FROM webhook");
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($webhooks as $webhook) {
            echo "   - URL: {$webhook['url']}\n";
            echo "     Eventos: {$webhook['events']}\n";
            echo "     ---\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
