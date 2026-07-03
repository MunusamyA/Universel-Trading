<?php
require_once '../includes/db.php';
require_once '../includes/security.php';

/** @var PDO $pdo */

secureSessionStart();

header('Content-Type: application/json');

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'login':
        verifyCsrfToken();
        loginUser($pdo);
        break;

    case 'logout':
        logoutUser();
        break;

    case 'check_session':
        checkSession();
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function loginUser(PDO $pdo)
{
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        jsonResponse(false, 'Please enter username, email or mobile.');
    }

    if ($password === '') {
        jsonResponse(false, 'Please enter password.');
    }

    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            r.role_name,
            r.status AS role_status
        FROM users u
        LEFT JOIN roles r 
            ON r.id = u.role_id
        WHERE 
            u.username = :login_username
        LIMIT 1
    ");

    $stmt->execute([
        ':login_username' => $username,
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    jsonResponse(false, 'Invalid username or password.');
}

if (!verifyPassword($password, $user['password'])) {
    jsonResponse(false, 'Invalid username or password.');
}

if ((int)$user['status'] !== 1) {
    jsonResponse(false, 'Your account is inactive. Please contact admin.');
}
    $userType = $user['user_type'] ?? 'business_user';

    if ($userType === 'platform_owner') {
        platformOwnerLogin($user);
    }

    businessUserLogin($pdo, $user);
}

function platformOwnerLogin(array $user)
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['user_type'] = 'platform_owner';

    $_SESSION['business_id'] = 0;
    $_SESSION['branch_id'] = 0;

    $_SESSION['role_id'] = 0;
    $_SESSION['role_name'] = 'Platform Owner';

    $_SESSION['business_name'] = 'Platform';
    $_SESSION['branch_name'] = 'All Branches';

    $_SESSION['login_time'] = date('Y-m-d H:i:s');

    jsonResponse(true, 'Login successful.', [
        'user_type' => 'platform_owner'
    ], [
        'redirect' => 'pages/dashboard.php'
    ]);
}

function businessUserLogin(PDO $pdo, array $user)
{
    if (empty($user['business_id']) || empty($user['branch_id'])) {
        jsonResponse(false, 'Business or branch not assigned for this user.');
    }

    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.business_id,
            u.branch_id,
            u.role_id,
            u.name,
            u.username,
            u.email,
            u.mobile,
            u.status AS user_status,

            b.business_name,
            b.status AS business_status,

            br.branch_name,
            br.approval_status,
            br.status AS branch_status,

            r.role_name,
            r.status AS role_status

        FROM users u

        INNER JOIN businesses b 
            ON b.id = u.business_id

        INNER JOIN branches br 
            ON br.id = u.branch_id
            AND br.business_id = u.business_id

        LEFT JOIN roles r 
            ON r.id = u.role_id
            AND r.business_id = u.business_id
            AND r.branch_id = u.branch_id

        WHERE u.id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user['id']
    ]);

    $loginUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loginUser) {
        jsonResponse(false, 'Invalid business user.');
    }

    if ((int)$loginUser['user_status'] !== 1) {
        jsonResponse(false, 'User account is inactive.');
    }

    if ((int)$loginUser['business_status'] !== 1) {
        jsonResponse(false, 'Business is not active.');
    }

    if ((int)$loginUser['approval_status'] !== 1) {
        jsonResponse(false, 'Branch is not approved.');
    }

    if ((int)$loginUser['branch_status'] !== 1) {
        jsonResponse(false, 'Branch is inactive.');
    }

    if (!empty($loginUser['role_id']) && isset($loginUser['role_status']) && (int)$loginUser['role_status'] !== 1) {
        jsonResponse(false, 'Role is inactive.');
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$loginUser['id'];
    $_SESSION['user_name'] = $loginUser['name'];
    $_SESSION['username'] = $loginUser['username'];
    $_SESSION['user_type'] = 'business_user';

    $_SESSION['business_id'] = (int)$loginUser['business_id'];
    $_SESSION['branch_id'] = (int)$loginUser['branch_id'];

    $_SESSION['role_id'] = (int)$loginUser['role_id'];
    $_SESSION['role_name'] = $loginUser['role_name'] ?? '';

    $_SESSION['business_name'] = $loginUser['business_name'];
    $_SESSION['branch_name'] = $loginUser['branch_name'];

    $_SESSION['login_time'] = date('Y-m-d H:i:s');

    jsonResponse(true, 'Login successful.', [
        'user_type' => 'business_user'
    ], [
        'redirect' => 'pages/dashboard.php'
    ]);
}

function logoutUser()
{
    $_SESSION = [];

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

    session_destroy();

    jsonResponse(true, 'Logged out successfully.', [], [
        'redirect' => 'login.php'
    ]);
}

function checkSession()
{
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        jsonResponse(true, 'Session active.', [
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'user_type' => $_SESSION['user_type'] ?? '',
            'business_id' => $_SESSION['business_id'] ?? 0,
            'branch_id' => $_SESSION['branch_id'] ?? 0,
            'role_id' => $_SESSION['role_id'] ?? 0,
            'role_name' => $_SESSION['role_name'] ?? '',
            'business_name' => $_SESSION['business_name'] ?? '',
            'branch_name' => $_SESSION['branch_name'] ?? ''
        ]);
    }

    jsonResponse(false, 'Session expired.', [], [
        'redirect' => 'login.php'
    ]);
}