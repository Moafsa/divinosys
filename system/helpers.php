<?php

/**
 * Helper functions for the application
 */

if (!function_exists('dd')) {
    function dd($data) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();
    }
}

if (!function_exists('dump')) {
    function dump($data) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($value, $currency = 'R$') {
        return $currency . ' ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) return '';
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime)) return '';
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
        $string = preg_replace('/[\s-]+/', '-', $string);
        return trim($string, '-');
    }
}

if (!function_exists('generateCode')) {
    function generateCode($length = 4) {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        switch ($status) {
            case 'Pendente':
                return 'warning';
            case 'Em Preparo':
                return 'info';
            case 'Pronto':
                return 'success';
            case 'Saiu para Entrega':
                return 'primary';
            case 'Entregue':
                return 'success';
            case 'Finalizado':
                return 'secondary';
            case 'Cancelado':
                return 'danger';
            case '1': // Mesa livre
                return 'success';
            case '2': // Mesa ocupada
                return 'danger';
            default:
                return 'secondary';
        }
    }
}

if (!function_exists('getStatusText')) {
    function getStatusText($status) {
        switch ($status) {
            case '1':
                return 'Livre';
            case '2':
                return 'Ocupada';
            default:
                return $status;
        }
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'agora mesmo';
        if ($time < 3600) return floor($time/60) . ' min atrás';
        if ($time < 86400) return floor($time/3600) . ' h atrás';
        if ($time < 2592000) return floor($time/86400) . ' dias atrás';
        if ($time < 31536000) return floor($time/2592000) . ' meses atrás';
        
        return floor($time/31536000) . ' anos atrás';
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validateCPF')) {
    function validateCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return false;
        }
        
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
}

if (!function_exists('validateCNPJ')) {
    function validateCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        for ($i = 0, $j = 5, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;
        
        if ($cnpj[12] != $digit1) {
            return false;
        }
        
        for ($i = 0, $j = 6, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;
        
        return $cnpj[13] == $digit2;
    }
}

if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }
}

if (!function_exists('validateCEP')) {
    function validateCEP($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return strlen($cep) == 8;
    }
}

if (!function_exists('maskCPF')) {
    function maskCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
}

if (!function_exists('maskCNPJ')) {
    function maskCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
}

if (!function_exists('maskPhone')) {
    function maskPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
        } elseif (strlen($phone) == 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
        }
        return $phone;
    }
}

if (!function_exists('maskCEP')) {
    function maskCEP($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        $session = \System\Session::getInstance();
        return $session->getUser();
    }
}

if (!function_exists('getCurrentTenant')) {
    function getCurrentTenant() {
        $session = \System\Session::getInstance();
        return $session->getTenant();
    }
}

if (!function_exists('getCurrentFilial')) {
    function getCurrentFilial() {
        $session = \System\Session::getInstance();
        return $session->getFilial();
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        $session = \System\Session::getInstance();
        return $session->isAdmin();
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        $session = \System\Session::getInstance();
        return $session->isLoggedIn();
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        $config = \System\Config::getInstance();
        return rtrim($config->getAppUrl(), '/') . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($view, $params = []) {
        $router = \System\Router::getInstance();
        return $router->url($view, $params);
    }
}

if (!function_exists('redirect')) {
    function redirect($view, $params = []) {
        $router = \System\Router::getInstance();
        $router->redirect($view, $params);
    }
}

if (!function_exists('upload')) {
    function upload($path) {
        $config = \System\Config::getInstance();
        return rtrim($config->getAppUrl(), '/') . '/uploads/' . ltrim($path, '/');
    }
}

if (!function_exists('flash')) {
    function flash($type, $message = null) {
        $session = \System\Session::getInstance();
        if ($message === null) {
            return $session->getMessage($type);
        }
        $session->setMessage($type, $message);
    }
}

if (!function_exists('old')) {
    function old($key, $default = null) {
        $session = \System\Session::getInstance();
        return $session->get("old.{$key}", $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        $security = \System\Security::getInstance();
        return $security->generateCSRFToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}
