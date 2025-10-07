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
        case 'listar_descontos':
            listarDescontos($pdo, $tenantId);
            break;
            
        case 'listar_cortesias':
            listarCortesias($pdo, $tenantId);
            break;
            
        case 'listar_promocoes':
            listarPromocoes($pdo, $tenantId);
            break;
            
        case 'listar_produtos':
            listarProdutos($pdo, $tenantId);
            break;
            
        case 'salvar_desconto':
            salvarDesconto($pdo, $tenantId, $filialId);
            break;
            
        case 'salvar_cortesia':
            salvarCortesia($pdo, $tenantId, $filialId);
            break;
            
        case 'salvar_promocao':
            salvarPromocao($pdo, $tenantId, $filialId);
            break;
            
        case 'gerar_relatorio':
            gerarRelatorio($pdo, $tenantId, $filialId);
            break;
            
        case 'editar_desconto':
            editarDesconto($pdo, $tenantId);
            break;
            
        case 'excluir_desconto':
            excluirDesconto($pdo, $tenantId);
            break;
            
        case 'editar_cortesia':
            editarCortesia($pdo, $tenantId);
            break;
            
        case 'excluir_cortesia':
            excluirCortesia($pdo, $tenantId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em descontos_cortesias.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function listarDescontos($pdo, $tenantId) {
    $sql = "
        SELECT 
            d.*,
            COUNT(du.id) as usos_atual
        FROM descontos d
        LEFT JOIN desconto_usos du ON d.id = du.desconto_id
        WHERE d.tenant_id = ?
        GROUP BY d.id
        ORDER BY d.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $descontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'descontos' => $descontos]);
}

function listarCortesias($pdo, $tenantId) {
    $sql = "
        SELECT 
            c.*,
            p.nome as produto_nome
        FROM cortesias c
        LEFT JOIN produtos p ON c.produto_id = p.id
        WHERE c.tenant_id = ?
        ORDER BY c.data_cortesia DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $cortesias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'cortesias' => $cortesias]);
}

function listarPromocoes($pdo, $tenantId) {
    $sql = "
        SELECT 
            p.*,
            COUNT(pu.id) as usos_atual
        FROM promocoes p
        LEFT JOIN promocao_usos pu ON p.id = pu.promocao_id
        WHERE p.tenant_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $promocoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'promocoes' => $promocoes]);
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

