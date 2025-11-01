<?php
/**
 * Model de Assinatura
 * Gerencia assinaturas de tenants no sistema SaaS
 */

use System\Database;

class Subscription {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar nova assinatura
     */
    public function create($data) {
        $insertData = [
            'tenant_id' => $data['tenant_id'],
            'plano_id' => $data['plano_id'],
            'status' => $data['status'] ?? 'trial',
            'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
            'data_proxima_cobranca' => $data['data_proxima_cobranca'] ?? date('Y-m-d', strtotime('+30 days')),
            'valor' => $data['valor'],
            'periodicidade' => $data['periodicidade'] ?? 'mensal',
            'trial_ate' => $data['trial_ate'] ?? date('Y-m-d', strtotime('+14 days'))
        ];
        
        return $this->db->insert('assinaturas', $insertData);
    }
    
    /**
     * Buscar assinatura por ID
     */
    public function getById($id) {
        $query = "SELECT a.*, t.nome as tenant_nome, t.subdomain,
                  p.nome as plano_nome, p.recursos
                  FROM assinaturas a
                  INNER JOIN tenants t ON a.tenant_id = t.id
                  INNER JOIN planos p ON a.plano_id = p.id
                  WHERE a.id = " . intval($id);
        
        return $this->db->fetch($query);
    }
    
    /**
     * Atualizar assinatura
     */
    public function update($id, $data) {
        // Apenas incluir campos que foram fornecidos
        $allowed_fields = ['tenant_id', 'plano_id', 'status', 'data_inicio', 'data_proxima_cobranca', 'valor', 'periodicidade', 'trial_ate', 'asaas_subscription_id'];
        
        $updateData = [];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('assinaturas', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Deletar assinatura
     */
    public function delete($id) {
        // Verificar se há pagamentos associados
        $checkQuery = "SELECT COUNT(*) as count FROM pagamentos_assinaturas WHERE assinatura_id = " . intval($id);
        $check = $this->db->fetch($checkQuery);
        
        if ($check['count'] > 0) {
            return false; // Não pode deletar se há pagamentos
        }
        
        $query = "DELETE FROM assinaturas WHERE id = " . intval($id);
        $result = $this->db->query($query);
        
        return $result !== false;
    }

    /**
     * Buscar assinatura por tenant
     */
    public function getByTenant($tenant_id) {
        $query = "SELECT a.*, p.nome as plano_nome, p.recursos 
                  FROM assinaturas a
                  INNER JOIN planos p ON a.plano_id = p.id
                  WHERE a.tenant_id = ? AND a.status IN ('ativa', 'trial')
                  ORDER BY a.created_at DESC LIMIT 1";
        
        return $this->db->fetch($query, [$tenant_id]);
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
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status == 'cancelada') {
            $updateData['cancelada_em'] = date('Y-m-d H:i:s');
            if ($motivo) {
                $updateData['motivo_cancelamento'] = $motivo;
            }
        }
        
        return $this->db->update('assinaturas', $updateData, 'id = ?', [$id]);
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
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Renovar assinatura
     */
    public function renew($subscription_id) {
        $updateData = [
            'data_proxima_cobranca' => date('Y-m-d', strtotime('+1 month')),
            'status' => 'ativa',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('assinaturas', $updateData, 'id = ?', [$subscription_id]);
    }
    
    /**
     * Estatísticas de assinaturas
     */
    public function getStats() {
        return $this->db->fetch("SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'ativa' THEN 1 END) as ativas,
                    COUNT(CASE WHEN status = 'trial' THEN 1 END) as trial,
                    COUNT(CASE WHEN status = 'inadimplente' THEN 1 END) as inadimplentes,
                    COUNT(CASE WHEN status = 'cancelada' THEN 1 END) as canceladas,
                    SUM(valor) as receita_mensal
                  FROM assinaturas
                  WHERE status IN ('ativa', 'trial')");
    }
}

