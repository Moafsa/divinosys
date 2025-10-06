<?php
/**
 * AJAX handler para gestão de clientes fiado
 */

header('Content-Type: application/json');

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenant = $session->getTenant();
    $filial = $session->getFilial();
    $user = $session->getUser();

    if (!$tenant || !$filial || !$user) {
        throw new \Exception('Sessão inválida');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'criar_cliente_fiado':
            $nome = trim($_POST['nome'] ?? '');
            $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $endereco = trim($_POST['endereco'] ?? '');
            $limite_credito = floatval($_POST['limite_credito'] ?? 0);
            $status = $_POST['status'] ?? 'ativo';
            $observacoes = trim($_POST['observacoes'] ?? '');

            if (empty($nome)) {
                throw new \Exception('Nome é obrigatório');
            }

            if ($limite_credito < 0) {
                throw new \Exception('Limite de crédito deve ser maior ou igual a zero');
            }

            // Verificar se CPF/CNPJ já existe
            if (!empty($cpf_cnpj)) {
                $existing = $db->fetch(
                    'SELECT id FROM clientes_fiado WHERE cpf_cnpj = ? AND tenant_id = ? AND filial_id = ?',
                    [$cpf_cnpj, $tenant['id'], $filial['id']]
                );
                
                if ($existing) {
                    throw new \Exception('CPF/CNPJ já cadastrado para outro cliente');
                }
            }

            $db->beginTransaction();

            try {
                $clienteId = $db->insert('clientes_fiado', [
                    'nome' => $nome,
                    'cpf_cnpj' => $cpf_cnpj ?: null,
                    'telefone' => $telefone ?: null,
                    'email' => $email ?: null,
                    'endereco' => $endereco ?: null,
                    'limite_credito' => $limite_credito,
                    'saldo_devedor' => 0.00,
                    'status' => $status,
                    'observacoes' => $observacoes ?: null,
                    'tenant_id' => $tenant['id'],
                    'filial_id' => $filial['id']
                ]);

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Cliente cadastrado com sucesso!',
                    'cliente_id' => $clienteId
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'editar_cliente_fiado':
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $endereco = trim($_POST['endereco'] ?? '');
            $limite_credito = floatval($_POST['limite_credito'] ?? 0);
            $status = $_POST['status'] ?? 'ativo';
            $observacoes = trim($_POST['observacoes'] ?? '');

            if ($clienteId <= 0) {
                throw new \Exception('ID do cliente inválido');
            }

            if (empty($nome)) {
                throw new \Exception('Nome é obrigatório');
            }

            if ($limite_credito < 0) {
                throw new \Exception('Limite de crédito deve ser maior ou igual a zero');
            }

            // Verificar se cliente existe
            $cliente = $db->fetch(
                'SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$clienteId, $tenant['id'], $filial['id']]
            );

            if (!$cliente) {
                throw new \Exception('Cliente não encontrado');
            }

            // Verificar se CPF/CNPJ já existe para outro cliente
            if (!empty($cpf_cnpj)) {
                $existing = $db->fetch(
                    'SELECT id FROM clientes_fiado WHERE cpf_cnpj = ? AND id != ? AND tenant_id = ? AND filial_id = ?',
                    [$cpf_cnpj, $clienteId, $tenant['id'], $filial['id']]
                );
                
                if ($existing) {
                    throw new \Exception('CPF/CNPJ já cadastrado para outro cliente');
                }
            }

            $db->beginTransaction();

            try {
                $db->update(
                    'clientes_fiado',
                    [
                        'nome' => $nome,
                        'cpf_cnpj' => $cpf_cnpj ?: null,
                        'telefone' => $telefone ?: null,
                        'email' => $email ?: null,
                        'endereco' => $endereco ?: null,
                        'limite_credito' => $limite_credito,
                        'status' => $status,
                        'observacoes' => $observacoes ?: null,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$clienteId, $tenant['id'], $filial['id']]
                );

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Cliente atualizado com sucesso!'
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'excluir_cliente_fiado':
            $clienteId = intval($_POST['cliente_id'] ?? 0);

            if ($clienteId <= 0) {
                throw new \Exception('ID do cliente inválido');
            }

            // Verificar se cliente existe
            $cliente = $db->fetch(
                'SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$clienteId, $tenant['id'], $filial['id']]
            );

            if (!$cliente) {
                throw new \Exception('Cliente não encontrado');
            }

            // Verificar se tem vendas fiadas pendentes
            $vendasPendentes = $db->fetch(
                'SELECT COUNT(*) as count FROM vendas_fiadas WHERE cliente_id = ? AND status = ?',
                [$clienteId, 'pendente']
            );

            if ($vendasPendentes['count'] > 0) {
                throw new \Exception('Não é possível excluir cliente com vendas fiadas pendentes');
            }

            $db->beginTransaction();

            try {
                // Excluir pagamentos de fiado primeiro
                $db->delete(
                    'pagamentos_fiado',
                    'venda_fiada_id IN (SELECT id FROM vendas_fiadas WHERE cliente_id = ?)',
                    [$clienteId]
                );

                // Excluir vendas fiadas
                $db->delete(
                    'vendas_fiadas',
                    'cliente_id = ?',
                    [$clienteId]
                );

                // Excluir cliente
                $db->delete(
                    'clientes_fiado',
                    'id = ? AND tenant_id = ? AND filial_id = ?',
                    [$clienteId, $tenant['id'], $filial['id']]
                );

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Cliente excluído com sucesso!'
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'alterar_status_cliente':
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $novoStatus = $_POST['novo_status'] ?? '';

            if ($clienteId <= 0) {
                throw new \Exception('ID do cliente inválido');
            }

            if (!in_array($novoStatus, ['ativo', 'bloqueado', 'suspenso'])) {
                throw new \Exception('Status inválido');
            }

            // Verificar se cliente existe
            $cliente = $db->fetch(
                'SELECT * FROM clientes_fiado WHERE id = ? AND tenant_id = ? AND filial_id = ?',
                [$clienteId, $tenant['id'], $filial['id']]
            );

            if (!$cliente) {
                throw new \Exception('Cliente não encontrado');
            }

            $db->update(
                'clientes_fiado',
                [
                    'status' => $novoStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ? AND tenant_id = ? AND filial_id = ?',
                [$clienteId, $tenant['id'], $filial['id']]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Status do cliente alterado com sucesso!'
            ]);
            break;

        case 'listar_clientes':
            $search = trim($_POST['search'] ?? '');
            $status = $_POST['status'] ?? '';
            $debt = $_POST['debt'] ?? '';

            $where = ['tenant_id = ?', 'filial_id = ?'];
            $params = [$tenant['id'], $filial['id']];

            if (!empty($search)) {
                $where[] = '(nome ILIKE ? OR cpf_cnpj ILIKE ? OR telefone ILIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if (!empty($status)) {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            if ($debt === 'com_debito') {
                $where[] = 'saldo_devedor > 0';
            } elseif ($debt === 'sem_debito') {
                $where[] = 'saldo_devedor = 0';
            } elseif ($debt === 'limite_esgotado') {
                $where[] = 'saldo_devedor >= limite_credito';
            }

            $clientes = $db->fetchAll(
                'SELECT * FROM clientes_fiado WHERE ' . implode(' AND ', $where) . ' ORDER BY nome ASC',
                $params
            );

            echo json_encode([
                'success' => true,
                'clientes' => $clientes
            ]);
            break;

        default:
            throw new \Exception('Ação não reconhecida');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
