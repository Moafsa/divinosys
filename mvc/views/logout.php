<?php
$authService = new \App\Auth\AuthService();
$authService->logout();
$router = \System\Router::getInstance();
$router->redirect('login');
?>
