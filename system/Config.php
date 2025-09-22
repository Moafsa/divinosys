<?php

namespace System;

class Config
{
    private static $instance = null;
    private $config = [];
    private $env = [];

    private function __construct()
    {
        $this->loadEnv();
        $this->loadConfig();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnv()
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $this->env[trim($key)] = trim($value);
                }
            }
        }
    }

    private function loadConfig()
    {
        $this->config = [
            'app' => [
                'name' => $this->getEnv('APP_NAME'),
                'version' => $this->getEnv('APP_VERSION'),
                'env' => $this->getEnv('APP_ENV'),
                'debug' => $this->getEnv('APP_DEBUG') === 'true',
                'url' => $this->getEnv('APP_URL'),
                'key' => $this->getEnv('APP_KEY'),
            ],
            'database' => [
                'host' => $this->getEnv('DB_HOST'),
                'port' => $this->getEnv('DB_PORT'),
                'name' => $this->getEnv('DB_NAME'),
                'user' => $this->getEnv('DB_USER'),
                'password' => $this->getEnv('DB_PASSWORD'),
                'charset' => 'utf8',
            ],
            'session' => [
                'lifetime' => (int) $this->getEnv('SESSION_LIFETIME'),
                'encrypt' => $this->getEnv('SESSION_ENCRYPT') === 'true',
            ],
            'redis' => [
                'host' => $this->getEnv('REDIS_HOST'),
                'port' => $this->getEnv('REDIS_PORT'),
                'password' => $this->getEnv('REDIS_PASSWORD'),
            ],
            'mail' => [
                'mailer' => $this->getEnv('MAIL_MAILER'),
                'host' => $this->getEnv('MAIL_HOST'),
                'port' => (int) $this->getEnv('MAIL_PORT'),
                'username' => $this->getEnv('MAIL_USERNAME'),
                'password' => $this->getEnv('MAIL_PASSWORD'),
                'encryption' => $this->getEnv('MAIL_ENCRYPTION'),
                'from_address' => $this->getEnv('MAIL_FROM_ADDRESS'),
                'from_name' => $this->getEnv('MAIL_FROM_NAME'),
            ],
            'upload' => [
                'max_size' => $this->getEnv('UPLOAD_MAX_SIZE'),
                'allowed_extensions' => explode(',', $this->getEnv('ALLOWED_EXTENSIONS')),
            ],
            'multi_tenant' => [
                'enabled' => $this->getEnv('ENABLE_MULTI_TENANT') === 'true',
                'default_tenant_id' => (int) $this->getEnv('DEFAULT_TENANT_ID'),
            ],
        ];
    }

    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function getEnv($key, $default = null)
    {
        return $this->env[$key] ?? $default;
    }

    public function isProduction()
    {
        return $this->get('app.env') === 'production';
    }

    public function isDebug()
    {
        return $this->get('app.debug', false);
    }

    public function getAppUrl()
    {
        return rtrim($this->get('app.url'), '/');
    }

    public function getDatabaseConfig()
    {
        return $this->get('database');
    }

    public function getRedisConfig()
    {
        return $this->get('redis');
    }

    public function getMailConfig()
    {
        return $this->get('mail');
    }

    public function getUploadConfig()
    {
        return $this->get('upload');
    }

    public function isMultiTenantEnabled()
    {
        return $this->get('multi_tenant.enabled', false);
    }

    public function getDefaultTenantId()
    {
        return $this->get('multi_tenant.default_tenant_id', 1);
    }
}
