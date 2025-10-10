<?php
/**
 * Model de Tenant
 * Gerencia os estabelecimentos (tenants) no sistema SaaS
 */

class Tenant {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar novo tenant
     */
    public function create($data) {
        $query = "INSERT INTO tenants 
                  (nome, subdomain, cnpj, telefone, email, endereco, cor_primaria, status, plano_id) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9) 
                  RETURNING id";
        
        $result = pg_query_params($this->conn, $query, [
            $data['nome'],
            $data['subdomain'],
            $data['cnpj'] ?? null,
            $data['telefone'] ?? null,
            $data['email'] ?? null,
            $data['endereco'] ?? null,
            $data['cor_primaria'] ?? '#007bff',
            $data['status'] ?? 'ativo',
            $data['plano_id']
        ]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
        
        return false;
    }
    
    /**
     * Buscar tenant por ID
     */
    public function getById($id) {
        $query = "SELECT t.*, p.nome as plano_nome, p.recursos as plano_recursos
                  FROM tenants t
                  LEFT JOIN planos p ON t.plano_id = p.id
                  WHERE t.id = $1";
        
        $result = pg_query_params($this->conn, $query, [$id]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Buscar tenant por subdomain
     */
    public function getBySubdomain($subdomain) {
        $query = "SELECT t.*, p.nome as plano_nome, p.recursos as plano_recursos
                  FROM tenants t
                  LEFT JOIN planos p ON t.plano_id = p.id
                  WHERE t.subdomain = $1";
        
        $result = pg_query_params($this->conn, $query, [$subdomain]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
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
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Atualizar tenant
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        $paramCount = 1;
        
        $allowed_fields = ['nome', 'subdomain', 'cnpj', 'telefone', 'email', 'endereco', 'logo_url', 'cor_primaria', 'status', 'plano_id'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = $$paramCount";
                $params[] = $data[$field];
                $paramCount++;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $query = "UPDATE tenants SET " . implode(', ', $fields) . " WHERE id = $$paramCount";
        
        return pg_query_params($this->conn, $query, $params);
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
        $query = "SELECT COUNT(*) as count FROM tenants WHERE subdomain = $1";
        $params = [$subdomain];
        
        if ($except_id) {
            $query .= " AND id != $2";
            $params[] = $except_id;
        }
        
        $result = pg_query_params($this->conn, $query, $params);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['count'] == 0;
        }
        
        return false;
    }
    
    /**
     * Estatísticas gerais
     */
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total_tenants,
                    COUNT(CASE WHEN status = 'ativo' THEN 1 END) as tenants_ativos,
                    COUNT(CASE WHEN status = 'inativo' THEN 1 END) as tenants_inativos,
                    COUNT(CASE WHEN status = 'suspenso' THEN 1 END) as tenants_suspensos
                  FROM tenants
                  WHERE subdomain != 'admin'";
        
        $result = pg_query($this->conn, $query);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Buscar filiais de um tenant
     */
    public function getFiliais($tenant_id) {
        $query = "SELECT * FROM filiais WHERE tenant_id = $1 ORDER BY created_at ASC";
        $result = pg_query_params($this->conn, $query, [$tenant_id]);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
}

