<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $db = \System\Database::getInstance();
    $session = \System\Session::getInstance();
    $tenantId = $session->getTenantId() ?? 1;
    $filialId = $session->getFilialId() ?? 1;
    
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    
    if ($action === 'get_next_comanda') {
        // Encontrar o maior número de comanda (usado nas mesas tipo comanda)
        $result = $db->fetch(
            "SELECT MAX(NULLIF(regexp_replace(id_mesa, '\D', '', 'g'), '')::integer) as max_num 
             FROM mesas 
             WHERE tenant_id = ? AND filial_id = ? AND (tipo_atendimento = 'comanda' OR id_mesa LIKE 'comanda_%' OR id_mesa ~ '^\d+$')",
            [$tenantId, $filialId]
        );
        
        $nextNum = ($result && $result['max_num']) ? intval($result['max_num']) + 1 : 1;
        
        echo json_encode(['success' => true, 'next_comanda' => $nextNum]);
        exit;
    }
    
    if ($action === 'vincular') {
        $comandaId = trim($_POST['comanda_id'] ?? '');
        $clienteNome = trim($_POST['cliente_nome'] ?? '');
        $clienteTelefone = trim($_POST['cliente_telefone'] ?? '');
        $clienteCpf = trim($_POST['cliente_cpf'] ?? '');
        
        if (empty($comandaId)) {
            throw new \Exception('Número da comanda é obrigatório');
        }
        
        // Formata a comanda para C-000 se for apenas números
        if (preg_match('/^\d+$/', $comandaId)) {
            $comandaId = 'C-' . str_pad($comandaId, 3, '0', STR_PAD_LEFT);
        } else if (preg_match('/^[cC]-?\d+$/', $comandaId)) {
            // Se já tiver C ou C-, apenas formata o número
            $num = preg_replace('/[^0-9]/', '', $comandaId);
            $comandaId = 'C-' . str_pad($num, 3, '0', STR_PAD_LEFT);
        }
        
        // Garante que as colunas cliente_nome, cliente_telefone e cliente_cpf existem na tabela mesas
        try {
            $db->query("ALTER TABLE mesas ADD COLUMN IF NOT EXISTS cliente_nome VARCHAR(255)");
            $db->query("ALTER TABLE mesas ADD COLUMN IF NOT EXISTS cliente_telefone VARCHAR(20)");
            $db->query("ALTER TABLE mesas ADD COLUMN IF NOT EXISTS cliente_cpf VARCHAR(20)");
            $db->query("ALTER TABLE mesas ADD COLUMN IF NOT EXISTS tipo_atendimento VARCHAR(20) DEFAULT 'ambos'");
        } catch (\Exception $e) {
            // Ignora se não puder alterar
        }
        
        $mesa = $db->fetch(
            'SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?',
            [$comandaId, $tenantId, $filialId]
        );
        
        if (!$mesa) {
            // Se não existir, a gente cria a comanda dinamicamente
            $numero = (int)preg_replace('/[^0-9]/', '', $comandaId);
            if ($numero == 0) $numero = rand(100, 999);

            $novoId = $db->insert('mesas', [
                'id_mesa' => $comandaId,
                'numero' => $numero,
                'capacidade' => 4,
                'status' => 'ocupada',
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'tipo_atendimento' => 'comanda',
                'cliente_nome' => $clienteNome,
                'cliente_telefone' => $clienteTelefone,
                'cliente_cpf' => $clienteCpf
            ]);
            
            echo json_encode(['success' => true, 'comanda_id' => $comandaId, 'message' => 'Comanda criada e vinculada com sucesso!']);
            exit;
        }
        
        // Se a comanda já existe, verifica se está livre ou se já tem outro cliente
        if (!empty($mesa['cliente_nome']) && $mesa['cliente_nome'] !== $clienteNome) {
            // Comanda está em uso por outra pessoa
            // Pode ser que esteja no status Livre, então deixamos sobreescrever
            if ($mesa['status'] !== 'Livre' && $mesa['status'] !== 'livre') {
                throw new \Exception("A comanda $comandaId já está em uso por " . $mesa['cliente_nome']);
            }
        }
        
        // Atualiza a comanda com os dados do cliente
        $db->update('mesas', [
            'cliente_nome' => $clienteNome,
            'cliente_telefone' => $clienteTelefone,
            'cliente_cpf' => $clienteCpf,
            'status' => 'ocupada'
        ], 'id_mesa = ? AND tenant_id = ? AND filial_id = ?', [$comandaId, $tenantId, $filialId]);
        
        echo json_encode(['success' => true, 'comanda_id' => $comandaId, 'message' => "Comanda $comandaId vinculada a $clienteNome com sucesso!"]);
        exit;
    }
    
    throw new \Exception('Ação não reconhecida');

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
