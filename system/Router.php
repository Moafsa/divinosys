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
            'logout' => 'logout.php',
            'dashboard' => 'Dashboard1.php',
            'gerar_pedido' => 'gerar_pedido.php',
            'pedidos' => 'pedidos.php',
            'mesas' => 'mesas.php',
            'delivery' => 'delivery.php',
            'gerenciar_produtos' => 'gerenciar_produtos.php',
            'gerenciar_categorias' => 'gerenciar_categorias.php',
            'estoque' => 'estoque.php',
            'financeiro' => 'financeiro.php',
            'relatorios' => 'relatorios.php',
            'agenda' => 'agenda/index.php',
            'clientes' => 'clientes.php',
            'entregadores' => 'entregadores.php',
            'usuarios' => 'usuarios.php',
            'configuracoes' => 'configuracoes.php',
            'salvar_configuracoes' => 'salvar_configuracoes.php',
            'dashboard_ajax' => 'dashboard.php',
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
        }

        // Check tenant context for multi-tenant routes
        if ($this->config->isMultiTenantEnabled() && $this->isMultiTenantRoute($view)) {
            $this->checkTenantContext();
        }

        $this->loadView($view);
    }

    private function isProtectedRoute($view)
    {
        $publicRoutes = ['home', 'login'];
        return !in_array($view, $publicRoutes);
    }

    private function isMultiTenantRoute($view)
    {
        $multiTenantRoutes = [
            'dashboard', 'gerar_pedido', 'pedidos', 'mesas', 'delivery',
            'gerenciar_produtos', 'gerenciar_categorias', 'estoque',
            'financeiro', 'relatorios', 'agenda', 'clientes', 'entregadores',
            'usuarios', 'configuracoes'
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
            'mesas' => 'Mesas',
            'delivery' => 'Delivery',
            'gerenciar_produtos' => 'Produtos',
            'gerenciar_categorias' => 'Categorias',
            'estoque' => 'Estoque',
            'financeiro' => 'Financeiro',
            'relatorios' => 'Relatórios',
            'agenda' => 'Agenda',
            'clientes' => 'Clientes',
            'entregadores' => 'Entregadores',
            'usuarios' => 'Usuários',
            'configuracoes' => 'Configurações',
        ];
        
        return $names[$view] ?? ucfirst($view);
    }
}
