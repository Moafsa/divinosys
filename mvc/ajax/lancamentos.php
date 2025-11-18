<?php
session_start();
header('Content-Type: application/json');

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
                $novoSaldo = $tipoLancamento === 'receita' ? 
                    $conta['saldo_atual'] + $valor : 
                    $conta['saldo_atual'] - $valor;
                
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
            ]);
            break;
            
        case 'buscar_lancamento':
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
            
            echo json_encode([
                'success' => true,
                'lancamento' => $lancamento
            ]);
            break;
            
        case 'atualizar_lancamento':
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
            
            $id = $_POST['id'] ?? '';
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            $categoriaId = $_POST['categoria_id'] ?? null;
            $contaId = $_POST['conta_id'] ?? '';
            $contaDestinoId = $_POST['conta_destino_id'] ?? null;
            $dataVencimento = $_POST['data_vencimento'] ?? null;
            $dataPagamento = $_POST['data_pagamento'] ?? null;
            $status = $_POST['status'] ?? 'confirmado';
            $formaPagamento = $_POST['forma_pagamento'] ?? null;
            $recorrencia = $_POST['recorrencia'] ?? 'nenhuma';
            $dataFimRecorrencia = $_POST['data_fim_recorrencia'] ?? null;
            $observacoes = $_POST['observacoes'] ?? null;
            
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
            
            // Validações
            if (empty($id) || empty($tipoLancamento) || empty($descricao) || empty($valor) || empty($dataLancamento) || empty($categoriaId) || empty($contaId)) {
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
                    $saldoRevertido = $lancamentoAtual['tipo_lancamento'] === 'receita' ? 
                        $contaAntiga['saldo_atual'] - $lancamentoAtual['valor'] : 
                        $contaAntiga['saldo_atual'] + $lancamentoAtual['valor'];
                    
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
            
            // Add optional fields if provided
            if ($categoriaId !== null) {
                $updateData['categoria_id'] = $categoriaId;
            }
            if ($contaDestinoId !== null) {
                $updateData['conta_destino_id'] = $contaDestinoId;
            }
            if ($dataVencimento !== null && $dataVencimento !== '') {
                $updateData['data_vencimento'] = $dataVencimento;
            }
            if ($dataPagamento !== null && $dataPagamento !== '') {
                $updateData['data_pagamento'] = $dataPagamento;
            }
            if ($formaPagamento !== null && $formaPagamento !== '') {
                $updateData['forma_pagamento'] = $formaPagamento;
            }
            if ($recorrencia !== null && $recorrencia !== '') {
                $updateData['recorrencia'] = $recorrencia;
            }
            if ($dataFimRecorrencia !== null && $dataFimRecorrencia !== '') {
                $updateData['data_fim_recorrencia'] = $dataFimRecorrencia;
            }
            if ($observacoes !== null) {
                $updateData['observacoes'] = $observacoes;
            }
            
            // Atualizar lançamento
            $atualizado = $db->update('lancamentos_financeiros', $updateData, 'id = ? AND tenant_id = ? AND filial_id = ?', [$id, $tenantId, $filialId]);
            
            if (!$atualizado) {
                throw new \Exception('Erro ao atualizar lançamento');
            }
            
            // Atualizar saldo da nova conta se status for pago (confirmado)
            if ($status === 'pago') {
                $novoSaldo = $tipoLancamento === 'receita' ? 
                    $conta['saldo_atual'] + $valor : 
                    $conta['saldo_atual'] - $valor;
                
                $db->update('contas_financeiras', 
                    ['saldo_atual' => $novoSaldo],
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$contaId, $tenantId, $filialId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento atualizado com sucesso!'
            ]);
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
            
            // Reverter saldo da conta se estava confirmado
            if ($lancamento['status'] === 'confirmado') {
                $conta = $db->fetch(
                    "SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                    [$lancamento['conta_id'], $tenantId, $filialId]
                );
                
                if ($conta) {
                    $saldoRevertido = $lancamento['tipo_lancamento'] === 'receita' ? 
                        $conta['saldo_atual'] - $lancamento['valor'] : 
                        $conta['saldo_atual'] + $lancamento['valor'];
                    
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
            ]);
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
            ]);
            break;
            
        default:
            throw new \Exception('Ação não reconhecida');
    }
    
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>




