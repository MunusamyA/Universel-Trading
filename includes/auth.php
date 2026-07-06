<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('secureSessionStart')) {
        secureSessionStart();
    } else {
        session_start();
    }
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
    static $loaded = false;
    static $cachedUser = null;

    global $pdo;

    if ($loaded) {
        return $cachedUser;
    }

    $loaded = true;

    if (!isLoggedIn()) {
        $cachedUser = null;
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

        $cachedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $cachedUser;

    } catch (Exception $e) {
        $cachedUser = null;
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
function currentPackageRoleId(){ $u = currentLiveUser(); return (int)($u['parent_role_id'] ?? 0); }
function isPlatformOwner(){ return currentUserType() === 'platform_owner'; }
function isBusinessUser(){ return currentUserType() === 'business_user'; }

function parseAccessActions($actions)
{
    if ($actions === null || $actions === '') {
        return [];
    }

    if (is_array($actions)) {
        $parts = $actions;
    } else {
        $parts = explode(',', (string)$actions);
    }

    $clean = [];

    foreach ($parts as $part) {
        $code = (int)trim((string)$part);

        if ($code > 0) {
            $clean[] = $code;
        }
    }

    $clean = array_values(array_unique($clean));
    sort($clean, SORT_NUMERIC);

    return $clean;
}

function accessActionsCsv($actions)
{
    $actions = parseAccessActions($actions);

    if (empty($actions)) {
        return '';
    }

    return implode(',', $actions);
}

/**
 * True means this role has its own role_base_access rows.
 * In that case, do NOT fallback to package for missing menus.
 *
 * False means this is normally Branch Admin created from customer registration.
 * In that case, fallback to package permissions using roles.parent_role_id.
 */
function roleHasOwnPermissionRows($roleId)
{
    static $cache = [];

    global $pdo;

    $roleId = (int)$roleId;

    if ($roleId <= 0) {
        return false;
    }

    if (isset($cache[$roleId])) {
        return $cache[$roleId];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM role_base_access
            WHERE role_id = :role_id
        ");

        $stmt->execute([
            ':role_id' => $roleId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$roleId] = ((int)($row['total'] ?? 0)) > 0;

        return $cache[$roleId];

    } catch (Exception $e) {
        $cache[$roleId] = false;
        return false;
    }
}

/**
 * Final access rule:
 *
 * Platform Owner:
 * - Full access.
 *
 * Business role with own permission rows:
 * - Use own role_base_access only.
 * - If status = 2 or row missing, deny.
 * - No fallback to package.
 *
 * Business role with no own permission rows:
 * - Use parent package permission.
 * - This is used for Branch Admin created during customer registration.
 */
function getPageAccess($menuKey)
{
    static $accessCache = [];

    global $pdo;

    $menuKey = trim((string)$menuKey);

    if ($menuKey === '' || !isLoggedIn()) {
        return [];
    }

    $cacheKey = currentUserId() . ':' . $menuKey;

    if (isset($accessCache[$cacheKey])) {
        return $accessCache[$cacheKey];
    }

    $user = currentLiveUser();

    if (!$user || (int)($user['user_status'] ?? 0) !== 1) {
        $accessCache[$cacheKey] = [];
        return [];
    }

    if (($user['user_type'] ?? '') === 'platform_owner') {
        $accessCache[$cacheKey] = range(1, 100);
        return $accessCache[$cacheKey];
    }

    if ((int)($user['business_status'] ?? 0) !== 1 ||
        (int)($user['approval_status'] ?? 0) !== 1 ||
        (int)($user['branch_status'] ?? 0) !== 1 ||
        (int)($user['role_status'] ?? 0) !== 1) {
        $accessCache[$cacheKey] = [];
        return [];
    }

    $roleId = (int)($user['role_id'] ?? 0);
    $packageRoleId = (int)($user['parent_role_id'] ?? 0);

    if ($roleId <= 0) {
        $accessCache[$cacheKey] = [];
        return [];
    }

    try {
        if (roleHasOwnPermissionRows($roleId)) {
            $stmt = $pdo->prepare("
                SELECT
                    rba.access_actions,
                    rba.status
                FROM sidebar_menus sm
                LEFT JOIN role_base_access rba
                    ON rba.menu_id = sm.id
                    AND rba.role_id = :role_id
                WHERE sm.menu_key = :menu_key
                AND sm.status = 1
                LIMIT 1
            ");

            $stmt->execute([
                ':role_id' => $roleId,
                ':menu_key' => $menuKey
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)($row['status'] ?? 0) !== 1) {
                $accessCache[$cacheKey] = [];
                return [];
            }

            $accessCache[$cacheKey] = parseAccessActions($row['access_actions'] ?? '');
            return $accessCache[$cacheKey];
        }

        if ($packageRoleId <= 0) {
            $accessCache[$cacheKey] = [];
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                package_access.access_actions
            FROM sidebar_menus sm
            INNER JOIN role_base_access package_access
                ON package_access.menu_id = sm.id
                AND package_access.role_id = :package_role_id
                AND package_access.status = 1
            WHERE sm.menu_key = :menu_key
            AND sm.status = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':package_role_id' => $packageRoleId,
            ':menu_key' => $menuKey
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $accessCache[$cacheKey] = parseAccessActions($row['access_actions'] ?? '');
        return $accessCache[$cacheKey];

    } catch (Exception $e) {
        $accessCache[$cacheKey] = [];
        return [];
    }
}

function canAccess($pageAccess, $actionCode)
{
    if (!is_array($pageAccess)) {
        return false;
    }

    $actionCode = (int)$actionCode;

    if ($actionCode <= 0) {
        return false;
    }

    return in_array($actionCode, $pageAccess, true);
}

function hasPermission($menuKey, $actionCode = 1)
{
    $pageAccess = getPageAccess($menuKey);
    return canAccess($pageAccess, (int)$actionCode);
}

function requirePageAccess($pageAccess, $actionCode = 1)
{
    if (!canAccess($pageAccess, (int)$actionCode)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

function requirePermission($menuKey, $actionCode = 1)
{
    if (!hasPermission($menuKey, (int)$actionCode)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

function requireApiPageAccess($menuKey, $actionCode = 1)
{
    if (!hasPermission($menuKey, (int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function requireApiPermission($menuKey, $actionCode = 1)
{
    requireApiPageAccess($menuKey, (int)$actionCode);
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
}
