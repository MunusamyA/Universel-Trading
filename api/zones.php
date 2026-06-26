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

function requireZonePermission($action = 'view')
{
    if (isPlatformOwner()) {
        return;
    }

    if (function_exists('hasPermission') && !hasPermission('zones', $action)) {
        jsonResponse(false, 'Permission denied.');
    }
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
    requireZonePermission('view');

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
                zone_name LIKE :search
                OR zone_code LIKE :search
                OR description LIKE :search
            )
        ";
        $params[':search'] = '%' . $search . '%';
    }

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

    jsonResponse(true, 'Zones loaded.', [
        'records' => $zones,
        'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
    ]);
}

function getZone(PDO $pdo)
{
    requireZonePermission('view');

    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid zone.');
    }

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
}

function saveZone(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    requireZonePermission($id > 0 ? 'edit' : 'add');

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
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE zones
                SET zone_name = :zone_name,
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
    requireZonePermission('delete');

    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid zone.');
    }

    /*
        Later if customers / routes / sales users use zone_id,
        add usage check here and block delete.
    */

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
