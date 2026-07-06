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

    case 'search_customers':
        searchLedgerCustomers($pdo);
        break;

    case 'list_ledger':
        listCustomerLedger($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

/*
|--------------------------------------------------------------------------
| Customer Ledger permission action numbers
|--------------------------------------------------------------------------
| 1 = View
| 2 = List
|--------------------------------------------------------------------------
*/

function ledgerCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('customer_ledger', (int)$actionCode);
}

function customerMasterCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('customers', (int)$actionCode);
}

function salesCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('sales', (int)$actionCode);
}

function requireLedgerPermission($actionCode)
{
    /*
    |--------------------------------------------------------------------------
    | Main permission is customer_ledger.
    | Fallback allowed if package gives customers or sales view/list permission.
    |--------------------------------------------------------------------------
    */

    $actionCode = (int)$actionCode;

    if (
        ledgerCan($actionCode)
        || customerMasterCan($actionCode)
        || salesCan($actionCode)
    ) {
        return;
    }

    jsonResponse(false, 'Permission denied.');
}

function getPageContext(PDO $pdo)
{
    if (
        !ledgerCan(1)
        && !ledgerCan(2)
        && !customerMasterCan(1)
        && !customerMasterCan(2)
        && !salesCan(1)
        && !salesCan(2)
    ) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => ledgerCan(1) || customerMasterCan(1) || salesCan(1),
            'can_list' => ledgerCan(2) || customerMasterCan(2) || salesCan(2),
            'can_customers' => customerMasterCan(1) || customerMasterCan(2),
            'page_title' => 'Customer Ledger',
            'page_note' => 'Debit / Credit / Balance statement',
            'customers_url' => BASE_URL . 'pages/customers.php'
        ]
    ]);
}

function getLedgerScope()
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

