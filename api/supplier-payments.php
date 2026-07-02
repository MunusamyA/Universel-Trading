<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_suppliers':
        spGetSuppliers($pdo);
        break;
    case 'get_payment_modes':
        spGetPaymentModes($pdo);
        break;
    case 'get_supplier_summary':
        spGetSupplierSummary($pdo);
        break;
    case 'get_pending_purchases':
        spGetPendingPurchases($pdo);
        break;
    case 'list_payments':
        spListPayments($pdo);
        break;
    case 'get_payment':
        spGetPayment($pdo);
        break;
    case 'save_payment':
        verifyCsrfToken();
        spSavePayment($pdo);
        break;
    case 'cancel_payment':
        verifyCsrfToken();
        spCancelPayment($pdo);
        break;
    case 'delete_payment':
        verifyCsrfToken();
        spDeletePayment($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function spRequirePermission($action = 'view')
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    if (!function_exists('hasPermission')) {
        return;
    }

    foreach (['supplier_payments', 'supplier_payment', 'purchases', 'suppliers'] as $key) {
        if (hasPermission($key, $action)) {
            return;
        }
    }

    jsonResponse(false, 'Permission denied.');
}

function spScope()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    return ['business_id' => $businessId, 'branch_id' => $branchId];
}

function spGetSuppliers(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();

    $stmt = $pdo->prepare("
        SELECT
            id,
            supplier_name,
            mobile,
            COALESCE(opening_outstanding, 0) AS opening_outstanding,
            COALESCE(current_outstanding, 0) AS current_outstanding
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

function spGetPaymentModes(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();

    $stmt = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Payment modes loaded.', [
        'payment_modes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function spGetSupplierSummary(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();
    $supplierId = (int)($_GET['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Please select supplier.');
    }

    spValidateSupplier($pdo, $scope, $supplierId);

    jsonResponse(true, 'Supplier summary loaded.', [
        'summary' => spCalculateSupplierBalance($pdo, $scope, $supplierId)
    ]);
}

function spGetPendingPurchases(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();
    $supplierId = (int)($_GET['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Please select supplier.');
    }

    $stmt = $pdo->prepare("
        SELECT id, bill_no, purchase_date, grand_total, paid_amount, due_amount
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_id = :supplier_id
        AND status = 1
        AND due_amount > 0.009
        ORDER BY purchase_date ASC, id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ]);

    jsonResponse(true, 'Pending purchases loaded.', [
        'purchases' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function spListPayments(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();

    $supplierId = (int)($_GET['supplier_id'] ?? 0);
    $fromDate = spCleanDate($_GET['from_date'] ?? '');
    $toDate = spCleanDate($_GET['to_date'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = "WHERE sp.business_id = :business_id AND sp.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($supplierId > 0) {
        $where .= " AND sp.supplier_id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    if ($fromDate) {
        $where .= " AND sp.payment_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND sp.payment_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($status === 1 || $status === 2) {
        $where .= " AND sp.status = :status";
        $params[':status'] = $status;
    }

    $sql = "
        SELECT
            sp.*,
            s.supplier_name,
            COALESCE(split_summary.mode_summary, '') AS split_summary,
            COALESCE(allocation_summary.allocation_summary, '') AS allocation_summary
        FROM supplier_payments sp
        LEFT JOIN suppliers s ON s.id = sp.supplier_id
        LEFT JOIN (
            SELECT
                sps.supplier_payment_id,
                GROUP_CONCAT(CONCAT(COALESCE(pm.mode_name, 'Mode'), ' ₹', FORMAT(sps.amount, 2)) ORDER BY sps.id SEPARATOR ', ') AS mode_summary
            FROM supplier_payment_splits sps
            LEFT JOIN payment_modes pm ON pm.id = sps.payment_mode_id
            WHERE sps.business_id = :split_business_id
            AND sps.branch_id = :split_branch_id
            AND sps.status = 1
            GROUP BY sps.supplier_payment_id
        ) split_summary ON split_summary.supplier_payment_id = sp.id
        LEFT JOIN (
            SELECT
                spa.supplier_payment_id,
                GROUP_CONCAT(
                    CASE
                        WHEN spa.allocation_type = 1 THEN CONCAT('Bill #', COALESCE(p.bill_no, spa.purchase_id), ' ₹', FORMAT(spa.amount, 2))
                        ELSE CONCAT('Opening Outstanding ₹', FORMAT(spa.amount, 2))
                    END
                    ORDER BY spa.id SEPARATOR ', '
                ) AS allocation_summary
            FROM supplier_payment_allocations spa
            LEFT JOIN purchases p ON p.id = spa.purchase_id
            WHERE spa.business_id = :alloc_business_id
            AND spa.branch_id = :alloc_branch_id
            AND spa.status = 1
            GROUP BY spa.supplier_payment_id
        ) allocation_summary ON allocation_summary.supplier_payment_id = sp.id
        $where
        ORDER BY sp.payment_date DESC, sp.id DESC
        LIMIT 300
    ";

    $params[':split_business_id'] = $scope['business_id'];
    $params[':split_branch_id'] = $scope['branch_id'];
    $params[':alloc_business_id'] = $scope['business_id'];
    $params[':alloc_branch_id'] = $scope['branch_id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'Payments loaded.', [
        'payments' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function spGetPayment(PDO $pdo)
{
    spRequirePermission('view');
    $scope = spScope();
    $paymentId = (int)($_GET['payment_id'] ?? 0);

    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM supplier_payments
        WHERE id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        jsonResponse(false, 'Payment not found.');
    }

    $splitStmt = $pdo->prepare("
        SELECT *
        FROM supplier_payment_splits
        WHERE supplier_payment_id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY id ASC
    ");
    $splitStmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $allocStmt = $pdo->prepare("
        SELECT spa.*, p.bill_no
        FROM supplier_payment_allocations spa
        LEFT JOIN purchases p ON p.id = spa.purchase_id
        WHERE spa.supplier_payment_id = :payment_id
        AND spa.business_id = :business_id
        AND spa.branch_id = :branch_id
        AND spa.status = 1
        ORDER BY spa.id ASC
    ");
    $allocStmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Payment loaded.', [
        'payment' => $payment,
        'splits' => $splitStmt->fetchAll(PDO::FETCH_ASSOC),
        'allocations' => $allocStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function spSavePayment(PDO $pdo)
{
    $scope = spScope();
    $paymentId = (int)($_POST['payment_id'] ?? 0);

    spRequirePermission($paymentId > 0 ? 'edit' : 'add');

    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $paymentDate = spCleanDate($_POST['payment_date'] ?? date('Y-m-d'));
    $paymentType = (int)($_POST['payment_type'] ?? 1);
    $purchaseId = (int)($_POST['purchase_id'] ?? 0);
    $amount = round((float)($_POST['total_amount'] ?? 0), 2);
    $notes = cleanInput($_POST['notes'] ?? '');
    $splits = json_decode($_POST['payment_splits_json'] ?? '', true);

    if (!is_array($splits)) {
        $splits = [];
    }

    if ($supplierId <= 0) {
        jsonResponse(false, 'Please select supplier.');
    }

    if (!$paymentDate) {
        jsonResponse(false, 'Please select payment date.');
    }

    if (!in_array($paymentType, [1, 2, 3], true)) {
        jsonResponse(false, 'Invalid payment type.');
    }

    if ($amount <= 0) {
        jsonResponse(false, 'Payment amount must be greater than zero.');
    }

    if ($paymentType === 2 && $purchaseId <= 0) {
        jsonResponse(false, 'Please select purchase bill for individual payment.');
    }

    if ($paymentType === 3) {
        $purchaseId = 0;
    }

    spValidateSupplier($pdo, $scope, $supplierId);
    $splits = spNormalizeSplits($pdo, $scope, $splits, $amount);

    try {
        $pdo->beginTransaction();

        if ($paymentId > 0) {
            $oldPayment = spLockPayment($pdo, $scope, $paymentId);
            if (!$oldPayment) {
                throw new Exception('Payment not found.');
            }

            if (($oldPayment['source_type'] ?? '') === 'purchase_form') {
                throw new Exception('Purchase form payment cannot be edited here. Edit the purchase bill.');
            }

            spReversePayment($pdo, $scope, $paymentId);

            $stmt = $pdo->prepare("
                UPDATE supplier_payments
                SET supplier_id = :supplier_id,
                    payment_date = :payment_date,
                    payment_type = :payment_type,
                    source_type = 'supplier_payment_page',
                    source_id = NULL,
                    total_amount = :total_amount,
                    notes = :notes,
                    status = 1,
                    updated_at = NOW()
                WHERE id = :payment_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':supplier_id' => $supplierId,
                ':payment_date' => $paymentDate,
                ':payment_type' => $paymentType,
                ':total_amount' => $amount,
                ':notes' => $notes,
                ':payment_id' => $paymentId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $supplierPaymentId = $paymentId;
        } else {
            $paymentNo = spGeneratePaymentNo($pdo, $scope);

            $stmt = $pdo->prepare("
                INSERT INTO supplier_payments
                (
                    business_id, branch_id, supplier_id, payment_no, payment_date,
                    payment_type, source_type, source_id, total_amount, notes,
                    status, created_by, created_at
                )
                VALUES
                (
                    :business_id, :branch_id, :supplier_id, :payment_no, :payment_date,
                    :payment_type, 'supplier_payment_page', NULL, :total_amount, :notes,
                    1, :created_by, NOW()
                )
            ");
            $stmt->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':supplier_id' => $supplierId,
                ':payment_no' => $paymentNo,
                ':payment_date' => $paymentDate,
                ':payment_type' => $paymentType,
                ':total_amount' => $amount,
                ':notes' => $notes,
                ':created_by' => currentUserId()
            ]);

            $supplierPaymentId = (int)$pdo->lastInsertId();
        }

        spInsertSplits($pdo, $scope, $supplierPaymentId, $splits);

        if ($paymentType === 2) {
            spAllocateIndividual($pdo, $scope, $supplierPaymentId, $supplierId, $purchaseId, $amount);
        } elseif ($paymentType === 3) {
            spAllocateOpeningOnly($pdo, $scope, $supplierPaymentId, $supplierId, $amount);
        } else {
            spAllocateOverall($pdo, $scope, $supplierPaymentId, $supplierId, $amount);
        }

        spRecalcSupplierCurrentOutstanding($pdo, $scope, $supplierId);

        $pdo->commit();

        jsonResponse(true, 'Supplier payment saved successfully.', [
            'payment_id' => $supplierPaymentId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Payment save failed.');
    }
}

function spCancelPayment(PDO $pdo)
{
    $scope = spScope();
    spRequirePermission('edit');

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment.');
    }

    try {
        $pdo->beginTransaction();

        $payment = spLockPayment($pdo, $scope, $paymentId);
        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        $supplierId = (int)$payment['supplier_id'];

        spReversePayment($pdo, $scope, $paymentId);

        $stmt = $pdo->prepare("
            UPDATE supplier_payments
            SET status = 2, updated_at = NOW()
            WHERE id = :payment_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        spRecalcSupplierCurrentOutstanding($pdo, $scope, $supplierId);

        $pdo->commit();
        jsonResponse(true, 'Payment cancelled successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Cancel failed.');
    }
}

function spDeletePayment(PDO $pdo)
{
    $scope = spScope();
    spRequirePermission('delete');

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment.');
    }

    try {
        $pdo->beginTransaction();

        $payment = spLockPayment($pdo, $scope, $paymentId);
        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        $supplierId = (int)$payment['supplier_id'];

        spReversePayment($pdo, $scope, $paymentId);

        $stmt = $pdo->prepare("
            DELETE FROM supplier_payment_splits
            WHERE supplier_payment_id = :payment_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $stmt = $pdo->prepare("
            DELETE FROM supplier_payment_allocations
            WHERE supplier_payment_id = :payment_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $stmt = $pdo->prepare("
            DELETE FROM supplier_payments
            WHERE id = :payment_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        spRecalcSupplierCurrentOutstanding($pdo, $scope, $supplierId);

        $pdo->commit();
        jsonResponse(true, 'Payment deleted successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Delete failed.');
    }
}

function spNormalizeSplits(PDO $pdo, array $scope, array $splits, $amount)
{
    $clean = [];
    $total = 0;

    foreach ($splits as $split) {
        $modeId = (int)($split['payment_mode_id'] ?? 0);
        $splitAmount = round((float)($split['amount'] ?? 0), 2);
        $referenceNo = cleanInput($split['reference_no'] ?? '');

        if ($modeId <= 0 || $splitAmount <= 0) {
            continue;
        }

        spValidatePaymentMode($pdo, $scope, $modeId);

        $clean[] = [
            'payment_mode_id' => $modeId,
            'amount' => $splitAmount,
            'reference_no' => $referenceNo
        ];
        $total += $splitAmount;
    }

    $total = round($total, 2);

    if (empty($clean)) {
        jsonResponse(false, 'Please add payment split.');
    }

    if (abs($total - $amount) > 0.01) {
        jsonResponse(false, 'Payment split total must match payment amount.');
    }

    return $clean;
}

function spInsertSplits(PDO $pdo, array $scope, $paymentId, array $splits)
{
    $stmt = $pdo->prepare("
        INSERT INTO supplier_payment_splits
        (
            business_id, branch_id, supplier_payment_id,
            payment_mode_id, amount, reference_no, status, created_at
        )
        VALUES
        (
            :business_id, :branch_id, :supplier_payment_id,
            :payment_mode_id, :amount, :reference_no, 1, NOW()
        )
    ");

    foreach ($splits as $split) {
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':supplier_payment_id' => $paymentId,
            ':payment_mode_id' => $split['payment_mode_id'],
            ':amount' => $split['amount'],
            ':reference_no' => $split['reference_no']
        ]);
    }
}

function spAllocateIndividual(PDO $pdo, array $scope, $paymentId, $supplierId, $purchaseId, $amount)
{
    $purchase = spLockPurchase($pdo, $scope, $supplierId, $purchaseId);
    if (!$purchase) {
        throw new Exception('Purchase bill not found.');
    }

    $dueAmount = round((float)$purchase['due_amount'], 2);
    if ($amount > $dueAmount + 0.01) {
        throw new Exception('Payment amount cannot exceed selected purchase bill due amount.');
    }

    spInsertAllocation($pdo, $scope, $paymentId, $supplierId, 1, $purchaseId, $amount);
    spRecalcPurchase($pdo, $scope, $purchaseId);
}

function spAllocateOpeningOnly(PDO $pdo, array $scope, $paymentId, $supplierId, $amount)
{
    $summary = spCalculateSupplierBalance($pdo, $scope, $supplierId);
    $openingDue = round((float)$summary['opening_due'], 2);

    if ($openingDue <= 0) {
        throw new Exception('No opening outstanding due for this supplier.');
    }

    if ($amount > $openingDue + 0.01) {
        throw new Exception('Payment amount cannot exceed opening outstanding due.');
    }

    spInsertAllocation($pdo, $scope, $paymentId, $supplierId, 2, null, $amount);
}

function spAllocateOverall(PDO $pdo, array $scope, $paymentId, $supplierId, $amount)
{
    $remaining = round((float)$amount, 2);

    $stmt = $pdo->prepare("
        SELECT id, due_amount
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_id = :supplier_id
        AND status = 1
        AND due_amount > 0.009
        ORDER BY purchase_date ASC, id ASC
        FOR UPDATE
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $purchase) {
        if ($remaining <= 0) {
            break;
        }

        $due = round((float)$purchase['due_amount'], 2);
        $allocate = min($remaining, $due);

        if ($allocate > 0) {
            spInsertAllocation($pdo, $scope, $paymentId, $supplierId, 1, (int)$purchase['id'], $allocate);
            spRecalcPurchase($pdo, $scope, (int)$purchase['id']);
            $remaining = round($remaining - $allocate, 2);
        }
    }

    if ($remaining > 0) {
        $summary = spCalculateSupplierBalance($pdo, $scope, $supplierId);
        $openingDue = round((float)$summary['opening_due'], 2);
        $openingAllocate = min($remaining, $openingDue);

        if ($openingAllocate > 0) {
            spInsertAllocation($pdo, $scope, $paymentId, $supplierId, 2, null, $openingAllocate);
            $remaining = round($remaining - $openingAllocate, 2);
        }
    }

    if ($remaining > 0.01) {
        throw new Exception('Payment amount is more than supplier payable balance.');
    }
}

function spReversePayment(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT purchase_id
        FROM supplier_payment_allocations
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_payment_id = :payment_id
        AND allocation_type = 1
        AND purchase_id IS NOT NULL
        AND status = 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId
    ]);

    $purchaseIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare("
        UPDATE supplier_payment_allocations
        SET status = 2
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_payment_id = :payment_id
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId
    ]);

    $stmt = $pdo->prepare("
        UPDATE supplier_payment_splits
        SET status = 2
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_payment_id = :payment_id
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId
    ]);

    foreach (array_unique($purchaseIds) as $purchaseId) {
        spRecalcPurchase($pdo, $scope, $purchaseId);
    }
}

function spInsertAllocation(PDO $pdo, array $scope, $paymentId, $supplierId, $allocationType, $purchaseId, $amount)
{
    $stmt = $pdo->prepare("
        INSERT INTO supplier_payment_allocations
        (
            business_id, branch_id, supplier_payment_id, supplier_id,
            allocation_type, purchase_id, amount, status, created_at
        )
        VALUES
        (
            :business_id, :branch_id, :supplier_payment_id, :supplier_id,
            :allocation_type, :purchase_id, :amount, 1, NOW()
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_payment_id' => $paymentId,
        ':supplier_id' => $supplierId,
        ':allocation_type' => $allocationType,
        ':purchase_id' => $purchaseId,
        ':amount' => $amount
    ]);
}

function spRecalcPurchase(PDO $pdo, array $scope, $purchaseId)
{
    $stmt = $pdo->prepare("
        SELECT grand_total
        FROM purchases
        WHERE id = :purchase_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':purchase_id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $grandTotal = round((float)$stmt->fetchColumn(), 2);

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM supplier_payment_allocations
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND purchase_id = :purchase_id
        AND allocation_type = 1
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':purchase_id' => $purchaseId
    ]);

    $paidAmount = round((float)$stmt->fetchColumn(), 2);
    if ($paidAmount > $grandTotal) {
        $paidAmount = $grandTotal;
    }

    $dueAmount = round($grandTotal - $paidAmount, 2);

    if ($paidAmount <= 0) {
        $paymentStatus = 1;
    } elseif ($dueAmount > 0) {
        $paymentStatus = 2;
    } else {
        $paymentStatus = 3;
    }

    $stmt = $pdo->prepare("
        UPDATE purchases
        SET paid_amount = :paid_amount,
            due_amount = :due_amount,
            payment_status = :payment_status
        WHERE id = :purchase_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $stmt->execute([
        ':paid_amount' => $paidAmount,
        ':due_amount' => $dueAmount,
        ':payment_status' => $paymentStatus,
        ':purchase_id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function spCalculateSupplierBalance(PDO $pdo, array $scope, $supplierId)
{
    $stmt = $pdo->prepare("
        SELECT
            supplier_name,
            COALESCE(opening_outstanding, 0) AS opening_outstanding,
            COALESCE(current_outstanding, 0) AS current_outstanding
        FROM suppliers
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        jsonResponse(false, 'Supplier not found.');
    }

    $openingOutstanding = round((float)$supplier['opening_outstanding'], 2);

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM supplier_payment_allocations
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_id = :supplier_id
        AND allocation_type = 2
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ]);

    $openingPaid = round((float)$stmt->fetchColumn(), 2);
    $openingDue = max(0, round($openingOutstanding - $openingPaid, 2));

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(grand_total), 0) AS purchase_total,
            COALESCE(SUM(paid_amount), 0) AS purchase_paid,
            COALESCE(SUM(due_amount), 0) AS purchase_due
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND supplier_id = :supplier_id
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':supplier_id' => $supplierId
    ]);

    $purchase = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $purchaseDue = round((float)($purchase['purchase_due'] ?? 0), 2);

    return [
        'supplier_id' => $supplierId,
        'supplier_name' => $supplier['supplier_name'],
        'opening_outstanding' => $openingOutstanding,
        'opening_paid' => $openingPaid,
        'opening_due' => $openingDue,
        'purchase_total' => round((float)($purchase['purchase_total'] ?? 0), 2),
        'purchase_paid' => round((float)($purchase['purchase_paid'] ?? 0), 2),
        'purchase_due' => $purchaseDue,
        'total_payable' => round($openingDue + $purchaseDue, 2),
        'current_outstanding' => round((float)$supplier['current_outstanding'], 2)
    ];
}

function spRecalcSupplierCurrentOutstanding(PDO $pdo, array $scope, $supplierId)
{
    $summary = spCalculateSupplierBalance($pdo, $scope, $supplierId);
    $currentOutstanding = round((float)$summary['total_payable'], 2);

    $stmt = $pdo->prepare("
        UPDATE suppliers
        SET current_outstanding = :current_outstanding
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $stmt->execute([
        ':current_outstanding' => $currentOutstanding,
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function spValidateSupplier(PDO $pdo, array $scope, $supplierId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM suppliers
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid supplier selected.');
    }
}

function spValidatePaymentMode(PDO $pdo, array $scope, $modeId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM payment_modes
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $modeId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid payment mode selected.');
    }
}

function spLockPayment(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM supplier_payments
        WHERE id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function spLockPurchase(PDO $pdo, array $scope, $supplierId, $purchaseId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM purchases
        WHERE id = :purchase_id
        AND supplier_id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':purchase_id' => $purchaseId,
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function spGeneratePaymentNo(PDO $pdo, array $scope)
{
    $prefix = 'SP-' . date('Ym') . '-';

    $stmt = $pdo->prepare("
        SELECT payment_no
        FROM supplier_payments
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND payment_no LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':prefix' => $prefix . '%'
    ]);

    $lastNo = (string)$stmt->fetchColumn();
    $next = 1;

    if ($lastNo !== '') {
        $parts = explode('-', $lastNo);
        $next = ((int)end($parts)) + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function spCleanDate($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}
