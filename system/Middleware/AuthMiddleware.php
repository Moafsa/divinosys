<?php

namespace System\Middleware;

use System\Auth;

/**
 * Authentication Middleware
 * Checks user authentication and permissions
 */
class AuthMiddleware
{
    /**
     * Check if user is authenticated
     */
    public static function checkAuth()
    {
        $session = Auth::validateSession();
        if (!$session) {
            self::redirectToLogin();
        }
        return $session;
    }

    /**
     * Check if user has specific permission
     */
    public static function checkPermission($permission)
    {
        $session = self::checkAuth();
        
        if (!Auth::hasPermission($permission)) {
            self::redirectToUnauthorized();
        }
        
        return $session;
    }

    /**
     * Check if user has specific role
     */
    public static function checkRole($roles)
    {
        $session = self::checkAuth();
        
        $userType = $_SESSION['user_type'] ?? null;
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        if (!$userType || !in_array($userType, $roles)) {
            self::redirectToUnauthorized();
        }
        
        return $session;
    }

    /**
     * Redirect to login page
     */
    private static function redirectToLogin()
    {
        if (php_sapi_name() !== 'cli') {
            header('Location: index.php?view=login');
            exit;
        }
        throw new \Exception('Authentication required');
    }

    /**
     * Redirect to unauthorized page
     */
    private static function redirectToUnauthorized()
    {
        if (php_sapi_name() !== 'cli') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acesso negado. Você não tem permissão para acessar esta página.',
                'error' => 'UNAUTHORIZED'
            ]);
            exit;
        }
        throw new \Exception('Access denied');
    }

    /**
     * Get current user data
     */
    public static function getCurrentUser()
    {
        $session = self::checkAuth();
        
        $db = \System\Database::getInstance();
        $user = $db->fetch(
            "SELECT * FROM usuarios_globais WHERE id = ?",
            [$session['usuario_global_id']]
        );
        
        $userEstablishment = $db->fetch(
            "SELECT * FROM usuarios_estabelecimento 
             WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
            [$session['usuario_global_id'], $session['tenant_id'], $session['filial_id']]
        );
        
        return [
            'user' => $user,
            'establishment' => $userEstablishment,
            'session' => $session
        ];
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin()
    {
        $userType = $_SESSION['user_type'] ?? null;
        return $userType === 'admin';
    }

    /**
     * Check if current user is customer
     */
    public static function isCustomer()
    {
        $userType = $_SESSION['user_type'] ?? null;
        return $userType === 'cliente';
    }

    /**
     * Get user dashboard URL based on role
     */
    public static function getDashboardUrl()
    {
        $userType = $_SESSION['user_type'] ?? 'admin';
        
        $dashboards = [
            'admin' => 'index.php?view=dashboard',
            'cozinha' => 'index.php?view=pedidos',
            'garcom' => 'index.php?view=dashboard',
            'entregador' => 'index.php?view=delivery',
            'caixa' => 'index.php?view=dashboard',
            'cliente' => 'index.php?view=cliente_dashboard'
        ];
        
        return $dashboards[$userType] ?? 'index.php?view=dashboard';
    }
}
