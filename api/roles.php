<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

/** @var PDO $pdo */

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'list_roles':
        listRoles($pdo);
        break;

    case 'get_role':
        getRole($pdo);
        break;

    case 'get_permission_menus':
        getPermissionMenus($pdo);
        break;

    case 'save_role':
        verifyCsrfToken();
        saveRole($pdo);
        break;

    case 'delete_role':
        verifyCsrfToken();
        deleteRole($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function getWorkingBusinessBranch(PDO $pdo)
{
    if (isPlatformOwner()) {
        return ['business_id' => null, 'branch_id' => null];
    }

    return [
        'business_id' => currentBusinessId(),
        'branch_id' => currentBranchId()
    ];
}

function listRoles(PDO $pdo)
{
    if (isPlatformOwner()) {
        $stmt = $pdo->prepare("
            SELECT id, role_name, description, status, role_type, parent_role_id, is_locked
            FROM roles
            WHERE role_type = 1
            AND business_id IS NULL
            AND branch_id IS NULL
            ORDER BY id DESC
        ");
        $stmt->execute();
    } else {
        $scope = getWorkingBusinessBranch($pdo);

        $stmt = $pdo->prepare("
            SELECT id, role_name, description, status, role_type, parent_role_id, is_locked
            FROM roles
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND role_type = 2
            ORDER BY id DESC
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    }

    jsonResponse(true, 'Roles loaded.', ['roles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getRole(PDO $pdo)
{
    $roleId = (int)($_GET['role_id'] ?? 0);

    if ($roleId <= 0) {
        jsonResponse(false, 'Invalid role.');
    }

    if (isPlatformOwner()) {
        $stmt = $pdo->prepare("
            SELECT id, role_name, description, status, role_type, parent_role_id, is_locked
            FROM roles
            WHERE id = :role_id
            LIMIT 1
        ");
        $stmt->execute([':role_id' => $roleId]);
    } else {
        $scope = getWorkingBusinessBranch($pdo);

        $stmt = $pdo->prepare("
            SELECT id, role_name, description, status, role_type, parent_role_id, is_locked
            FROM roles
            WHERE id = :role_id
            AND business_id = :business_id
            AND branch_id = :branch_id
            AND role_type = 2
            LIMIT 1
        ");

        $stmt->execute([
            ':role_id' => $roleId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    }

    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        jsonResponse(false, 'Role not found.');
    }

    jsonResponse(true, 'Role loaded.', ['role' => $role]);
}

function getPermissionMenus(PDO $pdo)
{
    $roleId = (int)($_GET['role_id'] ?? 0);

    /*
        Locked branch roles are package based.
        Do not show menu permission edit list for locked Branch Admin roles.
    */
    if ($roleId > 0) {
        $role = getRoleRow($pdo, $roleId);

        if (!$role) {
            jsonResponse(false, 'Role not found.');
        }

        if (!isPlatformOwner() && (int)($role['is_locked'] ?? 0) === 1) {
            jsonResponse(true, 'This branch role is package controlled. Menu permissions are not editable.', [
                'menus' => [],
                'is_locked' => 1,
                'package_controlled' => 1
            ]);
        }
    }

    /*
        Platform owner edits Basic / Premium / Gold package roles.
        So show all active sidebar menus.
    */
    if (isPlatformOwner()) {
        $stmt = $pdo->prepare("
            SELECT
                sm.id,
                sm.parent_id,
                sm.menu_key,
                sm.menu_name,
                sm.menu_url,
                sm.icon_class,
                sm.sort_order,

                COALESCE(rmp.can_view, 0) AS can_view,
                COALESCE(rmp.can_add, 0) AS can_add,
                COALESCE(rmp.can_edit, 0) AS can_edit,
                COALESCE(rmp.can_delete, 0) AS can_delete,
                COALESCE(rmp.can_print, 0) AS can_print,
                COALESCE(rmp.can_export, 0) AS can_export,
                COALESCE(rmp.can_approve, 0) AS can_approve,
                COALESCE(rmp.can_convert, 0) AS can_convert,
                COALESCE(rmp.can_adjust, 0) AS can_adjust,
                COALESCE(rmp.can_ship, 0) AS can_ship,
                COALESCE(rmp.can_generate_invoice, 0) AS can_generate_invoice,

                1 AS allowed_can_view,
                1 AS allowed_can_add,
                1 AS allowed_can_edit,
                1 AS allowed_can_delete,
                1 AS allowed_can_print,
                1 AS allowed_can_export,
                1 AS allowed_can_approve,
                1 AS allowed_can_convert,
                1 AS allowed_can_adjust,
                1 AS allowed_can_ship,
                1 AS allowed_can_generate_invoice

            FROM sidebar_menus sm

            LEFT JOIN role_menu_permissions rmp
                ON rmp.menu_id = sm.id
                AND rmp.role_id = :role_id
                AND rmp.status = 1

            WHERE sm.status = 1

            ORDER BY
                COALESCE(sm.parent_id, 0),
                COALESCE(sm.sort_order, 9999) ASC,
                sm.id ASC
        ");

        $stmt->execute([
            ':role_id' => $roleId
        ]);

        jsonResponse(true, 'Menus loaded.', [
            'menus' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    /*
        Branch / business user Add Role modal:
        Menus must be listed based on the branch package role.
        Package role id is taken from current branch locked Branch Admin role / current user's parent_role_id.
    */
    $packageRoleId = getEffectivePackageRoleId($pdo);

    if ($packageRoleId <= 0) {
        jsonResponse(true, 'No package role assigned for this branch.', [
            'menus' => [],
            'package_role_id' => 0
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT
            sm.id,
            sm.parent_id,
            sm.menu_key,
            sm.menu_name,
            sm.menu_url,
            sm.icon_class,
            sm.sort_order,

            COALESCE(rmp.can_view, 0) AS can_view,
            COALESCE(rmp.can_add, 0) AS can_add,
            COALESCE(rmp.can_edit, 0) AS can_edit,
            COALESCE(rmp.can_delete, 0) AS can_delete,
            COALESCE(rmp.can_print, 0) AS can_print,
            COALESCE(rmp.can_export, 0) AS can_export,
            COALESCE(rmp.can_approve, 0) AS can_approve,
            COALESCE(rmp.can_convert, 0) AS can_convert,
            COALESCE(rmp.can_adjust, 0) AS can_adjust,
            COALESCE(rmp.can_ship, 0) AS can_ship,
            COALESCE(rmp.can_generate_invoice, 0) AS can_generate_invoice,

            CASE
                WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_view, 0)
                WHEN parent_package_perm.id IS NOT NULL THEN 1
                ELSE 0
            END AS allowed_can_view,

            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_add, 0) ELSE 0 END AS allowed_can_add,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_edit, 0) ELSE 0 END AS allowed_can_edit,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_delete, 0) ELSE 0 END AS allowed_can_delete,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_print, 0) ELSE 0 END AS allowed_can_print,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_export, 0) ELSE 0 END AS allowed_can_export,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_approve, 0) ELSE 0 END AS allowed_can_approve,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_convert, 0) ELSE 0 END AS allowed_can_convert,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_adjust, 0) ELSE 0 END AS allowed_can_adjust,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_ship, 0) ELSE 0 END AS allowed_can_ship,
            CASE WHEN package_perm.id IS NOT NULL THEN COALESCE(package_perm.can_generate_invoice, 0) ELSE 0 END AS allowed_can_generate_invoice

        FROM sidebar_menus sm

        LEFT JOIN role_menu_permissions package_perm
            ON package_perm.menu_id = sm.id
            AND package_perm.role_id = :package_role_id
            AND package_perm.status = 1

        LEFT JOIN sidebar_menus child_sm
            ON child_sm.parent_id = sm.id
            AND child_sm.status = 1

        LEFT JOIN role_menu_permissions parent_package_perm
            ON parent_package_perm.menu_id = child_sm.id
            AND parent_package_perm.role_id = :package_role_id_parent
            AND parent_package_perm.status = 1

        LEFT JOIN role_menu_permissions rmp
            ON rmp.menu_id = sm.id
            AND rmp.role_id = :role_id
            AND rmp.status = 1

        WHERE sm.status = 1
        AND (
            package_perm.id IS NOT NULL
            OR parent_package_perm.id IS NOT NULL
        )

        GROUP BY sm.id

        ORDER BY
            COALESCE(sm.parent_id, 0),
            COALESCE(sm.sort_order, 9999) ASC,
            sm.id ASC
    ");

    $stmt->execute([
        ':package_role_id' => $packageRoleId,
        ':package_role_id_parent' => $packageRoleId,
        ':role_id' => $roleId
    ]);

    jsonResponse(true, 'Menus loaded.', [
        'menus' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'package_role_id' => $packageRoleId
    ]);
}

function saveRole(PDO $pdo)
{
    $scope = getWorkingBusinessBranch($pdo);
    $businessId = $scope['business_id'];
    $branchId = $scope['branch_id'];

    $roleId = (int)($_POST['role_id'] ?? 0);
    $roleName = cleanInput($_POST['role_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    $permissions = $_POST['permissions'] ?? [];
    $menuIds = $_POST['menu_ids'] ?? [];

    if ($roleName === '') {
        jsonResponse(false, 'Please enter role name.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        $pdo->beginTransaction();

        if ($roleId > 0) {
            if (!isPlatformOwner() && isLockedRole($pdo, $roleId)) {
                jsonResponse(false, 'This role is package controlled. You cannot edit this role.');
            }

            if (!isPlatformOwner() && !hasPermission('roles', 'edit')) {
                jsonResponse(false, 'You do not have edit permission.');
            }

            updateRole($pdo, $businessId, $branchId, $roleId, $roleName, $description, $status);
        } else {
            if (!isPlatformOwner() && !hasPermission('roles', 'add')) {
                jsonResponse(false, 'You do not have add permission.');
            }

            $roleId = createRole($pdo, $businessId, $branchId, $roleName, $description, $status);
        }

        validatePermissionsWithinCurrentUserAccess($pdo, $menuIds, $permissions);
        deleteRolePermissions($pdo, $roleId);
        saveRolePermissions($pdo, $roleId, $menuIds, $permissions);

        $pdo->commit();
        jsonResponse(true, 'Role saved successfully.', ['role_id' => $roleId]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Role save failed.');
    }
}

function createRole(PDO $pdo, $businessId, $branchId, $roleName, $description, $status)
{
    if (isPlatformOwner()) {
        $duplicateStmt = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE role_type = 1
            AND business_id IS NULL
            AND branch_id IS NULL
            AND role_name = :role_name
            LIMIT 1
        ");
        $duplicateStmt->execute([':role_name' => $roleName]);

        if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Package role name already exists.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO roles
            (business_id, branch_id, role_type, parent_role_id, role_name, description, status, is_locked)
            VALUES
            (NULL, NULL, 1, NULL, :role_name, :description, :status, 0)
        ");
        $stmt->execute([
            ':role_name' => $roleName,
            ':description' => $description,
            ':status' => $status
        ]);

        return (int)$pdo->lastInsertId();
    }

    $duplicateStmt = $pdo->prepare("
        SELECT id
        FROM roles
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND role_type = 2
        AND role_name = :role_name
        LIMIT 1
    ");
    $duplicateStmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':role_name' => $roleName
    ]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Role name already exists.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO roles
        (business_id, branch_id, role_type, parent_role_id, role_name, description, status, is_locked)
        VALUES
        (:business_id, :branch_id, 2, NULL, :role_name, :description, :status, 0)
    ");
    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':role_name' => $roleName,
        ':description' => $description,
        ':status' => $status
    ]);

    return (int)$pdo->lastInsertId();
}

function updateRole(PDO $pdo, $businessId, $branchId, $roleId, $roleName, $description, $status)
{
    if (isPlatformOwner()) {
        $checkStmt = $pdo->prepare("SELECT id FROM roles WHERE id = :role_id LIMIT 1");
        $checkStmt->execute([':role_id' => $roleId]);

        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Role not found.');
        }

        $stmt = $pdo->prepare("
            UPDATE roles
            SET role_name = :role_name,
                description = :description,
                status = :status
            WHERE id = :role_id
        ");
        $stmt->execute([
            ':role_name' => $roleName,
            ':description' => $description,
            ':status' => $status,
            ':role_id' => $roleId
        ]);
        return;
    }

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM roles
        WHERE id = :role_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND role_type = 2
        LIMIT 1
    ");
    $checkStmt->execute([
        ':role_id' => $roleId,
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Role not found.');
    }

    $stmt = $pdo->prepare("
        UPDATE roles
        SET role_name = :role_name,
            description = :description,
            status = :status
        WHERE id = :role_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $stmt->execute([
        ':role_name' => $roleName,
        ':description' => $description,
        ':status' => $status,
        ':role_id' => $roleId,
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);
}

function deleteRole(PDO $pdo)
{
    $roleId = (int)($_POST['role_id'] ?? 0);

    if ($roleId <= 0) {
        jsonResponse(false, 'Invalid role.');
    }

    if (!isPlatformOwner() && isLockedRole($pdo, $roleId)) {
        jsonResponse(false, 'This role is package controlled. You cannot delete this role.');
    }

    if (!isPlatformOwner() && !hasPermission('roles', 'delete')) {
        jsonResponse(false, 'You do not have delete permission.');
    }

    $scope = getWorkingBusinessBranch($pdo);

    try {
        $pdo->beginTransaction();

        $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE role_id = :role_id LIMIT 1");
        $checkUserStmt->execute([':role_id' => $roleId]);

        if ($checkUserStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('This role is assigned to users. Cannot delete.');
        }

        deleteRolePermissions($pdo, $roleId);

        if (isPlatformOwner()) {
            $usedStmt = $pdo->prepare("SELECT id FROM roles WHERE parent_role_id = :role_id LIMIT 1");
            $usedStmt->execute([':role_id' => $roleId]);

            if ($usedStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('This package role is assigned to customers. Cannot delete.');
            }

            $deleteRoleStmt = $pdo->prepare("DELETE FROM roles WHERE id = :role_id");
            $deleteRoleStmt->execute([':role_id' => $roleId]);
        } else {
            $deleteRoleStmt = $pdo->prepare("
                DELETE FROM roles
                WHERE id = :role_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                AND role_type = 2
            ");
            $deleteRoleStmt->execute([
                ':role_id' => $roleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        $pdo->commit();
        jsonResponse(true, 'Role deleted successfully.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Role delete failed.');
    }
}

function deleteRolePermissions(PDO $pdo, $roleId)
{
    $stmt = $pdo->prepare("DELETE FROM role_menu_permissions WHERE role_id = :role_id");
    $stmt->execute([':role_id' => $roleId]);
}

function saveRolePermissions(PDO $pdo, $roleId, $menuIds, $permissions)
{
    if (empty($menuIds)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO role_menu_permissions
        (role_id, menu_id, can_view, can_add, can_edit, can_delete, can_print, can_export, can_approve, can_convert, can_adjust, can_ship, can_generate_invoice, status)
        VALUES
        (:role_id, :menu_id, :can_view, :can_add, :can_edit, :can_delete, :can_print, :can_export, :can_approve, :can_convert, :can_adjust, :can_ship, :can_generate_invoice, 1)
    ");

    foreach ($menuIds as $menuId) {
        $menuId = (int)$menuId;
        $p = $permissions[$menuId] ?? [];

        $canView = isset($p['can_view']) ? 1 : 0;
        $canAdd = isset($p['can_add']) ? 1 : 0;
        $canEdit = isset($p['can_edit']) ? 1 : 0;
        $canDelete = isset($p['can_delete']) ? 1 : 0;
        $canPrint = isset($p['can_print']) ? 1 : 0;
        $canExport = isset($p['can_export']) ? 1 : 0;
        $canApprove = isset($p['can_approve']) ? 1 : 0;
        $canConvert = isset($p['can_convert']) ? 1 : 0;
        $canAdjust = isset($p['can_adjust']) ? 1 : 0;
        $canShip = isset($p['can_ship']) ? 1 : 0;
        $canGenerateInvoice = isset($p['can_generate_invoice']) ? 1 : 0;

        if ($canAdd || $canEdit || $canDelete || $canPrint || $canExport || $canApprove || $canConvert || $canAdjust || $canShip || $canGenerateInvoice) {
            $canView = 1;
        }

        if (!$canView && !$canAdd && !$canEdit && !$canDelete && !$canPrint && !$canExport && !$canApprove && !$canConvert && !$canAdjust && !$canShip && !$canGenerateInvoice) {
            continue;
        }

        $stmt->execute([
            ':role_id' => $roleId,
            ':menu_id' => $menuId,
            ':can_view' => $canView,
            ':can_add' => $canAdd,
            ':can_edit' => $canEdit,
            ':can_delete' => $canDelete,
            ':can_print' => $canPrint,
            ':can_export' => $canExport,
            ':can_approve' => $canApprove,
            ':can_convert' => $canConvert,
            ':can_adjust' => $canAdjust,
            ':can_ship' => $canShip,
            ':can_generate_invoice' => $canGenerateInvoice
        ]);
    }
}

function validatePermissionsWithinCurrentUserAccess(PDO $pdo, $menuIds, $permissions)
{
    if (isPlatformOwner() || empty($menuIds)) {
        return;
    }

    $packageRoleId = getEffectivePackageRoleId($pdo);

    if ($packageRoleId <= 0) {
        jsonResponse(false, 'Package role not assigned for this branch.');
    }

    $stmt = $pdo->prepare("
        SELECT
            sm.id AS menu_id,
            COALESCE(package.can_view, 0) AS can_view,
            COALESCE(package.can_add, 0) AS can_add,
            COALESCE(package.can_edit, 0) AS can_edit,
            COALESCE(package.can_delete, 0) AS can_delete,
            COALESCE(package.can_print, 0) AS can_print,
            COALESCE(package.can_export, 0) AS can_export,
            COALESCE(package.can_approve, 0) AS can_approve,
            COALESCE(package.can_convert, 0) AS can_convert,
            COALESCE(package.can_adjust, 0) AS can_adjust,
            COALESCE(package.can_ship, 0) AS can_ship,
            COALESCE(package.can_generate_invoice, 0) AS can_generate_invoice
        FROM sidebar_menus sm
        INNER JOIN role_menu_permissions package
            ON package.menu_id = sm.id
            AND package.role_id = :package_role_id
            AND package.status = 1
        WHERE sm.status = 1
    ");

    $stmt->execute([
        ':package_role_id' => $packageRoleId
    ]);

    $allowedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allowed = [];

    foreach ($allowedRows as $row) {
        $allowed[(int)$row['menu_id']] = $row;
    }

    $fields = [
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
        'can_print',
        'can_export',
        'can_approve',
        'can_convert',
        'can_adjust',
        'can_ship',
        'can_generate_invoice'
    ];

    foreach ($menuIds as $menuId) {
        $menuId = (int)$menuId;
        $posted = $permissions[$menuId] ?? [];

        foreach ($fields as $field) {
            $requested = isset($posted[$field]) ? 1 : 0;

            if ($requested === 1) {
                if (!isset($allowed[$menuId]) || (int)$allowed[$menuId][$field] !== 1) {
                    $menuName = getMenuNameById($pdo, $menuId);
                    jsonResponse(false, 'Package access not allowed for ' . $menuName . ' - ' . $field . '. Enable this permission in the package role first.');
                }
            }
        }
    }
}


function isLockedRole(PDO $pdo, $roleId)
{
    $stmt = $pdo->prepare("SELECT is_locked FROM roles WHERE id = :role_id LIMIT 1");
    $stmt->execute([':role_id' => $roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    return $role && (int)$role['is_locked'] === 1;
}

function getEffectivePackageRoleId(PDO $pdo)
{
    /*
        For branch based roles page:
        Menu permission list must come from the selected branch package.

        Priority:
        1. Current logged-in role parent_role_id.
        2. Locked Branch Admin role for current business + current branch.

        Note:
        Branch Admin-created staff roles must keep parent_role_id NULL.
        Package is always resolved from locked Branch Admin role.
    */

    $currentRoleId = currentRoleId();

    if ($currentRoleId > 0) {
        $stmt = $pdo->prepare("
            SELECT parent_role_id
            FROM roles
            WHERE id = :role_id
            AND status = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':role_id' => $currentRoleId
        ]);

        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        $parentRoleId = (int)($role['parent_role_id'] ?? 0);

        if ($parentRoleId > 0) {
            return $parentRoleId;
        }
    }

    $businessId = currentBusinessId();
    $branchId = currentBranchId();

    if ($businessId > 0 && $branchId > 0) {
        $stmt = $pdo->prepare("
            SELECT parent_role_id
            FROM roles
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND role_type = 2
            AND is_locked = 1
            AND parent_role_id IS NOT NULL
            AND parent_role_id > 0
            AND status = 1
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt->execute([
            ':business_id' => $businessId,
            ':branch_id' => $branchId
        ]);

        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        $parentRoleId = (int)($role['parent_role_id'] ?? 0);

        if ($parentRoleId > 0) {
            return $parentRoleId;
        }

    }

    return 0;
}

function getCurrentPackageRoleId(PDO $pdo)
{
    return getEffectivePackageRoleId($pdo);
}


function getMenuNameById(PDO $pdo, $menuId)
{
    $stmt = $pdo->prepare("SELECT menu_name FROM sidebar_menus WHERE id = :menu_id LIMIT 1");
    $stmt->execute([':menu_id' => (int)$menuId]);
    $name = $stmt->fetchColumn();

    return $name ? $name : ('Menu #' . (int)$menuId);
}


function getRoleRow(PDO $pdo, $roleId)
{
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = :role_id LIMIT 1");
    $stmt->execute([':role_id' => $roleId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
