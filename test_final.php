<?php
require_once 'system/Config.php';
require_once 'system/Database.php';
require_once 'system/WhatsApp/ChatwootManager.php';

use System\WhatsApp\ChatwootManager;

echo "=== TESTE DETALHADO CHATWOOT ===\n";

echo "1. Verificando variáveis de ambiente...\n";
echo "CHATWOOT_URL: " . ($_ENV['CHATWOOT_URL'] ?? 'NÃO DEFINIDO') . "\n";
echo "CHATWOOT_API_KEY: " . (empty($_ENV['CHATWOOT_API_KEY']) ? 'NÃO DEFINIDO' : 'DEFINIDO (' . strlen($_ENV['CHATWOOT_API_KEY']) . ' chars)') . "\n";
echo "CHATWOOT_ACCOUNT_ID: " . ($_ENV['CHATWOOT_ACCOUNT_ID'] ?? 'NÃO DEFINIDO') . "\n\n";

try {
    echo "2. Criando instância ChatwootManager...\n";
    $chatwoot = new ChatwootManager();
    echo "✅ ChatwootManager criado com sucesso\n\n";
    
    echo "3. Testando createChatwootUser...\n";
    $user = $chatwoot->createChatwootUser(11, 'Teste User', 'teste_user_' . time() . '@example.com', '+5554997092234');
    if ($user) {
        echo "✅ Usuário criado: " . json_encode($user) . "\n\n";
    } else {
        echo "❌ Falha ao criar usuário\n\n";
    }
    
    echo "4. Testando createWhatsAppInbox...\n";
    $inbox = $chatwoot->createWhatsAppInbox(11, 'Teste Inbox', '+5554997092235');
    if ($inbox) {
        echo "✅ Inbox criado: " . json_encode($inbox) . "\n\n";
    } else {
        echo "❌ Falha ao criar inbox\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM TESTE ===\n";
?>
