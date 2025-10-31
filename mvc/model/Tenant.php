<?php
/**
 * Model de Tenant
 * Gerencia os estabelecimentos (tenants) no sistema SaaS
 */

use System\Database;

class Tenant {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar novo tenant
     */
    public function create($data) {
        return $this->db->insert('tenants', [
            'nome' => $data['nome'],
            'subdomain' => $data['subdomain'],
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'cor_primaria' => $data['cor_primaria'] ?? '#007bff',
            'status' => $data['status'] ?? 'ativo',
            'plano_id' => $data['plano_id']
        ]);
    }
    
    /**
     * Buscar tenant por ID
     */
    public function getById($id) {
        $query = "SELECT t.*, p.nome as plano_nome, p.recursos as plano_recursos
                  FROM tenants t
                  LEFT JOIN planos p ON t.plano_id = p.id
                  WHERE t.id = ?";
        
        return $this->db->fetch($query, [$id]);
    }
    
    /**
     * Buscar tenant por subdomain
     */
    public function getBySubdomain($subdomain) {
        $query = "SELECT t.*, p.nome as plano_nome, p.recursos as plano_recursos
                  FROM tenants t
                  LEFT JOIN planos p ON t.plano_id = p.id
                  WHERE t.subdomain = ?";
        
        return $this->db->fetch($query, [$subdomain]);
    }
    
    /**
     * Listar todos os tenants
     */
    public function getAll($filters = []) {
        $query = "SELECT t.*, p.nome as plano_nome,
                  (SELECT COUNT(*) FROM filiais WHERE tenant_id = t.id) as total_filiais,
                  (SELECT COUNT(*) FROM usuarios WHERE tenant_id = t.id) as total_usuarios
                  FROM tenants t
                  LEFT JOIN planos p ON t.plano_id = p.id
                  WHERE t.subdomain != 'admin'";
        
        $params = [];
        $paramCount = 1;
        
        if (!empty($filters['status'])) {
            $query .= " AND t.status = $$paramCount";
            $params[] = $filters['status'];
            $paramCount++;
        }
        
        if (!empty($filters['plano_id'])) {
            $query .= " AND t.plano_id = $$paramCount";
            $params[] = $filters['plano_id'];
            $paramCount++;
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (t.nome ILIKE $$paramCount OR t.subdomain ILIKE $$paramCount OR t.email ILIKE $$paramCount)";
            $params[] = "%{$filters['search']}%";
            $paramCount++;
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . intval($filters['limit']);
        }
        
        if (!empty($filters['offset'])) {
            $query .= " OFFSET " . intval($filters['offset']);
        }
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Atualizar tenant
     */
    public function update($id, $data) {
        $allowed_fields = ['nome', 'subdomain', 'cnpj', 'telefone', 'email', 'endereco', 'logo_url', 'cor_primaria', 'status', 'plano_id'];
        
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
        
        return $this->db->update('tenants', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Deletar tenant (soft delete - apenas muda status)
     */
    public function delete($id) {
        return $this->update($id, ['status' => 'inativo']);
    }
    
    /**
     * Verificar se subdomain está disponível
     */
    public function isSubdomainAvailable($subdomain, $except_id = null) {
        $query = "SELECT COUNT(*) as count FROM tenants WHERE subdomain = ?";
        $params = [$subdomain];
        
        if ($except_id) {
            $query .= " AND id != ?";
            $params[] = $except_id;
        }
        
        $result = $this->db->fetch($query, $params);
        
        return $result && $result['count'] == 0;
    }
    
    /**
     * Estatísticas gerais
     */
    public function getStats() {
        return $this->db->fetch("SELECT 
                    COUNT(*) as total_tenants,
                    COUNT(CASE WHEN status = 'ativo' THEN 1 END) as tenants_ativos,
                    COUNT(CASE WHEN status = 'inativo' THEN 1 END) as tenants_inativos,
                    COUNT(CASE WHEN status = 'suspenso' THEN 1 END) as tenants_suspensos
                  FROM tenants
                  WHERE subdomain != 'admin'");
    }
    
    /**
     * Buscar filiais de um tenant
     */
    public function getFiliais($tenant_id) {
        return $this->db->fetchAll(
            "SELECT * FROM filiais WHERE tenant_id = ? ORDER BY created_at ASC",
            [$tenant_id]
        );
    }
}

