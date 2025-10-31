<?php
// Export API endpoints
require_once __DIR__ . '/../config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Initialize database connection
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get tenant and filial from session or use defaults
    $tenantId = 1; // Default tenant
    $filialId = 1;  // Default filial
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'export_products':
            exportProducts($pdo, $tenantId, $filialId);
            break;
            
        case 'export_categories':
            exportCategories($pdo, $tenantId, $filialId);
            break;
            
        case 'export_ingredients':
            exportIngredients($pdo, $tenantId, $filialId);
            break;
            
        case 'export_orders':
            exportOrders($pdo, $tenantId, $filialId);
            break;
            
        case 'export_financial':
            exportFinancial($pdo, $tenantId, $filialId);
            break;
            
        case 'export_paid_orders':
            exportPaidOrders($pdo, $tenantId, $filialId);
            break;
            
        case 'export_credit_orders':
            exportCreditOrders($pdo, $tenantId, $filialId);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function exportProducts($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE p.tenant_id = ?";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND p.filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.codigo,
            p.nome,
            p.descricao,
            p.preco_normal,
            p.preco_mini,
            p.ativo,
            p.imagem,
            p.created_at,
            c.nome as categoria_nome,
            c.id as categoria_id
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        $whereClause
        ORDER BY c.nome, p.nome
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ingredients for each product
    foreach ($products as &$product) {
        try {
            $ingredientSql = "
                SELECT i.nome, i.preco, pi.obrigatorio, pi.preco_adicional 
                FROM ingredientes i 
                JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id 
                WHERE pi.produto_id = ? AND i.tenant_id = ?" . 
                ($filialId ? " AND i.filial_id = ?" : "");
            
            $ingredientParams = [$product['id'], $tenantId];
            if ($filialId) $ingredientParams[] = $filialId;
            
            $ingredientStmt = $pdo->prepare($ingredientSql);
            $ingredientStmt->execute($ingredientParams);
            $ingredients = $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);
            $product['ingredientes'] = $ingredients;
        } catch (Exception $e) {
            $product['ingredientes'] = [];
        }
    }
    
    // Create Excel file
    $filename = 'produtos_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Create a simple Excel file using basic PHP
    $excelContent = createExcelFile($products, [
        'ID', 'Código', 'Nome', 'Descrição', 'Preço Normal', 'Preço Mini', 
        'Ativo', 'Imagem', 'Categoria ID', 'Categoria Nome', 'Ingredientes', 'Data Criação'
    ], 'Produtos');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportCategories($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE tenant_id = ?";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "SELECT * FROM categorias $whereClause ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'categorias_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($categories, [
        'ID', 'Nome', 'Descrição', 'Ativo', 'Data Criação'
    ], 'Categorias');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportIngredients($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE tenant_id = ?";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "SELECT * FROM ingredientes $whereClause ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'ingredientes_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($ingredients, [
        'ID', 'Nome', 'Preço', 'Ativo', 'Data Criação'
    ], 'Ingredientes');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportOrders($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE p.tenant_id = ?";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND p.filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "
        SELECT 
            p.idpedido,
            p.mesa,
            p.cliente_nome,
            p.cliente_telefone,
            p.status,
            p.forma_pagamento,
            p.valor_total,
            p.valor_pago,
            p.valor_restante,
            p.observacoes,
            p.created_at,
            u.nome as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($orders, [
        'ID', 'Mesa', 'Cliente', 'Telefone', 'Status', 'Forma Pagamento',
        'Valor Total', 'Valor Pago', 'Valor Restante', 'Observações', 'Usuário', 'Data'
    ], 'Pedidos');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportPaidOrders($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE p.tenant_id = ? AND p.status = 'quitado'";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND p.filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "
        SELECT 
            p.idpedido,
            p.mesa,
            p.cliente_nome,
            p.cliente_telefone,
            p.forma_pagamento,
            p.valor_total,
            p.valor_pago,
            p.observacoes,
            p.created_at,
            u.nome as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_quitados_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($orders, [
        'ID', 'Mesa', 'Cliente', 'Telefone', 'Forma Pagamento',
        'Valor Total', 'Valor Pago', 'Observações', 'Usuário', 'Data'
    ], 'Pedidos Quitados');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportCreditOrders($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE p.tenant_id = ? AND p.status = 'fiado'";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND p.filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "
        SELECT 
            p.idpedido,
            p.mesa,
            p.cliente_nome,
            p.cliente_telefone,
            p.valor_total,
            p.valor_pago,
            p.valor_restante,
            p.observacoes,
            p.created_at,
            u.nome as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_fiados_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($orders, [
        'ID', 'Mesa', 'Cliente', 'Telefone', 'Valor Total', 
        'Valor Pago', 'Valor Restante', 'Observações', 'Usuário', 'Data'
    ], 'Pedidos Fiados');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportFinancial($pdo, $tenantId, $filialId) {
    $whereClause = "WHERE l.tenant_id = ?";
    $params = [$tenantId];
    
    if ($filialId) {
        $whereClause .= " AND l.filial_id = ?";
        $params[] = $filialId;
    }
    
    $sql = "
        SELECT 
            l.id,
            l.tipo,
            l.valor,
            l.data_vencimento,
            l.data_pagamento,
            l.descricao,
            l.observacoes,
            l.forma_pagamento,
            l.status,
            l.created_at,
            cf.nome as categoria_nome,
            co.nome as conta_nome,
            u.nome as usuario_nome
        FROM lancamentos_financeiros l
        LEFT JOIN categorias_financeiras cf ON l.categoria_id = cf.id
        LEFT JOIN contas_financeiras co ON l.conta_id = co.id
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'lancamentos_financeiros_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createExcelFile($lancamentos, [
        'ID', 'Tipo', 'Valor', 'Data Vencimento', 'Data Pagamento',
        'Descrição', 'Observações', 'Forma Pagamento', 'Status',
        'Categoria', 'Conta', 'Usuário', 'Data Criação'
    ], 'Lançamentos Financeiros');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function createExcelFile($data, $headers, $sheetName) {
    // Create a simple Excel file using XML format
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml .= '<sheets><sheet name="' . htmlspecialchars($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>';
    $xml .= '</workbook>';
    
    // For now, we'll create a CSV that Excel can open as Excel format
    // This is a simplified approach that works without external libraries
    
    $csvContent = '';
    
    // Add BOM for UTF-8
    $csvContent .= chr(0xEF).chr(0xBB).chr(0xBF);
    
    // Add headers
    $csvContent .= implode(',', array_map('wrapCsvValue', $headers)) . "\n";
    
    // Add data
    foreach ($data as $row) {
        $csvRow = [];
        foreach ($headers as $header) {
            $value = '';
            switch ($header) {
                case 'ID':
                    $value = $row['id'] ?? $row['idpedido'] ?? '';
                    break;
                case 'Código':
                    $value = $row['codigo'] ?? '';
                    break;
                case 'Nome':
                    $value = $row['nome'] ?? $row['cliente_nome'] ?? '';
                    break;
                case 'Descrição':
                    $value = $row['descricao'] ?? '';
                    break;
                case 'Preço Normal':
                    $value = $row['preco_normal'] ?? '';
                    break;
                case 'Preço Mini':
                    $value = $row['preco_mini'] ?? '';
                    break;
                case 'Ativo':
                    $value = isset($row['ativo']) ? ($row['ativo'] ? 'Sim' : 'Não') : '';
                    break;
                case 'Imagem':
                    $value = $row['imagem'] ?? '';
                    break;
                case 'Categoria ID':
                    $value = $row['categoria_id'] ?? '';
                    break;
                case 'Categoria Nome':
                    $value = $row['categoria_nome'] ?? '';
                    break;
                case 'Ingredientes':
                    if (isset($row['ingredientes']) && is_array($row['ingredientes'])) {
                        $ingredientList = [];
                        foreach ($row['ingredientes'] as $ingredient) {
                            $ingredientList[] = $ingredient['nome'] . 
                                ($ingredient['obrigatorio'] ? ' (obrigatório)' : '') . 
                                ($ingredient['preco_adicional'] > 0 ? ' (+R$ ' . number_format($ingredient['preco_adicional'], 2, ',', '.') . ')' : '');
                        }
                        $value = implode('; ', $ingredientList);
                    } else {
                        $value = '';
                    }
                    break;
                case 'Data Criação':
                    $value = $row['created_at'] ?? '';
                    break;
                case 'Mesa':
                    $value = $row['mesa'] ?? '';
                    break;
                case 'Cliente':
                    $value = $row['cliente_nome'] ?? '';
                    break;
                case 'Telefone':
                    $value = $row['cliente_telefone'] ?? '';
                    break;
                case 'Status':
                    $value = $row['status'] ?? '';
                    break;
                case 'Forma Pagamento':
                    $value = $row['forma_pagamento'] ?? '';
                    break;
                case 'Valor Total':
                    $value = $row['valor_total'] ?? '';
                    break;
                case 'Valor Pago':
                    $value = $row['valor_pago'] ?? '';
                    break;
                case 'Valor Restante':
                    $value = $row['valor_restante'] ?? '';
                    break;
                case 'Observações':
                    $value = $row['observacoes'] ?? '';
                    break;
                case 'Usuário':
                    $value = $row['usuario_nome'] ?? '';
                    break;
                case 'Data':
                    $value = $row['created_at'] ?? '';
                    break;
                case 'Tipo':
                    $value = $row['tipo'] ?? '';
                    break;
                case 'Valor':
                    $value = $row['valor'] ?? '';
                    break;
                case 'Data Vencimento':
                    $value = $row['data_vencimento'] ?? '';
                    break;
                case 'Data Pagamento':
                    $value = $row['data_pagamento'] ?? '';
                    break;
                case 'Categoria':
                    $value = $row['categoria_nome'] ?? '';
                    break;
                case 'Conta':
                    $value = $row['conta_nome'] ?? '';
                    break;
            }
            $csvRow[] = $value;
        }
        $csvContent .= implode(',', array_map('wrapCsvValue', $csvRow)) . "\n";
    }
    
    return $csvContent;
}

function wrapCsvValue($value) {
    // Escape CSV values properly
    $value = str_replace('"', '""', $value);
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        return '"' . $value . '"';
    }
    return $value;
}
?>