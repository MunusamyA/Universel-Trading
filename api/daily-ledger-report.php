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
    case 'list_report':
        listDailyLedgerReport($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function requireDailyLedgerPermission()
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    if (!function_exists('hasPermission')) {
        return;
    }

    foreach (['daily_ledger_report', 'reports', 'purchases', 'sales', 'expenses', 'customer_payments', 'supplier_payments'] as $key) {
        if (hasPermission($key, 'view')) {
            return;
        }
    }

    jsonResponse(false, 'Permission denied.');
}

function dlScope()
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

function listDailyLedgerReport(PDO $pdo)
{
    requireDailyLedgerPermission();

    $scope = dlScope();

    $fromDate = dlCleanDate($_GET['from_date'] ?? date('Y-m-d'));
    $toDate = dlCleanDate($_GET['to_date'] ?? date('Y-m-d'));
    $entryTypesRaw = $_GET['entry_types'] ?? ($_GET['entry_type'] ?? 'all');
    $entryTypes = dlParseEntryTypes($entryTypesRaw);
    $search = trim((string)($_GET['search'] ?? ''));

    if (!$fromDate) {
        $fromDate = date('Y-m-d');
    }

    if (!$toDate) {
        $toDate = $fromDate;
    }

    if ($fromDate > $toDate) {
        jsonResponse(false, 'From date cannot be greater than To date.');
    }

    $rows = [];

    if (in_array('sales', $entryTypes, true)) {
        $rows = array_merge($rows, dlSalesRows($pdo, $scope, $fromDate, $toDate, $search));
    }

    if (in_array('customer_payment', $entryTypes, true)) {
        $rows = array_merge($rows, dlCustomerPaymentRows($pdo, $scope, $fromDate, $toDate, $search));
    }

    if (in_array('purchase', $entryTypes, true)) {
        $rows = array_merge($rows, dlPurchaseRows($pdo, $scope, $fromDate, $toDate, $search));
    }

    if (in_array('supplier_payment', $entryTypes, true)) {
        $rows = array_merge($rows, dlSupplierPaymentRows($pdo, $scope, $fromDate, $toDate, $search));
    }

    if (in_array('expense', $entryTypes, true)) {
        $rows = array_merge($rows, dlExpenseRows($pdo, $scope, $fromDate, $toDate, $search));
    }

    usort($rows, function ($a, $b) {
        $dateCompare = strcmp($a['entry_date'], $b['entry_date']);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return strcmp($a['sort_key'], $b['sort_key']);
    });

    $summary = [
        'sales_total' => 0,
        'customer_payment_total' => 0,
        'purchase_total' => 0,
        'supplier_payment_total' => 0,
        'expense_total' => 0,
        'debit_total' => 0,
        'credit_total' => 0,
        'entry_count' => count($rows)
    ];

    $dateSummary = [];

    foreach ($rows as &$row) {
        $row['amount'] = round((float)$row['amount'], 2);
        $row['debit_amount'] = $row['amount'];
        $row['credit_amount'] = $row['amount'];

        if (!isset($dateSummary[$row['entry_date']])) {
            $dateSummary[$row['entry_date']] = [
                'entry_date' => $row['entry_date'],
                'sales_total' => 0,
                'customer_payment_total' => 0,
                'purchase_total' => 0,
                'supplier_payment_total' => 0,
                'expense_total' => 0,
                'debit_total' => 0,
                'credit_total' => 0,
                'entry_count' => 0
            ];
        }

        if ($row['entry_type'] === 'sales') {
            $summary['sales_total'] += $row['amount'];
            $dateSummary[$row['entry_date']]['sales_total'] += $row['amount'];
        } elseif ($row['entry_type'] === 'customer_payment') {
            $summary['customer_payment_total'] += $row['amount'];
            $dateSummary[$row['entry_date']]['customer_payment_total'] += $row['amount'];
        } elseif ($row['entry_type'] === 'purchase') {
            $summary['purchase_total'] += $row['amount'];
            $dateSummary[$row['entry_date']]['purchase_total'] += $row['amount'];
        } elseif ($row['entry_type'] === 'supplier_payment') {
            $summary['supplier_payment_total'] += $row['amount'];
            $dateSummary[$row['entry_date']]['supplier_payment_total'] += $row['amount'];
        } elseif ($row['entry_type'] === 'expense') {
            $summary['expense_total'] += $row['amount'];
            $dateSummary[$row['entry_date']]['expense_total'] += $row['amount'];
        }

        $summary['debit_total'] += $row['amount'];
        $summary['credit_total'] += $row['amount'];

        $dateSummary[$row['entry_date']]['debit_total'] += $row['amount'];
        $dateSummary[$row['entry_date']]['credit_total'] += $row['amount'];
        $dateSummary[$row['entry_date']]['entry_count']++;
    }
    unset($row);

    foreach ($summary as $key => $value) {
        if ($key !== 'entry_count') {
            $summary[$key] = round((float)$value, 2);
        }
    }

    foreach ($dateSummary as &$day) {
        foreach ($day as $key => $value) {
            if (!in_array($key, ['entry_date', 'entry_count'], true)) {
                $day[$key] = round((float)$value, 2);
            }
        }
    }
    unset($day);

    jsonResponse(true, 'Daily ledger report loaded.', [
        'rows' => $rows,
        'summary' => $summary,
        'date_summary' => array_values($dateSummary),
        'filters' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'entry_types' => $entryTypes,
            'entry_type_label' => dlEntryTypesLabel($entryTypes),
            'search' => $search
        ]
    ]);
}

