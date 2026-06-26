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
    case 'list':
        listRecords($pdo);
        break;
    case 'get':
        getRecord($pdo);
        break;
    case 'save':
        verifyCsrfToken();
        saveRecord($pdo);
        break;
    case 'delete':
        verifyCsrfToken();
        deleteRecord($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function requireMasterPermission($action = 'view')
{
    if (isPlatformOwner()) return;
    if (function_exists('hasPermission') && !hasPermission('hsn_codes', $action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function scopeData()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();
    if ($businessId <= 0 || $branchId <= 0) jsonResponse(false, 'Invalid business or branch session.');
    return ['business_id'=>$businessId, 'branch_id'=>$branchId];
}

function listRecords(PDO $pdo)
{
    requireMasterPermission('view');
    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = " WHERE business_id=:business_id AND branch_id=:branch_id ";
    $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];

    if ($status === 1 || $status === 2) {
        $where .= " AND status=:status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $where .= " AND (hsn_code LIKE :search OR hsn_description LIKE :search) ";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT id, hsn_code, hsn_description, cgst_percentage, sgst_percentage, igst_percentage, status, created_at
        FROM hsn_codes
        $where
        ORDER BY id DESC
    ");
    $stmt->execute($params);

    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS inactive
        FROM hsn_codes
        WHERE business_id=:business_id AND branch_id=:branch_id
    ");
    $statsStmt->execute($scope);

    jsonResponse(true, 'HSN list loaded.', ['records'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'stats'=>$statsStmt->fetch(PDO::FETCH_ASSOC)]);
}

function getRecord(PDO $pdo)
{
    requireMasterPermission('view');
    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid HSN.');

    $stmt = $pdo->prepare("SELECT * FROM hsn_codes WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResponse(false, 'HSN not found.');
    jsonResponse(true, 'HSN loaded.', ['record'=>$row]);
}

function saveRecord(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);
    requireMasterPermission($id > 0 ? 'edit' : 'add');

    $hsnCode = cleanInput($_POST['hsn_code'] ?? '');
    $description = cleanInput($_POST['hsn_description'] ?? '');
    $cgst = (float)($_POST['cgst_percentage'] ?? 0);
    $sgst = (float)($_POST['sgst_percentage'] ?? 0);
    $igst = (float)($_POST['igst_percentage'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if ($hsnCode === '') jsonResponse(false, 'Please enter HSN code.');
    if ($cgst < 0 || $sgst < 0 || $igst < 0) jsonResponse(false, 'Tax percentage cannot be negative.');
    if (!in_array($status, [1,2], true)) $status = 1;

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE hsn_codes
                SET hsn_code=:hsn_code, hsn_description=:description, cgst_percentage=:cgst, sgst_percentage=:sgst, igst_percentage=:igst, status=:status
                WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id
            ");
            $stmt->execute([':hsn_code'=>$hsnCode, ':description'=>$description, ':cgst'=>$cgst, ':sgst'=>$sgst, ':igst'=>$igst, ':status'=>$status, ':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
            jsonResponse(true, 'HSN updated successfully.', ['id'=>$id]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO hsn_codes (business_id, branch_id, hsn_code, hsn_description, cgst_percentage, sgst_percentage, igst_percentage, status, created_by)
            VALUES (:business_id, :branch_id, :hsn_code, :description, :cgst, :sgst, :igst, :status, :created_by)
        ");
        $stmt->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id'], ':hsn_code'=>$hsnCode, ':description'=>$description, ':cgst'=>$cgst, ':sgst'=>$sgst, ':igst'=>$igst, ':status'=>$status, ':created_by'=>currentUserId()]);
        jsonResponse(true, 'HSN added successfully.', ['id'=>(int)$pdo->lastInsertId()]);
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'HSN save failed.');
    }
}

function deleteRecord(PDO $pdo)
{
    requireMasterPermission('delete');
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid HSN.');

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM products WHERE hsn_id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    if ((int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0) jsonResponse(false, 'HSN already used in products. Make it inactive instead.');

    $stmt = $pdo->prepare("DELETE FROM hsn_codes WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'HSN deleted successfully.');
}
