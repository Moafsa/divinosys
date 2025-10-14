<?php

require_once 'config/config.php';
require_once 'system/Database.php';

use System\Database;

try {
    echo "🚀 Iniciando migração do sistema de autenticação por telefone...\n";
    
    // Initialize database
    Database::init();
    $db = Database::getInstance();
    
    // Read migration file
    $migrationFile = 'database/migrations/create_phone_auth_tables.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Arquivo de migração não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "📊 Executando " . count($statements) . " comandos SQL...\n";
    
    foreach ($statements as $i => $statement) {
        try {
            echo "   " . ($i + 1) . ". Executando: " . substr($statement, 0, 50) . "...\n";
            $db->execute($statement);
            echo "      ✅ Sucesso\n";
        } catch (Exception $e) {
            // Check if it's a "relation already exists" error (which is OK)
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'relation') !== false) {
                echo "      ⚠️  Já existe (ignorando)\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Insert default WhatsApp instance if not exists
    echo "\n📱 Verificando instância WhatsApp padrão...\n";
    
    $existingInstance = $db->fetch(
        "SELECT id FROM whatsapp_instances WHERE instance_name = 'default' LIMIT 1"
    );
    
    if (!$existingInstance) {
        $db->insert('whatsapp_instances', [
            'tenant_id' => 1,
            'filial_id' => 1,
            'instance_name' => 'default',
            'phone_number' => '5511999999999',
            'wuzapi_token' => 'default_token_' . uniqid(),
            'status' => 'connecting'
        ]);
        echo "   ✅ Instância WhatsApp padrão criada\n";
    } else {
        echo "   ⚠️  Instância WhatsApp já existe\n";
    }
    
    // Create default global user for testing
    echo "\n👤 Verificando usuário global padrão...\n";
    
    $existingUser = $db->fetch(
        "SELECT id FROM usuarios_globais WHERE nome LIKE '%Teste%' LIMIT 1"
    );
    
    if (!$existingUser) {
        $userId = $db->insert('usuarios_globais', [
            'nome' => 'Usuário Teste',
            'email' => 'teste@divinolanches.com',
            'ativo' => true
        ]);
        
        // Add phone number
        $db->insert('usuarios_telefones', [
            'usuario_global_id' => $userId,
            'telefone' => '11999999999',
            'tipo' => 'principal',
            'ativo' => true
        ]);
        
        // Add establishment association as admin
        $db->insert('usuarios_estabelecimento', [
            'usuario_global_id' => $userId,
            'tenant_id' => 1,
            'filial_id' => 1,
            'tipo_usuario' => 'admin',
            'ativo' => true
        ]);
        
        echo "   ✅ Usuário teste criado (ID: $userId)\n";
        echo "   📞 Telefone: 11999999999\n";
        echo "   🔑 Tipo: admin\n";
    } else {
        echo "   ⚠️  Usuário teste já existe\n";
    }
    
    echo "\n🎉 Migração concluída com sucesso!\n";
    echo "\n📋 Próximos passos:\n";
    echo "   1. Configure uma instância WuzAPI ativa\n";
    echo "   2. Teste o sistema de login por telefone\n";
    echo "   3. Configure os tipos de usuário conforme necessário\n";
    echo "\n🔧 Para testar:\n";
    echo "   - Acesse: index.php?view=login\n";
    echo "   - Use o telefone: 11999999999\n";
    echo "   - O código será enviado via WhatsApp (se WuzAPI estiver configurada)\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro durante a migração:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\n🔍 Verifique:\n";
    echo "   1. Se o banco de dados está acessível\n";
    echo "   2. Se as credenciais estão corretas\n";
    echo "   3. Se o arquivo de migração existe\n";
    exit(1);
}
