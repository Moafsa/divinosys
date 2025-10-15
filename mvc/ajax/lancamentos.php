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
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            $categoriaId = $_POST['categoria_id'] ?? '';
            $contaId = $_POST['conta_id'] ?? '';
            $status = $_POST['status'] ?? 'confirmado';
            $observacoes = $_POST['observacoes'] ?? '';
            
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
            $id = $_POST['id'] ?? '';
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            $categoriaId = $_POST['categoria_id'] ?? '';
            $contaId = $_POST['conta_id'] ?? '';
            $status = $_POST['status'] ?? 'confirmado';
            $observacoes = $_POST['observacoes'] ?? '';
            
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
            
            // Reverter saldo da conta antiga se estava confirmado
            if ($lancamentoAtual['status'] === 'confirmado') {
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
            
            // Atualizar lançamento
            $atualizado = $db->update('lancamentos_financeiros', [
                'tipo_lancamento' => $tipoLancamento,
                'categoria_id' => $categoriaId,
                'conta_id' => $contaId,
                'valor' => $valor,
                'data_lancamento' => $dataLancamento,
                'descricao' => $descricao,
                'observacoes' => $observacoes,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ? AND tenant_id = ? AND filial_id = ?', [$id, $tenantId, $filialId]);
            
            if (!$atualizado) {
                throw new \Exception('Erro ao atualizar lançamento');
            }
            
            // Atualizar saldo da nova conta se status for confirmado
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