function dlParseEntryTypes($raw)
{
    $allowedTypes = ['sales', 'customer_payment', 'purchase', 'supplier_payment', 'expense'];

    if (is_array($raw)) {
        $items = $raw;
    } else {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === 'all') {
            return $allowedTypes;
        }
        $items = explode(',', $raw);
    }

    $selected = [];
    foreach ($items as $item) {
        $item = cleanInput($item);
        if ($item === 'all') {
            return $allowedTypes;
        }
        if (in_array($item, $allowedTypes, true) && !in_array($item, $selected, true)) {
            $selected[] = $item;
        }
    }

    if (empty($selected)) {
        return $allowedTypes;
    }

    return $selected;
}

function dlEntryTypesLabel(array $entryTypes)
{
    $allowedTypes = ['sales', 'customer_payment', 'purchase', 'supplier_payment', 'expense'];
    sort($entryTypes);
    $sortedAllowed = $allowedTypes;
    sort($sortedAllowed);

    if ($entryTypes === $sortedAllowed) {
        return 'All';
    }

    $labels = [];
    foreach ($entryTypes as $type) {
        switch ($type) {
            case 'sales':
                $labels[] = 'Sales';
                break;
            case 'customer_payment':
                $labels[] = 'Customer Payment';
                break;
            case 'purchase':
                $labels[] = 'Purchase';
                break;
            case 'supplier_payment':
                $labels[] = 'Supplier Payment';
                break;
            case 'expense':
                $labels[] = 'Expense';
                break;
        }
    }

    return implode(', ', $labels);
}

