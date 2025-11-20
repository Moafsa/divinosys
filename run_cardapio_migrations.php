<?php
/**
 * Script para executar migrations do cardápio online
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== Executando Migrations do Cardápio Online ===\n\n";
    
    // Migration 1: Campos do cardápio online
    echo "1. Adicionando campos do cardápio online nas filiais...\n";
    $migration1 = file_get_contents(__DIR__ . '/database/migrations/create_cardapio_online_fields.sql');
    $pdo->exec($migration1);
    echo "   ✓ Campos adicionados com sucesso!\n\n";
    
    // Migration 2: Campos de pagamento Asaas no pedido
    echo "2. Adicionando campos de pagamento Asaas no pedido...\n";
    $migration2 = file_get_contents(__DIR__ . '/database/migrations/add_asaas_payment_fields_pedido.sql');
    $pdo->exec($migration2);
    echo "   ✓ Campos adicionados com sucesso!\n\n";
    
    // Verificar se as colunas foram criadas
    echo "3. Verificando colunas criadas...\n";
    
    // Verificar colunas na tabela filiais
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'filiais' 
        AND column_name IN ('cardapio_online_ativo', 'taxa_delivery_fixa', 'usar_calculo_distancia')
        ORDER BY column_name
    ");
    $colunas_filiais = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Colunas em filiais: " . implode(', ', $colunas_filiais) . "\n";
    
    // Verificar colunas na tabela pedido
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        AND column_name IN ('asaas_payment_id', 'asaas_payment_url', 'telefone_cliente', 'tipo_entrega')
        ORDER BY column_name
    ");
    $colunas_pedido = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Colunas em pedido: " . implode(', ', $colunas_pedido) . "\n\n";
    
    echo "=== Migrations executadas com sucesso! ===\n";
    echo "\nPróximos passos:\n";
    echo "1. Configure as filiais para ativar o cardápio online:\n";
    echo "   UPDATE filiais SET cardapio_online_ativo = true WHERE id = FILIAL_ID;\n";
    echo "\n2. Acesse o cardápio:\n";
    echo "   http://seudominio.com/index.php?view=cardapio_online&tenant=1&filial=1\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

