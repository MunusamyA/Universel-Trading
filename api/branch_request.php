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
        listRequests($pdo);
        break;

    case 'save_request':
        verifyCsrfToken();
        saveRequest($pdo);
        break;

    case 'get_businesses':
        getBusinesses($pdo);
        break;

    case 'get_main_branches':
        getMainBranches($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
}

function getBusinesses(PDO $pdo)
{
    if (!isPlatformOwner()) {
        jsonResponse(false, 'Permission denied.');
    }

    $stmt = $pdo->prepare("\n        SELECT id, business_name\n        FROM businesses\n        WHERE status = 1\n        ORDER BY business_name ASC\n    ");
    $stmt->execute();

    jsonResponse(true, 'Businesses loaded.', [
        'businesses' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getMainBranches(PDO $pdo)
{
    if (!isPlatformOwner()) {
        jsonResponse(false, 'Permission denied.');
    }

    $businessId = (int)($_GET['business_id'] ?? 0);

    if ($businessId <= 0) {
        jsonResponse(false, 'Invalid business.');
    }

    $stmt = $pdo->prepare("\n        SELECT id, branch_name\n        FROM branches\n        WHERE business_id = :business_id\n        AND status = 1\n        AND COALESCE(approval_status, 1) = 1\n        AND (parent_branch_id IS NULL OR parent_branch_id = 0)\n        ORDER BY branch_name ASC\n    ");
    $stmt->execute([
        ':business_id' => $businessId
    ]);

    jsonResponse(true, 'Main branches loaded.', [
        'branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function listRequests(PDO $pdo)
{
    if (isPlatformOwner()) {
        $stmt = $pdo->prepare("\n            SELECT\n                br.*,\n                b.business_name,\n                parent_br.branch_name AS main_branch_name,\n                u.name AS requested_by_name\n            FROM branches br\n            INNER JOIN businesses b ON b.id = br.business_id\n            LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id\n            LEFT JOIN users u ON u.id = br.requested_by\n            WHERE br.requested_by IS NOT NULL\n            ORDER BY br.id DESC\n        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("\n            SELECT\n                br.*,\n                b.business_name,\n                parent_br.branch_name AS main_branch_name,\n                u.name AS requested_by_name\n            FROM branches br\n            INNER JOIN businesses b ON b.id = br.business_id\n            LEFT JOIN branches parent_br ON parent_br.id = br.parent_branch_id\n            LEFT JOIN users u ON u.id = br.requested_by\n            WHERE br.business_id = :business_id\n            AND br.parent_branch_id = :parent_branch_id\n            AND br.requested_by IS NOT NULL\n            ORDER BY br.id DESC\n        ");
        $stmt->execute([
            ':business_id'      => currentBusinessId(),
            ':parent_branch_id' => currentBranchId()
        ]);
    }

    jsonResponse(true, 'Branch requests loaded.', [
        'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function saveRequest(PDO $pdo)
{
    /*
        Final flow:
        - Customer registration creates only main branch user.
        - Branch request is NOT customer creation.
        - Only main branch user can submit branch request.
        - Platform owner approves request from branch approvals page.
        - Request page only inserts pending branch request. No user/role/package is created here.
    */

    if (isPlatformOwner()) {
        jsonResponse(false, 'Platform owner cannot create branch request here. Main branch user must request, platform owner can approve it.');
    }

    $businessId = currentBusinessId();
    $parentBranchId = currentBranchId();

    if ($businessId <= 0 || $parentBranchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    ensureCurrentUserIsMainBranchUser($pdo, $businessId, $parentBranchId);

    $branchName = cleanInput($_POST['branch_name'] ?? '');
    $branchCode = cleanInput($_POST['branch_code'] ?? '');
    $address    = cleanInput($_POST['address'] ?? '');
    $city       = cleanInput($_POST['city'] ?? '');
    $state      = cleanInput($_POST['state'] ?? '');
    $pincode    = cleanInput($_POST['pincode'] ?? '');
    $mobile     = cleanInput($_POST['mobile'] ?? '');
    $email      = cleanInput($_POST['email'] ?? '');

    if ($branchName === '') {
        jsonResponse(false, 'Please enter branch name.');
    }

    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($branchCode === '') {
        $branchCode = generateBranchRequestCode($pdo, $businessId);
    }

    $duplicateStmt = $pdo->prepare("
        SELECT id
        FROM branches
        WHERE business_id = :business_id
        AND (branch_name = :branch_name OR branch_code = :branch_code)
        LIMIT 1
    ");

    $duplicateStmt->execute([
        ':business_id' => $businessId,
        ':branch_name' => $branchName,
        ':branch_code' => $branchCode
    ]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Branch name or branch code already exists.');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO branches
            (
                business_id,
                parent_branch_id,
                requested_by,
                branch_name,
                branch_code,
                address,
                city,
                state,
                pincode,
                mobile,
                email,
                status,
                approval_status,
                requested_at
            )
            VALUES
            (
                :business_id,
                :parent_branch_id,
                :requested_by,
                :branch_name,
                :branch_code,
                :address,
                :city,
                :state,
                :pincode,
                :mobile,
                :email,
                2,
                0,
                NOW()
            )
        ");

        $stmt->execute([
            ':business_id'       => $businessId,
            ':parent_branch_id'  => $parentBranchId,
            ':requested_by'      => currentUserId(),
            ':branch_name'       => $branchName,
            ':branch_code'       => $branchCode,
            ':address'           => $address,
            ':city'              => $city,
            ':state'             => $state,
            ':pincode'           => $pincode,
            ':mobile'            => $mobile,
            ':email'             => $email
        ]);

        jsonResponse(true, 'Branch request submitted successfully. Platform owner approval is pending.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Branch request failed.');
    }
}

function ensureCurrentUserIsMainBranchUser(PDO $pdo, $businessId, $branchId)
{
    $stmt = $pdo->prepare("
        SELECT
            br.id,
            br.parent_branch_id,
            br.approval_status,
            br.status,
            u.id AS user_id,
            r.role_name,
            r.is_locked,
            r.parent_role_id
        FROM branches br
        INNER JOIN users u
            ON u.business_id = br.business_id
            AND u.branch_id = br.id
            AND u.id = :user_id
            AND u.status = 1
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE br.id = :branch_id
        AND br.business_id = :business_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => currentUserId(),
        ':branch_id' => $branchId,
        ':business_id' => $businessId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(false, 'Branch login not found.');
    }

    if ((int)$row['status'] !== 1 || (int)$row['approval_status'] !== 1) {
        jsonResponse(false, 'Only active approved main branch user can request branch.');
    }

    if (!empty($row['parent_branch_id']) && (int)$row['parent_branch_id'] > 0) {
        jsonResponse(false, 'Only main branch user can request new branches. Sub branch user cannot request another branch.');
    }

    /*
        Main branch admin/login should be package based.
        We accept role_name Branch Admin or Business Admin for old data compatibility.
    */
    $roleName = strtolower(trim((string)($row['role_name'] ?? '')));

    if (!in_array($roleName, ['branch admin', 'business admin'], true)) {
        jsonResponse(false, 'Only main branch admin user can request branch.');
    }
}

function generateBranchRequestCode(PDO $pdo, $businessId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS next_no
        FROM branches
        WHERE business_id = :business_id
    ");

    $stmt->execute([
        ':business_id' => $businessId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNo = (int)($row['next_no'] ?? 1);

    return 'BR-' . $businessId . '-' . str_pad((string)$nextNo, 3, '0', STR_PAD_LEFT);
}
