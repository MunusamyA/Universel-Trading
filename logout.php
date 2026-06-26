<?php
require_once __DIR__ . '/includes/config.php';
require_once BASE_PATH . 'includes/security.php';

secureSessionStart();

/*
|--------------------------------------------------------------------------
| Clear all session data
|--------------------------------------------------------------------------
*/
$_SESSION = [];

/*
|--------------------------------------------------------------------------
| Delete session cookie
|--------------------------------------------------------------------------
*/
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/*
|--------------------------------------------------------------------------
| Destroy session
|--------------------------------------------------------------------------
*/
session_destroy();

/*
|--------------------------------------------------------------------------
| Redirect to login
|--------------------------------------------------------------------------
*/
header('Location: ' . BASE_URL . 'login.php?logout=success');
exit;