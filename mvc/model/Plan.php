<?php
/**
 * Model de Planos
 * Gerencia os planos de assinatura do sistema SaaS
 */

class Plan {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Listar todos os planos
     */
    public function getAll() {
        $query = "SELECT * FROM planos ORDER BY preco_mensal ASC";
        $result = pg_query($this->conn, $query);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Buscar plano por ID
     */
    public function getById($id) {
        $query = "SELECT * FROM planos WHERE id = $1";
        $result = pg_query_params($this->conn, $query, [$id]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Criar novo plano
     */
    public function create($data) {
        $query = "INSERT INTO planos 
                  (nome, max_mesas, max_usuarios, max_produtos, max_pedidos_mes, recursos, preco_mensal) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7) 
                  RETURNING id";
        
        $recursos_json = json_encode($data['recursos']);
        
        $result = pg_query_params($this->conn, $query, [
            $data['nome'],
            $data['max_mesas'],
            $data['max_usuarios'],
            $data['max_produtos'],
            $data['max_pedidos_mes'],
            $recursos_json,
            $data['preco_mensal']
        ]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
        
        return false;
    }
    
    /**
     * Atualizar plano
     */
    public function update($id, $data) {
        $query = "UPDATE planos SET 
                  nome = $1, 
                  max_mesas = $2, 
                  max_usuarios = $3, 
                  max_produtos = $4, 
                  max_pedidos_mes = $5, 
                  recursos = $6, 
                  preco_mensal = $7
                  WHERE id = $8";
        
        $recursos_json = is_array($data['recursos']) ? json_encode($data['recursos']) : $data['recursos'];
        
        return pg_query_params($this->conn, $query, [
            $data['nome'],
            $data['max_mesas'],
            $data['max_usuarios'],
            $data['max_produtos'],
            $data['max_pedidos_mes'],
            $recursos_json,
            $data['preco_mensal'],
            $id
        ]);
    }
    
    /**
     * Deletar plano
     */
    public function delete($id) {
        // Verificar se existem assinaturas ativas
        $check_query = "SELECT COUNT(*) as count FROM assinaturas 
                       WHERE plano_id = $1 AND status IN ('ativa', 'trial')";
        $check_result = pg_query_params($this->conn, $check_query, [$id]);
        
        if ($check_result) {
            $row = pg_fetch_assoc($check_result);
            if ($row['count'] > 0) {
                return ['success' => false, 'message' => 'Não é possível deletar plano com assinaturas ativas'];
            }
        }
        
        $query = "DELETE FROM planos WHERE id = $1";
        $result = pg_query_params($this->conn, $query, [$id]);
        
        return ['success' => (bool)$result, 'message' => $result ? 'Plano deletado com sucesso' : 'Erro ao deletar plano'];
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
            default:
                return false;
        }
        
        $result = pg_query_params($this->conn, $query, [$tenant_id]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['count'] < $limite_plano;
        }
        
        return false;
    }
}

