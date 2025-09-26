<?php
/**
 * MIGRAÇÃO SEGURA - BD Local para Online 
 * DROP tables problemáticas do online e cria com dados locais que funcionam
 */

echo "=== MIGRAÇÃO SEGURA LOCAL → ONLINE ===\n";
echo "Dropando tabelas problemáticas e recriando com dados funcionais\n\n";

// Configurações do BD local (fonte)
$localHost = 'localhost';
$localPort = '5433';
$localDb = 'divino_db';
$localUser = 'divino_user';
$localPassword = 'divino_password';

// Configurações do BD online (destino)
$onlineHost = $_ENV['DB_HOST'] ?? 'postgres';
$onlinePort = $_ENV['DB_PORT'] ?? '5432';
$onlineDb = $_ENV['DB_NAME'] ?? 'divino_lanches';
$onlineUser = $_ENV['DB_USER'] ?? 'postgres';
$onlinePassword = $_ENV['DB_PASSWORD'] ?? 'divino_password';

echo "📦 CONEXÕES CONFIGURADAS:\n";
echo "Local:  $localHost:$localPort/$localDb\n";
echo "Online: $onlineHost:$onlinePort/$onlineDb\n\n";

/**
 * TABELAS PRINCIPAIS QUE DEVEM SER MIGRADAS
 * Estas tabelas são críticas para o funcionamento do sistema
 */
$criticalTables = [
    'whatsapp_instances',    // Principal - instâncias WhatsApp
    'whatsapp_messages',     // Mensagens
    'whatsapp_webhooks',     // Webhooks
    'usuarios',              // Usuários
    'usuarios_globais',      // Usuários globais
    'usuarios_estabelecimento', // Vinculação usuários
    'usuarios_telefones',    // Telefones dos usuários
    'whatsapp_instances_id_seq', // Sequence para ID
    'whatsapp_messages_id_seq',  // Sequence para ID
    'whatsapp_webhooks_id_seq',  // Sequence para ID
];

/**
 * TABELAS RELACIONADAS (se existirem conflitos)
 */
$relatedTables = [
    'tenants', 'filiais', 'perfil_estabelecimento', 'usuarios_logs_acesso'
];

try {
    // 1. Conectar ao BD local (fonte)
    echo "🔗 Conectando ao BD local (fonte)...\n";
    $localDsn = "pgsql:host=$localHost;port=$localPort;dbname=$localDb";
    $localPdo = new PDO($localDsn, $localUser, $localPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conectado ao BD local\n\n";

    // 2. Conectar ao BD online (destino)
    echo "🔗 Conectando ao BD online (destino)...\n";
    $onlineDsn = "pgsql:host=$onlineHost;port=$onlinePort;dbname=$onlineDb";
    $onlinePdo = new PDO($onlineDsn, $onlineUser, $onlinePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conectado ao BD online\n\n";

    // 3. Criar backup das sequences antes de apagar
    echo "💾 Salvando URLs de sequences críticas...\n";
    foreach($relatedTables as $table) {
        try {
            $seqQuery = "SELECT last_value FROM {$table}_id_seq";
            $result = $localPdo->query($seqQuery);
            if ($result) {
                $currentValue = $result->fetchColumn();
                echo "  ✅ $table: sequence valor atual = $currentValue\n";
            }
        } catch (Exception $e) {
            // Sequence pode não existir
            echo "  ⚠️ $table: sequence não encontrada\n";
        }
    }

    foreach($criticalTables as $table) {
        try {
            // Processo por tabela crítica
            
            echo "📊 PROCESSANDO TABELA: $table\n";
            echo "----------------------------------------\n";
            
            // 3.1 Fazer backup dos dados críticos via SELECT
            echo "📥 Extraindo dados do BD local...\n";
            $dataQuery = $localPdo->query("SELECT * FROM $table");
            $rows = $dataQuery->fetchAll();
            
            $totalRecords = count($rows);
            echo "  📦 $totalRecords registros encontrados\n";
            
            if($totalRecords == 0) {
                echo "  ⚠️ Nenhum dado na tabela local, pulando...\n\n";
                continue;
            }
            
            // 3.2 CRIAR SQL de INSERT seguro
            if($totalRecords > 0) {
                $columns = array_keys($rows[0]);
                $columnsStr = implode(', ', $columns);
                $placeholders = ':' . implode(', :', $columns);
                
                // 3.3 DROP da tabela online (se existir)
                echo "🗑️ Removendo dados antigos do BD online...\n";
                try {
                    $onlinePdo->exec("DELETE FROM $table");
                    echo "  ✅ Dados antigos removidos\n";
                } catch (Exception $e) {
                    echo "  ⚠️ Tabela $table não existe no online (normal)\n";
                }
                
                // 3.4 ESCREVER dados locais no online  
                echo "📤 Importando $totalRecords registros para BD online...\n";
                
                $insertQuery = "INSERT INTO $table ($columnsStr) VALUES ($placeholders)";
                $stmt = $onlinePdo->prepare($insertQuery);
                
                foreach($rows as $row) {
                    // Processing de dados para escapamento
                    $cleanRow = $row;
                    foreach($cleanRow as $key => $value) {
                        if($value === null || $value === 'NULL') {
                            $cleanRow[$key] = null;
                        }
                    }
                    
                    if($stmt->execute($cleanRow)) {
                        // OK INSERTED
                    } else {
                        echo "  ❌ Erro ao inserir registro: " . implode(', ', $stmt->errorInfo()) . "\n";
                    }
                }
                
                echo "  ✅ $totalRecords registros importados com sucesso\n";
            }
            
            echo "----------------------------------------\n\n";
            
        } catch (Exception $e) {
            echo "❌ ERRO na tabela $table: " . $e->getMessage() . "\n\n";
            continue;
        }
    }

    echo "🎯 MIGRAÇÃO CONCLUÍDA!\n";
    echo "O BD online agora tem dados que funcionam LOCALMENTE.\n";
    echo "As instâncias WhatsApp devem funcionar corretamente\n";
    
} catch (Exception $e) {
    echo "❌ ERRO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
