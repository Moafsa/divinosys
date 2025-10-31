<?php
// Export API endpoints with true Excel support
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
            exportProductsExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_categories':
            exportCategoriesExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_ingredients':
            exportIngredientsExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_orders':
            exportOrdersExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_financial':
            exportFinancialExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_paid_orders':
            exportPaidOrdersExcel($pdo, $tenantId, $filialId);
            break;
            
        case 'export_credit_orders':
            exportCreditOrdersExcel($pdo, $tenantId, $filialId);
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

function exportProductsExcel($pdo, $tenantId, $filialId) {
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
    
    // Create Excel file using simple XML format
    $excelContent = createTrueExcelFile($products, [
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

function exportCategoriesExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($categories, [
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

function exportIngredientsExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($ingredients, [
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

function exportOrdersExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($orders, [
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

function exportPaidOrdersExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($orders, [
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

function exportCreditOrdersExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($orders, [
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

function exportFinancialExcel($pdo, $tenantId, $filialId) {
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
    
    $excelContent = createTrueExcelFile($lancamentos, [
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

function createTrueExcelFile($data, $columnMapping, $sheetName) {
    // Create a proper Excel file using ZIP format with XML structure
    $tempDir = sys_get_temp_dir() . '/excel_export_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Create the Excel file structure
    $excelDir = $tempDir . '/xl';
    $excelWorkbookDir = $excelDir . '/workbook';
    $excelWorksheetDir = $excelDir . '/worksheets';
    
    mkdir($excelDir, 0755, true);
    mkdir($excelWorkbookDir, 0755, true);
    mkdir($excelWorksheetDir, 0755, true);
    
    // Create [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    file_put_contents($tempDir . '/[Content_Types].xml', $contentTypes);
    
    // Create _rels/.rels
    mkdir($tempDir . '/_rels', 0755, true);
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    file_put_contents($tempDir . '/_rels/.rels', $rels);
    
    // Create xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="' . htmlspecialchars($sheetName) . '" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
    file_put_contents($excelWorkbookDir . '/workbook.xml', $workbook);
    
    // Create xl/_rels/workbook.xml.rels
    mkdir($excelDir . '/_rels', 0755, true);
    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    file_put_contents($excelDir . '/_rels/workbook.xml.rels', $workbookRels);
    
    // Create xl/sharedStrings.xml
    $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0">
</sst>';
    file_put_contents($excelDir . '/sharedStrings.xml', $sharedStrings);
    
    // Create xl/styles.xml
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1">
        <font>
            <sz val="11"/>
            <name val="Calibri"/>
        </font>
    </fonts>
    <fills count="2">
        <fill>
            <patternFill patternType="none"/>
        </fill>
        <fill>
            <patternFill patternType="gray125"/>
        </fill>
    </fills>
    <borders count="1">
        <border>
            <left/>
            <right/>
            <top/>
            <bottom/>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    </cellXfs>
</styleSheet>';
    file_put_contents($excelDir . '/styles.xml', $styles);
    
    // Create xl/worksheets/sheet1.xml
    $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetData>';
    
    // Add header row
    $worksheet .= '<row r="1">';
    $colIndex = 1;
    foreach (array_keys($columnMapping) as $header) {
        $worksheet .= '<c r="' . getColumnLetter($colIndex) . '1" t="str"><v>' . htmlspecialchars($header) . '</v></c>';
        $colIndex++;
    }
    $worksheet .= '</row>';
    
    // Add data rows
    $rowIndex = 2;
    foreach ($data as $row) {
        $worksheet .= '<row r="' . $rowIndex . '">';
        $colIndex = 1;
        
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
            
            // Escape XML
            $value = htmlspecialchars($value);
            
            // Determine data type
            $cellType = 'str';
            if (is_numeric($value) && $value !== '') {
                $cellType = 'n';
            }
            
            $worksheet .= '<c r="' . getColumnLetter($colIndex) . $rowIndex . '" t="' . $cellType . '"><v>' . $value . '</v></c>';
            $colIndex++;
        }
        
        $worksheet .= '</row>';
        $rowIndex++;
    }
    
    $worksheet .= '</sheetData>
</worksheet>';
    file_put_contents($excelWorksheetDir . '/sheet1.xml', $worksheet);
    
    // Create the ZIP file
    $zipFile = $tempDir . '/export.xlsx';
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Cannot create ZIP file');
    }
    
    // Add all files to ZIP
    addDirToZip($zip, $tempDir, '');
    $zip->close();
    
    // Read the ZIP file content
    $content = file_get_contents($zipFile);
    
    // Clean up
    rmdir_recursive($tempDir);
    
    return $content;
}

function getColumnLetter($colIndex) {
    $letter = '';
    while ($colIndex > 0) {
        $colIndex--;
        $letter = chr(65 + ($colIndex % 26)) . $letter;
        $colIndex = intval($colIndex / 26);
    }
    return $letter;
}

function addDirToZip($zip, $dir, $basePath) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $fullPath = $dir . '/' . $file;
        $zipPath = $basePath . $file;
        
        if (is_dir($fullPath)) {
            $zip->addEmptyDir($zipPath . '/');
            addDirToZip($zip, $fullPath, $zipPath . '/');
        } else {
            $zip->addFile($fullPath, $zipPath);
        }
    }
}

function rmdir_recursive($dir) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $fullPath = $dir . '/' . $file;
        if (is_dir($fullPath)) {
            rmdir_recursive($fullPath);
        } else {
            unlink($fullPath);
        }
    }
    rmdir($dir);
}
?>
