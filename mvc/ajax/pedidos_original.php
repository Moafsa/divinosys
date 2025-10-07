<?php
session_start();

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';

$config = \System\Config::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$usuarioId = $_SESSION['user_id'] ?? null;
$tenantId = $_SESSION['tenant_id'] ?? null;
$filialId = $_SESSION['filial_id'] ?? null;

if (!$usuarioId || !$tenantId || !$filialId) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'gerar_pedido':
        try {
            $mesaId = $_POST['mesa_id'] ?? '';
            $itens = $_POST['itens'] ?? [];
            $observacao = $_POST['observacao'] ?? '';
            
            if (empty($mesaId) || empty($itens)) {
                throw new \Exception('Dados obrigatórios não fornecidos');
            }
            
            // Calcular valor total
            $valorTotal = 0;
            foreach ($itens as $item) {
                $valorTotal += $item['preco'] * $item['quantidade'];
            }
            
            // Criar pedido
            $pedidoId = $db->insert('pedido', [
                'idmesa' => $mesaId,
                'data_pedido' => date('Y-m-d'),
                'hora_pedido' => date('H:i:s'),
                'valor_total' => $valorTotal,
                'status' => 'Pendente',
                'observacao' => $observacao,
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'delivery' => ($mesaId === '999') ? 1 : 0
            ]);
            
            // Criar itens do pedido
            foreach ($itens as $item) {
                // Preparar ingredientes
                $ingredientesCom = [];
                $ingredientesSem = [];
                
                if (!empty($item['ingredientes_adicionados'])) {
                    foreach ($item['ingredientes_adicionados'] as $ing) {
                        $ingredientesCom[] = $ing['nome'];
                    }
                }
                
                if (!empty($item['ingredientes_removidos'])) {
                    foreach ($item['ingredientes_removidos'] as $ing) {
                        $ingredientesSem[] = $ing['nome'];
                    }
                }
                
                $db->insert('pedido_itens', [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['id'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['preco'],
                    'valor_total' => $item['preco'] * $item['quantidade'],
                    'ingredientes_com' => implode(', ', $ingredientesCom),
                    'ingredientes_sem' => implode(', ', $ingredientesSem),
                    'observacao' => $item['observacao'] ?? '',
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            // Atualizar status da mesa se não for delivery
            if ($mesaId !== '999') {
                $db->update('mesas', [
                    'status' => 'Ocupada'
                ], [
                    'id_mesa' => $mesaId,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'pedido_id' => $pedidoId
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
        break;
}
?>
