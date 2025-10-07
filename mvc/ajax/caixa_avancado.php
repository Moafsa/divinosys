<?php
session_start();
require_once '../../system/Database.php';
require_once '../../system/Utils.php';

// Configurar headers para JSON
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$filialId = $_SESSION['filial_id'] ?? 1;
$acao = $_POST['acao'] ?? '';

// Verificar se é uma requisição JSON
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['acao'])) {
    $acao = $input['acao'];
    $_POST = $input;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    switch ($acao) {
        case 'inicializar_caixa':
            inicializarCaixa($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_clientes':
            listarClientes($pdo, $tenantId);
            break;
            
        case 'listar_produtos':
            listarProdutos($pdo, $tenantId);
            break;
            
        case 'buscar_produtos':
            buscarProdutos($pdo, $tenantId);
            break;
            
        case 'obter_produto':
            obterProduto($pdo, $tenantId);
            break;
            
        case 'finalizar_venda':
            finalizarVenda($pdo, $tenantId, $filialId);
            break;
            
        case 'fechar_caixa':
            fecharCaixa($pdo, $tenantId, $filialId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em caixa_avancado.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function inicializarCaixa($pdo, $tenantId, $filialId) {
    // Verificar se há sessão de caixa aberta
    $sql = "
        SELECT * FROM sessoes_caixa 
        WHERE tenant_id = ? AND filial_id = ? AND status = 'aberta'
        ORDER BY data_abertura DESC 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessao) {
        // Criar nova sessão de caixa
        $sql = "
            INSERT INTO sessoes_caixa (
                tenant_id, filial_id, usuario_id, data_abertura, 
                saldo_inicial, status, created_at
            ) VALUES (?, ?, ?, NOW(), 0, 'aberta', NOW())
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId, $filialId, $_SESSION['user_id']]);
        $sessaoId = $pdo->lastInsertId();
        
        $sessao = [
            'id' => $sessaoId,
            'saldo_inicial' => 0,
            'saldo_atual' => 0
        ];
    }
    
    // Calcular total de vendas do dia
    $sqlVendas = "
        SELECT COALESCE(SUM(valor_total), 0) as total_vendas
        FROM vendas 
        WHERE tenant_id = ? AND filial_id = ? 
        AND DATE(data_venda) = CURDATE()
    ";
    
    $stmt = $pdo->prepare($sqlVendas);
    $stmt->execute([$tenantId, $filialId]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_vendas' => floatval($resultado['total_vendas']),
        'saldo_caixa' => floatval($sessao['saldo_atual'] ?? $sessao['saldo_inicial'])
    ]);
}

function listarClientes($pdo, $tenantId) {
    $sql = "
        SELECT id, nome, telefone, email
        FROM clientes_fiado 
        WHERE tenant_id = ? AND status = 'ativo'
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'clientes' => $clientes]);
}

function listarProdutos($pdo, $tenantId) {
    $sql = "
        SELECT id, nome, preco, categoria_id, estoque_atual
        FROM produtos 
        WHERE tenant_id = ? AND ativo = true
        ORDER BY nome
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'produtos' => $produtos]);
}

function buscarProdutos($pdo, $tenantId) {
    $termo = $_POST['termo'] ?? '';
    
    if (empty($termo)) {
        listarProdutos($pdo, $tenantId);
        return;
    }
    
    $sql = "
        SELECT id, nome, preco, categoria_id, estoque_atual
        FROM produtos 
        WHERE tenant_id = ? AND ativo = true 
        AND (nome LIKE ? OR descricao LIKE ?)
        ORDER BY nome
        LIMIT 20
    ";
    
    $termoBusca = "%$termo%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $termoBusca, $termoBusca]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'produtos' => $produtos]);
}

function obterProduto($pdo, $tenantId) {
    $produtoId = $_POST['produto_id'] ?? '';
    
    if (empty($produtoId)) {
        echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido']);
        return;
    }
    
    $sql = "
        SELECT id, nome, preco, categoria_id, estoque_atual
        FROM produtos 
        WHERE id = ? AND tenant_id = ? AND ativo = true
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$produtoId, $tenantId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        return;
    }
    
    echo json_encode(['success' => true, 'produto' => $produto]);
}

