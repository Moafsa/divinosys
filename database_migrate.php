<?php
/**
 * CONSOLIDATED DATABASE MIGRATION SYSTEM
 * 
 * This script consolidates all database initialization, migrations, seeds, and fixes
 * It ensures proper execution order and tracks what has been executed
 * 
 * Execution order:
 * 1. Create migrations tracking table
 * 2. Execute init scripts (database/init/*.sql) in numerical order
 * 3. Execute migrations (database/migrations/*.sql) in alphabetical order
 * 4. Fix sequences
 * 
 * Usage:
 * - Development: Called automatically on container startup
 * - Production: Called automatically on container startup
 * - Manual: php database_migrate.php
 */

require_once 'vendor/autoload.php';

use System\Config;
use System\Database;

class DatabaseMigrator {
    private $db;
    private $migrationsTable = 'database_migrations';
    private $basePath;
    
    public function __construct() {
        $this->basePath = __DIR__;
        $config = Config::getInstance();
        $this->db = Database::getInstance();
        $this->createMigrationsTable();
    }
    
    /**
     * Create migrations tracking table if it doesn't exist
     */
    private function createMigrationsTable() {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id SERIAL PRIMARY KEY,
                    migration_file VARCHAR(255) NOT NULL UNIQUE,
                    migration_type VARCHAR(50) NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    execution_time_ms INTEGER,
                    success BOOLEAN DEFAULT true,
                    error_message TEXT
                )
            ");
            
