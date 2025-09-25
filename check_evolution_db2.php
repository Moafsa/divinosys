<?php

// Tentar diferentes senhas
$passwords = [
    '122334Qw!!Conext',
    '122334QwConext',
    'postgres',
    'evolution',
    'admin'
];

foreach ($passwords as $password) {
    try {
        echo "Tentando senha: $password\n";
        $pdo = new PDO('pgsql:host=postgres;port=5432;dbname=evolutiona9T1BfvBoz', 'postgres', $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ CONECTADO COM SUCESSO!\n";
        
        // Listar tabelas
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tabelas encontradas: " . implode(', ', $tables) . "\n";
        
        // Verificar instâncias
        if (in_array('instances', $tables)) {
            $stmt = $pdo->query("SELECT * FROM instances");
            $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Instâncias: " . count($instances) . "\n";
            foreach ($instances as $instance) {
                echo "  - {$instance['instanceName']} ({$instance['status']})\n";
            }
        }
        
        break;
        
    } catch (Exception $e) {
        echo "❌ Falhou: " . $e->getMessage() . "\n\n";
    }
}
