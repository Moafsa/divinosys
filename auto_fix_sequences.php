<?php
/**
 * AUTO FIX SEQUENCES - ROBUST VERSION
 * 
 * This script automatically fixes sequence issues when called from index.php
 * It runs silently and handles all errors gracefully to prevent HTTP 500 errors
 */

function autoFixSequences() {
    // Check if we're in a web context and if session is already fixed
    if (isset($_SESSION['sequences_fixed']) && $_SESSION['sequences_fixed'] === true) {
        return true;
    }
    
    try {
        // Check if System classes are available
        if (!class_exists('System\Database') || !class_exists('System\Config')) {
            error_log('Auto-fix sequences: System classes not available');
            return false;
        }
        
        // Get database connection from system
        $db = \System\Database::getInstance();
        $pdo = $db->getConnection();
        
        // Verify database connection
        if (!$pdo) {
            error_log('Auto-fix sequences: Database connection failed');
            return false;
        }
        
        // List of tables and their sequences with error handling
        $tables = [
            'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
            'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
            'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
            'mesas' => ['seq' => 'mesas_id_seq', 'id' => 'id'],
            'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
            'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
            'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
            'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
        ];
        
        $fixedCount = 0;
        $errorCount = 0;
        
        // Sync all sequences with comprehensive error handling
        foreach ($tables as $table => $config) {
            try {
                // Check if table exists
                $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
                $tableExists = $stmt->fetchColumn();
                
                if (!$tableExists) {
                    error_log("Auto-fix sequences: Table '$table' does not exist, skipping");
                    continue;
                }
                
                // Check if sequence exists
                $stmt = $pdo->query("SELECT EXISTS (SELECT FROM pg_sequences WHERE sequencename = '{$config['seq']}')");
                $sequenceExists = $stmt->fetchColumn();
                
                if (!$sequenceExists) {
                    error_log("Auto-fix sequences: Sequence '{$config['seq']}' does not exist, skipping");
                    continue;
                }
                
                // Get current sequence value safely
                $stmt = $pdo->query("SELECT last_value FROM {$config['seq']}");
                if (!$stmt) {
                    error_log("Auto-fix sequences: Failed to get sequence value for {$config['seq']}");
                    $errorCount++;
                    continue;
                }
                $currentValue = $stmt->fetchColumn();
                
                // Get max ID from table safely
                $stmt = $pdo->query("SELECT COALESCE(MAX({$config['id']}), 0) FROM $table");
                if (!$stmt) {
                    error_log("Auto-fix sequences: Failed to get max ID for table $table");
                    $errorCount++;
                    continue;
                }
                $maxId = $stmt->fetchColumn();
                
                // If sequence is behind, sync it
                if ($currentValue <= $maxId) {
                    $newValue = $maxId + 1;
                    $result = $pdo->exec("SELECT setval('{$config['seq']}', $newValue, false)");
                    if ($result !== false) {
                        $fixedCount++;
                        error_log("Auto-fix sequences: Fixed sequence {$config['seq']} from $currentValue to $newValue (max ID: $maxId)");
                    } else {
                        error_log("Auto-fix sequences: Failed to set sequence value for {$config['seq']}");
                        $errorCount++;
                    }
                }
                
            } catch (Exception $e) {
                // Log error but don't break the application
                error_log("Auto-fix sequences error for $table: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        // Log summary
        if ($fixedCount > 0) {
            error_log("Auto-fix sequences: Fixed $fixedCount sequences, $errorCount errors");
        }
        
        // Mark as fixed in session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['sequences_fixed'] = true;
        }
        
        return true;
        
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Auto-fix sequences critical error: " . $e->getMessage());
        return false;
    }
}

// Function to check if auto-fix is needed
function isAutoFixNeeded() {
    // Only run if not already fixed in this session
    if (isset($_SESSION['sequences_fixed']) && $_SESSION['sequences_fixed'] === true) {
        return false;
    }
    
    // Check if we're in a web context
    if (php_sapi_name() === 'cli') {
        return false;
    }
    
    return true;
}
?>
