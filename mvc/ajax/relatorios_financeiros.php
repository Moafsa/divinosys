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
        case 'listar_filiais':
            listarFiliais($pdo, $tenantId);
            break;
            
        case 'listar_categorias_financeiras':
            listarCategoriasFinanceiras($pdo, $tenantId);
            break;
            
        case 'listar_categorias_produtos':
            listarCategoriasProdutos($pdo, $tenantId);
            break;
            
        case 'listar_usuarios':
            listarUsuarios($pdo, $tenantId);
            break;
            
        case 'resumo_geral':
            resumoGeral($pdo, $tenantId, $filialId);
            break;
            
        case 'relatorio_vendas':
            relatorioVendas($pdo, $tenantId, $filialId);
            break;
            
        case 'relatorio_financeiro':
            relatorioFinanceiro($pdo, $tenantId, $filialId);
            break;
            
        case 'relatorio_clientes':
            relatorioClientes($pdo, $tenantId, $filialId);
            break;
            
        case 'relatorio_produtos':
            relatorioProdutos($pdo, $tenantId, $filialId);
            break;
            
        case 'relatorio_operacional':
            relatorioOperacional($pdo, $tenantId, $filialId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em relatorios_financeiros.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function listarFiliais($pdo, $tenantId) {
    $sql = "
        SELECT id, nome
        FROM filiais 
        WHERE tenant_id = ? AND ativo = true
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'filiais' => $filiais]);
}

function listarCategoriasFinanceiras($pdo, $tenantId) {
    $sql = "
        SELECT id, nome
        FROM categorias_financeiras 
        WHERE tenant_id = ?
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'categorias' => $categorias]);
}

function listarCategoriasProdutos($pdo, $tenantId) {
    $sql = "
        SELECT id, nome
        FROM categorias 
        WHERE tenant_id = ?
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'categorias' => $categorias]);
}

function listarUsuarios($pdo, $tenantId) {
    $sql = "
        SELECT id, nome
        FROM usuarios 
        WHERE tenant_id = ? AND ativo = true
        ORDER BY nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'usuarios' => $usuarios]);
}

