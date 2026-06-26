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
    case 'list_requests':
        listBranchApprovals($pdo);
        break;

    case 'get_package_roles':
    case 'get_roles':
        getPackageRolesForApproval($pdo);
        break;

    case 'approve_branch':
        verifyCsrfToken();
        approveBranchWithLogin($pdo);
        break;

    case 'reject_branch':
        verifyCsrfToken();
        rejectBranch($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function canAccessBranchApprovalRow(array $branch)
{
    if (isPlatformOwner()) {
        return true;
    }

    return (int)$branch['business_id'] === currentBusinessId()
        && (int)$branch['parent_branch_id'] === currentBranchId();
}

function listBranchApprovals(PDO $pdo)
{
    if (isPlatformOwner()) {
        $stmt = $pdo->prepare("
            SELECT
                br.id,
                br.business_id,
                br.parent_branch_id,
                br.branch_name,
                br.branch_code,
                br.address,
                br.city,
                br.state,
                br.pincode,
                br.mobile,
                br.email,
                br.status,
                br.approval_status,
                br.approval_remarks,
                br.requested_at,
                br.approved_at,
                b.business_name,
                parent_br.branch_name AS main_branch_name,
                u.name AS requested_by_name
            FROM branches br
            INNER JOIN businesses b ON b.id = br.business_id
            LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id
            LEFT JOIN users u ON u.id = br.requested_by
            WHERE br.requested_by IS NOT NULL
            ORDER BY br.id DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT
                br.id,
                br.business_id,
                br.parent_branch_id,
                br.branch_name,
                br.branch_code,
                br.address,
                br.city,
                br.state,
                br.pincode,
                br.mobile,
                br.email,
                br.status,
                br.approval_status,
                br.approval_remarks,
                br.requested_at,
                br.approved_at,
                b.business_name,
                parent_br.branch_name AS main_branch_name,
                u.name AS requested_by_name
            FROM branches br
            INNER JOIN businesses b ON b.id = br.business_id
            LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id
            LEFT JOIN users u ON u.id = br.requested_by
            WHERE br.business_id = :business_id
            AND br.parent_branch_id = :parent_branch_id
            AND br.requested_by IS NOT NULL
            ORDER BY br.id DESC
        ");
        $stmt->execute([
            ':business_id' => currentBusinessId(),
            ':parent_branch_id' => currentBranchId()
        ]);
    }

    jsonResponse(true, 'Branch approval requests loaded.', [
        'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'user_type' => currentUserType()
    ]);
}

function getPackageRolesForApproval(PDO $pdo)
{
    $branchId = (int)($_GET['branch_id'] ?? 0);

    if ($branchId <= 0) {
        jsonResponse(false, 'Invalid branch request.');
    }

    $branch = getBranchRequest($pdo, $branchId);

    if (!$branch || !canAccessBranchApprovalRow($branch)) {
        jsonResponse(false, 'Branch request not found or access denied.');
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            role_name,
            description
        FROM roles
        WHERE role_type = 1
        AND business_id IS NULL
        AND branch_id IS NULL
        AND status = 1
        ORDER BY id ASC
    ");

    $stmt->execute();

    jsonResponse(true, 'Package roles loaded.', [
        'roles' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function approveBranchWithLogin(PDO $pdo)
{
    if (!isPlatformOwner() && !hasPermission('branch_approvals', 'approve')) {
        jsonResponse(false, 'You do not have branch approval permission.');
    }

    $branchId = (int)($_POST['branch_id'] ?? 0);
    $name = cleanInput($_POST['name'] ?? '');
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $packageRoleId = (int)($_POST['package_role_id'] ?? $_POST['role_id'] ?? 0);
    $remarks = cleanInput($_POST['remarks'] ?? '');

    if ($branchId <= 0) {
        jsonResponse(false, 'Invalid branch request.');
    }
    if ($name === '') {
        jsonResponse(false, 'Please enter login name.');
    }
    if ($username === '') {
        jsonResponse(false, 'Please enter username.');
    }
    if (strlen($password) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters.');
    }
    if ($packageRoleId <= 0) {
        jsonResponse(false, 'Please select package role.');
    }

    $branch = getBranchRequest($pdo, $branchId);

    if (!$branch || !canAccessBranchApprovalRow($branch)) {
        jsonResponse(false, 'Branch request not found or access denied.');
    }

    if ((int)$branch['approval_status'] === 1) {
        jsonResponse(false, 'This branch is already approved.');
    }

    if (!isValidPackageRole($pdo, $packageRoleId)) {
        jsonResponse(false, 'Invalid package role selected.');
    }

    if (usernameExists($pdo, $username)) {
        jsonResponse(false, 'Username already exists.');
    }

    try {
        $pdo->beginTransaction();

        $updateBranch = $pdo->prepare("
            UPDATE branches
            SET approval_status = 1,
                status = 1,
                approved_by = :approved_by,
                approved_at = NOW(),
                approval_remarks = :remarks
            WHERE id = :branch_id
        ");

        $updateBranch->execute([
            ':approved_by' => currentUserId(),
            ':remarks' => $remarks,
            ':branch_id' => $branchId
        ]);

        $createdRoleId = createApprovedBranchDefaultRole(
            $pdo,
            (int)$branch['business_id'],
            $branchId,
            $packageRoleId
        );


        $createUser = $pdo->prepare("
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

        $createUser->execute([
            ':business_id' => (int)$branch['business_id'],
            ':branch_id' => $branchId,
            ':role_id' => $createdRoleId,
            ':name' => $name,
            ':username' => $username,
            ':email' => $branch['email'] ?? '',
            ':mobile' => $branch['mobile'] ?? '',
            ':password' => hashPassword($password)
        ]);

        $pdo->commit();

        jsonResponse(true, 'Branch approved, package role applied and login created successfully.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Branch approval failed.');
    }
}

function rejectBranch(PDO $pdo)
{
    if (!isPlatformOwner() && !hasPermission('branch_approvals', 'approve')) {
        jsonResponse(false, 'You do not have branch approval permission.');
    }

    $branchId = (int)($_POST['branch_id'] ?? 0);
    $remarks = cleanInput($_POST['remarks'] ?? '');

    if ($branchId <= 0) {
        jsonResponse(false, 'Invalid branch request.');
    }

    $branch = getBranchRequest($pdo, $branchId);

    if (!$branch || !canAccessBranchApprovalRow($branch)) {
        jsonResponse(false, 'Branch request not found or access denied.');
    }

    $stmt = $pdo->prepare("
        UPDATE branches
        SET approval_status = 2,
            status = 2,
            approved_by = :approved_by,
            approved_at = NOW(),
            approval_remarks = :remarks
        WHERE id = :branch_id
    ");

    $stmt->execute([
        ':approved_by' => currentUserId(),
        ':remarks' => $remarks,
        ':branch_id' => $branchId
    ]);

    jsonResponse(true, 'Branch request rejected successfully.');
}

function createApprovedBranchDefaultRole(PDO $pdo, $businessId, $branchId, $packageRoleId)
{
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
            'Default branch admin role using selected package',
            1,
            1
        )
    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':parent_role_id' => $packageRoleId
    ]);

    return (int)$pdo->lastInsertId();
}

function getBranchRequest(PDO $pdo, $branchId)
{
    $stmt = $pdo->prepare("
        SELECT
            br.*,
            b.business_name,
            parent_br.branch_name AS main_branch_name
        FROM branches br
        INNER JOIN businesses b ON b.id = br.business_id
        LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id
        WHERE br.id = :branch_id
        AND br.requested_by IS NOT NULL
        LIMIT 1
    ");

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function usernameExists(PDO $pdo, $username)
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function isValidPackageRole(PDO $pdo, $packageRoleId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM roles
        WHERE id = :role_id
        AND role_type = 1
        AND business_id IS NULL
        AND branch_id IS NULL
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':role_id' => $packageRoleId
    ]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}
