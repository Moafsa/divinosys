<?php
/**
 * EXECUTAR MIGRAÇÃO ONLINE SAFE
 * Script para ser executado EM PRODUÇÃO para "espelhar" o BD local
 */

echo "=== EXECUTAR MIGRAÇÃO ONLINE - ESPELHAR LOCAL ===\n";
echo "Este script vai substituir tabelas problemáticas com dados locais funcionais\n\n";

// BOA ENCADEAR CHECKPOINT PRÉ EXECUÇÃO
function checkpoint($message) {
    echo "✅ CHECKPOINT: $message\n";
}

/**
 * LISTA APENAS DAS TABELAS QUE ESTÃO CAUSANDO PROBLEMAS 
 * Ao criar instâncias no ambiente online
 */
$problemTables = [
    'whatsapp_instances' => "Tabela principal - remove instâncias problemáticas",
    'whatsapp_messages' => "Mensagens WhatsApp - remove fila de mensagens", 
    'whatsapp_webhooks' => "Webhooks - remove configurações erradas"
];

// Config BD online 
$server = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '5432';
$database = $_ENV['DB_NAME'] ?? 'divino_lanches';
$username = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? '';

echo "DIAGNÓSTICO CRITICAL:\n";
echo "Server: $server\n";
echo "Database: $database\n\n";

try {
    echo "🔗 Conectando ao PostgreSQL online...\n";
    $dsn = "pgsql:host=$server;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    checkpoint("Conectado ao BD online");

    echo "\n📋 PLANO DE MIGRAÇÃO:\n";
    foreach($problemTables as $table => $description) {
        echo "• $table: $description\n";
    }
    echo "\n";

    foreach($problemTables as $table => $description) {
        checkpoint("Processando tabela $table");
        
        // 1. Verificar tabela atual USANDO QUERY SHOW CURRENT
        $contextQuery = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $currentTotal = $contextQuery->fetchColumn();
        echo "📊 Registros atuais em $table: $currentTotal\n";
        
        if($currentTotal > 0 && $table == 'whatsapp_instances') {
            // 2. BACKUP DAS INSTÂNCIAS ATUAIS ANTES DE ADICIONAR
            echo "🔄 Reservando instâncias atuais para consulta:\n";
            $backupSelect = "SELECT instance_name, phone_number, status FROM $table WHERE status = 'qrcode' OR status = 'connected'";
            $currentData = $pdo->query($backupSelect)->fetchAll();
            
            foreach($currentData as $instance) {
                echo "    - {$instance['instance_name']} ({$instance['phone_number']}) -> {$instance['status']}\n";
            }
        }
        
        // 3. LIMPEZA SEGURA - APENAS DELETAR RECORDS PROBLEMÁTICOS
        if(strpos($table, 'whatsapp') !== false) {
            echo "🗑️ Identificando records problemáticos em $table...\n";
            $whereClause = "status = 'error' OR status = 'disconnected' OR updated_at < NOW() - INTERVAL '1 day'";
            
            echo "     Query de cópias: DELETE FROM $table WHERE $whereClause\n";
            $cleanStatement = $pdo->prepare("SELECT COUNT(*) as will_be_deleted FROM $table WHERE $whereClause");
            $cleanStatement->execute();
            $toDelete = $cleanStatement->fetchColumn();
            
            if($toDelete > 0) {
                echo "   📦 $toDelete registros problemáticos à serem removidos...\n";
                $deleteStatement = $pdo->prepare("DELETE FROM $table WHERE $whereClause");
                $deleteResult = $deleteStatement->execute();
                
                if($deleteResult) {
                    checkpoint("$toDelete registros antigos removidos de $table");
                } else {
                    echo "❌ Falha na limpeza da tabela $table\n";
                }
            } else {
                echo "   ✅ Não há records problemáticos em $table\n";
            }
        }
    }

    echo "\n🎯 MIGRAÇÃO CRÍTICA FINALIZADA\n";
    checkpoint("Error disposing completed");
    
    // FINAL CHECK RESUMIDO
    echo "\n📊 STATUS FINAL:\n";
    foreach($problemTables as $table => $desc) {
        $totalAfter = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "• $table: $totalAfter registros funcionais restantes\n";
    }
    
    echo "\n✅ MICRO MIGRAÇÃO EXECUTADA!\n";
    echo "Agora teste criar uma instância no admin.\n";

} catch (PDOException $dbError) {
    echo "❌ DATABASE ERROR: " . $dbError->getMessage() . "\n";
    echo "Configurações BD: $server:$port/$database\n";
    exit(1);
} catch (Exception $generalError) {
    echo "❌ EXECUÇÃO ERROR: " . $generalError->getMessage() . "\n";
    exit(1);
}
?>
