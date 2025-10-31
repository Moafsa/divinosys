<?php

namespace System;

/**
 * Sistema de Logs
 * Gerencia logs de debug e erro para facilitar manutenção
 */
class Logger
{
    private static $instance = null;
    private $logDir;
    private $logLevel;

    const DEBUG = 1;
    const INFO = 2;
    const WARNING = 3;
    const ERROR = 4;

    private function __construct()
    {
        $this->logDir = __DIR__ . '/../logs';
        $this->logLevel = self::DEBUG; // Por padrão, log tudo
        
        // Criar diretório de logs se não existir
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Definir nível de log
     */
    public function setLogLevel($level)
    {
        $this->logLevel = $level;
    }

    /**
     * Log de debug
     */
    public function debug($message, $context = [])
    {
        $this->log(self::DEBUG, 'DEBUG', $message, $context);
    }

    /**
     * Log de informação
     */
    public function info($message, $context = [])
    {
        $this->log(self::INFO, 'INFO', $message, $context);
    }

    /**
     * Log de warning
     */
    public function warning($message, $context = [])
    {
        $this->log(self::WARNING, 'WARNING', $message, $context);
    }

    /**
     * Log de erro
     */
    public function error($message, $context = [])
    {
        $this->log(self::ERROR, 'ERROR', $message, $context);
    }

    /**
     * Log genérico
     */
    private function log($level, $levelName, $message, $context = [])
    {
        if ($level < $this->logLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$levelName}] {$message}{$contextStr}" . PHP_EOL;

        // Log geral
        $this->writeToFile('app.log', $logMessage);

        // Log específico por nível
        if ($level >= self::ERROR) {
            $this->writeToFile('error.log', $logMessage);
        }
    }

    /**
     * Log específico do SuperAdmin
     */
    public function superAdmin($action, $data = [])
    {
        $message = "SuperAdmin Action: {$action}";
        $context = array_merge($data, [
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'tenant_id' => $_SESSION['tenant_id'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $this->info($message, $context);
    }

    /**
     * Log de performance
     */
    public function performance($operation, $startTime, $endTime = null)
    {
        $endTime = $endTime ?? microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $this->info("Performance: {$operation}", [
            'duration_ms' => $duration,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    }

    /**
     * Log de cache
     */
    public function cache($operation, $key, $hit = null)
    {
        $context = ['key' => $key];
        if ($hit !== null) {
            $context['hit'] = $hit;
        }
        
        $this->debug("Cache {$operation}", $context);
    }

    /**
     * Escrever no arquivo de log
     */
    private function writeToFile($filename, $message)
    {
        $filepath = $this->logDir . '/' . $filename;
        file_put_contents($filepath, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obter logs recentes
     */
    public function getRecentLogs($lines = 100)
    {
        $logFile = $this->logDir . '/app.log';
        if (!file_exists($logFile)) {
            return [];
        }

        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        return array_slice($logs, -$lines);
    }

    /**
     * Limpar logs antigos
     */
    public function cleanOldLogs($days = 7)
    {
        $cutoff = time() - ($days * 24 * 60 * 60);
        $files = glob($this->logDir . '/*.log');
        $cleaned = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}