function resumoGeral($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    
    $whereConditions = ["v.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($dataInicio)) {
        $whereConditions[] = "DATE(v.data_venda) >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $whereConditions[] = "DATE(v.data_venda) <= ?";
        $params[] = $dataFim;
    }
    
    if (!empty($filial)) {
        $whereConditions[] = "v.filial_id = ?";
        $params[] = $filial;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Total de vendas
    $sqlVendas = "
        SELECT COALESCE(SUM(v.valor_total), 0) as total_vendas
        FROM vendas v
        WHERE $whereClause
    ";
    
    $stmt = $pdo->prepare($sqlVendas);
    $stmt->execute($params);
    $totalVendas = $stmt->fetch(PDO::FETCH_ASSOC)['total_vendas'];
    
    // Vendas fiadas
    $sqlFiadas = "
        SELECT COALESCE(SUM(vf.valor_final), 0) as vendas_fiadas
        FROM vendas_fiadas vf
        WHERE vf.tenant_id = ? AND DATE(vf.data_venda) >= ? AND DATE(vf.data_venda) <= ?
    ";
    
    $stmt = $pdo->prepare($sqlFiadas);
    $stmt->execute([$tenantId, $dataInicio ?: '1900-01-01', $dataFim ?: '2099-12-31']);
    $vendasFiadas = $stmt->fetch(PDO::FETCH_ASSOC)['vendas_fiadas'];
    
    // Total de descontos
    $sqlDescontos = "
        SELECT COALESCE(SUM(du.valor_desconto), 0) as total_descontos
        FROM desconto_usos du
        JOIN descontos d ON du.desconto_id = d.id
        WHERE d.tenant_id = ? AND DATE(du.data_uso) >= ? AND DATE(du.data_uso) <= ?
    ";
    
    $stmt = $pdo->prepare($sqlDescontos);
    $stmt->execute([$tenantId, $dataInicio ?: '1900-01-01', $dataFim ?: '2099-12-31']);
    $totalDescontos = $stmt->fetch(PDO::FETCH_ASSOC)['total_descontos'];
    
    // Total de cortesias
    $sqlCortesias = "
        SELECT COALESCE(SUM(c.valor), 0) as total_cortesias
        FROM cortesias c
        WHERE c.tenant_id = ? AND DATE(c.data_cortesia) >= ? AND DATE(c.data_cortesia) <= ?
    ";
    
    $stmt = $pdo->prepare($sqlCortesias);
    $stmt->execute([$tenantId, $dataInicio ?: '1900-01-01', $dataFim ?: '2099-12-31']);
    $totalCortesias = $stmt->fetch(PDO::FETCH_ASSOC)['total_cortesias'];
    
    echo json_encode([
        'success' => true,
        'resumo' => [
            'total_vendas' => floatval($totalVendas),
            'vendas_fiadas' => floatval($vendasFiadas),
            'total_descontos' => floatval($totalDescontos),
            'total_cortesias' => floatval($totalCortesias)
        ]
    ]);
}

function relatorioVendas($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $tipoVenda = $_POST['tipo_venda'] ?? '';
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    
    $whereConditions = ["v.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($dataInicio)) {
        $whereConditions[] = "DATE(v.data_venda) >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $whereConditions[] = "DATE(v.data_venda) <= ?";
        $params[] = $dataFim;
    }
    
    if (!empty($filial)) {
        $whereConditions[] = "v.filial_id = ?";
        $params[] = $filial;
    }
    
    if (!empty($tipoVenda)) {
        $whereConditions[] = "v.tipo_venda = ?";
        $params[] = $tipoVenda;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            v.id,
            v.data_venda,
            v.tipo_venda,
            v.valor_total,
            v.status,
            c.nome as cliente_nome,
            GROUP_CONCAT(vp.forma_pagamento SEPARATOR ', ') as formas_pagamento
        FROM vendas v
        LEFT JOIN clientes_fiado c ON v.cliente_id = c.id
        LEFT JOIN venda_pagamentos vp ON v.id = vp.venda_id
        WHERE $whereClause
        GROUP BY v.id
        ORDER BY v.data_venda DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioVendas($vendas);
    echo json_encode(['success' => true, 'html' => $html]);
}

function relatorioFinanceiro($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $tipoMovimentacao = $_POST['tipo_movimentacao'] ?? '';
    
    $whereConditions = ["mf.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($dataInicio)) {
        $whereConditions[] = "DATE(mf.data_movimentacao) >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $whereConditions[] = "DATE(mf.data_movimentacao) <= ?";
        $params[] = $dataFim;
    }
    
    if (!empty($filial)) {
        $whereConditions[] = "mf.filial_id = ?";
        $params[] = $filial;
    }
    
    if (!empty($categoria)) {
        $whereConditions[] = "mf.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if (!empty($tipoMovimentacao)) {
        $whereConditions[] = "mf.tipo = ?";
        $params[] = $tipoMovimentacao;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            mf.*,
            cf.nome as categoria_nome
        FROM movimentacoes_financeiras mf
        LEFT JOIN categorias_financeiras cf ON mf.categoria_id = cf.id
        WHERE $whereClause
        ORDER BY mf.data_movimentacao DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioFinanceiro($movimentacoes);
    echo json_encode(['success' => true, 'html' => $html]);
}

function relatorioClientes($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $statusCliente = $_POST['status_cliente'] ?? '';
    $tipoCliente = $_POST['tipo_cliente'] ?? '';
    
    $whereConditions = ["c.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($statusCliente)) {
        $whereConditions[] = "c.status = ?";
        $params[] = $statusCliente;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            c.*,
            COUNT(vf.id) as total_vendas_fiadas,
            COALESCE(SUM(vf.valor_final), 0) as valor_total_fiado,
            COALESCE(SUM(vf.valor_pago), 0) as valor_pago_fiado,
            COALESCE(SUM(vf.saldo_devedor), 0) as saldo_devedor
        FROM clientes_fiado c
        LEFT JOIN vendas_fiadas vf ON c.id = vf.cliente_id
        WHERE $whereClause
        GROUP BY c.id
        ORDER BY c.nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioClientes($clientes);
    echo json_encode(['success' => true, 'html' => $html]);
}

function relatorioProdutos($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $whereConditions = ["p.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($categoria)) {
        $whereConditions[] = "p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "p.ativo = ?";
        $params[] = ($status === 'ativo' ? 1 : 0);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            p.*,
            cat.nome as categoria_nome,
            COUNT(vi.id) as total_vendido,
            COALESCE(SUM(vi.quantidade), 0) as quantidade_vendida,
            COALESCE(SUM(vi.subtotal), 0) as valor_total_vendido
        FROM produtos p
        LEFT JOIN categorias cat ON p.categoria_id = cat.id
        LEFT JOIN venda_itens vi ON p.id = vi.produto_id
        LEFT JOIN vendas v ON vi.venda_id = v.id
        WHERE $whereClause
        GROUP BY p.id
        ORDER BY p.nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioProdutos($produtos);
    echo json_encode(['success' => true, 'html' => $html]);
}

function relatorioOperacional($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $filial = $_POST['filial'] ?? '';
    $tipoOperacao = $_POST['tipo_operacao'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    
    $whereConditions = ["a.tenant_id = ?"];
    $params = [$tenantId];
    
    if (!empty($dataInicio)) {
        $whereConditions[] = "DATE(a.data_atividade) >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $whereConditions[] = "DATE(a.data_atividade) <= ?";
        $params[] = $dataFim;
    }
    
    if (!empty($filial)) {
        $whereConditions[] = "a.filial_id = ?";
        $params[] = $filial;
    }
    
    if (!empty($tipoOperacao)) {
        $whereConditions[] = "a.tipo_atividade = ?";
        $params[] = $tipoOperacao;
    }
    
    if (!empty($usuario)) {
        $whereConditions[] = "a.usuario_id = ?";
        $params[] = $usuario;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            a.*,
            u.nome as usuario_nome
        FROM atividade a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE $whereClause
        ORDER BY a.data_atividade DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHtmlRelatorioOperacional($atividades);
    echo json_encode(['success' => true, 'html' => $html]);
}

// Funções para gerar HTML dos relatórios
function gerarHtmlRelatorioVendas($vendas) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Formas de Pagamento</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($vendas as $venda) {
        $tipoClass = $venda['tipo_venda'] === 'fiado' ? 'text-warning' : 'text-success';
        $tipoText = ucfirst($venda['tipo_venda']);
        
        $html .= "
            <tr>
                <td>{$venda['id']}</td>
                <td>" . date('d/m/Y H:i', strtotime($venda['data_venda'])) . "</td>
                <td>{$venda['cliente_nome']}</td>
                <td><span class='{$tipoClass}'>{$tipoText}</span></td>
                <td>R$ " . number_format($venda['valor_total'], 2, ',', '.') . "</td>
                <td>{$venda['formas_pagamento']}</td>
                <td><span class='badge bg-success'>{$venda['status']}</span></td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}

function gerarHtmlRelatorioFinanceiro($movimentacoes) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Categoria</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($movimentacoes as $mov) {
        $tipoClass = $mov['tipo'] === 'entrada' ? 'text-success' : 'text-danger';
        $tipoText = $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída';
        
        $html .= "
            <tr>
                <td>" . date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) . "</td>
                <td><span class='{$tipoClass}'>{$tipoText}</span></td>
                <td>{$mov['descricao']}</td>
                <td class='{$tipoClass}'>R$ " . number_format($mov['valor'], 2, ',', '.') . "</td>
                <td>{$mov['categoria_nome']}</td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}

function gerarHtmlRelatorioClientes($clientes) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Vendas Fiadas</th>
                        <th>Valor Total</th>
                        <th>Valor Pago</th>
                        <th>Saldo Devedor</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($clientes as $cliente) {
        $statusClass = $cliente['status'] === 'ativo' ? 'bg-success' : 'bg-secondary';
        $statusText = ucfirst($cliente['status']);
        
        $html .= "
            <tr>
                <td>{$cliente['nome']}</td>
                <td>{$cliente['telefone']}</td>
                <td>{$cliente['email']}</td>
                <td><span class='badge {$statusClass}'>{$statusText}</span></td>
                <td>{$cliente['total_vendas_fiadas']}</td>
                <td>R$ " . number_format($cliente['valor_total_fiado'], 2, ',', '.') . "</td>
                <td>R$ " . number_format($cliente['valor_pago_fiado'], 2, ',', '.') . "</td>
                <td class='text-danger'>R$ " . number_format($cliente['saldo_devedor'], 2, ',', '.') . "</td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}

function gerarHtmlRelatorioProdutos($produtos) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Vendas</th>
                        <th>Quantidade Vendida</th>
                        <th>Valor Total</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($produtos as $produto) {
        $statusClass = $produto['ativo'] ? 'bg-success' : 'bg-secondary';
        $statusText = $produto['ativo'] ? 'Ativo' : 'Inativo';
        
        $html .= "
            <tr>
                <td>{$produto['nome']}</td>
                <td>{$produto['categoria_nome']}</td>
                <td>R$ " . number_format($produto['preco'], 2, ',', '.') . "</td>
                <td>{$produto['estoque_atual']}</td>
                <td><span class='badge {$statusClass}'>{$statusText}</span></td>
                <td>{$produto['total_vendido']}</td>
                <td>{$produto['quantidade_vendida']}</td>
                <td>R$ " . number_format($produto['valor_total_vendido'], 2, ',', '.') . "</td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}

function gerarHtmlRelatorioOperacional($atividades) {
    $html = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
    ';
    
    foreach ($atividades as $atividade) {
        $html .= "
            <tr>
                <td>" . date('d/m/Y H:i', strtotime($atividade['data_atividade'])) . "</td>
                <td>{$atividade['usuario_nome']}</td>
                <td><span class='badge bg-info'>{$atividade['tipo_atividade']}</span></td>
                <td>{$atividade['descricao']}</td>
                <td>{$atividade['ip']}</td>
            </tr>
        ";
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
    ';
    
    return $html;
}
?>