function salvarDesconto($pdo, $tenantId, $filialId) {
    $nome = $_POST['nome_desconto'] ?? '';
    $tipo = $_POST['tipo_desconto'] ?? '';
    $valor = floatval($_POST['valor_desconto'] ?? 0);
    $valorMinimo = floatval($_POST['valor_minimo'] ?? 0);
    $valorMaximo = floatval($_POST['valor_maximo'] ?? 0);
    $dataInicio = $_POST['data_inicio'] ?? null;
    $dataFim = $_POST['data_fim'] ?? null;
    $usosMaximos = intval($_POST['usos_maximos'] ?? 0);
    $observacoes = $_POST['observacoes_desconto'] ?? '';
    
    // Validar dados obrigatórios
    if (empty($nome) || empty($tipo) || $valor <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $sql = "
        INSERT INTO descontos (
            tenant_id, filial_id, nome, tipo, valor, valor_minimo, valor_maximo,
            data_inicio, data_fim, usos_maximos, observacoes, ativo, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, true, NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tenantId, $filialId, $nome, $tipo, $valor, $valorMinimo, $valorMaximo,
        $dataInicio, $dataFim, $usosMaximos, $observacoes
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Desconto salvo com sucesso']);
}

function salvarCortesia($pdo, $tenantId, $filialId) {
    $nome = $_POST['nome_cortesia'] ?? '';
    $tipo = $_POST['tipo_cortesia'] ?? '';
    $produtoId = $_POST['produto_cortesia'] ?? null;
    $valor = floatval($_POST['valor_cortesia'] ?? 0);
    $motivo = $_POST['motivo_cortesia'] ?? '';
    $responsavel = $_POST['responsavel_cortesia'] ?? '';
    
    // Validar dados obrigatórios
    if (empty($nome) || empty($tipo) || empty($motivo)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $sql = "
        INSERT INTO cortesias (
            tenant_id, filial_id, nome, tipo, produto_id, valor, motivo,
            responsavel, data_cortesia, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tenantId, $filialId, $nome, $tipo, $produtoId, $valor, $motivo, $responsavel
    ]);
    
    // Registrar movimentação financeira (saída)
    $sqlMovimentacao = "
        INSERT INTO movimentacoes_financeiras (
            tenant_id, filial_id, tipo, categoria_id, descricao,
            valor, data_movimentacao, referencia_id, created_at
        ) VALUES (?, ?, 'saida', 3, ?, ?, NOW(), ?, NOW())
    ";
    
    $descricao = "Cortesia: $nome - $motivo";
    $stmt = $pdo->prepare($sqlMovimentacao);
    $stmt->execute([$tenantId, $filialId, $descricao, $valor, $pdo->lastInsertId()]);
    
    echo json_encode(['success' => true, 'message' => 'Cortesia salva com sucesso']);
}

function salvarPromocao($pdo, $tenantId, $filialId) {
    $nome = $_POST['nome_promocao'] ?? '';
    $tipo = $_POST['tipo_promocao'] ?? '';
    $regras = $_POST['regras_promocao'] ?? '';
    $dataInicio = $_POST['data_inicio_promocao'] ?? '';
    $dataFim = $_POST['data_fim_promocao'] ?? '';
    $ativo = intval($_POST['ativo_promocao'] ?? 1);
    
    // Validar dados obrigatórios
    if (empty($nome) || empty($tipo) || empty($regras) || empty($dataInicio) || empty($dataFim)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $sql = "
        INSERT INTO promocoes (
            tenant_id, filial_id, nome, tipo, regras, data_inicio, data_fim,
            ativo, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tenantId, $filialId, $nome, $tipo, $regras, $dataInicio, $dataFim, $ativo
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Promoção salva com sucesso']);
}

function gerarRelatorio($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    
    $html = '<div class="row">';
    
    // Relatório de Descontos
    if (empty($tipo) || $tipo === 'desconto') {
        $sqlDescontos = "
            SELECT 
                d.*,
                COUNT(du.id) as usos_total,
                SUM(du.valor_desconto) as valor_total_desconto
            FROM descontos d
            LEFT JOIN desconto_usos du ON d.id = du.desconto_id
            WHERE d.tenant_id = ?
        ";
        
        $params = [$tenantId];
        
        if (!empty($dataInicio)) {
            $sqlDescontos .= " AND d.created_at >= ?";
            $params[] = $dataInicio;
        }
        
        if (!empty($dataFim)) {
            $sqlDescontos .= " AND d.created_at <= ?";
            $params[] = $dataFim;
        }
        
        $sqlDescontos .= " GROUP BY d.id ORDER BY d.created_at DESC";
        
        $stmt = $pdo->prepare($sqlDescontos);
        $stmt->execute($params);
        $descontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html .= '
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Relatório de Descontos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Usos</th>
                                        <th>Total Desconto</th>
                                    </tr>
                                </thead>
                                <tbody>
        ';
        
        foreach ($descontos as $desconto) {
            $valor = $desconto['tipo'] === 'percentual' ? $desconto['valor'] . '%' : 'R$ ' . number_format($desconto['valor'], 2, ',', '.');
            $totalDesconto = $desconto['valor_total_desconto'] ?? 0;
            
            $html .= "
                <tr>
                    <td>{$desconto['nome']}</td>
                    <td>{$desconto['tipo']}</td>
                    <td>{$valor}</td>
                    <td>{$desconto['usos_total']}</td>
                    <td>R$ " . number_format($totalDesconto, 2, ',', '.') . "</td>
                </tr>
            ";
        }
        
        $html .= '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
    
    // Relatório de Cortesias
    if (empty($tipo) || $tipo === 'cortesia') {
        $sqlCortesias = "
            SELECT 
                c.*,
                p.nome as produto_nome
            FROM cortesias c
            LEFT JOIN produtos p ON c.produto_id = p.id
            WHERE c.tenant_id = ?
        ";
        
        $params = [$tenantId];
        
        if (!empty($dataInicio)) {
            $sqlCortesias .= " AND c.data_cortesia >= ?";
            $params[] = $dataInicio;
        }
        
        if (!empty($dataFim)) {
            $sqlCortesias .= " AND c.data_cortesia <= ?";
            $params[] = $dataFim;
        }
        
        $sqlCortesias .= " ORDER BY c.data_cortesia DESC";
        
        $stmt = $pdo->prepare($sqlCortesias);
        $stmt->execute($params);
        $cortesias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html .= '
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Relatório de Cortesias</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Motivo</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
        ';
        
        foreach ($cortesias as $cortesia) {
            $html .= "
                <tr>
                    <td>{$cortesia['nome']}</td>
                    <td>{$cortesia['tipo']}</td>
                    <td>R$ " . number_format($cortesia['valor'], 2, ',', '.') . "</td>
                    <td>{$cortesia['motivo']}</td>
                    <td>" . date('d/m/Y', strtotime($cortesia['data_cortesia'])) . "</td>
                </tr>
            ";
        }
        
        $html .= '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        ';
    }
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
}

function editarDesconto($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do desconto não fornecido']);
        return;
    }
    
    // Implementar lógica de edição
    echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
}

function excluirDesconto($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do desconto não fornecido']);
        return;
    }
    
    $sql = "DELETE FROM descontos WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $tenantId]);
    
    echo json_encode(['success' => true, 'message' => 'Desconto excluído com sucesso']);
}

function editarCortesia($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID da cortesia não fornecido']);
        return;
    }
    
    // Implementar lógica de edição
    echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
}

function excluirCortesia($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID da cortesia não fornecido']);
        return;
    }
    
    $sql = "DELETE FROM cortesias WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $tenantId]);
    
    echo json_encode(['success' => true, 'message' => 'Cortesia excluída com sucesso']);
}
?>
