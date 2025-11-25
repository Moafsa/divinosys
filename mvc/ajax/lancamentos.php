<?php
// Iniciar output buffering para capturar qualquer output indesejado
ob_start();

// Desabilitar exibição de erros para não quebrar o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => __DIR__ . '/../../system/',
        'App\\' => __DIR__ . '/../../app/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Simples e direto - usar require_once
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (!$action) {
        throw new \Exception('Ação não especificada');
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Verificar autenticação
    if (!$session->isLoggedIn()) {
        throw new \Exception('Usuário não autenticado');
    }
    
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    $usuarioId = $session->getUserId();
    
    switch ($action) {
        case 'criar_lancamento':
            // Debug log
            error_log("LANÇAMENTOS: Creating financial entry");
            error_log("LANÇAMENTOS: POST data: " . json_encode($_POST));
            
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            $categoriaId = $_POST['categoria_id'] ?? '';
            $contaId = $_POST['conta_id'] ?? '';
            $status = $_POST['status'] ?? 'confirmado';
            $observacoes = $_POST['observacoes'] ?? '';
            
            error_log("LANÇAMENTOS: Parsed values - tipo: $tipoLancamento, descricao: $descricao, valor: $valor, data: $dataLancamento, categoria: $categoriaId, conta: $contaId, status: $status");
            
            // Validações
            if (empty($tipoLancamento) || empty($descricao) || empty($valor) || empty($dataLancamento) || empty($categoriaId) || empty($contaId)) {
                throw new \Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($tipoLancamento, ['receita', 'despesa'])) {
                throw new \Exception('Tipo de lançamento inválido');
            }
            
            if (!in_array($status, ['pendente', 'confirmado'])) {
                throw new \Exception('Status inválido');
            }
            
            $valor = (float) $valor;
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            // Verificar se categoria e conta existem
            $categoria = $db->fetch(
                "SELECT * FROM categorias_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                [$categoriaId, $tenantId, $filialId]
            );
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada ou inativa');
            }
            
            $conta = $db->fetch(
                "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                [$contaId, $tenantId, $filialId]
            );
            
            if (!$conta) {
                throw new \Exception('Conta não encontrada ou inativa');
            }
            
            // Criar lançamento
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
                'usuario_id' => $usuarioId
            ]);
            
            if (!$lancamentoId) {
                throw new \Exception('Erro ao criar lançamento');
            }
            
            // Atualizar saldo da conta se status for confirmado
            if ($status === 'confirmado') {
                // Converter valores para float antes de fazer operações
                $saldoAtual = (float) $conta['saldo_atual'];
                $valorFloat = (float) $valor;
                
                $novoSaldo = $tipoLancamento === 'receita' ? 
                    $saldoAtual + $valorFloat : 
                    $saldoAtual - $valorFloat;
                
                $db->update('contas_financeiras', 
                    ['saldo_atual' => $novoSaldo],
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$contaId, $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento criado com sucesso!',
                'lancamento_id' => $lancamentoId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'buscar_lancamento':
            // Limpar buffer antes de enviar resposta
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new \Exception('ID do lançamento é obrigatório');
            }
            
            $lancamento = $db->fetch(
                "SELECT * FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$id, $tenantId, $filialId]
            );
            
            if (!$lancamento) {
                throw new \Exception('Lançamento não encontrado');
            }
            
            // Limpar buffer novamente antes de enviar JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'lancamento' => $lancamento
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'atualizar_lancamento':
            // Limpar buffer antes de processar
            if (ob_get_level() > 0) {
                ob_clean();
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
                    error_log('LANCAMENTOS: Adding data_lancamento column');
                    $db->query("ALTER TABLE lancamentos_financeiros ADD COLUMN data_lancamento DATE");
                }
            } catch (\Exception $e) {
                error_log('LANCAMENTOS: Error checking/adding data_lancamento column: ' . $e->getMessage());
            }
            
            // Fix atualizar_saldo_conta function and status constraint
            try {
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
            } catch (\Exception $e) {
                error_log("Erro ao corrigir função atualizar_saldo_conta: " . $e->getMessage());
            }
            
            // Fix status constraint
            try {
                $constraints = $db->fetchAll("
                    SELECT tc.constraint_name
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.constraint_column_usage ccu 
                        ON tc.constraint_name = ccu.constraint_name
                    WHERE tc.table_name = 'lancamentos_financeiros'
                    AND ccu.column_name = 'status'
                    AND tc.constraint_type = 'CHECK'
                ");
                
                foreach ($constraints as $constraint) {
                    $constraintName = $constraint['constraint_name'];
                    try {
                        $db->query("ALTER TABLE lancamentos_financeiros DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");
                    } catch (\Exception $e) {
                        error_log("Erro ao remover constraint {$constraintName}: " . $e->getMessage());
                    }
                }
                
                $db->query("
                    ALTER TABLE lancamentos_financeiros 
                    ADD CONSTRAINT lancamentos_financeiros_status_check 
                    CHECK (status IN ('pendente', 'pago', 'vencido', 'cancelado'))
                ");
            } catch (\Exception $e) {
                error_log("Erro ao corrigir constraint de status: " . $e->getMessage());
            }
            
            // PRIMEIRO: Verificar e criar colunas de recorrência ANTES de qualquer operação
            try {
                $colunasExistentes = $db->fetchAll(
                    "SELECT column_name 
                     FROM information_schema.columns 
                     WHERE table_name = 'lancamentos_financeiros' 
                     AND column_name IN ('recorrencia', 'data_fim_recorrencia', 'data_lancamento')"
                );
                
                $colunasNomes = array_column($colunasExistentes, 'column_name');
                
                // Adicionar coluna recorrencia se não existir
                if (!in_array('recorrencia', $colunasNomes)) {
                    try {
                        $db->query("
                            ALTER TABLE lancamentos_financeiros 
                            ADD COLUMN recorrencia VARCHAR(20) 
                            CHECK (recorrencia IN ('nenhuma', 'diaria', 'semanal', 'mensal', 'anual'))
                        ");
                        error_log("Coluna 'recorrencia' criada com sucesso");
                    } catch (\Exception $e) {
                        error_log("Erro ao criar coluna recorrencia: " . $e->getMessage());
                    }
                }
                
                // Adicionar coluna data_fim_recorrencia se não existir
                if (!in_array('data_fim_recorrencia', $colunasNomes)) {
                    try {
                        $db->query("
                            ALTER TABLE lancamentos_financeiros 
                            ADD COLUMN data_fim_recorrencia DATE
                        ");
                        error_log("Coluna 'data_fim_recorrencia' criada com sucesso");
                    } catch (\Exception $e) {
                        error_log("Erro ao criar coluna data_fim_recorrencia: " . $e->getMessage());
                    }
                }
                
                // Adicionar coluna data_lancamento se não existir
                if (!in_array('data_lancamento', $colunasNomes)) {
                    try {
                        $db->query("
                            ALTER TABLE lancamentos_financeiros 
                            ADD COLUMN data_lancamento DATE
                        ");
                        error_log("Coluna 'data_lancamento' criada com sucesso");
                    } catch (\Exception $e) {
                        error_log("Erro ao criar coluna data_lancamento: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                error_log("Erro ao verificar/criar colunas de recorrência: " . $e->getMessage());
            }
            
            $id = $_POST['id'] ?? '';
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            
            // Tratar campos opcionais - converter string "null" para null real
            $categoriaId = isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '' && $_POST['categoria_id'] !== 'null' ? $_POST['categoria_id'] : null;
            $contaId = $_POST['conta_id'] ?? '';
            $contaDestinoId = isset($_POST['conta_destino_id']) && $_POST['conta_destino_id'] !== '' && $_POST['conta_destino_id'] !== 'null' ? $_POST['conta_destino_id'] : null;
            $dataVencimento = isset($_POST['data_vencimento']) && $_POST['data_vencimento'] !== '' && $_POST['data_vencimento'] !== 'null' ? $_POST['data_vencimento'] : null;
            $dataPagamento = isset($_POST['data_pagamento']) && $_POST['data_pagamento'] !== '' && $_POST['data_pagamento'] !== 'null' ? $_POST['data_pagamento'] : null;
            $status = $_POST['status'] ?? 'confirmado';
            $formaPagamento = isset($_POST['forma_pagamento']) && $_POST['forma_pagamento'] !== '' && $_POST['forma_pagamento'] !== 'null' ? $_POST['forma_pagamento'] : null;
            $recorrencia = $_POST['recorrencia'] ?? 'nenhuma';
            $dataFimRecorrencia = isset($_POST['data_fim_recorrencia']) && $_POST['data_fim_recorrencia'] !== '' && $_POST['data_fim_recorrencia'] !== 'null' ? $_POST['data_fim_recorrencia'] : null;
            $observacoes = isset($_POST['observacoes']) && $_POST['observacoes'] !== '' && $_POST['observacoes'] !== 'null' ? $_POST['observacoes'] : null;
            
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
            
            // Validações - categoria_id pode ser null/opcional
            if (empty($id) || empty($tipoLancamento) || empty($descricao) || empty($valor) || empty($dataLancamento) || empty($contaId)) {
                throw new \Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Buscar lançamento atual
            $lancamentoAtual = $db->fetch(
                "SELECT * FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$id, $tenantId, $filialId]
            );
            
            if (!$lancamentoAtual) {
                throw new \Exception('Lançamento não encontrado');
            }
            
            $valor = (float) $valor;
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            // Verificar se categoria existe (se fornecida)
            // Tratar string vazia como null
            if (empty($categoriaId)) {
                $categoriaId = null;
            }
            
            if ($categoriaId !== null) {
                $categoria = $db->fetch(
                    "SELECT * FROM categorias_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                    [$categoriaId, $tenantId, $filialId]
                );
                
                if (!$categoria) {
                    throw new \Exception('Categoria não encontrada ou inativa');
                }
            }
            
            // Verificar se conta existe
            $conta = $db->fetch(
                "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                [$contaId, $tenantId, $filialId]
            );
            
            if (!$conta) {
                throw new \Exception('Conta não encontrada ou inativa');
            }
            
            // Reverter saldo da conta antiga se estava pago (confirmado)
            // Map old status for comparison
            $statusAntigoMap = [
                'pendente' => 'pendente',
                'confirmado' => 'pago',
                'pago' => 'pago',
                'vencido' => 'vencido',
                'cancelado' => 'cancelado'
            ];
            $statusAntigo = $statusAntigoMap[$lancamentoAtual['status']] ?? 'pendente';
            
            if ($statusAntigo === 'pago') {
                $contaAntiga = $db->fetch(
                    "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                    [$lancamentoAtual['conta_id'], $tenantId, $filialId]
                );
                
                if ($contaAntiga) {
                    // Converter valores para float antes de fazer operações
                    $saldoAtualAntigo = (float) $contaAntiga['saldo_atual'];
                    $valorAntigo = (float) $lancamentoAtual['valor'];
                    
                    $saldoRevertido = $lancamentoAtual['tipo_lancamento'] === 'receita' ? 
                        $saldoAtualAntigo - $valorAntigo : 
                        $saldoAtualAntigo + $valorAntigo;
                    
                    $db->update('contas_financeiras', 
                        ['saldo_atual' => $saldoRevertido],
                        'id = ? AND tenant_id = ? AND filial_id = ?',
                        [$lancamentoAtual['conta_id'], $tenantId, $filialId]
                    );
                }
            }
            
            // Prepare update data
            $updateData = [
                'tipo_lancamento' => $tipoLancamento,
                'conta_id' => $contaId,
                'valor' => $valor,
                'data_lancamento' => $dataLancamento,
                'descricao' => $descricao,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add optional fields - incluir apenas se foram explicitamente enviados no POST
            // Isso permite atualizar para NULL quando necessário
            if (isset($_POST['categoria_id'])) {
                $updateData['categoria_id'] = $categoriaId; // Pode ser NULL
            }
            if (isset($_POST['conta_destino_id'])) {
                $updateData['conta_destino_id'] = $contaDestinoId; // Pode ser NULL
            }
            if (isset($_POST['data_vencimento'])) {
                $updateData['data_vencimento'] = $dataVencimento !== '' ? $dataVencimento : null;
            }
            if (isset($_POST['data_pagamento'])) {
                $updateData['data_pagamento'] = $dataPagamento !== '' ? $dataPagamento : null;
            }
            if (isset($_POST['forma_pagamento'])) {
                $updateData['forma_pagamento'] = $formaPagamento;
            }
            
            // Adicionar campos de recorrência (as colunas já foram criadas no início do case)
            if (isset($_POST['recorrencia'])) {
                if ($recorrencia !== null && $recorrencia !== '' && $recorrencia !== 'nenhuma') {
                    $updateData['recorrencia'] = $recorrencia;
                } else {
                    // Se for nenhuma, definir como NULL ou 'nenhuma' dependendo do schema
                    $updateData['recorrencia'] = 'nenhuma';
                }
            }
            
            if (isset($_POST['data_fim_recorrencia'])) {
                $updateData['data_fim_recorrencia'] = $dataFimRecorrencia !== '' ? $dataFimRecorrencia : null;
            }
            
            if (isset($_POST['observacoes'])) {
                $updateData['observacoes'] = $observacoes;
            }
            
            // Verificar quais colunas realmente existem antes de fazer o update
            $colunasExistentes = $db->fetchAll(
                "SELECT column_name 
                 FROM information_schema.columns 
                 WHERE table_name = 'lancamentos_financeiros'"
            );
            $colunasNomes = array_column($colunasExistentes, 'column_name');
            
            // Filtrar updateData para incluir apenas colunas que existem
            $updateDataFiltrado = [];
            foreach ($updateData as $coluna => $valor) {
                if (in_array($coluna, $colunasNomes)) {
                    $updateDataFiltrado[$coluna] = $valor;
                } else {
                    error_log("Coluna '{$coluna}' não existe na tabela lancamentos_financeiros, ignorando no update");
                }
            }
            
            // Atualizar lançamento apenas com colunas que existem
            try {
                error_log("Atualizando lançamento ID: $id com dados: " . json_encode($updateDataFiltrado));
                $atualizado = $db->update('lancamentos_financeiros', $updateDataFiltrado, 'id = ? AND tenant_id = ? AND filial_id = ?', [$id, $tenantId, $filialId]);
                error_log("Resultado do update: " . ($atualizado ? 'sucesso' : 'falha'));
            } catch (\Exception $e) {
                error_log("Erro ao atualizar lançamento: " . $e->getMessage());
                throw new \Exception('Erro ao atualizar lançamento: ' . $e->getMessage());
            }
            
            if (!$atualizado) {
                throw new \Exception('Erro ao atualizar lançamento - nenhuma linha foi atualizada');
            }
            
            // Atualizar saldo da nova conta se status for pago (confirmado)
            if ($status === 'pago') {
                // Converter valores para float antes de fazer operações
                $saldoAtual = (float) $conta['saldo_atual'];
                $valorFloat = (float) $valor;
                
                $novoSaldo = $tipoLancamento === 'receita' ? 
                    $saldoAtual + $valorFloat : 
                    $saldoAtual - $valorFloat;
                
                $db->update('contas_financeiras', 
                    ['saldo_atual' => $novoSaldo],
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$contaId, $tenantId, $filialId]
                );
            }
            
            // Limpar buffer antes de enviar resposta
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento atualizado com sucesso!'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'excluir_lancamento':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new \Exception('ID do lançamento é obrigatório');
            }
            
            // Buscar lançamento
            $lancamento = $db->fetch(
                "SELECT * FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                [$id, $tenantId, $filialId]
            );
            
            if (!$lancamento) {
                throw new \Exception('Lançamento não encontrado');
            }
            
            // Reverter saldo da conta se estava pago (confirmado)
            // O banco armazena como 'pago', mas o frontend pode enviar 'confirmado'
            $statusParaReverter = $lancamento['status'];
            if ($statusParaReverter === 'confirmado' || $statusParaReverter === 'pago') {
                $conta = $db->fetch(
                    "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                    [$lancamento['conta_id'], $tenantId, $filialId]
                );
                
                if ($conta) {
                    // Converter valores para float antes de fazer operações
                    $saldoAtual = (float) $conta['saldo_atual'];
                    $valorLancamento = (float) $lancamento['valor'];
                    
                    $saldoRevertido = $lancamento['tipo_lancamento'] === 'receita' ? 
                        $saldoAtual - $valorLancamento : 
                        $saldoAtual + $valorLancamento;
                    
                    $db->update('contas_financeiras', 
                        ['saldo_atual' => $saldoRevertido],
                        'id = ? AND tenant_id = ? AND filial_id = ?',
                        [$lancamento['conta_id'], $tenantId, $filialId]
                    );
                }
            }
            
            // Excluir lançamento
            $excluido = $db->delete('lancamentos_financeiros', 'id = ? AND tenant_id = ? AND filial_id = ?', [$id, $tenantId, $filialId]);
            
            if (!$excluido) {
                throw new \Exception('Erro ao excluir lançamento');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento excluído com sucesso!'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'listar_lancamentos':
            $tipoFiltro = $_GET['tipo'] ?? '';
            $categoriaFiltro = $_GET['categoria'] ?? '';
            $contaFiltro = $_GET['conta'] ?? '';
            $statusFiltro = $_GET['status'] ?? '';
            $dataInicio = $_GET['data_inicio'] ?? '';
            $dataFim = $_GET['data_fim'] ?? '';
            $limite = (int) ($_GET['limite'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);
            
            // Construir query com filtros
            $whereConditions = ['l.tenant_id = ?', 'l.filial_id = ?'];
            $params = [$tenantId, $filialId];
            
            if (!empty($tipoFiltro)) {
                $whereConditions[] = 'l.tipo_lancamento = ?';
                $params[] = $tipoFiltro;
            }
            
            if (!empty($categoriaFiltro)) {
                $whereConditions[] = 'l.categoria_id = ?';
                $params[] = $categoriaFiltro;
            }
            
            if (!empty($contaFiltro)) {
                $whereConditions[] = 'l.conta_id = ?';
                $params[] = $contaFiltro;
            }
            
            if (!empty($statusFiltro)) {
                $whereConditions[] = 'l.status = ?';
                $params[] = $statusFiltro;
            }
            
            if (!empty($dataInicio)) {
                $whereConditions[] = 'l.data_lancamento >= ?';
                $params[] = $dataInicio . ' 00:00:00';
            }
            
            if (!empty($dataFim)) {
                $whereConditions[] = 'l.data_lancamento <= ?';
                $params[] = $dataFim . ' 23:59:59';
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Buscar lançamentos
            $lancamentos = $db->fetchAll(
                "SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
                        co.nome as conta_nome, co.tipo as conta_tipo, u.login as usuario_nome
                 FROM lancamentos_financeiros l
                 LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
                 LEFT JOIN contas_financeiras co ON l.conta_id = co.id
                 LEFT JOIN usuarios u ON l.usuario_id = u.id
                 WHERE $whereClause
                 ORDER BY l.data_lancamento DESC, l.created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limite, $offset])
            );
            
            // Contar total
            $total = $db->fetch(
                "SELECT COUNT(*) as total FROM lancamentos_financeiros l WHERE $whereClause",
                $params
            );
            
            echo json_encode([
                'success' => true,
                'lancamentos' => $lancamentos,
                'total' => $total['total']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        default:
            throw new \Exception('Ação não reconhecida');
    }
    
    // Limpar buffer antes de enviar resposta de sucesso
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
} catch (\Exception $e) {
    // Limpar qualquer output anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (\Error $e) {
    // Limpar qualquer output anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (\Throwable $e) {
    // Limpar qualquer output anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>




