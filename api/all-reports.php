<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {
    case 'get_context':
        getAllReportsContext($pdo);
        break;

    case 'list':
        listAllReportData($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
}

function rptActionCode($action)
{
    if (is_numeric($action)) {
        return (int)$action;
    }

    $map = [
        'view' => 1,
        'list' => 2,
        'add' => 3,
        'create' => 3,
        'edit' => 4,
        'delete' => 5,
        'print' => 6,
        'export' => 7,
        'download' => 21,
        'generate_report' => 31,
        'report' => 31
    ];

    $key = strtolower(trim((string)$action));

    return $map[$key] ?? 1;
}

function rptActionName($actionCode)
{
    $map = [
        1 => 'view',
        2 => 'list',
        3 => 'add',
        4 => 'edit',
        5 => 'delete',
        6 => 'print',
        7 => 'export',
        21 => 'download',
        31 => 'generate_report'
    ];

    return $map[(int)$actionCode] ?? 'view';
}

function allReportsPermissionKeys()
{
    return ['all_reports', 'all-reports', 'reports'];
}

function rptCurrentRoleIds()
{
    static $roleIds = null;

    if ($roleIds !== null) {
        return $roleIds;
    }

    global $pdo;

    $roleIds = [];

    if (function_exists('currentRoleId')) {
        $roleId = (int)currentRoleId();
        if ($roleId > 0) {
            $roleIds[] = $roleId;
        }
    }

    foreach (['role_id', 'current_role_id', 'user_role_id'] as $sessionKey) {
        if (!empty($_SESSION[$sessionKey])) {
            $roleId = (int)$_SESSION[$sessionKey];
            if ($roleId > 0) {
                $roleIds[] = $roleId;
            }
        }
    }

    if (function_exists('currentUserId')) {
        $userId = (int)currentUserId();

        if ($userId > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $dbRoleId = (int)$stmt->fetchColumn();

                if ($dbRoleId > 0) {
                    $roleIds[] = $dbRoleId;
                }
            } catch (Throwable $e) {
                // Ignore fallback errors.
            }
        }
    }

    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

    /*
     * Include parent package role also.
     */
    if ($roleIds && isset($pdo) && $pdo instanceof PDO) {
        try {
            $holders = [];
            $params = [];

            foreach ($roleIds as $index => $roleId) {
                $key = ':role_' . $index;
                $holders[] = $key;
                $params[$key] = $roleId;
            }

            $stmt = $pdo->prepare("
                SELECT parent_role_id
                FROM roles
                WHERE id IN (" . implode(',', $holders) . ")
                AND parent_role_id IS NOT NULL
                AND parent_role_id > 0
            ");
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentRoleId) {
                $parentRoleId = (int)$parentRoleId;
                if ($parentRoleId > 0) {
                    $roleIds[] = $parentRoleId;
                }
            }
        } catch (Throwable $e) {
            // Ignore parent role lookup errors.
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $roleIds))));
}

function rptRoleBaseMenuRowsExist($moduleKeys)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = rptCurrentRoleIds();

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':rpt_menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':rpt_role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = $roleId;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function rptRoleBasePermissionAllowed($moduleKeys, $action)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = rptCurrentRoleIds();
    $actionCode = rptActionCode($action);

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [
            ':action_code' => (string)$actionCode
        ];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':rpt_allowed_menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':rpt_allowed_role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = $roleId;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.status = 1
            AND sm.status = 1
            AND rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            AND FIND_IN_SET(:action_code, REPLACE(COALESCE(rba.access_actions, ''), ' ', '')) > 0
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function rptHasPermissionFallback($moduleKeys, $action)
{
    if (!function_exists('hasPermission')) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $actionCode = rptActionCode($action);
    $actionName = rptActionName($actionCode);

    foreach ($moduleKeys as $moduleKey) {
        if ($moduleKey === '') {
            continue;
        }

        try {
            if (hasPermission($moduleKey, $actionCode) || hasPermission($moduleKey, $actionName)) {
                return true;
            }
        } catch (Throwable $e) {
            // Continue with next key.
        }
    }

    return false;
}

function rptCan($moduleKeys, $action = 1)
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    if (rptRoleBaseMenuRowsExist($moduleKeys)) {
        return rptRoleBasePermissionAllowed($moduleKeys, $action);
    }

    return rptHasPermissionFallback($moduleKeys, $action);
}

