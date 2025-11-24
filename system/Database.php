<?php

namespace System;

use PDO;
use PDOException;
use System\Config;

class Database
{
    private static $instance = null;
    private $connection = null;
    private $config;

    private function __construct()
    {
        $this->config = \System\Config::getInstance();
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        try {
            $dbConfig = $this->config->getDatabaseConfig();
            
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['name']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 120,
                PDO::ATTR_PERSISTENT => false,
            ];

            $this->connection = new PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['password'],
                $options
            );

        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new \Exception('Database connection failed');
        }
    }

    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $conn = $this->getConnection();
            
            // Check if connection is in a failed transaction state
            if ($conn->inTransaction()) {
                try {
                    // Try a simple query to check transaction state
                    $conn->query('SELECT 1');
                } catch (PDOException $e) {
                    // Transaction is in error state, rollback
                    error_log('Transaction in error state, rolling back...');
                    $conn->rollBack();
                }
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database query failed: ' . $e->getMessage());
            error_log('SQL: ' . $sql);
            error_log('Params: ' . json_encode($params));
            
            // Rollback if in transaction
            $conn = $this->getConnection();
            if ($conn->inTransaction()) {
                try {
                    $conn->rollBack();
                } catch (PDOException $rollbackError) {
                    error_log('Rollback failed: ' . $rollbackError->getMessage());
                }
            }
            
            throw new \Exception('Database query failed: ' . $e->getMessage());
        }
    }

    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Sanitize data before database operations
     * Converts empty strings to NULL for date/datetime fields
     */
    private function sanitizeData($data)
    {
        foreach ($data as $key => $value) {
            // Convert empty strings to NULL for date/datetime/timestamp fields
            if (is_string($value) && empty($value) && 
                (stripos($key, 'data_') === 0 || 
                 stripos($key, '_date') !== false || 
                 stripos($key, '_at') !== false ||
                 in_array($key, ['data_nascimento', 'data_admissao', 'data_demissao', 
                                'data_vencimento', 'data_inicio', 'data_fim', 'dueDate']))) {
                $data[$key] = null;
            }
        }
        return $data;
    }

    public function insert($table, $data)
    {
        // Sanitize data before insert
        $data = $this->sanitizeData($data);
        
        // Handle boolean values for PostgreSQL
        $columns = [];
        $placeholders = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $columns[] = $key;
            if (is_bool($value)) {
                // Convert boolean to PostgreSQL boolean literal
                $placeholders[] = ($value ? 'true' : 'false');
            } else {
                $placeholders[] = ':' . $key;
                $params[$key] = $value;
            }
        }
        
        $columnsStr = implode(',', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        
        $sql = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholdersStr})";
        
        $stmt = $this->query($sql, $params);
        return $this->getConnection()->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        // Sanitize data before update
        $data = $this->sanitizeData($data);
        
        $set = [];
        $params = [];
        $paramIndex = 1;
        
        foreach ($data as $key => $value) {
            // Convert boolean values directly in SQL for PostgreSQL
            if (is_bool($value)) {
                $set[] = "{$key} = " . ($value ? 'true' : 'false');
            } else {
                $set[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($params, $whereParams);
        return $this->query($sql, $params);
    }

    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    public function beginTransaction()
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit()
    {
        return $this->getConnection()->commit();
    }

    public function rollback()
    {
        return $this->getConnection()->rollback();
    }

    public function lastInsertId()
    {
        return $this->getConnection()->lastInsertId();
    }

    public function rowCount($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function exists($table, $where, $params = [])
    {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $result = $this->fetch($sql, $params);
        return $result !== false;
    }

    public function count($table, $where = '1=1', $params = [])
    {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return (int) $result['count'];
    }

    public function paginate($sql, $params = [], $page = 1, $perPage = 15)
    {
        $offset = ($page - 1) * $perPage;
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_query";
        
        $total = $this->fetch($countSql, $params)['total'];
        $data = $this->fetchAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);
        
        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }
}
