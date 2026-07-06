<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');

requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_page_context':
        getPageContext($pdo);
        break;

    case 'list_roles':
        listRoles($pdo);
        break;

    case 'get_role':
        getRole($pdo);
        break;

    case 'save_role':
        verifyCsrfToken();
        saveRole($pdo);
        break;

    case 'delete_role':
        verifyCsrfToken();
        deleteRole($pdo);
        break;

    case 'get_permission_menus':
        getPermissionMenus($pdo);
        break;

    case 'get_menu_count':
        getMenuCount($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function rolesPageCan($actionCode)
{
    return hasPermission('roles', (int)$actionCode);
}

function getPageContext(PDO $pdo)
{
    if (!rolesPageCan(1)) {
        jsonResponse(false, 'Permission denied.');
    }

    $isPlatform = isPlatformOwner();

    $context = [
        'user_type' => currentUserType(),
        'is_platform_owner' => $isPlatform,
        'can_view' => rolesPageCan(1),
        'can_add' => rolesPageCan(3),
        'can_edit' => rolesPageCan(4),
        'can_delete' => rolesPageCan(5),
        'add_button_label' => $isPlatform ? 'Add Package Role' : 'Add Role',
        'add_modal_title' => $isPlatform ? 'Add Package Role' : 'Add Role',
        'edit_modal_title' => $isPlatform ? 'Edit Package Role' : 'Edit Role',
        'page_note' => $isPlatform
            ? 'Create package roles and control package permissions.'
            : 'Create business roles inside your assigned package permission.',
        'modal_note' => $isPlatform
            ? 'Package Control: You control what menus/actions are available for this package.'
            : 'Package Limited: You can create roles only within the package permission assigned during customer registration.',
        'permission_source_note' => $isPlatform
            ? 'Showing all actions from sidebar_menus.allowed_actions.'
            : 'Showing only permissions allowed in your selected package.'
    ];

    jsonResponse(true, 'Page context loaded.', [
        'context' => $context
    ]);
}

function roleAccessScopeSql()
{
    if (isPlatformOwner()) {
        return [
            'sql' => " role_type = 1 AND business_id IS NULL AND branch_id IS NULL ",
            'params' => []
        ];
    }

    return [
        'sql' => " role_type = 2 AND business_id = :business_id AND branch_id = :branch_id ",
        'params' => [
            ':business_id' => currentBusinessId(),
            ':branch_id' => currentBranchId()
        ]
    ];
}

function businessPackageRoleIdForNewRole(PDO $pdo)
{
    $user = currentLiveUser();
    $packageRoleId = (int)($user['parent_role_id'] ?? 0);

    if ($packageRoleId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM roles
        WHERE id = :id
        AND role_type = 1
        AND business_id IS NULL
        AND branch_id IS NULL
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $packageRoleId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ? $packageRoleId : 0;
}

function listRoles(PDO $pdo)
{
    if (!rolesPageCan(1)) {
        jsonResponse(false, 'Permission denied.');
    }

    try {
        $scope = roleAccessScopeSql();

        $stmt = $pdo->prepare("
            SELECT
                id,
                role_name,
                description,
                status,
                is_locked,
                role_type,
                parent_role_id,
                created_at
            FROM roles
            WHERE {$scope['sql']}
            ORDER BY id ASC
        ");

        $stmt->execute($scope['params']);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = rolesPageCan(4);
        $canDelete = rolesPageCan(5);

        $stats = [
            'total_roles' => count($roles),
            'active_roles' => 0,
            'inactive_roles' => 0
        ];

        foreach ($roles as &$role) {
            if ((int)$role['status'] === 1) {
                $stats['active_roles']++;
            } else {
                $stats['inactive_roles']++;
            }

            $isLocked = (int)($role['is_locked'] ?? 0) === 1;

            $role['can_edit'] = (!$isLocked && $canEdit);
            $role['can_delete'] = (!$isLocked && $canDelete);
        }
        unset($role);

        jsonResponse(true, 'Roles loaded.', [
            'roles' => $roles,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load roles.');
    }
}

function getRole(PDO $pdo)
{
    if (!rolesPageCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    $roleId = (int)($_GET['role_id'] ?? 0);

    if ($roleId <= 0) {
        jsonResponse(false, 'Invalid role.');
    }

    try {
        $scope = roleAccessScopeSql();

        $stmt = $pdo->prepare("
            SELECT
                id,
                role_name,
                description,
                status,
                is_locked,
                role_type,
                parent_role_id
            FROM roles
            WHERE id = :role_id
            AND {$scope['sql']}
            LIMIT 1
        ");

        $params = array_merge([':role_id' => $roleId], $scope['params']);
        $stmt->execute($params);

        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            jsonResponse(false, 'Role not found.');
        }

        if ((int)($role['is_locked'] ?? 0) === 1) {
            jsonResponse(false, 'Locked role cannot be edited.');
        }

        jsonResponse(true, 'Role loaded.', [
            'role' => $role
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load role.');
    }
}

function saveRole(PDO $pdo)
{
    $roleId = (int)($_POST['role_id'] ?? 0);

    if ($roleId > 0 && !rolesPageCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    if ($roleId <= 0 && !rolesPageCan(3)) {
        jsonResponse(false, 'Permission denied.');
    }

    $roleName = cleanInput($_POST['role_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    $permissions = $_POST['permissions'] ?? [];

    if ($roleName === '') {
        jsonResponse(false, 'Please enter role name.');
    }

    $status = $status === 1 ? 1 : 2;

    try {
        $pdo->beginTransaction();

        if ($roleId > 0) {
            $scope = roleAccessScopeSql();

            $checkStmt = $pdo->prepare("
                SELECT id, is_locked
                FROM roles
                WHERE id = :role_id
                AND {$scope['sql']}
                LIMIT 1
            ");

            $params = array_merge([':role_id' => $roleId], $scope['params']);
            $checkStmt->execute($params);

            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new Exception('Role not found.');
            }

            if ((int)($existing['is_locked'] ?? 0) === 1) {
                throw new Exception('Locked role cannot be edited.');
            }

            $updateStmt = $pdo->prepare("
                UPDATE roles
                SET
                    role_name = :role_name,
                    description = :description,
                    status = :status
                WHERE id = :role_id
                LIMIT 1
            ");

            $updateStmt->execute([
                ':role_name' => $roleName,
                ':description' => $description,
                ':status' => $status,
                ':role_id' => $roleId
            ]);

        } else {
            if (isPlatformOwner()) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO roles
                    (
                        business_id,
                        branch_id,
                        role_type,
                        parent_role_id,
                        role_name,
                        description,
                        status,
                        is_locked
                    )
                    VALUES
                    (
                        NULL,
                        NULL,
                        1,
                        NULL,
                        :role_name,
                        :description,
                        :status,
                        0
                    )
                ");

                $insertStmt->execute([
                    ':role_name' => $roleName,
                    ':description' => $description,
                    ':status' => $status
                ]);

            } else {
                $packageRoleId = businessPackageRoleIdForNewRole($pdo);

                if ($packageRoleId <= 0) {
                    throw new Exception('Package role not found for this business. Please check customer registration package.');
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO roles
                    (
                        business_id,
                        branch_id,
                        role_type,
                        parent_role_id,
                        role_name,
                        description,
                        status,
                        is_locked
                    )
                    VALUES
                    (
                        :business_id,
                        :branch_id,
                        2,
                        :parent_role_id,
                        :role_name,
                        :description,
                        :status,
                        0
                    )
                ");

                $insertStmt->execute([
                    ':business_id' => currentBusinessId(),
                    ':branch_id' => currentBranchId(),
                    ':parent_role_id' => $packageRoleId,
                    ':role_name' => $roleName,
                    ':description' => $description,
                    ':status' => $status
                ]);
            }

            $roleId = (int)$pdo->lastInsertId();
        }

        saveRolePermissions($pdo, $roleId, $permissions);

        $pdo->commit();

        jsonResponse(true, 'Role saved successfully.', [
            'role_id' => $roleId
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Unable to save role.');
    }
}

function deleteRole(PDO $pdo)
{
    if (!rolesPageCan(5)) {
        jsonResponse(false, 'Permission denied.');
    }

    $roleId = (int)($_POST['role_id'] ?? 0);

    if ($roleId <= 0) {
        jsonResponse(false, 'Invalid role.');
    }

    try {
        $scope = roleAccessScopeSql();

        $checkStmt = $pdo->prepare("
            SELECT id, is_locked
            FROM roles
            WHERE id = :role_id
            AND {$scope['sql']}
            LIMIT 1
        ");

        $params = array_merge([':role_id' => $roleId], $scope['params']);
        $checkStmt->execute($params);

        $role = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            jsonResponse(false, 'Role not found.');
        }

        if ((int)$role['is_locked'] === 1) {
            jsonResponse(false, 'Locked role cannot be deleted.');
        }

        $userStmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE role_id = :role_id
        ");

        $userStmt->execute([
            ':role_id' => $roleId
        ]);

        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($userRow['total'] ?? 0) > 0) {
            jsonResponse(false, 'This role is used by users. Cannot delete.');
        }

        $pdo->beginTransaction();

        $deleteAccess = $pdo->prepare("
            DELETE FROM role_base_access
            WHERE role_id = :role_id
        ");

        $deleteAccess->execute([
            ':role_id' => $roleId
        ]);

        $deleteRole = $pdo->prepare("
            DELETE FROM roles
            WHERE id = :role_id
            LIMIT 1
        ");

        $deleteRole->execute([
            ':role_id' => $roleId
        ]);

        $pdo->commit();

        jsonResponse(true, 'Role deleted successfully.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Unable to delete role.');
    }
}

function getPermissionMenus(PDO $pdo)
{
    $roleId = (int)($_GET['role_id'] ?? 0);

    if ($roleId > 0 && !rolesPageCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    if ($roleId <= 0 && !rolesPageCan(3)) {
        jsonResponse(false, 'Permission denied.');
    }

    try {
        if (isPlatformOwner()) {
            if ($roleId > 0) {
                $stmt = $pdo->prepare("
                    SELECT
                        sm.id,
                        sm.parent_id,
                        sm.menu_key,
                        sm.menu_name,
                        sm.menu_url,
                        sm.allowed_actions,
                        sm.sort_order,
                        rba.access_actions
                    FROM sidebar_menus sm
                    LEFT JOIN role_base_access rba
                        ON rba.menu_id = sm.id
                        AND rba.role_id = :role_id
                        AND rba.status = 1
                    WHERE sm.status = 1
                    AND sm.menu_for IN ('customer', 'both')
                    ORDER BY COALESCE(sm.parent_id, 0), sm.sort_order ASC, sm.id ASC
                ");

                $stmt->execute([
                    ':role_id' => $roleId
                ]);

            } else {
                $stmt = $pdo->prepare("
                    SELECT
                        sm.id,
                        sm.parent_id,
                        sm.menu_key,
                        sm.menu_name,
                        sm.menu_url,
                        sm.allowed_actions,
                        sm.sort_order,
                        '' AS access_actions
                    FROM sidebar_menus sm
                    WHERE sm.status = 1
                    AND sm.menu_for IN ('customer', 'both')
                    ORDER BY COALESCE(sm.parent_id, 0), sm.sort_order ASC, sm.id ASC
                ");

                $stmt->execute();
            }

            $menus = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['allowed_action_codes'] = parseAccessActions($row['allowed_actions'] ?? '');
                $row['selected_action_codes'] = parseAccessActions($row['access_actions'] ?? '');
                $menus[] = $row;
            }

            jsonResponse(true, 'Permission menus loaded.', [
                'menus' => $menus
            ]);
        }

        $packageRoleId = businessPackageRoleIdForNewRole($pdo);

        

        if ($packageRoleId <= 0) {
            jsonResponse(false, 'Package role not found for this business.');
        }

        if ($roleId > 0) {
            $stmt = $pdo->prepare("
                SELECT
                    sm.id,
                    sm.parent_id,
                    sm.menu_key,
                    sm.menu_name,
                    sm.menu_url,
                    package_access.access_actions AS allowed_actions,
                    sm.sort_order,
                    business_access.access_actions
                FROM sidebar_menus sm
                INNER JOIN role_base_access package_access
                    ON package_access.menu_id = sm.id
                    AND package_access.role_id = :package_role_id
                    AND package_access.status = 1
                    AND package_access.access_actions <> ''
                LEFT JOIN role_base_access business_access
                    ON business_access.menu_id = sm.id
                    AND business_access.role_id = :role_id
                    AND business_access.status = 1
                WHERE sm.status = 1
                AND sm.menu_for IN ('customer', 'both')
                ORDER BY COALESCE(sm.parent_id, 0), sm.sort_order ASC, sm.id ASC
            ");

            $stmt->execute([
                ':package_role_id' => $packageRoleId,
                ':role_id' => $roleId
            ]);

        } else {
            $stmt = $pdo->prepare("
                SELECT
                    sm.id,
                    sm.parent_id,
                    sm.menu_key,
                    sm.menu_name,
                    sm.menu_url,
                    package_access.access_actions AS allowed_actions,
                    sm.sort_order,
                    '' AS access_actions
                FROM sidebar_menus sm
                INNER JOIN role_base_access package_access
                    ON package_access.menu_id = sm.id
                    AND package_access.role_id = :package_role_id
                    AND package_access.status = 1
                    AND package_access.access_actions <> ''
                WHERE sm.status = 1
                AND sm.menu_for IN ('customer', 'both')
                ORDER BY COALESCE(sm.parent_id, 0), sm.sort_order ASC, sm.id ASC
            ");

            $stmt->execute([
                ':package_role_id' => $packageRoleId
            ]);
        }

        $menus = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['allowed_action_codes'] = parseAccessActions($row['allowed_actions'] ?? '');
            $row['selected_action_codes'] = parseAccessActions($row['access_actions'] ?? '');
            $menus[] = $row;
        }

        jsonResponse(true, 'Package limited permission menus loaded.', [
            'menus' => $menus
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load permission menus.');
    }
}

function getMenuCount(PDO $pdo)
{
    if (!rolesPageCan(1)) {
        jsonResponse(false, 'Permission denied.');
    }

    try {
        if (isPlatformOwner()) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM sidebar_menus
                WHERE status = 1
                AND is_sidebar = 1
            ");

            $stmt->execute();

        } else {
            $packageRoleId = businessPackageRoleIdForNewRole($pdo);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM sidebar_menus sm
                INNER JOIN role_base_access package_access
                    ON package_access.menu_id = sm.id
                    AND package_access.role_id = :package_role_id
                    AND package_access.status = 1
                    AND package_access.access_actions <> ''
                WHERE sm.status = 1
                AND sm.is_sidebar = 1
                AND sm.menu_for IN ('customer', 'both')
            ");

            $stmt->execute([
                ':package_role_id' => $packageRoleId
            ]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Menu count loaded.', [
            'menu_count' => (int)($row['total'] ?? 0)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, 'Unable to load menu count.');
    }
}

function saveRolePermissions(PDO $pdo, $roleId, $permissions)
{
    $roleId = (int)$roleId;

    if ($roleId <= 0) {
        return;
    }

    if (!is_array($permissions)) {
        $permissions = [];
    }

    $existingStmt = $pdo->prepare("
        SELECT menu_id
        FROM role_base_access
        WHERE role_id = :role_id
    ");

    $existingStmt->execute([
        ':role_id' => $roleId
    ]);

    $existingMenuIds = [];

    while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingMenuIds[] = (int)$row['menu_id'];
    }

    if (isPlatformOwner()) {
        $allowedStmt = $pdo->prepare("
            SELECT id, allowed_actions
            FROM sidebar_menus
            WHERE id = :menu_id
            AND status = 1
            AND menu_for IN ('customer', 'both')
            LIMIT 1
        ");
    } else {
        $packageRoleId = businessPackageRoleIdForNewRole($pdo);

        if ($packageRoleId <= 0) {
            throw new Exception('Package role not found for this business.');
        }

        $allowedStmt = $pdo->prepare("
            SELECT
                sm.id,
                package_access.access_actions AS allowed_actions
            FROM sidebar_menus sm
            INNER JOIN role_base_access package_access
                ON package_access.menu_id = sm.id
                AND package_access.role_id = :package_role_id
                AND package_access.status = 1
                AND package_access.access_actions <> ''
            WHERE sm.id = :menu_id
            AND sm.status = 1
            AND sm.menu_for IN ('customer', 'both')
            LIMIT 1
        ");
    }

    $upsertStmt = $pdo->prepare("
        INSERT INTO role_base_access
        (
            role_id,
            menu_id,
            access_actions,
            status
        )
        VALUES
        (
            :role_id,
            :menu_id,
            :access_actions,
            1
        )
        ON DUPLICATE KEY UPDATE
            access_actions = VALUES(access_actions),
            status = 1,
            updated_at = NOW()
    ");

    $selectedMenuIds = [];

    foreach ($permissions as $menuId => $actions) {
        $menuId = (int)$menuId;

        if ($menuId <= 0) {
            continue;
        }

        if (isPlatformOwner()) {
            $allowedStmt->execute([
                ':menu_id' => $menuId
            ]);
        } else {
            $allowedStmt->execute([
                ':package_role_id' => $packageRoleId,
                ':menu_id' => $menuId
            ]);
        }

        $menu = $allowedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$menu) {
            continue;
        }

        $allowedActions = parseAccessActions($menu['allowed_actions'] ?? '');
        $selectedActions = parseAccessActions($actions);

        $finalActions = array_values(array_intersect($selectedActions, $allowedActions));
        sort($finalActions, SORT_NUMERIC);

        if (empty($finalActions)) {
            continue;
        }

        $accessActions = implode(',', $finalActions);

        $upsertStmt->execute([
            ':role_id' => $roleId,
            ':menu_id' => $menuId,
            ':access_actions' => $accessActions
        ]);

        $selectedMenuIds[] = $menuId;
    }

    $uncheckedMenuIds = array_values(array_diff($existingMenuIds, $selectedMenuIds));

    if (!empty($uncheckedMenuIds)) {
        $placeholders = implode(',', array_fill(0, count($uncheckedMenuIds), '?'));

        $disableStmt = $pdo->prepare("
            UPDATE role_base_access
            SET
                access_actions = '',
                status = 2,
                updated_at = NOW()
            WHERE role_id = ?
            AND menu_id IN ($placeholders)
        ");

        $params = array_merge([$roleId], $uncheckedMenuIds);
        $disableStmt->execute($params);
    }
}
