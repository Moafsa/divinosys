<?php
/**
 * Safe Migration Runner for Production
 * Executes all new migrations created on 2025-11-18
 * All migrations use IF NOT EXISTS to prevent data loss
 */

require_once __DIR__ . '/vendor/autoload.php';

use System\Database;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log file
$logFile = __DIR__ . '/logs/migrations_' . date('Y-m-d_His') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message, $type = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

try {
    logMessage("=== Starting Safe Migration Process ===");
    logMessage("Log file: $logFile");
    
    $db = Database::getInstance();
    logMessage("Database connection established");
    
    // List of migrations to execute (in order)
    $migrations = [
        [
            'name' => 'add_asaas_subscription_id_to_assinaturas',
            'file' => __DIR__ . '/database/migrations/add_asaas_subscription_id_to_assinaturas.sql',
            'check_column' => ['table' => 'assinaturas', 'column' => 'asaas_subscription_id']
        ],
        [
            'name' => 'add_cidade_estado_cep_to_tables',
            'file' => __DIR__ . '/database/migrations/add_cidade_estado_cep_to_tables.sql',
            'check_column' => ['table' => 'tenants', 'column' => 'cidade']
        ],
        [
            'name' => 'add_max_filiais_to_planos',
            'file' => __DIR__ . '/database/migrations/add_max_filiais_to_planos.sql',
            'check_column' => ['table' => 'planos', 'column' => 'max_filiais']
        ],
        [
            'name' => 'add_valor_pago_to_pagamentos',
            'file' => __DIR__ . '/database/migrations/add_valor_pago_to_pagamentos.sql',
            'check_column' => ['table' => 'pagamentos_assinaturas', 'column' => 'valor_pago']
        ],
        [
            'name' => 'add_valor_pago_to_pagamentos_assinaturas',
            'file' => __DIR__ . '/database/migrations/add_valor_pago_to_pagamentos_assinaturas.sql',
            'check_column' => ['table' => 'pagamentos_assinaturas', 'column' => 'valor_pago']
        ],
        [
            'name' => 'create_pagamentos_funcionarios',
            'file' => __DIR__ . '/database/migrations/create_pagamentos_funcionarios.sql',
            'check_table' => 'pagamentos_funcionarios'
        ],
        [
            'name' => 'populate_categorias_contas_financeiras',
            'file' => __DIR__ . '/database/migrations/populate_categorias_contas_financeiras.sql',
            'check_table' => 'categorias_financeiras'
        ]
    ];
    
    $results = [];
    $executed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($migrations as $migration) {
        logMessage("--- Processing: {$migration['name']} ---");
        
        // Check if migration already applied
        $alreadyApplied = false;
        
        if (isset($migration['check_column'])) {
            $table = $migration['check_column']['table'];
            $column = $migration['check_column']['column'];
            try {
                $check = $db->query("
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = '$table' AND column_name = '$column'
                ");
                if ($check && count($check) > 0) {
                    $alreadyApplied = true;
                    logMessage("Column $table.$column already exists - SKIPPING");
                }
            } catch (Exception $e) {
                logMessage("Check failed (will proceed): " . $e->getMessage(), 'WARN');
            }
        } elseif (isset($migration['check_table'])) {
            $table = $migration['check_table'];
            try {
                $check = $db->query("
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_name = '$table'
                ");
                if ($check && count($check) > 0) {
                    // Table exists, but we still need to check if migration data was populated
                    // For populate migrations, we'll execute anyway (it uses NOT EXISTS)
                    if ($migration['name'] === 'populate_categorias_contas_financeiras') {
                        $alreadyApplied = false; // Will check with NOT EXISTS
                    } else {
                        $alreadyApplied = true;
                        logMessage("Table $table already exists - SKIPPING");
                    }
                }
            } catch (Exception $e) {
                logMessage("Check failed (will proceed): " . $e->getMessage(), 'WARN');
            }
        }
        
        if ($alreadyApplied) {
            $skipped++;
            $results[] = [
                'migration' => $migration['name'],
                'status' => 'skipped',
                'reason' => 'Already applied'
            ];
            continue;
        }
        
        // Check if file exists
        if (!file_exists($migration['file'])) {
            logMessage("Migration file not found: {$migration['file']}", 'ERROR');
            $errors++;
            $results[] = [
                'migration' => $migration['name'],
                'status' => 'error',
                'reason' => 'File not found'
            ];
            continue;
        }
        
        // Read and execute migration
        try {
            $sql = file_get_contents($migration['file']);
            
            // Split SQL into statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $statementsExecuted = 0;
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                
                try {
                    $db->query($statement);
                    $statementsExecuted++;
                } catch (Exception $e) {
                    // Ignore "already exists" errors (safe)
                    $errorMsg = $e->getMessage();
                    if (strpos($errorMsg, 'already exists') !== false || 
                        strpos($errorMsg, 'duplicate') !== false ||
                        strpos($errorMsg, 'IF NOT EXISTS') !== false) {
                        logMessage("Statement skipped (already exists): " . substr($statement, 0, 60) . "...", 'INFO');
                    } else {
                        logMessage("Statement error: " . $errorMsg, 'ERROR');
                        logMessage("Statement: " . substr($statement, 0, 200), 'ERROR');
                        throw $e;
                    }
                }
            }
            
            logMessage("Migration executed successfully ($statementsExecuted statements)");
            $executed++;
            $results[] = [
                'migration' => $migration['name'],
                'status' => 'success',
                'statements' => $statementsExecuted
            ];
            
        } catch (Exception $e) {
            logMessage("Migration failed: " . $e->getMessage(), 'ERROR');
            logMessage("Trace: " . $e->getTraceAsString(), 'ERROR');
            $errors++;
            $results[] = [
                'migration' => $migration['name'],
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Summary
    logMessage("=== Migration Process Complete ===");
    logMessage("Executed: $executed");
    logMessage("Skipped: $skipped");
    logMessage("Errors: $errors");
    
    echo "\n=== SUMMARY ===\n";
    echo "Executed: $executed\n";
    echo "Skipped: $skipped\n";
    echo "Errors: $errors\n";
    echo "Log file: $logFile\n\n";
    
    // Return results as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $errors === 0,
        'executed' => $executed,
        'skipped' => $skipped,
        'errors' => $errors,
        'results' => $results,
        'log_file' => $logFile
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'FATAL');
    logMessage("Trace: " . $e->getTraceAsString(), 'FATAL');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log_file' => $logFile ?? 'unknown'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

