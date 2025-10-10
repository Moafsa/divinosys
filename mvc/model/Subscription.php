<?php
/**
 * Model de Assinatura
 * Gerencia assinaturas de tenants no sistema SaaS
 */

class Subscription {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar nova assinatura
     */
    public function create($data) {
        $query = "INSERT INTO assinaturas 
                  (tenant_id, plano_id, status, data_inicio, data_proxima_cobranca, valor, periodicidade, trial_ate) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8) 
                  RETURNING id";
        
        $result = pg_query_params($this->conn, $query, [
            $data['tenant_id'],
            $data['plano_id'],
            $data['status'] ?? 'trial',
            $data['data_inicio'] ?? date('Y-m-d'),
            $data['data_proxima_cobranca'] ?? date('Y-m-d', strtotime('+30 days')),
            $data['valor'],
            $data['periodicidade'] ?? 'mensal',
            $data['trial_ate'] ?? date('Y-m-d', strtotime('+14 days'))
        ]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
        
        return false;
    }
    
    /**
     * Buscar assinatura por tenant
     */
    public function getByTenant($tenant_id) {
        $query = "SELECT a.*, p.nome as plano_nome, p.recursos 
                  FROM assinaturas a
                  INNER JOIN planos p ON a.plano_id = p.id
                  WHERE a.tenant_id = $1 AND a.status IN ('ativa', 'trial')
                  ORDER BY a.created_at DESC LIMIT 1";
        
        $result = pg_query_params($this->conn, $query, [$tenant_id]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Verificar se assinatura está ativa
     */
    public function isActive($tenant_id) {
        $subscription = $this->getByTenant($tenant_id);
        
        if (!$subscription) {
            return false;
        }
        
        // Verificar trial expirado
        if ($subscription['status'] == 'trial' && $subscription['trial_ate']) {
            if (strtotime($subscription['trial_ate']) < time()) {
                $this->updateStatus($subscription['id'], 'inadimplente');
                return false;
            }
        }
        
        return in_array($subscription['status'], ['ativa', 'trial']);
    }
    
    /**
     * Atualizar status da assinatura
     */
    public function updateStatus($id, $status, $motivo = null) {
        $query = "UPDATE assinaturas SET status = $1, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];
        $paramCount = 2;
        
        if ($status == 'cancelada') {
            $query .= ", cancelada_em = CURRENT_TIMESTAMP";
            if ($motivo) {
                $query .= ", motivo_cancelamento = $$paramCount";
                $params[] = $motivo;
                $paramCount++;
            }
        }
        
        $query .= " WHERE id = $$paramCount";
        $params[] = $id;
        
        return pg_query_params($this->conn, $query, $params);
    }
    
    /**
     * Listar todas as assinaturas (para superadmin)
     */
    public function getAll($filters = []) {
        $query = "SELECT a.*, t.nome as tenant_nome, t.subdomain, p.nome as plano_nome
                  FROM assinaturas a
                  INNER JOIN tenants t ON a.tenant_id = t.id
                  INNER JOIN planos p ON a.plano_id = p.id
                  WHERE 1=1";
        
        $params = [];
        $paramCount = 1;
        
        if (!empty($filters['status'])) {
            $query .= " AND a.status = $$paramCount";
            $params[] = $filters['status'];
            $paramCount++;
        }
        
        if (!empty($filters['plano_id'])) {
            $query .= " AND a.plano_id = $$paramCount";
            $params[] = $filters['plano_id'];
            $paramCount++;
        }
        
        $query .= " ORDER BY a.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . intval($filters['limit']);
        }
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Renovar assinatura
     */
    public function renew($subscription_id) {
        $query = "UPDATE assinaturas 
                  SET data_proxima_cobranca = data_proxima_cobranca + INTERVAL '1 month',
                      status = 'ativa',
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = $1";
        
        return pg_query_params($this->conn, $query, [$subscription_id]);
    }
    
    /**
     * Estatísticas de assinaturas
     */
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'ativa' THEN 1 END) as ativas,
                    COUNT(CASE WHEN status = 'trial' THEN 1 END) as trial,
                    COUNT(CASE WHEN status = 'inadimplente' THEN 1 END) as inadimplentes,
                    COUNT(CASE WHEN status = 'cancelada' THEN 1 END) as canceladas,
                    SUM(valor) as receita_mensal
                  FROM assinaturas
                  WHERE status IN ('ativa', 'trial')";
        
        $result = pg_query($this->conn, $query);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
}

