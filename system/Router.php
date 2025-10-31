<?php

namespace System;

class Router
{
    private static $instance = null;
    private $routes = [];
    private $currentRoute = null;
    private $config;

    private function __construct()
    {
        $this->config = Config::getInstance();
        $this->loadRoutes();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    private function loadRoutes()
    {
        $this->routes = [
            'home' => 'home.php',
            'login' => 'login.php',
            'login_admin' => 'login_admin.php',
            'register' => 'register.php',
            'logout' => 'logout.php',
            'dashboard' => 'Dashboard1.php',
            'cliente_dashboard' => 'cliente_dashboard.php',
            'historico_pedidos' => 'historico_pedidos.php',
            'perfil' => 'perfil.php',
            'gerar_pedido' => 'gerar_pedido.php',
            'pedidos' => 'pedidos.php',
            'fechar_pedido' => 'FecharPedido.php',
            'delivery' => 'delivery.php',
            'gerenciar_produtos' => 'gerenciar_produtos.php',
            'crud_simples' => 'crud_simples.php',
            'estoque' => 'estoque.php',
            'financeiro' => 'financeiro.php',
            'novo_lancamento' => 'novo_lancamento.php',
            'gerar_relatorios' => 'gerar_relatorios.php',
            'relatorios' => 'relatorios.php',
            'lancamentos' => 'lancamentos.php',
            'agenda' => 'agenda/index.php',
            'clientes' => 'clientes.php',
            'entregadores' => 'entregadores.php',
            'ai_chat' => 'AIChat.php',
            'configuracoes' => 'configuracoes.php',
            'salvar_configuracoes' => 'salvar_configuracoes.php',
            'dashboard_ajax' => 'dashboard.php',
            'gestao_clientes_fiado' => 'gestao_clientes_fiado.php',
        'vendas_fiadas' => 'vendas_fiadas.php',
        'descontos_cortesias' => 'descontos_cortesias.php',
        'integracao_pagamentos' => 'integracao_pagamentos.php',
        'caixa_avancado' => 'caixa_avancado.php',
        'relatorios_financeiros' => 'relatorios_financeiros.php',
        'gestao_mesas_pedidos' => 'gestao_mesas_pedidos.php',
        'caixa_pedidos_antigos' => 'caixa_pedidos_antigos.php',
        'fix_mesas_pedidos' => 'fix_mesas_pedidos.php',
        'fix_mesas_page' => 'fix_mesas_page.php',
        'test_fechar_pedido' => 'test_fechar_pedido.php',
        // SaaS Routes
        'superadmin_dashboard' => 'superadmin_dashboard.php',
        'tenant_dashboard' => 'tenant_dashboard.php',
        'onboarding' => 'onboarding.php',
        'subscription_expired' => 'subscription_expired.php',
        'gerenciar_faturas' => 'gerenciar_faturas.php',
        'asaas_config' => 'asaas_config.php',
        ];
    }

    public function resolve()
    {
        $view = $_GET['view'] ?? 'home';
        
        // Check if route exists
        if (!isset($this->routes[$view])) {
            $this->show404();
            return;
        }

        $this->currentRoute = $view;
        
        // Check authentication for protected routes
        if ($this->isProtectedRoute($view)) {
            $this->checkAuthentication();
            
            // Check access control based on user type
            $this->checkAccessControl($view);
        }

        // Check tenant context for multi-tenant routes
        if ($this->config->isMultiTenantEnabled() && $this->isMultiTenantRoute($view)) {
            $this->checkTenantContext();
        }

        $this->loadView($view);
    }

    private function isProtectedRoute($view)
    {
        $publicRoutes = [
            'home', 
            'login', 
            'login_admin',
            'register',
            'onboarding',
            'subscription_expired'
        ];
        
        // SuperAdmin dashboard é protegido mas acessível para superadmin
        if ($view === 'superadmin_dashboard') {
            return !isset($_SESSION['nivel']) || $_SESSION['nivel'] != 999;
        }
        
        return !in_array($view, $publicRoutes);
    }

    private function isMultiTenantRoute($view)
    {
        $multiTenantRoutes = [
            'dashboard', 'gerar_pedido', 'pedidos', 'delivery',
            'gerenciar_produtos', 'gerenciar_categorias', 'estoque',
            'financeiro', 'relatorios', 'lancamentos', 'agenda', 'clientes', 'entregadores',
            'ai_chat', 'configuracoes', 'whatsapp_config',
            // Sistema de Filiais
            'dashboard_estabelecimento', 'gerenciar_filiais', 'relatorios_consolidados'
        ];
        return in_array($view, $multiTenantRoutes);
    }

    private function checkAuthentication()
    {
        $session = Session::getInstance();
        
        if (!$session->isLoggedIn()) {
            $this->redirect('login');
            return;
        }
    }

    private function checkTenantContext()
    {
        $session = Session::getInstance();
        
        if (!$session->getTenantId()) {
            $this->redirect('login');
            return;
        }
    }

    private function checkAccessControl($view)
    {
        // Importar o AccessControl
        require_once __DIR__ . '/Middleware/AccessControl.php';
        
        // Verificar se o usuário tem acesso à página
        if (!\System\Middleware\AccessControl::checkAccess($view)) {
            // O AccessControl já redireciona automaticamente
            exit();
        }
    }

    private function loadView($view)
    {
        $viewFile = $this->routes[$view];
        $viewPath = __DIR__ . '/../mvc/views/' . $viewFile;
        
        if (file_exists($viewPath)) {
            // Set global variables for views
            $this->setGlobalVariables();
            
            // Include the view
            include $viewPath;
        } else {
            $this->show404();
        }
    }

    private function setGlobalVariables()
    {
        $session = Session::getInstance();
        
        // Set access control variables if user is logged in
        if ($session->isLoggedIn()) {
            require_once __DIR__ . '/Middleware/AccessControl.php';
            
            $navigationMenu = \System\Middleware\AccessControl::getNavigationMenu();
            $currentUserType = \System\Middleware\AccessControl::getCurrentUserType();
            
            // Make these available to views
            $GLOBALS['navigationMenu'] = $navigationMenu;
            $GLOBALS['currentUserType'] = $currentUserType;
        }
        $config = Config::getInstance();
        
        // Make these available in views
        $GLOBALS['user'] = $session->getUser();
        $GLOBALS['tenant'] = $session->getTenant();
        $GLOBALS['filial'] = $session->getFilial();
        $GLOBALS['cor'] = $session->getCor();
        $GLOBALS['app_url'] = $config->getAppUrl();
        $GLOBALS['app_name'] = $config->get('app.name');
        $GLOBALS['current_route'] = $this->currentRoute;
    }

    private function show404()
    {
        http_response_code(404);
        include __DIR__ . '/../mvc/views/404.php';
    }

    public function redirect($view, $params = [])
    {
        $url = $this->config->getAppUrl() . '/index.php?view=' . $view;
        
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        
        header('Location: ' . $url);
        exit;
    }

    public function redirectTo($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    public function url($view, $params = [])
    {
        $url = $this->config->getAppUrl() . '/index.php?view=' . $view;
        
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        
        return $url;
    }

    public function asset($path)
    {
        return $this->config->getAppUrl() . '/assets/' . ltrim($path, '/');
    }

    public function upload($path)
    {
        return $this->config->getAppUrl() . '/uploads/' . ltrim($path, '/');
    }

    public function isCurrentRoute($view)
    {
        return $this->currentRoute === $view;
    }

    public function getRouteName($view)
    {
        $names = [
            'home' => 'Home',
            'login' => 'Login',
            'dashboard' => 'Dashboard',
            'gerar_pedido' => 'Novo Pedido',
            'pedidos' => 'Pedidos',
            'delivery' => 'Delivery',
            'gerenciar_produtos' => 'Produtos',
            'gerenciar_categorias' => 'Categorias',
            'estoque' => 'Estoque',
            'financeiro' => 'Financeiro',
            'relatorios' => 'Relatórios',
            'agenda' => 'Agenda',
            'clientes' => 'Clientes',
            'entregadores' => 'Entregadores',
            'configuracoes' => 'Configurações',
            // Sistema de Filiais
            'dashboard_estabelecimento' => 'Dashboard Estabelecimento',
            'gerenciar_filiais' => 'Gerenciar Filiais',
            'relatorios_consolidados' => 'Relatórios Consolidados',
        ];
        
        return $names[$view] ?? ucfirst($view);
    }
}
