<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');

requireApiLogin();

if (!isPlatformOwner()) {
    jsonResponse(false, 'Only platform owner can access customer registration.');
}

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

/** @var PDO $pdo */

switch ($action) {
    case 'list_registrations':
        listRegistrations($pdo);
        break;

    case 'get_existing_businesses':
        getExistingBusinesses($pdo);
        break;

    case 'get_main_branches':
        getMainBranches($pdo);
        break;

    case 'save_registration':
        verifyCsrfToken();
        saveRegistration($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function listRegistrations(PDO $pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                b.id AS business_id,
                b.business_code,
                b.business_name,
                b.owner_name,
                b.mobile,
                b.email,
                b.status AS business_status,
                b.created_at,

                br.id AS branch_id,
                br.branch_name,
                br.branch_code,
                br.approval_status,
                br.status AS branch_status,

                u.id AS user_id,
                u.name AS user_name,
                u.username,
                u.status AS user_status,

                r.id AS role_id,
                r.role_name,
                r.parent_role_id,
                r.is_locked,

                pr.role_name AS package_role_name,

                (
                    SELECT COUNT(*)
                    FROM branches cb
                    WHERE cb.business_id = b.id
                    AND cb.parent_branch_id = br.id
                    AND cb.approval_status = 1
                    AND cb.status = 1
                ) AS child_branch_count

            FROM businesses b
            LEFT JOIN branches br
                ON br.id = b.default_branch_id
            LEFT JOIN users u
                ON u.business_id = b.id
                AND u.branch_id = br.id
                AND u.user_type = 'business_user'
            LEFT JOIN roles r
                ON r.id = u.role_id
            LEFT JOIN roles pr
                ON pr.id = r.parent_role_id
            ORDER BY b.id DESC
            LIMIT 500
        ");

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
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load registrations.');
    }
}

function getExistingBusinesses(PDO $pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.business_name,
                b.owner_name,
                b.mobile,
                b.email,
                b.default_branch_id,
                br.branch_name AS main_branch_name
            FROM businesses b
            LEFT JOIN branches br ON br.id = b.default_branch_id
            WHERE b.status = 1
            ORDER BY b.business_name ASC
        ");

        $stmt->execute();

        jsonResponse(true, 'Businesses loaded.', [
            'businesses' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, 'Unable to load businesses.');
    }
}

function getMainBranches(PDO $pdo)
{
    $businessId = (int)($_GET['business_id'] ?? 0);

    if ($businessId <= 0) {
        jsonResponse(false, 'Invalid business.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, branch_name, branch_code
            FROM branches
            WHERE business_id = :business_id
            AND approval_status = 1
            AND status = 1
            AND (parent_branch_id IS NULL OR parent_branch_id = 0)
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':business_id' => $businessId
        ]);

        jsonResponse(true, 'Main branches loaded.', [
            'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, 'Unable to load branches.');
    }
}

function saveRegistration(PDO $pdo)
{
    $mode = cleanInput($_POST['registration_mode'] ?? 'new_business');

    $existingBusinessId = (int)($_POST['existing_business_id'] ?? 0);
    $parentBranchId = (int)($_POST['parent_branch_id'] ?? 0);

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
    $branchMobile = cleanInput($_POST['branch_mobile'] ?? '');
    $branchEmail = cleanInput($_POST['branch_email'] ?? '');
    $branchAddress = cleanInput($_POST['branch_address'] ?? '');

    $username = cleanInput($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $packageRoleId = (int)($_POST['package_role_id'] ?? 0);

    if (!in_array($mode, ['new_business', 'new_branch'], true)) {
        jsonResponse(false, 'Invalid registration type.');
    }

    if ($mode === 'new_business' && $businessName === '') {
        jsonResponse(false, 'Please enter business name.');
    }

    if ($mode === 'new_branch') {
        if ($existingBusinessId <= 0) {
            jsonResponse(false, 'Please select existing business.');
        }

        if ($parentBranchId <= 0) {
            jsonResponse(false, 'Please select main branch.');
        }
    }

    if ($ownerName === '') {
        jsonResponse(false, 'Please enter owner / login name.');
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($branchName === '') {
        jsonResponse(false, 'Please enter branch name.');
    }

    if (strlen($username) < 4) {
        jsonResponse(false, 'Username must be at least 4 characters.');
    }

    if (strlen($password) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters.');
    }

    if ($password !== $confirmPassword) {
        jsonResponse(false, 'Password and confirm password do not match.');
    }

    if ($packageRoleId <= 0) {
        jsonResponse(false, 'Please select package role.');
    }

    if (!packageRoleExists($pdo, $packageRoleId)) {
        jsonResponse(false, 'Invalid package role.');
    }

    if (!packageHasAccess($pdo, $packageRoleId)) {
        jsonResponse(false, 'Selected package has no permission in role_base_access. Save package permission first.');
    }

    if (usernameExists($pdo, $username)) {
        jsonResponse(false, 'Username already exists.');
    }

    if ($email !== '' && emailExists($pdo, $email)) {
        jsonResponse(false, 'Email already exists.');
    }

    try {
        $pdo->beginTransaction();

        $businessId = 0;
        $branchId = 0;

        if ($mode === 'new_business') {
            $businessCode = generateBusinessCode($pdo);

            $businessStmt = $pdo->prepare("
                INSERT INTO businesses
                (
                    business_code,
                    business_name,
                    owner_name,
                    mobile,
                    email,
                    gst_number,
                    address,
                    city,
                    state,
                    pincode,
                    status
                )
                VALUES
                (
                    :business_code,
                    :business_name,
                    :owner_name,
                    :mobile,
                    :email,
                    :gst_number,
                    :address,
                    :city,
                    :state,
                    :pincode,
                    1
                )
            ");

            $businessStmt->execute([
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

            $businessId = (int)$pdo->lastInsertId();
            $branchCode = generateBranchCode($pdo, $businessId);

            $branchStmt = $pdo->prepare("
                INSERT INTO branches
                (
                    business_id,
                    parent_branch_id,
                    branch_code,
                    branch_name,
                    mobile,
                    email,
                    address,
                    city,
                    state,
                    pincode,
                    requested_by,
                    approved_by,
                    approval_status,
                    approval_remarks,
                    requested_at,
                    approved_at,
                    status
                )
                VALUES
                (
                    :business_id,
                    NULL,
                    :branch_code,
                    :branch_name,
                    :mobile,
                    :email,
                    :address,
                    :city,
                    :state,
                    :pincode,
                    :requested_by,
                    :approved_by,
                    1,
                    'Directly created by platform owner',
                    NOW(),
                    NOW(),
                    1
                )
            ");

            $branchStmt->execute([
                ':business_id' => $businessId,
                ':branch_code' => $branchCode,
                ':branch_name' => $branchName,
                ':mobile' => $branchMobile !== '' ? $branchMobile : $mobile,
                ':email' => $branchEmail !== '' ? $branchEmail : $email,
                ':address' => $branchAddress !== '' ? $branchAddress : $address,
                ':city' => $city,
                ':state' => $state,
                ':pincode' => $pincode,
                ':requested_by' => currentUserId(),
                ':approved_by' => currentUserId()
            ]);

            $branchId = (int)$pdo->lastInsertId();

            $updateBusiness = $pdo->prepare("
                UPDATE businesses
                SET default_branch_id = :branch_id
                WHERE id = :business_id
                LIMIT 1
            ");

            $updateBusiness->execute([
                ':branch_id' => $branchId,
                ':business_id' => $businessId
            ]);

        } else {
            $businessId = $existingBusinessId;

            if (!businessExists($pdo, $businessId)) {
                throw new Exception('Selected business not found.');
            }

            if (!mainBranchExists($pdo, $businessId, $parentBranchId)) {
                throw new Exception('Selected main branch not found.');
            }

            $businessInfo = getBusinessInfo($pdo, $businessId);
            $branchCode = generateBranchCode($pdo, $businessId);

            $branchStmt = $pdo->prepare("
                INSERT INTO branches
                (
                    business_id,
                    parent_branch_id,
                    branch_code,
                    branch_name,
                    mobile,
                    email,
                    address,
                    city,
                    state,
                    pincode,
                    requested_by,
                    approved_by,
                    approval_status,
                    approval_remarks,
                    requested_at,
                    approved_at,
                    status
                )
                VALUES
                (
                    :business_id,
                    :parent_branch_id,
                    :branch_code,
                    :branch_name,
                    :mobile,
                    :email,
                    :address,
                    :city,
                    :state,
                    :pincode,
                    :requested_by,
                    :approved_by,
                    1,
                    'Directly created by platform owner',
                    NOW(),
                    NOW(),
                    1
                )
            ");

            $branchStmt->execute([
                ':business_id' => $businessId,
                ':parent_branch_id' => $parentBranchId,
                ':branch_code' => $branchCode,
                ':branch_name' => $branchName,
                ':mobile' => $branchMobile !== '' ? $branchMobile : $mobile,
                ':email' => $branchEmail !== '' ? $branchEmail : $email,
                ':address' => $branchAddress,
                ':city' => $businessInfo['city'] ?? '',
                ':state' => $businessInfo['state'] ?? 'Tamil Nadu',
                ':pincode' => $businessInfo['pincode'] ?? '',
                ':requested_by' => currentUserId(),
                ':approved_by' => currentUserId()
            ]);

            $branchId = (int)$pdo->lastInsertId();
        }

        /*
        |--------------------------------------------------------------------------
        | Create locked Branch Admin role
        |--------------------------------------------------------------------------
        | Important:
        | is_locked = 1
        | parent_role_id = selected package role id
        | No role_base_access copy
        |--------------------------------------------------------------------------
        */
        $roleId = createBusinessRole($pdo, $businessId, $branchId, $packageRoleId);

        $userStmt = $pdo->prepare("
            INSERT INTO users
            (
                business_id,
                branch_id,
                role_id,
                name,
                username,
                email,
                mobile,
                password,
                user_type,
                status
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :role_id,
                :name,
                :username,
                :email,
                :mobile,
                :password,
                'business_user',
                1
            )
        ");

        $userStmt->execute([
            ':business_id' => $businessId,
            ':branch_id' => $branchId,
            ':role_id' => $roleId,
            ':name' => $ownerName,
            ':username' => $username,
            ':email' => $email,
            ':mobile' => $mobile,
            ':password' => hashPassword($password)
        ]);

        $pdo->commit();

        jsonResponse(true, 'Customer registration saved successfully.', [
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'package_role_id' => $packageRoleId,
            'is_locked' => 1
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Unable to save registration.');
    }
}

function packageRoleExists(PDO $pdo, $roleId)
{
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
        ':id' => $roleId
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function packageHasAccess(PDO $pdo, $roleId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM role_base_access
        WHERE role_id = :role_id
        AND status = 1
        AND access_actions IS NOT NULL
        AND access_actions <> ''
    ");

    $stmt->execute([
        ':role_id' => $roleId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int)($row['total'] ?? 0) > 0;
}

function usernameExists(PDO $pdo, $username)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function emailExists(PDO $pdo, $email)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function businessExists(PDO $pdo, $businessId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM businesses
        WHERE id = :id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $businessId
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function mainBranchExists(PDO $pdo, $businessId, $branchId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM branches
        WHERE id = :id
        AND business_id = :business_id
        AND approval_status = 1
        AND status = 1
        AND (parent_branch_id IS NULL OR parent_branch_id = 0)
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $branchId,
        ':business_id' => $businessId
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function getBusinessInfo(PDO $pdo, $businessId)
{
    $stmt = $pdo->prepare("
        SELECT business_name, city, state, pincode
        FROM businesses
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $businessId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function generateBusinessCode(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT COALESCE(MAX(id), 0) + 1 AS next_id
        FROM businesses
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return 'BUS' . str_pad((string)((int)$row['next_id']), 5, '0', STR_PAD_LEFT);
}

function generateBranchCode(PDO $pdo, $businessId)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(COUNT(*), 0) + 1 AS next_no
        FROM branches
        WHERE business_id = :business_id
    ");

    $stmt->execute([
        ':business_id' => $businessId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return 'BR-' . $businessId . '-' . str_pad((string)((int)$row['next_no']), 3, '0', STR_PAD_LEFT);
}

function createBusinessRole(PDO $pdo, $businessId, $branchId, $packageRoleId)
{
    /*
    |--------------------------------------------------------------------------
    | Branch Admin Role
    |--------------------------------------------------------------------------
    | role_type      = 2
    | parent_role_id = selected package role id
    | is_locked      = 1
    |
    | No permission copy here.
    | Branch Admin inherits package permissions from parent_role_id.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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
            'Branch Admin',
            :description,
            1,
            1
        )
    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':parent_role_id' => $packageRoleId,
        ':description' => 'Default locked branch admin role using selected package permission'
    ]);

    return (int)$pdo->lastInsertId();
}
