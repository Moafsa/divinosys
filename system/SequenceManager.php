<?php

namespace System;

/**
 * Sequence Manager - Solução definitiva para problemas de sequências
 * 
 * Este sistema garante que as sequências sempre estejam sincronizadas
 * e previne que sejam resetadas incorretamente.
 */
class SequenceManager
{
    private static $instance = null;
    private $db;
    
    private function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Garante que uma sequência esteja sincronizada com a tabela
     * Este método é chamado automaticamente antes de inserções
     */
    public function ensureSequenceSync($tableName, $sequenceName = null, $idColumn = 'id')
    {
        if ($sequenceName === null) {
            $sequenceName = $tableName . '_id_seq';
        }
        
        try {
            // Verificar se a sequência existe
            $stmt = $this->db->query("
                SELECT EXISTS(
                    SELECT 1 FROM pg_sequences 
                    WHERE sequencename = ? AND schemaname = 'public'
                ) as exists
            ", [$sequenceName]);
            
            if (!$stmt->fetchColumn()) {
                error_log("Sequence $sequenceName does not exist for table $tableName");
                return false;
            }
            
            // Obter valor atual da sequência
            $stmt = $this->db->query("SELECT last_value FROM $sequenceName");
            $currentValue = $stmt->fetchColumn();
            
            // Obter MAX ID da tabela
            $stmt = $this->db->query("SELECT COALESCE(MAX($idColumn), 0) FROM $tableName");
            $maxId = $stmt->fetchColumn();
            
            // Se a sequência está atrás do MAX ID, corrigir
            if ($currentValue <= $maxId) {
                $newValue = $maxId + 1;
                $this->db->query("SELECT setval(?, ?, false)", [$sequenceName, $newValue]);
                error_log("Sequence $sequenceName synchronized: $currentValue → $newValue (MAX ID: $maxId)");
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Error syncing sequence $sequenceName: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincroniza todas as sequências principais
     */
    public function syncAllSequences()
    {
        $sequences = [
            'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
            'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
            'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
            'mesas' => ['seq' => 'mesas_id_seq', 'id' => 'id'],
            'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
            'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
            'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
            'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
        ];
        
        $results = [];
        foreach ($sequences as $table => $config) {
            $results[$table] = $this->ensureSequenceSync($table, $config['seq'], $config['id']);
        }
        
        return $results;
    }
    
    /**
     * Verifica o status de todas as sequências
     */
    public function getSequenceStatus()
    {
        $sequences = [
            'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
            'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
            'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
            'mesas' => ['seq' => 'mesas_id_seq', 'id' => 'id'],
            'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
            'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
            'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
            'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
        ];
        
        $status = [];
        foreach ($sequences as $table => $config) {
            try {
                $stmt = $this->db->query("SELECT last_value FROM {$config['seq']}");
                $currentValue = $stmt->fetchColumn();
                
                $stmt = $this->db->query("SELECT COALESCE(MAX({$config['id']}), 0) FROM $table");
                $maxId = $stmt->fetchColumn();
                
                $status[$table] = [
                    'sequence' => $config['seq'],
                    'current_value' => $currentValue,
                    'max_id' => $maxId,
                    'status' => $currentValue > $maxId ? 'OK' : 'NEEDS_FIX',
                    'next_id' => $currentValue > $maxId ? $currentValue + 1 : $maxId + 1
                ];
            } catch (\Exception $e) {
                $status[$table] = [
                    'sequence' => $config['seq'],
                    'error' => $e->getMessage(),
                    'status' => 'ERROR'
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Método para ser chamado antes de inserções críticas
     */
    public function beforeInsert($tableName)
    {
        return $this->ensureSequenceSync($tableName);
    }
}
