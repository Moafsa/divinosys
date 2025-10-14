<?php

namespace System\Middleware;

use System\Auth;
use System\Session;

class AccessControl
{
    /**
     * Verificar se o usuário tem acesso à página solicitada
     */
    public static function checkAccess($viewName)
    {
        // Inicializar sessão se necessário
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verificar se há sessão ativa
        if (!isset($_SESSION['user']) || !isset($_SESSION['user_type'])) {
            self::redirectToLogin();
            return false;
        }

        // Obter permissões do usuário
        $permissions = Auth::getUserPermissions($_SESSION['user_type']);
        
        // Se não tem permissões definidas, redirecionar para login
        if (empty($permissions)) {
            self::redirectToLogin();
            return false;
        }

        // Verificar se o usuário tem acesso à view solicitada
        if (!in_array($viewName, $permissions)) {
            self::redirectToUnauthorized($viewName);
            return false;
        }

        return true;
    }

    /**
     * Redirecionar para login
     */
    private static function redirectToLogin()
    {
        header('Location: index.php?view=login');
        exit();
    }

    /**
     * Redirecionar para página de acesso negado ou dashboard apropriado
     */
    private static function redirectToUnauthorized($viewName)
    {
        $userType = $_SESSION['user_type'] ?? 'cliente';
        
        // Log da tentativa de acesso negado
        error_log("Access denied for user type '{$userType}' trying to access '{$viewName}'");
        
        // Redirecionar para o dashboard apropriado do usuário
        $redirectUrl = self::getDefaultDashboard($userType);
        
        header("Location: {$redirectUrl}");
        exit();
    }

    /**
     * Obter dashboard padrão para cada tipo de usuário
     */
    private static function getDefaultDashboard($userType)
    {
        $dashboards = [
            'admin' => 'index.php?view=dashboard',
            'cozinha' => 'index.php?view=pedidos',
            'garcom' => 'index.php?view=dashboard',
            'entregador' => 'index.php?view=delivery',
            'caixa' => 'index.php?view=dashboard',
            'cliente' => 'index.php?view=cliente_dashboard'
        ];

        return $dashboards[$userType] ?? 'index.php?view=login';
    }

    /**
     * Obter menu de navegação baseado nas permissões do usuário
     */
    public static function getNavigationMenu()
    {
        if (!isset($_SESSION['user_type'])) {
            return [];
        }

        $permissions = Auth::getUserPermissions($_SESSION['user_type']);
        
        $menuItems = [
            'dashboard' => [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'index.php?view=dashboard'
            ],
            'novo_pedido' => [
                'label' => 'Novo Pedido',
                'icon' => 'fas fa-plus-circle',
                'url' => 'index.php?view=gerar_pedido'
            ],
            'pedidos' => [
                'label' => 'Pedidos',
                'icon' => 'fas fa-list',
                'url' => 'index.php?view=pedidos'
            ],
            'delivery' => [
                'label' => 'Delivery',
                'icon' => 'fas fa-truck',
                'url' => 'index.php?view=delivery'
            ],
            'produtos' => [
                'label' => 'Produtos',
                'icon' => 'fas fa-box',
                'url' => 'index.php?view=produtos'
            ],
            'estoque' => [
                'label' => 'Estoque',
                'icon' => 'fas fa-warehouse',
                'url' => 'index.php?view=estoque'
            ],
            'mesas' => [
                'label' => 'Mesas',
                'icon' => 'fas fa-table',
                'url' => 'index.php?view=mesas'
            ],
            'financeiro' => [
                'label' => 'Financeiro',
                'icon' => 'fas fa-dollar-sign',
                'url' => 'index.php?view=financeiro'
            ],
            'relatorios' => [
                'label' => 'Relatórios',
                'icon' => 'fas fa-chart-bar',
                'url' => 'index.php?view=relatorios'
            ],
            'clientes' => [
                'label' => 'Clientes',
                'icon' => 'fas fa-users',
                'url' => 'index.php?view=clientes'
            ],
            'configuracoes' => [
                'label' => 'Configurações',
                'icon' => 'fas fa-cog',
                'url' => 'index.php?view=configuracoes'
            ],
            'usuarios' => [
                'label' => 'Usuários',
                'icon' => 'fas fa-user-cog',
                'url' => 'index.php?view=usuarios'
            ],
            'historico_pedidos' => [
                'label' => 'Histórico',
                'icon' => 'fas fa-history',
                'url' => 'index.php?view=historico_pedidos'
            ],
            'perfil' => [
                'label' => 'Perfil',
                'icon' => 'fas fa-user',
                'url' => 'index.php?view=perfil'
            ]
        ];

        // Filtrar apenas os itens que o usuário tem permissão
        $allowedMenu = [];
        foreach ($permissions as $permission) {
            if (isset($menuItems[$permission])) {
                $allowedMenu[$permission] = $menuItems[$permission];
            }
        }

        return $allowedMenu;
    }

    /**
     * Verificar se o usuário tem uma permissão específica
     */
    public static function hasPermission($permission)
    {
        if (!isset($_SESSION['user_type'])) {
            return false;
        }

        $permissions = Auth::getUserPermissions($_SESSION['user_type']);
        return in_array($permission, $permissions);
    }

    /**
     * Obter tipo de usuário atual
     */
    public static function getCurrentUserType()
    {
        return $_SESSION['user_type'] ?? null;
    }
}
