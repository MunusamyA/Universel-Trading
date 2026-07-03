<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireApiLogin()
{
    if (!isLoggedIn()) {
        jsonResponse(false, 'Session expired. Please login again.', [], [
            'redirect' => BASE_URL . 'login.php'
        ]);
    }
}

function currentLiveUser()
{
    global $pdo;

    if (!isLoggedIn()) {
        return null;
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        require_once __DIR__ . '/db.php';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.username,
                u.email,
                u.mobile,
                u.business_id,
                u.branch_id,
                u.role_id,
                u.user_type,
                u.status AS user_status,

                b.business_name,
                b.status AS business_status,

                br.branch_name,
                br.approval_status,
                br.status AS branch_status,

                r.role_name,
                r.role_type,
                r.parent_role_id,
                r.is_locked,
                r.status AS role_status
            FROM users u
            LEFT JOIN businesses b ON b.id = u.business_id
            LEFT JOIN branches br ON br.id = u.branch_id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => (int)$_SESSION['user_id']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function currentUserId(){ return (int)($_SESSION['user_id'] ?? 0); }
function currentUserName(){ $u = currentLiveUser(); return $u['name'] ?? ''; }
function currentUserType(){ $u = currentLiveUser(); return $u['user_type'] ?? ''; }
function currentBusinessId(){ $u = currentLiveUser(); return (int)($u['business_id'] ?? 0); }
function currentBranchId(){ $u = currentLiveUser(); return (int)($u['branch_id'] ?? 0); }
function currentRoleId(){ $u = currentLiveUser(); return (int)($u['role_id'] ?? 0); }
function currentRoleName(){ $u = currentLiveUser(); return $u['role_name'] ?? ''; }
function currentBusinessName(){ $u = currentLiveUser(); return $u['business_name'] ?? ''; }
function currentBranchName(){ $u = currentLiveUser(); return $u['branch_name'] ?? ''; }
function isPlatformOwner(){ return currentUserType() === 'platform_owner'; }
function isBusinessUser(){ return currentUserType() === 'business_user'; }

function hasPermission($menuKey, $action = 'view')
{
    global $pdo;

    if (!isLoggedIn()) {
        return false;
    }

    $user = currentLiveUser();

    if (!$user || (int)$user['user_status'] !== 1) {
        return false;
    }

    if (($user['user_type'] ?? '') === 'platform_owner') {
        return true;
    }

    if ((int)($user['business_status'] ?? 0) !== 1) {
        return false;
    }

    if ((int)($user['approval_status'] ?? 0) !== 1) {
        return false;
    }

    if ((int)($user['branch_status'] ?? 0) !== 1) {
        return false;
    }

    if ((int)($user['role_status'] ?? 0) !== 1) {
        return false;
    }

    $roleId = (int)($user['role_id'] ?? 0);

    if ($roleId <= 0 || $menuKey === '' || $action === '') {
        return false;
    }

    $columnMap = [
        'view'             => 'can_view',
        'add'              => 'can_add',
        'edit'             => 'can_edit',
        'delete'           => 'can_delete',
        'print'            => 'can_print',
        'export'           => 'can_export',
        'approve'          => 'can_approve',
        'convert'          => 'can_convert',
        'adjust'           => 'can_adjust',
        'ship'             => 'can_ship',
        'generate_invoice' => 'can_generate_invoice'
    ];

    if (!isset($columnMap[$action])) {
        return false;
    }

    $column = $columnMap[$action];

    try {
        $stmt = $pdo->prepare("
            SELECT
                CASE
                    WHEN rmp_direct.id IS NOT NULL THEN COALESCE(rmp_direct.{$column}, 0)
                    ELSE COALESCE(rmp_package.{$column}, 0)
                END AS permission_value
            FROM roles r
            INNER JOIN sidebar_menus sm
                ON sm.menu_key = :menu_key
                AND sm.status = 1
            LEFT JOIN role_menu_permissions rmp_direct
                ON rmp_direct.role_id = r.id
                AND rmp_direct.menu_id = sm.id
                AND rmp_direct.status = 1
            LEFT JOIN role_menu_permissions rmp_package
                ON rmp_package.role_id = r.parent_role_id
                AND rmp_package.menu_id = sm.id
                AND rmp_package.status = 1
            WHERE r.id = :role_id
            AND r.status = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':role_id'  => $roleId,
            ':menu_key' => $menuKey
        ]);

        $permission = $stmt->fetch(PDO::FETCH_ASSOC);

        return $permission && (int)$permission['permission_value'] === 1;
    } catch (Exception $e) {
        return false;
    }
}

function requirePermission($menuKey, $action = 'view')
{
    if (!hasPermission($menuKey, $action)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

function requireApiPermission($menuKey, $action = 'view')
{
    if (!hasPermission($menuKey, $action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function businessWhereClause($tableAlias = '')
{
    if (isPlatformOwner()) {
        return ['sql' => '', 'params' => []];
    }

    $prefix = $tableAlias ? $tableAlias . '.' : '';

    return [
        'sql' => " AND {$prefix}business_id = ? AND {$prefix}branch_id = ? ",
        'params' => [currentBusinessId(), currentBranchId()]
    ];
}

function logout()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
