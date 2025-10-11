<?php
/**
 * AUTO FIX SEQUENCES - Roda automaticamente
 * Este script deve ser chamado no início da aplicação
 */

// Função para corrigir sequências automaticamente
function autoFixSequences() {
    try {
        // Carregar configuração do banco
        require_once __DIR__ . '/vendor/autoload.php';
        $config = \System\Config::getInstance();
        $dbConfig = $config->get('database');
        
        $pdo = new PDO(
            "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}", 
            $dbConfig['user'], 
            $dbConfig['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
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
        
        $fixed = 0;
        foreach ($sequences as $table => $config) {
            try {
                // Verificar se a tabela existe
                $stmt = $pdo->query("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_name = '$table')");
                if (!$stmt->fetchColumn()) {
                    continue; // Pular se tabela não existir
                }
                
                // Obter valor atual da sequência
                $stmt = $pdo->query("SELECT last_value FROM {$config['seq']}");
                $seqValue = $stmt->fetchColumn();
                
                // Obter MAX ID da tabela
                $stmt = $pdo->query("SELECT COALESCE(MAX({$config['id']}), 0) FROM $table");
                $maxId = $stmt->fetchColumn();
                
                // Corrigir se necessário
                if ($seqValue <= $maxId) {
                    $newValue = $maxId + 1;
                    $pdo->exec("SELECT setval('{$config['seq']}', $newValue, false)");
                    $fixed++;
                    error_log("Auto-fixed sequence {$config['seq']}: $seqValue → $newValue (MAX ID: $maxId)");
                }
            } catch (Exception $e) {
                // Log erro mas continua com outras sequências
                error_log("Error fixing sequence for $table: " . $e->getMessage());
            }
        }
        
        if ($fixed > 0) {
            error_log("Auto-fix sequences: $fixed sequences corrected");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Auto-fix sequences error: " . $e->getMessage());
        return false;
    }
}

// Executar apenas se chamado diretamente
if (basename($_SERVER['PHP_SELF']) === 'auto_fix_sequences.php') {
    echo "<h1>🔧 Auto Fix Sequences</h1>";
    $result = autoFixSequences();
    echo $result ? "<p style='color: green;'>✅ Sequences auto-fixed successfully!</p>" : "<p style='color: red;'>❌ Error fixing sequences</p>";
}
?>
