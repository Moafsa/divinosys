<?php

try {
    $pdo = new PDO('pgsql:host=divino-lanches-db;port=5432;dbname=divino_db', 'divino_user', 'divino_password');
    $pdo->exec('DELETE FROM evolution_instancias');
    echo "Instâncias excluídas com sucesso!\n";
    
    // Verificar se foi limpo
    $stmt = $pdo->query('SELECT COUNT(*) FROM evolution_instancias');
    $count = $stmt->fetchColumn();
    echo "Instâncias restantes: $count\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
