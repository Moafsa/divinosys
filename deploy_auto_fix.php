<?php
/**
 * DEPLOY AUTO FIX - Para executar após cada deploy online
 * Este script deve ser executado após cada deploy no Coolify
 */

echo "🚀 DEPLOY AUTO FIX SEQUENCES\n";
echo "============================\n\n";

// Função para corrigir sequências
function deployFixSequences() {
    try {
        // Usar variáveis de ambiente do Coolify
        $host = $_ENV['DB_HOST'] ?? 'postgres';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $dbname = $_ENV['DB_NAME'] ?? 'divino_db';
        $user = $_ENV['DB_USER'] ?? 'divino_user';
        $password = $_ENV['DB_PASSWORD'] ?? 'divino_password';
        
        echo "Connecting to: $dbname@$host:$port\n";
        
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ Database connection successful!\n\n";
        
        // Lista de sequências para corrigir
        $sequences = [
            'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
            'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
            'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
            'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
            'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
            'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
            'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
        ];
        
        echo "🔧 Fixing sequences...\n";
        $fixed = 0;
        
        foreach ($sequences as $table => $config) {
            try {
                // Verificar se a tabela existe
                $stmt = $pdo->query("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_name = '$table')");
                if (!$stmt->fetchColumn()) {
                    echo "   ⚠️  Table $table does not exist, skipping\n";
                    continue;
                }
                
                // Obter valor atual da sequência
                $stmt = $pdo->query("SELECT last_value FROM {$config['seq']}");
                $seqValue = $stmt->fetchColumn();
                
                // Obter MAX ID da tabela
                $stmt = $pdo->query("SELECT COALESCE(MAX({$config['id']}), 0) FROM $table");
                $maxId = $stmt->fetchColumn();
                
                echo "   $table: Seq=$seqValue, Max=$maxId";
                
                // Corrigir se necessário
                if ($seqValue <= $maxId) {
                    $newValue = $maxId + 1;
                    $pdo->exec("SELECT setval('{$config['seq']}', $newValue, false)");
                    echo " → Fixed to $newValue";
                    $fixed++;
                }
                echo "\n";
                
            } catch (Exception $e) {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n✅ Deploy fix completed! $fixed sequences corrected.\n";
        
        // Verificar coluna tipo_usuario
        echo "\n🔍 Checking tipo_usuario column...\n";
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'usuarios_estabelecimento' 
            AND column_name = 'tipo_usuario'
        ");
        
        if ($stmt->rowCount() == 0) {
            echo "   Adding missing tipo_usuario column...\n";
            $pdo->exec("ALTER TABLE usuarios_estabelecimento ADD COLUMN tipo_usuario VARCHAR(50) NOT NULL DEFAULT 'admin'");
            echo "   ✅ Column added!\n";
        } else {
            echo "   ✅ Column already exists\n";
        }
        
        echo "\n🎉 DEPLOY FIX COMPLETED SUCCESSFULLY!\n";
        echo "Your online system is now ready to use!\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        return false;
    }
}

// Executar
deployFixSequences();
?>
