<?php
/**
 * Model de Planos
 * Gerencia os planos de assinatura do sistema SaaS
 */

use System\Database;

class Plan {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Listar todos os planos
     */
    public function getAll() {
        $query = "SELECT * FROM planos ORDER BY preco_mensal ASC";
        return $this->db->fetchAll($query);
    }
    
    /**
     * Buscar plano por ID
     */
    public function getById($id) {
        $query = "SELECT * FROM planos WHERE id = " . intval($id);
        return $this->db->fetch($query);
    }
    
    /**
     * Criar novo plano
     */
    public function create($data) {
        try {
            $recursos_json = is_array($data['recursos']) ? json_encode($data['recursos']) : $data['recursos'];
            
            // Verificar se coluna trial_days existe (para compatibilidade com bancos antigos)
            $columnExists = $this->db->fetch("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'planos' AND column_name = 'trial_days'
            ");
            
            $insertData = [
                'nome' => $data['nome'],
                'max_mesas' => intval($data['max_mesas'] ?? 10),
                'max_usuarios' => intval($data['max_usuarios'] ?? 3),
                'max_produtos' => intval($data['max_produtos'] ?? 100),
                'max_pedidos_mes' => intval($data['max_pedidos_mes'] ?? 1000),
                'max_filiais' => intval($data['max_filiais'] ?? 1),
                'recursos' => $recursos_json,
                'preco_mensal' => floatval($data['preco_mensal']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Só adiciona trial_days se a coluna existir
            if ($columnExists) {
                $insertData['trial_days'] = intval($data['trial_days'] ?? 14);
            }
            
            $plan_id = $this->db->insert('planos', $insertData);
            
            if ($plan_id) {
                error_log("Plan::create - Plan created successfully: ID $plan_id" . ($columnExists ? " (with trial_days)" : " (without trial_days - column not exists)"));
                return $plan_id;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Plan::create - Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar plano
     */
    public function update($id, $data) {
        try {
            $recursos_json = is_array($data['recursos']) ? json_encode($data['recursos']) : $data['recursos'];
            
            // Verificar se coluna trial_days existe (para compatibilidade com bancos antigos)
            $columnExists = $this->db->fetch("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'planos' AND column_name = 'trial_days'
            ");
            
            $trialDaysClause = $columnExists ? "trial_days = " . intval($data['trial_days'] ?? 14) . "," : "";
            
            $query = "UPDATE planos SET 
                      nome = '" . addslashes($data['nome']) . "', 
                      max_mesas = " . intval($data['max_mesas']) . ", 
                      max_usuarios = " . intval($data['max_usuarios']) . ", 
                      max_produtos = " . intval($data['max_produtos']) . ", 
                      max_pedidos_mes = " . intval($data['max_pedidos_mes']) . ", 
                      max_filiais = " . intval($data['max_filiais'] ?? 1) . ",
                      {$trialDaysClause}
                      recursos = '" . $recursos_json . "', 
                      preco_mensal = " . floatval($data['preco_mensal']) . ",
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = " . intval($id);
            
            $result = $this->db->query($query);
            
            error_log("Plan::update - Plan $id updated successfully" . ($columnExists ? " (with trial_days)" : " (without trial_days - column not exists)"));
            return $result !== false;
        } catch (\Exception $e) {
            error_log("Plan::update - Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletar plano
     */
    public function delete($id) {
        try {
            // Verificar se existem assinaturas ativas
            $check_query = "SELECT COUNT(*) as count FROM assinaturas 
                           WHERE plano_id = ? AND status IN ('ativa', 'trial')";
            $check_result = $this->db->fetch($check_query, [$id]);
            
            if ($check_result && $check_result['count'] > 0) {
                error_log("Plan::delete - Cannot delete plan $id: {$check_result['count']} active subscriptions");
                return ['success' => false, 'error' => 'Não é possível deletar plano com assinaturas ativas'];
            }
            
            // Usar método query() diretamente ao invés de execute()
            $query = "DELETE FROM planos WHERE id = ?";
            $stmt = $this->db->query($query, [$id]);
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected > 0) {
                error_log("Plan::delete - Plan $id deleted successfully");
                return ['success' => true, 'message' => 'Plano deletado com sucesso'];
            } else {
                error_log("Plan::delete - No rows affected, plan $id may not exist");
                return ['success' => false, 'error' => 'Plano não encontrado'];
            }
        } catch (\Exception $e) {
            error_log("Plan::delete - Exception: " . $e->getMessage());
            error_log("Plan::delete - Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Erro ao deletar plano: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verificar limites do plano
     */
    public function checkLimits($tenant_id, $plano_id, $tipo_limite) {
        $plano = $this->getById($plano_id);
        
        if (!$plano) {
            return false;
        }
        
        // Se limite é -1, é ilimitado
        $limite_plano = $plano["max_$tipo_limite"];
        if ($limite_plano == -1) {
            return true;
        }
        
        // Verificar uso atual
        switch ($tipo_limite) {
            case 'mesas':
                $query = "SELECT COUNT(*) as count FROM mesas WHERE tenant_id = $1";
                break;
            case 'usuarios':
                $query = "SELECT COUNT(*) as count FROM usuarios WHERE tenant_id = $1";
                break;
            case 'produtos':
                $query = "SELECT COUNT(*) as count FROM produtos WHERE tenant_id = $1";
                break;
            case 'pedidos_mes':
                $query = "SELECT COUNT(*) as count FROM pedidos 
                         WHERE tenant_id = $1 
                         AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
                         AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)";
                break;
            case 'filiais':
                $query = "SELECT COUNT(*) as count FROM tenants WHERE tenant_pai_id = $1";
                break;
            default:
                return false;
        }
        
        $result = $this->db->fetch($query, [$tenant_id]);
        
        if ($result) {
            return $result['count'] < $limite_plano;
        }
        
        return false;
    }
}

