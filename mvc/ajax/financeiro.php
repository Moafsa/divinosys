<?php
// Desabilitar exibição de erros na saída (para não corromper JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Ainda loga erros, mas não exibe

// Iniciar output buffering para capturar qualquer saída inesperada
ob_start();

session_start();
header('Content-Type: application/json');

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
    
    // Validar tenant_id - obrigatório
    $tenantId = $session->getTenantId();
    if (!$tenantId) {
        throw new \Exception('Tenant ID não encontrado na sessão. Faça login novamente.');
    }
    
    // Obter filial_id - buscar padrão se não estiver na sessão
    $filialId = $session->getFilialId();
    if ($filialId === null) {
        $filial_padrao = $db->fetch("SELECT id FROM filiais WHERE tenant_id = ? LIMIT 1", [$tenantId]);
        $filialId = $filial_padrao ? $filial_padrao['id'] : null;
    }
    
    $userId = $session->getUserId();
    
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
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'pedido' => $pedido
            ]);
            exit;
        
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
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);
            exit;
            
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
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'total_recebiveis' => $resultado['total_recebiveis'] ?? 0
            ]);
            exit;
            
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
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido excluído com sucesso!'
                ]);
                exit;
                
            } catch (\Exception $e) {
                // Rollback transaction
                $db->rollback();
                throw $e;
            }
            

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
                    'nome_cliente' => $pedido['cliente'] ?? null,
                    'telefone_cliente' => $pedido['telefone_cliente'] ?? null,
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
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Pagamento registrado com sucesso!',
                    'pedido_id' => $pedidoId,
                    'pedido_quitado' => $novoStatusPagamento === 'quitado',
                    'mesa_liberada' => $mesaLiberada,
                    'saldo_restante' => $novoSaldoDevedor
                ]);
                exit;
                
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
            
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
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento criado com sucesso!',
                'lancamento_id' => $lancamentoId
            ]);
            exit;
            
        case 'criar_categoria':
            $nome = $_POST['nome'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $cor = $_POST['cor'] ?? '#007bff';
            
            if (empty($nome) || empty($tipo)) {
                throw new \Exception('Nome e tipo são obrigatórios');
            }
            
            if (!in_array($tipo, ['receita', 'despesa'])) {
                throw new \Exception('Tipo inválido. Use: receita ou despesa');
            }
            
            // Create category
            $categoria_id = $db->insert('categorias_financeiras', [
                'nome' => $nome,
                'tipo' => $tipo,
                'descricao' => $descricao,
                'cor' => $cor,
                'icone' => $tipo === 'receita' ? 'fas fa-plus-circle' : 'fas fa-minus-circle',
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true
            ]);
            
            if (!$categoria_id) {
                throw new \Exception('Erro ao criar categoria');
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Categoria criada com sucesso',
                'categoria_id' => $categoria_id
            ]);
            exit;
            
        case 'criar_conta':
            $nome = $_POST['nome'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $saldoInicial = floatval($_POST['saldo_inicial'] ?? 0);
            $cor = $_POST['cor'] ?? '#28a745';
            
            if (empty($nome) || empty($tipo)) {
                throw new \Exception('Nome e tipo são obrigatórios');
            }
            
            if (!in_array($tipo, ['caixa', 'banco', 'pix', 'cartao', 'outros'])) {
                throw new \Exception('Tipo inválido');
            }
            
            // Icon mapping
            $icones = [
                'caixa' => 'fas fa-cash-register',
                'banco' => 'fas fa-university',
                'pix' => 'fas fa-mobile-alt',
                'cartao' => 'fas fa-credit-card',
                'outros' => 'fas fa-wallet'
            ];
            
            // Create account
            $conta_id = $db->insert('contas_financeiras', [
                'nome' => $nome,
                'tipo' => $tipo,
                'saldo_inicial' => $saldoInicial,
                'saldo_atual' => $saldoInicial,
                'cor' => $cor,
                'icone' => $icones[$tipo] ?? 'fas fa-wallet',
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true
            ]);
            
            if (!$conta_id) {
                throw new \Exception('Erro ao criar conta');
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Conta criada com sucesso',
                'conta_id' => $conta_id
            ]);
            exit;
            
        case 'excluir_categoria':
            $categoriaId = $_POST['categoria_id'] ?? '';
            
            if (empty($categoriaId)) {
                throw new \Exception('ID da categoria é obrigatório');
            }
            
            // Check if category belongs to tenant
            $categoria = $db->fetch(
                "SELECT id FROM categorias_financeiras WHERE id = ? AND tenant_id = ?",
                [$categoriaId, $tenantId]
            );
            
            if (!$categoria) {
                throw new \Exception('Categoria não encontrada ou não pertence a este estabelecimento');
            }
            
            // Soft delete - just set ativo = false
            $db->query(
                "UPDATE categorias_financeiras SET ativo = false WHERE id = ? AND tenant_id = ?",
                [$categoriaId, $tenantId]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Categoria excluída com sucesso'
            ]);
            exit;
            
        case 'excluir_conta':
            $contaId = $_POST['conta_id'] ?? '';
            
            if (empty($contaId)) {
                throw new \Exception('ID da conta é obrigatório');
            }
            
            // Check if account belongs to tenant
            $conta = $db->fetch(
                "SELECT id FROM contas_financeiras WHERE id = ? AND tenant_id = ?",
                [$contaId, $tenantId]
            );
            
            if (!$conta) {
                throw new \Exception('Conta não encontrada ou não pertence a este estabelecimento');
            }
            
            // Soft delete - just set ativo = false
            $db->query(
                "UPDATE contas_financeiras SET ativo = false WHERE id = ? AND tenant_id = ?",
                [$contaId, $tenantId]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Conta excluída com sucesso'
            ]);
            exit;
            
        case 'excluir_lancamento':
            $lancamentoId = $_POST['lancamento_id'] ?? '';
            
            if (empty($lancamentoId)) {
                throw new \Exception('ID do lançamento é obrigatório');
            }
            
            // Check if lancamento belongs to tenant
            $lancamento = $db->fetch(
                "SELECT id FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ?",
                [$lancamentoId, $tenantId]
            );
            
            if (!$lancamento) {
                throw new \Exception('Lançamento não encontrado ou não pertence a este estabelecimento');
            }
            
            // Delete lancamento (hard delete is OK for financial entries)
            $db->query(
                "DELETE FROM lancamentos_financeiros WHERE id = ? AND tenant_id = ?",
                [$lancamentoId, $tenantId]
            );
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Lançamento excluído com sucesso'
            ]);
            exit;
            
        default:
            throw new \Exception('Action not implemented: ' . $action);
    }
    
} catch (\Exception $e) {
    // Limpar qualquer output inesperado
    ob_end_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

?>
