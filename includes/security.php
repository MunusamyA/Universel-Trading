<?php

/*
|--------------------------------------------------------------------------
| Secure Session Start
|--------------------------------------------------------------------------
*/

function secureSessionStart()
{
    if (session_status() === PHP_SESSION_NONE) {

        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();

        if (!isset($_SESSION['CREATED'])) {
            $_SESSION['CREATED'] = time();
        }

        if (time() - $_SESSION['CREATED'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfTokenInput()
{
    $token = generateCsrfToken();

    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken()
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(false, 'Invalid security token. Please refresh and try again.');
    }
}

/*
|--------------------------------------------------------------------------
| Input Cleaning
|--------------------------------------------------------------------------
*/

function cleanInput($value)
{
    if (is_array($value)) {
        return array_map('cleanInput', $value);
    }

    return trim($value);
}

function escapeHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Password Helpers
|--------------------------------------------------------------------------
*/

function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hashedPassword)
{
    return password_verify($password, $hashedPassword);
}

/*
|--------------------------------------------------------------------------
| API JSON Response
|--------------------------------------------------------------------------
*/

function jsonResponse($status, $message, $data = [], $extra = [])
{
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], $extra));
    exit;
}