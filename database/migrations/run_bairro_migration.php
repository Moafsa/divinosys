<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

try {
    $db = \System\Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/create_taxa_entrega_bairros.sql');
    
    // Connect explicitly and run query
    $pdo = $db->getConnection();
    $pdo->exec($sql);
    
    echo "Migration executada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
}
