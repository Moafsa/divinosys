<?php
// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Autoloader do Composer (necessário para AWS SDK)
require_once __DIR__ . '/../../vendor/autoload.php';

// Autoloader do sistema
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/Middleware/SubscriptionCheck.php';
require_once __DIR__ . '/../../system/Storage/MinIO.php';

try {
    // Conectar ao banco
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId();
    $filialId = $session->getFilialId(); // Don't default to 1, keep null if not set
    
    // Validar tenant_id
    if (!$tenantId) {
        throw new Exception('Tenant ID não encontrado na sessão. Faça login novamente.');
    }
    
    // Obter ação
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Ação não especificada');
    }
    
    // Log da ação
    error_log("AJAX Action: " . $action);
    
    switch ($action) {
        case 'buscar_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? $_POST['produto_id'] ?? $_GET['produto_id'] ?? '';

            if (empty($id)) {
                throw new Exception('ID do produto é obrigatório');
            }

            $produto = $db->fetch("SELECT * FROM produtos WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);

            if (!$produto) {
                throw new Exception('Produto não encontrado');
            }

            // Buscar ingredientes do produto
            $ingredientes = $db->fetchAll("
                SELECT i.*, pi.produto_id 
                FROM ingredientes i 
                INNER JOIN produto_ingredientes pi ON i.id = pi.ingrediente_id 
                WHERE pi.produto_id = ? AND i.tenant_id = $tenantId AND i.filial_id = $filialId
            ", [$id]);

            // Buscar todos os ingredientes disponíveis
            $todosIngredientes = $db->fetchAll("
                SELECT * FROM ingredientes 
                WHERE tenant_id = $tenantId AND filial_id = $filialId 
                ORDER BY nome
            ");

            echo json_encode([
                'success' => true, 
                'produto' => $produto, 
                'ingredientes' => $ingredientes,
                'todos_ingredientes' => $todosIngredientes
            ]);
            break;
            
        case 'buscar_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID da categoria é obrigatório');
            }
            
            $categoria = $db->fetch("SELECT * FROM categorias WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);
            
            if (!$categoria) {
                throw new Exception('Categoria não encontrada');
            }
            
            echo json_encode(['success' => true, 'categoria' => $categoria]);
            break;
            
        case 'buscar_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do ingrediente é obrigatório');
            }
            
            $ingrediente = $db->fetch("SELECT * FROM ingredientes WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);
            
            if (!$ingrediente) {
                throw new Exception('Ingrediente não encontrado');
            }
            
            echo json_encode(['success' => true, 'ingrediente' => $ingrediente]);
            break;
            
        case 'salvar_produto':
            $produtoId = $_POST['produto_id'] ?? '';
            
            // Log para debug
            error_log("SALVAR_PRODUTO - produtoId: " . $produtoId);
            error_log("SALVAR_PRODUTO - POST data: " . json_encode($_POST));
            
            // VERIFICAÇÃO DE ASSINATURA - Bloquear criação se trial expirado ou fatura vencida
            if (empty($produtoId)) { // Apenas ao CRIAR (não ao editar)
                if (!\System\Middleware\SubscriptionCheck::canPerformCriticalAction()) {
                    $status = \System\Middleware\SubscriptionCheck::checkSubscriptionStatus();
                    throw new Exception($status['message'] . ' Para cadastrar produtos, regularize sua situação.');
                }
            }
            
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoNormal = $_POST['preco_normal'] ?? 0;
            $precoMini = $_POST['preco_mini'] ?? 0;
            $categoriaId = $_POST['categoria_id'] ?? null;
            $ativo = $_POST['ativo'] ?? 0;
            $exibirCardapioOnline = isset($_POST['exibir_cardapio_online']) ? (int)$_POST['exibir_cardapio_online'] : 1;
            
            // Check if column exists
            $hasExibirCardapioColumn = false;
            try {
                $columnCheck = $db->fetch("
                    SELECT 1 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                      AND table_name = 'produtos' 
                      AND column_name = 'exibir_cardapio_online'
                    LIMIT 1
                ");
                $hasExibirCardapioColumn = !empty($columnCheck);
            } catch (\Exception $e) {
                $hasExibirCardapioColumn = false;
            }
            
            $estoqueAtual = $_POST['estoque_atual'] ?? 0;
            $estoqueMinimo = $_POST['estoque_minimo'] ?? 0;
            $precoCusto = $_POST['preco_custo'] ?? 0;
            $ingredientes = $_POST['ingredientes'] ?? '[]';
            
            // Promotional fields - SEMPRE processar, assumir que colunas existem
            $precoPromocional = $_POST['preco_promocional'] ?? null;
            $emPromocao = isset($_POST['em_promocao']) ? (int)$_POST['em_promocao'] : 0;
            
            error_log("PROMOCAO - POST preco_promocional: " . var_export($precoPromocional, true));
            error_log("PROMOCAO - POST em_promocao: " . var_export($_POST['em_promocao'] ?? 'não definido', true));
            
            // Tratar valores vazios
            if ($precoMini === '' || $precoMini === null) $precoMini = 0;
            if ($categoriaId === '' || $categoriaId === null) $categoriaId = null;
            if ($estoqueAtual === '' || $estoqueAtual === null) $estoqueAtual = 0;
            if ($estoqueMinimo === '' || $estoqueMinimo === null) $estoqueMinimo = 0;
            if ($precoCusto === '' || $precoCusto === null) $precoCusto = 0;
            
            // Tratar preço promocional - converter string vazia para null
            if ($precoPromocional === '' || $precoPromocional === null) {
                $precoPromocional = null;
            } else {
                // Converter formato brasileiro para numérico
                $precoPromocional = (float)str_replace(',', '.', $precoPromocional);
                if ($precoPromocional <= 0) {
                    $precoPromocional = null;
                }
            }
            
            // Se em_promocao está marcado mas não tem preço promocional válido, desmarcar
            if ($emPromocao && ($precoPromocional === null || $precoPromocional <= 0)) {
                $emPromocao = 0;
            }
            
            error_log("PROMOCAO - Valores finais - emPromocao: " . $emPromocao . ", precoPromocional: " . var_export($precoPromocional, true));
            
            if (empty($nome) || empty($precoNormal)) {
                error_log("SALVAR_PRODUTO - Validação falhou: Nome ou preço vazio");
                throw new Exception('Nome e preço normal são obrigatórios');
            }
            
            // Validar categoria obrigatória (apenas ao criar, não ao editar)
            if (empty($produtoId) && empty($categoriaId)) {
                error_log("SALVAR_PRODUTO - Validação falhou: Categoria obrigatória para novo produto");
                throw new Exception('Categoria é obrigatória para criar um produto');
            }
            
            // Se estiver editando e não tiver categoria, buscar a categoria atual
            if (!empty($produtoId) && empty($categoriaId)) {
                $produtoAtual = $db->fetch("SELECT categoria_id FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?", [$produtoId, $tenantId, $filialId]);
                if ($produtoAtual && !empty($produtoAtual['categoria_id'])) {
                    $categoriaId = $produtoAtual['categoria_id'];
                    error_log("SALVAR_PRODUTO - Categoria não fornecida, usando categoria atual: " . $categoriaId);
                }
            }
            
            // Processar imagem se enviada
            $imagemPath = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                try {
                    $minio = \System\Storage\MinIO::getInstance();
                    $imagemPath = $minio->uploadFile($_FILES['imagem'], 'produtos');
                } catch (Exception $e) {
                    error_log('Erro ao fazer upload para MinIO: ' . $e->getMessage());
                    throw new Exception('Erro ao fazer upload da imagem: ' . $e->getMessage());
                }
            }
            
            if (empty($produtoId)) {
                // Criar novo produto - usar as variáveis já definidas no início do arquivo
                // Normalizar filial: se ausente ou inválida, usar filial padrão do tenant
                $filialIdToUse = $filialId;
                if ($filialIdToUse === null) {
                    $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                    $filialIdToUse = $filial_padrao['id'] ?? null;
                } else {
                    // Validar se a filial existe para este tenant
                    $filial_valida = $db->fetch("SELECT id FROM filiais WHERE id = ? AND tenant_id = ?", [$filialIdToUse, $tenantId]);
                    if (!$filial_valida) {
                        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                        $filialIdToUse = $filial_padrao['id'] ?? null;
                    }
                }
                if ($filialIdToUse === null) {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma filial encontrada para este estabelecimento. Crie uma filial antes de cadastrar produtos.']);
                    break;
                }
                $filialId = $filialIdToUse;
                
                // Build INSERT query with optional columns
                $columns = ['nome', 'descricao', 'preco_normal', 'preco_mini', 'categoria_id', 'ativo', 'estoque_atual', 'estoque_minimo', 'preco_custo', 'imagem', 'tenant_id', 'filial_id'];
                $values = [$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $estoqueAtual, $estoqueMinimo, $precoCusto, $imagemPath, $tenantId, $filialId];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
                
                if ($hasExibirCardapioColumn) {
                    $columns[] = 'exibir_cardapio_online';
                    $values[] = $exibirCardapioOnline;
                    $placeholders[] = '?';
                }
                
                // SEMPRE adicionar campos de promoção
                $columns[] = 'preco_promocional';
                $values[] = $precoPromocional;
                $placeholders[] = '?';
                
                $columns[] = 'em_promocao';
                $values[] = $emPromocao;
                $placeholders[] = '?';
                
                error_log("PROMOCAO - INSERT - preco_promocional: " . var_export($precoPromocional, true) . ", em_promocao: " . $emPromocao);
                
                $columnsStr = implode(', ', $columns);
                $placeholdersStr = implode(', ', $placeholders);
                
                error_log("SALVAR_PRODUTO - INSERT - Columns: " . $columnsStr);
                error_log("SALVAR_PRODUTO - INSERT - Values count: " . count($values));
                
                $db->query("
                    INSERT INTO produtos ($columnsStr) 
                    VALUES ($placeholdersStr)
                ", $values);
                
                $novoProdutoId = $db->lastInsertId();
                error_log("SALVAR_PRODUTO - INSERT - Novo produto ID: " . $novoProdutoId);
                
                // Salvar ingredientes
                $ingredientesArray = json_decode($ingredientes, true);
                if (is_array($ingredientesArray)) {
                    foreach ($ingredientesArray as $ingredienteId) {
                        $db->query("
                            INSERT INTO produto_ingredientes (produto_id, ingrediente_id, tenant_id, filial_id) 
                            VALUES (?, ?, ?, ?)
                        ", [$novoProdutoId, $ingredienteId, $tenantId, $filialId]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!']);
            } else {
                // Atualizar produto existente
                // Deletar imagem antiga se uma nova foi enviada
                if ($imagemPath) {
                    if ($filialId !== null) {
                        $produtoAtual = $db->fetch("SELECT imagem FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id = ?", [$produtoId, $tenantId, $filialId]);
                    } else {
                        $produtoAtual = $db->fetch("SELECT imagem FROM produtos WHERE id = ? AND tenant_id = ? AND filial_id IS NULL", [$produtoId, $tenantId]);
                    }
                    if ($produtoAtual && !empty($produtoAtual['imagem'])) {
                        try {
                            $minio = \System\Storage\MinIO::getInstance();
                            $minio->deleteByUrl($produtoAtual['imagem']);
                        } catch (Exception $e) {
                            error_log('Erro ao deletar imagem antiga: ' . $e->getMessage());
                            // Continuar mesmo se falhar a deleção
                        }
                    }
                }
                
                // Build UPDATE query with optional columns
                $setParts = ['nome = ?', 'descricao = ?', 'preco_normal = ?', 'preco_mini = ?', 'categoria_id = ?', 'ativo = ?', 'estoque_atual = ?', 'estoque_minimo = ?', 'preco_custo = ?', 'updated_at = CURRENT_TIMESTAMP'];
                $updateValues = [$nome, $descricao, $precoNormal, $precoMini, $categoriaId, $ativo, $estoqueAtual, $estoqueMinimo, $precoCusto];
                
                if ($imagemPath) {
                    $setParts[] = 'imagem = ?';
                    $updateValues[] = $imagemPath;
                }
                
                if ($hasExibirCardapioColumn) {
                    $setParts[] = 'exibir_cardapio_online = ?';
                    $updateValues[] = $exibirCardapioOnline;
                }
                
                // SEMPRE adicionar campos de promoção ao UPDATE
                $setParts[] = 'preco_promocional = ?';
                $updateValues[] = $precoPromocional;
                
                $setParts[] = 'em_promocao = ?';
                $updateValues[] = $emPromocao;
                
                error_log("PROMOCAO - UPDATE - preco_promocional: " . var_export($precoPromocional, true) . ", em_promocao: " . $emPromocao);
                
                $setStr = implode(', ', $setParts);
                
                // Contar placeholders na query SET (não contar CURRENT_TIMESTAMP)
                $setPlaceholdersCount = substr_count($setStr, '?');
                
                error_log("SALVAR_PRODUTO - UPDATE - SET: " . $setStr);
                error_log("SALVAR_PRODUTO - UPDATE - SET placeholders: " . $setPlaceholdersCount);
                error_log("SALVAR_PRODUTO - UPDATE - Values count: " . count($updateValues));
                error_log("SALVAR_PRODUTO - UPDATE - produtoId: " . $produtoId);
                error_log("SALVAR_PRODUTO - UPDATE - tenantId: " . $tenantId);
                error_log("SALVAR_PRODUTO - UPDATE - filialId: " . var_export($filialId, true));
                
                // Validar que o número de placeholders corresponde ao número de valores
                if ($setPlaceholdersCount !== count($updateValues)) {
                    $errorMsg = "ERRO: Número de placeholders ($setPlaceholdersCount) não corresponde ao número de valores (" . count($updateValues) . ")";
                    error_log("SALVAR_PRODUTO - " . $errorMsg);
                    throw new Exception($errorMsg);
                }
                
                try {
                    // Construir WHERE clause com parâmetros preparados
                    $whereClause = "WHERE id = ? AND tenant_id = ?";
                    $whereParams = [$produtoId, $tenantId];
                    
                    if ($filialId !== null) {
                        $whereClause .= " AND filial_id = ?";
                        $whereParams[] = $filialId;
                    } else {
                        $whereClause .= " AND filial_id IS NULL";
                    }
                    
                    // Contar placeholders na WHERE clause
                    $wherePlaceholdersCount = substr_count($whereClause, '?');
                    
                    // Combinar valores de SET com valores de WHERE
                    // $updateValues já contém todos os valores para SET
                    // $whereParams contém os valores para WHERE
                    $allParams = array_merge($updateValues, $whereParams);
                    
                    error_log("PROMOCAO - WHERE clause: " . $whereClause);
                    error_log("PROMOCAO - WHERE placeholders: " . $wherePlaceholdersCount);
                    error_log("PROMOCAO - WHERE params count: " . count($whereParams));
                    error_log("PROMOCAO - SET params count: " . count($updateValues));
                    error_log("PROMOCAO - Total params: " . count($allParams));
                    error_log("PROMOCAO - Total placeholders: " . ($setPlaceholdersCount + $wherePlaceholdersCount));
                    
                    // Validar novamente o total
                    if (($setPlaceholdersCount + $wherePlaceholdersCount) !== count($allParams)) {
                        $errorMsg = "ERRO: Total de placeholders (" . ($setPlaceholdersCount + $wherePlaceholdersCount) . ") não corresponde ao total de parâmetros (" . count($allParams) . ")";
                        error_log("SALVAR_PRODUTO - " . $errorMsg);
                        throw new Exception($errorMsg);
                    }
                    
                    error_log("PROMOCAO - All params: " . json_encode($allParams));
                    
                    $result = $db->query("
                        UPDATE produtos 
                        SET $setStr
                        $whereClause
                    ", $allParams);
                    
                    $rowsAffected = $result->rowCount();
                    error_log("SALVAR_PRODUTO - UPDATE - Rows affected: " . $rowsAffected);
                    
                    if ($rowsAffected === 0) {
                        error_log("SALVAR_PRODUTO - WARNING: Nenhuma linha foi atualizada. Verificar se produto existe e se tenant_id/filial_id estão corretos.");
                        // Verificar se produto existe
                        $produtoExiste = $db->fetch("SELECT id, tenant_id, filial_id FROM produtos WHERE id = ?", [$produtoId]);
                        if (!$produtoExiste) {
                            throw new Exception('Produto não encontrado para atualização');
                        } else {
                            error_log("SALVAR_PRODUTO - Produto existe mas não foi atualizado. Produto tenant_id: " . ($produtoExiste['tenant_id'] ?? 'null') . ", filial_id: " . ($produtoExiste['filial_id'] ?? 'null'));
                            error_log("SALVAR_PRODUTO - Tentando atualizar com tenant_id: " . $tenantId . ", filial_id: " . $filialId);
                            // Tentar atualizar sem verificar filial_id se for null
                            if ($filialId === null) {
                                $result = $db->query("
                                    UPDATE produtos 
                                    SET $setStr
                                    WHERE id = ? AND tenant_id = $tenantId
                                ", $updateValues);
                                $rowsAffected = $result->rowCount();
                                error_log("SALVAR_PRODUTO - UPDATE sem filial_id - Rows affected: " . $rowsAffected);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("SALVAR_PRODUTO - Erro ao executar UPDATE: " . $e->getMessage());
                    throw $e;
                }
                
                // Atualizar ingredientes
                if ($filialId !== null) {
                    $db->query("DELETE FROM produto_ingredientes WHERE produto_id = ? AND tenant_id = ? AND filial_id = ?", [$produtoId, $tenantId, $filialId]);
                } else {
                    $db->query("DELETE FROM produto_ingredientes WHERE produto_id = ? AND tenant_id = ? AND filial_id IS NULL", [$produtoId, $tenantId]);
                }
                
                $ingredientesArray = json_decode($ingredientes, true);
                if (is_array($ingredientesArray)) {
                    foreach ($ingredientesArray as $ingredienteId) {
                        $db->query("
                            INSERT INTO produto_ingredientes (produto_id, ingrediente_id, tenant_id, filial_id) 
                            VALUES (?, ?, ?, ?)
                        ", [$produtoId, $ingredienteId, $tenantId, $filialId]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_produto':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do produto é obrigatório');
            }
            
            $db->query("DELETE FROM produtos WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);
            echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            break;
            
        case 'salvar_categoria':
            $categoriaId = $_POST['categoria_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $parentId = $_POST['parent_id'] ?? '';
            $ativo = $_POST['ativo'] ?? 0;
            
            // Tratar valores vazios
            if ($parentId === '' || $parentId === null) $parentId = null;
            
            if (empty($nome)) {
                throw new Exception('Nome da categoria é obrigatório');
            }
            
            if (empty($categoriaId)) {
                // Criar nova categoria - usar as variáveis já definidas no início do arquivo
                // Normalizar filial: se ausente ou inválida, usar filial padrão do tenant
                $filialIdToUse = $filialId;
                if ($filialIdToUse === null) {
                    $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                    $filialIdToUse = $filial_padrao['id'] ?? null;
                } else {
                    // Validar se a filial existe para este tenant
                    $filial_valida = $db->fetch("SELECT id FROM filiais WHERE id = ? AND tenant_id = ?", [$filialIdToUse, $tenantId]);
                    if (!$filial_valida) {
                        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                        $filialIdToUse = $filial_padrao['id'] ?? null;
                    }
                }
                if ($filialIdToUse === null) {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma filial encontrada para este estabelecimento. Crie uma filial antes de cadastrar categorias.']);
                    break;
                }
                $filialId = $filialIdToUse;
                
                $db->query("
                    INSERT INTO categorias (nome, descricao, parent_id, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$nome, $descricao, $parentId, $ativo, $tenantId, $filialIdToUse]);
                echo json_encode(['success' => true, 'message' => 'Categoria criada com sucesso!']);
            } else {
                // Atualizar categoria existente
                $db->query("
                    UPDATE categorias 
                    SET nome = ?, descricao = ?, parent_id = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId
                ", [$nome, $descricao, $parentId, $ativo, $categoriaId]);
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada com sucesso!']);
            }
            break;
            
        case 'excluir_categoria':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID da categoria é obrigatório');
            }
            
            $db->query("DELETE FROM categorias WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);
            echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
            break;
            
        case 'salvar_ingrediente':
            $ingredienteId = $_POST['ingrediente_id'] ?? $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $precoAdicional = $_POST['preco_adicional'] ?? 0;
            $ativo = $_POST['ativo'] ?? 0;
            
            if (empty($nome)) {
                throw new Exception('Nome do ingrediente é obrigatório');
            }
            
            if (empty($ingredienteId)) {
                // Criar novo ingrediente
                // Normalizar filial: se ausente ou inválida, usar filial padrão do tenant
                $filialIdToUse = $filialId;
                if ($filialIdToUse === null) {
                    $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                    $filialIdToUse = $filial_padrao['id'] ?? null;
                } else {
                    // Validar se a filial existe para este tenant
                    $filial_valida = $db->fetch("SELECT id FROM filiais WHERE id = ? AND tenant_id = ?", [$filialIdToUse, $tenantId]);
                    if (!$filial_valida) {
                        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenantId]);
                        $filialIdToUse = $filial_padrao['id'] ?? null;
                    }
                }
                if ($filialIdToUse === null) {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma filial encontrada para este estabelecimento. Crie uma filial antes de cadastrar ingredientes.']);
                    break;
                }
                $db->query("
                    INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, tenant_id, filial_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$nome, $descricao, $precoAdicional, $ativo, $tenantId, $filialIdToUse]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente criado com sucesso!']);
            } else {
                // Atualizar ingrediente existente
                $db->query("
                    UPDATE ingredientes 
                    SET nome = ?, descricao = ?, preco_adicional = ?, ativo = ? 
                    WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId
                ", [$nome, $descricao, $precoAdicional, $ativo, $ingredienteId]);
                echo json_encode(['success' => true, 'message' => 'Ingrediente atualizado com sucesso!']);
            }
            break;
            
        case 'excluir_ingrediente':
            $id = $_POST['id'] ?? $_GET['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do ingrediente é obrigatório');
            }
            
            $db->query("DELETE FROM ingredientes WHERE id = ? AND tenant_id = $tenantId AND filial_id = $filialId", [$id]);
            echo json_encode(['success' => true, 'message' => 'Ingrediente excluído com sucesso!']);
            break;
            
        default:
            throw new Exception('Ação não encontrada: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    error_log("AJAX Fatal Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro fatal: ' . $e->getMessage()]);
}
?>
