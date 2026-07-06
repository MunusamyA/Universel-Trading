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
        listZones($pdo);
        break;

    case 'get':
        getZone($pdo);
        break;

    case 'save':
        verifyCsrfToken();
        saveZone($pdo);
        break;

    case 'delete':
        verifyCsrfToken();
        deleteZone($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

/*
|--------------------------------------------------------------------------
| Zone permission action numbers
|--------------------------------------------------------------------------
| 1 = View
| 2 = List
| 3 = Create / Add
| 4 = Edit
| 5 = Delete
|--------------------------------------------------------------------------
*/

function zoneCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('zones', (int)$actionCode);
}

function requireZonePermission($actionCode)
{
    if (!zoneCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!zoneCan(1) && !zoneCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => zoneCan(1),
            'can_list' => zoneCan(2),
            'can_add' => zoneCan(3),
            'can_edit' => zoneCan(4),
            'can_delete' => zoneCan(5),
            'page_title' => 'Zone Master',
            'page_note' => 'Manage zone master data based on your role permission.',
            'add_button_label' => 'Add Zone',
            'add_modal_title' => 'Add Zone',
            'edit_modal_title' => 'Edit Zone'
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

function listZones(PDO $pdo)
{
    if (!zoneCan(1) && !zoneCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }
if ($search !== '') {
    $where .= "
        AND (
            zone_name LIKE :search_zone_name
            OR zone_code LIKE :search_zone_code
            OR description LIKE :search_description
        )
    ";
    $params[':search_zone_name'] = '%' . $search . '%';
    $params[':search_zone_code'] = '%' . $search . '%';
    $params[':search_description'] = '%' . $search . '%';
}

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                zone_name,
                zone_code,
                description,
                status,
                created_at,
                updated_at
            FROM zones
            $where
            ORDER BY id DESC
        ");

        $stmt->execute($params);
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = zoneCan(4);
        $canDelete = zoneCan(5);

        foreach ($zones as &$zone) {
            $zone['can_edit'] = $canEdit;
            $zone['can_delete'] = $canDelete;
        }
        unset($zone);

        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive
            FROM zones
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

        jsonResponse(true, 'Zones loaded.', [
            'records' => $zones,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load zones.');
    }
}

function getZone(PDO $pdo)
{
    requireZonePermission(4);

    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid zone.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                zone_name,
                zone_code,
                description,
                status
            FROM zones
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

        $zone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$zone) {
            jsonResponse(false, 'Zone not found.');
        }

        jsonResponse(true, 'Zone loaded.', [
            'record' => $zone
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load zone.');
    }
}

function saveZone(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        requireZonePermission(4);
    } else {
        requireZonePermission(3);
    }

    $zoneName = cleanInput($_POST['zone_name'] ?? '');
    $zoneCode = strtoupper(cleanInput($_POST['zone_code'] ?? ''));
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($zoneName === '') {
        jsonResponse(false, 'Please enter zone name.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    if ($zoneCode === '') {
        $zoneCode = generateZoneCode($pdo, $scope['business_id'], $scope['branch_id']);
    }

    try {
        checkDuplicateZone($pdo, $scope, $zoneName, $zoneCode, $id);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE zones
                SET
                    zone_name = :zone_name,
                    zone_code = :zone_code,
                    description = :description,
                    status = :status
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':zone_name' => $zoneName,
                ':zone_code' => $zoneCode,
                ':description' => $description,
                ':status' => $status,
                ':id' => $id,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Zone updated successfully.', [
                'id' => $id
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO zones
            (
                business_id,
                branch_id,
                zone_name,
                zone_code,
                description,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :zone_name,
                :zone_code,
                :description,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':zone_name' => $zoneName,
            ':zone_code' => $zoneCode,
            ':description' => $description,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Zone added successfully.', [
            'id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Zone save failed.');
    }
}

function deleteZone(PDO $pdo)
{
    requireZonePermission(5);

    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid zone.');
    }

    try {
        checkZoneUsage($pdo, $scope, $id);

        $stmt = $pdo->prepare("
            DELETE FROM zones
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Zone deleted successfully.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Zone delete failed.');
    }
}

function generateZoneCode(PDO $pdo, $businessId, $branchId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS next_no
        FROM zones
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNo = (int)($row['next_no'] ?? 1);

    return 'ZONE-' . str_pad((string)$nextNo, 3, '0', STR_PAD_LEFT);
}

function checkDuplicateZone(PDO $pdo, array $scope, $zoneName, $zoneCode, $ignoreId = 0)
{
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':zone_name' => $zoneName,
        ':zone_code' => $zoneCode
    ];

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND (
            zone_name = :zone_name
            OR zone_code = :zone_code
        )
    ";

    if ((int)$ignoreId > 0) {
        $where .= " AND id != :ignore_id ";
        $params[':ignore_id'] = (int)$ignoreId;
    }

    $stmt = $pdo->prepare("
        SELECT id, zone_name, zone_code
        FROM zones
        $where
        LIMIT 1
    ");

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if (strtolower((string)$row['zone_name']) === strtolower((string)$zoneName)) {
            jsonResponse(false, 'Zone name already exists.');
        }

        jsonResponse(false, 'Zone code already exists.');
    }
}

function checkZoneUsage(PDO $pdo, array $scope, $zoneId)
{
    /*
    |--------------------------------------------------------------------------
    | Add future usage checks here if zone_id is used in customers/routes/users.
    | Currently no fixed dependency table is assumed.
    |--------------------------------------------------------------------------
    */

    return;
}
