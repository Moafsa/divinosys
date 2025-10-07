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

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    switch ($acao) {
        case 'listar_vendas':
            listarVendasFiadas($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_vendas_filtradas':
            listarVendasFiadasFiltradas($pdo, $tenantId, $filialId);
            break;
            
        case 'listar_produtos':
            listarProdutos($pdo, $tenantId);
            break;
            
        case 'criar_venda':
            criarVendaFiada($pdo, $tenantId, $filialId);
            break;
            
        case 'obter_detalhes_venda':
            obterDetalhesVenda($pdo, $tenantId);
            break;
            
        case 'obter_detalhes_completos':
            obterDetalhesCompletos($pdo, $tenantId);
            break;
            
        case 'registrar_pagamento':
            registrarPagamento($pdo, $tenantId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em vendas_fiadas.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function listarVendasFiadas($pdo, $tenantId, $filialId) {
    $sql = "
        SELECT 
            vf.id,
            vf.data_venda,
            vf.data_vencimento,
            vf.valor_total,
            vf.valor_pago,
            vf.saldo_devedor,
            vf.status,
            vf.observacoes,
            cf.nome as cliente_nome,
            cf.telefone as cliente_telefone
        FROM vendas_fiadas vf
        JOIN clientes_fiado cf ON vf.cliente_id = cf.id
        WHERE vf.tenant_id = ? AND vf.filial_id = ?
        ORDER BY vf.data_venda DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $filialId]);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'vendas' => $vendas]);
}

function listarVendasFiadasFiltradas($pdo, $tenantId, $filialId) {
    $status = $_POST['status'] ?? '';
    $cliente = $_POST['cliente'] ?? '';
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    
    $sql = "
        SELECT 
            vf.id,
            vf.data_venda,
            vf.data_vencimento,
            vf.valor_total,
            vf.valor_pago,
            vf.saldo_devedor,
            vf.status,
            vf.observacoes,
            cf.nome as cliente_nome,
            cf.telefone as cliente_telefone
        FROM vendas_fiadas vf
        JOIN clientes_fiado cf ON vf.cliente_id = cf.id
        WHERE vf.tenant_id = ? AND vf.filial_id = ?
    ";
    
    $params = [$tenantId, $filialId];
    
    if (!empty($status)) {
        $sql .= " AND vf.status = ?";
        $params[] = $status;
    }
    
    if (!empty($cliente)) {
        $sql .= " AND vf.cliente_id = ?";
        $params[] = $cliente;
    }
    
    if (!empty($dataInicio)) {
        $sql .= " AND vf.data_venda >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $sql .= " AND vf.data_venda <= ?";
        $params[] = $dataFim;
    }
    
    $sql .= " ORDER BY vf.data_venda DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'vendas' => $vendas]);
}

function listarProdutos($pdo, $tenantId) {
    $sql = "
        SELECT id, nome, preco, categoria_id
        FROM produtos 
        WHERE tenant_id = ? AND ativo = true
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'produtos' => $produtos]);
}

function criarVendaFiada($pdo, $tenantId, $filialId) {
    $clienteId = $_POST['cliente_id'] ?? '';
    $dataVencimento = $_POST['data_vencimento'] ?? '';
    $valorTotal = floatval($_POST['valor_total'] ?? 0);
    $desconto = floatval($_POST['desconto'] ?? 0);
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Validar dados obrigatórios
    if (empty($clienteId) || empty($dataVencimento) || $valorTotal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Calcular valor final com desconto
        $valorFinal = $valorTotal - $desconto;
        
        // Inserir venda fiada
        $sqlVenda = "
            INSERT INTO vendas_fiadas (
                tenant_id, filial_id, cliente_id, data_venda, data_vencimento,
                valor_total, valor_desconto, valor_final, valor_pago, saldo_devedor,
                status, observacoes, created_at
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 0, ?, 'pendente', ?, NOW())
        ";
        
        $stmt = $pdo->prepare($sqlVenda);
        $stmt->execute([
            $tenantId, $filialId, $clienteId, $dataVencimento,
            $valorTotal, $desconto, $valorFinal, $valorFinal, $observacoes
        ]);
        
        $vendaId = $pdo->lastInsertId();
        
        // Processar produtos se fornecidos
        if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
            $produtoIds = $_POST['produto_id'];
            $quantidades = $_POST['quantidade'] ?? [];
            $precosUnitarios = $_POST['preco_unitario'] ?? [];
            
            for ($i = 0; $i < count($produtoIds); $i++) {
                if (!empty($produtoIds[$i])) {
                    $sqlItem = "
                        INSERT INTO vendas_fiadas_itens (
                            venda_id, produto_id, quantidade, preco_unitario, 
                            subtotal, created_at
                        ) VALUES (?, ?, ?, ?, ?, NOW())
                    ";
                    
                    $quantidade = floatval($quantidades[$i] ?? 1);
                    $precoUnitario = floatval($precosUnitarios[$i] ?? 0);
                    $subtotal = $quantidade * $precoUnitario;
                    
                    $stmt = $pdo->prepare($sqlItem);
                    $stmt->execute([
                        $vendaId, $produtoIds[$i], $quantidade, $precoUnitario, $subtotal
                    ]);
                }
            }
        }
        
        // Registrar movimentação financeira
        $sqlMovimentacao = "
            INSERT INTO movimentacoes_financeiras (
                tenant_id, filial_id, tipo, categoria_id, descricao,
                valor, data_movimentacao, referencia_id, created_at
            ) VALUES (?, ?, 'entrada', 1, ?, ?, NOW(), ?, NOW())
        ";
        
        $descricao = "Venda fiada - Cliente ID: $clienteId";
        $stmt = $pdo->prepare($sqlMovimentacao);
        $stmt->execute([$tenantId, $filialId, $descricao, $valorFinal, $vendaId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Venda fiada criada com sucesso', 'venda_id' => $vendaId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function obterDetalhesVenda($pdo, $tenantId) {
    $vendaId = $_POST['venda_id'] ?? '';
    
    if (empty($vendaId)) {
        echo json_encode(['success' => false, 'message' => 'ID da venda não fornecido']);
        return;
    }
    
    $sql = "
        SELECT 
            vf.*,
            cf.nome as cliente_nome,
            cf.telefone as cliente_telefone,
            cf.email as cliente_email
        FROM vendas_fiadas vf
        JOIN clientes_fiado cf ON vf.cliente_id = cf.id
        WHERE vf.id = ? AND vf.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vendaId, $tenantId]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venda) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
        return;
    }
    
    echo json_encode(['success' => true, 'venda' => $venda]);
}

