<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

header('Content-Type: application/json');

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? 'load_dashboard');

if ($action !== 'load_dashboard') {
    jsonResponse(false, 'Invalid action.');
}

function dashTableExists(PDO $pdo, $table)
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
        $stmt->execute([':table_name' => $table]);
        $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function dashColumnExists(PDO $pdo, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column
        ]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function dashValue(PDO $pdo, $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function dashRows(PDO $pdo, $sql, array $params = [])
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function moneyValue($value)
{
    return round((float)$value, 2);
}

function docLabelText($type)
{
    $map = [
        1 => 'Quotation',
        2 => 'Proforma Bill',
        3 => 'Sales Bill',
        4 => 'Direct Sale',
        5 => 'Final Invoice'
    ];

    return $map[(int)$type] ?? 'Document';
}

function docBadgeClassText($type)
{
    $map = [
        1 => 'bg-primary-subtle text-primary',
        2 => 'bg-info-subtle text-info',
        3 => 'bg-warning-subtle text-warning',
        4 => 'bg-success-subtle text-success',
        5 => 'bg-danger-subtle text-danger'
    ];

    return $map[(int)$type] ?? 'bg-secondary-subtle text-secondary';
}

function dashboardIsPlatformOwner(): bool
{
    return function_exists('isPlatformOwner') && isPlatformOwner();
}

function dashboardBusinessTable(PDO $pdo): string
{
    foreach (['businesses', 'business', 'business_details', 'business_master'] as $table) {
        if (dashTableExists($pdo, $table)) {
            return $table;
        }
    }

    return '';
}

function dashboardBusinessNameColumn(PDO $pdo, string $table): string
{
    foreach (['business_name', 'company_name', 'shop_name', 'name', 'firm_name'] as $column) {
        if (dashColumnExists($pdo, $table, $column)) {
            return $column;
        }
    }

    return 'id';
}

function dashboardBusinessCodeColumn(PDO $pdo, string $table): string
{
    foreach (['business_code', 'code'] as $column) {
        if (dashColumnExists($pdo, $table, $column)) {
            return $column;
        }
    }

    return '';
}

function dashboardBusinessActiveWhere(PDO $pdo, string $table): string
{
    $where = [];

    if (dashColumnExists($pdo, $table, 'status')) {
        $where[] = 'status = 1';
    }

    if (dashColumnExists($pdo, $table, 'approval_status')) {
        $where[] = 'approval_status = 1';
    }

    return $where ? ('WHERE ' . implode(' AND ', $where)) : '';
}

function dashboardLoadBusinesses(PDO $pdo): array
{
    $table = dashboardBusinessTable($pdo);
    if ($table === '') {
        return [];
    }

    $nameColumn = dashboardBusinessNameColumn($pdo, $table);
    $codeColumn = dashboardBusinessCodeColumn($pdo, $table);
    $codeSelect = $codeColumn !== '' ? ", `$codeColumn` AS business_code" : ", '' AS business_code";
    $where = dashboardBusinessActiveWhere($pdo, $table);

    return dashRows($pdo, "
        SELECT id, `$nameColumn` AS business_name $codeSelect
        FROM `$table`
        $where
        ORDER BY business_name ASC
    ");
}

function dashboardBusinessNameFromRows(array $businesses, int $businessId): string
{
    foreach ($businesses as $business) {
        if ((int)($business['id'] ?? 0) === $businessId) {
            return (string)($business['business_name'] ?? ('Business #' . $businessId));
        }
    }

    return $businessId > 0 ? ('Business #' . $businessId) : 'Platform Overall';
}

function canSwitchDashboardBranch(PDO $pdo, $businessId, $assignedBranchId)
{
    if (dashboardIsPlatformOwner()) {
        return true;
    }

    if ($businessId <= 0 || $assignedBranchId <= 0) {
        return false;
    }

    try {
        $roleName = strtolower((string)($_SESSION['role_name'] ?? (function_exists('currentRoleName') ? currentRoleName() : '')));
        $isAdminRole = (
            strpos($roleName, 'business admin') !== false ||
            strpos($roleName, 'main branch') !== false ||
            strpos($roleName, 'branch admin') !== false ||
            strpos($roleName, 'admin') !== false
        );

        if (!$isAdminRole) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT parent_branch_id
            FROM branches
            WHERE id = :branch_id
              AND business_id = :business_id
            LIMIT 1
        ");
        $stmt->execute([
            ':branch_id' => $assignedBranchId,
            ':business_id' => $businessId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && empty($row['parent_branch_id']);
    } catch (Throwable $e) {
        return false;
    }
}

function dashboardLoadBranches(PDO $pdo, int $businessId): array
{
    if ($businessId <= 0 || !dashTableExists($pdo, 'branches')) {
        return [];
    }

    $approvalSql = dashColumnExists($pdo, 'branches', 'approval_status') ? ' AND approval_status = 1 ' : '';

    return dashRows($pdo, "
        SELECT id, branch_code, branch_name, parent_branch_id
        FROM branches
        WHERE business_id = :business_id
          $approvalSql
          AND status = 1
        ORDER BY
          CASE WHEN parent_branch_id IS NULL THEN 0 ELSE 1 END,
          branch_name ASC
    ", [':business_id' => $businessId]);
}

function dashboardScopeSql(string $alias, int $businessId, int $branchId): string
{
    $prefix = $alias !== '' ? ($alias . '.') : '';
    $where = [];

    if ($businessId > 0) {
        $where[] = $prefix . 'business_id = :business_id';
    }

    if ($branchId > 0) {
        $where[] = $prefix . 'branch_id = :branch_id';
    }

    return $where ? implode(' AND ', $where) : '1=1';
}

function dashboardStatusCount(PDO $pdo, string $table, int $status = 1): int
{
    if ($table === '' || !dashTableExists($pdo, $table)) {
        return 0;
    }

    if (dashColumnExists($pdo, $table, 'status')) {
        return (int)dashValue($pdo, "SELECT COUNT(*) FROM `$table` WHERE status = :status", [':status' => $status]);
    }

    return (int)dashValue($pdo, "SELECT COUNT(*) FROM `$table`");
}

function dashboardTotalCount(PDO $pdo, string $table): int
{
    if ($table === '' || !dashTableExists($pdo, $table)) {
        return 0;
    }

    return (int)dashValue($pdo, "SELECT COUNT(*) FROM `$table`");
}

function dashboardPlatformStats(PDO $pdo): array
{
    $businessTable = dashboardBusinessTable($pdo);

    $totalBusinesses = dashboardTotalCount($pdo, $businessTable);
    $activeBusinesses = 0;

    if ($businessTable !== '') {
        $activeWhere = dashboardBusinessActiveWhere($pdo, $businessTable);
        $activeBusinesses = (int)dashValue($pdo, "SELECT COUNT(*) FROM `$businessTable` $activeWhere");
    }

    $inactiveBusinesses = max(0, $totalBusinesses - $activeBusinesses);

    $totalBranches = dashboardTotalCount($pdo, 'branches');
    $activeBranches = dashboardStatusCount($pdo, 'branches', 1);

    $totalUsers = dashboardTotalCount($pdo, 'users');
    $activeUsers = dashboardStatusCount($pdo, 'users', 1);

    $totalCustomers = dashboardTotalCount($pdo, 'customers');
    $totalProducts = dashboardTotalCount($pdo, 'products');
    $totalSuppliers = dashboardTotalCount($pdo, 'suppliers');

    return [
        'totalBusinesses' => $totalBusinesses,
        'activeBusinesses' => $activeBusinesses,
        'inactiveBusinesses' => $inactiveBusinesses,
        'totalBranches' => $totalBranches,
        'activeBranches' => $activeBranches,
        'totalUsers' => $totalUsers,
        'activeUsers' => $activeUsers,
        'totalCustomers' => $totalCustomers,
        'totalProducts' => $totalProducts,
        'totalSuppliers' => $totalSuppliers
    ];
}

$isPlatformOwnerDashboard = dashboardIsPlatformOwner();
$sessionBusinessId = function_exists('currentBusinessId') ? (int)currentBusinessId() : (int)($_SESSION['business_id'] ?? 0);
$assignedBranchId = (int)($_SESSION['branch_id'] ?? (function_exists('currentBranchId') ? currentBranchId() : 0));
$assignedBranchName = $_SESSION['branch_name'] ?? (function_exists('currentBranchName') ? currentBranchName() : '');

if (!$isPlatformOwnerDashboard && ($sessionBusinessId <= 0 || $assignedBranchId <= 0)) {
    jsonResponse(false, 'Invalid business or branch session.');
}

$requestBusinessId = isset($_POST['business_id'])
    ? (int)$_POST['business_id']
    : (isset($_GET['business_id']) ? (int)$_GET['business_id'] : ($isPlatformOwnerDashboard ? 0 : $sessionBusinessId));

$requestedBranchId = isset($_POST['branch_id'])
    ? (int)$_POST['branch_id']
    : (isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($isPlatformOwnerDashboard ? 0 : $assignedBranchId));

/*
 * Platform Owner dashboard must stay as platform-only.
 * Do not allow selecting a business/customer/branch context from here.
 */
$businesses = [];
$businessId = $isPlatformOwnerDashboard ? 0 : $sessionBusinessId;
$activeBusinessName = $isPlatformOwnerDashboard
    ? 'Platform Overall'
    : ($_SESSION['business_name'] ?? ($_SESSION['company_name'] ?? 'Business'));

$canSwitch = $isPlatformOwnerDashboard ? false : canSwitchDashboardBranch($pdo, $sessionBusinessId, $assignedBranchId);
$branches = [];
$branchId = 0;
$activeBranchName = 'Platform Overall';
$isOverall = false;

if ($isPlatformOwnerDashboard) {
    /*
     * Platform Owner dashboard is a high-level overview only.
     * Do not switch into branch/customer operational mode here.
     * Sales invoice/new sale/customer actions need a selected business login/branch session,
     * so Platform Owner always sees overall business/platform data.
     */
    $branchId = 0;
    $branches = [];
    $businessId = 0;
    $isOverall = true;
    $activeBusinessName = 'Platform Overall';
    $activeBranchName = 'Platform Overall - All Businesses';
} else {
    $businessId = $sessionBusinessId;
    $branchId = $assignedBranchId;
    $activeBranchName = $assignedBranchName;

    if ($canSwitch) {
        $branches = dashboardLoadBranches($pdo, $businessId);

        if ($requestedBranchId === 0) {
            $isOverall = true;
            $branchId = 0;
            $activeBranchName = 'Overall - All Branches';
        } else {
            foreach ($branches as $branch) {
                if ((int)$branch['id'] === $requestedBranchId) {
                    $branchId = (int)$branch['id'];
                    $activeBranchName = $branch['branch_name'];
                    break;
                }
            }
        }
    }
}

$scope = [];
if ($businessId > 0) {
    $scope[':business_id'] = $businessId;
}
if ($branchId > 0) {
    $scope[':branch_id'] = $branchId;
}

$scopeSql = dashboardScopeSql('', $businessId, $branchId);
$scopeSqlS = dashboardScopeSql('s', $businessId, $branchId);
$scopeSqlP = dashboardScopeSql('p', $businessId, $branchId);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$hasSales = dashTableExists($pdo, 'sales');
$hasSalesPayments = dashTableExists($pdo, 'sales_payments');
$hasCustomerPayments = dashTableExists($pdo, 'customer_payments');
$hasPurchases = dashTableExists($pdo, 'purchases');
$hasExpenses = dashTableExists($pdo, 'expenses');
$hasCustomers = dashTableExists($pdo, 'customers');
$hasProducts = dashTableExists($pdo, 'products');
$hasSuppliers = dashTableExists($pdo, 'suppliers');
$hasPurchaseItems = dashTableExists($pdo, 'purchase_items');

$platformStats = $isPlatformOwnerDashboard ? dashboardPlatformStats($pdo) : [];
$showOperationalDashboard = !$isPlatformOwnerDashboard;

$salesWhere = $scopeSql . " AND status IN (1,2)";
$activeWhere = $scopeSql . " AND status = 1";

// Sales amount cards include Sales Bill, Direct Sale and Final Invoice.
$todaySales = ($showOperationalDashboard && $hasSales) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE $salesWhere AND document_type IN (3,4,5) AND sales_date = CURDATE()", $scope) : 0;
$todaySalesCount = ($showOperationalDashboard && $hasSales) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM sales WHERE $salesWhere AND document_type IN (3,4,5) AND sales_date = CURDATE()", $scope) : 0;

$monthSales = ($showOperationalDashboard && $hasSales) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE $salesWhere AND document_type IN (3,4,5) AND sales_date BETWEEN :from_date AND :to_date", $scope + [':from_date' => $monthStart, ':to_date' => $monthEnd]) : 0;
$monthSalesCount = ($showOperationalDashboard && $hasSales) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM sales WHERE $salesWhere AND document_type IN (3,4,5) AND sales_date BETWEEN :from_date AND :to_date", $scope + [':from_date' => $monthStart, ':to_date' => $monthEnd]) : 0;

$pendingReceivable = ($showOperationalDashboard && $hasSales) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(due_amount),0) FROM sales WHERE $salesWhere AND document_type IN (3,4,5)", $scope) : 0;
$totalDocuments = ($showOperationalDashboard && $hasSales) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM sales WHERE $salesWhere", $scope) : 0;
$quotationValue = ($showOperationalDashboard && $hasSales) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE $salesWhere AND document_type = 1", $scope) : 0;
$finalInvoiceValue = ($showOperationalDashboard && $hasSales) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE $salesWhere AND document_type = 5", $scope) : 0;

$todayCollection = 0;
if ($showOperationalDashboard && $hasSalesPayments) {
    $todayCollection = (float)dashValue($pdo, "SELECT COALESCE(SUM(payment_amount),0) FROM sales_payments WHERE $activeWhere AND payment_date = CURDATE()", $scope);
} elseif ($showOperationalDashboard && $hasCustomerPayments) {
    $amountExpr = dashColumnExists($pdo, 'customer_payments', 'payment_amount') ? 'payment_amount' : 'amount';
    $todayCollection = (float)dashValue($pdo, "SELECT COALESCE(SUM($amountExpr),0) FROM customer_payments WHERE $activeWhere AND payment_date = CURDATE()", $scope);
}

$monthPurchase = ($showOperationalDashboard && $hasPurchases) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE $activeWhere AND purchase_date BETWEEN :from_date AND :to_date", $scope + [':from_date' => $monthStart, ':to_date' => $monthEnd]) : 0;
$purchaseDue = ($showOperationalDashboard && $hasPurchases) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(due_amount),0) FROM purchases WHERE $activeWhere", $scope) : 0;

$expenseAmountColumn = ($hasExpenses && dashColumnExists($pdo, 'expenses', 'total_amount')) ? 'total_amount' : 'amount';
$monthExpense = ($showOperationalDashboard && $hasExpenses) ? (float)dashValue($pdo, "SELECT COALESCE(SUM($expenseAmountColumn),0) FROM expenses WHERE $activeWhere AND expense_date BETWEEN :from_date AND :to_date", $scope + [':from_date' => $monthStart, ':to_date' => $monthEnd]) : 0;

$totalCustomers = ($showOperationalDashboard && $hasCustomers) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM customers WHERE $activeWhere", $scope) : 0;
$totalProducts = ($showOperationalDashboard && $hasProducts) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM products WHERE $activeWhere", $scope) : 0;
$totalSuppliers = ($showOperationalDashboard && $hasSuppliers) ? (int)dashValue($pdo, "SELECT COUNT(*) FROM suppliers WHERE $activeWhere", $scope) : 0;

$stockValue = ($showOperationalDashboard && $hasPurchaseItems) ? (float)dashValue($pdo, "SELECT COALESCE(SUM(available_qty * purchase_price),0) FROM purchase_items WHERE $activeWhere", $scope) : 0;

$lowStockProducts = [];
if (!$isPlatformOwnerDashboard && $hasProducts && $hasPurchaseItems) {
    $lowStockProducts = dashRows($pdo, "
        SELECT
            p.product_code,
            p.product_name,
            p.minimum_stock,
            COALESCE(SUM(pi.available_qty), p.initial_stock, 0) AS current_stock
        FROM products p
        LEFT JOIN purchase_items pi
               ON pi.product_id = p.id
              AND pi.business_id = p.business_id
              AND pi.branch_id = p.branch_id
              AND pi.status = 1
        WHERE $scopeSqlP
          AND p.status = 1
        GROUP BY p.id, p.product_code, p.product_name, p.minimum_stock, p.initial_stock
        HAVING p.minimum_stock > 0 AND current_stock <= p.minimum_stock
        ORDER BY current_stock ASC, p.product_name ASC
        LIMIT 8
    ", $scope);
}
$lowStockCount = count($lowStockProducts);

$last10Days = [];
for ($i = 9; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last10Days[$date] = [
        'date' => $date,
        'label' => date('d M', strtotime($date)),
        'amount' => 0
    ];
}
$last10DayKeys = array_keys($last10Days);
$firstChartDate = isset($last10DayKeys[0]) ? $last10DayKeys[0] : $today;

if ($showOperationalDashboard && $hasSales) {
    $rows = dashRows($pdo, "
        SELECT sales_date, COALESCE(SUM(grand_total),0) AS amount
        FROM sales
        WHERE $salesWhere
          AND document_type IN (3,4,5)
          AND sales_date BETWEEN :from_date AND :to_date
        GROUP BY sales_date
        ORDER BY sales_date ASC
    ", $scope + [
        ':from_date' => $firstChartDate,
        ':to_date' => $today
    ]);

    foreach ($rows as $row) {
        $date = $row['sales_date'];
        if (isset($last10Days[$date])) {
            $last10Days[$date]['amount'] = (float)$row['amount'];
        }
    }
}

$pipeline = [
    1 => ['count' => 0, 'amount' => 0],
    2 => ['count' => 0, 'amount' => 0],
    3 => ['count' => 0, 'amount' => 0],
    4 => ['count' => 0, 'amount' => 0],
    5 => ['count' => 0, 'amount' => 0]
];

if ($showOperationalDashboard && $hasSales) {
    $rows = dashRows($pdo, "
        SELECT document_type, COUNT(*) AS total_count, COALESCE(SUM(grand_total),0) AS amount
        FROM sales
        WHERE $salesWhere
        GROUP BY document_type
    ", $scope);

    foreach ($rows as $row) {
        $type = (int)$row['document_type'];
        if (isset($pipeline[$type])) {
            $pipeline[$type] = [
                'count' => (int)$row['total_count'],
                'amount' => (float)$row['amount']
            ];
        }
    }
}

$totalPipelineCount = 0;
foreach ($pipeline as $info) {
    $totalPipelineCount += (int)$info['count'];
}

$recentSales = [];
if (!$isPlatformOwnerDashboard && $hasSales) {
    $recentSales = dashRows($pdo, "
        SELECT s.id, s.sales_no, s.document_type, s.sales_date, s.grand_total, s.due_amount, c.customer_name
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        WHERE $scopeSqlS
          AND s.status IN (1,2)
        ORDER BY s.id DESC
        LIMIT 8
    ", $scope);
}

$topDueCustomers = (!$isPlatformOwnerDashboard && $hasCustomers) ? dashRows($pdo, "
    SELECT customer_name, mobile, current_outstanding
    FROM customers
    WHERE $activeWhere
      AND current_outstanding > 0
    ORDER BY current_outstanding DESC
    LIMIT 6
", $scope) : [];

$chartSalesLabels = [];
$chartSalesData = [];
foreach ($last10Days as $day) {
    $chartSalesLabels[] = $day['label'];
    $chartSalesData[] = moneyValue($day['amount']);
}

$collection10Days = [];
foreach ($last10Days as $dateKey => $dayInfo) {
    $collection10Days[$dateKey] = 0;
}

if ($showOperationalDashboard && $hasSalesPayments) {
    $rows = dashRows($pdo, "
        SELECT payment_date, COALESCE(SUM(payment_amount),0) AS amount
        FROM sales_payments
        WHERE $scopeSql
          AND status = 1
          AND payment_date BETWEEN :from_date AND :to_date
        GROUP BY payment_date
        ORDER BY payment_date ASC
    ", $scope + [
        ':from_date' => $firstChartDate,
        ':to_date' => $today
    ]);

    foreach ($rows as $row) {
        $date = $row['payment_date'];
        if (isset($collection10Days[$date])) {
            $collection10Days[$date] = (float)$row['amount'];
        }
    }
} elseif ($showOperationalDashboard && $hasCustomerPayments) {
    $paymentAmountColumn = dashColumnExists($pdo, 'customer_payments', 'payment_amount') ? 'payment_amount' : 'amount';
    $rows = dashRows($pdo, "
        SELECT payment_date, COALESCE(SUM($paymentAmountColumn),0) AS amount
        FROM customer_payments
        WHERE $scopeSql
          AND status = 1
          AND payment_date BETWEEN :from_date AND :to_date
        GROUP BY payment_date
        ORDER BY payment_date ASC
    ", $scope + [
        ':from_date' => $firstChartDate,
        ':to_date' => $today
    ]);

    foreach ($rows as $row) {
        $date = $row['payment_date'];
        if (isset($collection10Days[$date])) {
            $collection10Days[$date] = (float)$row['amount'];
        }
    }
}

$chartCollectionData = [];
foreach ($collection10Days as $value) {
    $chartCollectionData[] = moneyValue($value);
}

$chartDocLabels = [];
$chartDocCounts = [];
foreach ($pipeline as $type => $info) {
    $chartDocLabels[] = docLabelText($type);
    $chartDocCounts[] = (int)$info['count'];
}

$platformMetricLabels = [];
$platformMetricData = [];
$platformMasterLabels = [];
$platformMasterData = [];
$platformStatusLabels = [];
$platformStatusData = [];

if ($isPlatformOwnerDashboard) {
    /*
     * Platform Owner chart data must be platform-level only.
     * Do not show customer/business sales, invoice, receivable or collection charts.
     */
    $platformMetricLabels = ['Businesses', 'Branches', 'Users', 'Customers'];
    $platformMetricData = [
        (int)($platformStats['totalBusinesses'] ?? 0),
        (int)($platformStats['totalBranches'] ?? 0),
        (int)($platformStats['totalUsers'] ?? 0),
        (int)($platformStats['totalCustomers'] ?? 0)
    ];

    $platformMasterLabels = ['Products', 'Suppliers', 'Customers', 'Branches'];
    $platformMasterData = [
        (int)($platformStats['totalProducts'] ?? 0),
        (int)($platformStats['totalSuppliers'] ?? 0),
        (int)($platformStats['totalCustomers'] ?? 0),
        (int)($platformStats['totalBranches'] ?? 0)
    ];

    $platformStatusLabels = ['Active Businesses', 'Inactive Businesses'];
    $platformStatusData = [
        (int)($platformStats['activeBusinesses'] ?? 0),
        (int)($platformStats['inactiveBusinesses'] ?? 0)
    ];

    $chartSalesLabels = $platformMetricLabels;
    $chartSalesData = $platformMetricData;
    $chartCollectionData = [];
    $chartDocLabels = $platformStatusLabels;
    $chartDocCounts = $platformStatusData;
}

$quickLinks = $isPlatformOwnerDashboard ? [] : [
    ['label' => 'New Sale', 'url' => 'pages/sales.php', 'icon' => 'mdi mdi-cart-plus', 'class' => 'btn-primary'],
    ['label' => 'Sales List', 'url' => 'pages/sales-list.php', 'icon' => 'mdi mdi-format-list-bulleted', 'class' => 'btn-info'],
    ['label' => 'Products', 'url' => 'pages/products.php', 'icon' => 'mdi mdi-package-variant-closed', 'class' => 'btn-success'],
    ['label' => 'Customers', 'url' => 'pages/customers.php', 'icon' => 'mdi mdi-account-group', 'class' => 'btn-warning']
];

$responseBusinesses = [];

$responseBranches = [];
if (!$isPlatformOwnerDashboard && $canSwitch) {
    $responseBranches[] = [
        'id' => 0,
        'label' => $isPlatformOwnerDashboard ? ($activeBusinessName . ' - All Branches') : 'Overall - All Branches'
    ];

    foreach ($branches as $branch) {
        $label = $branch['branch_name'];
        if (!empty($branch['branch_code'])) {
            $label .= ' (' . $branch['branch_code'] . ')';
        }
        $label .= empty($branch['parent_branch_id']) ? ' - Main' : ' - Branch';

        $responseBranches[] = [
            'id' => (int)$branch['id'],
            'label' => $label
        ];
    }
}

jsonResponse(true, 'Dashboard loaded successfully.', [
    'context' => [
        'is_platform' => $isPlatformOwnerDashboard ? 1 : 0,
        'business_id' => $businessId,
        'business_name' => $activeBusinessName,
        'branch_id' => $branchId,
        'branch_name' => $activeBranchName,
        'scope_name' => $activeBranchName,
        'is_overall' => ($isOverall || $branchId === 0) ? 1 : 0,
        'can_switch_business' => 0,
        'can_switch_branch' => (!$isPlatformOwnerDashboard && $canSwitch) ? 1 : 0,
        'show_operations' => $isPlatformOwnerDashboard ? 0 : 1,
        'show_operational_stats' => $isPlatformOwnerDashboard ? 0 : 1,
        'show_platform_charts' => $isPlatformOwnerDashboard ? 1 : 0,
        'businesses' => [],
        'branches' => $responseBranches
    ],
    'metrics' => [
        'todaySales' => moneyValue($todaySales),
        'todaySalesCount' => $todaySalesCount,
        'monthSales' => moneyValue($monthSales),
        'monthSalesCount' => $monthSalesCount,
        'pendingReceivable' => moneyValue($pendingReceivable),
        'todayCollection' => moneyValue($todayCollection),
        'monthPurchase' => moneyValue($monthPurchase),
        'purchaseDue' => moneyValue($purchaseDue),
        'monthExpense' => moneyValue($monthExpense),
        'totalCustomers' => $totalCustomers,
        'totalProducts' => $totalProducts,
        'totalSuppliers' => $totalSuppliers,
        'stockValue' => moneyValue($stockValue),
        'lowStockCount' => $lowStockCount,
        'quotationValue' => moneyValue($quotationValue),
        'finalInvoiceValue' => moneyValue($finalInvoiceValue),
        'totalPipelineCount' => $totalPipelineCount,
        'platform' => $platformStats
    ],
    'pipeline' => [
        'quotationCount' => (int)$pipeline[1]['count'],
        'proformaCount' => (int)$pipeline[2]['count'],
        'salesBillCount' => (int)$pipeline[3]['count'],
        'directSaleCount' => (int)$pipeline[4]['count'],
        'finalInvoiceCount' => (int)$pipeline[5]['count']
    ],
    'charts' => [
        'salesLabels' => $chartSalesLabels,
        'salesData' => $chartSalesData,
        'collectionData' => $chartCollectionData,
        'docLabels' => $chartDocLabels,
        'docCounts' => $chartDocCounts,
        'splitLabels' => $isPlatformOwnerDashboard ? $platformMasterLabels : ['Sales', 'Purchase', 'Expense'],
        'splitData' => $isPlatformOwnerDashboard ? $platformMasterData : [moneyValue($monthSales), moneyValue($monthPurchase), moneyValue($monthExpense)],
        'platformMetricLabels' => $platformMetricLabels,
        'platformMetricData' => $platformMetricData,
        'platformMasterLabels' => $platformMasterLabels,
        'platformMasterData' => $platformMasterData,
        'platformStatusLabels' => $platformStatusLabels,
        'platformStatusData' => $platformStatusData
    ],
    'recentSales' => array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'sales_no' => $row['sales_no'],
            'document_type' => (int)$row['document_type'],
            'document_label' => docLabelText((int)$row['document_type']),
            'badge_class' => docBadgeClassText((int)$row['document_type']),
            'sales_date' => date('d-m-Y', strtotime($row['sales_date'])),
            'grand_total' => moneyValue($row['grand_total']),
            'due_amount' => moneyValue($row['due_amount']),
            'customer_name' => $row['customer_name'] ?: 'Walk-in Customer'
        ];
    }, $recentSales),
    'topDueCustomers' => $isPlatformOwnerDashboard ? [] : array_map(function ($row) {
        return [
            'customer_name' => $row['customer_name'],
            'mobile' => $row['mobile'] ?? '',
            'current_outstanding' => moneyValue($row['current_outstanding'])
        ];
    }, $topDueCustomers),
    'lowStockProducts' => array_map(function ($row) {
        return [
            'product_code' => $row['product_code'],
            'product_name' => $row['product_name'],
            'current_stock' => (float)$row['current_stock']
        ];
    }, $lowStockProducts),
    'quickLinks' => $quickLinks
]);
