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

        // Logout deve estar sempre disponível para usuários logados
        if ($viewName === 'logout' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
            return true;
        }

        // Verificar se há sessão ativa
        if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
            self::redirectToLogin();
            return false;
        }

        // Se é admin, permitir acesso a tudo
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
            return true;
        }

        // Se não tem user_type definido mas tem user_id, buscar do banco
        if (!isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
            $db = \System\Database::getInstance();
            $usuario = $db->fetch("SELECT tipo_usuario FROM usuarios_estabelecimento WHERE usuario_global_id = ?", [$_SESSION['user_id']]);
            if ($usuario && $usuario['tipo_usuario']) {
                $_SESSION['user_type'] = $usuario['tipo_usuario'];
                error_log("AccessControl: User type loaded from database: {$usuario['tipo_usuario']}");
            } else {
                return true; // Fallback para admin se não encontrar
            }
        }
        
        // Log current user type for debugging
        error_log("AccessControl: Current user_type = " . ($_SESSION['user_type'] ?? 'not set'));

        // Obter permissões do usuário
        $userType = $_SESSION['user_type'] ?? 'admin';
        $permissions = Auth::getUserPermissions($userType);
        
        // Se não tem permissões definidas, permitir acesso (fallback)
        if (empty($permissions)) {
            return true;
        }

        // Verificar se o usuário tem acesso à view solicitada
        // Mapear views para permissões
        // Verificar se é uma view que requer tipo de usuário específico
        $userTypeRestrictedViews = [
            'fechar_pedido' => ['garcom', 'caixa', 'entregador'], // Apenas garçom, caixa e entregador podem fechar pedidos
        ];
        
        // Verificar restrição por tipo de usuário primeiro
        if (isset($userTypeRestrictedViews[$viewName])) {
            $allowedUserTypes = $userTypeRestrictedViews[$viewName];
            $currentUserType = $_SESSION['user_type'] ?? 'admin';
            if (!in_array($currentUserType, $allowedUserTypes)) {
                // Usuário não tem permissão para esta view
                self::redirectToUnauthorized($viewName);
                return false;
            } else {
                // Usuário tem permissão, permitir acesso
                return true;
            }
        }
        
        // RESTRIÇÃO: Faturas apenas para filial matriz
        if ($viewName === 'gerenciar_faturas') {
            if (!self::isFilialMatriz()) {
                error_log("AccessControl: Acesso negado a 'gerenciar_faturas' - não é filial matriz");
                self::redirectToUnauthorized($viewName);
                return false;
            }
        }
        
        // Mapear views para permissões
        $viewPermissionMap = [
            'gerar_pedido' => 'novo_pedido',
            'gerenciar_produtos' => ['produtos', 'gerenciar_produtos'],
        ];
        
        // Verificar mapeamento de permissões
        if (isset($viewPermissionMap[$viewName])) {
            $mappedPermissions = is_array($viewPermissionMap[$viewName]) ? $viewPermissionMap[$viewName] : [$viewPermissionMap[$viewName]];
            
            // Verificar se o usuário tem alguma das permissões requeridas
            $hasAccess = false;
            foreach ($mappedPermissions as $permission) {
                if (in_array($permission, $permissions)) {
                    $hasAccess = true;
                    break;
                }
            }
            if ($hasAccess) {
                return true;
            }
        }
        
        // Se não tem permissão na view solicitada
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
        // Se não tem user_type definido mas tem user_id, buscar do banco
        if (!isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
            $db = \System\Database::getInstance();
            $usuario = $db->fetch("SELECT tipo_usuario FROM usuarios_estabelecimento WHERE usuario_global_id = ?", [$_SESSION['user_id']]);
            if ($usuario && $usuario['tipo_usuario']) {
                $_SESSION['user_type'] = $usuario['tipo_usuario'];
            }
        }
        
        if (!isset($_SESSION['user_type'])) {
            error_log("getNavigationMenu: user_type not set");
            return [];
        }

        $permissions = Auth::getUserPermissions($_SESSION['user_type']);
        error_log("getNavigationMenu: user_type = " . $_SESSION['user_type'] . ", permissions = " . implode(', ', $permissions));
        
        $menuItems = [
            'dashboard' => [
                'label' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'index.php?view=dashboard'
            ],
            'pedidos' => [
                'label' => 'Pedidos',
                'icon' => 'fas fa-list',
                'url' => 'index.php?view=pedidos'
            ],
            'novo_pedido' => [
                'label' => 'Novo Pedido',
                'icon' => 'fas fa-plus-circle',
                'url' => 'index.php?view=gerar_pedido'
            ],
            'delivery' => [
                'label' => 'Delivery',
                'icon' => 'fas fa-truck',
                'url' => 'index.php?view=delivery'
            ],
            'produtos' => [
                'label' => 'Produtos',
                'icon' => 'fas fa-box',
                'url' => 'index.php?view=gerenciar_produtos'
            ],
            'estoque' => [
                'label' => 'Estoque',
                'icon' => 'fas fa-warehouse',
                'url' => 'index.php?view=estoque'
            ],
            'financeiro' => [
                'label' => 'Financeiro',
                'icon' => 'fas fa-dollar-sign',
                'url' => 'index.php?view=financeiro'
            ],
                'gerenciar_faturas' => [
                    'label' => 'Faturas',
                    'icon' => 'fas fa-file-invoice-dollar',
                    'url' => 'index.php?view=gerenciar_faturas'
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
            'historico_pedidos' => [
                'label' => 'Histórico',
                'icon' => 'fas fa-history',
                'url' => 'index.php?view=historico_pedidos'
            ],
            'perfil' => [
                'label' => 'Perfil',
                'icon' => 'fas fa-user',
                'url' => 'index.php?view=perfil'
            ],
            'logout' => [
                'label' => 'Sair',
                'icon' => 'fas fa-sign-out-alt',
                'url' => 'index.php?action=logout'
            ],
        ];

        // Filtrar apenas os itens que o usuário tem permissão
        $allowedMenu = [];
        foreach ($permissions as $permission) {
            if (isset($menuItems[$permission])) {
                // RESTRIÇÃO: Faturas apenas para filial matriz
                if ($permission === 'gerenciar_faturas') {
                    if (self::isFilialMatriz()) {
                        $allowedMenu[$permission] = $menuItems[$permission];
                    }
                    // Se não for matriz, pular este item do menu
                } else {
                    $allowedMenu[$permission] = $menuItems[$permission];
                }
            }
        }
        
        // Sempre adicionar logout se o usuário estiver logado
        if (isset($menuItems['logout'])) {
            $allowedMenu['logout'] = $menuItems['logout'];
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
    
    /**
     * Verificar se a filial atual é a matriz (primeira filial do tenant)
     */
    private static function isFilialMatriz()
    {
        $session = Session::getInstance();
        $db = \System\Database::getInstance();
        
        $tenantId = $session->getTenantId();
        $filialId = $session->getFilialId();
        
        // Se não tem tenant ou filial, retornar false
        if (!$tenantId || !$filialId) {
            error_log("AccessControl::isFilialMatriz - Sem tenant ou filial na sessão");
            return false;
        }
        
        // Buscar a primeira filial do tenant (matriz)
        $primeiraFilial = $db->fetch(
            "SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1",
            [$tenantId]
        );
        
        if (!$primeiraFilial) {
            error_log("AccessControl::isFilialMatriz - Nenhuma filial encontrada para tenant $tenantId");
            return false;
        }
        
        $isMatriz = ($filialId == $primeiraFilial['id']);
        
        error_log("AccessControl::isFilialMatriz - Tenant: $tenantId, Filial atual: $filialId, Primeira filial: {$primeiraFilial['id']}, É matriz? " . ($isMatriz ? 'SIM' : 'NÃO'));
        
        return $isMatriz;
    }
}
