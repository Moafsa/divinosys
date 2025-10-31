<?php
/**
 * Model de Faturas
 * Gerencia faturas do sistema SaaS integrado com Asaas
 */

class Fatura {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar nova fatura
     */
    public function create($data) {
        $query = "INSERT INTO faturas 
                  (tenant_id, asaas_payment_id, asaas_subscription_id, valor, status, data_vencimento, descricao, webhook_data) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8) 
                  RETURNING id";
        
        $result = pg_query_params($this->conn, $query, [
            $data['tenant_id'],
            $data['asaas_payment_id'] ?? null,
            $data['asaas_subscription_id'] ?? null,
            $data['valor'],
            $data['status'] ?? 'pending',
            $data['data_vencimento'] ?? null,
            $data['descricao'] ?? null,
            $data['webhook_data'] ? json_encode($data['webhook_data']) : null
        ]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            return $row['id'];
        }
        
        return false;
    }
    
    /**
     * Buscar faturas por tenant
     */
    public function getByTenant($tenant_id, $limit = 50, $offset = 0) {
        $query = "SELECT f.*, t.nome as tenant_nome, p.nome as plano_nome 
                  FROM faturas f
                  LEFT JOIN tenants t ON f.tenant_id = t.id
                  LEFT JOIN assinaturas a ON f.tenant_id = a.tenant_id
                  LEFT JOIN planos p ON a.plano_id = p.id
                  WHERE f.tenant_id = $1 
                  ORDER BY f.created_at DESC 
                  LIMIT $2 OFFSET $3";
        
        $result = pg_query_params($this->conn, $query, [$tenant_id, $limit, $offset]);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Buscar fatura por ID do Asaas
     */
    public function getByAsaasPaymentId($asaas_payment_id) {
        $query = "SELECT * FROM faturas WHERE asaas_payment_id = $1";
        $result = pg_query_params($this->conn, $query, [$asaas_payment_id]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Atualizar status da fatura
     */
    public function updateStatus($asaas_payment_id, $status, $data_pagamento = null, $webhook_data = null) {
        $query = "UPDATE faturas 
                  SET status = $2, data_pagamento = $3, webhook_data = $4, updated_at = CURRENT_TIMESTAMP 
                  WHERE asaas_payment_id = $1";
        
        $result = pg_query_params($this->conn, $query, [
            $asaas_payment_id,
            $status,
            $data_pagamento,
            $webhook_data ? json_encode($webhook_data) : null
        ]);
        
        return $result !== false;
    }
    
    /**
     * Buscar estatísticas de faturas por tenant
     */
    public function getStatsByTenant($tenant_id) {
        $query = "SELECT 
                    COUNT(*) as total_faturas,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as pagas,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendentes,
                    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as vencidas,
                    SUM(CASE WHEN status = 'paid' THEN valor ELSE 0 END) as total_pago,
                    SUM(CASE WHEN status = 'pending' THEN valor ELSE 0 END) as total_pendente
                  FROM faturas 
                  WHERE tenant_id = $1";
        
        $result = pg_query_params($this->conn, $query, [$tenant_id]);
        
        if ($result && pg_num_rows($result) > 0) {
            return pg_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Buscar todas as faturas (para superadmin)
     */
    public function getAll($limit = 100, $offset = 0) {
        $query = "SELECT f.*, t.nome as tenant_nome, p.nome as plano_nome 
                  FROM faturas f
                  LEFT JOIN tenants t ON f.tenant_id = t.id
                  LEFT JOIN assinaturas a ON f.tenant_id = a.tenant_id
                  LEFT JOIN planos p ON a.plano_id = p.id
                  ORDER BY f.created_at DESC 
                  LIMIT $1 OFFSET $2";
        
        $result = pg_query_params($this->conn, $query, [$limit, $offset]);
        
        if ($result) {
            return pg_fetch_all($result) ?: [];
        }
        
        return [];
    }
    
    /**
     * Criar fatura a partir de webhook do Asaas
     */
    public function createFromWebhook($webhook_data) {
        $payment_data = $webhook_data['payment'] ?? [];
        
        // Buscar tenant pelo customer_id do Asaas
        $tenant_query = "SELECT id FROM tenants WHERE asaas_customer_id = $1";
        $tenant_result = pg_query_params($this->conn, $tenant_query, [$payment_data['customer']]);
        
        if (!$tenant_result || pg_num_rows($tenant_result) == 0) {
            return false;
        }
        
        $tenant = pg_fetch_assoc($tenant_result);
        
        $data = [
            'tenant_id' => $tenant['id'],
            'asaas_payment_id' => $payment_data['id'],
            'asaas_subscription_id' => $payment_data['subscription'] ?? null,
            'valor' => $payment_data['value'],
            'status' => $this->mapAsaasStatus($payment_data['status']),
            'data_vencimento' => $payment_data['dueDate'] ?? null,
            'descricao' => $payment_data['description'] ?? 'Assinatura Divino Lanches',
            'webhook_data' => $webhook_data
        ];
        
        return $this->create($data);
    }
    
    /**
     * Mapear status do Asaas para status interno
     */
    private function mapAsaasStatus($asaas_status) {
        $status_map = [
            'PENDING' => 'pending',
            'CONFIRMED' => 'paid',
            'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE' => 'overdue',
            'REFUNDED' => 'refunded',
            'CANCELLED' => 'cancelled'
        ];
        
        return $status_map[$asaas_status] ?? 'pending';
    }
}
