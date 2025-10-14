<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

header('Content-Type: application/json');

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    $usuarioId = $session->getUserId();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'criar_lancamento':
            criarLancamento($db, $tenantId, $filialId, $usuarioId);
            break;
            
        case 'editar_lancamento':
            editarLancamento($db, $tenantId, $filialId, $usuarioId);
            break;
            
        case 'excluir_lancamento':
            excluirLancamento($db, $tenantId, $filialId);
            break;
            
        case 'listar_lancamentos':
            listarLancamentos($db, $tenantId, $filialId);
            break;
            
        case 'listar_categorias':
            listarCategorias($db, $tenantId, $filialId);
            break;
            
        case 'listar_contas':
            listarContas($db, $tenantId, $filialId);
            break;
            
        case 'criar_categoria':
            criarCategoria($db, $tenantId, $filialId);
            break;
            
        case 'criar_conta':
            criarConta($db, $tenantId, $filialId);
            break;
            
        case 'upload_anexo':
            uploadAnexo($db, $tenantId, $filialId);
            break;
            
        case 'gerar_relatorio':
            gerarRelatorio($db, $tenantId, $filialId);
            break;
            
        case 'resumo_financeiro':
            resumoFinanceiro($db, $tenantId, $filialId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

function criarLancamento($db, $tenantId, $filialId, $usuarioId) {
    $tipo = $_POST['tipo'] ?? '';
    $categoriaId = $_POST['categoria_id'] ?? null;
    $contaId = $_POST['conta_id'] ?? null;
    $contaDestinoId = $_POST['conta_destino_id'] ?? null;
    $pedidoId = $_POST['pedido_id'] ?? null;
    $valor = (float)($_POST['valor'] ?? 0);
    $dataVencimento = $_POST['data_vencimento'] ?? null;
    $dataPagamento = $_POST['data_pagamento'] ?? null;
    $descricao = trim($_POST['descricao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    $recorrencia = $_POST['recorrencia'] ?? 'nenhuma';
    $dataFimRecorrencia = $_POST['data_fim_recorrencia'] ?? null;
    
    // Validações
    if (empty($tipo) || !in_array($tipo, ['receita', 'despesa', 'transferencia'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de lançamento inválido']);
        return;
    }
    
    if (empty($contaId)) {
        echo json_encode(['success' => false, 'message' => 'Conta é obrigatória']);
        return;
    }
    
    if ($valor <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero']);
        return;
    }
    
    if (empty($descricao)) {
        echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória']);
        return;
    }
    
    // Para transferências, conta destino é obrigatória
    if ($tipo === 'transferencia' && empty($contaDestinoId)) {
        echo json_encode(['success' => false, 'message' => 'Conta destino é obrigatória para transferências']);
        return;
    }
    
    // Verificar se a conta existe
    $conta = $db->fetch("SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?", 
                       [$contaId, $tenantId, $filialId]);
    if (!$conta) {
        echo json_encode(['success' => false, 'message' => 'Conta não encontrada']);
        return;
    }
    
    // Para transferências, verificar conta destino
    if ($tipo === 'transferencia') {
        $contaDestino = $db->fetch("SELECT * FROM contas_financeiras WHERE id = ? AND tenant_id = ? AND filial_id = ?", 
                                 [$contaDestinoId, $tenantId, $filialId]);
        if (!$contaDestino) {
            echo json_encode(['success' => false, 'message' => 'Conta destino não encontrada']);
            return;
        }
    }
    
    // Determinar status baseado na data de pagamento
    $status = 'pendente';
    if (!empty($dataPagamento)) {
        $status = 'pago';
    }
    
    // Criar lançamento
    $lancamentoId = $db->insert('lancamentos_financeiros', [
        'tipo' => $tipo,
        'categoria_id' => $categoriaId,
        'conta_id' => $contaId,
        'conta_destino_id' => $contaDestinoId,
        'pedido_id' => $pedidoId,
        'valor' => $valor,
        'data_vencimento' => $dataVencimento,
        'data_pagamento' => $dataPagamento,
        'descricao' => $descricao,
        'observacoes' => $observacoes,
        'forma_pagamento' => $formaPagamento,
        'status' => $status,
        'recorrência' => $recorrencia,
        'data_fim_recorrencia' => $dataFimRecorrencia,
        'usuario_id' => $usuarioId,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId
    ]);
    
    // Para transferências, criar lançamento de saída na conta origem
    if ($tipo === 'transferencia') {
        $db->insert('lancamentos_financeiros', [
            'tipo' => 'despesa',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'conta_destino_id' => $contaDestinoId,
            'pedido_id' => $pedidoId,
            'valor' => $valor,
            'data_vencimento' => $dataVencimento,
            'data_pagamento' => $dataPagamento,
            'descricao' => "Transferência para {$contaDestino['nome']}",
            'observacoes' => $observacoes,
            'forma_pagamento' => 'Transferência',
            'status' => $status,
            'recorrência' => $recorrencia,
            'data_fim_recorrencia' => $dataFimRecorrencia,
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        // Criar lançamento de entrada na conta destino
        $db->insert('lancamentos_financeiros', [
            'tipo' => 'receita',
            'categoria_id' => $categoriaId,
            'conta_id' => $contaDestinoId,
            'conta_destino_id' => $contaId,
            'pedido_id' => $pedidoId,
            'valor' => $valor,
            'data_vencimento' => $dataVencimento,
            'data_pagamento' => $dataPagamento,
            'descricao' => "Transferência de {$conta['nome']}",
            'observacoes' => $observacoes,
            'forma_pagamento' => 'Transferência',
            'status' => $status,
            'recorrência' => $recorrencia,
            'data_fim_recorrencia' => $dataFimRecorrencia,
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Lançamento criado com sucesso',
        'lancamento_id' => $lancamentoId
    ]);
}

function editarLancamento($db, $tenantId, $filialId, $usuarioId) {
    $lancamentoId = $_POST['lancamento_id'] ?? 0;
    $tipo = $_POST['tipo'] ?? '';
    $categoriaId = $_POST['categoria_id'] ?? null;
    $contaId = $_POST['conta_id'] ?? null;
    $valor = (float)($_POST['valor'] ?? 0);
    $dataVencimento = $_POST['data_vencimento'] ?? null;
    $dataPagamento = $_POST['data_pagamento'] ?? null;
    $descricao = trim($_POST['descricao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    $status = $_POST['status'] ?? 'pendente';
    
    // Verificar se o lançamento existe
    $lancamento = $db->fetch("SELECT * FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?", 
                            [$lancamentoId, $tenantId, $filialId]);
    if (!$lancamento) {
        echo json_encode(['success' => false, 'message' => 'Lançamento não encontrado']);
        return;
    }
    
    // Atualizar lançamento
    $db->update('lancamentos_financeiros', [
        'tipo' => $tipo,
        'categoria_id' => $categoriaId,
        'conta_id' => $contaId,
        'valor' => $valor,
        'data_vencimento' => $dataVencimento,
        'data_pagamento' => $dataPagamento,
        'descricao' => $descricao,
        'observacoes' => $observacoes,
        'forma_pagamento' => $formaPagamento,
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ? AND tenant_id = ? AND filial_id = ?', [$lancamentoId, $tenantId, $filialId]);
    
    echo json_encode(['success' => true, 'message' => 'Lançamento atualizado com sucesso']);
}

function excluirLancamento($db, $tenantId, $filialId) {
    $lancamentoId = $_POST['lancamento_id'] ?? 0;
    
    // Verificar se o lançamento existe
    $lancamento = $db->fetch("SELECT * FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?", 
                            [$lancamentoId, $tenantId, $filialId]);
    if (!$lancamento) {
        echo json_encode(['success' => false, 'message' => 'Lançamento não encontrado']);
        return;
    }
    
    // Excluir anexos relacionados
    $db->execute("DELETE FROM anexos_financeiros WHERE lancamento_id = ?", [$lancamentoId]);
    
    // Excluir lançamento
    $db->execute("DELETE FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ? AND filial_id = ?", 
                [$lancamentoId, $tenantId, $filialId]);
    
    echo json_encode(['success' => true, 'message' => 'Lançamento excluído com sucesso']);
}

function listarLancamentos($db, $tenantId, $filialId) {
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
    $tipo = $_GET['tipo'] ?? '';
    $categoriaId = $_GET['categoria_id'] ?? '';
    $contaId = $_GET['conta_id'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $whereConditions = ["l.tenant_id = ?", "l.filial_id = ?"];
    $params = [$tenantId, $filialId];
    
    if ($dataInicio && $dataFim) {
        $whereConditions[] = "l.created_at BETWEEN ? AND ?";
        $params[] = $dataInicio . ' 00:00:00';
        $params[] = $dataFim . ' 23:59:59';
    }
    
    if (!empty($tipo)) {
        $whereConditions[] = "l.tipo = ?";
        $params[] = $tipo;
    }
    
    if (!empty($categoriaId)) {
        $whereConditions[] = "l.categoria_id = ?";
        $params[] = $categoriaId;
    }
    
    if (!empty($contaId)) {
        $whereConditions[] = "l.conta_id = ?";
        $params[] = $contaId;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "l.status = ?";
        $params[] = $status;
    }
    
    $lancamentos = $db->fetchAll(
        "SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
                cf.nome as conta_nome, cf.tipo as conta_tipo, cf.cor as conta_cor,
                u.login as usuario_nome, p.idpedido, p.cliente as pedido_cliente
         FROM lancamentos_financeiros l
         LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
         LEFT JOIN contas_financeiras cf ON l.conta_id = cf.id
         LEFT JOIN usuarios u ON l.usuario_id = u.id
         LEFT JOIN pedido p ON l.pedido_id = p.idpedido
         WHERE " . implode(' AND ', $whereConditions) . "
         ORDER BY l.created_at DESC",
        $params
    );
    
    echo json_encode(['success' => true, 'data' => $lancamentos]);
}

function listarCategorias($db, $tenantId, $filialId) {
    $categorias = $db->fetchAll(
        "SELECT * FROM categorias_financeiras 
         WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
         ORDER BY tipo, nome",
        [$tenantId, $filialId]
    );
    
    echo json_encode(['success' => true, 'data' => $categorias]);
}

function listarContas($db, $tenantId, $filialId) {
    $contas = $db->fetchAll(
        "SELECT * FROM contas_financeiras 
         WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
         ORDER BY tipo, nome",
        [$tenantId, $filialId]
    );
    
    echo json_encode(['success' => true, 'data' => $contas]);
}

function criarCategoria($db, $tenantId, $filialId) {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $cor = $_POST['cor'] ?? '#007bff';
    $icone = $_POST['icone'] ?? 'fas fa-tag';
    $paiId = $_POST['pai_id'] ?? null;
    
    if (empty($nome) || empty($tipo)) {
        echo json_encode(['success' => false, 'message' => 'Nome e tipo são obrigatórios']);
        return;
    }
    
    $categoriaId = $db->insert('categorias_financeiras', [
        'nome' => $nome,
        'tipo' => $tipo,
        'descricao' => $descricao,
        'cor' => $cor,
        'icone' => $icone,
        'pai_id' => $paiId,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso', 'categoria_id' => $categoriaId]);
}

function criarConta($db, $tenantId, $filialId) {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $saldoInicial = (float)($_POST['saldo_inicial'] ?? 0);
    $banco = trim($_POST['banco'] ?? '');
    $agencia = trim($_POST['agencia'] ?? '');
    $conta = trim($_POST['conta'] ?? '');
    $limite = (float)($_POST['limite'] ?? 0);
    $cor = $_POST['cor'] ?? '#28a745';
    $icone = $_POST['icone'] ?? 'fas fa-wallet';
    
    if (empty($nome) || empty($tipo)) {
        echo json_encode(['success' => false, 'message' => 'Nome e tipo são obrigatórios']);
        return;
    }
    
    $contaId = $db->insert('contas_financeiras', [
        'nome' => $nome,
        'tipo' => $tipo,
        'saldo_inicial' => $saldoInicial,
        'saldo_atual' => $saldoInicial,
        'banco' => $banco,
        'agencia' => $agencia,
        'conta' => $conta,
        'limite' => $limite,
        'cor' => $cor,
        'icone' => $icone,
        'tenant_id' => $tenantId,
        'filial_id' => $filialId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Conta criada com sucesso', 'conta_id' => $contaId]);
}

function uploadAnexo($db, $tenantId, $filialId) {
    $lancamentoId = $_POST['lancamento_id'] ?? 0;
    
    if (!isset($_FILES['anexo']) || $_FILES['anexo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo']);
        return;
    }
    
    $file = $_FILES['anexo'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileType = $file['type'];
    
    // Validar tamanho (máximo 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB.']);
        return;
    }
    
    // Validar tipo de arquivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido']);
        return;
    }
    
    // Criar diretório se não existir
    $uploadDir = __DIR__ . '/../../uploads/financeiro/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $newFileName;
    
    // Mover arquivo
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Salvar no banco
        $anexoId = $db->insert('anexos_financeiros', [
            'lancamento_id' => $lancamentoId,
            'nome_arquivo' => $fileName,
            'caminho_arquivo' => $filePath,
            'tipo_arquivo' => $fileType,
            'tamanho_arquivo' => $fileSize,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Anexo enviado com sucesso',
            'anexo_id' => $anexoId,
            'file_path' => $filePath
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']);
    }
}

function gerarRelatorio($db, $tenantId, $filialId) {
    $tipo = $_POST['tipo'] ?? '';
    $dataInicio = $_POST['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_POST['data_fim'] ?? date('Y-m-t');
    $filtros = $_POST['filtros'] ?? [];
    
    $relatorioId = $db->insert('relatorios_financeiros', [
        'nome' => "Relatório {$tipo} - " . date('d/m/Y'),
        'tipo' => $tipo,
        'periodo_inicio' => $dataInicio,
        'periodo_fim' => $dataFim,
        'filtros' => json_encode($filtros),
        'dados' => json_encode([]),
        'status' => 'gerando',
        'usuario_id' => $session->getUserId(),
        'tenant_id' => $tenantId,
        'filial_id' => $filialId
    ]);
    
    // Simular geração de relatório (implementar lógica específica)
    $dados = [];
    switch ($tipo) {
        case 'fluxo_caixa':
            $dados = gerarRelatorioFluxoCaixa($db, $tenantId, $filialId, $dataInicio, $dataFim);
            break;
        case 'receitas_categoria':
            $dados = gerarRelatorioReceitasCategoria($db, $tenantId, $filialId, $dataInicio, $dataFim);
            break;
        case 'despesas_categoria':
            $dados = gerarRelatorioDespesasCategoria($db, $tenantId, $filialId, $dataInicio, $dataFim);
            break;
    }
    
    // Atualizar relatório com dados
    $db->update('relatorios_financeiros', [
        'dados' => json_encode($dados),
        'status' => 'gerado'
    ], 'id = ?', [$relatorioId]);
    
    echo json_encode(['success' => true, 'message' => 'Relatório gerado com sucesso', 'relatorio_id' => $relatorioId]);
}

function gerarRelatorioFluxoCaixa($db, $tenantId, $filialId, $dataInicio, $dataFim) {
    $lancamentos = $db->fetchAll(
        "SELECT DATE(created_at) as data, tipo, SUM(valor) as total
         FROM lancamentos_financeiros 
         WHERE tenant_id = ? AND filial_id = ? 
         AND created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at), tipo
         ORDER BY data",
        [$tenantId, $filialId, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    return $lancamentos;
}

function gerarRelatorioReceitasCategoria($db, $tenantId, $filialId, $dataInicio, $dataFim) {
    $receitas = $db->fetchAll(
        "SELECT c.nome as categoria, SUM(l.valor) as total
         FROM lancamentos_financeiros l
         LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
         WHERE l.tenant_id = ? AND l.filial_id = ? 
         AND l.tipo = 'receita'
         AND l.created_at BETWEEN ? AND ?
         GROUP BY c.id, c.nome
         ORDER BY total DESC",
        [$tenantId, $filialId, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    return $receitas;
}

function gerarRelatorioDespesasCategoria($db, $tenantId, $filialId, $dataInicio, $dataFim) {
    $despesas = $db->fetchAll(
        "SELECT c.nome as categoria, SUM(l.valor) as total
         FROM lancamentos_financeiros l
         LEFT JOIN categorias_financeiras c ON l.categoria_id = c.id
         WHERE l.tenant_id = ? AND l.filial_id = ? 
         AND l.tipo = 'despesa'
         AND l.created_at BETWEEN ? AND ?
         GROUP BY c.id, c.nome
         ORDER BY total DESC",
        [$tenantId, $filialId, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    return $despesas;
}

function resumoFinanceiro($db, $tenantId, $filialId) {
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
    
    $resumo = $db->fetch(
        "SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) as total_receitas,
            COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as total_despesas,
            COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) as saldo_liquido,
            COUNT(*) as total_lancamentos
         FROM lancamentos_financeiros 
         WHERE tenant_id = ? AND filial_id = ?
         AND created_at BETWEEN ? AND ?",
        [$tenantId, $filialId, $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']
    );
    
    echo json_encode(['success' => true, 'data' => $resumo]);
}
?>
