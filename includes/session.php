<?php
// Include guard - prevent file from being executed twice
if (defined('SESSION_PHP_LOADED')) {
    return;
}
define('SESSION_PHP_LOADED', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only declare functions if they don't already exist
if (!function_exists('is_logged_in')) {

    // Check if user is logged in
    function is_logged_in() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    // Get current logged-in user
    function get_auth_user() {
        if (is_logged_in()) {
            return array(
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name']
            );
        }
        return null;
    }

    // Check if user has specific role
    function has_role($required_role) {
        if (!is_logged_in()) {
            return false;
        }
        
        if (is_array($required_role)) {
            return in_array($_SESSION['role'], $required_role);
        }
        
        return $_SESSION['role'] === $required_role;
    }

    // Redirect to login if not authenticated
    function require_login() {
        if (!is_logged_in()) {
            header("Location: /M1PROJET/auth/login.php");
            exit();
        }
    }

    // Redirect to login if user doesn't have required role
    function require_role($required_role) {
        require_login();
        
        if (!has_role($required_role)) {
            header("Location: /M1PROJET/index.php?error=unauthorized");
            exit();
        }
    }

    // Logout
    function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        header("Location: /M1PROJET/index.php");
        exit();
    }

}
?>
