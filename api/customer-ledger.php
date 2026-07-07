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
    case 'get_page_context':
        getCustomerLedgerPageContext($pdo);
        break;

    case 'search_customers':
        searchLedgerCustomers($pdo);
        break;

    case 'list_ledger':
        listCustomerLedger($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
}

function clActionCode($action)
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

function clCurrentRoleIds()
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

    if (function_exists('currentUserId') && isset($pdo) && $pdo instanceof PDO) {
        $userId = (int)currentUserId();
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $dbRoleId = (int)$stmt->fetchColumn();
                if ($dbRoleId > 0) {
                    $roleIds[] = $dbRoleId;
                }
            } catch (Throwable $e) {}
        }
    }

    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

    if ($roleIds && isset($pdo) && $pdo instanceof PDO && clTableExists($pdo, 'roles')) {
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
        } catch (Throwable $e) {}
    }

    return array_values(array_unique(array_filter(array_map('intval', $roleIds))));
}

function clCan($moduleKeys, $action = 1)
{
    global $pdo;

    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = clCurrentRoleIds();
    $actionCode = clActionCode($action);

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [':action_code' => (string)(int)$actionCode];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = (int)$roleId;
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

function clLedgerKeys()
{
    return ['customer_ledger', 'customer-ledger'];
}

function clCustomerKeys()
{
    return ['customers'];
}

function clRequireLedgerAccess()
{
    if (!clCan(clLedgerKeys(), 1) && !clCan(clLedgerKeys(), 2)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function clScope()
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

function getCustomerLedgerPageContext(PDO $pdo)
{
    clRequireLedgerAccess();

    jsonResponse(true, 'Customer ledger context loaded.', [
        'context' => [
            'can_view' => clCan(clLedgerKeys(), 1),
            'can_list' => clCan(clLedgerKeys(), 2),
            'can_print' => clCan(clLedgerKeys(), 6),
            'can_export' => clCan(clLedgerKeys(), 7),
            'can_generate_report' => clCan(clLedgerKeys(), 31),
            'can_customers' => clCan(clCustomerKeys(), 1) || clCan(clCustomerKeys(), 2),
            'customers_url' => BASE_URL . 'pages/customers.php',
            'page_title' => 'Customer Ledger',
            'page_note' => 'Debit / Credit / Balance statement'
        ]
    ]);
}

function searchLedgerCustomers(PDO $pdo)
{
    clRequireLedgerAccess();

    $scope = clScope();
    $q = cleanInput($_GET['q'] ?? '');

    $where = "business_id = :business_id AND branch_id = :branch_id AND status = 1";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($q !== '') {
        $where .= " AND (customer_name LIKE :q_customer OR mobile LIKE :q_mobile OR gst_number LIKE :q_gst)";
        $params[':q_customer'] = '%' . $q . '%';
        $params[':q_mobile'] = '%' . $q . '%';
        $params[':q_gst'] = '%' . $q . '%';
    }

    $stmt = $pdo->prepare("
        SELECT id, customer_name, mobile, gst_number
        FROM customers
        WHERE $where
        ORDER BY customer_name ASC
        LIMIT 500
    ");
    $stmt->execute($params);

    jsonResponse(true, 'Customers loaded.', [
        'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function listCustomerLedger(PDO $pdo)
{
    clRequireLedgerAccess();

    $scope = clScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);

    if ($customerId <= 0) {
        jsonResponse(false, 'Please select customer.');
    }

    $fromDate = clCleanDate($_GET['from_date'] ?? '');
    $toDate = clCleanDate($_GET['to_date'] ?? '');
    $documentTypes = clParseDocumentTypes($_GET['document_types'] ?? []);

    if (!$fromDate) {
        $fromDate = null;
    }

    if (!$toDate) {
        $toDate = null;
    }

    if ($fromDate && $toDate && $fromDate > $toDate) {
        jsonResponse(false, 'From date cannot be greater than To date.');
    }

    $customer = clFetchCustomer($pdo, $scope, $customerId);
    if (!$customer) {
        jsonResponse(false, 'Customer not found.');
    }

    $rows = [];
    $runningBalance = 0.0;

    $openingBalance = clOpeningBalance($customer);

    if ($fromDate) {
        $openingBalance += clSalesAmountBefore($pdo, $scope, $customerId, $documentTypes, $fromDate);
        if (in_array(99, $documentTypes, true)) {
            $openingBalance -= clPaymentAmountBefore($pdo, $scope, $customerId, $fromDate);
        }
        $openingParticular = 'Opening Balance as on ' . $fromDate;
        $openingDate = $fromDate;
    } else {
        $openingParticular = 'Opening Balance';
        $openingDate = $customer['created_at'] ? substr((string)$customer['created_at'], 0, 10) : date('Y-m-d');
    }

    if (abs($openingBalance) > 0.009) {
        $runningBalance = round($openingBalance, 2);
        $rows[] = [
            'entry_date' => $openingDate,
            'particular' => $openingParticular,
            'reference_no' => '-',
            'document_type' => 0,
            'document_label' => 'Opening',
            'debit' => $openingBalance > 0 ? round($openingBalance, 2) : 0,
            'credit' => $openingBalance < 0 ? round(abs($openingBalance), 2) : 0,
            'balance' => $runningBalance,
            'sort_key' => $openingDate . '-000000'
        ];
    }

    $entries = [];

    $salesTypes = array_values(array_intersect($documentTypes, [1, 2, 3, 4, 5]));
    if ($salesTypes) {
        $entries = array_merge($entries, clSalesRows($pdo, $scope, $customerId, $fromDate, $toDate, $salesTypes));
    }

    if (in_array(99, $documentTypes, true)) {
        $entries = array_merge($entries, clPaymentRows($pdo, $scope, $customerId, $fromDate, $toDate));
    }

    usort($entries, function ($a, $b) {
        $cmp = strcmp($a['entry_date'], $b['entry_date']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a['sort_key'], $b['sort_key']);
    });

    foreach ($entries as $entry) {
        $debit = round((float)($entry['debit'] ?? 0), 2);
        $credit = round((float)($entry['credit'] ?? 0), 2);
        $runningBalance = round($runningBalance + $debit - $credit, 2);
        $entry['debit'] = $debit;
        $entry['credit'] = $credit;
        $entry['balance'] = $runningBalance;
        $rows[] = $entry;
    }

    $summary = [
        'entry_count' => count($rows),
        'total_debit' => 0,
        'total_credit' => 0,
        'closing_balance' => $runningBalance
    ];

    foreach ($rows as $row) {
        $summary['total_debit'] += (float)($row['debit'] ?? 0);
        $summary['total_credit'] += (float)($row['credit'] ?? 0);
    }

    $summary['total_debit'] = round($summary['total_debit'], 2);
    $summary['total_credit'] = round($summary['total_credit'], 2);
    $summary['closing_balance'] = round($summary['closing_balance'], 2);

    jsonResponse(true, 'Ledger loaded.', [
        'customer' => $customer,
        'entries' => $rows,
        'summary' => $summary
    ]);
}

function clCleanDate($date)
{
    $date = trim((string)$date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

function clParseDocumentTypes($raw)
{
    if (is_array($raw)) {
        $values = $raw;
    } else {
        $raw = trim((string)$raw);
        if ($raw === '') {
            $values = [1, 2, 3, 4, 5, 99];
        } else {
            $values = explode(',', $raw);
        }
    }

    $allowed = [1, 2, 3, 4, 5, 99];
    $selected = [];

    foreach ($values as $value) {
        $type = (int)$value;
        if (in_array($type, $allowed, true) && !in_array($type, $selected, true)) {
            $selected[] = $type;
        }
    }

    if (!$selected) {
        return $allowed;
    }

    return $selected;
}

function clFetchCustomer(PDO $pdo, array $scope, $customerId)
{
    $select = [
        'id', 'customer_name', 'mobile', 'email', 'gst_number', 'address', 'city', 'state', 'pincode', 'status', 'created_at'
    ];

    foreach (['opening_outstanding', 'opening_balance', 'opening_paid', 'opening_due', 'current_outstanding'] as $column) {
        if (clColumnExists($pdo, 'customers', $column)) {
            $select[] = $column;
        }
    }

    $stmt = $pdo->prepare("
        SELECT " . implode(', ', $select) . "
        FROM customers
        WHERE id = :customer_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':customer_id' => $customerId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function clOpeningBalance(array $customer)
{
    if (array_key_exists('opening_outstanding', $customer)) {
        return round((float)$customer['opening_outstanding'], 2);
    }

    if (array_key_exists('opening_balance', $customer)) {
        return round((float)$customer['opening_balance'], 2);
    }

    return 0.0;
}

function clSalesAmountBefore(PDO $pdo, array $scope, $customerId, array $documentTypes, $beforeDate)
{
    $types = array_values(array_intersect($documentTypes, [1, 2, 3, 4, 5]));
    if (!$types) {
        return 0.0;
    }

    $holders = [];
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId,
        ':before_date' => $beforeDate
    ];

    foreach ($types as $index => $type) {
        $key = ':doc_' . $index;
        $holders[] = $key;
        $params[$key] = $type;
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(grand_total), 0)
        FROM sales
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND customer_id = :customer_id
        AND sales_date < :before_date
        AND status IN (1, 2)
        AND document_type IN (" . implode(',', $holders) . ")
    ");
    $stmt->execute($params);

    return round((float)$stmt->fetchColumn(), 2);
}

function clPaymentAmountBefore(PDO $pdo, array $scope, $customerId, $beforeDate)
{
    if (!clTableExists($pdo, 'customer_payments')) {
        return 0.0;
    }

    $amountExpr = clCustomerPaymentAmountExpression($pdo, 'cp');

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM($amountExpr), 0)
        FROM customer_payments cp
        WHERE cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        AND cp.customer_id = :customer_id
        AND cp.payment_date < :before_date
        AND cp.status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId,
        ':before_date' => $beforeDate
    ]);

    return round((float)$stmt->fetchColumn(), 2);
}

function clSalesRows(PDO $pdo, array $scope, $customerId, $fromDate, $toDate, array $documentTypes)
{
    $holders = [];
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ];

    foreach ($documentTypes as $index => $type) {
        $key = ':doc_' . $index;
        $holders[] = $key;
        $params[$key] = $type;
    }

    $where = "
        s.business_id = :business_id
        AND s.branch_id = :branch_id
        AND s.customer_id = :customer_id
        AND s.status IN (1, 2)
        AND s.document_type IN (" . implode(',', $holders) . ")
    ";

    if ($fromDate) {
        $where .= " AND s.sales_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND s.sales_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.sales_no,
            s.document_type,
            s.sales_date,
            s.grand_total,
            s.notes
        FROM sales s
        WHERE $where
        ORDER BY s.sales_date ASC, s.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $docType = (int)$row['document_type'];
        $rows[] = [
            'entry_date' => $row['sales_date'],
            'particular' => clDocumentLabel($docType),
            'reference_no' => $row['sales_no'],
            'document_type' => $docType,
            'document_label' => clDocumentLabel($docType),
            'debit' => round((float)$row['grand_total'], 2),
            'credit' => 0,
            'balance' => 0,
            'sort_key' => $row['sales_date'] . '-' . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT) . '-S'
        ];
    }

    return $rows;
}

function clPaymentRows(PDO $pdo, array $scope, $customerId, $fromDate, $toDate)
{
    if (!clTableExists($pdo, 'customer_payments')) {
        return [];
    }

    $amountExpr = clCustomerPaymentAmountExpression($pdo, 'cp');

    $where = "
        cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        AND cp.customer_id = :customer_id
        AND cp.status = 1
    ";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ];

    if ($fromDate) {
        $where .= " AND cp.payment_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND cp.payment_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    $joinMode = clTableExists($pdo, 'payment_modes')
        ? 'LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id'
        : '';
    $modeSelect = clTableExists($pdo, 'payment_modes') ? 'COALESCE(pm.mode_name, \'Payment\')' : "'Payment'";

    $stmt = $pdo->prepare("
        SELECT
            cp.id,
            cp.payment_no,
            cp.payment_date,
            cp.reference_no,
            cp.sales_id,
            $amountExpr AS paid_amount,
            $modeSelect AS mode_name
        FROM customer_payments cp
        $joinMode
        WHERE $where
        ORDER BY cp.payment_date ASC, cp.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reference = trim((string)($row['payment_no'] ?? ''));
        if ($reference === '') {
            $reference = trim((string)($row['reference_no'] ?? ''));
        }

        $rows[] = [
            'entry_date' => $row['payment_date'],
            'particular' => 'Payment Received - ' . ($row['mode_name'] ?: 'Payment'),
            'reference_no' => $reference ?: '-',
            'document_type' => 99,
            'document_label' => 'Payment',
            'debit' => 0,
            'credit' => round((float)$row['paid_amount'], 2),
            'balance' => 0,
            'sort_key' => $row['payment_date'] . '-' . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT) . '-P'
        ];
    }

    return $rows;
}

function clCustomerPaymentAmountExpression(PDO $pdo, $alias = 'cp')
{
    $parts = [];

    if (clColumnExists($pdo, 'customer_payments', 'amount')) {
        $parts[] = "NULLIF($alias.amount, 0)";
    }

    if (clColumnExists($pdo, 'customer_payments', 'payment_amount')) {
        $parts[] = "NULLIF($alias.payment_amount, 0)";
    }

    if (!$parts) {
        return '0';
    }

    $parts[] = '0';
    return 'COALESCE(' . implode(', ', $parts) . ')';
}

function clDocumentLabel($type)
{
    $labels = [
        1 => 'Quotation',
        2 => 'Proforma Bill',
        3 => 'Sales Bill',
        4 => 'Direct Bill',
        5 => 'Final Invoice'
    ];

    return $labels[(int)$type] ?? 'Document';
}

function clTableExists(PDO $pdo, $tableName)
{
    static $cache = [];

    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
    ");
    $stmt->execute([':table_name' => $tableName]);

    $cache[$tableName] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$tableName];
}

function clColumnExists(PDO $pdo, $tableName, $columnName)
{
    static $cache = [];

    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$tableName);
    $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$columnName);
    $key = $tableName . '.' . $columnName;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
        AND COLUMN_NAME = :column_name
    ");
    $stmt->execute([
        ':table_name' => $tableName,
        ':column_name' => $columnName
    ]);

    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}
