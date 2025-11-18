<?php
// Desabilitar exibição de erros para garantir resposta JSON limpa
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Iniciar output buffering para capturar qualquer saída indesejada
ob_start();

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'System\\' => __DIR__ . '/../../system/',
        'App\\' => __DIR__ . '/../../app/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Simples e direto - usar require_once
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Debug log
    error_log("Financeiro AJAX - Action: " . $action);
    error_log("Financeiro AJAX - POST data: " . json_encode($_POST));
    
    if (empty($action)) {
        throw new \Exception('No action specified');
    }
    
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    
    // Debug session data
    error_log("FINANCEIRO: Session data - " . json_encode([
        'user_id' => $session->getUserId(),
        'tenant_id' => $session->getTenantId(),
        'filial_id' => $session->getFilialId(),
        'is_logged_in' => $session->isLoggedIn()
    ]));
    
    $userId = $session->getUserId() ?? 1;
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    switch ($action) {
        
        case 'buscar_dados_pedido':
            $pedidoId = $_GET['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('Pedido ID é obrigatório');
            }
            
            // Buscar dados completos do pedido
            $pedido = $db->fetch(
                "SELECT DISTINCT p.idpedido, p.data, p.hora_pedido, p.cliente, 
                        p.idmesa, p.valor_total, p.status_pagamento, p.status, p.observacao,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_nao_fiado,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_fiado,
                        (SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago,
                        (p.valor_total - COALESCE((SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id), 0)) as saldo_devedor_real
                 FROM pedido p
                 WHERE p.idpedido = ? 
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?
                 AND EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )",
                [$pedidoId, $tenantId, $filialId, $tenantId, $filialId]
            );
            
            if (!$pedido) {
                throw new \Exception('Pedido fiado não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'pedido' => $pedido
            ]);
            break;
        
        case 'buscar_pedidos_fiado':
            error_log("financeiro.php - buscar_pedidos_fiado - Tenant: $tenantId, Filial: $filialId");
            
            // Buscar pedidos que tenham valores FIADO pendentes (não quitados)
            $pedidos = $db->fetchAll(
                "SELECT DISTINCT p.idpedido, p.data, p.hora_pedido, p.cliente, 
                        p.idmesa, p.valor_total, p.status_pagamento, p.status, p.observacao,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento != 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_nao_fiado,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago_fiado,
                        (SELECT SUM(valor_pago) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as total_pago,
                        (SELECT SUM(CASE WHEN pp.forma_pagamento = 'FIADO' THEN pp.valor_pago ELSE 0 END) FROM pagamentos_pedido pp WHERE pp.pedido_id = p.idpedido AND pp.tenant_id = p.tenant_id AND pp.filial_id = p.filial_id) as saldo_fiado_pendente
                 FROM pedido p
                 WHERE EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?
                 ORDER BY p.data DESC, p.hora_pedido DESC",
                [$tenantId, $filialId, $tenantId, $filialId]
            );
            
            error_log("financeiro.php - buscar_pedidos_fiado - Encontrados: " . count($pedidos) . " pedidos");
            
            if (empty($pedidos)) {
                error_log("financeiro.php - buscar_pedidos_fiado - NENHUM pedido encontrado. Verificando se existem pagamentos fiado...");
                $checkFiado = $db->fetch(
                    "SELECT COUNT(*) as total FROM pagamentos_pedido WHERE forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$tenantId, $filialId]
                );
                error_log("financeiro.php - buscar_pedidos_fiado - Total de pagamentos FIADO no banco: " . ($checkFiado['total'] ?? 0));
            }
            
            // Filtrar apenas pedidos com saldo fiado real pendente
            $pedidosFiltrados = [];
            foreach ($pedidos as $pedido) {
                // Calcular saldo fiado real (fiado original - fiado quitado)
                $fiadoOriginal = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $fiadoQuitado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?
                     AND descricao LIKE '%fiado%'",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $saldoFiadoReal = ($fiadoOriginal['total'] ?? 0) - ($fiadoQuitado['total'] ?? 0);
                
                error_log("financeiro.php - Pedido #{$pedido['idpedido']}: Fiado Original = " . ($fiadoOriginal['total'] ?? 0) . ", Fiado Quitado = " . ($fiadoQuitado['total'] ?? 0) . ", Saldo = $saldoFiadoReal");
                
                // Incluir todos os pedidos que tiveram FIADO (mesmo se já quitados)
                // O frontend mostrará o status correto
                $pedido['saldo_fiado_pendente'] = $saldoFiadoReal;
                $pedidosFiltrados[] = $pedido;
            }
            
            $pedidos = $pedidosFiltrados;
            
            error_log("financeiro.php - buscar_pedidos_fiado - Retornando " . count($pedidos) . " pedidos após filtragem");
            
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);
            break;
            
        case 'buscar_total_recebiveis_fiado':
            // Calcular saldo fiado real (fiado original - fiado quitado)
            $totalRecebiveis = 0;
            $pedidos = $db->fetchAll(
                "SELECT DISTINCT p.idpedido FROM pedido p
                 WHERE EXISTS (
                     SELECT 1 FROM pagamentos_pedido pp 
                     WHERE pp.pedido_id = p.idpedido 
                     AND pp.forma_pagamento = 'FIADO'
                     AND pp.tenant_id = ? 
                     AND pp.filial_id = ?
                 )
                 AND p.tenant_id = ? 
                 AND p.filial_id = ?",
                [$tenantId, $filialId, $tenantId, $filialId]
            );
            
            foreach ($pedidos as $pedido) {
                // Calcular saldo fiado real (fiado original - fiado quitado)
                $fiadoOriginal = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $fiadoQuitado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?
                     AND descricao LIKE '%fiado%'",
                    [$pedido['idpedido'], $tenantId, $filialId]
                );
                
                $saldoFiadoReal = ($fiadoOriginal['total'] ?? 0) - ($fiadoQuitado['total'] ?? 0);
                
                if ($saldoFiadoReal > 0.01) {
                    $totalRecebiveis += $saldoFiadoReal;
                }
            }
            
            $resultado = ['total_recebiveis' => $totalRecebiveis];
            
            echo json_encode([
                'success' => true,
                'total_recebiveis' => $resultado['total_recebiveis'] ?? 0
            ]);
            break;
            
        case 'excluir_pedido_fiado':
            $pedidoId = $_POST['pedido_id'] ?? '';
            
            if (empty($pedidoId)) {
                throw new \Exception('ID do pedido não informado');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Verificar se o pedido existe e pertence ao tenant/filial
                $pedido = $db->fetch(
                    "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }
                
                // Verificar se o pedido tem pagamentos fiado
                $temFiado = $db->fetch(
                    "SELECT COUNT(*) as count FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if ($temFiado['count'] == 0) {
                    throw new \Exception('Este pedido não possui pagamentos fiado');
                }
                
                // Excluir todos os pagamentos do pedido
                $db->delete(
                    'pagamentos_pedido',
                    'pedido_id = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Excluir itens do pedido
                $db->delete(
                    'pedido_itens',
                    'pedido_id = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Excluir o pedido
                $db->delete(
                    'pedido',
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Commit transaction
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido excluído com sucesso!'
                ]);
                
            } catch (\Exception $e) {
                // Rollback transaction
                $db->rollback();
                throw $e;
            }
            break;
            

        case 'registrar_pagamento_fiado':
            $pedidoId = $_POST['pedido_id'] ?? '';
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            $valorPago = $_POST['valor_pago'] ?? 0;
            $descricao = $_POST['descricao'] ?? '';
            
            if (empty($pedidoId) || empty($formaPagamento) || $valorPago <= 0) {
                throw new \Exception('Dados incompletos para registrar pagamento');
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Buscar dados do pedido
                $pedido = $db->fetch(
                    "SELECT * FROM pedido WHERE idpedido = ? AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                if (!$pedido) {
                    throw new \Exception('Pedido não encontrado');
                }
                
                // Calcular valor fiado pendente
                $totalFiado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento = 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $valorFiadoPendente = $totalFiado['total'] ?? 0;
                
                // Verificar se o valor não excede o saldo fiado
                if ($valorPago > $valorFiadoPendente + 0.01) {
                    throw new \Exception('Valor não pode ser maior que o saldo fiado pendente');
                }
                
                // Registrar o pagamento
                $db->insert('pagamentos_pedido', [
                    'pedido_id' => $pedidoId,
                    'valor_pago' => $valorPago,
                    'forma_pagamento' => $formaPagamento,
                    'nome_cliente' => $pedido['cliente'],
                    'telefone_cliente' => $pedido['telefone_cliente'],
                    'descricao' => $descricao ?: 'Pagamento de pedido fiado',
                    'usuario_id' => $userId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
                
                // Recalcular totais após o pagamento (apenas pagamentos não-fiado)
                $novoTotalPagoNaoFiado = $db->fetch(
                    "SELECT SUM(valor_pago) as total FROM pagamentos_pedido 
                     WHERE pedido_id = ? AND forma_pagamento != 'FIADO' AND tenant_id = ? AND filial_id = ?",
                    [$pedidoId, $tenantId, $filialId]
                );
                
                $novoValorPagoNaoFiado = $novoTotalPagoNaoFiado['total'] ?? 0;
                $novoSaldoDevedor = $pedido['valor_total'] - $novoValorPagoNaoFiado;
                
                // Manter status como 'quitado' se já estava quitado
                // (pagamentos parciais de fiado não devem alterar o status)
                $novoStatusPagamento = $pedido['status_pagamento'];
                
                // Atualizar pedido
                $db->update(
                    'pedido',
                    [
                        'valor_pago' => $novoValorPagoNaoFiado,
                        'saldo_devedor' => $novoSaldoDevedor,
                        'status_pagamento' => $novoStatusPagamento,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'idpedido = ? AND tenant_id = ? AND filial_id = ?',
                    [$pedidoId, $tenantId, $filialId]
                );
                
                // Se totalmente quitado, liberar mesa se necessário
                $mesaLiberada = false;
                if ($novoStatusPagamento === 'quitado') {
                    // Verificar se todos os pedidos da mesa estão quitados
                    $pedidosPendentes = $db->fetch(
                        "SELECT COUNT(*) as total FROM pedido 
                         WHERE idmesa = ? AND status NOT IN ('Finalizado', 'Cancelado') 
                         AND status_pagamento != 'quitado' AND tenant_id = ? AND filial_id = ?",
                        [$pedido['idmesa'], $tenantId, $filialId]
                    );
                    
                    if (($pedidosPendentes['total'] ?? 0) === 0) {
                        $db->update(
                            'mesas',
                            ['status' => 'livre'],
                            'id_mesa = ? AND tenant_id = ? AND filial_id = ?',
                            [$pedido['idmesa'], $tenantId, $filialId]
                        );
                        $mesaLiberada = true;
                    }
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pagamento registrado com sucesso!',
                    'pedido_id' => $pedidoId,
                    'pedido_quitado' => $novoStatusPagamento === 'quitado',
                    'mesa_liberada' => $mesaLiberada,
                    'saldo_restante' => $novoSaldoDevedor
                ]);
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'criar_lancamento':
            // Get form data
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataLancamento = $_POST['data_lancamento'] ?? '';
            $categoriaId = $_POST['categoria_id'] ?? '';
            $contaId = $_POST['conta_id'] ?? '';
            $status = $_POST['status'] ?? 'confirmado';
            $observacoes = $_POST['observacoes'] ?? '';
            
            // Validate required fields
            if (empty($tipoLancamento) || empty($descricao) || empty($valor) || empty($dataLancamento) || empty($contaId)) {
                throw new \Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($tipoLancamento, ['receita', 'despesa', 'transferencia'])) {
                throw new \Exception('Tipo de lançamento inválido');
            }
            
            if (!in_array($status, ['pendente', 'confirmado'])) {
                throw new \Exception('Status inválido');
            }
            
            $valor = (float) $valor;
            if ($valor <= 0) {
                throw new \Exception('Valor deve ser maior que zero');
            }
            
            // Create tables if they don't exist
            $db->query("
                CREATE TABLE IF NOT EXISTS categorias_financeiras (
                    id SERIAL PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('receita', 'despesa')),
                    descricao TEXT,
                    cor VARCHAR(7) DEFAULT '#007bff',
                    ativo BOOLEAN DEFAULT true,
                    tenant_id INTEGER NOT NULL,
                    filial_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $db->query("
                CREATE TABLE IF NOT EXISTS contas_financeiras (
                    id SERIAL PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('caixa', 'banco', 'cartao', 'outros')),
                    saldo_atual DECIMAL(10,2) DEFAULT 0.00,
                    descricao TEXT,
                    ativo BOOLEAN DEFAULT true,
                    tenant_id INTEGER NOT NULL,
                    filial_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $db->query("
                CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    filial_id INTEGER,
                    tipo_lancamento VARCHAR(20) NOT NULL CHECK (tipo_lancamento IN ('receita', 'despesa', 'transferencia')),
                    categoria_id INTEGER,
                    conta_id INTEGER NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    data_lancamento DATE NOT NULL,
                    descricao TEXT NOT NULL,
                    observacoes TEXT,
                    status VARCHAR(20) DEFAULT 'confirmado' CHECK (status IN ('pendente', 'confirmado', 'cancelado')),
                    usuario_id INTEGER,
                    pedido_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert default data if needed
            $categorias = $db->fetchAll("SELECT * FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
            if (empty($categorias)) {
                $db->insert('categorias_financeiras', [
                    'nome' => 'Vendas',
                    'tipo' => 'receita',
                    'cor' => '#28a745',
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            $contas = $db->fetchAll("SELECT * FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ?", [$tenantId, $filialId]);
            if (empty($contas)) {
                $db->insert('contas_financeiras', [
                    'nome' => 'Caixa',
                    'tipo' => 'caixa',
                    'saldo_atual' => 0.00,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            // If no categoria_id provided, use first available
            if (empty($categoriaId)) {
                $categoria = $db->fetch("SELECT id FROM categorias_financeiras WHERE tenant_id = ? AND filial_id = ? LIMIT 1", [$tenantId, $filialId]);
                $categoriaId = $categoria['id'] ?? null;
            }
            
            // If no conta_id provided, use first available
            if (empty($contaId)) {
                $conta = $db->fetch("SELECT id FROM contas_financeiras WHERE tenant_id = ? AND filial_id = ? LIMIT 1", [$tenantId, $filialId]);
                $contaId = $conta['id'] ?? null;
            }
            
            if (!$contaId) {
                throw new \Exception('Nenhuma conta disponível');
            }
            
            // Handle transfer logic
            if ($tipoLancamento === 'transferencia') {
                $contaDestinoId = $_POST['conta_destino_id'] ?? '';
                if (empty($contaDestinoId)) {
                    throw new \Exception('Conta destino é obrigatória para transferências');
                }
                if ($contaId === $contaDestinoId) {
                    throw new \Exception('Conta origem e destino devem ser diferentes');
                }
                
                // Create two entries for transfer: debit and credit
                $lancamentoId = $db->insert('lancamentos_financeiros', [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'tipo_lancamento' => 'despesa',
                    'categoria_id' => $categoriaId,
                    'conta_id' => $contaId,
                    'valor' => $valor,
                    'data_lancamento' => $dataLancamento,
                    'descricao' => 'Transferência: ' . $descricao,
                    'observacoes' => $observacoes,
                    'status' => $status,
                    'usuario_id' => $userId
                ]);
                
                $lancamentoId2 = $db->insert('lancamentos_financeiros', [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'tipo_lancamento' => 'receita',
                    'categoria_id' => $categoriaId,
                    'conta_id' => $contaDestinoId,
                    'valor' => $valor,
                    'data_lancamento' => $dataLancamento,
                    'descricao' => 'Transferência: ' . $descricao,
                    'observacoes' => $observacoes,
                    'status' => $status,
                    'usuario_id' => $userId
                ]);
            } else {
                // Create single entry for revenue/expense
                $lancamentoId = $db->insert('lancamentos_financeiros', [
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'tipo_lancamento' => $tipoLancamento,
                    'categoria_id' => $categoriaId,
                    'conta_id' => $contaId,
                    'valor' => $valor,
                    'data_lancamento' => $dataLancamento,
                    'descricao' => $descricao,
                    'observacoes' => $observacoes,
                    'status' => $status,
                    'usuario_id' => $userId
                ]);
            }
            
            if (!$lancamentoId) {
                throw new \Exception('Erro ao criar lançamento');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento criado com sucesso!',
                'lancamento_id' => $lancamentoId
            ]);
            break;
            
        case 'criar_categoria_rapida':
            error_log("========== CRIAR CATEGORIA RAPIDA ==========");
            error_log("POST data: " . json_encode($_POST));
            error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
            error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));
            
            $nome = trim($_POST['nome'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $cor = trim($_POST['cor'] ?? '#007bff');
            $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
            
            error_log("Parsed values - Nome: '$nome', Tipo: '$tipo', Cor: '$cor', TipoLancamento: '$tipoLancamento'");
            
            if (empty($nome)) {
                error_log("ERRO: Nome vazio");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'O nome é obrigatório'
                ]);
                exit;
            }
            
            // Se não informou tipo, usar padrão baseado no tipo de lançamento do formulário ou 'despesa'
            if (empty($tipo)) {
                $tipoLancamento = $_POST['tipo_lancamento'] ?? '';
                $tipo = ($tipoLancamento === 'receita') ? 'receita' : 'despesa';
            }
            
            // Validar tipo apenas se informado
            if (!empty($tipo) && !in_array($tipo, ['receita', 'despesa'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo inválido. Use "receita" ou "despesa"'
                ]);
                exit;
            }
            
            $categoriaId = $db->insert('categorias_financeiras', [
                'nome' => $nome,
                'tipo' => $tipo,
                'cor' => $cor,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true
            ]);
            
            if (!$categoriaId) {
                throw new \Exception('Erro ao criar categoria');
            }
            
            $categoria = $db->fetch(
                "SELECT * FROM categorias_financeiras WHERE id = ?",
                [$categoriaId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Categoria criada com sucesso!',
                'categoria' => $categoria
            ]);
            break;
            
        case 'criar_conta_rapida':
            error_log("========== CRIAR CONTA RAPIDA ==========");
            error_log("POST data: " . json_encode($_POST));
            error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
            error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));
            
            $nome = trim($_POST['nome'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $saldoInicial = $_POST['saldo_inicial'] ?? 0;
            
            error_log("Parsed values - Nome: '$nome', Tipo: '$tipo', SaldoInicial: '$saldoInicial'");
            
            if (empty($nome)) {
                error_log("ERRO: Nome vazio");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'O nome é obrigatório'
                ]);
                exit;
            }
            
            // Se não informou tipo, usar 'outros' como padrão
            if (empty($tipo)) {
                $tipo = 'outros';
            }
            
            // Validar tipo apenas se informado
            if (!empty($tipo) && !in_array($tipo, ['caixa', 'banco', 'cartao', 'pix', 'outros'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo inválido. Use: caixa, banco, cartao, pix ou outros'
                ]);
                exit;
            }
            
            $contaId = $db->insert('contas_financeiras', [
                'nome' => $nome,
                'tipo' => $tipo,
                'saldo_inicial' => (float) $saldoInicial,
                'saldo_atual' => (float) $saldoInicial,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true
            ]);
            
            if (!$contaId) {
                throw new \Exception('Erro ao criar conta');
            }
            
            $conta = $db->fetch(
                "SELECT * FROM contas_financeiras WHERE id = ?",
                [$contaId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Conta criada com sucesso!',
                'conta' => $conta
            ]);
            break;
            
        case 'pesquisar_produto_vendido':
            $nomeProduto = trim($_POST['nome_produto'] ?? '');
            $dataInicio = $_POST['data_inicio'] ?? date('Y-m-01');
            $dataFim = $_POST['data_fim'] ?? date('Y-m-t');
            
            if (empty($nomeProduto)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nome do produto é obrigatório'
                ]);
                exit;
            }
            
            // Buscar produtos que correspondem ao nome
            $produtos = $db->fetchAll(
                "SELECT id, nome FROM produtos 
                 WHERE tenant_id = ? AND filial_id = ? 
                 AND LOWER(nome) LIKE LOWER(?)
                 ORDER BY nome",
                [$tenantId, $filialId, "%{$nomeProduto}%"]
            );
            
            if (empty($produtos)) {
                echo json_encode([
                    'success' => true,
                    'produto' => null,
                    'message' => 'Nenhum produto encontrado'
                ]);
                exit;
            }
            
            // Buscar vendas do primeiro produto encontrado (ou pode ser melhorado para mostrar todos)
            $produtoId = $produtos[0]['id'];
            $produtoNome = $produtos[0]['nome'];
            
            // Buscar estatísticas de vendas
            $estatisticas = $db->fetch(
                "SELECT 
                    COUNT(DISTINCT pi.pedido_id) as total_pedidos,
                    SUM(pi.quantidade) as quantidade_vendida,
                    SUM(pi.valor_total) as receita_total,
                    AVG(pi.valor_unitario) as valor_unitario_medio
                 FROM pedido_itens pi
                 INNER JOIN pedido p ON pi.pedido_id = p.idpedido
                 WHERE pi.produto_id = ?
                 AND pi.tenant_id = ?
                 AND pi.filial_id = ?
                 AND p.data BETWEEN ? AND ?
                 AND p.status_pagamento = 'quitado'",
                [$produtoId, $tenantId, $filialId, $dataInicio, $dataFim]
            );
            
            echo json_encode([
                'success' => true,
                'produto' => [
                    'id' => $produtoId,
                    'nome' => $produtoNome,
                    'quantidade_vendida' => (int) ($estatisticas['quantidade_vendida'] ?? 0),
                    'receita_total' => (float) ($estatisticas['receita_total'] ?? 0),
                    'valor_unitario_medio' => (float) ($estatisticas['valor_unitario_medio'] ?? 0),
                    'total_pedidos' => (int) ($estatisticas['total_pedidos'] ?? 0)
                ]
            ]);
            break;
            
        case 'listar_funcionarios':
            // Buscar todos os usuários do sistema (funcionários)
            // Buscar de duas fontes:
            // 1. Tabela usuarios (sistema antigo)
            // 2. Tabela usuarios_estabelecimento + usuarios_globais (sistema novo)
            
            $funcionarios = [];
            
            try {
                // Buscar da tabela usuarios (sistema antigo)
                $usuariosAntigos = $db->fetchAll(
                    "SELECT id, login, 
                            COALESCE(login, 'Usuário ' || id::text) as nome
                     FROM usuarios 
                     WHERE tenant_id = ? AND filial_id = ?
                     ORDER BY login",
                    [$tenantId, $filialId]
                );
                
                foreach ($usuariosAntigos as $usuario) {
                    $funcionarios[$usuario['id']] = [
                        'id' => $usuario['id'],
                        'login' => $usuario['login'],
                        'nome' => $usuario['nome']
                    ];
                }
            } catch (\Exception $e) {
                error_log("Erro ao buscar usuarios antigos: " . $e->getMessage());
            }
            
            try {
                // Buscar da tabela usuarios_estabelecimento (sistema novo)
                // Primeiro verificar se a tabela existe
                $tabelaExiste = $db->fetch(
                    "SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_name = 'usuarios_estabelecimento'
                    )"
                );
                
                if ($tabelaExiste && $tabelaExiste['exists']) {
                    $usuariosNovos = $db->fetchAll(
                        "SELECT ue.usuario_global_id as id,
                                COALESCE(ug.nome, ug.email, 'Usuário ' || ue.usuario_global_id::text) as nome,
                                COALESCE(ug.email, ue.usuario_global_id::text) as login
                         FROM usuarios_estabelecimento ue
                         LEFT JOIN usuarios_globais ug ON ue.usuario_global_id = ug.id
                         WHERE ue.tenant_id = ? AND ue.filial_id = ? AND ue.ativo = true
                         ORDER BY COALESCE(ug.nome, ug.email, ue.usuario_global_id::text)",
                        [$tenantId, $filialId]
                    );
                    
                    foreach ($usuariosNovos as $usuario) {
                        // Usar usuario_global_id como ID único
                        $id = $usuario['id'];
                        if (!isset($funcionarios[$id])) {
                            $funcionarios[$id] = [
                                'id' => $id,
                                'login' => $usuario['login'] ?? 'usuario_' . $id,
                                'nome' => $usuario['nome'] ?? $usuario['login'] ?? 'Usuário ' . $id
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Erro ao buscar usuarios novos: " . $e->getMessage());
            }
            
            // Converter array associativo para array indexado
            $funcionariosLista = array_values($funcionarios);
            
            // Ordenar por nome
            usort($funcionariosLista, function($a, $b) {
                return strcmp($a['nome'], $b['nome']);
            });
            
            echo json_encode([
                'success' => true,
                'funcionarios' => $funcionariosLista
            ]);
            break;
            
        case 'salvar_pagamento_funcionario':
            // Criar tabela se não existir
            try {
                // Verificar se a tabela já existe
                $tabelaExiste = $db->fetch(
                    "SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_name = 'pagamentos_funcionarios'
                    )"
                );
                
                if (!$tabelaExiste || !$tabelaExiste['exists']) {
                    // Criar tabela sem foreign key restritiva para usuario_id
                    // para permitir tanto usuarios.id quanto usuarios_globais.id
                    $db->query("
                        CREATE TABLE pagamentos_funcionarios (
                            id SERIAL PRIMARY KEY,
                            usuario_id INTEGER NOT NULL,
                            tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
                            filial_id INTEGER REFERENCES filiais(id) ON DELETE SET NULL,
                            tipo_pagamento VARCHAR(20) NOT NULL CHECK (tipo_pagamento IN ('salario', 'adiantamento', 'bonus', 'outros')),
                            valor DECIMAL(10,2) NOT NULL,
                            data_pagamento DATE NOT NULL,
                            data_referencia DATE,
                            descricao TEXT,
                            forma_pagamento VARCHAR(50),
                            conta_id INTEGER REFERENCES contas_financeiras(id) ON DELETE SET NULL,
                            lancamento_financeiro_id INTEGER REFERENCES lancamentos_financeiros(id) ON DELETE SET NULL,
                            status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'pago', 'cancelado')),
                            observacoes TEXT,
                            usuario_pagamento_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                } else {
                    // Se a tabela já existe, tentar remover constraint se houver
                    try {
                        $db->query("
                            ALTER TABLE pagamentos_funcionarios 
                            DROP CONSTRAINT IF EXISTS pagamentos_funcionarios_usuario_id_fkey
                        ");
                    } catch (\Exception $e) {
                        // Ignorar erro se constraint não existir
                    }
                }
            } catch (\Exception $e) {
                error_log("Erro ao criar/ajustar tabela pagamentos_funcionarios: " . $e->getMessage());
            }
            
            $funcionarioId = $_POST['funcionario_id'] ?? '';
            $tipoPagamento = $_POST['tipo_pagamento'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $dataPagamento = $_POST['data_pagamento'] ?? '';
            $dataReferencia = $_POST['data_referencia'] ?? null;
            $formaPagamento = $_POST['forma_pagamento'] ?? null;
            $contaId = $_POST['conta_id'] ?? null;
            $observacoes = $_POST['observacoes'] ?? null;
            
            // Usar observações como descrição também
            $descricao = $observacoes;
            
            if (empty($funcionarioId) || empty($tipoPagamento) || empty($valor) || empty($dataPagamento)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Campos obrigatórios não preenchidos'
                ]);
                exit;
            }
            
            $valor = (float) $valor;
            if ($valor <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Valor deve ser maior que zero'
                ]);
                exit;
            }
            
            // Verificar se funcionário existe (pode estar em usuarios ou usuarios_globais)
            $funcionario = null;
            
            // Tentar buscar da tabela usuarios primeiro
            try {
                $funcionario = $db->fetch(
                    "SELECT id, login, COALESCE(login, 'Usuário ' || id::text) as nome
                     FROM usuarios 
                     WHERE id = ? AND tenant_id = ? AND filial_id = ?",
                    [$funcionarioId, $tenantId, $filialId]
                );
            } catch (\Exception $e) {
                error_log("Erro ao buscar funcionário na tabela usuarios: " . $e->getMessage());
            }
            
            // Se não encontrou, buscar em usuarios_globais
            if (!$funcionario) {
                try {
                    $tabelaExiste = $db->fetch(
                        "SELECT EXISTS (
                            SELECT FROM information_schema.tables 
                            WHERE table_name = 'usuarios_estabelecimento'
                        )"
                    );
                    
                    if ($tabelaExiste && $tabelaExiste['exists']) {
                        $funcionario = $db->fetch(
                            "SELECT ue.usuario_global_id as id,
                                    COALESCE(ug.nome, ug.email, 'Usuário ' || ue.usuario_global_id::text) as nome,
                                    COALESCE(ug.email, ue.usuario_global_id::text) as login
                             FROM usuarios_estabelecimento ue
                             LEFT JOIN usuarios_globais ug ON ue.usuario_global_id = ug.id
                             WHERE ue.usuario_global_id = ? 
                             AND ue.tenant_id = ? 
                             AND ue.filial_id = ? 
                             AND ue.ativo = true",
                            [$funcionarioId, $tenantId, $filialId]
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao buscar funcionário em usuarios_globais: " . $e->getMessage());
                }
            }
            
            if (!$funcionario) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Funcionário não encontrado'
                ]);
                exit;
            }
            
            // Garantir que temos login e nome
            if (!isset($funcionario['login'])) {
                $funcionario['login'] = $funcionario['nome'] ?? 'usuario_' . $funcionarioId;
            }
            if (!isset($funcionario['nome'])) {
                $funcionario['nome'] = $funcionario['login'];
            }
            
            // Criar lançamento financeiro relacionado (despesa)
            $lancamentoId = null;
            if ($contaId) {
                try {
                    // Buscar categoria de despesa padrão ou criar
                    $categoriaDespesa = $db->fetch(
                        "SELECT id FROM categorias_financeiras 
                         WHERE tenant_id = ? AND filial_id = ? 
                         AND tipo = 'despesa' 
                         LIMIT 1",
                        [$tenantId, $filialId]
                    );
                    
                    if (!$categoriaDespesa) {
                        $categoriaDespesaId = $db->insert('categorias_financeiras', [
                            'nome' => 'Pagamento de Funcionários',
                            'tipo' => 'despesa',
                            'cor' => '#dc3545',
                            'icone' => 'fas fa-users',
                            'tenant_id' => $tenantId,
                            'filial_id' => $filialId,
                            'ativo' => true
                        ]);
                    } else {
                        $categoriaDespesaId = $categoriaDespesa['id'];
                    }
                    
                    // Criar descrição automática baseada no tipo e funcionário
                    $tipoLabels = [
                        'salario' => 'Salário',
                        'adiantamento' => 'Adiantamento',
                        'bonus' => 'Bônus',
                        'outros' => 'Outros'
                    ];
                    $tipoLabel = $tipoLabels[$tipoPagamento] ?? ucfirst($tipoPagamento);
                    $descricaoLancamento = "Pagamento de {$tipoLabel} - {$funcionario['nome']}";
                    if ($observacoes) {
                        $descricaoLancamento .= " - {$observacoes}";
                    }
                    
                    $lancamentoId = $db->insert('lancamentos_financeiros', [
                        'tipo_lancamento' => 'despesa',
                        'categoria_id' => $categoriaDespesaId,
                        'conta_id' => $contaId,
                        'valor' => $valor,
                        'data_lancamento' => $dataPagamento,
                        'data_pagamento' => $dataPagamento . ' 00:00:00',
                        'descricao' => $descricaoLancamento,
                        'observacoes' => $observacoes,
                        'forma_pagamento' => $formaPagamento,
                        'status' => 'pago',
                        'usuario_id' => $usuarioId,
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId
                    ]);
                } catch (\Exception $e) {
                    error_log("Erro ao criar lançamento financeiro: " . $e->getMessage());
                }
            }
            
            // Inserir pagamento do funcionário
            // Criar descrição automática se não houver observações
            $descricaoPagamento = $observacoes;
            if (empty($descricaoPagamento)) {
                $tipoLabels = [
                    'salario' => 'Salário',
                    'adiantamento' => 'Adiantamento',
                    'bonus' => 'Bônus',
                    'outros' => 'Outros'
                ];
                $tipoLabel = $tipoLabels[$tipoPagamento] ?? ucfirst($tipoPagamento);
                $descricaoPagamento = "Pagamento de {$tipoLabel}";
            }
            
            // Preparar dados para inserção
            $dadosPagamento = [
                'usuario_id' => $funcionarioId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'tipo_pagamento' => $tipoPagamento,
                'valor' => $valor,
                'data_pagamento' => $dataPagamento,
                'status' => 'pago',
                'usuario_pagamento_id' => $usuarioId
            ];
            
            // Adicionar campos opcionais apenas se não forem null/vazios
            if ($dataReferencia !== null && $dataReferencia !== '') {
                $dadosPagamento['data_referencia'] = $dataReferencia;
            }
            if ($descricaoPagamento !== null && $descricaoPagamento !== '') {
                $dadosPagamento['descricao'] = $descricaoPagamento;
            }
            if ($formaPagamento !== null && $formaPagamento !== '') {
                $dadosPagamento['forma_pagamento'] = $formaPagamento;
            }
            if ($contaId !== null && $contaId !== '') {
                $dadosPagamento['conta_id'] = $contaId;
            }
            if ($lancamentoId !== null) {
                $dadosPagamento['lancamento_financeiro_id'] = $lancamentoId;
            }
            if ($observacoes !== null && $observacoes !== '') {
                $dadosPagamento['observacoes'] = $observacoes;
            }
            
            error_log("SALVAR_PAGAMENTO_FUNCIONARIO: Dados preparados: " . json_encode($dadosPagamento));
            
            try {
                $pagamentoId = $db->insert('pagamentos_funcionarios', $dadosPagamento);
                
                if (!$pagamentoId) {
                    error_log("SALVAR_PAGAMENTO_FUNCIONARIO: Erro - insert retornou false");
                    throw new \Exception('Erro ao registrar pagamento no banco de dados');
                }
                
                error_log("SALVAR_PAGAMENTO_FUNCIONARIO: Pagamento criado com ID: " . $pagamentoId);
            } catch (\Exception $e) {
                error_log("SALVAR_PAGAMENTO_FUNCIONARIO: Exception ao inserir: " . $e->getMessage());
                error_log("SALVAR_PAGAMENTO_FUNCIONARIO: Stack trace: " . $e->getTraceAsString());
                throw $e;
            }
            
            // Limpar qualquer saída anterior antes de enviar JSON
            ob_clean();
            
            echo json_encode([
                'success' => true,
                'message' => 'Pagamento registrado com sucesso!',
                'pagamento_id' => $pagamentoId
            ]);
            break;
            
        default:
            throw new \Exception('Action not implemented: ' . $action);
    }
    
} catch (\Exception $e) {
    // Limpar qualquer saída anterior
    ob_clean();
    
    error_log("Financeiro AJAX - Exception: " . $e->getMessage());
    error_log("Financeiro AJAX - Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'action' => $_POST['action'] ?? $_GET['action'] ?? 'unknown',
            'post_data' => $_POST
        ]
    ]);
} catch (\Error $e) {
    // Limpar qualquer saída anterior
    ob_clean();
    
    error_log("Financeiro AJAX - Error: " . $e->getMessage());
    error_log("Financeiro AJAX - Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage(),
        'debug' => [
            'action' => $_POST['action'] ?? $_GET['action'] ?? 'unknown'
        ]
    ]);
}

// Limpar buffer de saída antes de enviar resposta
ob_end_flush();
?>
