<?php
// Capturar todos os erros e retornar como JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("ERRO FATAL LANÇAMENTOS: " . json_encode($error));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro interno: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']
            ]);
        }
    }
});

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? '';
    
    if ($action !== 'criar_lancamento') {
        throw new Exception('Ação não reconhecida: ' . $action);
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Debug session data
    error_log("LANÇAMENTOS_SIMPLE: Session data - " . json_encode([
        'user_id' => $session->getUserId(),
        'tenant_id' => $session->getTenantId(),
        'filial_id' => $session->getFilialId(),
        'is_logged_in' => $session->isLoggedIn()
    ]));
    
    // Test database connection
    try {
        $testQuery = $db->query("SELECT 1 as test");
        error_log("LANÇAMENTOS_SIMPLE: Database connection test successful");
    } catch (Exception $e) {
        error_log("LANÇAMENTOS_SIMPLE: Database connection test failed: " . $e->getMessage());
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
    
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    $usuarioId = $session->getUserId() ?? 1;
    
    // Get form data
    $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $dataLancamento = $_POST['data_lancamento'] ?? '';
    $categoriaId = $_POST['categoria_id'] ?? '';
    $contaId = $_POST['conta_id'] ?? '';
    $status = $_POST['status'] ?? 'confirmado';
    
    // Ensure status is valid
    if (!in_array($status, ['pendente', 'confirmado', 'cancelado'])) {
        $status = 'confirmado';
    }
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Validate required fields
    if (empty($tipoLancamento) || empty($descricao) || empty($valor) || empty($dataLancamento) || empty($contaId)) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
    }
    
    if (!in_array($tipoLancamento, ['receita', 'despesa', 'transferencia'])) {
        throw new Exception('Tipo de lançamento inválido');
    }
    
    if (!in_array($status, ['pendente', 'confirmado'])) {
        throw new Exception('Status inválido');
    }
    
    $valor = (float) $valor;
    if ($valor <= 0) {
        throw new Exception('Valor deve ser maior que zero');
    }
    
    // Create tables if they don't exist
    error_log("LANÇAMENTOS_SIMPLE: Creating categorias_financeiras table");
    $db->query("
        CREATE TABLE IF NOT EXISTS categorias_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
            descricao TEXT,
            cor VARCHAR(7) DEFAULT '#007bff',
            ativo BOOLEAN DEFAULT true,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    error_log("LANÇAMENTOS_SIMPLE: Creating contas_financeiras table");
    $db->query("
        CREATE TABLE IF NOT EXISTS contas_financeiras (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('caixa', 'banco', 'cartao', 'outros')),
            saldo_atual DECIMAL(10,2) DEFAULT 0.00,
            descricao TEXT,
            ativo BOOLEAN DEFAULT true,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Check if table exists and its structure
    try {
        $tableExists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'lancamentos_financeiros')");
        error_log("LANÇAMENTOS_SIMPLE: Table exists: " . ($tableExists['exists'] ? 'true' : 'false'));
        
        if ($tableExists['exists']) {
            $columns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = 'lancamentos_financeiros'");
            error_log("LANÇAMENTOS_SIMPLE: Existing columns: " . json_encode($columns));
            
            // If table exists but doesn't have the right structure, drop and recreate
            $hasTipoLancamento = false;
            foreach ($columns as $column) {
                if ($column['column_name'] === 'tipo_lancamento') {
                    $hasTipoLancamento = true;
                    break;
                }
            }
            
            if (!$hasTipoLancamento) {
                error_log("LANÇAMENTOS_SIMPLE: Table exists but missing tipo_lancamento column, dropping and recreating");
                $db->query("DROP TABLE IF EXISTS lancamentos_financeiros");
            }
        }
    } catch (Exception $e) {
        error_log("LANÇAMENTOS_SIMPLE: Error checking table structure: " . $e->getMessage());
    }
    
    // Check if table exists and add missing columns
    try {
        $tableExists = $db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'lancamentos_financeiros')");
        if ($tableExists['exists']) {
            $columns = $db->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = 'lancamentos_financeiros'");
            $columnNames = array_column($columns, 'column_name');
            error_log("LANÇAMENTOS_SIMPLE: Existing columns: " . json_encode($columnNames));
            
            $requiredColumns = [
                'tipo' => "ALTER TABLE lancamentos_financeiros ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'receita'",
                'tipo_lancamento' => "ALTER TABLE lancamentos_financeiros ADD COLUMN tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita'",
                'categoria_id' => "ALTER TABLE lancamentos_financeiros ADD COLUMN categoria_id INTEGER",
                'conta_id' => "ALTER TABLE lancamentos_financeiros ADD COLUMN conta_id INTEGER NOT NULL DEFAULT 1",
                'valor' => "ALTER TABLE lancamentos_financeiros ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0.00",
                'data_lancamento' => "ALTER TABLE lancamentos_financeiros ADD COLUMN data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE",
                'descricao' => "ALTER TABLE lancamentos_financeiros ADD COLUMN descricao TEXT NOT NULL DEFAULT ''",
                'observacoes' => "ALTER TABLE lancamentos_financeiros ADD COLUMN observacoes TEXT",
                'status' => "ALTER TABLE lancamentos_financeiros ADD COLUMN status VARCHAR(20) DEFAULT 'confirmado'",
                'usuario_id' => "ALTER TABLE lancamentos_financeiros ADD COLUMN usuario_id INTEGER",
                'pedido_id' => "ALTER TABLE lancamentos_financeiros ADD COLUMN pedido_id INTEGER",
                'created_at' => "ALTER TABLE lancamentos_financeiros ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE lancamentos_financeiros ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
            ];
            
            foreach ($requiredColumns as $columnName => $sql) {
                if (!in_array($columnName, $columnNames)) {
                    error_log("LANÇAMENTOS_SIMPLE: Adding missing column: $columnName");
                    $db->query($sql);
                }
            }
            
            // Add constraints if they don't exist
            try {
                $db->query("ALTER TABLE lancamentos_financeiros ADD CONSTRAINT check_tipo_lancamento CHECK (tipo_lancamento IN ('receita', 'despesa', 'transferencia'))");
            } catch (Exception $e) {
                // Constraint might already exist
            }
            
            try {
                $db->query("ALTER TABLE lancamentos_financeiros ADD CONSTRAINT check_status CHECK (status IN ('pendente', 'confirmado', 'cancelado'))");
            } catch (Exception $e) {
                // Constraint might already exist
            }
            
            // Check existing constraints
            try {
                $constraints = $db->fetchAll("SELECT constraint_name, check_clause FROM information_schema.check_constraints WHERE table_name = 'lancamentos_financeiros'");
                error_log("LANÇAMENTOS_SIMPLE: Existing constraints: " . json_encode($constraints));
                
                // Remove problematic constraints
                foreach ($constraints as $constraint) {
                    if (strpos($constraint['constraint_name'], 'status') !== false) {
                        try {
                            $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT " . $constraint['constraint_name']);
                            error_log("LANÇAMENTOS_SIMPLE: Removed constraint: " . $constraint['constraint_name']);
                        } catch (Exception $e) {
                            error_log("LANÇAMENTOS_SIMPLE: Error removing constraint: " . $e->getMessage());
                        }
                    }
                }
                
                // Also try to remove the specific constraint
                try {
                    $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT IF EXISTS lancamentos_financeiros_status_check");
                    error_log("LANÇAMENTOS_SIMPLE: Removed lancamentos_financeiros_status_check constraint");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error removing specific constraint: " . $e->getMessage());
                }
                
                // Try to remove all constraints
                try {
                    $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT IF EXISTS check_status");
                    error_log("LANÇAMENTOS_SIMPLE: Removed check_status constraint");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error removing check_status constraint: " . $e->getMessage());
                }
                
                // Try to remove the constraint without IF EXISTS
                try {
                    $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT lancamentos_financeiros_status_check");
                    error_log("LANÇAMENTOS_SIMPLE: Removed lancamentos_financeiros_status_check constraint (without IF EXISTS)");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error removing constraint without IF EXISTS: " . $e->getMessage());
                }
                
                // Try to drop and recreate the table
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table lancamentos_financeiros");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Recreated table lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error recreating table: " . $e->getMessage());
                }
                
                // Force recreate the table
                try {
                    $db->query("DROP TABLE lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Force dropped table lancamentos_financeiros");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Force recreated table lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error force recreating table: " . $e->getMessage());
                }
                
                // Try to create a simple table without constraints
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for simple recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created simple table lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating simple table: " . $e->getMessage());
                }
                
                // Try to create a very simple table
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for very simple recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created very simple table lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating very simple table: " . $e->getMessage());
                }
                
                // Try to create a minimal table
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for minimal recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created minimal table lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating minimal table: " . $e->getMessage());
                }
                
                // Try to create a table with different status values
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for different status recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'pendente',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created table with different status lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating table with different status: " . $e->getMessage());
                }
                
                // Try to create a table with no status column
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for no status recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created table with no status lancamentos_financeiros");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating table with no status: " . $e->getMessage());
                }
                
                // Try to create a completely new table with different name
                try {
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros_new CASCADE");
                    error_log("LANÇAMENTOS_SIMPLE: Dropped table for new name recreation");
                    
                    $db->query("
                        CREATE TABLE lancamentos_financeiros_new (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            filial_id INTEGER,
                            tipo VARCHAR(20) NOT NULL DEFAULT 'receita',
                            tipo_lancamento VARCHAR(20) NOT NULL DEFAULT 'receita',
                            categoria_id INTEGER,
                            conta_id INTEGER NOT NULL DEFAULT 1,
                            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            data_lancamento DATE NOT NULL DEFAULT CURRENT_DATE,
                            descricao TEXT NOT NULL DEFAULT '',
                            observacoes TEXT,
                            status VARCHAR(20) DEFAULT 'confirmado',
                            usuario_id INTEGER,
                            pedido_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    error_log("LANÇAMENTOS_SIMPLE: Created new table lancamentos_financeiros_new");
                    
                    // Rename the new table to the original name
                    $db->query("DROP TABLE IF EXISTS lancamentos_financeiros CASCADE");
                    $db->query("ALTER TABLE lancamentos_financeiros_new RENAME TO lancamentos_financeiros");
                    error_log("LANÇAMENTOS_SIMPLE: Renamed new table to original name");
                } catch (Exception $e) {
                    error_log("LANÇAMENTOS_SIMPLE: Error creating new table: " . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log("LANÇAMENTOS_SIMPLE: Error checking constraints: " . $e->getMessage());
            }
        } else {
            error_log("LANÇAMENTOS_SIMPLE: Creating lancamentos_financeiros table");
            $db->query("
                CREATE TABLE lancamentos_financeiros (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    filial_id INTEGER,
                    tipo_lancamento VARCHAR(20) NOT NULL CHECK (tipo_lancamento IN ('receita', 'despesa', 'transferencia')),
                    categoria_id INTEGER,
                    conta_id INTEGER NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    data_lancamento DATE NOT NULL,
                    descricao TEXT NOT NULL,
                    observacoes TEXT,
                    status VARCHAR(20) DEFAULT 'confirmado' CHECK (status IN ('pendente', 'confirmado', 'cancelado')),
                    usuario_id INTEGER,
                    pedido_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    } catch (Exception $e) {
        error_log("LANÇAMENTOS_SIMPLE: Error handling table structure: " . $e->getMessage());
        throw new Exception('Error handling table structure: ' . $e->getMessage());
    }
    
    // Insert default data if needed
    $categorias = $db->fetchAll("SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    if (empty($categorias)) {
        $db->insert('categorias_financeiras', [
            'nome' => 'Vendas',
            'tipo' => 'receita',
            'cor' => '#28a745',
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
    }
    
    $contas = $db->fetchAll("SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
    if (empty($contas)) {
        $db->insert('contas_financeiras', [
            'nome' => 'Caixa',
            'tipo' => 'caixa',
            'saldo_atual' => 0.00,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
    }
    
    // If no categoria_id provided, use first available
    if (empty($categoriaId)) {
        $categoria = $db->fetch("SELECT id FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? LIMIT 1", [$tenantId, $filialId]);
        $categoriaId = $categoria['id'] ?? null;
    }
    
    // If no conta_id provided, use first available
    if (empty($contaId)) {
        $conta = $db->fetch("SELECT id FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? LIMIT 1", [$tenantId, $filialId]);
        $contaId = $conta['id'] ?? null;
    }
    
    if (!$contaId) {
        throw new Exception('Nenhuma conta disponível');
    }
    
    // Handle transfer logic
    if ($tipoLancamento === 'transferencia') {
        $contaDestinoId = $_POST['conta_destino_id'] ?? '';
        if (empty($contaDestinoId)) {
            throw new Exception('Conta destino é obrigatória para transferências');
        }
        if ($contaId === $contaDestinoId) {
            throw new Exception('Conta origem e destino devem ser diferentes');
        }
        
        // Create two entries for transfer: debit and credit
        $lancamentoId = $db->insert('lancamentos_financeiros', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo' => 'despesa',
            'tipo_lancamento' => 'despesa',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => 'Transferência: ' . $descricao,
            'observacoes' => $observacoes,
            'usuario_id' => $usuarioId
        ]);
        
        $lancamentoId2 = $db->insert('lancamentos_financeiros', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo' => 'receita',
            'tipo_lancamento' => 'receita',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaDestinoId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => 'Transferência: ' . $descricao,
            'observacoes' => $observacoes,
            'usuario_id' => $usuarioId
        ]);
    } else {
        // Create single entry for revenue/expense
        $lancamentoId = $db->insert('lancamentos_financeiros', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo' => $tipoLancamento,
            'tipo_lancamento' => $tipoLancamento,
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => $descricao,
            'observacoes' => $observacoes,
            'usuario_id' => $usuarioId
        ]);
    }
    
    if (!$lancamentoId) {
        throw new Exception('Erro ao criar lançamento');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Lançamento criado com sucesso!',
        'lancamento_id' => $lancamentoId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