function dlSalesRows(PDO $pdo, array $scope, $fromDate, $toDate, $search)
{
    $where = "
        s.business_id = :business_id
        AND s.branch_id = :branch_id
        AND s.sales_date BETWEEN :from_date AND :to_date
        AND s.status IN (1, 2)
        AND s.document_type IN (3, 4, 5)
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($search !== '') {
        $where .= " AND (s.sales_no LIKE :sales_search_no OR c.customer_name LIKE :sales_search_customer OR s.reference_no LIKE :sales_search_ref)";
        $searchValue = '%' . $search . '%';
        $params[':sales_search_no'] = $searchValue;
        $params[':sales_search_customer'] = $searchValue;
        $params[':sales_search_ref'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.sales_date AS entry_date,
            s.sales_no AS reference_no,
            COALESCE(c.customer_name, 'Customer') AS party_name,
            s.document_type,
            s.grand_total,
            s.paid_amount,
            s.due_amount,
            s.notes
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        WHERE $where
        ORDER BY s.sales_date ASC, s.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = round((float)$row['grand_total'], 2);
        if ($amount <= 0) {
            continue;
        }

        $rows[] = [
            'id' => (int)$row['id'],
            'entry_date' => $row['entry_date'],
            'entry_type' => 'sales',
            'type_label' => dlSalesDocumentLabel((int)$row['document_type']),
            'reference_no' => $row['reference_no'],
            'party_name' => $row['party_name'],
            'debit_account' => $row['party_name'] . ' A/c',
            'credit_account' => 'Sales A/c',
            'amount' => $amount,
            'payment_mode' => '-',
            'description' => 'Sales bill created',
            'paid_amount' => round((float)$row['paid_amount'], 2),
            'due_amount' => round((float)$row['due_amount'], 2),
            'source_url' => 'sales-view.php?id=' . (int)$row['id'],
            'sort_key' => '1-' . str_pad((string)$row['id'], 10, '0', STR_PAD_LEFT)
        ];
    }

    return $rows;
}

function dlCustomerPaymentRows(PDO $pdo, array $scope, $fromDate, $toDate, $search)
{
    $where = "
        cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        AND cp.payment_date BETWEEN :from_date AND :to_date
        AND cp.status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($search !== '') {
        $where .= " AND (cp.payment_no LIKE :cp_search_no OR c.customer_name LIKE :cp_search_customer OR cp.reference_no LIKE :cp_search_ref)";
        $searchValue = '%' . $search . '%';
        $params[':cp_search_no'] = $searchValue;
        $params[':cp_search_customer'] = $searchValue;
        $params[':cp_search_ref'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            cp.id,
            cp.payment_date AS entry_date,
            COALESCE(cp.payment_no, CONCAT('PAY-', cp.id)) AS reference_no,
            COALESCE(c.customer_name, 'Customer') AS party_name,
            COALESCE(pm.mode_name, 'Cash/Bank') AS payment_mode,
            CASE
                WHEN cp.amount IS NOT NULL AND cp.amount > 0 THEN cp.amount
                ELSE COALESCE(cp.payment_amount, 0)
            END AS amount,
            cp.notes
        FROM customer_payments cp
        LEFT JOIN customers c ON c.id = cp.customer_id
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        WHERE $where
        ORDER BY cp.payment_date ASC, cp.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = round((float)$row['amount'], 2);
        if ($amount <= 0) {
            continue;
        }

        $rows[] = [
            'id' => (int)$row['id'],
            'entry_date' => $row['entry_date'],
            'entry_type' => 'customer_payment',
            'type_label' => 'Customer Payment',
            'reference_no' => $row['reference_no'],
            'party_name' => $row['party_name'],
            'debit_account' => $row['payment_mode'] . ' A/c',
            'credit_account' => $row['party_name'] . ' A/c',
            'amount' => $amount,
            'payment_mode' => $row['payment_mode'],
            'description' => trim((string)($row['notes'] ?? '')),
            'paid_amount' => $amount,
            'due_amount' => 0,
            'source_url' => 'customer-payments.php?id=' . (int)$row['id'],
            'sort_key' => '2-' . str_pad((string)$row['id'], 10, '0', STR_PAD_LEFT)
        ];
    }

    return $rows;
}

function dlPurchaseRows(PDO $pdo, array $scope, $fromDate, $toDate, $search)
{
    $where = "
        p.business_id = :business_id
        AND p.branch_id = :branch_id
        AND p.purchase_date BETWEEN :from_date AND :to_date
        AND p.status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($search !== '') {
        $where .= " AND (p.bill_no LIKE :purchase_search_bill OR p.batch_no LIKE :purchase_search_batch OR sup.supplier_name LIKE :purchase_search_supplier)";
        $searchValue = '%' . $search . '%';
        $params[':purchase_search_bill'] = $searchValue;
        $params[':purchase_search_batch'] = $searchValue;
        $params[':purchase_search_supplier'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.purchase_date AS entry_date,
            p.bill_no AS reference_no,
            COALESCE(sup.supplier_name, 'Supplier') AS party_name,
            p.batch_no,
            p.grand_total,
            p.paid_amount,
            p.due_amount,
            p.notes
        FROM purchases p
        LEFT JOIN suppliers sup ON sup.id = p.supplier_id
        WHERE $where
        ORDER BY p.purchase_date ASC, p.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = round((float)$row['grand_total'], 2);
        if ($amount <= 0) {
            continue;
        }

        $description = trim((string)($row['notes'] ?? ''));
        if ($description === '' && (string)$row['batch_no'] !== '') {
            $description = 'Batch: ' . $row['batch_no'];
        }

        $rows[] = [
            'id' => (int)$row['id'],
            'entry_date' => $row['entry_date'],
            'entry_type' => 'purchase',
            'type_label' => 'Purchase',
            'reference_no' => $row['reference_no'],
            'party_name' => $row['party_name'],
            'debit_account' => 'Purchase A/c',
            'credit_account' => $row['party_name'] . ' A/c',
            'amount' => $amount,
            'payment_mode' => '-',
            'description' => $description,
            'paid_amount' => round((float)$row['paid_amount'], 2),
            'due_amount' => round((float)$row['due_amount'], 2),
            'source_url' => 'purchase-form.php?id=' . (int)$row['id'],
            'sort_key' => '3-' . str_pad((string)$row['id'], 10, '0', STR_PAD_LEFT)
        ];
    }

    return $rows;
}

function dlSupplierPaymentRows(PDO $pdo, array $scope, $fromDate, $toDate, $search)
{
    $where = "
        sp.business_id = :business_id
        AND sp.branch_id = :branch_id
        AND sp.payment_date BETWEEN :from_date AND :to_date
        AND sp.status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($search !== '') {
        $where .= " AND (sp.payment_no LIKE :sp_search_no OR sup.supplier_name LIKE :sp_search_supplier OR sps.reference_no LIKE :sp_search_ref)";
        $searchValue = '%' . $search . '%';
        $params[':sp_search_no'] = $searchValue;
        $params[':sp_search_supplier'] = $searchValue;
        $params[':sp_search_ref'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            sp.id,
            sp.payment_date AS entry_date,
            sp.payment_no AS reference_no,
            COALESCE(sup.supplier_name, 'Supplier') AS party_name,
            sp.payment_type,
            sp.total_amount,
            sp.notes,
            COALESCE(pm.mode_name, 'Cash/Bank') AS payment_mode,
            COALESCE(sps.amount, sp.total_amount) AS split_amount,
            COALESCE(sps.reference_no, '') AS split_reference
        FROM supplier_payments sp
        LEFT JOIN suppliers sup ON sup.id = sp.supplier_id
        LEFT JOIN supplier_payment_splits sps
            ON sps.supplier_payment_id = sp.id
            AND sps.status = 1
        LEFT JOIN payment_modes pm ON pm.id = sps.payment_mode_id
        WHERE $where
        ORDER BY sp.payment_date ASC, sp.id ASC, sps.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = round((float)$row['split_amount'], 2);
        if ($amount <= 0) {
            continue;
        }

        $rows[] = [
            'id' => (int)$row['id'],
            'entry_date' => $row['entry_date'],
            'entry_type' => 'supplier_payment',
            'type_label' => dlSupplierPaymentLabel((int)$row['payment_type']),
            'reference_no' => $row['reference_no'],
            'party_name' => $row['party_name'],
            'debit_account' => $row['party_name'] . ' A/c',
            'credit_account' => $row['payment_mode'] . ' A/c',
            'amount' => $amount,
            'payment_mode' => $row['payment_mode'],
            'description' => trim((string)($row['notes'] ?? '')),
            'paid_amount' => $amount,
            'due_amount' => 0,
            'source_url' => 'supplier-payments.php?supplier_id=0',
            'sort_key' => '4-' . str_pad((string)$row['id'], 10, '0', STR_PAD_LEFT)
        ];
    }

    return $rows;
}

function dlExpenseRows(PDO $pdo, array $scope, $fromDate, $toDate, $search)
{
    $where = "
        e.business_id = :business_id
        AND e.branch_id = :branch_id
        AND e.expense_date BETWEEN :from_date AND :to_date
        AND e.status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($search !== '') {
        $where .= " AND (e.expense_no LIKE :expense_search_no OR e.vendor_name LIKE :expense_search_vendor OR e.reference_no LIKE :expense_search_ref OR ec.category_name LIKE :expense_search_cat)";
        $searchValue = '%' . $search . '%';
        $params[':expense_search_no'] = $searchValue;
        $params[':expense_search_vendor'] = $searchValue;
        $params[':expense_search_ref'] = $searchValue;
        $params[':expense_search_cat'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.expense_date AS entry_date,
            e.expense_no AS reference_no,
            COALESCE(e.vendor_name, ec.category_name, 'Expense') AS party_name,
            COALESCE(ec.category_name, 'Expense') AS category_name,
            e.total_amount,
            e.amount AS old_amount,
            e.notes,
            e.description,
            COALESCE(pm.mode_name, 'Cash/Bank') AS payment_mode,
            eps.amount AS split_amount,
            eps.reference_no AS split_reference
        FROM expenses e
        LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id
        LEFT JOIN expense_payment_splits eps
            ON eps.expense_id = e.id
            AND eps.status = 1
        LEFT JOIN payment_modes pm ON pm.id = eps.payment_mode_id
        WHERE $where
        ORDER BY e.expense_date ASC, e.id ASC, eps.id ASC
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['split_amount'] !== null) {
            $amount = round((float)$row['split_amount'], 2);
        } else {
            $amount = round((float)$row['total_amount'], 2);
            if ($amount <= 0) {
                $amount = round((float)$row['old_amount'], 2);
            }
        }

        if ($amount <= 0) {
            continue;
        }

        $description = trim((string)($row['notes'] ?? ''));
        if ($description === '') {
            $description = trim((string)($row['description'] ?? ''));
        }

        $rows[] = [
            'id' => (int)$row['id'],
            'entry_date' => $row['entry_date'],
            'entry_type' => 'expense',
            'type_label' => $row['category_name'],
            'reference_no' => $row['reference_no'],
            'party_name' => $row['party_name'],
            'debit_account' => $row['category_name'] . ' A/c',
            'credit_account' => $row['payment_mode'] . ' A/c',
            'amount' => $amount,
            'payment_mode' => $row['payment_mode'],
            'description' => $description,
            'paid_amount' => $amount,
            'due_amount' => 0,
            'source_url' => 'expenses.php?id=' . (int)$row['id'],
            'sort_key' => '5-' . str_pad((string)$row['id'], 10, '0', STR_PAD_LEFT)
        ];
    }

    return $rows;
}

function dlSalesDocumentLabel($documentType)
{
    switch ((int)$documentType) {
        case 3:
            return 'Sales Bill';
        case 4:
            return 'Direct Sale';
        case 5:
            return 'Final Invoice';
        default:
            return 'Sales';
    }
}

function dlSupplierPaymentLabel($paymentType)
{
    switch ((int)$paymentType) {
        case 2:
            return 'Supplier Payment - Individual';
        case 3:
            return 'Supplier Payment - Opening';
        case 1:
        default:
            return 'Supplier Payment - Overall';
    }
}

function dlCleanDate($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}
