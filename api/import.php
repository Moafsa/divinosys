<?php
// Import API endpoints
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
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
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $action = $_POST['action'] ?? '';
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $filePath = $file['tmp_name'];
    
    // Validate file type
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'csv') {
        throw new Exception('Only CSV files are allowed');
    }
    
    switch ($action) {
        case 'import_products':
            importProducts($pdo, $filePath, $tenantId, $filialId);
            break;
            
        case 'import_categories':
            importCategories($pdo, $filePath, $tenantId, $filialId);
            break;
            
        case 'import_ingredients':
            importIngredients($pdo, $filePath, $tenantId, $filialId);
            break;
            
        case 'import_orders':
            importOrders($pdo, $filePath, $tenantId, $filialId);
            break;
            
        case 'import_financial':
            importFinancial($pdo, $filePath, $tenantId, $filialId);
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

function importProducts($pdo, $filePath, $tenantId, $filialId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Invalid CSV format');
    }
    
    $imported = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 11) {
            $errors[] = "Row with insufficient columns: " . implode(',', $row);
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Check if product exists by ID or create new
            $productId = $row[0] ?: null;
            $codigo = $row[1] ?: null;
            $nome = $row[2];
            $descricao = $row[3] ?: null;
            $precoNormal = floatval(str_replace(',', '.', $row[4]));
            $precoMini = $row[5] ? floatval(str_replace(',', '.', $row[5])) : null;
            $ativo = $row[6] === 'Sim' ? 1 : 0;
            $imagem = $row[7] ?: null;
            $categoriaId = $row[8] ?: null;
            $categoriaNome = $row[9];
            $ingredientes = $row[10];
            $createdAt = $row[11] ?: date('Y-m-d H:i:s');
            
            // Validate required fields
            if (empty($nome) || $precoNormal <= 0) {
                throw new Exception("Product name and price are required");
            }
            
            // Handle category
            if ($categoriaNome && !$categoriaId) {
                // Try to find category by name
                $stmt = $pdo->prepare(
                    "SELECT id FROM categorias WHERE nome = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$categoriaNome, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
                $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingCategory) {
                    $categoriaId = $existingCategory['id'];
                } else {
                    // Create new category
                    $stmt = $pdo->prepare(
                        "INSERT INTO categorias (nome, tenant_id" . ($filialId ? ", filial_id" : "") . ") VALUES (?, ?" . ($filialId ? ", ?" : "") . ")"
                    );
                    $params = [$categoriaNome, $tenantId];
                    if ($filialId) $params[] = $filialId;
                    $stmt->execute($params);
                    $categoriaId = $pdo->lastInsertId();
                }
            }
            
            if ($productId) {
                // Update existing product
                $stmt = $pdo->prepare(
                    "UPDATE produtos SET 
                        codigo = ?, nome = ?, descricao = ?, preco_normal = ?, 
                        preco_mini = ?, ativo = ?, imagem = ?, categoria_id = ?
                     WHERE id = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$codigo, $nome, $descricao, $precoNormal, $precoMini, $ativo, $imagem, $categoriaId, $productId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            } else {
                // Create new product
                $stmt = $pdo->prepare(
                    "INSERT INTO produtos (codigo, nome, descricao, preco_normal, preco_mini, ativo, imagem, categoria_id, tenant_id" . 
                    ($filialId ? ", filial_id" : "") . ") 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?" . ($filialId ? ", ?" : "") . ")"
                );
                $params = [$codigo, $nome, $descricao, $precoNormal, $precoMini, $ativo, $imagem, $categoriaId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
                $productId = $pdo->lastInsertId();
            }
            
            // Handle ingredients if provided
            if ($ingredientes && $productId) {
                // Clear existing ingredients
                $stmt = $pdo->prepare("DELETE FROM produto_ingredientes WHERE produto_id = ?");
                $stmt->execute([$productId]);
                
                // Parse ingredients
                $ingredientList = explode(';', $ingredientes);
                foreach ($ingredientList as $ingredientStr) {
                    $ingredientStr = trim($ingredientStr);
                    if (empty($ingredientStr)) continue;
                    
                    $obrigatorio = strpos($ingredientStr, '(obrigatório)') !== false;
                    $precoAdicional = 0;
                    
                    // Extract additional price
                    if (preg_match('/\(\+R\$ ([\d,\.]+)\)/', $ingredientStr, $matches)) {
                        $precoAdicional = floatval(str_replace(',', '.', $matches[1]));
                    }
                    
                    // Clean ingredient name
                    $ingredientName = preg_replace('/\s*\([^)]*\)/', '', $ingredientStr);
                    $ingredientName = trim($ingredientName);
                    
                    if (empty($ingredientName)) continue;
                    
                    // Find or create ingredient
                    $stmt = $pdo->prepare(
                        "SELECT id FROM ingredientes WHERE nome = ? AND tenant_id = ?" . 
                        ($filialId ? " AND filial_id = ?" : "")
                    );
                    $params = [$ingredientName, $tenantId];
                    if ($filialId) $params[] = $filialId;
                    $stmt->execute($params);
                    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$ingredient) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO ingredientes (nome, tenant_id" . ($filialId ? ", filial_id" : "") . ") VALUES (?, ?" . ($filialId ? ", ?" : "") . ")"
                        );
                        $params = [$ingredientName, $tenantId];
                        if ($filialId) $params[] = $filialId;
                        $stmt->execute($params);
                        $ingredientId = $pdo->lastInsertId();
                    } else {
                        $ingredientId = $ingredient['id'];
                    }
                    
                    // Link ingredient to product
                    $stmt = $pdo->prepare(
                        "INSERT INTO produto_ingredientes (produto_id, ingrediente_id, obrigatorio, preco_adicional) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->execute([$productId, $ingredientId, $obrigatorio ? 1 : 0, $precoAdicional]);
                }
            }
            
            $pdo->commit();
            $imported++;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Error importing product '{$nome}': " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}

function importCategories($pdo, $filePath, $tenantId, $filialId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Invalid CSV format');
    }
    
    $imported = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 4) {
            $errors[] = "Row with insufficient columns: " . implode(',', $row);
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            $categoryId = $row[0] ?: null;
            $nome = $row[1];
            $descricao = $row[2] ?: null;
            $ativo = $row[3] === 'Sim' ? 1 : 0;
            
            if (empty($nome)) {
                throw new Exception("Category name is required");
            }
            
            if ($categoryId) {
                // Update existing category
                $stmt = $pdo->prepare(
                    "UPDATE categorias SET nome = ?, descricao = ?, ativo = ? 
                     WHERE id = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$nome, $descricao, $ativo, $categoryId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            } else {
                // Create new category
                $stmt = $pdo->prepare(
                    "INSERT INTO categorias (nome, descricao, ativo, tenant_id" . 
                    ($filialId ? ", filial_id" : "") . ") VALUES (?, ?, ?, ?" . ($filialId ? ", ?" : "") . ")"
                );
                $params = [$nome, $descricao, $ativo, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            }
            
            $pdo->commit();
            $imported++;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Error importing category '{$nome}': " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}

function importIngredients($pdo, $filePath, $tenantId, $filialId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Invalid CSV format');
    }
    
    $imported = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 4) {
            $errors[] = "Row with insufficient columns: " . implode(',', $row);
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            $ingredientId = $row[0] ?: null;
            $nome = $row[1];
            $preco = floatval(str_replace(',', '.', $row[2]));
            $ativo = $row[3] === 'Sim' ? 1 : 0;
            
            if (empty($nome)) {
                throw new Exception("Ingredient name is required");
            }
            
            if ($ingredientId) {
                // Update existing ingredient
                $stmt = $pdo->prepare(
                    "UPDATE ingredientes SET nome = ?, preco = ?, ativo = ? 
                     WHERE id = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$nome, $preco, $ativo, $ingredientId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            } else {
                // Create new ingredient
                $stmt = $pdo->prepare(
                    "INSERT INTO ingredientes (nome, preco, ativo, tenant_id" . 
                    ($filialId ? ", filial_id" : "") . ") VALUES (?, ?, ?, ?" . ($filialId ? ", ?" : "") . ")"
                );
                $params = [$nome, $preco, $ativo, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            }
            
            $pdo->commit();
            $imported++;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Error importing ingredient '{$nome}': " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}

function importOrders($pdo, $filePath, $tenantId, $filialId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Invalid CSV format');
    }
    
    $imported = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 11) {
            $errors[] = "Row with insufficient columns: " . implode(',', $row);
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            $orderId = $row[0] ?: null;
            $mesa = $row[1] ?: null;
            $clienteNome = $row[2] ?: null;
            $clienteTelefone = $row[3] ?: null;
            $status = $row[4] ?: 'pendente';
            $formaPagamento = $row[5] ?: null;
            $valorTotal = floatval(str_replace(',', '.', $row[6]));
            $valorPago = floatval(str_replace(',', '.', $row[7]));
            $valorRestante = floatval(str_replace(',', '.', $row[8]));
            $observacoes = $row[9] ?: null;
            $usuarioNome = $row[10] ?: null;
            $createdAt = $row[11] ?: date('Y-m-d H:i:s');
            
            if ($valorTotal <= 0) {
                throw new Exception("Total value must be greater than 0");
            }
            
            // Find user by name
            $usuarioId = null;
            if ($usuarioNome) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ? AND tenant_id = ?");
                $stmt->execute([$usuarioNome, $tenantId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $usuarioId = $user['id'];
                }
            }
            
            if ($orderId) {
                // Update existing order
                $stmt = $pdo->prepare(
                    "UPDATE pedido SET 
                        mesa = ?, cliente_nome = ?, cliente_telefone = ?, status = ?, 
                        forma_pagamento = ?, valor_total = ?, valor_pago = ?, valor_restante = ?, 
                        observacoes = ?, usuario_id = ?
                     WHERE idpedido = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$mesa, $clienteNome, $clienteTelefone, $status, $formaPagamento, 
                           $valorTotal, $valorPago, $valorRestante, $observacoes, $usuarioId, 
                           $orderId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            } else {
                // Create new order
                $stmt = $pdo->prepare(
                    "INSERT INTO pedido (mesa, cliente_nome, cliente_telefone, status, forma_pagamento, 
                                        valor_total, valor_pago, valor_restante, observacoes, usuario_id, 
                                        tenant_id" . ($filialId ? ", filial_id" : "") . ") 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($filialId ? ", ?" : "") . ")"
                );
                $params = [$mesa, $clienteNome, $clienteTelefone, $status, $formaPagamento, 
                           $valorTotal, $valorPago, $valorRestante, $observacoes, $usuarioId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            }
            
            $pdo->commit();
            $imported++;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Error importing order: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}

function importFinancial($pdo, $filePath, $tenantId, $filialId) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        throw new Exception('Invalid CSV format');
    }
    
    $imported = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 12) {
            $errors[] = "Row with insufficient columns: " . implode(',', $row);
            continue;
        }
        
        try {
            $pdo->beginTransaction();
            
            $lancamentoId = $row[0] ?: null;
            $tipo = $row[1];
            $valor = floatval(str_replace(',', '.', $row[2]));
            $dataVencimento = $row[3] ?: null;
            $dataPagamento = $row[4] ?: null;
            $descricao = $row[5];
            $observacoes = $row[6] ?: null;
            $formaPagamento = $row[7] ?: null;
            $status = $row[8] ?: 'pendente';
            $categoriaNome = $row[9] ?: null;
            $contaNome = $row[10] ?: null;
            $usuarioNome = $row[11] ?: null;
            $createdAt = $row[12] ?: date('Y-m-d H:i:s');
            
            if (empty($descricao) || $valor <= 0) {
                throw new Exception("Description and value are required");
            }
            
            // Find category
            $categoriaId = null;
            if ($categoriaNome) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM categorias_financeiras WHERE nome = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$categoriaNome, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($category) {
                    $categoriaId = $category['id'];
                }
            }
            
            // Find account
            $contaId = null;
            if ($contaNome) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM contas_financeiras WHERE nome = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$contaNome, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($account) {
                    $contaId = $account['id'];
                }
            }
            
            // Find user
            $usuarioId = null;
            if ($usuarioNome) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ? AND tenant_id = ?");
                $stmt->execute([$usuarioNome, $tenantId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $usuarioId = $user['id'];
                }
            }
            
            if ($lancamentoId) {
                // Update existing entry
                $stmt = $pdo->prepare(
                    "UPDATE lancamentos_financeiros SET 
                        tipo = ?, categoria_id = ?, conta_id = ?, valor = ?, data_vencimento = ?, 
                        data_pagamento = ?, descricao = ?, observacoes = ?, forma_pagamento = ?, 
                        status = ?, usuario_id = ?
                     WHERE id = ? AND tenant_id = ?" . 
                    ($filialId ? " AND filial_id = ?" : "")
                );
                $params = [$tipo, $categoriaId, $contaId, $valor, $dataVencimento, $dataPagamento, 
                           $descricao, $observacoes, $formaPagamento, $status, $usuarioId, 
                           $lancamentoId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            } else {
                // Create new entry
                $stmt = $pdo->prepare(
                    "INSERT INTO lancamentos_financeiros (tipo, categoria_id, conta_id, valor, data_vencimento, 
                                                        data_pagamento, descricao, observacoes, forma_pagamento, 
                                                        status, usuario_id, tenant_id" . 
                    ($filialId ? ", filial_id" : "") . ") 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($filialId ? ", ?" : "") . ")"
                );
                $params = [$tipo, $categoriaId, $contaId, $valor, $dataVencimento, $dataPagamento, 
                           $descricao, $observacoes, $formaPagamento, $status, $usuarioId, $tenantId];
                if ($filialId) $params[] = $filialId;
                $stmt->execute($params);
            }
            
            $pdo->commit();
            $imported++;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Error importing financial entry: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'errors' => $errors
    ]);
}
?>