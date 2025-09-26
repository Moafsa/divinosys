<?php
/**
 * MIGRAÇÃO APENAS DAS TABELAS CRÍTICAS
 * Dropa e recria apenas tabelas que falharam na criação de instâncias
 */

echo "=== MIGRAÇÃO CRÍTICA - TABELAS PROBLEMÁTICAS ===\n";
echo "Dropando apenas tabelas que causam erro na criação de instâncias\n\n";

// Config online (substituir pelos dados do Coolify real)
$onlineHost = $_ENV['DB_HOST'] ?? 'localhost';
$onlinePort = $_ENV['DB_PORT'] ?? '5432';
$onlineDb = $_ENV['DB_NAME'] ?? 'divino_lanches';
$onlineUser = $_ENV['DB_USER'] ?? 'postgres';
$onlinePassword = $_ENV['DB_PASSWORD'] ?? '';

// Config local
$localHost = 'localhost';
$localPort = '5433';
$localDb = 'divino_db';
$localUser = 'divino_user';
$localPassword = 'divino_password';

/**
 * APENAS AS TABELAS CRÍTICAS QUE ESTÃO CAUSANDO ERRO NA CRIAÇÃO DE INSTÂNCIAS
 */
$criticalTables = [
    'whatsapp_instances',
    'whatsapp_messages', 
    'whatsapp_webhooks'
];

try {
    // Conectar ao local (fonte funcional)
    $localDsn = "pgsql:host=$localHost;port=$localPort;dbname=$localDb";
    $localPdo = new PDO($localDsn, $localUser, $localPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Conectar ao online (problema)
    $onlineDsn = "pgsql:host=$onlineHost;port=$onlinePort;dbname=$onlineDb";
    $onlinePdo = new PDO($onlineDsn, $onlineUser, $onlinePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "✅ Conectado aos dois BDs\n\n";

    foreach($criticalTables as $table) {
        echo "🔄 MIGRANDO TABELA: $table\n";
        echo "==========================================\n";
        
        // 1. PEGAR dados do local
        echo "📥 Extraindo dados do BD local...\n";
        $localQuery = $localPdo->query("SELECT * FROM $table");
        $rows = $localQuery->fetchAll(PDO::FETCH_ASSOC);
        $total = count($rows);
        echo "  📦 $total registros encontrados no BD local\n";
        
        // Se não tem dados, pular
        if($total == 0) {
            echo "  ⚠️ Nenhum dado na tabela $table - pulando\n\n";
            continue;
        }

        // 2. DELETAR tudo no BD online
        echo "🗑️ Limpando tabela online...\n";
        $onlinePdo->exec("DELETE FROM $table");
        echo "  ✅ Dados antigos removidos\n";

        // 3. IMPORTAR dados locais
        echo "📤 Importando dados locais para online...\n";
        
        // Pegar estrutura dos dados
        $columns = array_keys($rows[0]);
        $columnsStr = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);
        
        $insertSql = "INSERT INTO $table ($columnsStr) VALUES ($placeholders)";
        $stmt = $onlinePdo->prepare($insertSql);
        
        $success = 0;
        $errors = 0;
        
        foreach($rows as $row) {
            try {
                $stmt->execute($row);
                $success++;
            } catch (Exception $e) {
                echo "    ❌ Erro inserindo registro: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        echo "    ✅ $success registros importados\n";
        if($errors > 0) echo "    ❌ $errors erros\n";
        
        echo "==========================================\n\n";
    }

    echo "🎯 MIGRAÇÃO CONCLUÍDA!\n";
    echo "Foi migrado:\n";
    foreach($criticalTables as $table) {
        echo "• $table\n";
    }
    echo "\nAs instâncias WhatsApp agora devem funcionar! ✅\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
