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
    case 'get_page_context':
        getPageContext($pdo);
        break;

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
        break;
}

/*
|--------------------------------------------------------------------------
| HSN permission action numbers
|--------------------------------------------------------------------------
| 1 = View
| 2 = List
| 3 = Create / Add
| 4 = Edit
| 5 = Delete
|--------------------------------------------------------------------------
*/

function hsnCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('hsn_codes', (int)$actionCode);
}

function requireHsnPermission($actionCode)
{
    if (!hsnCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!hsnCan(1) && !hsnCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => hsnCan(1),
            'can_list' => hsnCan(2),
            'can_add' => hsnCan(3),
            'can_edit' => hsnCan(4),
            'can_delete' => hsnCan(5),
            'page_title' => 'HSN Codes',
            'page_note' => 'Manage HSN master data based on your role permission.',
            'add_button_label' => 'Add HSN',
            'add_modal_title' => 'Add HSN',
            'edit_modal_title' => 'Edit HSN'
        ]
    ]);
}

function scopeData()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    return [
        'business_id' => $businessId,
        'branch_id' => $branchId
    ];
}

function listRecords(PDO $pdo)
{
    if (!hsnCan(1) && !hsnCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = " WHERE business_id = :business_id AND branch_id = :branch_id ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
    $where .= " AND (hsn_code LIKE :search_hsn_code OR hsn_description LIKE :search_hsn_description) ";
    $params[':search_hsn_code'] = '%' . $search . '%';
    $params[':search_hsn_description'] = '%' . $search . '%';
}

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                hsn_code,
                hsn_description,
                cgst_percentage,
                sgst_percentage,
                igst_percentage,
                status,
                created_at
            FROM hsn_codes
            $where
            ORDER BY id DESC
        ");

        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = hsnCan(4);
        $canDelete = hsnCan(5);

        foreach ($records as &$record) {
            $record['can_edit'] = $canEdit;
            $record['can_delete'] = $canDelete;
        }
        unset($record);

        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive
            FROM hsn_codes
            WHERE business_id = :business_id
            AND branch_id = :branch_id
        ");

        $statsStmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0,
            'active' => 0,
            'inactive' => 0
        ];

        jsonResponse(true, 'HSN list loaded.', [
            'records' => $records,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load HSN list.');
    }
}

function getRecord(PDO $pdo)
{
    requireHsnPermission(4);

    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid HSN.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM hsn_codes
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse(false, 'HSN not found.');
        }

        jsonResponse(true, 'HSN loaded.', [
            'record' => $row
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load HSN.');
    }
}

function saveRecord(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        requireHsnPermission(4);
    } else {
        requireHsnPermission(3);
    }

    $hsnCode = cleanInput($_POST['hsn_code'] ?? '');
    $description = cleanInput($_POST['hsn_description'] ?? '');
    $cgst = (float)($_POST['cgst_percentage'] ?? 0);
    $sgst = (float)($_POST['sgst_percentage'] ?? 0);
    $igst = (float)($_POST['igst_percentage'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if ($hsnCode === '') {
        jsonResponse(false, 'Please enter HSN code.');
    }

    if ($cgst < 0 || $sgst < 0 || $igst < 0) {
        jsonResponse(false, 'Tax percentage cannot be negative.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        checkDuplicateHsnCode($pdo, $scope, $hsnCode, $id);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE hsn_codes
                SET
                    hsn_code = :hsn_code,
                    hsn_description = :description,
                    cgst_percentage = :cgst,
                    sgst_percentage = :sgst,
                    igst_percentage = :igst,
                    status = :status
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':hsn_code' => $hsnCode,
                ':description' => $description,
                ':cgst' => $cgst,
                ':sgst' => $sgst,
                ':igst' => $igst,
                ':status' => $status,
                ':id' => $id,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'HSN updated successfully.', [
                'id' => $id
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO hsn_codes
            (
                business_id,
                branch_id,
                hsn_code,
                hsn_description,
                cgst_percentage,
                sgst_percentage,
                igst_percentage,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :hsn_code,
                :description,
                :cgst,
                :sgst,
                :igst,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':hsn_code' => $hsnCode,
            ':description' => $description,
            ':cgst' => $cgst,
            ':sgst' => $sgst,
            ':igst' => $igst,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'HSN added successfully.', [
            'id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'HSN save failed.');
    }
}

function deleteRecord(PDO $pdo)
{
    requireHsnPermission(5);

    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid HSN.');
    }

    try {
        checkHsnUsage($pdo, $scope, $id);

        $stmt = $pdo->prepare("
            DELETE FROM hsn_codes
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'HSN deleted successfully.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'HSN delete failed.');
    }
}

function checkDuplicateHsnCode(PDO $pdo, array $scope, $hsnCode, $ignoreId = 0)
{
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':hsn_code' => $hsnCode
    ];

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND hsn_code = :hsn_code
    ";

    if ((int)$ignoreId > 0) {
        $where .= " AND id != :ignore_id ";
        $params[':ignore_id'] = (int)$ignoreId;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM hsn_codes
        $where
        LIMIT 1
    ");

    $stmt->execute($params);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'HSN code already exists.');
    }
}

function checkHsnUsage(PDO $pdo, array $scope, $hsnId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM products
        WHERE hsn_id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':id' => $hsnId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)($row['total'] ?? 0) > 0) {
        jsonResponse(false, 'HSN already used in products. Make it inactive instead.');
    }
}