function reportDefinitions()
{
    return [
        ['group' => 'Sales', 'key' => 'sales_summary', 'title' => 'Sales Summary', 'icon' => 'mdi-chart-bar'],
        ['group' => 'Sales', 'key' => 'sales_detailed', 'title' => 'Sales Detailed', 'icon' => 'mdi-file-document'],
        ['group' => 'Sales', 'key' => 'customer_wise_sales', 'title' => 'Customer-wise Sales', 'icon' => 'mdi-account-cash'],
        ['group' => 'Sales', 'key' => 'product_wise_sales', 'title' => 'Product-wise Sales', 'icon' => 'mdi-package-variant'],
        ['group' => 'Sales', 'key' => 'sales_due', 'title' => 'Sales Due', 'icon' => 'mdi-calendar-alert'],
        ['group' => 'Sales', 'key' => 'customer_payment', 'title' => 'Customer Payment', 'icon' => 'mdi-cash-plus'],

        ['group' => 'Purchase', 'key' => 'purchase_summary', 'title' => 'Purchase Summary', 'icon' => 'mdi-chart-bar'],
        ['group' => 'Purchase', 'key' => 'purchase_detailed', 'title' => 'Purchase Detailed', 'icon' => 'mdi-file-document'],
        ['group' => 'Purchase', 'key' => 'supplier_wise_purchase', 'title' => 'Supplier-wise Purchase', 'icon' => 'mdi-truck'],
        ['group' => 'Purchase', 'key' => 'product_wise_purchase', 'title' => 'Product-wise Purchase', 'icon' => 'mdi-package-down'],
        ['group' => 'Purchase', 'key' => 'purchase_due', 'title' => 'Purchase Due', 'icon' => 'mdi-calendar-alert'],
        ['group' => 'Purchase', 'key' => 'supplier_payment', 'title' => 'Supplier Payment', 'icon' => 'mdi-cash-minus'],

        ['group' => 'Expense', 'key' => 'expense_summary', 'title' => 'Expense Summary', 'icon' => 'mdi-chart-pie'],
        ['group' => 'Expense', 'key' => 'expense_detailed', 'title' => 'Expense Detailed', 'icon' => 'mdi-receipt'],
        ['group' => 'Expense', 'key' => 'payment_mode_expense', 'title' => 'Payment Mode-wise Expense', 'icon' => 'mdi-bank'],

        ['group' => 'Outstanding', 'key' => 'customer_outstanding', 'title' => 'Customer Outstanding', 'icon' => 'mdi-account-alert'],
        ['group' => 'Outstanding', 'key' => 'supplier_outstanding', 'title' => 'Supplier Outstanding', 'icon' => 'mdi-truck-alert'],
        ['group' => 'Outstanding', 'key' => 'customer_ageing', 'title' => 'Customer Ageing', 'icon' => 'mdi-timer-sand'],
        ['group' => 'Outstanding', 'key' => 'supplier_ageing', 'title' => 'Supplier Ageing', 'icon' => 'mdi-timer-sand'],

        ['group' => 'Stock', 'key' => 'current_stock', 'title' => 'Current Stock', 'icon' => 'mdi-warehouse'],
        ['group' => 'Stock', 'key' => 'low_stock', 'title' => 'Low Stock', 'icon' => 'mdi-alert-box'],
        ['group' => 'Stock', 'key' => 'batch_stock', 'title' => 'Batch-wise Stock', 'icon' => 'mdi-barcode'],
        ['group' => 'Stock', 'key' => 'stock_movement', 'title' => 'Stock Movement', 'icon' => 'mdi-swap-horizontal'],
        ['group' => 'Stock', 'key' => 'stock_valuation', 'title' => 'Stock Valuation', 'icon' => 'mdi-currency-inr'],

        ['group' => 'Profit', 'key' => 'gross_profit', 'title' => 'Gross Profit', 'icon' => 'mdi-trending-up'],
        ['group' => 'Profit', 'key' => 'net_profit', 'title' => 'Net Profit', 'icon' => 'mdi-finance'],
        ['group' => 'Profit', 'key' => 'product_profit', 'title' => 'Product Profit', 'icon' => 'mdi-package-variant-closed'],

        ['group' => 'GST', 'key' => 'sales_gst', 'title' => 'Sales GST', 'icon' => 'mdi-percent'],
        ['group' => 'GST', 'key' => 'purchase_gst', 'title' => 'Purchase GST', 'icon' => 'mdi-percent'],
        ['group' => 'GST', 'key' => 'gst_summary', 'title' => 'GST Summary', 'icon' => 'mdi-calculator'],

        ['group' => 'Master', 'key' => 'customer_master', 'title' => 'Customer Master', 'icon' => 'mdi-account-group'],
        ['group' => 'Master', 'key' => 'supplier_master', 'title' => 'Supplier Master', 'icon' => 'mdi-truck'],
        ['group' => 'Master', 'key' => 'product_master', 'title' => 'Product Master', 'icon' => 'mdi-package'],

        ['group' => 'Admin', 'key' => 'user_wise_sales', 'title' => 'User-wise Sales', 'icon' => 'mdi-account-star'],
        ['group' => 'Admin', 'key' => 'cancelled_deleted', 'title' => 'Cancelled / Deleted', 'icon' => 'mdi-delete-alert']
    ];
}

function reportGroupForKey($reportKey)
{
    foreach (reportDefinitions() as $report) {
        if ($report['key'] === $reportKey) {
            return $report['group'];
        }
    }

    return '';
}

function reportModuleKeysForGroup($group)
{
    switch ($group) {
        case 'Sales':
            return ['sales', 'sales_list', 'all_sales_list', 'sales_bill_list', 'final_invoice_list', 'customer_payments'];
        case 'Purchase':
            return ['purchases', 'purchase', 'purchase_list', 'purchase_form', 'supplier_payments'];
        case 'Expense':
            return ['expenses', 'expense', 'expense_list'];
        case 'Outstanding':
            return ['customers', 'suppliers', 'sales', 'purchases'];
        case 'Stock':
            return ['products', 'stock', 'inventory_stock', 'current_stock'];
        case 'Profit':
            return ['sales', 'expenses', 'reports'];
        case 'GST':
            return ['sales', 'purchases', 'reports'];
        case 'Master':
            return ['customers', 'suppliers', 'products'];
        case 'Admin':
            return ['users', 'audit_logs', 'reports'];
        default:
            return [];
    }
}

function canUseAllReportsMenu($action = 1)
{
    return rptCan(allReportsPermissionKeys(), $action);
}

function canAccessReportKey($reportKey)
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (canUseAllReportsMenu(1) || canUseAllReportsMenu(2) || canUseAllReportsMenu(31)) {
        return true;
    }

    $group = reportGroupForKey($reportKey);
    $moduleKeys = reportModuleKeysForGroup($group);

    return rptCan($moduleKeys, 1) || rptCan($moduleKeys, 2);
}