function searchLedgerCustomers(PDO $pdo)
{
    requireLedgerPermission(1);

    $scope = getLedgerScope();
    $search = cleanInput($_GET['search'] ?? '');

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($search !== '') {
        /*
        |--------------------------------------------------------------------------
        | Unique PDO placeholders avoid SQLSTATE[HY093].
        |--------------------------------------------------------------------------
        */

        $where .= "
            AND (
                customer_name LIKE :search_customer_name
                OR mobile LIKE :search_mobile
                OR gst_number LIKE :search_gst_number
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_customer_name'] = $searchValue;
        $params[':search_mobile'] = $searchValue;
        $params[':search_gst_number'] = $searchValue;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                customer_name,
                mobile,
                gst_number,
                current_outstanding
            FROM customers
            $where
            ORDER BY customer_name ASC
            LIMIT 100
        ");

        $stmt->execute($params);

        jsonResponse(true, 'Customers loaded.', [
            'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load customers.');
    }
}

function listCustomerLedger(PDO $pdo)
{
    requireLedgerPermission(2);

    $scope = getLedgerScope();

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $fromDate = ledgerCleanDate($_GET['from_date'] ?? '');
    $toDate = ledgerCleanDate($_GET['to_date'] ?? '');
    $rawTypes = $_GET['document_types'] ?? [];

    if (!is_array($rawTypes)) {
        $rawTypes = $rawTypes !== '' ? explode(',', (string)$rawTypes) : [];
    }

    $documentTypes = [];

    foreach ($rawTypes as $type) {
        $type = (int)$type;

        if (in_array($type, [1, 2, 3, 4, 5, 99], true)) {
            $documentTypes[] = $type;
        }
    }

    if (!$documentTypes) {
        $documentTypes = [1, 2, 3, 4, 5, 99];
    }

    if ($customerId <= 0) {
        jsonResponse(false, 'Select customer.');
    }

    $customer = getLedgerCustomer($pdo, $scope, $customerId);

    if (!$customer) {
        jsonResponse(false, 'Customer not found.');
    }

    try {
        $openingBalance = calculateOpeningBalance($pdo, $scope, $customerId, $fromDate, $documentTypes);

        $entries = [];

        if ($fromDate || ((float)$openingBalance) != 0.0) {
            $entries[] = [
                'entry_id' => 0,
                'entry_date' => $fromDate ?: '',
                'sort_date' => $fromDate ?: '0000-00-00',
                'reference_no' => '',
                'particular' => 'Opening Balance',
                'document_type' => 0,
                'document_label' => 'Opening',
                'debit' => $openingBalance > 0 ? ledgerRound2($openingBalance) : 0,
                'credit' => $openingBalance < 0 ? ledgerRound2(abs($openingBalance)) : 0,
                'balance' => 0
            ];
        }

        $entries = array_merge(
            $entries,
            fetchLedgerSalesEntries($pdo, $scope, $customerId, $fromDate, $toDate, $documentTypes)
        );

        $entries = array_merge(
            $entries,
            fetchLedgerPaymentEntries($pdo, $scope, $customerId, $fromDate, $toDate, $documentTypes)
        );

        usort($entries, function ($a, $b) {
            $dateCompare = strcmp((string)$a['sort_date'], (string)$b['sort_date']);

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int)$a['entry_id'] <=> (int)$b['entry_id']);
        });

        $balance = 0;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as &$entry) {
            $debit = ledgerRound2($entry['debit'] ?? 0);
            $credit = ledgerRound2($entry['credit'] ?? 0);

            $totalDebit += $debit;
            $totalCredit += $credit;
            $balance = ledgerRound2($balance + $debit - $credit);

            $entry['debit'] = $debit;
            $entry['credit'] = $credit;
            $entry['balance'] = $balance;
        }
        unset($entry);

        jsonResponse(true, 'Ledger loaded.', [
            'customer' => $customer,
            'entries' => $entries,
            'summary' => [
                'total_debit' => ledgerRound2($totalDebit),
                'total_credit' => ledgerRound2($totalCredit),
                'closing_balance' => ledgerRound2($balance),
                'entry_count' => count($entries)
            ]
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load ledger.');
    }
}

function getLedgerCustomer(PDO $pdo, array $scope, $customerId)
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            customer_name,
            mobile,
            gst_number,
            COALESCE(opening_outstanding, 0) AS opening_outstanding,
            COALESCE(current_outstanding, 0) AS current_outstanding
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

function calculateOpeningBalance(PDO $pdo, array $scope, $customerId, $fromDate, array $documentTypes)
{
    $customer = getLedgerCustomer($pdo, $scope, $customerId);
    $balance = (float)($customer['opening_outstanding'] ?? 0);

    if (!$fromDate) {
        return ledgerRound2($balance);
    }

    $salesTypes = array_values(array_filter($documentTypes, fn($type) => in_array((int)$type, [1, 2, 3, 4, 5], true)));

    if ($salesTypes) {
        $params = [
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId,
            ':from_date' => $fromDate
        ];

        $holders = [];

        foreach ($salesTypes as $index => $type) {
            $key = ':type_' . $index;
            $holders[] = $key;
            $params[$key] = $type;
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(grand_total), 0)
            FROM sales
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND customer_id = :customer_id
            AND status IN (1, 2)
            AND sales_date < :from_date
            AND document_type IN (" . implode(',', $holders) . ")
        ");

        $stmt->execute($params);
        $balance += (float)$stmt->fetchColumn();
    }

    if (in_array(99, $documentTypes, true)) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM customer_payments
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND customer_id = :customer_id
            AND status = 1
            AND payment_date < :from_date
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId,
            ':from_date' => $fromDate
        ]);

        $balance -= (float)$stmt->fetchColumn();
    }

    return ledgerRound2($balance);
}

