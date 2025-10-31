<?php

namespace System;

/**
 * Sistema de Cache Simples
 * Gerencia cache em arquivos para melhorar performance
 */
class Cache
{
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hora

    private function __construct()
    {
        $this->cacheDir = __DIR__ . '/../cache';
        
        // Criar diretório de cache se não existir
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
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
     * Armazenar dados no cache
     */
    public function set($key, $data, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTTL;
        $cacheFile = $this->getCacheFile($key);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($cacheFile, serialize($cacheData)) !== false;
    }

    /**
     * Recuperar dados do cache
     */
    public function get($key)
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }
        
        $cacheData = unserialize($content);
        if ($cacheData === false) {
            return null;
        }
        
        // Verificar se expirou
        if (time() > $cacheData['expires']) {
            $this->delete($key);
            return null;
        }
        
        return $cacheData['data'];
    }

    /**
     * Verificar se existe no cache
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * Deletar do cache
     */
    public function delete($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }

    /**
     * Limpar todo o cache
     */
    public function clear()
    {
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    /**
     * Obter estatísticas do cache
     */
    public function getStats()
    {
        $files = glob($this->cacheDir . '/*');
        $totalFiles = count($files);
        $totalSize = 0;
        $expiredFiles = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                
                $content = file_get_contents($file);
                if ($content !== false) {
                    $cacheData = unserialize($content);
                    if ($cacheData && time() > $cacheData['expires']) {
                        $expiredFiles++;
                    }
                }
            }
        }
        
        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'expired_files' => $expiredFiles,
            'cache_dir' => $this->cacheDir
        ];
    }

    /**
     * Limpar arquivos expirados
     */
    public function cleanExpired()
    {
        $files = glob($this->cacheDir . '/*');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $cacheData = unserialize($content);
                    if ($cacheData && time() > $cacheData['expires']) {
                        unlink($file);
                        $cleaned++;
                    }
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Obter arquivo de cache
     */
    private function getCacheFile($key)
    {
        $safeKey = md5($key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
    }
}