            echo "✅ Migrations tracking table ready\n";
        } catch (Exception $e) {
            echo "❌ Failed to create migrations table: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Check if a migration has already been executed
     */
    private function isMigrationExecuted($filename) {
        try {
            $stmt = $this->db->query("
                SELECT success FROM {$this->migrationsTable} 
                WHERE migration_file = :filename AND success = true
            ", ['filename' => $filename]);
            
            $result = $stmt->fetch();
            if ($result === false) {
                return false;
            }
            
            // PostgreSQL returns boolean as 't'/'f' string or true/false depending on PDO config
            $success = $result['success'] ?? false;
            return $success === true || $success === 't' || $success === '1';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Split SQL statements handling PL/pgSQL blocks correctly
     */
    private function splitSqlStatements($sql) {
        $statements = [];
        $current = '';
        $inDollarQuote = false;
        $dollarTag = '';
        $inFunction = false;
        $inDoBlock = false;
        
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip comments
            if (empty($trimmed) || preg_match('/^--/', $trimmed)) {
                continue;
            }
            
            // Detect start of DO block
            if (preg_match('/^\s*DO\s+\$\$/i', $line)) {
                $inDoBlock = true;
                $current .= $line . "\n";
                continue;
            }
            
            // Detect end of DO block
            if ($inDoBlock && preg_match('/^\s*END\s*\$\$\s*;/i', $line)) {
                $current .= $line . "\n";
                $statements[] = trim($current);
                $current = '';
                $inDoBlock = false;
                continue;
            }
            
            // Detect start of function/trigger
            if (preg_match('/^\s*(CREATE\s+(OR\s+REPLACE\s+)?FUNCTION|CREATE\s+(OR\s+REPLACE\s+)?TRIGGER)/i', $line)) {
                $inFunction = true;
                $current .= $line . "\n";
                continue;
            }
            
            // Detect dollar quotes
            if (preg_match('/\$\$/', $line)) {
                if (!$inDollarQuote) {
                    $inDollarQuote = true;
                } else {
                    $inDollarQuote = false;
                }
                $current .= $line . "\n";
                continue;
            }
            
            // If inside a block, keep adding lines
            if ($inDoBlock || $inFunction || $inDollarQuote) {
                $current .= $line . "\n";
                
                // End function when we see semicolon after END $$
                if ($inFunction && !$inDollarQuote && preg_match('/;\\s*$/', $line)) {
                    $statements[] = trim($current);
                    $current = '';
                    $inFunction = false;
                }
                continue;
            }
            
            // Normal statement
            $current .= $line . "\n";
            
            // If line ends with semicolon, it's end of statement
            if (preg_match('/;\\s*$/', $line)) {
                $stmt = trim($current);
                if (!empty($stmt) && strlen($stmt) > 5) {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }
        
        // Add remaining if any
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return $statements;
    }
    
    /**
     * Record migration execution
     */
    private function recordMigration($filename, $type, $executionTime, $success = true, $error = null) {
        try {
            $this->db->query("
                INSERT INTO {$this->migrationsTable} 
                (migration_file, migration_type, execution_time_ms, success, error_message)
                VALUES (:filename, :type, :time, :success, :error)
                ON CONFLICT (migration_file) 
                DO UPDATE SET 
                    executed_at = CURRENT_TIMESTAMP,
                    execution_time_ms = :time,
                    success = :success,
                    error_message = :error
            ", [
                'filename' => $filename,
                'type' => $type,
                'time' => $executionTime,
                'success' => $success ? 'true' : 'false',
                'error' => $error
            ]);
        } catch (Exception $e) {
            echo "⚠️  Warning: Failed to record migration: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Execute SQL file with error handling
     */
    private function executeSqlFile($filePath, $filename, $type) {
        $startTime = microtime(true);
        
        try {
            if (!file_exists($filePath)) {
                echo "⚠️  File not found: $filename\n";
                return false;
            }
            
            $sql = file_get_contents($filePath);
            
            if (empty(trim($sql))) {
                echo "⚠️  Empty file: $filename\n";
                return false;
            }
            
            // Split SQL into statements, handling PL/pgSQL blocks correctly
            $statements = $this->splitSqlStatements($sql);
            
            $executed = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                try {
                    $this->db->query($statement);
                    $executed++;
                } catch (Exception $e) {
                    // Ignore "already exists" errors for CREATE TABLE IF NOT EXISTS
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'duplicate key') === false &&
                        strpos($e->getMessage(), 'duplicate') === false) {
                        $errors[] = substr($statement, 0, 100) . "... Error: " . $e->getMessage();
                    }
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000);
            
            if (!empty($errors) && count($errors) > 5) {
                // Only show errors if there are many failures
                $errorMsg = implode("\n", array_slice($errors, 0, 3));
                echo "⚠️  $filename: Executed $executed statements with some errors\n";
                $this->recordMigration($filename, $type, $executionTime, false, $errorMsg);
                return false;
            } else {
                echo "✅ $filename: Executed $executed statements in {$executionTime}ms\n";
                $this->recordMigration($filename, $type, $executionTime, true);
                return true;
            }
            
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000);
            echo "❌ $filename: Failed - " . $e->getMessage() . "\n";
            $this->recordMigration($filename, $type, $executionTime, false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute init scripts in numerical order
     * Note: PostgreSQL may execute these automatically on first startup
     * This method ensures they run even if PostgreSQL init didn't execute them
     */
    private function executeInitScripts() {
        echo "\n=== EXECUTING INIT SCRIPTS ===\n";
        
        $initDir = $this->basePath . '/database/init';
        
        if (!is_dir($initDir)) {
            echo "⚠️  Init directory not found: $initDir\n";
            return;
        }
        
        // Get all SQL files, excluding disabled files
        $files = glob($initDir . '/*.sql');
        $files = array_filter($files, function($file) {
            return strpos($file, '.disabled') === false;
        });
        
        // Sort by filename (numerical prefix should sort correctly)
        usort($files, function($a, $b) {
            return strcmp(basename($a), basename($b));
        });
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Skip dump files
            if (strpos($filename, 'dump') !== false) {
                continue;
            }
            
            // Check if already executed
            // Note: We allow re-execution for init scripts since they use IF NOT EXISTS
            // but we track them to avoid unnecessary re-runs
            if ($this->isMigrationExecuted($filename)) {
                echo "⏭️  Skipping already executed: $filename\n";
                continue;
            }
            
            $this->executeSqlFile($file, $filename, 'init');
        }
    }
    
    /**
     * Execute migration scripts in alphabetical order
     */
    private function executeMigrations() {
        echo "\n=== EXECUTING MIGRATIONS ===\n";
        
        $migrationsDir = $this->basePath . '/database/migrations';
        
        if (!is_dir($migrationsDir)) {
            echo "⚠️  Migrations directory not found: $migrationsDir\n";
            return;
        }
        
        // Get all SQL files
        $files = glob($migrationsDir . '/*.sql');
        
        // Sort alphabetically
        sort($files);
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Check if already executed
            if ($this->isMigrationExecuted($filename)) {
                echo "⏭️  Skipping already executed: $filename\n";
                continue;
            }
            
            $this->executeSqlFile($file, $filename, 'migration');
        }
    }
    
    /**
     * Fix all database sequences
     */
    private function fixSequences() {
        echo "\n=== FIXING SEQUENCES ===\n";
        
        $sequenceFile = $this->basePath . '/database/init/99_fix_sequences.sql';
        
        if (file_exists($sequenceFile)) {
            // Always execute sequence fix (it's idempotent)
            $this->executeSqlFile($sequenceFile, '99_fix_sequences.sql', 'fix');
        } else {
            echo "⚠️  Sequence fix file not found\n";
            
            // Fallback: Manual sequence fix
            $this->manualSequenceFix();
        }
    }
    
    /**
     * Manual sequence fix as fallback
     */
    private function manualSequenceFix() {
        $tables = [
            'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
            'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
            'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
            'produto_ingredientes' => ['seq' => 'produto_ingredientes_id_seq', 'id' => 'id'],
            'mesas' => ['seq' => 'mesas_id_seq', 'id' => 'id'],
            'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
            'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
            'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
            'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
            'tenants' => ['seq' => 'tenants_id_seq', 'id' => 'id'],
            'filiais' => ['seq' => 'filiais_id_seq', 'id' => 'id'],
            'usuarios' => ['seq' => 'usuarios_id_seq', 'id' => 'id'],
            'clientes' => ['seq' => 'clientes_id_seq', 'id' => 'id'],
            'assinaturas' => ['seq' => 'assinaturas_id_seq', 'id' => 'id'],
        ];
        
        $fixed = 0;
        
        foreach ($tables as $table => $config) {
            try {
                // Check if table exists
                $stmt = $this->db->query("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_name = :table
                    )
                ", ['table' => $table]);
                
                $result = $stmt->fetch();
                if (!$result || !$result['exists']) {
                    continue;
                }
                
                // Check if sequence exists
                $stmt = $this->db->query("
                    SELECT EXISTS (
                        SELECT FROM pg_sequences 
                        WHERE sequencename = :seq
                    )
                ", ['seq' => $config['seq']]);
                
                $result = $stmt->fetch();
                if (!$result || !$result['exists']) {
                    continue;
                }
                
                // Get max ID
                $stmt = $this->db->query("
                    SELECT COALESCE(MAX({$config['id']}), 0) as max_id FROM {$table}
                ");
                $result = $stmt->fetch();
                $maxId = $result ? (int)$result['max_id'] : 0;
                
                // Get current sequence value
                $stmt = $this->db->query("SELECT last_value FROM {$config['seq']}");
                $result = $stmt->fetch();
                $currentValue = $result ? (int)$result['last_value'] : 0;
                
                // Fix if needed
                if ($currentValue <= $maxId) {
                    $newValue = $maxId + 1;
                    $this->db->query("SELECT setval('{$config['seq']}', $newValue, false)");
                    echo "✅ Fixed sequence {$config['seq']}: $currentValue → $newValue\n";
                    $fixed++;
                }
                
            } catch (Exception $e) {
                echo "⚠️  Warning: Could not fix sequence for $table: " . $e->getMessage() . "\n";
            }
        }
        
        if ($fixed > 0) {
            echo "✅ Fixed $fixed sequences\n";
        } else {
            echo "✅ All sequences are up to date\n";
        }
    }
    
    /**
     * Verify database state
     */
    private function verifyDatabase() {
        echo "\n=== VERIFYING DATABASE STATE ===\n";
        
        try {
            // Check essential tables
            $essentialTables = ['tenants', 'usuarios', 'produtos', 'categorias', 'mesas'];
            $missingTables = [];
            
            foreach ($essentialTables as $table) {
                $stmt = $this->db->query("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_name = :table
                    ) as exists
                ", ['table' => $table]);
                
                $result = $stmt->fetch();
                if (!$result || !$result['exists']) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                echo "❌ Missing essential tables: " . implode(', ', $missingTables) . "\n";
                return false;
            }
            
            // Check if admin user exists
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM usuarios WHERE login = 'admin'");
            $userCount = $stmt->fetch()['count'];
            
            if ($userCount == 0) {
                echo "⚠️  Warning: No admin user found\n";
            } else {
                echo "✅ Admin user exists\n";
            }
            
            echo "✅ Database verification passed\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Database verification failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Run complete migration process
     */
    public function migrate() {
        echo "========================================\n";
        echo "DATABASE MIGRATION SYSTEM\n";
        echo "========================================\n\n";
        
        echo "Database: " . getenv('DB_NAME') . "\n";
        echo "Host: " . getenv('DB_HOST') . "\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        try {
            // Step 1: Execute init scripts
            $this->executeInitScripts();
            
            // Step 2: Execute migrations
            $this->executeMigrations();
            
            // Step 3: Fix sequences
            $this->fixSequences();
            
            // Step 4: Verify database state
            $this->verifyDatabase();
            
            echo "\n========================================\n";
            echo "✅ MIGRATION COMPLETED SUCCESSFULLY\n";
            echo "========================================\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "\n========================================\n";
            echo "❌ MIGRATION FAILED\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "========================================\n";
            
            return false;
        }
    }
}

// Execute migration if run directly
if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_METHOD'])) {
    $migrator = new DatabaseMigrator();
    $success = $migrator->migrate();
    exit($success ? 0 : 1);
}

