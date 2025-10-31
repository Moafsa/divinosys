<?php
header('Content-Type: application/json');

// Autoloader
require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'salvar_aparencia':
            $corPrimaria = $_POST['cor_primaria'] ?? '';
            $nomeEstabelecimento = $_POST['nome_estabelecimento'] ?? '';
            
            if (empty($corPrimaria) || empty($nomeEstabelecimento)) {
                throw new \Exception('Todos os campos são obrigatórios');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            
            // Atualizar tenant
            $db->update(
                'tenants',
                [
                    'cor_primaria' => $corPrimaria,
                    'nome' => $nomeEstabelecimento
                ],
                'id = ?',
                [$tenantId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Configurações de aparência salvas com sucesso!']);
            break;
            
        case 'salvar_mesas':
            $numeroMesas = (int) ($_POST['numero_mesas'] ?? 0);
            $capacidadeMesa = (int) ($_POST['capacidade_mesa'] ?? 0);
            
            if ($numeroMesas <= 0 || $capacidadeMesa <= 0) {
                throw new \Exception('Valores inválidos para mesas');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId();
            $filialId = $session->getFilialId();
            
            // Verificar quantas mesas existem
            $mesasExistentes = $db->count('mesas', 'tenant_id = ? AND filial_id = ?', [$tenantId, $filialId]);
            
            if ($numeroMesas > $mesasExistentes) {
                // Adicionar novas mesas
                for ($i = $mesasExistentes + 1; $i <= $numeroMesas; $i++) {
                    $db->insert('mesas', [
                        'id_mesa' => (string) $i,
                        'nome' => '',
                        'status' => '1',
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId
                    ]);
                }
            } elseif ($numeroMesas < $mesasExistentes) {
                // Remover mesas extras
                $db->delete(
                    'mesas',
                    'tenant_id = ? AND filial_id = ? AND id_mesa::integer > ?',
                    [$tenantId, $filialId, $numeroMesas]
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Configurações de mesas salvas com sucesso!']);
            break;
            
        default:
            throw new \Exception('Ação não encontrada');
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
