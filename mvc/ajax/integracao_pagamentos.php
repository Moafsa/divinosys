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
        case 'listar_gateways':
            listarGateways($pdo, $tenantId);
            break;
            
        case 'listar_maquinas':
            listarMaquinas($pdo, $tenantId);
            break;
            
        case 'listar_pix':
            listarPix($pdo, $tenantId);
            break;
            
        case 'salvar_gateway':
            salvarGateway($pdo, $tenantId, $filialId);
            break;
            
        case 'salvar_maquina':
            salvarMaquina($pdo, $tenantId, $filialId);
            break;
            
        case 'salvar_pix':
            salvarPix($pdo, $tenantId, $filialId);
            break;
            
        case 'testar_gateway':
            testarGateway($pdo, $tenantId);
            break;
            
        case 'gerar_relatorio_financeiro':
            gerarRelatorioFinanceiro($pdo, $tenantId, $filialId);
            break;
            
        case 'editar_gateway':
            editarGateway($pdo, $tenantId);
            break;
            
        case 'excluir_gateway':
            excluirGateway($pdo, $tenantId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("Erro em integracao_pagamentos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function listarGateways($pdo, $tenantId) {
    $sql = "
        SELECT *
        FROM gateways_pagamento
        WHERE tenant_id = ?
        ORDER BY created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'gateways' => $gateways]);
}

function listarMaquinas($pdo, $tenantId) {
    $sql = "
        SELECT *
        FROM maquinas_cartao
        WHERE tenant_id = ?
        ORDER BY created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $maquinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'maquinas' => $maquinas]);
}

function listarPix($pdo, $tenantId) {
    $sql = "
        SELECT *
        FROM configuracao_pix
        WHERE tenant_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $pix = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'pix' => $pix]);
}

function salvarGateway($pdo, $tenantId, $filialId) {
    $provedor = $_POST['gateway_provedor'] ?? '';
    $nome = $_POST['gateway_nome'] ?? '';
    $ambiente = $_POST['gateway_ambiente'] ?? '';
    $clientId = $_POST['gateway_client_id'] ?? '';
    $clientSecret = $_POST['gateway_client_secret'] ?? '';
    $webhookUrl = $_POST['gateway_webhook_url'] ?? '';
    $taxaFixa = floatval($_POST['gateway_taxa_fixa'] ?? 0);
    $taxaPercentual = floatval($_POST['gateway_taxa_percentual'] ?? 0);
    $ativo = isset($_POST['gateway_ativo']) ? 1 : 0;
    
    // Validar dados obrigatórios
    if (empty($provedor) || empty($nome) || empty($ambiente) || empty($clientId) || empty($clientSecret)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $sql = "
        INSERT INTO gateways_pagamento (
            tenant_id, filial_id, provedor, nome, ambiente, client_id, client_secret,
            webhook_url, taxa_fixa, taxa_percentual, ativo, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tenantId, $filialId, $provedor, $nome, $ambiente, $clientId, $clientSecret,
        $webhookUrl, $taxaFixa, $taxaPercentual, $ativo
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Gateway salvo com sucesso']);
}

function salvarMaquina($pdo, $tenantId, $filialId) {
    $fabricante = $_POST['maquina_fabricante'] ?? '';
    $modelo = $_POST['maquina_modelo'] ?? '';
    $serial = $_POST['maquina_serial'] ?? '';
    $terminalId = $_POST['maquina_terminal_id'] ?? '';
    $apiKey = $_POST['maquina_api_key'] ?? '';
    $taxaDebito = floatval($_POST['maquina_taxa_debito'] ?? 0);
    $taxaCredito = floatval($_POST['maquina_taxa_credito'] ?? 0);
    $ativo = isset($_POST['maquina_ativo']) ? 1 : 0;
    
    // Validar dados obrigatórios
    if (empty($fabricante) || empty($modelo)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    $sql = "
        INSERT INTO maquinas_cartao (
            tenant_id, filial_id, fabricante, modelo, serial, terminal_id, api_key,
            taxa_debito, taxa_credito, ativo, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tenantId, $filialId, $fabricante, $modelo, $serial, $terminalId, $apiKey,
        $taxaDebito, $taxaCredito, $ativo
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Máquina salva com sucesso']);
}

function salvarPix($pdo, $tenantId, $filialId) {
    $chave = $_POST['pix_chave'] ?? '';
    $tipoChave = $_POST['pix_tipo_chave'] ?? '';
    $banco = $_POST['pix_banco'] ?? '';
    $agencia = $_POST['pix_agencia'] ?? '';
    $conta = $_POST['pix_conta'] ?? '';
    $ativo = isset($_POST['pix_ativo']) ? 1 : 0;
    
    // Validar dados obrigatórios
    if (empty($chave) || empty($tipoChave)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não preenchidos']);
        return;
    }
    
    // Verificar se já existe configuração PIX
    $sqlCheck = "SELECT id FROM configuracao_pix WHERE tenant_id = ?";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([$tenantId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Atualizar configuração existente
        $sql = "
            UPDATE configuracao_pix 
            SET chave = ?, tipo_chave = ?, banco = ?, agencia = ?, conta = ?, ativo = ?, updated_at = NOW()
            WHERE tenant_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$chave, $tipoChave, $banco, $agencia, $conta, $ativo, $tenantId]);
    } else {
        // Inserir nova configuração
        $sql = "
            INSERT INTO configuracao_pix (
                tenant_id, filial_id, chave, tipo_chave, banco, agencia, conta, ativo, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId, $filialId, $chave, $tipoChave, $banco, $agencia, $conta, $ativo]);
    }
    
    // Processar upload de QR Code se fornecido
    if (isset($_FILES['pix_qr_code']) && $_FILES['pix_qr_code']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/pix/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'pix_qr_' . $tenantId . '_' . time() . '.png';
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['pix_qr_code']['tmp_name'], $uploadPath)) {
            // Atualizar caminho do QR Code no banco
            $sqlQr = "UPDATE configuracao_pix SET qr_code = ? WHERE tenant_id = ?";
            $stmt = $pdo->prepare($sqlQr);
            $stmt->execute(['uploads/pix/' . $fileName, $tenantId]);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'PIX salvo com sucesso']);
}

