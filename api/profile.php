<?php

require_once __DIR__ . '/../includes/config.php';

require_once BASE_PATH . 'includes/db.php';

require_once BASE_PATH . 'includes/security.php';

require_once BASE_PATH . 'includes/auth.php';



secureSessionStart();

header('Content-Type: application/json');

requireApiLogin();



$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');



switch ($action) {

    case 'get_context':

        getProfileContext($pdo);

        break;

    case 'get_profile':

        getProfile($pdo);

        break;



    case 'update_profile':

        verifyCsrfToken();

        updateProfile($pdo);

        break;



    case 'change_password':

        verifyCsrfToken();

        changePassword($pdo);

        break;



    default:

        jsonResponse(false, 'Invalid action.');

}



function profileUserId() {

    $userId = function_exists('currentUserId') ? (int)currentUserId() : 0;



    if ($userId <= 0 && isset($_SESSION['user_id'])) {

        $userId = (int)$_SESSION['user_id'];

    }



    if ($userId <= 0) {

        jsonResponse(false, 'Invalid user session.');

    }



    return $userId;

}




function profileActionCode($action)
{
    if (is_numeric($action)) {
        return (int)$action;
    }

    $map = [
        'view' => 1,
        'list' => 2,
        'add' => 3,
        'create' => 3,
        'edit' => 4,
        'update' => 4,
        'delete' => 5,
        'print' => 6,
        'export' => 7,
        'change_password' => 4
    ];

    $key = strtolower(trim((string)$action));

    return $map[$key] ?? 1;
}

function profileActionName($actionCode)
{
    $map = [
        1 => 'view',
        2 => 'list',
        3 => 'add',
        4 => 'edit',
        5 => 'delete',
        6 => 'print',
        7 => 'export'
    ];

    return $map[(int)$actionCode] ?? 'view';
}

function profilePermissionKeys()
{
    return ['profile', 'my_profile', 'user_profile'];
}

function currentRoleIdsForProfilePermission()
{
    static $roleIds = null;

    if ($roleIds !== null) {
        return $roleIds;
    }

    global $pdo;

    $roleIds = [];

    if (function_exists('currentRoleId')) {
        $roleId = (int)currentRoleId();
        if ($roleId > 0) {
            $roleIds[] = $roleId;
        }
    }

    foreach (['role_id', 'current_role_id', 'user_role_id'] as $sessionKey) {
        if (!empty($_SESSION[$sessionKey])) {
            $roleId = (int)$_SESSION[$sessionKey];
            if ($roleId > 0) {
                $roleIds[] = $roleId;
            }
        }
    }

    if (function_exists('currentUserId')) {
        $userId = (int)currentUserId();

        if ($userId > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $dbRoleId = (int)$stmt->fetchColumn();

                if ($dbRoleId > 0) {
                    $roleIds[] = $dbRoleId;
                }
            } catch (Throwable $e) {
                // Ignore fallback errors.
            }
        }
    }

    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

    /*
     * Add parent package role also.
     */
    if ($roleIds && isset($pdo) && $pdo instanceof PDO) {
        try {
            $holders = [];
            $params = [];

            foreach ($roleIds as $index => $roleId) {
                $key = ':role_' . $index;
                $holders[] = $key;
                $params[$key] = $roleId;
            }

            $stmt = $pdo->prepare("
                SELECT parent_role_id
                FROM roles
                WHERE id IN (" . implode(',', $holders) . ")
                AND parent_role_id IS NOT NULL
                AND parent_role_id > 0
            ");
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentRoleId) {
                $parentRoleId = (int)$parentRoleId;
                if ($parentRoleId > 0) {
                    $roleIds[] = $parentRoleId;
                }
            }
        } catch (Throwable $e) {
            // Ignore parent lookup errors.
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $roleIds))));
}

function profileRoleBaseMenuRowsExist($moduleKeys)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = currentRoleIdsForProfilePermission();

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':profile_menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':profile_role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = $roleId;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function profileRoleBasePermissionAllowed($moduleKeys, $action)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = currentRoleIdsForProfilePermission();
    $actionCode = profileActionCode($action);

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [
            ':action_code' => (string)$actionCode
        ];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':profile_allowed_menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':profile_allowed_role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = $roleId;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.status = 1
            AND sm.status = 1
            AND rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            AND FIND_IN_SET(:action_code, REPLACE(COALESCE(rba.access_actions, ''), ' ', '')) > 0
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function profileHasPermissionFallback($moduleKeys, $action)
{
    if (!function_exists('hasPermission')) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $actionCode = profileActionCode($action);
    $actionName = profileActionName($actionCode);

    foreach ($moduleKeys as $moduleKey) {
        if ($moduleKey === '') {
            continue;
        }

        try {
            if (hasPermission($moduleKey, $actionCode) || hasPermission($moduleKey, $actionName)) {
                return true;
            }
        } catch (Throwable $e) {
            // Continue with next key.
        }
    }

    return false;
}

function profileCan($action)
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    $keys = profilePermissionKeys();

    if (profileRoleBaseMenuRowsExist($keys)) {
        return profileRoleBasePermissionAllowed($keys, $action);
    }

    /*
     * My Profile is personal, so if no role row exists yet, keep it usable.
     * After you run SQL and save role permission, role_base_access will control it.
     */
    if (!profileRoleBaseMenuRowsExist($keys) && !function_exists('hasPermission')) {
        return true;
    }

    return profileHasPermissionFallback($keys, $action);
}

