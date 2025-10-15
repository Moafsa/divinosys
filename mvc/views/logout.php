<?php
// Clear all session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear Auth system session
if (class_exists('\System\Auth')) {
    \System\Auth::logout();
}

// Clear Session system
if (class_exists('\System\Session')) {
    $session = \System\Session::getInstance();
    $session->destroy();
}

// Clear AuthService
if (class_exists('\App\Auth\AuthService')) {
    $authService = new \App\Auth\AuthService();
    $authService->logout();
}

// Clear PHP session completely
session_destroy();
session_start();
session_regenerate_id(true);

// Redirect to login
$router = \System\Router::getInstance();
$router->redirect('login');
?>
