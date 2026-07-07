<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_GET['action'] ?? $_POST['action'] ?? 'list_movements');

if ($action !== 'list_movements') {
    jsonResponse(false, 'Invalid action.');
}

function stockMovementCan($actionCode)
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }
    return function_exists('hasPermission') && hasPermission('stock_movement', (int)$actionCode);
}

function stockMovementScope()
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

if (!stockMovementCan(1) && !stockMovementCan(2)) {
    jsonResponse(false, 'Permission denied.');
}

$scope = stockMovementScope();
$search = cleanInput($_GET['search'] ?? '');
$type = strtoupper(cleanInput($_GET['movement_type'] ?? ''));
$sourceType = cleanInput($_GET['source_type'] ?? '');
$fromDate = cleanInput($_GET['from_date'] ?? '');
$toDate = cleanInput($_GET['to_date'] ?? '');

$where = "WHERE sm.business_id = :business_id AND sm.branch_id = :branch_id AND sm.status = 1";
$params = [
    ':business_id' => $scope['business_id'],
    ':branch_id' => $scope['branch_id']
];

if ($search !== '') {
    $where .= " AND (sm.product_name LIKE :search OR sm.product_code LIKE :search OR sm.source_no LIKE :search OR sm.batch_no LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (in_array($type, ['IN', 'OUT'], true)) {
    $where .= " AND sm.movement_type = :movement_type";
    $params[':movement_type'] = $type;
}

if ($sourceType !== '') {
    $where .= " AND sm.source_type = :source_type";
    $params[':source_type'] = $sourceType;
}

if ($fromDate !== '') {
    $where .= " AND sm.movement_date >= :from_date";
    $params[':from_date'] = $fromDate;
}

if ($toDate !== '') {
    $where .= " AND sm.movement_date <= :to_date";
    $params[':to_date'] = $toDate;
}

$stmt = $pdo->prepare("
    SELECT sm.*
    FROM stock_movements sm
    $where
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 500
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summaryStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.qty ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.qty ELSE 0 END), 0) AS total_out
    FROM stock_movements sm
    $where
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_in' => 0,
    'total_out' => 0
];

jsonResponse(true, 'Stock movement loaded.', [
    'rows' => $rows,
    'summary' => $summary
]);
