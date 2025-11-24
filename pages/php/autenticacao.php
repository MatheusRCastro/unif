<?php
// functions/auth.php
require_once 'config/session.php';

function isUserLoggedIn() {
    // Verifica se a sessão existe e se o usuário está logado
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        
        // Verifica timeout da sessão (opcional - 30 minutos)
        if (isset($_SESSION['login_time'])) {
            $session_duration = 30 * 60; // 30 minutos em segundos
            if (time() - $_SESSION['login_time'] > $session_duration) {
                // Sessão expirada
                logoutUser();
                return false;
            }
            
            // Renova o tempo da sessão
            $_SESSION['login_time'] = time();
        }
        
        return true;
    }
    
    return false;
}

function logoutUser() {
    // Limpa todos os dados da sessão
    $_SESSION = array();
    
    // Destrói o cookie da sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destrói a sessão
    session_destroy();
}

function requireLogin() {
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserInfo() {
    if (isUserLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null
        ];
    }
    return null;
}
?>