function obterDetalhesCompletos($pdo, $tenantId) {
    $vendaId = $_POST['venda_id'] ?? '';
    
    if (empty($vendaId)) {
        echo json_encode(['success' => false, 'message' => 'ID da venda não fornecido']);
        return;
    }
    
    // Obter dados da venda
    $sqlVenda = "
        SELECT 
            vf.*,
            cf.nome as cliente_nome,
            cf.telefone as cliente_telefone,
            cf.email as cliente_email,
            cf.endereco as cliente_endereco
        FROM vendas_fiadas vf
        JOIN clientes_fiado cf ON vf.cliente_id = cf.id
        WHERE vf.id = ? AND vf.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($sqlVenda);
    $stmt->execute([$vendaId, $tenantId]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venda) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
        return;
    }
    
    // Obter itens da venda
    $sqlItens = "
        SELECT 
            vfi.*,
            p.nome as produto_nome,
            p.descricao as produto_descricao
        FROM vendas_fiadas_itens vfi
        LEFT JOIN produtos p ON vfi.produto_id = p.id
        WHERE vfi.venda_id = ?
        ORDER BY vfi.id
    ";
    
    $stmt = $pdo->prepare($sqlItens);
    $stmt->execute([$vendaId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obter histórico de pagamentos
    $sqlPagamentos = "
        SELECT *
        FROM pagamentos_fiado
        WHERE venda_id = ?
        ORDER BY data_pagamento DESC
    ";
    
    $stmt = $pdo->prepare($sqlPagamentos);
    $stmt->execute([$vendaId]);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gerar HTML dos detalhes
    $html = gerarHtmlDetalhesVenda($venda, $itens, $pagamentos);
    
    echo json_encode(['success' => true, 'html' => $html]);
}

function registrarPagamento($pdo, $tenantId) {
    $vendaId = $_POST['venda_id'] ?? '';
    $valorPagamento = floatval($_POST['valor_pagamento'] ?? 0);
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    $observacoes = $_POST['observacoes_pagamento'] ?? '';
    
    if (empty($vendaId) || $valorPagamento <= 0 || empty($formaPagamento)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Obter dados atuais da venda
        $sqlVenda = "SELECT * FROM vendas_fiadas WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sqlVenda);
        $stmt->execute([$vendaId, $tenantId]);
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$venda) {
            throw new Exception('Venda não encontrada');
        }
        
        // Verificar se o valor do pagamento não excede o saldo devedor
        if ($valorPagamento > $venda['saldo_devedor']) {
            throw new Exception('Valor do pagamento excede o saldo devedor');
        }
        
        // Inserir pagamento
        $sqlPagamento = "
            INSERT INTO pagamentos_fiado (
                venda_id, valor_pagamento, forma_pagamento, data_pagamento,
                observacoes, created_at
            ) VALUES (?, ?, ?, NOW(), ?, NOW())
        ";
        
        $stmt = $pdo->prepare($sqlPagamento);
        $stmt->execute([$vendaId, $valorPagamento, $formaPagamento, $observacoes]);
        
        // Atualizar venda
        $novoValorPago = $venda['valor_pago'] + $valorPagamento;
        $novoSaldoDevedor = $venda['saldo_devedor'] - $valorPagamento;
        $novoStatus = $novoSaldoDevedor <= 0 ? 'pago' : 'parcial';
        
        $sqlUpdateVenda = "
            UPDATE vendas_fiadas 
            SET valor_pago = ?, saldo_devedor = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sqlUpdateVenda);
        $stmt->execute([$novoValorPago, $novoSaldoDevedor, $novoStatus, $vendaId]);
        
        // Registrar movimentação financeira
        $sqlMovimentacao = "
            INSERT INTO movimentacoes_financeiras (
                tenant_id, filial_id, tipo, categoria_id, descricao,
                valor, data_movimentacao, referencia_id, created_at
            ) VALUES (?, ?, 'entrada', 2, ?, ?, NOW(), ?, NOW())
        ";
        
        $descricao = "Pagamento fiado - Venda ID: $vendaId - $formaPagamento";
        $stmt = $pdo->prepare($sqlMovimentacao);
        $stmt->execute([$tenantId, $filialId, $descricao, $valorPagamento, $vendaId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pagamento registrado com sucesso']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function gerarHtmlDetalhesVenda($venda, $itens, $pagamentos) {
    $statusClass = '';
    $statusText = '';
    
    switch($venda['status']) {
        case 'pendente':
            $statusClass = 'bg-warning';
            $statusText = 'Pendente';
            break;
        case 'parcial':
            $statusClass = 'bg-info';
            $statusText = 'Pago Parcial';
            break;
        case 'pago':
            $statusClass = 'bg-success';
            $statusText = 'Pago';
            break;
        case 'vencido':
            $statusClass = 'bg-danger';
            $statusText = 'Vencido';
            break;
    }
    
    $html = "
        <div class='row'>
            <div class='col-md-6'>
                <h5>Informações da Venda</h5>
                <table class='table table-sm'>
                    <tr><td><strong>ID:</strong></td><td>{$venda['id']}</td></tr>
                    <tr><td><strong>Data:</strong></td><td>" . date('d/m/Y H:i', strtotime($venda['data_venda'])) . "</td></tr>
                    <tr><td><strong>Vencimento:</strong></td><td>" . date('d/m/Y', strtotime($venda['data_vencimento'])) . "</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class='badge $statusClass'>$statusText</span></td></tr>
                </table>
            </div>
            <div class='col-md-6'>
                <h5>Informações do Cliente</h5>
                <table class='table table-sm'>
                    <tr><td><strong>Nome:</strong></td><td>{$venda['cliente_nome']}</td></tr>
                    <tr><td><strong>Telefone:</strong></td><td>{$venda['cliente_telefone']}</td></tr>
                    <tr><td><strong>Email:</strong></td><td>{$venda['cliente_email']}</td></tr>
                </table>
            </div>
        </div>
        
        <div class='row mt-3'>
            <div class='col-md-6'>
                <h5>Valores</h5>
                <table class='table table-sm'>
                    <tr><td><strong>Valor Total:</strong></td><td>R$ " . number_format($venda['valor_total'], 2, ',', '.') . "</td></tr>
                    <tr><td><strong>Desconto:</strong></td><td>R$ " . number_format($venda['valor_desconto'], 2, ',', '.') . "</td></tr>
                    <tr><td><strong>Valor Final:</strong></td><td>R$ " . number_format($venda['valor_final'], 2, ',', '.') . "</td></tr>
                    <tr><td><strong>Valor Pago:</strong></td><td>R$ " . number_format($venda['valor_pago'], 2, ',', '.') . "</td></tr>
                    <tr><td><strong>Saldo Devedor:</strong></td><td><strong>R$ " . number_format($venda['saldo_devedor'], 2, ',', '.') . "</strong></td></tr>
                </table>
            </div>
        </div>
    ";
    
    if (!empty($itens)) {
        $html .= "
            <div class='row mt-3'>
                <div class='col-12'>
                    <h5>Itens da Venda</h5>
                    <table class='table table-sm'>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Preço Unitário</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
        ";
        
        foreach ($itens as $item) {
            $html .= "
                <tr>
                    <td>{$item['produto_nome']}</td>
                    <td>{$item['quantidade']}</td>
                    <td>R$ " . number_format($item['preco_unitario'], 2, ',', '.') . "</td>
                    <td>R$ " . number_format($item['subtotal'], 2, ',', '.') . "</td>
                </tr>
            ";
        }
        
        $html .= "
                        </tbody>
                    </table>
                </div>
            </div>
        ";
    }
    
    if (!empty($pagamentos)) {
        $html .= "
            <div class='row mt-3'>
                <div class='col-12'>
                    <h5>Histórico de Pagamentos</h5>
                    <table class='table table-sm'>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Forma de Pagamento</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
        ";
        
        foreach ($pagamentos as $pagamento) {
            $html .= "
                <tr>
                    <td>" . date('d/m/Y H:i', strtotime($pagamento['data_pagamento'])) . "</td>
                    <td>R$ " . number_format($pagamento['valor_pagamento'], 2, ',', '.') . "</td>
                    <td>{$pagamento['forma_pagamento']}</td>
                    <td>{$pagamento['observacoes']}</td>
                </tr>
            ";
        }
        
        $html .= "
                        </tbody>
                    </table>
                </div>
            </div>
        ";
    }
    
    if (!empty($venda['observacoes'])) {
        $html .= "
            <div class='row mt-3'>
                <div class='col-12'>
                    <h5>Observações</h5>
                    <p>{$venda['observacoes']}</p>
                </div>
            </div>
        ";
    }
    
    return $html;
}
?>