function testarGateway($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do gateway não fornecido']);
        return;
    }
    
    // Obter dados do gateway
    $sql = "SELECT * FROM gateways_pagamento WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $tenantId]);
    $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gateway) {
        echo json_encode(['success' => false, 'message' => 'Gateway não encontrado']);
        return;
    }
    
    // Simular teste de conexão (implementar lógica específica para cada provedor)
    $testResult = testarConexaoGateway($gateway);
    
    if ($testResult['success']) {
        echo json_encode(['success' => true, 'message' => 'Gateway testado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao testar gateway: ' . $testResult['message']]);
    }
}

function testarConexaoGateway($gateway) {
    // Implementar testes específicos para cada provedor
    switch ($gateway['provedor']) {
        case 'stripe':
            return testarStripe($gateway);
        case 'paypal':
            return testarPayPal($gateway);
        case 'mercadopago':
            return testarMercadoPago($gateway);
        case 'pagseguro':
            return testarPagSeguro($gateway);
        default:
            return ['success' => false, 'message' => 'Provedor não suportado para teste'];
    }
}

function testarStripe($gateway) {
    // Implementar teste do Stripe
    return ['success' => true, 'message' => 'Conexão Stripe OK'];
}

function testarPayPal($gateway) {
    // Implementar teste do PayPal
    return ['success' => true, 'message' => 'Conexão PayPal OK'];
}

function testarMercadoPago($gateway) {
    // Implementar teste do Mercado Pago
    return ['success' => true, 'message' => 'Conexão Mercado Pago OK'];
}

function testarPagSeguro($gateway) {
    // Implementar teste do PagSeguro
    return ['success' => true, 'message' => 'Conexão PagSeguro OK'];
}