function fetchLedgerSalesEntries(PDO $pdo, array $scope, $customerId, $fromDate, $toDate, array $documentTypes)
{
    $salesTypes = array_values(array_filter($documentTypes, fn($type) => in_array((int)$type, [1, 2, 3, 4, 5], true)));

    if (!$salesTypes) {
        return [];
    }

    $where = "
        WHERE s.business_id = :business_id
        AND s.branch_id = :branch_id
        AND s.customer_id = :customer_id
        AND s.status IN (1, 2)
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ];

    if ($fromDate) {
        $where .= " AND s.sales_date >= :from_date ";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND s.sales_date <= :to_date ";
        $params[':to_date'] = $toDate;
    }

    $holders = [];

    foreach ($salesTypes as $index => $type) {
        $key = ':document_type_' . $index;
        $holders[] = $key;
        $params[$key] = $type;
    }

    $where .= " AND s.document_type IN (" . implode(',', $holders) . ") ";

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.sales_date,
            s.sales_no,
            s.document_type,
            s.grand_total
        FROM sales s
        $where
        ORDER BY s.sales_date ASC, s.id ASC
    ");

    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sale) {
        $label = ledgerDocumentLabel((int)$sale['document_type']);

        $rows[] = [
            'entry_id' => (int)$sale['id'],
            'entry_date' => $sale['sales_date'],
            'sort_date' => $sale['sales_date'],
            'reference_no' => $sale['sales_no'],
            'particular' => $label . ' - ' . $sale['sales_no'],
            'document_type' => (int)$sale['document_type'],
            'document_label' => $label,
            'debit' => (float)$sale['grand_total'],
            'credit' => 0,
            'balance' => 0
        ];
    }

    return $rows;
}

function fetchLedgerPaymentEntries(PDO $pdo, array $scope, $customerId, $fromDate, $toDate, array $documentTypes)
{
    if (!in_array(99, $documentTypes, true)) {
        return [];
    }

    $where = "
        WHERE cp.business_id = :business_id
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
        $where .= " AND cp.payment_date >= :from_date ";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND cp.payment_date <= :to_date ";
        $params[':to_date'] = $toDate;
    }

    $stmt = $pdo->prepare("
        SELECT
            cp.id,
            cp.payment_date,
            cp.payment_no,
            cp.amount
        FROM customer_payments cp
        $where
        ORDER BY cp.payment_date ASC, cp.id ASC
    ");

    $stmt->execute($params);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $payment) {
        $rows[] = [
            'entry_id' => (int)$payment['id'],
            'entry_date' => $payment['payment_date'],
            'sort_date' => $payment['payment_date'],
            'reference_no' => $payment['payment_no'],
            'particular' => 'Payment Received - ' . $payment['payment_no'] . paymentLedgerModeText($pdo, $scope, (int)$payment['id']),
            'document_type' => 99,
            'document_label' => 'Payment',
            'debit' => 0,
            'credit' => (float)$payment['amount'],
            'balance' => 0
        ];
    }

    return $rows;
}

function paymentLedgerModeText(PDO $pdo, array $scope, $paymentId)
{
    if (!ledgerTableExists($pdo, 'customer_payment_splits')) {
        return '';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                pm.mode_name,
                cps.amount
            FROM customer_payment_splits cps
            LEFT JOIN payment_modes pm
                ON pm.id = cps.payment_mode_id
            WHERE cps.business_id = :business_id
            AND cps.branch_id = :branch_id
            AND cps.payment_id = :payment_id
            AND cps.status = 1
            ORDER BY cps.id ASC
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':payment_id' => $paymentId
        ]);

        $parts = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $parts[] = trim(($row['mode_name'] ?: 'Mode') . ' ₹' . number_format((float)$row['amount'], 2));
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';

    } catch (Throwable $e) {
        return '';
    }
}

function ledgerDocumentLabel($type)
{
    return [
        1 => 'Quotation',
        2 => 'Proforma Bill',
        3 => 'Sales Bill',
        4 => 'Direct Bill',
        5 => 'Final Invoice',
        99 => 'Payment'
    ][(int)$type] ?? 'Document';
}

function ledgerTableExists(PDO $pdo, $table)
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");

        $stmt->execute([
            ':table_name' => $table
        ]);

        return (bool)$stmt->fetch(PDO::FETCH_NUM);

    } catch (Throwable $e) {
        return false;
    }
}

function ledgerCleanDate($value)
{
    $value = trim((string)$value);

    return ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : null;
}

function ledgerRound2($value)
{
    return round((float)$value, 2);
}
