<?php
/**
 * Script para adicionar colunas wuzapi_instance_id e wuzapi_token online
 * Execute este script no servidor online para corrigir o problema
 */

// Configuração do banco online
$host = 'localhost';
$dbname = 'divino_db';
$username = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao banco online com sucesso!\n";
    
    // Verificar se as colunas já existem
    $checkColumns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances' 
        AND column_name IN ('wuzapi_instance_id', 'wuzapi_token')
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas existentes: " . implode(', ', $checkColumns) . "\n";
    
    // Adicionar wuzapi_instance_id se não existir
    if (!in_array('wuzapi_instance_id', $checkColumns)) {
        echo "Adicionando coluna wuzapi_instance_id...\n";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_instance_id INTEGER");
        echo "Coluna wuzapi_instance_id adicionada com sucesso!\n";
    } else {
        echo "Coluna wuzapi_instance_id já existe.\n";
    }
    
    // Adicionar wuzapi_token se não existir
    if (!in_array('wuzapi_token', $checkColumns)) {
        echo "Adicionando coluna wuzapi_token...\n";
        $pdo->exec("ALTER TABLE whatsapp_instances ADD COLUMN wuzapi_token VARCHAR(255)");
        echo "Coluna wuzapi_token adicionada com sucesso!\n";
    } else {
        echo "Coluna wuzapi_token já existe.\n";
    }
    
    // Verificar estrutura final da tabela
    $finalColumns = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'whatsapp_instances'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nEstrutura final da tabela whatsapp_instances:\n";
    foreach ($finalColumns as $column) {
        echo "- {$column['column_name']}: {$column['data_type']}\n";
    }
    
    echo "\n✅ Correção concluída com sucesso!\n";
    echo "Agora a criação de instâncias deve funcionar online.\n";
    
} catch (PDOException $e) {
    echo "❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    echo "Verifique as credenciais de conexão.\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