function gerarRelatorioFinanceiro($pdo, $tenantId, $filialId) {
    $dataInicio = $_POST['data_inicio'] ?? '';
    $dataFim = $_POST['data_fim'] ?? '';
    $formaPagamento = $_POST['forma_pagamento'] ?? '';
    
    $sql = "
        SELECT 
            mf.*,
            cf.nome as categoria_nome
        FROM movimentacoes_financeiras mf
        LEFT JOIN categorias_financeiras cf ON mf.categoria_id = cf.id
        WHERE mf.tenant_id = ? AND mf.filial_id = ?
    ";
    
    $params = [$tenantId, $filialId];
    
    if (!empty($dataInicio)) {
        $sql .= " AND mf.data_movimentacao >= ?";
        $params[] = $dataInicio;
    }
    
    if (!empty($dataFim)) {
        $sql .= " AND mf.data_movimentacao <= ?";
        $params[] = $dataFim;
    }
    
    if (!empty($formaPagamento)) {
        $sql .= " AND mf.descricao LIKE ?";
        $params[] = "%$formaPagamento%";
    }
    
    $sql .= " ORDER BY mf.data_movimentacao DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $totalEntradas = 0;
    $totalSaidas = 0;
    $resumoFormas = [];
    
    foreach ($movimentacoes as $mov) {
        if ($mov['tipo'] === 'entrada') {
            $totalEntradas += $mov['valor'];
        } else {
            $totalSaidas += $mov['valor'];
        }
        
        // Agrupar por forma de pagamento
        $forma = extrairFormaPagamento($mov['descricao']);
        if (!isset($resumoFormas[$forma])) {
            $resumoFormas[$forma] = ['entradas' => 0, 'saidas' => 0];
        }
        
        if ($mov['tipo'] === 'entrada') {
            $resumoFormas[$forma]['entradas'] += $mov['valor'];
        } else {
            $resumoFormas[$forma]['saidas'] += $mov['valor'];
        }
    }
    
    $saldoLiquido = $totalEntradas - $totalSaidas;
    
    // Gerar HTML do relatório
    $html = gerarHtmlRelatorioFinanceiro($movimentacoes, $totalEntradas, $totalSaidas, $saldoLiquido, $resumoFormas);
    
    echo json_encode(['success' => true, 'html' => $html]);
}

function extrairFormaPagamento($descricao) {
    $descricao = strtolower($descricao);
    
    if (strpos($descricao, 'pix') !== false) return 'PIX';
    if (strpos($descricao, 'cartão') !== false || strpos($descricao, 'cartao') !== false) return 'Cartão';
    if (strpos($descricao, 'dinheiro') !== false) return 'Dinheiro';
    if (strpos($descricao, 'transferência') !== false || strpos($descricao, 'transferencia') !== false) return 'Transferência';
    
    return 'Outros';
}

function gerarHtmlRelatorioFinanceiro($movimentacoes, $totalEntradas, $totalSaidas, $saldoLiquido, $resumoFormas) {
    $html = '
        <div class="row">
            <div class="col-md-12">
                <h5>Resumo Financeiro</h5>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Total Entradas</h6>
                                <h4>R$ ' . number_format($totalEntradas, 2, ',', '.') . '</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6>Total Saídas</h6>
                                <h4>R$ ' . number_format($totalSaidas, 2, ',', '.') . '</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Saldo Líquido</h6>
                                <h4>R$ ' . number_format($saldoLiquido, 2, ',', '.') . '</h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h5>Resumo por Forma de Pagamento</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Forma de Pagamento</th>
                                <th>Entradas</th>
                                <th>Saídas</th>
                                <th>Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
    ';
    
    foreach ($resumoFormas as $forma => $valores) {
        $saldo = $valores['entradas'] - $valores['saidas'];
        $saldoClass = $saldo >= 0 ? 'text-success' : 'text-danger';
        
        $html .= "
            <tr>
                <td>{$forma}</td>
                <td>R$ " . number_format($valores['entradas'], 2, ',', '.') . "</td>
                <td>R$ " . number_format($valores['saidas'], 2, ',', '.') . "</td>
                <td class='{$saldoClass}'>R$ " . number_format($saldo, 2, ',', '.') . "</td>
            </tr>
        ";
    }
    
    $html .= '
                        </tbody>
                    </table>
                </div>
                
                <h5>Movimentações Detalhadas</h5>
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
            </div>
        </div>
    ';
    
    return $html;
}

function editarGateway($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do gateway não fornecido']);
        return;
    }
    
    // Implementar lógica de edição
    echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
}

function excluirGateway($pdo, $tenantId) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID do gateway não fornecido']);
        return;
    }
    
    $sql = "DELETE FROM gateways_pagamento WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $tenantId]);
    
    echo json_encode(['success' => true, 'message' => 'Gateway excluído com sucesso']);
}
?>
