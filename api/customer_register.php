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
    case 'list_registrations':
    case 'list_customers':
        listRegistrations($pdo);
        break;

    case 'get_package_roles':
        getPackageRoles($pdo);
        break;

    case 'get_existing_businesses':
        getExistingBusinesses($pdo);
        break;

    case 'get_main_branches':
        getMainBranches($pdo);
        break;

    case 'get_business_branches':
        getBusinessBranches($pdo);
        break;

    case 'get_child_branches':
        getChildBranches($pdo);
        break;

    case 'get_registration':
        getRegistrationForEdit($pdo);
        break;

    case 'save_registration':
    case 'register_customer':
        verifyCsrfToken();
        saveRegistration($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function requirePlatformOwnerAccess()
{
    if (!isPlatformOwner()) {
        jsonResponse(false, 'Only platform owner can access customer registration.');
    }
}

function listRegistrations(PDO $pdo)
{
    requirePlatformOwnerAccess();

    try {
        $stmt = $pdo->prepare("\n            SELECT\n                b.id AS business_id,\n                b.business_code,\n                b.business_name,\n                b.owner_name,\n                b.mobile,\n                b.email,\n                b.status AS business_status,\n                b.created_at,\n\n                br.id AS branch_id,\n                br.branch_name,\n                br.branch_code,\n                br.approval_status,\n                br.status AS branch_status,\n\n                u.id AS user_id,\n                u.name AS user_name,\n                u.username,\n                u.status AS user_status,\n\n                r.id AS role_id,\n                r.role_name,\n                r.parent_role_id,\n                r.is_locked,\n                pr.role_name AS package_role_name,\n\n                (\n                    SELECT COUNT(*)\n                    FROM branches cb\n                    WHERE cb.business_id = b.id\n                    AND cb.parent_branch_id = br.id\n                    AND cb.approval_status = 1\n                    AND cb.status = 1\n                ) AS child_branch_count\n\n            FROM businesses b\n            LEFT JOIN branches br\n                ON br.id = b.default_branch_id\n            LEFT JOIN users u\n                ON u.business_id = b.id\n                AND u.branch_id = br.id\n                AND u.user_type = 'business_user'\n            LEFT JOIN roles r\n                ON r.id = u.role_id\n            LEFT JOIN roles pr\n                ON pr.id = r.parent_role_id\n            ORDER BY b.id DESC\n            LIMIT 500\n        ");

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'total_businesses' => 0,
            'active_businesses' => 0,
            'approved_branches' => 0,
            'business_users' => 0
        ];

        $seenBusinesses = [];
        $seenUsers = [];

        foreach ($rows as $row) {
            $businessId = (int)$row['business_id'];
            $userId = (int)($row['user_id'] ?? 0);

            if (!isset($seenBusinesses[$businessId])) {
                $seenBusinesses[$businessId] = true;
                $stats['total_businesses']++;

                if ((int)$row['business_status'] === 1) {
                    $stats['active_businesses']++;
                }

                if ((int)($row['approval_status'] ?? 0) === 1) {
                    $stats['approved_branches']++;
                }
            }

            if ($userId > 0 && !isset($seenUsers[$userId])) {
                $seenUsers[$userId] = true;
                $stats['business_users']++;
            }
        }

        jsonResponse(true, 'Registrations loaded.', [
            'registrations' => $rows,
            'customers' => $rows,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load registrations.');
    }
}

function getPackageRoles(PDO $pdo)
{
    requirePlatformOwnerAccess();

    try {
        $stmt = $pdo->prepare("\n            SELECT id, role_name, description, status\n            FROM roles\n            WHERE role_type = 1\n            AND business_id IS NULL\n            AND branch_id IS NULL\n            AND status = 1\n            ORDER BY id ASC\n        ");

        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Package roles loaded.', [
            'package_roles' => $roles,
            'roles' => $roles
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load package roles.');
    }
}

function getExistingBusinesses(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $stmt = $pdo->prepare("\n        SELECT\n            b.id,\n            b.business_name,\n            b.owner_name,\n            b.mobile,\n            b.email,\n            b.default_branch_id,\n            br.branch_name AS main_branch_name\n        FROM businesses b\n        LEFT JOIN branches br ON br.id = b.default_branch_id\n        WHERE b.status = 1\n        ORDER BY b.business_name ASC\n    ");

    $stmt->execute();

    jsonResponse(true, 'Businesses loaded.', [
        'businesses' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getMainBranches(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $businessId = (int)($_GET['business_id'] ?? 0);

    if ($businessId <= 0) {
        jsonResponse(false, 'Invalid business.');
    }

    $stmt = $pdo->prepare("\n        SELECT\n            id,\n            branch_name,\n            branch_code\n        FROM branches\n        WHERE business_id = :business_id\n        AND approval_status = 1\n        AND status = 1\n        AND (parent_branch_id IS NULL OR parent_branch_id = 0)\n        ORDER BY id ASC\n    ");

    $stmt->execute([
        ':business_id' => $businessId
    ]);

    jsonResponse(true, 'Main branches loaded.', [
        'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}


function getBusinessBranches(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $businessId = (int)($_GET['business_id'] ?? 0);

    if ($businessId <= 0) {
        jsonResponse(false, 'Invalid business.');
    }

    $stmt = $pdo->prepare("
        SELECT
            br.id,
            br.branch_name,
            br.branch_code,
            br.parent_branch_id,
            parent_br.branch_name AS parent_branch_name
        FROM branches br
        LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id
        WHERE br.business_id = :business_id
        AND br.approval_status = 1
        AND br.status = 1
        ORDER BY
            CASE WHEN br.parent_branch_id IS NULL OR br.parent_branch_id = 0 THEN 0 ELSE 1 END,
            br.id ASC
    ");

    $stmt->execute([
        ':business_id' => $businessId
    ]);

    jsonResponse(true, 'Branches loaded.', [
        'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getChildBranches(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $businessId = (int)($_GET['business_id'] ?? 0);
    $parentBranchId = (int)($_GET['parent_branch_id'] ?? 0);

    if ($businessId <= 0 || $parentBranchId <= 0) {
        jsonResponse(false, 'Invalid business or main branch.');
    }

    $stmt = $pdo->prepare("\n        SELECT\n            br.id,\n            br.branch_name,\n            br.branch_code,\n            br.mobile,\n            br.email,\n            br.city,\n            br.state,\n            br.pincode,\n            br.approval_status,\n            br.status,\n            br.requested_at,\n            br.approved_at,\n            u.name AS login_name,\n            u.username,\n            r.role_name,\n            pr.role_name AS package_role_name\n        FROM branches br\n        LEFT JOIN users u\n            ON u.business_id = br.business_id\n            AND u.branch_id = br.id\n            AND u.user_type = 'business_user'\n        LEFT JOIN roles r ON r.id = u.role_id\n        LEFT JOIN roles pr ON pr.id = r.parent_role_id\n        WHERE br.business_id = :business_id\n        AND br.parent_branch_id = :parent_branch_id\n        ORDER BY br.id DESC\n    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':parent_branch_id' => $parentBranchId
    ]);

    jsonResponse(true, 'Branches loaded.', [
        'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}


function getRegistrationForEdit(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $businessId = (int)($_GET['business_id'] ?? 0);
    $branchId = (int)($_GET['branch_id'] ?? 0);

    if ($businessId <= 0) {
        jsonResponse(false, 'Invalid registration.');
    }

    $branchCondition = '';
    $params = [':business_id' => $businessId];

    if ($branchId > 0) {
        $branchCondition = ' AND br.id = :branch_id ';
        $params[':branch_id'] = $branchId;
    } else {
        $branchCondition = ' AND br.id = b.default_branch_id ';
    }

    $stmt = $pdo->prepare("\n        SELECT\n            b.id AS business_id,\n            b.business_code,\n            b.business_name,\n            b.owner_name,\n            b.mobile AS business_mobile,\n            b.email AS business_email,\n            b.gst_number,\n            b.address AS business_address,\n            b.city AS business_city,\n            b.state AS business_state,\n            b.pincode AS business_pincode,\n            b.status AS business_status,\n\n            br.id AS branch_id,\n            br.parent_branch_id,\n            br.branch_name,\n            br.branch_code,\n            br.mobile AS branch_mobile,\n            br.email AS branch_email,\n            br.address AS branch_address,\n            br.city AS branch_city,\n            br.state AS branch_state,\n            br.pincode AS branch_pincode,\n            br.approval_status,\n            br.status AS branch_status,\n\n            u.id AS user_id,\n            u.name AS user_name,\n            u.username,\n            u.email AS user_email,\n            u.mobile AS user_mobile,\n            u.status AS user_status,\n\n            r.id AS role_id,\n            r.parent_role_id AS package_role_id,\n            r.role_name,\n            r.is_locked\n\n        FROM businesses b\n        INNER JOIN branches br\n            ON br.business_id = b.id\n            {$branchCondition}\n        LEFT JOIN users u\n            ON u.business_id = b.id\n            AND u.branch_id = br.id\n            AND u.user_type = 'business_user'\n        LEFT JOIN roles r ON r.id = u.role_id\n        WHERE b.id = :business_id\n        LIMIT 1\n    ");

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(false, 'Registration / branch not found.');
    }

    jsonResponse(true, 'Registration loaded.', [
        'registration' => $row
    ]);
}

function updateRegistration(PDO $pdo, $businessId, $packageRoleId, array $packageRole)
{
    $businessId = (int)$businessId;
    $branchId = (int)($_POST['edit_branch_id'] ?? $_POST['parent_branch_id'] ?? 0);

    if ($branchId <= 0) {
        jsonResponse(false, 'Please select branch to edit.');
    }

    $currentStmt = $pdo->prepare("
        SELECT
            b.id AS business_id,
            b.default_branch_id,
            br.id AS branch_id,
            u.id AS user_id,
            r.id AS role_id
        FROM businesses b
        INNER JOIN branches br
            ON br.business_id = b.id
            AND br.id = :branch_id
        LEFT JOIN users u
            ON u.business_id = b.id
            AND u.branch_id = br.id
            AND u.user_type = 'business_user'
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE b.id = :business_id
        LIMIT 1
    ");

    $currentStmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        jsonResponse(false, 'Selected branch not found for this business.');
    }

    $userId = (int)($current['user_id'] ?? 0);
    $roleId = (int)($current['role_id'] ?? 0);

    if ($userId <= 0 || $roleId <= 0) {
        jsonResponse(false, 'Selected branch login/role is not created. Please approve/create login first.');
    }

    $businessName = cleanInput($_POST['business_name'] ?? '');
    $ownerName = cleanInput($_POST['owner_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = cleanInput($_POST['gst_number'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');
    $branchName = cleanInput($_POST['branch_name'] ?? '');
    $branchCode = cleanInput($_POST['branch_code'] ?? '');
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($businessName === '') {
        jsonResponse(false, 'Please enter business name.');
    }

    if ($ownerName === '') {
        jsonResponse(false, 'Please enter login / owner name.');
    }

    if ($branchName === '') {
        jsonResponse(false, 'Please enter branch name.');
    }

    if ($branchCode === '') {
        $branchCode = generateBranchCode($pdo, $businessId);
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($username === '' || strlen($username) < 4) {
        jsonResponse(false, 'Username must be at least 4 characters.');
    }

    if ($password !== '') {
        if (strlen($password) < 6) {
            jsonResponse(false, 'Password must be at least 6 characters.');
        }
        if ($password !== $confirmPassword) {
            jsonResponse(false, 'Password and confirm password do not match.');
        }
    }

    ensureUniqueUserForUpdate($pdo, $userId, $mobile, $email, $username);
    ensureUniqueBusinessForUpdate($pdo, $businessId, $businessName, $mobile, $email);

    try {
        $pdo->beginTransaction();

        $businessStmt = $pdo->prepare("\n            UPDATE businesses\n            SET business_name = :business_name,\n                owner_name = :owner_name,\n                mobile = :mobile,\n                email = :email,\n                gst_number = :gst_number,\n                address = :address,\n                city = :city,\n                state = :state,\n                pincode = :pincode,\n                status = 1\n            WHERE id = :business_id\n        ");

        $businessStmt->execute([
            ':business_name' => $businessName,
            ':owner_name' => $ownerName,
            ':mobile' => $mobile,
            ':email' => $email,
            ':gst_number' => $gstNumber,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':business_id' => $businessId
        ]);

        $branchStmt = $pdo->prepare("\n            UPDATE branches\n            SET branch_name = :branch_name,\n                branch_code = :branch_code,\n                mobile = :mobile,\n                email = :email,\n                address = :address,\n                city = :city,\n                state = :state,\n                pincode = :pincode,\n                approval_status = 1,\n                status = 1\n            WHERE id = :branch_id\n            AND business_id = :business_id\n        ");

        $branchStmt->execute([
            ':branch_name' => $branchName,
            ':branch_code' => $branchCode,
            ':mobile' => $mobile,
            ':email' => $email,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':branch_id' => $branchId,
            ':business_id' => $businessId
        ]);

        $userSql = "\n            UPDATE users\n            SET name = :name,\n                username = :username,\n                email = :email,\n                mobile = :mobile,\n                status = 1\n        ";

        $userParams = [
            ':name' => $ownerName,
            ':username' => $username,
            ':email' => $email,
            ':mobile' => $mobile,
            ':user_id' => $userId,
            ':business_id' => $businessId,
            ':branch_id' => $branchId
        ];

        if ($password !== '') {
            $userSql .= ", password = :password";
            $userParams[':password'] = hashPassword($password);
        }

        $userSql .= "\n            WHERE id = :user_id\n            AND business_id = :business_id\n            AND branch_id = :branch_id\n        ";

        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute($userParams);

        $roleStmt = $pdo->prepare("\n            UPDATE roles\n            SET parent_role_id = :parent_role_id,\n                description = :description,\n                status = 1,\n                is_locked = 1\n            WHERE id = :role_id\n            AND business_id = :business_id\n            AND branch_id = :branch_id\n        ");

        $roleStmt->execute([
            ':parent_role_id' => $packageRoleId,
            ':description' => 'Default locked role using package: ' . $packageRole['role_name'],
            ':role_id' => $roleId,
            ':business_id' => $businessId,
            ':branch_id' => $branchId
        ]);

        insertActivityLog($pdo, $businessId, $branchId, currentUserId(), currentUserName(), $businessName, 'Customer registration updated with package ' . $packageRole['role_name']);

        $pdo->commit();

        jsonResponse(true, 'Customer registration updated successfully.', [
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'user_id' => $userId
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Registration update failed.');
    }
}

function saveRegistration(PDO $pdo)
{
    requirePlatformOwnerAccess();

    $registrationId = (int)($_POST['registration_id'] ?? 0);
    $registrationMode = cleanInput($_POST['registration_mode'] ?? 'new_business');
    $packageRoleId = (int)($_POST['package_role_id'] ?? 0);

    if ($packageRoleId <= 0) {
        jsonResponse(false, 'Please select package role.');
    }

    $packageRole = getValidPackageRole($pdo, $packageRoleId);

    if ($registrationId > 0) {
        updateRegistration($pdo, $registrationId, $packageRoleId, $packageRole);
        return;
    }

    if ($registrationMode === 'existing_business') {
        saveExistingBusinessBranch($pdo, $packageRoleId, $packageRole);
        return;
    }

    saveNewBusinessRegistration($pdo, $packageRoleId, $packageRole);
}

function saveNewBusinessRegistration(PDO $pdo, $packageRoleId, array $packageRole)
{
    $businessName = cleanInput($_POST['business_name'] ?? '');
    $ownerName = cleanInput($_POST['owner_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = cleanInput($_POST['gst_number'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    validateLoginFields($ownerName, $mobile, $email, $username, $password, $confirmPassword);

    if ($businessName === '') {
        jsonResponse(false, 'Please enter business name.');
    }

    ensureUniqueRegistration($pdo, $businessName, $mobile, $email, $username);

    try {
        $pdo->beginTransaction();

        $businessCode = generateBusinessCode($pdo);
        $branchCode = generateBranchCode($pdo, 0);
        $hashedPassword = hashPassword($password);

        $businessId = createApprovedBusiness($pdo, $businessCode, $businessName, $ownerName, $mobile, $email, $gstNumber, $address, $city, $state, $pincode);
        $branchId = createApprovedBranch($pdo, $businessId, null, $branchCode, 'Main Branch', $mobile, $email, $address, $city, $state, $pincode);

        updateBusinessDefaultBranch($pdo, $businessId, $branchId);

        $roleId = createLockedBranchAdminRole($pdo, $businessId, $branchId, $packageRoleId, $packageRole['role_name']);

        $userId = createBusinessAdminUser($pdo, $businessId, $branchId, $roleId, $ownerName, $username, $email, $mobile, $hashedPassword);
        updateBranchRequestedBy($pdo, $branchId, $userId);

        insertDefaultBusinessSettings($pdo, $businessId, $branchId);
        insertDefaultInvoiceSettings($pdo, $businessId, $branchId);
        insertActivityLog($pdo, $businessId, $branchId, currentUserId(), currentUserName(), $businessName, 'New Business registered with package ' . $packageRole['role_name']);

        $pdo->commit();

        jsonResponse(true, 'New customer business registered successfully.', [
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'user_id' => $userId
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Registration failed.');
    }
}

function saveExistingBusinessBranch(PDO $pdo, $packageRoleId, array $packageRole)
{
    $businessId = (int)($_POST['existing_business_id'] ?? 0);
    $parentBranchId = (int)($_POST['parent_branch_id'] ?? 0);

    $branchName = cleanInput($_POST['branch_name'] ?? '');
    $branchCode = cleanInput($_POST['branch_code'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    $ownerName = cleanInput($_POST['owner_name'] ?? '');
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($businessId <= 0) {
        jsonResponse(false, 'Please select existing business.');
    }

    if ($parentBranchId <= 0) {
        jsonResponse(false, 'Please select main branch.');
    }

    if ($branchName === '') {
        jsonResponse(false, 'Please enter branch name.');
    }

    validateExistingMainBranch($pdo, $businessId, $parentBranchId);
    validateLoginFields($ownerName, $mobile, $email, $username, $password, $confirmPassword);
    ensureUniqueUser($pdo, $mobile, $email, $username);

    if ($branchCode === '') {
        $branchCode = generateBranchCode($pdo, $businessId);
    }

    ensureUniqueBranchCode($pdo, $businessId, $branchCode);

    try {
        $pdo->beginTransaction();

        $hashedPassword = hashPassword($password);
        $branchId = createApprovedBranch($pdo, $businessId, $parentBranchId, $branchCode, $branchName, $mobile, $email, $address, $city, $state, $pincode);

        $roleId = createLockedBranchAdminRole($pdo, $businessId, $branchId, $packageRoleId, $packageRole['role_name']);

        $userId = createBusinessAdminUser($pdo, $businessId, $branchId, $roleId, $ownerName, $username, $email, $mobile, $hashedPassword);
        updateBranchRequestedBy($pdo, $branchId, $userId);

        insertDefaultBusinessSettings($pdo, $businessId, $branchId);
        insertDefaultInvoiceSettings($pdo, $businessId, $branchId);
        insertActivityLog($pdo, $businessId, $branchId, currentUserId(), currentUserName(), $branchName, 'New branch created under existing business with package ' . $packageRole['role_name']);

        $pdo->commit();

        jsonResponse(true, 'New branch created successfully under selected business.', [
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'user_id' => $userId
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Branch creation failed.');
    }
}

function validateLoginFields($name, $mobile, $email, $username, $password, $confirmPassword)
{
    if ($name === '') {
        jsonResponse(false, 'Please enter login / owner name.');
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($username === '' || strlen($username) < 4) {
        jsonResponse(false, 'Username must be at least 4 characters.');
    }

    if ($password === '' || strlen($password) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters.');
    }

    if ($password !== $confirmPassword) {
        jsonResponse(false, 'Password and confirm password do not match.');
    }
}

function getValidPackageRole(PDO $pdo, $packageRoleId)
{
    $stmt = $pdo->prepare("\n        SELECT id, role_name, description\n        FROM roles\n        WHERE id = :role_id\n        AND role_type = 1\n        AND business_id IS NULL\n        AND branch_id IS NULL\n        AND status = 1\n        LIMIT 1\n    ");

    $stmt->execute([':role_id' => $packageRoleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        jsonResponse(false, 'Invalid package role selected.');
    }

    $permissionStmt = $pdo->prepare("\n        SELECT COUNT(*) AS total_permissions\n        FROM role_menu_permissions\n        WHERE role_id = :role_id\n        AND status = 1\n        AND (\n            can_view = 1 OR can_add = 1 OR can_edit = 1 OR can_delete = 1 OR\n            can_print = 1 OR can_export = 1 OR can_approve = 1 OR can_convert = 1 OR\n            can_adjust = 1 OR can_ship = 1 OR can_generate_invoice = 1\n        )\n    ");

    $permissionStmt->execute([':role_id' => $packageRoleId]);
    $row = $permissionStmt->fetch(PDO::FETCH_ASSOC);

    if ((int)($row['total_permissions'] ?? 0) <= 0) {
        jsonResponse(false, 'Selected package role has no menu permissions. Please set package permissions first.');
    }

    return $role;
}

function validateExistingMainBranch(PDO $pdo, $businessId, $parentBranchId)
{
    $stmt = $pdo->prepare("\n        SELECT id\n        FROM branches\n        WHERE id = :branch_id\n        AND business_id = :business_id\n        AND approval_status = 1\n        AND status = 1\n        AND (parent_branch_id IS NULL OR parent_branch_id = 0)\n        LIMIT 1\n    ");

    $stmt->execute([
        ':branch_id' => $parentBranchId,
        ':business_id' => $businessId
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid main branch selected.');
    }
}

function ensureUniqueRegistration(PDO $pdo, $businessName, $mobile, $email, $username)
{
    ensureUniqueUser($pdo, $mobile, $email, $username);

    $businessSql = "\n        SELECT id\n        FROM businesses\n        WHERE business_name = :business_name\n        OR mobile = :mobile\n    ";

    $businessParams = [
        ':business_name' => $businessName,
        ':mobile' => $mobile
    ];

    if ($email !== '') {
        $businessSql .= " OR email = :business_email";
        $businessParams[':business_email'] = $email;
    }

    $businessSql .= " LIMIT 1";

    $businessStmt = $pdo->prepare($businessSql);
    $businessStmt->execute($businessParams);

    if ($businessStmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Business already registered with same name, mobile or email.');
    }
}

function ensureUniqueUser(PDO $pdo, $mobile, $email, $username)
{
    $userSql = "\n        SELECT id\n        FROM users\n        WHERE username = :username\n        OR mobile = :mobile\n    ";

    $userParams = [
        ':username' => $username,
        ':mobile' => $mobile
    ];

    if ($email !== '') {
        $userSql .= " OR email = :email";
        $userParams[':email'] = $email;
    }

    $userSql .= " LIMIT 1";

    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute($userParams);

    if ($userStmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Username, mobile or email already exists.');
    }
}

function ensureUniqueBranchCode(PDO $pdo, $businessId, $branchCode)
{
    $stmt = $pdo->prepare("\n        SELECT id\n        FROM branches\n        WHERE business_id = :business_id\n        AND branch_code = :branch_code\n        LIMIT 1\n    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_code' => $branchCode
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Branch code already exists for selected business.');
    }
}


function ensureUniqueUserForUpdate(PDO $pdo, $userId, $mobile, $email, $username)
{
    $userSql = "\n        SELECT id\n        FROM users\n        WHERE id != :user_id\n        AND (username = :username OR mobile = :mobile\n    ";

    $userParams = [
        ':user_id' => $userId,
        ':username' => $username,
        ':mobile' => $mobile
    ];

    if ($email !== '') {
        $userSql .= " OR email = :email";
        $userParams[':email'] = $email;
    }

    $userSql .= ") LIMIT 1";

    $stmt = $pdo->prepare($userSql);
    $stmt->execute($userParams);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Username, mobile or email already exists.');
    }
}

function ensureUniqueBusinessForUpdate(PDO $pdo, $businessId, $businessName, $mobile, $email)
{
    $businessSql = "\n        SELECT id\n        FROM businesses\n        WHERE id != :business_id\n        AND (business_name = :business_name OR mobile = :mobile\n    ";

    $businessParams = [
        ':business_id' => $businessId,
        ':business_name' => $businessName,
        ':mobile' => $mobile
    ];

    if ($email !== '') {
        $businessSql .= " OR email = :email";
        $businessParams[':email'] = $email;
    }

    $businessSql .= ") LIMIT 1";

    $stmt = $pdo->prepare($businessSql);
    $stmt->execute($businessParams);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Business already registered with same name, mobile or email.');
    }
}

function generateBusinessCode(PDO $pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) + 1 AS next_no FROM businesses");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return 'BUS' . str_pad((int)($row['next_no'] ?? 1), 5, '0', STR_PAD_LEFT);
}

function generateBranchCode(PDO $pdo, $businessId)
{
    if ($businessId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 AS next_no FROM branches WHERE business_id = :business_id");
        $stmt->execute([':business_id' => $businessId]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) + 1 AS next_no FROM branches");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return 'BR' . str_pad((int)($row['next_no'] ?? 1), 3, '0', STR_PAD_LEFT);
}

function createApprovedBusiness(PDO $pdo, $businessCode, $businessName, $ownerName, $mobile, $email, $gstNumber, $address, $city, $state, $pincode)
{
    $stmt = $pdo->prepare("\n        INSERT INTO businesses\n        (business_code, business_name, owner_name, mobile, email, gst_number, address, city, state, pincode, status, default_branch_id)\n        VALUES\n        (:business_code, :business_name, :owner_name, :mobile, :email, :gst_number, :address, :city, :state, :pincode, 1, NULL)\n    ");

    $stmt->execute([
        ':business_code' => $businessCode,
        ':business_name' => $businessName,
        ':owner_name' => $ownerName,
        ':mobile' => $mobile,
        ':email' => $email,
        ':gst_number' => $gstNumber,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':pincode' => $pincode
    ]);

    return (int)$pdo->lastInsertId();
}

function createApprovedBranch(PDO $pdo, $businessId, $parentBranchId, $branchCode, $branchName, $mobile, $email, $address, $city, $state, $pincode)
{
    $stmt = $pdo->prepare("\n        INSERT INTO branches\n        (\n            business_id, parent_branch_id, branch_code, branch_name, mobile, email, address, city, state, pincode,\n            requested_by, approved_by, approval_status, approval_remarks, requested_at, approved_at, status\n        )\n        VALUES\n        (\n            :business_id, :parent_branch_id, :branch_code, :branch_name, :mobile, :email, :address, :city, :state, :pincode,\n            NULL, :approved_by, 1, 'Directly created by platform owner', NOW(), NOW(), 1\n        )\n    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':parent_branch_id' => $parentBranchId,
        ':branch_code' => $branchCode,
        ':branch_name' => $branchName,
        ':mobile' => $mobile,
        ':email' => $email,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':pincode' => $pincode,
        ':approved_by' => currentUserId()
    ]);

    return (int)$pdo->lastInsertId();
}

function updateBusinessDefaultBranch(PDO $pdo, $businessId, $branchId)
{
    $stmt = $pdo->prepare("UPDATE businesses SET default_branch_id = :branch_id WHERE id = :business_id");
    $stmt->execute([':branch_id' => $branchId, ':business_id' => $businessId]);
}

function createLockedBranchAdminRole(PDO $pdo, $businessId, $branchId, $packageRoleId, $packageRoleName)
{
    $stmt = $pdo->prepare("\n        INSERT INTO roles\n        (business_id, branch_id, role_type, parent_role_id, role_name, description, status, is_locked)\n        VALUES\n        (:business_id, :branch_id, 2, :parent_role_id, 'Branch Admin', :description, 1, 1)\n    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':parent_role_id' => $packageRoleId,
        ':description' => 'Default locked role using package: ' . $packageRoleName
    ]);

    return (int)$pdo->lastInsertId();
}

function createBusinessAdminUser(PDO $pdo, $businessId, $branchId, $roleId, $ownerName, $username, $email, $mobile, $hashedPassword)
{
    $stmt = $pdo->prepare("\n        INSERT INTO users\n        (business_id, branch_id, role_id, name, username, email, mobile, password, user_type, status)\n        VALUES\n        (:business_id, :branch_id, :role_id, :name, :username, :email, :mobile, :password, 'business_user', 1)\n    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':role_id' => $roleId,
        ':name' => $ownerName,
        ':username' => $username,
        ':email' => $email,
        ':mobile' => $mobile,
        ':password' => $hashedPassword
    ]);

    return (int)$pdo->lastInsertId();
}

function updateBranchRequestedBy(PDO $pdo, $branchId, $userId)
{
    $stmt = $pdo->prepare("UPDATE branches SET requested_by = :requested_by WHERE id = :branch_id");
    $stmt->execute([':requested_by' => $userId, ':branch_id' => $branchId]);
}

function insertDefaultBusinessSettings(PDO $pdo, $businessId, $branchId)
{
    try {
        $settings = [
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'gst_enabled' => 'yes',
            'tax_mode' => 'cgst_sgst',
            'fifo_stock_deduction' => 'yes',
            'sales_flow' => 'proforma_to_quotation_to_sale_order_to_invoice'
        ];

        $stmt = $pdo->prepare("\n            INSERT INTO business_settings (business_id, branch_id, setting_key, setting_value)\n            VALUES (:business_id, :branch_id, :setting_key, :setting_value)\n        ");

        foreach ($settings as $key => $value) {
            $stmt->execute([
                ':business_id' => $businessId,
                ':branch_id' => $branchId,
                ':setting_key' => $key,
                ':setting_value' => $value
            ]);
        }
    } catch (Exception $e) {
        // Optional table. Do not stop registration.
    }
}

function insertDefaultInvoiceSettings(PDO $pdo, $businessId, $branchId)
{
    try {
        $stmt = $pdo->prepare("\n            INSERT INTO invoice_settings\n            (business_id, branch_id, invoice_prefix, proforma_prefix, quotation_prefix, sale_order_prefix, next_invoice_no, next_proforma_no, next_quotation_no, next_sale_order_no, terms, footer_text, logo_path, signature_path)\n            VALUES\n            (:business_id, :branch_id, 'INV', 'PRO', 'QUO', 'SO', 1, 1, 1, 1, :terms, :footer_text, NULL, NULL)\n        ");

        $stmt->execute([
            ':business_id' => $businessId,
            ':branch_id' => $branchId,
            ':terms' => 'Goods once sold will not be taken back.',
            ':footer_text' => 'Thank you for your business.'
        ]);
    } catch (Exception $e) {
        // Optional table. Do not stop registration.
    }
}

function insertActivityLog(PDO $pdo, $businessId, $branchId, $userId, $userName, $referenceName, $details)
{
    try {
        $stmt = $pdo->prepare("\n            INSERT INTO activity_logs\n            (business_id, branch_id, user_id, user_name, module, action, details, ip_address, request_method, request_url)\n            VALUES\n            (:business_id, :branch_id, :user_id, :user_name, 'Customer Registration', 'Registered', :details, :ip_address, :request_method, :request_url)\n        ");

        $stmt->execute([
            ':business_id' => $businessId,
            ':branch_id' => $branchId,
            ':user_id' => $userId,
            ':user_name' => $userName,
            ':details' => $details . ' - ' . $referenceName,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ':request_url' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
    } catch (Exception $e) {
        // Optional table. Do not stop registration.
    }
}
