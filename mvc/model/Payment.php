<?php
/**
 * Model de Pagamentos
 * Gerencia os pagamentos das assinaturas
 */

use System\Database;

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar novo pagamento
     */
    public function create($data) {
        $query = "INSERT INTO pagamentos 
                  (assinatura_id, tenant_id, valor, status, metodo_pagamento, data_vencimento) 
                  VALUES ($1, $2, $3, $4, $5, $6) 
                  RETURNING id";
        
        $result = $this->db->execute($query, [
            $data['assinatura_id'],
            $data['tenant_id'],
            $data['valor'],
            $data['status'] ?? 'pendente',
            $data['metodo_pagamento'] ?? null,
            $data['data_vencimento']
        ]);
        
        if ($result) {
            return $result;
        }
        
        return false;
    }
    
    /**
     * Buscar pagamento por ID
     */
    public function getById($id) {
        $query = "SELECT p.*, t.nome as tenant_nome, a.plano_id
                  FROM pagamentos p
                  INNER JOIN tenants t ON p.tenant_id = t.id
                  INNER JOIN assinaturas a ON p.assinatura_id = a.id
                  WHERE p.id = $1";
        
        return $this->db->fetch($query, [$id]);
    }
    
    /**
     * Listar pagamentos de uma assinatura
     */
    public function getBySubscription($assinatura_id) {
        $query = "SELECT * FROM pagamentos 
                  WHERE assinatura_id = $1 
                  ORDER BY created_at DESC";
        
        return $this->db->fetchAll($query, [$assinatura_id]);
    }
    
    /**
     * Listar pagamentos de um tenant
     */
    public function getByTenant($tenant_id, $filters = []) {
        $query = "SELECT p.*, a.plano_id, pl.nome as plano_nome
                  FROM pagamentos p
                  INNER JOIN assinaturas a ON p.assinatura_id = a.id
                  INNER JOIN planos pl ON a.plano_id = pl.id
                  WHERE p.tenant_id = $1";
        
        $params = [$tenant_id];
        $paramCount = 2;
        
        if (!empty($filters['status'])) {
            $query .= " AND p.status = $$paramCount";
            $params[] = $filters['status'];
            $paramCount++;
        }
        
        $query .= " ORDER BY p.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . intval($filters['limit']);
        }
        
        $result = pg_query_params($this->db->getConnection(), $query, $params);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Listar todos os pagamentos (superadmin)
     */
    public function getAll($filters = []) {
        $query = "SELECT p.*, 
                  t.nome as tenant_nome, 
                  t.subdomain,
                  pl.nome as plano_nome
                  FROM pagamentos p
                  LEFT JOIN tenants t ON p.tenant_id = t.id
                  LEFT JOIN assinaturas a ON p.assinatura_id = a.id
                  LEFT JOIN planos pl ON a.plano_id = pl.id
                  WHERE 1=1";
        
        $params = [];
        $paramCount = 1;
        
        if (!empty($filters['status'])) {
            $query .= " AND p.status = $$paramCount";
            $params[] = $filters['status'];
            $paramCount++;
        }
        
        if (!empty($filters['tenant_id'])) {
            $query .= " AND p.tenant_id = $$paramCount";
            $params[] = $filters['tenant_id'];
            $paramCount++;
        }
        
        $query .= " ORDER BY p.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . intval($filters['limit']);
        }
        
        if (!empty($filters['offset'])) {
            $query .= " OFFSET " . intval($filters['offset']);
        }
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Atualizar status do pagamento
     */
    public function updateStatus($id, $status, $dados_gateway = []) {
        $query = "UPDATE pagamentos SET 
                  status = $1, 
                  updated_at = CURRENT_TIMESTAMP";
        
        $params = [$status];
        $paramCount = 2;
        
        if ($status == 'pago') {
            $query .= ", data_pagamento = CURRENT_TIMESTAMP";
        }
        
        if (!empty($dados_gateway['gateway_payment_id'])) {
            $query .= ", gateway_payment_id = $$paramCount";
            $params[] = $dados_gateway['gateway_payment_id'];
            $paramCount++;
        }
        
        if (!empty($dados_gateway['gateway_response'])) {
            $query .= ", gateway_response = $$paramCount";
            $params[] = $dados_gateway['gateway_response'];
            $paramCount++;
        }
        
        $query .= " WHERE id = $$paramCount";
        $params[] = $id;
        
        return pg_query_params($this->db->getConnection(), $query, $params);
    }
    
    /**
     * Marcar pagamento como pago
     */
    public function markAsPaid($id, $metodo_pagamento = null, $gateway_data = []) {
        $updateData = [
            'status' => 'pago',
            'data_pagamento' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($metodo_pagamento) {
            $updateData['metodo_pagamento'] = $metodo_pagamento;
        }
        
        if (!empty($gateway_data['gateway_payment_id'])) {
            $updateData['gateway_payment_id'] = $gateway_data['gateway_payment_id'];
        }
        
        if (!empty($gateway_data['gateway_response'])) {
            $updateData['gateway_response'] = json_encode($gateway_data['gateway_response']);
        }
        
        return $this->db->update('pagamentos', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Incrementar tentativas de cobrança
     */
    public function incrementTentativas($id) {
        $query = "UPDATE pagamentos SET 
                  tentativas = tentativas + 1,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = $1";
        
        return pg_query_params($this->db->getConnection(), $query, [$id]);
    }
    
    /**
     * Buscar pagamentos vencidos
     */
    public function getOverdue() {
        $query = "SELECT p.*, t.nome as tenant_nome, t.email as tenant_email,
                  a.plano_id, pl.nome as plano_nome
                  FROM pagamentos p
                  INNER JOIN tenants t ON p.tenant_id = t.id
                  INNER JOIN assinaturas a ON p.assinatura_id = a.id
                  INNER JOIN planos pl ON a.plano_id = pl.id
                  WHERE p.status = 'pendente' 
                  AND p.data_vencimento < CURRENT_DATE
                  ORDER BY p.data_vencimento ASC";
        
        $result = pg_query($this->db->getConnection(), $query);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Estatísticas de pagamentos
     */
    public function getStats($periodo = 'mes') {
        $date_filter = match($periodo) {
            'hoje' => "DATE(p.created_at) = CURRENT_DATE",
            'semana' => "p.created_at >= CURRENT_DATE - INTERVAL '7 days'",
            'mes' => "EXTRACT(MONTH FROM p.created_at) = EXTRACT(MONTH FROM CURRENT_DATE) 
                     AND EXTRACT(YEAR FROM p.created_at) = EXTRACT(YEAR FROM CURRENT_DATE)",
            'ano' => "EXTRACT(YEAR FROM p.created_at) = EXTRACT(YEAR FROM CURRENT_DATE)",
            default => "1=1"
        };
        
        return $this->db->fetch("SELECT 
                    COUNT(*) as total_pagamentos,
                    COUNT(CASE WHEN status = 'pago' THEN 1 END) as pagamentos_pagos,
                    COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pagamentos_pendentes,
                    COUNT(CASE WHEN status = 'falhou' THEN 1 END) as pagamentos_falhados,
                    COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) as receita_total,
                    COALESCE(SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END), 0) as receita_pendente
                  FROM pagamentos p
                  WHERE $date_filter");
    }
}

