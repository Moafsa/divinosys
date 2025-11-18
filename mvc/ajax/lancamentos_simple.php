<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    // Debug: Log all received data
    error_log('LANCAMENTOS_SIMPLE: ========== REQUEST START ==========');
    error_log('LANCAMENTOS_SIMPLE: REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    error_log('LANCAMENTOS_SIMPLE: CONTENT_TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));
    error_log('LANCAMENTOS_SIMPLE: POST data received: ' . json_encode($_POST));
    error_log('LANCAMENTOS_SIMPLE: POST count: ' . count($_POST));
    error_log('LANCAMENTOS_SIMPLE: FILES count: ' . count($_FILES));
    
    $action = $_POST['action'] ?? '';
    error_log('LANCAMENTOS_SIMPLE: Action: ' . $action);
    
    if ($action !== 'criar_lancamento' && $action !== 'salvar_rascunho') {
        throw new Exception('Ação não reconhecida: ' . $action);
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    if (!$session->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }
    
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    $usuarioId = $session->getUserId() ?? 1;
    
    // Fix atualizar_saldo_conta function to use tipo_lancamento instead of tipo_movimentacao
    try {
        error_log('LANCAMENTOS_SIMPLE: Fixing atualizar_saldo_conta function');
        $db->query("
            CREATE OR REPLACE FUNCTION atualizar_saldo_conta()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.tipo_lancamento = 'receita' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual + NEW.valor
                        WHERE id = NEW.conta_id;
                    ELSIF NEW.tipo_lancamento = 'despesa' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual - NEW.valor
                        WHERE id = NEW.conta_id;
                    END IF;
                ELSIF TG_OP = 'UPDATE' THEN
                    -- Reverter saldo anterior
                    IF OLD.tipo_lancamento = 'receita' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual - OLD.valor
                        WHERE id = OLD.conta_id;
                    ELSIF OLD.tipo_lancamento = 'despesa' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual + OLD.valor
                        WHERE id = OLD.conta_id;
                    END IF;
                    
                    -- Aplicar novo saldo
                    IF NEW.tipo_lancamento = 'receita' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual + NEW.valor
                        WHERE id = NEW.conta_id;
                    ELSIF NEW.tipo_lancamento = 'despesa' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual - NEW.valor
                        WHERE id = NEW.conta_id;
                    END IF;
                ELSIF TG_OP = 'DELETE' THEN
                    IF OLD.tipo_lancamento = 'receita' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual - OLD.valor
                        WHERE id = OLD.conta_id;
                    ELSIF OLD.tipo_lancamento = 'despesa' THEN
                        UPDATE contas_financeiras 
                        SET saldo_atual = saldo_atual + OLD.valor
                        WHERE id = OLD.conta_id;
                    END IF;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            \$\$ LANGUAGE plpgsql;
        ");
        error_log('LANCAMENTOS_SIMPLE: Function atualizar_saldo_conta fixed successfully');
    } catch (Exception $e) {
        error_log('LANCAMENTOS_SIMPLE: Error fixing atualizar_saldo_conta function: ' . $e->getMessage());
        // Continue anyway
    }
    
    // Ensure data_lancamento column exists
    try {
        $columnExists = $db->fetch("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'lancamentos_financeiros' 
            AND column_name = 'data_lancamento'
        ");
        
        if (empty($columnExists)) {
            error_log('LANCAMENTOS_SIMPLE: Adding data_lancamento column');
            $db->query("ALTER TABLE lancamentos_financeiros ADD COLUMN data_lancamento DATE");
        }
    } catch (Exception $e) {
        error_log('LANCAMENTOS_SIMPLE: Error checking/adding data_lancamento column: ' . $e->getMessage());
        // Continue anyway - might use data_vencimento as fallback
    }
    
    // Fix status constraint - drop all status constraints and recreate with correct values
    try {
        // Get all constraints on status column
        $constraints = $db->fetchAll("
            SELECT tc.constraint_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage ccu 
                ON tc.constraint_name = ccu.constraint_name
            WHERE tc.table_name = 'lancamentos_financeiros'
            AND ccu.column_name = 'status'
            AND tc.constraint_type = 'CHECK'
        ");
        
        error_log('LANCAMENTOS_SIMPLE: Found status constraints: ' . json_encode($constraints));
        
        // Drop all existing status constraints
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['constraint_name'];
            try {
                error_log("LANCAMENTOS_SIMPLE: Dropping constraint: {$constraintName}");
                $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");
            } catch (Exception $e) {
                error_log("LANCAMENTOS_SIMPLE: Error dropping constraint {$constraintName}: " . $e->getMessage());
            }
        }
        
        // Add correct constraint
        try {
            error_log('LANCAMENTOS_SIMPLE: Adding correct status constraint');
            $db->query("
                ALTER TABLE lancamentos_financeiros 
                ADD CONSTRAINT lancamentos_financeiros_status_check 
                CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado'))
            ");
        } catch (Exception $e) {
            // Constraint might already exist, that's okay
            error_log('LANCAMENTOS_SIMPLE: Constraint might already exist: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        error_log('LANCAMENTOS_SIMPLE: Error fixing status constraint: ' . $e->getMessage());
        // Continue anyway - will fail with better error message
    }
    
    // Get form data
    $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $dataLancamento = $_POST['data_lancamento'] ?? '';
    $categoriaId = $_POST['categoria_id'] ?? '';
    $contaId = $_POST['conta_id'] ?? '';
    $status = $_POST['status'] ?? 'confirmado';
    $observacoes = $_POST['observacoes'] ?? '';
    $pedidoId = $_POST['pedido_id'] ?? null;
    
    // Debug: Log parsed values
    error_log('LANCAMENTOS_SIMPLE: Parsed values - tipo: [' . $tipoLancamento . '], descricao: [' . $descricao . '], valor: [' . $valor . '], data: [' . $dataLancamento . '], categoria: [' . $categoriaId . '], conta: [' . $contaId . ']');
    
    // Validate required fields
    $missingFields = [];
    if (empty($tipoLancamento)) $missingFields[] = 'tipo_lancamento';
    if (empty($descricao)) $missingFields[] = 'descricao';
    if (empty($valor)) $missingFields[] = 'valor';
    if (empty($dataLancamento)) $missingFields[] = 'data_lancamento';
    if (empty($contaId)) $missingFields[] = 'conta_id';
    
    if (!empty($missingFields)) {
        error_log('LANCAMENTOS_SIMPLE: Missing fields: ' . implode(', ', $missingFields));
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos. Campos faltando: ' . implode(', ', $missingFields));
    }
    
    if (!in_array($tipoLancamento, ['receita', 'despesa', 'transferencia'])) {
        throw new Exception('Tipo de lançamento inválido');
    }
    
    // Map status values to match database constraint
    // Database accepts: 'pendente', 'pago', 'vencido', 'cancelado'
    // Frontend sends: 'pendente', 'confirmado'
    $statusMap = [
        'pendente' => 'pendente',
        'confirmado' => 'pago',  // Map 'confirmado' to 'pago' for database
        'pago' => 'pago',
        'vencido' => 'vencido',
        'cancelado' => 'cancelado'
    ];
    
    // Validate and map status
    if (!in_array($status, ['pendente', 'confirmado', 'pago', 'vencido', 'cancelado'])) {
        $status = 'pendente';
    }
    
    // Map status to database-compatible value
    $status = $statusMap[$status] ?? 'pendente';
    
    $valor = (float) $valor;
    if ($valor <= 0) {
        throw new Exception('Valor deve ser maior que zero');
    }
    
    // If no categoria_id provided, use first available or create default
    if (empty($categoriaId)) {
        $categoria = $db->fetch(
            "SELECT id FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? AND tipo = ? AND ativo = true LIMIT 1",
            [$tenantId, $filialId, $tipoLancamento]
        );
        $categoriaId = $categoria['id'] ?? null;
        
        // If still no category, create a default one
        if (!$categoriaId) {
            $categoriaId = $db->insert('categorias_financeiras', [
                'nome' => $tipoLancamento === 'receita' ? 'Vendas' : 'Despesas Gerais',
                'tipo' => $tipoLancamento === 'transferencia' ? 'despesa' : $tipoLancamento,
                'cor' => $tipoLancamento === 'receita' ? '#28a745' : '#dc3545',
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true
            ]);
        }
    }
    
    // Verify category exists
    $categoria = $db->fetch(
        "SELECT * FROM categorias_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
        [$categoriaId, $tenantId, $filialId]
    );
    
    if (!$categoria) {
        throw new Exception('Categoria não encontrada ou inativa');
    }
    
    // Verify account exists
    $conta = $db->fetch(
        "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
        [$contaId, $tenantId, $filialId]
    );
    
    if (!$conta) {
        throw new Exception('Conta não encontrada ou inativa');
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
            'tipo_lancamento' => 'despesa',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => 'Transferência: ' . $descricao,
            'observacoes' => $observacoes,
            'status' => $status,
            'usuario_id' => $usuarioId,
            'pedido_id' => $pedidoId
        ]);
        
        $lancamentoId2 = $db->insert('lancamentos_financeiros', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo_lancamento' => 'receita',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaDestinoId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => 'Transferência: ' . $descricao,
            'observacoes' => $observacoes,
            'status' => $status,
            'usuario_id' => $usuarioId,
            'pedido_id' => $pedidoId
        ]);
    } else {
        // Create single entry for revenue/expense
        $lancamentoId = $db->insert('lancamentos_financeiros', [
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo_lancamento' => $tipoLancamento,
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'valor' => $valor,
            'data_lancamento' => $dataLancamento,
            'descricao' => $descricao,
            'observacoes' => $observacoes,
            'status' => $status,
            'usuario_id' => $usuarioId,
            'pedido_id' => $pedidoId
        ]);
    }
    
    if (!$lancamentoId) {
        throw new Exception('Erro ao criar lançamento');
    }
    
    // Update account balance if status is 'pago' (paid/confirmed)
    if ($status === 'pago') {
        if ($tipoLancamento === 'transferencia') {
            // Debit from source account
            $novoSaldoOrigem = $conta['saldo_atual'] - $valor;
            $db->update('contas_financeiras', 
                ['saldo_atual' => $novoSaldoOrigem],
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$contaId, $tenantId, $filialId]
            );
            
            // Credit to destination account
            $contaDestino = $db->fetch(
                "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$contaDestinoId, $tenantId, $filialId]
            );
            if ($contaDestino) {
                $novoSaldoDestino = $contaDestino['saldo_atual'] + $valor;
                $db->update('contas_financeiras', 
                    ['saldo_atual' => $novoSaldoDestino],
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$contaDestinoId, $tenantId, $filialId]
                );
            }
        } else {
            $novoSaldo = $tipoLancamento === 'receita' ? 
                $conta['saldo_atual'] + $valor : 
                $conta['saldo_atual'] - $valor;
            
            $db->update('contas_financeiras', 
                ['saldo_atual' => $novoSaldo],
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$contaId, $tenantId, $filialId]
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Lançamento criado com sucesso!',
        'lancamento_id' => $lancamentoId
    ]);
    
} catch (Exception $e) {
    error_log('LANCAMENTOS_SIMPLE: Exception caught: ' . $e->getMessage());
    error_log('LANCAMENTOS_SIMPLE: Stack trace: ' . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log('LANCAMENTOS_SIMPLE: Fatal error: ' . $e->getMessage());
    error_log('LANCAMENTOS_SIMPLE: Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