function allowedReports()
{
    $allowed = [];

    foreach (reportDefinitions() as $report) {
        if (canAccessReportKey($report['key'])) {
            $allowed[] = $report;
        }
    }

    return $allowed;
}

function requireAllReportsAccess()
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    if (canUseAllReportsMenu(1) || canUseAllReportsMenu(2) || canUseAllReportsMenu(31) || count(allowedReports()) > 0) {
        return;
    }

    jsonResponse(false, 'Permission denied.');
}

function getAllReportsContext(PDO $pdo)
{
    requireAllReportsAccess();

    $allowedReports = allowedReports();

    jsonResponse(true, 'All reports context loaded.', [
        'context' => [
            'can_view' => canUseAllReportsMenu(1) || canUseAllReportsMenu(2) || count($allowedReports) > 0,
            'can_print' => canUseAllReportsMenu(6),
            'can_export' => canUseAllReportsMenu(7),
            'can_generate_report' => canUseAllReportsMenu(31),
            'reports' => $allowedReports,
            'default_report' => $allowedReports[0]['key'] ?? ''
        ]
    ]);
}

function listAllReportData(PDO $pdo)
{
    requireAllReportsAccess();

    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    $reportKey = cleanInput($_GET['report_key'] ?? 'sales_summary');

    if (!canAccessReportKey($reportKey)) {
        jsonResponse(false, 'Permission denied for selected report.');
    }

    $fromDate = rptDate($_GET['from_date'] ?? date('Y-m-01')) ?: date('Y-m-01');
    $toDate = rptDate($_GET['to_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
    $search = trim((string)($_GET['search'] ?? ''));

    if ($fromDate > $toDate) {
        jsonResponse(false, 'From date cannot be greater than To date.');
    }

    try {
        $data = runReport($pdo, $businessId, $branchId, $reportKey, $fromDate, $toDate, $search);
        $data['filters'] = [
            'report_key' => $reportKey,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'search' => $search
        ];
        $data['context'] = [
            'can_print' => canUseAllReportsMenu(6),
            'can_export' => canUseAllReportsMenu(7),
            'can_generate_report' => canUseAllReportsMenu(31)
        ];

        jsonResponse(true, 'Report loaded.', $data);
    } catch (Throwable $e) {
        jsonResponse(false, $e->getMessage());
    }
}

function runReport(PDO $pdo, $bid, $brid, $key, $from, $to, $search) {
    $p = [$bid, $brid];
    $dateP = [$bid, $brid, $from, $to];
    $q = '%' . $search . '%';

    switch ($key) {
        /* SALES */
        case 'sales_summary':
            return report($pdo,'Sales Summary',['Date','Bills','Taxable','GST','Grand Total','Paid','Due'],
            "SELECT sales_date report_date, COUNT(*) bills, SUM(taxable_amount) taxable, SUM(tax_amount) gst, SUM(grand_total) grand_total, SUM(paid_amount) paid, SUM(due_amount) due FROM sales WHERE business_id=? AND branch_id=? AND sales_date BETWEEN ? AND ? AND status IN (1,2) AND document_type IN (3,4,5) GROUP BY sales_date ORDER BY sales_date",$dateP,['taxable','gst','grand_total','paid','due']);
        case 'sales_detailed':
            $sql="SELECT s.sales_no, s.sales_date, COALESCE(c.customer_name,'Customer') customer, CASE s.document_type WHEN 3 THEN 'Sales Bill' WHEN 4 THEN 'Direct Sale' WHEN 5 THEN 'Final Invoice' ELSE 'Sales' END document_type, s.taxable_amount, s.tax_amount, s.grand_total, s.paid_amount, s.due_amount, CASE s.payment_status WHEN 2 THEN 'Paid' WHEN 1 THEN 'Partial' ELSE 'Unpaid' END payment_status FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.business_id=? AND s.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5)";
            return report($pdo,'Sales Detailed',['Sales No','Date','Customer','Type','Taxable','GST','Grand Total','Paid','Due','Payment'],$sql.searchSql($search,['s.sales_no','c.customer_name','s.reference_no']),addSearch($dateP,$search,3),['taxable_amount','tax_amount','grand_total','paid_amount','due_amount']);
        case 'customer_wise_sales':
            $sql="SELECT COALESCE(c.customer_name,'Customer') customer, COALESCE(c.mobile,'') mobile, COUNT(s.id) bills, SUM(s.grand_total) sales_total, SUM(s.paid_amount) paid_total, SUM(s.due_amount) due_total FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.business_id=? AND s.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5)";
            return report($pdo,'Customer-wise Sales',['Customer','Mobile','Bills','Sales','Paid','Due'],$sql.searchSql($search,['c.customer_name','c.mobile'])." GROUP BY s.customer_id,c.customer_name,c.mobile ORDER BY sales_total DESC",addSearch($dateP,$search,2),['sales_total','paid_total','due_total']);
        case 'product_wise_sales':
            $sql="SELECT si.product_code, si.product_name, si.hsn_code, SUM(si.qty) qty_sold, SUM(si.taxable_amount) taxable, SUM(si.gst_amount) gst, SUM(si.line_total) sales_value, SUM(si.purchase_price*si.qty) purchase_cost, SUM(si.line_total-(si.purchase_price*si.qty)) profit FROM sales_items si INNER JOIN sales s ON s.id=si.sales_id WHERE si.business_id=? AND si.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5) AND si.status=1";
            return report($pdo,'Product-wise Sales',['Code','Product','HSN','Qty','Taxable','GST','Sales Value','Purchase Cost','Profit'],$sql.searchSql($search,['si.product_name','si.product_code','si.hsn_code'])." GROUP BY si.product_id,si.product_code,si.product_name,si.hsn_code ORDER BY sales_value DESC",addSearch($dateP,$search,3),['qty_sold','taxable','gst','sales_value','purchase_cost','profit']);
        case 'sales_due':
            $sql="SELECT COALESCE(c.customer_name,'Customer') customer, s.sales_no, s.sales_date, s.grand_total, s.paid_amount, s.due_amount, DATEDIFF(CURDATE(),s.sales_date) due_days FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.business_id=? AND s.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5) AND s.due_amount>0.009";
            return report($pdo,'Sales Due',['Customer','Sales No','Date','Grand Total','Paid','Due','Due Days'],$sql.searchSql($search,['c.customer_name','s.sales_no'])." ORDER BY s.sales_date",addSearch($dateP,$search,2),['grand_total','paid_amount','due_amount']);
        case 'customer_payment':
            $sql="SELECT cp.payment_no, cp.payment_date, COALESCE(c.customer_name,'Customer') customer, COALESCE(pm.mode_name,'Cash/Bank') payment_mode, CASE WHEN cp.amount>0 THEN cp.amount ELSE cp.payment_amount END amount, cp.reference_no, CASE cp.status WHEN 1 THEN 'Active' ELSE 'Cancelled' END status FROM customer_payments cp LEFT JOIN customers c ON c.id=cp.customer_id LEFT JOIN payment_modes pm ON pm.id=cp.payment_mode_id WHERE cp.business_id=? AND cp.branch_id=? AND cp.payment_date BETWEEN ? AND ?";
            return report($pdo,'Customer Payment',['Payment No','Date','Customer','Mode','Amount','Reference','Status'],$sql.searchSql($search,['cp.payment_no','c.customer_name','cp.reference_no'])." ORDER BY cp.payment_date DESC, cp.id DESC",addSearch($dateP,$search,3),['amount']);

        /* PURCHASE */
        case 'purchase_summary':
            return report($pdo,'Purchase Summary',['Date','Bills','Taxable','GST','Grand Total','Paid','Due'],"SELECT purchase_date report_date, COUNT(*) bills, SUM(sub_total) taxable, SUM(tax_amount) gst, SUM(grand_total) grand_total, SUM(paid_amount) paid, SUM(due_amount) due FROM purchases WHERE business_id=? AND branch_id=? AND purchase_date BETWEEN ? AND ? AND status=1 GROUP BY purchase_date ORDER BY purchase_date",$dateP,['taxable','gst','grand_total','paid','due']);
        case 'purchase_detailed':
            $sql="SELECT p.bill_no, p.purchase_date, COALESCE(s.supplier_name,'Supplier') supplier, p.batch_no, p.sub_total taxable, p.tax_amount gst, p.grand_total, p.paid_amount, p.due_amount, CASE p.payment_status WHEN 3 THEN 'Paid' WHEN 2 THEN 'Partial' ELSE 'Unpaid' END payment_status FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.business_id=? AND p.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND p.status=1";
            return report($pdo,'Purchase Detailed',['Bill No','Date','Supplier','Batch','Taxable','GST','Grand Total','Paid','Due','Payment'],$sql.searchSql($search,['p.bill_no','p.batch_no','s.supplier_name'])." ORDER BY p.purchase_date DESC,p.id DESC",addSearch($dateP,$search,3),['taxable','gst','grand_total','paid_amount','due_amount']);
        case 'supplier_wise_purchase':
            $sql="SELECT COALESCE(s.supplier_name,'Supplier') supplier, COALESCE(s.mobile,'') mobile, COUNT(p.id) bills, SUM(p.grand_total) purchase_total, SUM(p.paid_amount) paid_total, SUM(p.due_amount) due_total FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.business_id=? AND p.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND p.status=1";
            return report($pdo,'Supplier-wise Purchase',['Supplier','Mobile','Bills','Purchase','Paid','Due'],$sql.searchSql($search,['s.supplier_name','s.mobile'])." GROUP BY p.supplier_id,s.supplier_name,s.mobile ORDER BY purchase_total DESC",addSearch($dateP,$search,2),['purchase_total','paid_total','due_total']);
        case 'product_wise_purchase':
            $sql="SELECT pi.product_code, pi.product_name, p.bill_no, p.batch_no, COALESCE(s.supplier_name,'Supplier') supplier, SUM(pi.qty) qty_purchased, SUM(pi.taxable_amount) taxable, SUM(pi.gst_amount) gst, SUM(pi.line_total) purchase_value FROM purchase_items pi INNER JOIN purchases p ON p.id=pi.purchase_id LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE pi.business_id=? AND pi.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND pi.status=1 AND p.status=1";
            return report($pdo,'Product-wise Purchase',['Code','Product','Bill','Batch','Supplier','Qty','Taxable','GST','Purchase Value'],$sql.searchSql($search,['pi.product_name','pi.product_code','p.bill_no','s.supplier_name'])." GROUP BY pi.product_id,pi.product_code,pi.product_name,p.bill_no,p.batch_no,s.supplier_name ORDER BY purchase_value DESC",addSearch($dateP,$search,4),['qty_purchased','taxable','gst','purchase_value']);
        case 'purchase_due':
            $sql="SELECT COALESCE(s.supplier_name,'Supplier') supplier, p.bill_no, p.purchase_date, p.grand_total, p.paid_amount, p.due_amount, DATEDIFF(CURDATE(),p.purchase_date) due_days FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.business_id=? AND p.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND p.status=1 AND p.due_amount>0.009";
            return report($pdo,'Purchase Due',['Supplier','Bill No','Date','Grand Total','Paid','Due','Due Days'],$sql.searchSql($search,['s.supplier_name','p.bill_no'])." ORDER BY p.purchase_date",addSearch($dateP,$search,2),['grand_total','paid_amount','due_amount']);
        case 'supplier_payment':
            $sql="SELECT sp.payment_no, sp.payment_date, COALESCE(s.supplier_name,'Supplier') supplier, CASE sp.payment_type WHEN 2 THEN 'Individual' WHEN 3 THEN 'Opening Outstanding' ELSE 'Overall' END payment_type, COALESCE(pm.mode_name,'Cash/Bank') payment_mode, COALESCE(sps.amount,sp.total_amount) amount, COALESCE(sps.reference_no,'') reference_no, CASE sp.status WHEN 1 THEN 'Active' ELSE 'Cancelled' END status FROM supplier_payments sp LEFT JOIN suppliers s ON s.id=sp.supplier_id LEFT JOIN supplier_payment_splits sps ON sps.supplier_payment_id=sp.id AND sps.status=1 LEFT JOIN payment_modes pm ON pm.id=sps.payment_mode_id WHERE sp.business_id=? AND sp.branch_id=? AND sp.payment_date BETWEEN ? AND ?";
            return report($pdo,'Supplier Payment',['Payment No','Date','Supplier','Type','Mode','Amount','Reference','Status'],$sql.searchSql($search,['sp.payment_no','s.supplier_name','sps.reference_no'])." ORDER BY sp.payment_date DESC,sp.id DESC",addSearch($dateP,$search,3),['amount']);

        /* EXPENSE */
        case 'expense_summary':
            $sql="SELECT COALESCE(ec.category_name,'Expense') category, COUNT(e.id) entries, SUM(e.taxable_amount) taxable, SUM(e.gst_amount) gst, SUM(CASE WHEN e.total_amount>0 THEN e.total_amount ELSE e.amount END) total FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.expense_category_id WHERE e.business_id=? AND e.branch_id=? AND e.expense_date BETWEEN ? AND ? AND e.status=1";
            return report($pdo,'Expense Summary',['Category','Entries','Taxable','GST','Total'],$sql.searchSql($search,['ec.category_name'])." GROUP BY e.expense_category_id,ec.category_name ORDER BY total DESC",addSearch($dateP,$search,1),['taxable','gst','total']);
        case 'expense_detailed':
            $sql="SELECT e.expense_no, e.expense_date, COALESCE(ec.category_name,'Expense') category, COALESCE(e.vendor_name,'') vendor, COALESCE(pm.mode_name,'Cash/Bank') payment_mode, e.taxable_amount, e.gst_amount, CASE WHEN eps.amount IS NOT NULL THEN eps.amount WHEN e.total_amount>0 THEN e.total_amount ELSE e.amount END total, COALESCE(e.notes,e.description,'') notes FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.expense_category_id LEFT JOIN expense_payment_splits eps ON eps.expense_id=e.id AND eps.status=1 LEFT JOIN payment_modes pm ON pm.id=eps.payment_mode_id WHERE e.business_id=? AND e.branch_id=? AND e.expense_date BETWEEN ? AND ? AND e.status=1";
            return report($pdo,'Expense Detailed',['Expense No','Date','Category','Vendor','Mode','Taxable','GST','Total','Notes'],$sql.searchSql($search,['e.expense_no','e.vendor_name','ec.category_name','e.reference_no'])." ORDER BY e.expense_date DESC,e.id DESC",addSearch($dateP,$search,4),['taxable_amount','gst_amount','total']);
        case 'payment_mode_expense':
            return report($pdo,'Payment Mode-wise Expense',['Payment Mode','Entries','Total'],"SELECT COALESCE(pm.mode_name,'Cash/Bank') payment_mode, COUNT(DISTINCT e.id) entries, SUM(CASE WHEN eps.amount IS NOT NULL THEN eps.amount WHEN e.total_amount>0 THEN e.total_amount ELSE e.amount END) total FROM expenses e LEFT JOIN expense_payment_splits eps ON eps.expense_id=e.id AND eps.status=1 LEFT JOIN payment_modes pm ON pm.id=eps.payment_mode_id WHERE e.business_id=? AND e.branch_id=? AND e.expense_date BETWEEN ? AND ? AND e.status=1 GROUP BY pm.mode_name ORDER BY total DESC",$dateP,['total']);

        /* OUTSTANDING */
        case 'customer_outstanding':
            $sql="SELECT c.customer_name, c.mobile, COALESCE(c.opening_outstanding,0) opening_outstanding, COALESCE(SUM(s.grand_total),0) sales_total, COALESCE(SUM(s.paid_amount),0) paid_total, COALESCE(c.current_outstanding,0) current_outstanding FROM customers c LEFT JOIN sales s ON s.customer_id=c.id AND s.status IN (1,2) AND s.document_type IN (3,4,5) WHERE c.business_id=? AND c.branch_id=? AND c.status=1";
            return report($pdo,'Customer Outstanding',['Customer','Mobile','Opening','Sales','Paid','Current Outstanding'],$sql.searchSql($search,['c.customer_name','c.mobile'])." GROUP BY c.id,c.customer_name,c.mobile,c.opening_outstanding,c.current_outstanding ORDER BY c.current_outstanding DESC",addSearch($p,$search,2),['opening_outstanding','sales_total','paid_total','current_outstanding']);
        case 'supplier_outstanding':
            $sql="SELECT s.supplier_name, s.mobile, COALESCE(s.opening_outstanding,0) opening_outstanding, COALESCE(SUM(p.grand_total),0) purchase_total, COALESCE(SUM(p.paid_amount),0) paid_total, COALESCE(s.current_outstanding,0) current_outstanding FROM suppliers s LEFT JOIN purchases p ON p.supplier_id=s.id AND p.status=1 WHERE s.business_id=? AND s.branch_id=? AND s.status=1";
            return report($pdo,'Supplier Outstanding',['Supplier','Mobile','Opening','Purchase','Paid','Current Outstanding'],$sql.searchSql($search,['s.supplier_name','s.mobile'])." GROUP BY s.id,s.supplier_name,s.mobile,s.opening_outstanding,s.current_outstanding ORDER BY s.current_outstanding DESC",addSearch($p,$search,2),['opening_outstanding','purchase_total','paid_total','current_outstanding']);
        case 'customer_ageing':
            $sql="SELECT COALESCE(c.customer_name,'Customer') customer, SUM(CASE WHEN DATEDIFF(CURDATE(),s.sales_date) BETWEEN 0 AND 30 THEN s.due_amount ELSE 0 END) days_0_30, SUM(CASE WHEN DATEDIFF(CURDATE(),s.sales_date) BETWEEN 31 AND 60 THEN s.due_amount ELSE 0 END) days_31_60, SUM(CASE WHEN DATEDIFF(CURDATE(),s.sales_date) BETWEEN 61 AND 90 THEN s.due_amount ELSE 0 END) days_61_90, SUM(CASE WHEN DATEDIFF(CURDATE(),s.sales_date)>90 THEN s.due_amount ELSE 0 END) days_90_plus, SUM(s.due_amount) total_due FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.business_id=? AND s.branch_id=? AND s.status IN (1,2) AND s.document_type IN (3,4,5) AND s.due_amount>0.009";
            return report($pdo,'Customer Ageing',['Customer','0-30','31-60','61-90','90+','Total Due'],$sql.searchSql($search,['c.customer_name','s.sales_no'])." GROUP BY s.customer_id,c.customer_name ORDER BY total_due DESC",addSearch($p,$search,2),['days_0_30','days_31_60','days_61_90','days_90_plus','total_due']);
        case 'supplier_ageing':
            $sql="SELECT COALESCE(s.supplier_name,'Supplier') supplier, SUM(CASE WHEN DATEDIFF(CURDATE(),p.purchase_date) BETWEEN 0 AND 30 THEN p.due_amount ELSE 0 END) days_0_30, SUM(CASE WHEN DATEDIFF(CURDATE(),p.purchase_date) BETWEEN 31 AND 60 THEN p.due_amount ELSE 0 END) days_31_60, SUM(CASE WHEN DATEDIFF(CURDATE(),p.purchase_date) BETWEEN 61 AND 90 THEN p.due_amount ELSE 0 END) days_61_90, SUM(CASE WHEN DATEDIFF(CURDATE(),p.purchase_date)>90 THEN p.due_amount ELSE 0 END) days_90_plus, SUM(p.due_amount) total_due FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.business_id=? AND p.branch_id=? AND p.status=1 AND p.due_amount>0.009";
            return report($pdo,'Supplier Ageing',['Supplier','0-30','31-60','61-90','90+','Total Due'],$sql.searchSql($search,['s.supplier_name','p.bill_no'])." GROUP BY p.supplier_id,s.supplier_name ORDER BY total_due DESC",addSearch($p,$search,2),['days_0_30','days_31_60','days_61_90','days_90_plus','total_due']);

        /* STOCK */
        case 'current_stock':
            $sql="SELECT pr.product_code, pr.product_name, COALESCE(c.category_name,'') category, COALESCE(SUM(pi.available_qty),0) current_stock, COALESCE(pr.cost_price,0) purchase_rate, COALESCE(pr.retail_price,0) selling_rate, COALESCE(SUM(pi.available_qty),0)*COALESCE(pr.cost_price,0) stock_value FROM products pr LEFT JOIN categories c ON c.id=pr.category_id LEFT JOIN purchase_items pi ON pi.product_id=pr.id AND pi.status=1 WHERE pr.business_id=? AND pr.branch_id=? AND pr.status=1";
            return report($pdo,'Current Stock',['Code','Product','Category','Stock','Purchase Rate','Selling Rate','Stock Value'],$sql.searchSql($search,['pr.product_name','pr.product_code','c.category_name'])." GROUP BY pr.id,pr.product_code,pr.product_name,c.category_name,pr.cost_price,pr.retail_price ORDER BY pr.product_name",addSearch($p,$search,3),['current_stock','stock_value']);
        case 'low_stock':
            $sql="SELECT pr.product_code, pr.product_name, COALESCE(SUM(pi.available_qty),0) current_stock, COALESCE(pr.minimum_stock,0) minimum_stock, GREATEST(COALESCE(pr.minimum_stock,0)-COALESCE(SUM(pi.available_qty),0),0) required_qty FROM products pr LEFT JOIN purchase_items pi ON pi.product_id=pr.id AND pi.status=1 WHERE pr.business_id=? AND pr.branch_id=? AND pr.status=1";
            return report($pdo,'Low Stock',['Code','Product','Current Stock','Minimum Stock','Required Qty'],$sql.searchSql($search,['pr.product_name','pr.product_code'])." GROUP BY pr.id,pr.product_code,pr.product_name,pr.minimum_stock HAVING current_stock <= minimum_stock ORDER BY required_qty DESC",addSearch($p,$search,2),['current_stock','required_qty']);
        case 'batch_stock':
            $sql="SELECT pi.product_code, pi.product_name, p.bill_no, p.batch_no, p.purchase_date, pi.stock_qty purchase_qty, pi.sold_qty, pi.available_qty FROM purchase_items pi INNER JOIN purchases p ON p.id=pi.purchase_id WHERE pi.business_id=? AND pi.branch_id=? AND pi.status=1 AND p.status=1";
            return report($pdo,'Batch-wise Stock',['Code','Product','Bill','Batch','Purchase Date','Purchase Qty','Sold Qty','Available Qty'],$sql.searchSql($search,['pi.product_name','pi.product_code','p.bill_no','p.batch_no'])." ORDER BY p.purchase_date DESC",addSearch($p,$search,4),['purchase_qty','sold_qty','available_qty']);
        case 'stock_movement':
            $sql="SELECT p.purchase_date trans_date, pi.product_name, 'Purchase In' trans_type, p.bill_no reference_no, pi.stock_qty in_qty, 0 out_qty FROM purchase_items pi INNER JOIN purchases p ON p.id=pi.purchase_id WHERE pi.business_id=? AND pi.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND pi.status=1 AND p.status=1".searchSql($search,['pi.product_name','pi.product_code','p.bill_no'])." UNION ALL SELECT s.sales_date trans_date, si.product_name, 'Sales Out' trans_type, s.sales_no reference_no, 0 in_qty, si.qty out_qty FROM sales_items si INNER JOIN sales s ON s.id=si.sales_id WHERE si.business_id=? AND si.branch_id=? AND s.sales_date BETWEEN ? AND ? AND si.status=1 AND s.status IN (1,2) AND s.document_type IN (3,4,5)".searchSql($search,['si.product_name','si.product_code','s.sales_no'])." ORDER BY trans_date";
            return report($pdo,'Stock Movement',['Date','Product','Type','Reference','In Qty','Out Qty'],$sql,array_merge(addSearch($dateP,$search,3),addSearch($dateP,$search,3)),['in_qty','out_qty']);
        case 'stock_valuation':
            $sql="SELECT pr.product_code, pr.product_name, COALESCE(SUM(pi.available_qty),0) available_qty, pr.cost_price purchase_rate, pr.retail_price selling_rate, COALESCE(SUM(pi.available_qty),0)*pr.cost_price purchase_stock_value, COALESCE(SUM(pi.available_qty),0)*pr.retail_price sales_stock_value, (COALESCE(SUM(pi.available_qty),0)*pr.retail_price)-(COALESCE(SUM(pi.available_qty),0)*pr.cost_price) potential_profit FROM products pr LEFT JOIN purchase_items pi ON pi.product_id=pr.id AND pi.status=1 WHERE pr.business_id=? AND pr.branch_id=? AND pr.status=1";
            return report($pdo,'Stock Valuation',['Code','Product','Qty','Purchase Rate','Selling Rate','Purchase Value','Sales Value','Potential Profit'],$sql.searchSql($search,['pr.product_name','pr.product_code'])." GROUP BY pr.id,pr.product_code,pr.product_name,pr.cost_price,pr.retail_price ORDER BY purchase_stock_value DESC",addSearch($p,$search,2),['available_qty','purchase_stock_value','sales_stock_value','potential_profit']);

        /* PROFIT/GST/MASTER/ADMIN */
        case 'gross_profit':
        case 'product_profit':
            return runReport($pdo,$bid,$brid,'product_wise_sales',$from,$to,$search);
        case 'net_profit':
            $sales=scalar($pdo,"SELECT COALESCE(SUM(line_total),0) FROM sales_items si INNER JOIN sales s ON s.id=si.sales_id WHERE si.business_id=? AND si.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5) AND si.status=1",$dateP);
            $cost=scalar($pdo,"SELECT COALESCE(SUM(si.purchase_price*si.qty),0) FROM sales_items si INNER JOIN sales s ON s.id=si.sales_id WHERE si.business_id=? AND si.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5) AND si.status=1",$dateP);
            $expense=scalar($pdo,"SELECT COALESCE(SUM(CASE WHEN total_amount>0 THEN total_amount ELSE amount END),0) FROM expenses WHERE business_id=? AND branch_id=? AND expense_date BETWEEN ? AND ? AND status=1",$dateP);
            return build('Net Profit',['Particular','Amount'],[['particular'=>'Sales Total','amount'=>$sales],['particular'=>'Purchase Cost','amount'=>$cost],['particular'=>'Gross Profit','amount'=>$sales-$cost],['particular'=>'Expenses','amount'=>$expense],['particular'=>'Net Profit','amount'=>$sales-$cost-$expense]],['amount']);
        case 'sales_gst':
            $sql="SELECT s.sales_no, s.sales_date, COALESCE(c.customer_name,'Customer') customer, COALESCE(c.gst_number,'') gst_number, s.taxable_amount, s.cgst_amount, s.sgst_amount, s.igst_amount, s.tax_amount, s.grand_total FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.business_id=? AND s.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5)";
            return report($pdo,'Sales GST',['Sales No','Date','Customer','GST No','Taxable','CGST','SGST','IGST','GST','Grand Total'],$sql.searchSql($search,['s.sales_no','c.customer_name'])." ORDER BY s.sales_date DESC",addSearch($dateP,$search,2),['taxable_amount','cgst_amount','sgst_amount','igst_amount','tax_amount','grand_total']);
        case 'purchase_gst':
            $sql="SELECT p.bill_no, p.purchase_date, COALESCE(s.supplier_name,'Supplier') supplier, COALESCE(s.gst_number,'') gst_number, p.sub_total taxable, p.tax_amount gst, p.grand_total FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.business_id=? AND p.branch_id=? AND p.purchase_date BETWEEN ? AND ? AND p.status=1";
            return report($pdo,'Purchase GST',['Bill No','Date','Supplier','GST No','Taxable','GST','Grand Total'],$sql.searchSql($search,['p.bill_no','s.supplier_name'])." ORDER BY p.purchase_date DESC",addSearch($dateP,$search,2),['taxable','gst','grand_total']);
        case 'gst_summary':
            $out=scalar($pdo,"SELECT COALESCE(SUM(tax_amount),0) FROM sales WHERE business_id=? AND branch_id=? AND sales_date BETWEEN ? AND ? AND status IN (1,2) AND document_type IN (3,4,5)",$dateP);
            $inp=scalar($pdo,"SELECT COALESCE(SUM(tax_amount),0) FROM purchases WHERE business_id=? AND branch_id=? AND purchase_date BETWEEN ? AND ? AND status=1",$dateP);
            return build('GST Summary',['Particular','Amount'],[['particular'=>'Output GST','amount'=>$out],['particular'=>'Input GST','amount'=>$inp],['particular'=>'Net GST Payable','amount'=>$out-$inp]],['amount']);
        case 'customer_master':
            $sql="SELECT customer_name, mobile, gst_number, opening_outstanding, current_outstanding, CASE status WHEN 1 THEN 'Active' ELSE 'Inactive' END status FROM customers WHERE business_id=? AND branch_id=?";
            return report($pdo,'Customer Master',['Customer','Mobile','GST','Opening','Current Outstanding','Status'],$sql.searchSql($search,['customer_name','mobile','gst_number'])." ORDER BY customer_name",addSearch($p,$search,3),['opening_outstanding','current_outstanding']);
        case 'supplier_master':
            $sql="SELECT supplier_name, mobile, gst_number, opening_outstanding, current_outstanding, CASE status WHEN 1 THEN 'Active' ELSE 'Inactive' END status FROM suppliers WHERE business_id=? AND branch_id=?";
            return report($pdo,'Supplier Master',['Supplier','Mobile','GST','Opening','Current Outstanding','Status'],$sql.searchSql($search,['supplier_name','mobile','gst_number'])." ORDER BY supplier_name",addSearch($p,$search,3),['opening_outstanding','current_outstanding']);
        case 'product_master':
            $sql="SELECT pr.product_code, pr.product_name, COALESCE(c.category_name,'') category, COALESCE(h.hsn_code,'') hsn_code, pr.cost_price, pr.retail_price, pr.wholesale_price, pr.minimum_stock, CASE pr.status WHEN 1 THEN 'Active' ELSE 'Inactive' END status FROM products pr LEFT JOIN categories c ON c.id=pr.category_id LEFT JOIN hsn_codes h ON h.id=pr.hsn_id WHERE pr.business_id=? AND pr.branch_id=?";
            return report($pdo,'Product Master',['Code','Product','Category','HSN','Cost','Retail','Wholesale','Minimum Stock','Status'],$sql.searchSql($search,['pr.product_name','pr.product_code','c.category_name','h.hsn_code'])." ORDER BY pr.product_name",addSearch($p,$search,4),['cost_price','retail_price','wholesale_price','minimum_stock']);
        case 'user_wise_sales':
            $sql="SELECT COALESCE(u.name,u.username,'User') user_name, COUNT(s.id) bills, SUM(s.grand_total) sales_amount, SUM(s.paid_amount) collection_amount FROM sales s LEFT JOIN users u ON u.id=s.created_by WHERE s.business_id=? AND s.branch_id=? AND s.sales_date BETWEEN ? AND ? AND s.status IN (1,2) AND s.document_type IN (3,4,5)";
            return report($pdo,'User-wise Sales',['User','Bills','Sales','Collection'],$sql.searchSql($search,['u.name','u.username'])." GROUP BY s.created_by,u.name,u.username ORDER BY sales_amount DESC",addSearch($dateP,$search,2),['sales_amount','collection_amount']);
        case 'cancelled_deleted':
            $sql="SELECT sales_date entry_date, 'Sales' module, sales_no reference_no, grand_total amount, CASE status WHEN 3 THEN 'Deleted' WHEN 4 THEN 'Cancelled' ELSE 'Inactive' END action_status, COALESCE(cancel_reason,delete_reason,'') reason FROM sales WHERE business_id=? AND branch_id=? AND sales_date BETWEEN ? AND ? AND status IN (3,4)";
            return report($pdo,'Cancelled / Deleted',['Date','Module','Reference','Amount','Status','Reason'],$sql.searchSql($search,['sales_no','cancel_reason','delete_reason'])." ORDER BY sales_date DESC",addSearch($dateP,$search,3),['amount']);
    }
    throw new Exception('Report not found.');
}

function searchSql($search, array $fields) {
    if ($search === '') return '';
    return ' AND (' . implode(' OR ', array_map(fn($f)=>"$f LIKE ?", $fields)) . ') ';
}
function addSearch(array $params, $search, $count) {
    if ($search !== '') { for($i=0;$i<$count;$i++) $params[]='%'.$search.'%'; }
    return $params;
}
function report(PDO $pdo, $title, $cols, $sql, array $params, array $sum=[]) {
    $stmt=$pdo->prepare($sql); $stmt->execute($params); return build($title,$cols,$stmt->fetchAll(PDO::FETCH_ASSOC),$sum);
}
function build($title, $cols, array $rows, array $sum=[]) {
    $tot=[]; foreach($sum as $s) $tot[$s]=0;
    foreach($rows as &$r){ foreach($r as $k=>$v){ if(is_numeric($v) && !in_array($k,['id','bills','entries','due_days'],true)) $r[$k]=round((float)$v,2); } foreach($tot as $k=>$v){ $tot[$k]+= (float)($r[$k]??0); } } unset($r);
    foreach($tot as $k=>$v) $tot[$k]=round($v,2);
    return ['title'=>$title,'columns'=>$cols,'rows'=>$rows,'totals'=>$tot,'count'=>count($rows)];
}
function scalar(PDO $pdo, $sql, array $params){$st=$pdo->prepare($sql);$st->execute($params);return round((float)$st->fetchColumn(),2);} 
function rptDate($v){$v=trim((string)$v);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null;}
