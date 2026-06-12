<?php
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

header('Content-Type: application/json');

$session = \System\Session::getInstance();
if (!$session->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$tenantId = $session->getTenantId();
$filialId = $session->getFilialId();
$db = \System\Database::getInstance();

$clienteId = $_POST['cliente_id'] ?? null;
$pedidoId = $_POST['pedido_id'] ?? null;

if (!$clienteId || !$pedidoId) {
    echo json_encode(['success' => false, 'message' => 'Cliente ou Pedido não informados.']);
    exit;
}

try {
    // Buscar o cliente fiado (tabela clientes_fiado ou usuarios_globais)
    $cliente = $db->fetch(
        "SELECT id, nome, wpp FROM clientes_fiado WHERE id = ? AND tenant_id = ?",
        [$clienteId, $tenantId]
    );

    if (!$cliente) {
        throw new Exception('Cliente não encontrado.');
    }

    // Buscar o pedido (pelo ID ou Comanda)
    $pedido = $db->fetch(
        "SELECT * FROM pedido WHERE (idpedido = ? OR idmesa::integer = ?) AND tenant_id = ? AND filial_id = ? AND status != 'Finalizado'",
        [$pedidoId, $pedidoId, $tenantId, $filialId]
    );

    if (!$pedido) {
        throw new Exception('Pedido/Comanda não encontrado ou já finalizado.');
    }

    $db->beginTransaction();

    // Adicionar o valor do pedido ao saldo do cliente fiado
    $valorPedido = (float)$pedido['valor_total'];
    
    // Buscar o valor atual
    $clienteAtual = $db->fetch("SELECT saldo_devedor, qtd_pedidos_fiado FROM clientes_fiado WHERE id = ?", [$clienteId]);
    $novoSaldo = (float)$clienteAtual['saldo_devedor'] + $valorPedido;
    $novaQtd = (int)$clienteAtual['qtd_pedidos_fiado'] + 1;

    $db->update('clientes_fiado', [
        'saldo_devedor' => $novoSaldo,
        'qtd_pedidos_fiado' => $novaQtd
    ], 'id = ?', [$clienteId]);

    // Opcional: Atualizar o pedido para vinculado ao cliente e status fiado
    $db->update('pedido', [
        'status' => 'Fiado',
        'cliente_nome' => $cliente['nome'],
        'cliente_telefone' => $cliente['wpp']
    ], 'idpedido = ?', [$pedido['idpedido']]);

    $db->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Pedido vinculado com sucesso',
        'novo_saldo' => $novoSaldo
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
