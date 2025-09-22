<?php

namespace System;

class Session
{
    private static $instance = null;
    private $config;

    private function __construct()
    {
        $this->config = Config::getInstance();
        $this->start();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = $this->config->get('session.lifetime', 120) * 60;
            
            ini_set('session.cookie_lifetime', $lifetime);
            ini_set('session.gc_maxlifetime', $lifetime);
            
            session_start();
        }
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public function destroy()
    {
        session_destroy();
        $_SESSION = [];
    }

    public function regenerate()
    {
        session_regenerate_id(true);
    }

    public function flash($key, $value = null)
    {
        if ($value === null) {
            $value = $this->get("flash.{$key}");
            $this->remove("flash.{$key}");
            return $value;
        }
        
        $this->set("flash.{$key}", $value);
    }

    public function setUser($user)
    {
        $this->set('user', $user);
        $this->set('user_id', $user['id']);
        $this->set('user_login', $user['login']);
        $this->set('user_nivel', $user['nivel']);
        $this->set('tenant_id', $user['tenant_id']);
        $this->set('filial_id', $user['filial_id']);
    }

    public function getUser()
    {
        return $this->get('user');
    }

    public function getUserId()
    {
        return $this->get('user_id');
    }

    public function getUserLogin()
    {
        return $this->get('user_login');
    }

    public function getUserNivel()
    {
        return $this->get('user_nivel');
    }

    public function getTenantId()
    {
        return $this->get('tenant_id');
    }

    public function getFilialId()
    {
        return $this->get('filial_id');
    }

    public function isLoggedIn()
    {
        return $this->has('user_id');
    }

    public function isAdmin()
    {
        return $this->getUserNivel() == 1;
    }

    public function setTenant($tenant)
    {
        $this->set('tenant', $tenant);
        $this->set('tenant_id', $tenant['id']);
        $this->set('tenant_nome', $tenant['nome']);
        $this->set('tenant_subdomain', $tenant['subdomain']);
    }

    public function getTenant()
    {
        return $this->get('tenant');
    }

    public function getTenantNome()
    {
        return $this->get('tenant_nome');
    }

    public function getTenantSubdomain()
    {
        return $this->get('tenant_subdomain');
    }

    public function setFilial($filial)
    {
        $this->set('filial', $filial);
        $this->set('filial_id', $filial['id']);
        $this->set('filial_nome', $filial['nome']);
    }

    public function getFilial()
    {
        return $this->get('filial');
    }

    public function getFilialNome()
    {
        return $this->get('filial_nome');
    }

    public function setCor($cor)
    {
        $this->set('cor', $cor);
    }

    public function getCor()
    {
        return $this->get('cor', 'info');
    }

    public function setMessage($type, $message)
    {
        $this->flash("message.{$type}", $message);
    }

    public function getMessage($type)
    {
        return $this->flash("message.{$type}");
    }

    public function hasMessage($type)
    {
        return $this->has("flash.message.{$type}");
    }

    public function getAllMessages()
    {
        $messages = [];
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'flash.message.') === 0) {
                $type = str_replace('flash.message.', '', $key);
                $messages[$type] = $value;
                unset($_SESSION[$key]);
            }
        }
        return $messages;
    }
}
