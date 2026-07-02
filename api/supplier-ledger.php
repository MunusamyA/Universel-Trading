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
    case 'get_suppliers':
        ledgerSuppliers($pdo);
        break;
    case 'list_ledger':
        listSupplierLedger($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function ledgerScope()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    return ['business_id' => $businessId, 'branch_id' => $branchId];
}

function ledgerPermission()
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    if (!function_exists('hasPermission')) {
        return;
    }

    foreach (['supplier_ledger', 'supplier_payments', 'suppliers', 'purchases'] as $key) {
        if (hasPermission($key, 'view')) {
            return;
        }
    }

    jsonResponse(false, 'Permission denied.');
}

function ledgerSuppliers(PDO $pdo)
{
    ledgerPermission();
    $scope = ledgerScope();

    $stmt = $pdo->prepare("
        SELECT id, supplier_name
        FROM suppliers
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY supplier_name ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Suppliers loaded.', [
        'suppliers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function listSupplierLedger(PDO $pdo)
{
    ledgerPermission();
    $scope = ledgerScope();

    $supplierId = (int)($_GET['supplier_id'] ?? 0);
    $fromDate = ledgerCleanDate($_GET['from_date'] ?? '');
    $toDate = ledgerCleanDate($_GET['to_date'] ?? '');

    if ($supplierId <= 0) {
        jsonResponse(false, 'Please select supplier.');
    }

    $supplierStmt = $pdo->prepare("
        SELECT supplier_name, COALESCE(opening_outstanding, 0) AS opening_outstanding
        FROM suppliers
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $supplierStmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        jsonResponse(false, 'Supplier not found.');
    }

    $entries = [];

    $openingOutstanding = round((float)$supplier['opening_outstanding'], 2);
    if ($openingOutstanding > 0) {
        $entries[] = [
            'entry_date' => '0000-00-00',
            'particular' => 'Opening Outstanding',
            'reference_no' => '-',
            'entry_type' => 'Opening',
            'debit' => 0,
            'credit' => $openingOutstanding
        ];
    }

    $purchaseSql = "
        SELECT purchase_date AS entry_date, bill_no AS reference_no, grand_total AS credit
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_id = :supplier_id
        AND status = 1
    ";
    $purchaseParams = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ];

    if ($fromDate) {
        $purchaseSql .= " AND purchase_date >= :from_date";
        $purchaseParams[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $purchaseSql .= " AND purchase_date <= :to_date";
        $purchaseParams[':to_date'] = $toDate;
    }

    $purchaseStmt = $pdo->prepare($purchaseSql);
    $purchaseStmt->execute($purchaseParams);

    foreach ($purchaseStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $entries[] = [
            'entry_date' => $row['entry_date'],
            'particular' => 'Purchase Bill',
            'reference_no' => $row['reference_no'],
            'entry_type' => 'Purchase',
            'debit' => 0,
            'credit' => round((float)$row['credit'], 2)
        ];
    }

    $paymentSql = "
        SELECT sp.payment_date AS entry_date, sp.payment_no AS reference_no,
               sp.total_amount AS debit, sp.payment_type
        FROM supplier_payments sp
        WHERE sp.business_id = :business_id
        AND sp.branch_id = :branch_id
        AND sp.supplier_id = :supplier_id
        AND sp.status = 1
    ";
    $paymentParams = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ];

    if ($fromDate) {
        $paymentSql .= " AND sp.payment_date >= :pay_from_date";
        $paymentParams[':pay_from_date'] = $fromDate;
    }

    if ($toDate) {
        $paymentSql .= " AND sp.payment_date <= :pay_to_date";
        $paymentParams[':pay_to_date'] = $toDate;
    }

    $paymentStmt = $pdo->prepare($paymentSql);
    $paymentStmt->execute($paymentParams);

    foreach ($paymentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $entries[] = [
            'entry_date' => $row['entry_date'],
            'particular' => ledgerPaymentTypeName((int)$row['payment_type']),
            'reference_no' => $row['reference_no'],
            'entry_type' => 'Payment',
            'debit' => round((float)$row['debit'], 2),
            'credit' => 0
        ];
    }

    usort($entries, function ($a, $b) {
        $dateCompare = strcmp($a['entry_date'], $b['entry_date']);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return strcmp($a['entry_type'], $b['entry_type']);
    });

    $balance = 0;
    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($entries as &$entry) {
        $totalDebit += (float)$entry['debit'];
        $totalCredit += (float)$entry['credit'];
        $balance = round($balance + (float)$entry['credit'] - (float)$entry['debit'], 2);
        $entry['balance'] = $balance;
        $entry['display_date'] = $entry['entry_date'] === '0000-00-00' ? 'Opening' : $entry['entry_date'];
    }
    unset($entry);

    jsonResponse(true, 'Ledger loaded.', [
        'supplier' => $supplier,
        'entries' => $entries,
        'summary' => [
            'total_entries' => count($entries),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => round($balance, 2)
        ]
    ]);
}

function ledgerPaymentTypeName($paymentType)
{
    $paymentType = (int)$paymentType;

    if ($paymentType === 2) {
        return 'Individual Supplier Payment';
    }

    if ($paymentType === 3) {
        return 'Opening Outstanding Payment';
    }

    return 'Overall Supplier Payment';
}

function ledgerCleanDate($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}
