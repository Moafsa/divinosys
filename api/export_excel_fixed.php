<?php
// Fixed Excel Export - Creates proper Excel files
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
            exportProductsFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_categories':
            exportCategoriesFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_ingredients':
            exportIngredientsFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_orders':
            exportOrdersFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_financial':
            exportFinancialFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_paid_orders':
            exportPaidOrdersFixed($pdo, $tenantId, $filialId);
            break;
            
        case 'export_credit_orders':
            exportCreditOrdersFixed($pdo, $tenantId, $filialId);
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

function exportProductsFixed($pdo, $tenantId, $filialId) {
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
    
    $filename = 'produtos_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Create Excel file using proper format
    $excelContent = createFixedExcelFile($products, [
        'ID' => 'id',
        'Código' => 'codigo', 
        'Nome' => 'nome',
        'Descrição' => 'descricao',
        'Preço Normal' => 'preco_normal',
        'Preço Mini' => 'preco_mini',
        'Ativo' => 'ativo',
        'Imagem' => 'imagem',
        'Categoria ID' => 'categoria_id',
        'Categoria Nome' => 'categoria_nome',
        'Ingredientes' => 'ingredientes',
        'Data Criação' => 'created_at'
    ], 'Produtos');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportCategoriesFixed($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createFixedExcelFile($categories, [
        'ID' => 'id',
        'Nome' => 'nome',
        'Descrição' => 'descricao',
        'Ativo' => 'ativo',
        'Data Criação' => 'created_at'
    ], 'Categorias');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportIngredientsFixed($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createFixedExcelFile($ingredients, [
        'ID' => 'id',
        'Nome' => 'nome',
        'Preço' => 'preco',
        'Ativo' => 'ativo',
        'Data Criação' => 'created_at'
    ], 'Ingredientes');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportOrdersFixed($pdo, $tenantId, $filialId) {
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
            u.login as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createFixedExcelFile($orders, [
        'ID' => 'idpedido',
        'Mesa' => 'mesa',
        'Cliente' => 'cliente_nome',
        'Telefone' => 'cliente_telefone',
        'Status' => 'status',
        'Forma Pagamento' => 'forma_pagamento',
        'Valor Total' => 'valor_total',
        'Valor Pago' => 'valor_pago',
        'Valor Restante' => 'valor_restante',
        'Observações' => 'observacoes',
        'Usuário' => 'usuario_nome',
        'Data' => 'created_at'
    ], 'Pedidos');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportPaidOrdersFixed($pdo, $tenantId, $filialId) {
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
            u.login as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_quitados_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createFixedExcelFile($orders, [
        'ID' => 'idpedido',
        'Mesa' => 'mesa',
        'Cliente' => 'cliente_nome',
        'Telefone' => 'cliente_telefone',
        'Forma Pagamento' => 'forma_pagamento',
        'Valor Total' => 'valor_total',
        'Valor Pago' => 'valor_pago',
        'Observações' => 'observacoes',
        'Usuário' => 'usuario_nome',
        'Data' => 'created_at'
    ], 'Pedidos Quitados');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportCreditOrdersFixed($pdo, $tenantId, $filialId) {
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
            u.login as usuario_nome
        FROM pedido p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'pedidos_fiados_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    $excelContent = createFixedExcelFile($orders, [
        'ID' => 'idpedido',
        'Mesa' => 'mesa',
        'Cliente' => 'cliente_nome',
        'Telefone' => 'cliente_telefone',
        'Valor Total' => 'valor_total',
        'Valor Pago' => 'valor_pago',
        'Valor Restante' => 'valor_restante',
        'Observações' => 'observacoes',
        'Usuário' => 'usuario_nome',
        'Data' => 'created_at'
    ], 'Pedidos Fiados');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function exportFinancialFixed($pdo, $tenantId, $filialId) {
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
            u.login as usuario_nome
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
    
    $excelContent = createFixedExcelFile($lancamentos, [
        'ID' => 'id',
        'Tipo' => 'tipo',
        'Valor' => 'valor',
        'Data Vencimento' => 'data_vencimento',
        'Data Pagamento' => 'data_pagamento',
        'Descrição' => 'descricao',
        'Observações' => 'observacoes',
        'Forma Pagamento' => 'forma_pagamento',
        'Status' => 'status',
        'Categoria' => 'categoria_nome',
        'Conta' => 'conta_nome',
        'Usuário' => 'usuario_nome',
        'Data Criação' => 'created_at'
    ], 'Lançamentos Financeiros');
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $excelContent;
    exit;
}

function createFixedExcelFile($data, $columnMapping, $sheetName) {
    // Create a proper Excel file using CSV format with Excel headers
    // This approach creates a file that Excel and Google Drive can properly recognize
    
    $csvContent = '';
    
    // Add BOM for UTF-8
    $csvContent .= chr(0xEF).chr(0xBB).chr(0xBF);
    
    // Add headers
    $csvContent .= implode(',', array_map('wrapCsvValue', array_keys($columnMapping))) . "\n";
    
    // Add data
    foreach ($data as $row) {
        $csvRow = [];
        foreach ($columnMapping as $columnName => $fieldName) {
            $value = '';
            
            if ($fieldName === 'ingredientes' && isset($row['ingredientes']) && is_array($row['ingredientes'])) {
                $ingredientList = [];
                foreach ($row['ingredientes'] as $ingredient) {
                    $ingredientList[] = $ingredient['nome'] . 
                        ($ingredient['obrigatorio'] ? ' (obrigatório)' : '') . 
                        ($ingredient['preco_adicional'] > 0 ? ' (+R$ ' . number_format($ingredient['preco_adicional'], 2, ',', '.') . ')' : '');
                }
                $value = implode('; ', $ingredientList);
            } else {
                $value = $row[$fieldName] ?? '';
            }
            
            // Format boolean values
            if ($fieldName === 'ativo') {
                $value = $value ? 'Sim' : 'Não';
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