function requireProfilePermission($action = 1)
{
    if (!profileCan($action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function profilePermissionContext()
{
    return [
        'can_view' => profileCan(1) || profileCan(4),
        'can_edit' => profileCan(4),
        'can_change_password' => profileCan(4),
        'page_title' => 'My Profile',
        'page_note' => 'Profile controlled by role based permission.'
    ];
}

function getProfileContext(PDO $pdo)
{
    requireProfilePermission(1);

    jsonResponse(true, 'Profile context loaded.', [
        'context' => profilePermissionContext()
    ]);
}


function getProfile(PDO $pdo) {

    requireProfilePermission(1);

    $userId = profileUserId();



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

            u.user_type,

            u.status,

            u.created_at,

            r.role_name,

            b.business_name,

            br.branch_name

        FROM users u

        LEFT JOIN roles r ON r.id = u.role_id

        LEFT JOIN businesses b ON b.id = u.business_id

        LEFT JOIN branches br ON br.id = u.branch_id

        WHERE u.id = :user_id

        LIMIT 1

    ");

    $stmt->execute([':user_id' => $userId]);



    $user = $stmt->fetch(PDO::FETCH_ASSOC);



    if (!$user) {

        jsonResponse(false, 'User not found.');

    }



    jsonResponse(true, 'Profile loaded.', ['user' => $user, 'context' => profilePermissionContext()]);

}



function updateProfile(PDO $pdo) {

    requireProfilePermission(4);

    $userId = profileUserId();



    $name = cleanInput($_POST['name'] ?? '');

    $email = cleanInput($_POST['email'] ?? '');

    $mobile = cleanInput($_POST['mobile'] ?? '');



    if ($name === '') {

        jsonResponse(false, 'Please enter name.');

    }



    if (strlen($name) > 150) {

        jsonResponse(false, 'Name should be within 150 characters.');

    }



    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        jsonResponse(false, 'Please enter valid email.');

    }



    if (strlen($email) > 150) {

        jsonResponse(false, 'Email should be within 150 characters.');

    }



    if (strlen($mobile) > 20) {

        jsonResponse(false, 'Mobile should be within 20 characters.');

    }



    $stmt = $pdo->prepare("

        UPDATE users

        SET name = :name,

            email = :email,

            mobile = :mobile

        WHERE id = :user_id

    ");

    $stmt->execute([

        ':name' => $name,

        ':email' => $email,

        ':mobile' => $mobile,

        ':user_id' => $userId

    ]);



    logProfileActivity($pdo, $userId, 'profile', 'UPDATE', 'Profile details updated');



    jsonResponse(true, 'Profile updated successfully.');

}



function changePassword(PDO $pdo) {

    requireProfilePermission(4);

    $userId = profileUserId();



    $currentPassword = (string)($_POST['current_password'] ?? '');

    $newPassword = (string)($_POST['new_password'] ?? '');

    $confirmPassword = (string)($_POST['confirm_password'] ?? '');



    if ($currentPassword === '') {

        jsonResponse(false, 'Please enter current password.');

    }



    if (strlen($newPassword) < 6) {

        jsonResponse(false, 'New password must be minimum 6 characters.');

    }



    if ($newPassword !== $confirmPassword) {

        jsonResponse(false, 'Confirm password does not match.');

    }



    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id LIMIT 1");

    $stmt->execute([':user_id' => $userId]);

    $hash = (string)$stmt->fetchColumn();



    if ($hash === '') {

        jsonResponse(false, 'User password not found.');

    }



    if (!password_verify($currentPassword, $hash)) {

        jsonResponse(false, 'Current password is wrong.');

    }



    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);



    $update = $pdo->prepare("

        UPDATE users

        SET password = :password

        WHERE id = :user_id

    ");

    $update->execute([

        ':password' => $newHash,

        ':user_id' => $userId

    ]);



    logProfileActivity($pdo, $userId, 'profile', 'PASSWORD_CHANGE', 'User password changed');



    jsonResponse(true, 'Password changed successfully.');

}



function logProfileActivity(PDO $pdo, int $userId, string $moduleKey, string $actionType, string $description) {

    try {

        $stmt = $pdo->prepare("

            SELECT business_id, branch_id

            FROM users

            WHERE id = :user_id

            LIMIT 1

        ");

        $stmt->execute([':user_id' => $userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);



        if (!$user) {

            return;

        }



        $log = $pdo->prepare("

            INSERT INTO activity_logs

                (business_id, branch_id, user_id, module_key, action_type, description, ip_address)

            VALUES

                (:business_id, :branch_id, :user_id, :module_key, :action_type, :description, :ip_address)

        ");

        $log->execute([

            ':business_id' => $user['business_id'] ?: null,

            ':branch_id' => $user['branch_id'] ?: null,

            ':user_id' => $userId,

            ':module_key' => $moduleKey,

            ':action_type' => $actionType,

            ':description' => $description,

            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''

        ]);

    } catch (Exception $e) {

        // Profile save should not fail because of activity log.

    }

}
