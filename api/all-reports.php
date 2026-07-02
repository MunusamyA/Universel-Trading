<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireApiLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action !== 'list') {
    jsonResponse(false, 'Invalid action');
}

$scope = [
    'business_id' => currentBusinessId(),
    'branch_id'   => currentBranchId()
];

$reportKey = $_GET['report_key'] ?? '';
$search    = $_GET['search'] ?? '';
$fromDate  = $_GET['from_date'] ?? '';
$toDate    = $_GET['to_date'] ?? '';

function dateFilter($col, $from, $to) {
    if ($from && $to) {
        return " AND $col BETWEEN :from_date AND :to_date ";
    }
    return "";
}

$params = [
    ':business_id' => $scope['business_id'],
    ':branch_id'   => $scope['branch_id']
];

if ($fromDate && $toDate) {
    $params[':from_date'] = $fromDate;
    $params[':to_date'] = $toDate;
}

/* ================= SALES SUMMARY ================= */
if ($reportKey === 'sales_summary') {

    $sql = "
        SELECT 
            COUNT(*) AS bills,
            SUM(grand_total) AS total,
            SUM(paid_amount) AS paid,
            SUM(due_amount) AS due
        FROM sales
        WHERE business_id=:business_id
        AND branch_id=:branch_id
        AND status IN (1,2)
    " . dateFilter("sales_date", $fromDate, $toDate);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse(true, 'OK', [
        'title' => 'Sales Summary',
        'columns' => ['Bills','Total','Paid','Due'],
        'rows' => [$data]
    ]);
}

/* ================= PURCHASE SUMMARY ================= */
if ($reportKey === 'purchase_summary') {

    $sql = "
        SELECT 
            COUNT(*) AS bills,
            SUM(grand_total) AS total,
            SUM(paid_amount) AS paid,
            SUM(due_amount) AS due
        FROM purchases
        WHERE business_id=:business_id
        AND branch_id=:branch_id
        AND status=1
    " . dateFilter("purchase_date", $fromDate, $toDate);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'OK', [
        'title' => 'Purchase Summary',
        'columns' => ['Bills','Total','Paid','Due'],
        'rows' => [$stmt->fetch(PDO::FETCH_ASSOC)]
    ]);
}

/* ================= CUSTOMER OUTSTANDING ================= */
if ($reportKey === 'customer_outstanding') {

    $sql = "
        SELECT 
            customer_name,
            opening_outstanding,
            current_outstanding
        FROM customers
        WHERE business_id=:business_id
        AND branch_id=:branch_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'OK', [
        'title' => 'Customer Outstanding',
        'columns' => ['Customer','Opening','Current'],
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/* ================= EXPENSE SUMMARY ================= */
if ($reportKey === 'expense_summary') {

    $sql = "
        SELECT 
            COUNT(*) AS entries,
            SUM(total_amount) AS total
        FROM expenses
        WHERE business_id=:business_id
        AND branch_id=:branch_id
        AND status=1
    " . dateFilter("expense_date", $fromDate, $toDate);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'OK', [
        'title' => 'Expense Summary',
        'columns' => ['Entries','Total'],
        'rows' => [$stmt->fetch(PDO::FETCH_ASSOC)]
    ]);
}

/* DEFAULT */
jsonResponse(false, 'Invalid report key');