function finalizarVenda($pdo, $tenantId, $filialId) {
    $clienteId = $_POST['cliente_id'] ?? null;
    $tipoVenda = $_POST['tipo_venda'] ?? 'normal';
    $itens = $_POST['itens'] ?? [];
    $pagamentos = $_POST['pagamentos'] ?? [];
    $observacoes = $_POST['observacoes'] ?? '';
    
    if (empty($itens)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum item na venda']);
        return;
    }
    
    if (empty($pagamentos)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma forma de pagamento selecionada']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Calcular totais
        $subtotal = 0;
        foreach ($itens as $item) {
            $subtotal += $item['preco'] * $item['quantidade'];
        }
        
        $totalPagamentos = 0;
        foreach ($pagamentos as $pagamento) {
            $totalPagamentos += $pagamento['valor'];
        }
        
        // Inserir venda
        $sqlVenda = "
            INSERT INTO vendas (
                tenant_id, filial_id, cliente_id, tipo_venda, data_venda,
                subtotal, desconto, valor_total, status, observacoes, created_at
            ) VALUES (?, ?, ?, ?, NOW(), ?, 0, ?, 'finalizada', ?, NOW())
        ";
        
        $stmt = $pdo->prepare($sqlVenda);
        $stmt->execute([$tenantId, $filialId, $clienteId, $tipoVenda, $subtotal, $subtotal, $observacoes]);
        $vendaId = $pdo->lastInsertId();
        
        // Inserir itens da venda
        foreach ($itens as $item) {
            $sqlItem = "
                INSERT INTO venda_itens (
                    venda_id, produto_id, quantidade, preco_unitario, subtotal, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $subtotalItem = $item['preco'] * $item['quantidade'];
            $stmt = $pdo->prepare($sqlItem);
            $stmt->execute([$vendaId, $item['produto_id'], $item['quantidade'], $item['preco'], $subtotalItem]);
            
            // Atualizar estoque
            $sqlEstoque = "
                UPDATE produtos 
                SET estoque_atual = estoque_atual - ? 
                WHERE id = ? AND tenant_id = ?
            ";
            $stmt = $pdo->prepare($sqlEstoque);
            $stmt->execute([$item['quantidade'], $item['produto_id'], $tenantId]);
        }
        
        // Inserir pagamentos
        foreach ($pagamentos as $pagamento) {
            $sqlPagamento = "
                INSERT INTO venda_pagamentos (
                    venda_id, forma_pagamento, valor, observacoes, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ";
            
            $stmt = $pdo->prepare($sqlPagamento);
            $stmt->execute([$vendaId, $pagamento['forma'], $pagamento['valor'], $pagamento['observacoes']]);
        }
        
        // Registrar movimentação financeira
        $sqlMovimentacao = "
            INSERT INTO movimentacoes_financeiras (
                tenant_id, filial_id, tipo, categoria_id, descricao,
                valor, data_movimentacao, referencia_id, created_at
            ) VALUES (?, ?, 'entrada', 1, ?, ?, NOW(), ?, NOW())
        ";
        
        $descricao = "Venda #$vendaId - " . ($tipoVenda === 'fiado' ? 'Fiado' : 'À vista');
        $stmt = $pdo->prepare($sqlMovimentacao);
        $stmt->execute([$tenantId, $filialId, $descricao, $subtotal, $vendaId]);
        
        // Se for venda fiada, criar registro de fiado
        if ($tipoVenda === 'fiado' && $clienteId) {
            $sqlFiado = "
                INSERT INTO vendas_fiadas (
                    tenant_id, filial_id, cliente_id, data_venda, data_vencimento,
                    valor_total, valor_desconto, valor_final, valor_pago, saldo_devedor,
                    status, observacoes, created_at
                ) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY),
                         ?, 0, ?, 0, ?, 'pendente', ?, NOW())
            ";
            
            $stmt = $pdo->prepare($sqlFiado);
            $stmt->execute([$tenantId, $filialId, $clienteId, $subtotal, $subtotal, $subtotal, $observacoes]);
        }
        
        // Atualizar saldo do caixa
        $sqlCaixa = "
            UPDATE sessoes_caixa 
            SET saldo_atual = saldo_atual + ? 
            WHERE tenant_id = ? AND filial_id = ? AND status = 'aberta'
        ";
        
        $stmt = $pdo->prepare($sqlCaixa);
        $stmt->execute([$subtotal, $tenantId, $filialId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Venda finalizada com sucesso', 'venda_id' => $vendaId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fecharCaixa($pdo, $tenantId, $filialId) {
    // Verificar se há sessão de caixa aberta
    $sql = "
        SELECT * FROM sessoes_caixa 
        WHERE tenant_id = ? AND filial_id = ? AND status = 'aberta'
        ORDER BY data_abertura DESC 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessao) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma sessão de caixa aberta']);
        return;
    }
    
    // Calcular total de vendas da sessão
    $sqlVendas = "
        SELECT 
            COUNT(*) as total_vendas,
            COALESCE(SUM(valor_total), 0) as total_valor
        FROM vendas 
        WHERE tenant_id = ? AND filial_id = ? 
        AND data_venda >= ? AND data_venda <= NOW()
    ";
    
    $stmt = $pdo->prepare($sqlVendas);
    $stmt->execute([$tenantId, $filialId, $sessao['data_abertura']]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fechar sessão de caixa
    $sqlFechar = "
        UPDATE sessoes_caixa 
        SET 
            status = 'fechada',
            data_fechamento = NOW(),
            total_vendas = ?,
            total_valor = ?,
            saldo_final = saldo_atual,
            updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = $pdo->prepare($sqlFechar);
    $stmt->execute([
        $resumo['total_vendas'],
        $resumo['total_valor'],
        $sessao['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Caixa fechado com sucesso']);
}
?>
